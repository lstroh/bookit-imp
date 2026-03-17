<?php
/**
 * Tests for Service Meeting Fields in Dashboard Services API.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test meeting field support in dashboard service endpoints.
 */
class Test_Meetings_Service_API extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->ensure_meeting_columns();

		bookit_test_truncate_tables(
			array(
				'bookings_staff',
				'bookings_services',
				'bookings_categories',
				'bookings_service_categories',
			)
		);

		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		bookit_test_truncate_tables(
			array(
				'bookings_staff',
				'bookings_services',
				'bookings_categories',
				'bookings_service_categories',
			)
		);

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * GET /dashboard/services list includes meeting fields.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_services_list
	 */
	public function test_get_services_list_includes_meeting_fields() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$this->create_test_service(
			array(
				'meeting_type'         => 'online',
				'preferred_platform'   => 'zoom',
				'default_meeting_link' => 'https://zoom.us/j/123',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/services/list' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertNotEmpty( $data['services'] );
		$this->assertArrayHasKey( 'meeting_type', $data['services'][0] );
		$this->assertArrayHasKey( 'preferred_platform', $data['services'][0] );
		$this->assertArrayHasKey( 'default_meeting_link', $data['services'][0] );
	}

	/**
	 * GET /dashboard/services/{id} includes meeting fields.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_service_details
	 */
	public function test_get_single_service_includes_meeting_fields() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$service_id = $this->create_test_service(
			array(
				'meeting_type'         => 'online',
				'preferred_platform'   => 'teams',
				'default_meeting_link' => 'https://teams.microsoft.com/l/meetup-join/test',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/services/' . $service_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'meeting_type', $data['service'] );
		$this->assertArrayHasKey( 'preferred_platform', $data['service'] );
		$this->assertArrayHasKey( 'default_meeting_link', $data['service'] );
		$this->assertEquals( 'online', $data['service']['meeting_type'] );
		$this->assertEquals( 'teams', $data['service']['preferred_platform'] );
	}

	/**
	 * POST /dashboard/services/create defaults meeting_type to none.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::create_service
	 */
	public function test_create_service_with_meeting_type_none_defaults() {
		global $wpdb;

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/services/create' );
		$request->set_body_params(
			array(
				'name'          => 'Create Default Service',
				'description'   => 'Service without explicit meeting config',
				'duration'      => 45,
				'price'         => 35.00,
				'deposit_type'  => 'fixed',
				'buffer_before' => 0,
				'buffer_after'  => 0,
				'category_ids'  => array(),
				'is_active'     => true,
				'display_order' => 0,
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );

		$service_id = (int) $data['service']['id'];
		$stored     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meeting_type FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				$service_id
			),
			ARRAY_A
		);

		$this->assertEquals( 'none', $stored['meeting_type'] );
	}

	/**
	 * PUT /dashboard/services/{id} stores meeting fields when online.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_service
	 */
	public function test_patch_service_meeting_type_online_saves_all_fields() {
		global $wpdb;

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$service_id = $this->create_test_service();
		$request    = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/services/' . $service_id );
		$request->set_body_params(
			$this->build_update_payload(
				array(
					'meeting_type'         => 'online',
					'preferred_platform'   => 'zoom',
					'default_meeting_link' => 'https://zoom.us/j/123',
				)
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$stored = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meeting_type, preferred_platform, default_meeting_link
				FROM {$wpdb->prefix}bookings_services
				WHERE id = %d",
				$service_id
			),
			ARRAY_A
		);

		$this->assertEquals( 'online', $stored['meeting_type'] );
		$this->assertEquals( 'zoom', $stored['preferred_platform'] );
		$this->assertEquals( 'https://zoom.us/j/123', $stored['default_meeting_link'] );
	}

	/**
	 * PUT /dashboard/services/{id} clears platform/link when meeting_type is none.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_service
	 */
	public function test_patch_service_meeting_type_none_clears_platform_and_link() {
		global $wpdb;

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$service_id = $this->create_test_service(
			array(
				'meeting_type'         => 'online',
				'preferred_platform'   => 'google_meet',
				'default_meeting_link' => 'https://meet.google.com/abc-defg-hij',
			)
		);

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/services/' . $service_id );
		$request->set_body_params(
			$this->build_update_payload(
				array(
					'meeting_type'         => 'none',
					'preferred_platform'   => 'zoom',
					'default_meeting_link' => 'https://zoom.us/j/should-clear',
				)
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		$stored = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meeting_type, preferred_platform, default_meeting_link
				FROM {$wpdb->prefix}bookings_services
				WHERE id = %d",
				$service_id
			),
			ARRAY_A
		);

		$this->assertEquals( 'none', $stored['meeting_type'] );
		$this->assertNull( $stored['preferred_platform'] );
		$this->assertNull( $stored['default_meeting_link'] );
	}

	/**
	 * PUT /dashboard/services/{id} rejects invalid meeting type.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::register_routes
	 */
	public function test_patch_service_invalid_meeting_type_returns_400() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$service_id = $this->create_test_service();
		$request    = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/services/' . $service_id );
		$request->set_body_params(
			$this->build_update_payload(
				array(
					'meeting_type' => 'teleport',
				)
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * PUT /dashboard/services/{id} rejects invalid preferred platform.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::register_routes
	 */
	public function test_patch_service_invalid_platform_returns_400() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$service_id = $this->create_test_service();
		$request    = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/services/' . $service_id );
		$request->set_body_params(
			$this->build_update_payload(
				array(
					'meeting_type'       => 'online',
					'preferred_platform' => 'skype',
				)
			)
		);

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     'admin' or 'staff'.
	 * @return void
	 */
	private function login_as( $staff_id, $role = 'staff' ) {
		global $wpdb;

		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);

		$_SESSION['staff_id']      = (int) $staff['id'];
		$_SESSION['staff_email']   = $staff['email'];
		$_SESSION['staff_role']    = $role;
		$_SESSION['staff_name']    = trim( $staff['first_name'] . ' ' . $staff['last_name'] );
		$_SESSION['is_logged_in']  = true;
		$_SESSION['last_activity'] = time();
	}

	/**
	 * Create test staff row.
	 *
	 * @param array $args Field overrides.
	 * @return int
	 */
	private function create_test_staff( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'              => 'staff-' . wp_generate_password( 6, false ) . '@test.com',
			'password_hash'      => password_hash( 'password123', PASSWORD_BCRYPT ),
			'first_name'         => 'Test',
			'last_name'          => 'Staff',
			'phone'              => '07700900000',
			'photo_url'          => null,
			'bio'                => 'Test bio',
			'title'              => 'Therapist',
			'role'               => 'staff',
			'google_calendar_id' => null,
			'is_active'          => 1,
			'display_order'      => 0,
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
			'deleted_at'         => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Create test service row.
	 *
	 * @param array $args Field overrides.
	 * @return int
	 */
	private function create_test_service( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'name'                 => 'Test Service ' . wp_generate_password( 4, false ),
			'description'          => 'Test service description',
			'duration'             => 60,
			'price'                => 50.00,
			'deposit_amount'       => 10.00,
			'deposit_type'         => 'fixed',
			'buffer_before'        => 0,
			'buffer_after'         => 0,
			'meeting_type'         => 'none',
			'preferred_platform'   => null,
			'default_meeting_link' => null,
			'is_active'            => 1,
			'display_order'        => 0,
			'created_at'           => current_time( 'mysql' ),
			'updated_at'           => current_time( 'mysql' ),
			'deleted_at'           => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			$data,
			array( '%s', '%s', '%d', '%f', '%f', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Build a valid PUT /dashboard/services/{id} payload.
	 *
	 * @param array $overrides Override values.
	 * @return array
	 */
	private function build_update_payload( $overrides = array() ) {
		$base = array(
			'name'                => 'Updated Service',
			'description'         => 'Updated description',
			'duration'            => 75,
			'price'               => 60.00,
			'deposit_amount'      => 15.00,
			'deposit_type'        => 'fixed',
			'buffer_before'       => 5,
			'buffer_after'        => 5,
			'category_ids'        => array(),
			'is_active'           => true,
			'display_order'       => 1,
			'meeting_type'        => 'none',
			'preferred_platform'  => null,
			'default_meeting_link' => null,
		);

		return array_merge( $base, $overrides );
	}

	/**
	 * Ensure meetings columns exist in the test database.
	 *
	 * @return void
	 */
	private function ensure_meeting_columns() {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_services';

		$meeting_type_exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'meeting_type' ) );
		if ( ! $meeting_type_exists ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN meeting_type VARCHAR(20) NOT NULL DEFAULT 'none'" );
		}

		$preferred_platform_exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'preferred_platform' ) );
		if ( ! $preferred_platform_exists ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN preferred_platform VARCHAR(50) NULL DEFAULT NULL" );
		}

		$default_meeting_link_exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'default_meeting_link' ) );
		if ( ! $default_meeting_link_exists ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN default_meeting_link VARCHAR(2048) NULL DEFAULT NULL" );
		}
	}
}
