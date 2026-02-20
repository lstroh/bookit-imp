<?php
/**
 * Tests for Dashboard Bookings API (Sprint 3)
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Dashboard_Bookings_API REST endpoints.
 */
class Test_Dashboard_Bookings_API extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Cached test data IDs.
	 *
	 * @var array
	 */
	private $test_data = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_customers" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_payments" );

		$_SESSION = array();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_customers" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff_services" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_payments" );

		$_SESSION = array();

		parent::tearDown();
	}

	// ========== TESTS FOR: GET /dashboard/bookings/today ==========

	/**
	 * Test admin can view all today's bookings.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_todays_bookings
	 */
	public function test_admin_can_view_all_todays_bookings() {
		$today   = current_time( 'Y-m-d' );
		$staff_a = $this->create_test_staff( array( 'first_name' => 'Alice', 'role' => 'staff' ) );
		$staff_b = $this->create_test_staff( array( 'first_name' => 'Bob', 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$booking1 = $this->create_test_booking( array(
			'staff_id'     => $staff_a,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => $today,
			'start_time'   => '09:00:00',
			'end_time'     => '10:00:00',
			'status'       => 'confirmed',
		) );

		$booking2 = $this->create_test_booking( array(
			'staff_id'     => $staff_b,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => $today,
			'start_time'   => '14:00:00',
			'end_time'     => '15:00:00',
			'status'       => 'pending',
		) );

		$admin = $this->create_test_staff( array( 'role' => 'admin', 'first_name' => 'Admin' ) );
		$this->login_as( $admin, 'admin' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/bookings/today' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( $today, $data['date'] );
		$this->assertCount( 2, $data['bookings'] );
		$this->assertEquals( $booking1, $data['bookings'][0]['id'] );
		$this->assertEquals( $booking2, $data['bookings'][1]['id'] );
	}

	/**
	 * Test staff can only view own bookings for today.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_todays_bookings
	 */
	public function test_staff_can_only_view_own_todays_bookings() {
		$today   = current_time( 'Y-m-d' );
		$staff_a = $this->create_test_staff( array( 'first_name' => 'Alice', 'role' => 'staff' ) );
		$staff_b = $this->create_test_staff( array( 'first_name' => 'Bob', 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$booking_alice = $this->create_test_booking( array(
			'staff_id'     => $staff_a,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => $today,
			'start_time'   => '09:00:00',
			'end_time'     => '10:00:00',
		) );

		$this->create_test_booking( array(
			'staff_id'     => $staff_b,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => $today,
			'start_time'   => '14:00:00',
			'end_time'     => '15:00:00',
		) );

		$this->login_as( $staff_a, 'staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/bookings/today' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertCount( 1, $data['bookings'], 'Staff should only see own bookings' );
		$this->assertEquals( $booking_alice, $data['bookings'][0]['id'] );
	}

	/**
	 * Test today endpoint excludes bookings from other dates.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_todays_bookings
	 */
	public function test_today_endpoint_only_returns_today() {
		$today    = current_time( 'Y-m-d' );
		$tomorrow = gmdate( 'Y-m-d', strtotime( $today . ' +1 day' ) );
		$staff    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$today_booking = $this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => $today,
			'start_time'   => '09:00:00',
			'end_time'     => '10:00:00',
		) );

		$this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => $tomorrow,
			'start_time'   => '09:00:00',
			'end_time'     => '10:00:00',
		) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/bookings/today' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertCount( 1, $data['bookings'] );
		$this->assertEquals( $today_booking, $data['bookings'][0]['id'] );
	}

	/**
	 * Test unauthenticated user cannot access today's bookings.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::check_dashboard_permission
	 */
	public function test_unauthenticated_cannot_view_todays_bookings() {
		$_SESSION = array();

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/bookings/today' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 401, $response->get_status() );
	}

	// ========== TESTS FOR: GET /dashboard/bookings (with filters) ==========

	/**
	 * Test get all bookings without filters.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_all_bookings
	 */
	public function test_get_all_bookings_no_filters() {
		$staff    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => '2026-05-15',
			'start_time'   => '09:00:00',
			'end_time'     => '10:00:00',
		) );

		$this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => '2026-05-20',
			'start_time'   => '14:00:00',
			'end_time'     => '15:00:00',
		) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/bookings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertGreaterThanOrEqual( 2, count( $data['bookings'] ) );
		$this->assertArrayHasKey( 'pagination', $data );
		$this->assertArrayHasKey( 'total', $data['pagination'] );
	}

	/**
	 * Test filter bookings by status.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_all_bookings
	 */
	public function test_filter_bookings_by_status() {
		$staff    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'status'       => 'confirmed',
			'booking_date' => '2026-06-10',
			'start_time'   => '09:00:00',
			'end_time'     => '10:00:00',
		) );

		$this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'status'       => 'pending',
			'booking_date' => '2026-06-11',
			'start_time'   => '09:00:00',
			'end_time'     => '10:00:00',
		) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/bookings' );
		$request->set_param( 'status', 'confirmed' );

		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		foreach ( $data['bookings'] as $booking ) {
			$this->assertEquals( 'confirmed', $booking['status'] );
		}
	}

	/**
	 * Test filter bookings by date range.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_all_bookings
	 */
	public function test_filter_bookings_by_date_range() {
		$staff    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => '2026-05-15',
			'start_time'   => '09:00:00',
			'end_time'     => '10:00:00',
		) );

		$this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => '2026-06-15',
			'start_time'   => '09:00:00',
			'end_time'     => '10:00:00',
		) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/bookings' );
		$request->set_param( 'date_from', '2026-05-01' );
		$request->set_param( 'date_to', '2026-05-31' );

		$response = rest_get_server()->dispatch( $request );

		$data  = $response->get_data();
		$dates = array_column( $data['bookings'], 'booking_date' );
		$this->assertContains( '2026-05-15', $dates );
		$this->assertNotContains( '2026-06-15', $dates );
	}

	/**
	 * Test staff only sees own bookings in all-bookings endpoint.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::get_all_bookings
	 */
	public function test_staff_only_sees_own_bookings_in_list() {
		$staff_a  = $this->create_test_staff( array( 'first_name' => 'Alice', 'role' => 'staff' ) );
		$staff_b  = $this->create_test_staff( array( 'first_name' => 'Bob', 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$this->create_test_booking( array(
			'staff_id'     => $staff_a,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => '2026-06-10',
			'start_time'   => '09:00:00',
			'end_time'     => '10:00:00',
		) );

		$this->create_test_booking( array(
			'staff_id'     => $staff_b,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => '2026-06-11',
			'start_time'   => '09:00:00',
			'end_time'     => '10:00:00',
		) );

		$this->login_as( $staff_a, 'staff' );

		$request  = new WP_REST_Request( 'GET', '/' . $this->namespace . '/dashboard/bookings' );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();
		$this->assertCount( 1, $data['bookings'] );
		$this->assertEquals( (int) $staff_a, $data['bookings'][0]['staff_id'] );
	}

	// ========== TESTS FOR: POST /dashboard/bookings/create ==========

	/**
	 * Test create manual booking with valid data.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::create_manual_booking
	 */
	public function test_create_manual_booking_success() {
		$staff    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$service  = $this->create_test_service( array( 'duration' => 60 ) );
		$customer = $this->create_test_customer();

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/bookings/create' );
		$request->set_body_params( array(
			'staff_id'          => $staff,
			'service_id'        => $service,
			'customer_id'       => $customer,
			'booking_date'      => '2026-06-15',
			'booking_time'      => '10:00',
			'payment_method'    => 'cash',
			'amount_paid'       => 50,
			'send_confirmation' => false,
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'booking_id', $data );
		$this->assertArrayHasKey( 'booking', $data );
		$this->assertGreaterThan( 0, $data['booking_id'] );
	}

	/**
	 * Test create booking rejects missing required fields.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::create_manual_booking
	 */
	public function test_create_booking_rejects_missing_required_fields() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/bookings/create' );
		$request->set_body_params( array(
			'booking_date' => '2026-06-15',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test create booking rejects conflicting time slot.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::create_manual_booking
	 */
	public function test_create_booking_rejects_conflict() {
		$staff    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$service  = $this->create_test_service( array( 'duration' => 60 ) );
		$customer = $this->create_test_customer();

		$this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => '2026-06-15',
			'start_time'   => '10:00:00',
			'end_time'     => '11:00:00',
			'status'       => 'confirmed',
		) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/bookings/create' );
		$request->set_body_params( array(
			'staff_id'          => $staff,
			'service_id'        => $service,
			'customer_id'       => $customer,
			'booking_date'      => '2026-06-15',
			'booking_time'      => '10:30',
			'payment_method'    => 'cash',
			'send_confirmation' => false,
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$error = $response->as_error();
		$this->assertEquals( 'slot_unavailable', $error->get_error_code() );
	}

	// ========== TESTS FOR: PUT /dashboard/bookings/{id} (update) ==========

	/**
	 * Test update booking status.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_booking
	 */
	public function test_update_booking_status() {
		$staff    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$booking_id = $this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => '2026-06-15',
			'start_time'   => '10:00:00',
			'end_time'     => '11:00:00',
			'status'       => 'pending',
		) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params( array(
			'service_id'        => $service,
			'staff_id'          => $staff,
			'booking_date'      => '2026-06-15',
			'booking_time'      => '10:00:00',
			'status'            => 'confirmed',
			'payment_method'    => 'cash',
			'amount_paid'       => 50,
			'send_notification' => false,
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 'confirmed', $data['booking']['status'] );
	}

	/**
	 * Test staff cannot update other staff's bookings.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_booking
	 */
	public function test_staff_cannot_update_others_bookings() {
		$staff_a  = $this->create_test_staff( array( 'first_name' => 'Alice', 'role' => 'staff' ) );
		$staff_b  = $this->create_test_staff( array( 'first_name' => 'Bob', 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$booking_id = $this->create_test_booking( array(
			'staff_id'     => $staff_b,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'booking_date' => '2026-06-15',
			'start_time'   => '10:00:00',
			'end_time'     => '11:00:00',
		) );

		$this->login_as( $staff_a, 'staff' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id );
		$request->set_body_params( array(
			'service_id'        => $service,
			'staff_id'          => $staff_b,
			'booking_date'      => '2026-06-15',
			'booking_time'      => '10:00:00',
			'status'            => 'cancelled',
			'payment_method'    => 'cash',
			'send_notification' => false,
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test update non-existent booking returns 404.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::update_booking
	 */
	public function test_update_nonexistent_booking_returns_404() {
		$staff   = $this->create_test_staff( array( 'role' => 'staff' ) );
		$service = $this->create_test_service();

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request = new WP_REST_Request( 'PUT', '/' . $this->namespace . '/dashboard/bookings/99999' );
		$request->set_body_params( array(
			'service_id'     => $service,
			'staff_id'       => $staff,
			'booking_date'   => '2026-06-15',
			'booking_time'   => '10:00:00',
			'status'         => 'confirmed',
			'payment_method' => 'cash',
		) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 404, $response->get_status() );
	}

	// ========== TESTS FOR: POST /dashboard/bookings/{id}/complete ==========

	/**
	 * Test admin can mark booking complete.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::mark_booking_complete
	 */
	public function test_admin_can_mark_booking_complete() {
		$staff    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$booking_id = $this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'status'       => 'confirmed',
		) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id . '/complete' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( $booking_id, $data['booking_id'] );

		global $wpdb;
		$status = $wpdb->get_var( $wpdb->prepare(
			"SELECT status FROM {$wpdb->prefix}bookings WHERE id = %d",
			$booking_id
		) );
		$this->assertEquals( 'completed', $status );
	}

	/**
	 * Test staff can mark own booking complete.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::mark_booking_complete
	 */
	public function test_staff_can_mark_own_booking_complete() {
		$staff    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$booking_id = $this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'status'       => 'confirmed',
		) );

		$this->login_as( $staff, 'staff' );

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id . '/complete' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * Test staff cannot mark other staff's booking complete.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::mark_booking_complete
	 */
	public function test_staff_cannot_mark_others_booking_complete() {
		$staff_a  = $this->create_test_staff( array( 'first_name' => 'Alice', 'role' => 'staff' ) );
		$staff_b  = $this->create_test_staff( array( 'first_name' => 'Bob', 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$booking_id = $this->create_test_booking( array(
			'staff_id'     => $staff_b,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'status'       => 'confirmed',
		) );

		$this->login_as( $staff_a, 'staff' );

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id . '/complete' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test marking already-completed booking returns error.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::mark_booking_complete
	 */
	public function test_mark_already_completed_booking_returns_error() {
		$staff    = $this->create_test_staff( array( 'role' => 'staff' ) );
		$service  = $this->create_test_service();
		$customer = $this->create_test_customer();

		$booking_id = $this->create_test_booking( array(
			'staff_id'     => $staff,
			'service_id'   => $service,
			'customer_id'  => $customer,
			'status'       => 'completed',
		) );

		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/bookings/' . $booking_id . '/complete' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$error = $response->as_error();
		$this->assertEquals( 'already_completed', $error->get_error_code() );
	}

	/**
	 * Test mark complete on non-existent booking returns 404.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::mark_booking_complete
	 */
	public function test_mark_complete_nonexistent_booking_returns_404() {
		$admin = $this->create_test_staff( array( 'role' => 'admin' ) );
		$this->login_as( $admin, 'admin' );

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/bookings/99999/complete' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 404, $response->get_status() );
	}

	// ========== TESTS FOR: Route registration ==========

	/**
	 * Test all dashboard booking endpoints are registered.
	 *
	 * @covers Bookit_Dashboard_Bookings_API::register_routes
	 */
	public function test_dashboard_booking_endpoints_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/bookings/today', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/bookings', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/bookings/create', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/bookings/(?P<id>\\d+)/complete', $routes );
		$this->assertArrayHasKey( '/' . $this->namespace . '/dashboard/bookings/(?P<id>\\d+)', $routes );
	}

	// ========== HELPER METHODS ==========

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * Sets the $_SESSION values that Bookit_Auth::is_logged_in() and
	 * Bookit_Auth::get_current_staff() check.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     'admin' or 'staff'.
	 */
	private function login_as( $staff_id, $role = 'staff' ) {
		global $wpdb;

		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name, role FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
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
	 * Create test staff member.
	 *
	 * @param array $args Override defaults.
	 * @return int Staff ID.
	 */
	private function create_test_staff( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'              => 'staff-' . wp_generate_password( 6, false ) . '@test.com',
			'password_hash'      => wp_hash_password( 'password123' ),
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
	 * Create test service.
	 *
	 * @param array $args Override defaults.
	 * @return int Service ID.
	 */
	private function create_test_service( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'name'           => 'Test Service ' . wp_generate_password( 4, false ),
			'description'    => 'Test service description',
			'duration'       => 60,
			'price'          => 50.00,
			'deposit_amount' => 10.00,
			'deposit_type'   => 'fixed',
			'buffer_before'  => 0,
			'buffer_after'   => 0,
			'is_active'      => 1,
			'display_order'  => 0,
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
			'deleted_at'     => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			$data,
			array( '%s', '%s', '%d', '%f', '%f', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Create test customer.
	 *
	 * @param array $args Override defaults.
	 * @return int Customer ID.
	 */
	private function create_test_customer( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'      => 'customer-' . wp_generate_password( 6, false ) . '@test.com',
			'first_name' => 'Test',
			'last_name'  => 'Customer',
			'phone'      => '07700900000',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Create test booking via direct DB insert.
	 *
	 * @param array $args Override defaults.
	 * @return int Booking ID.
	 */
	private function create_test_booking( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'customer_id'     => 0,
			'service_id'      => 0,
			'staff_id'        => 0,
			'booking_date'    => '2026-06-15',
			'start_time'      => '10:00:00',
			'end_time'        => '11:00:00',
			'duration'        => 60,
			'status'          => 'confirmed',
			'total_price'     => 50.00,
			'deposit_paid'    => 0.00,
			'balance_due'     => 50.00,
			'full_amount_paid' => 0,
			'payment_method'  => 'cash',
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert( $wpdb->prefix . 'bookings', $data );
		return (int) $wpdb->insert_id;
	}

	/**
	 * Link staff to service.
	 *
	 * @param int        $staff_id   Staff ID.
	 * @param int        $service_id Service ID.
	 * @param float|null $custom_price Custom price or null.
	 */
	private function link_staff_to_service( $staff_id, $service_id, $custom_price = null ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_services',
			array(
				'staff_id'     => $staff_id,
				'service_id'   => $service_id,
				'custom_price' => $custom_price,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', $custom_price === null ? '%s' : '%f', '%s' )
		);
	}
}
