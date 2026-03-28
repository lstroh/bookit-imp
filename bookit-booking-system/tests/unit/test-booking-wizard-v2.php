<?php
/**
 * Tests for Bookit Wizard V2 shortcode and assets.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit Wizard V2 scaffolding.
 */
class Test_Booking_Wizard_V2 extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		bookit_test_truncate_tables(
			array(
				'bookings_staff_services',
				'bookings_service_categories',
				'bookings_categories',
				'bookings_services',
				'bookings_staff',
			)
		);
		Bookit_Session_Manager::clear();
		wp_dequeue_style( 'bookit-wizard-v2' );
		wp_deregister_style( 'bookit-wizard-v2' );
		wp_dequeue_script( 'bookit-wizard-v2' );
		wp_deregister_script( 'bookit-wizard-v2' );
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		Bookit_Session_Manager::clear();
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}
		if ( isset( $_SESSION ) ) {
			$_SESSION = array();
		}
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Shortcodes::__construct
	 */
	public function test_v2_shortcode_is_registered() {
		$this->assertTrue( shortcode_exists( 'bookit_wizard_v2' ) );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_wizard_v2
	 */
	public function test_v2_shortcode_renders_wizard_container() {
		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertStringContainsString( 'bookit-v2-wizard-container', $output );
	}

	/**
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_v2_shortcode_does_not_break_existing_wizard() {
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'bookit-wizard-container', $output );
	}

	/**
	 * @covers Bookit_Shortcodes::enqueue_wizard_assets
	 */
	public function test_v2_css_enqueued_on_page_with_v2_shortcode() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Wizard V2 Page',
				'post_status'  => 'publish',
				'post_content' => '[bookit_wizard_v2]',
			)
		);
		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		do_action( 'wp_enqueue_scripts' );

		$styles = wp_styles();
		$this->assertTrue( in_array( 'bookit-wizard-v2', $styles->queue, true ), 'bookit-wizard-v2 CSS should be enqueued' );

		wp_reset_postdata();
	}

	/**
	 * @covers Bookit_Shortcodes::enqueue_wizard_assets
	 */
	public function test_v2_css_not_enqueued_on_page_without_v2_shortcode() {
		wp_dequeue_style( 'bookit-wizard-v2' );
		wp_deregister_style( 'bookit-wizard-v2' );
		wp_dequeue_style( 'bookit-wizard' );
		wp_dequeue_script( 'bookit-wizard' );
		wp_deregister_style( 'bookit-wizard' );
		wp_deregister_script( 'bookit-wizard' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'No Wizard V2 Page',
				'post_status'  => 'publish',
				'post_content' => 'Just some unrelated content.',
			)
		);
		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		do_action( 'wp_enqueue_scripts' );

		$styles = wp_styles();
		$this->assertFalse( in_array( 'bookit-wizard-v2', $styles->queue, true ), 'bookit-wizard-v2 CSS should not be enqueued' );

		wp_reset_postdata();
	}

	/**
	 * @covers Bookit_Shortcodes::enqueue_wizard_assets
	 */
	public function test_v2_css_not_enqueued_on_page_with_only_v1_shortcode() {
		wp_dequeue_style( 'bookit-wizard-v2' );
		wp_deregister_style( 'bookit-wizard-v2' );
		wp_dequeue_style( 'bookit-wizard' );
		wp_dequeue_script( 'bookit-wizard' );
		wp_deregister_style( 'bookit-wizard' );
		wp_deregister_script( 'bookit-wizard' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'V1 Only Page',
				'post_status'  => 'publish',
				'post_content' => '[bookit_booking_wizard]',
			)
		);
		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		do_action( 'wp_enqueue_scripts' );

		$styles = wp_styles();
		$this->assertFalse( in_array( 'bookit-wizard-v2', $styles->queue, true ), 'bookit-wizard-v2 CSS should not be enqueued on v1-only page' );

		wp_reset_postdata();
	}

	/**
	 * @covers Bookit_Shortcodes::enqueue_wizard_assets
	 */
	public function test_v2_and_v1_can_coexist_on_same_page() {
		wp_dequeue_style( 'bookit-wizard-v2' );
		wp_deregister_style( 'bookit-wizard-v2' );
		wp_dequeue_style( 'bookit-wizard' );
		wp_dequeue_script( 'bookit-wizard' );
		wp_deregister_style( 'bookit-wizard' );
		wp_deregister_script( 'bookit-wizard' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'V1 and V2 Page',
				'post_status'  => 'publish',
				'post_content' => '[bookit_booking_wizard] [bookit_wizard_v2]',
			)
		);
		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		do_action( 'wp_enqueue_scripts' );

		$styles = wp_styles();
		$this->assertTrue( in_array( 'bookit-wizard', $styles->queue, true ), 'bookit-wizard CSS should be enqueued' );
		$this->assertTrue( in_array( 'bookit-wizard-v2', $styles->queue, true ), 'bookit-wizard-v2 CSS should be enqueued' );

		wp_reset_postdata();
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step1_renders_service_cards() {
		$category_id = $this->create_test_category();
		$service_id  = $this->create_test_service( array( 'name' => 'Swedish Massage' ) );
		$this->link_service_to_category( $service_id, $category_id );
		$staff_id = $this->create_test_staff( array( 'first_name' => 'Alice' ) );
		$this->link_staff_to_service( $staff_id, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 1 );

		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertStringContainsString( 'bookit-v2-service-card', $output );
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step1_single_service_auto_skips_to_step2() {
		add_filter( 'wp_redirect', '__return_false', 999 );

		$category_id = $this->create_test_category();
		$service_id  = $this->create_test_service( array( 'name' => 'Only Service' ) );
		$this->link_service_to_category( $service_id, $category_id );
		$staff_id = $this->create_test_staff( array( 'first_name' => 'Bob' ) );
		$this->link_staff_to_service( $staff_id, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 1 );

		do_shortcode( '[bookit_wizard_v2]' );

		remove_filter( 'wp_redirect', '__return_false', 999 );

		$this->assertSame( 2, (int) Bookit_Session_Manager::get( 'current_step' ) );
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step1_few_services_adds_few_class() {
		$category_id = $this->create_test_category();
		$s1          = $this->create_test_service( array( 'name' => 'Service A' ) );
		$s2          = $this->create_test_service( array( 'name' => 'Service B' ) );
		$this->link_service_to_category( $s1, $category_id );
		$this->link_service_to_category( $s2, $category_id );
		$staff_id = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $s1 );
		$this->link_staff_to_service( $staff_id, $s2 );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 1 );

		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertStringContainsString( 'bookit-v2-services-grid--few', $output );
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step1_three_or_more_services_no_few_class() {
		$category_id = $this->create_test_category();
		$s1          = $this->create_test_service( array( 'name' => 'S1' ) );
		$s2          = $this->create_test_service( array( 'name' => 'S2' ) );
		$s3          = $this->create_test_service( array( 'name' => 'S3' ) );
		$this->link_service_to_category( $s1, $category_id );
		$this->link_service_to_category( $s2, $category_id );
		$this->link_service_to_category( $s3, $category_id );
		$staff_id = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $s1 );
		$this->link_staff_to_service( $staff_id, $s2 );
		$this->link_staff_to_service( $staff_id, $s3 );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 1 );

		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertStringNotContainsString( 'bookit-v2-services-grid--few', $output );
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step2_renders_list_layout_for_three_staff() {
		$category_id = $this->create_test_category();
		$service_id  = $this->create_test_service();
		$this->link_service_to_category( $service_id, $category_id );
		$a = $this->create_test_staff( array( 'first_name' => 'One' ) );
		$b = $this->create_test_staff( array( 'first_name' => 'Two' ) );
		$c = $this->create_test_staff( array( 'first_name' => 'Three' ) );
		$this->link_staff_to_service( $a, $service_id );
		$this->link_staff_to_service( $b, $service_id );
		$this->link_staff_to_service( $c, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 2 );
		Bookit_Session_Manager::set( 'service_id', $service_id );
		Bookit_Session_Manager::set( 'service_name', 'Test Service' );
		Bookit_Session_Manager::set( 'service_duration', 60 );

		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertStringContainsString( 'bookit-v2-staff-list', $output );
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step2_renders_grid_layout_for_four_staff() {
		$category_id = $this->create_test_category();
		$service_id  = $this->create_test_service();
		$this->link_service_to_category( $service_id, $category_id );
		for ( $i = 1; $i <= 4; $i++ ) {
			$sid = $this->create_test_staff( array( 'first_name' => 'Staff' . $i ) );
			$this->link_staff_to_service( $sid, $service_id );
		}

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 2 );
		Bookit_Session_Manager::set( 'service_id', $service_id );
		Bookit_Session_Manager::set( 'service_name', 'Grid Service' );
		Bookit_Session_Manager::set( 'service_duration', 45 );

		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertStringContainsString( 'bookit-v2-staff-grid', $output );
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step2_single_staff_auto_skips_to_step3() {
		add_filter( 'wp_redirect', '__return_false', 999 );

		$category_id = $this->create_test_category();
		$service_id  = $this->create_test_service();
		$this->link_service_to_category( $service_id, $category_id );
		$staff_id = $this->create_test_staff( array( 'first_name' => 'Solo' ) );
		$this->link_staff_to_service( $staff_id, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 2 );
		Bookit_Session_Manager::set( 'service_id', $service_id );
		Bookit_Session_Manager::set( 'service_name', 'Solo Service' );
		Bookit_Session_Manager::set( 'service_duration', 30 );

		do_shortcode( '[bookit_wizard_v2]' );

		remove_filter( 'wp_redirect', '__return_false', 999 );

		$this->assertSame( 3, (int) Bookit_Session_Manager::get( 'current_step' ) );
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step2_hidden_staff_mode_auto_skips_to_step3() {
		global $wpdb;

		add_filter( 'wp_redirect', '__return_false', 999 );

		$wpdb->replace(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'staff_selection_hidden',
				'setting_value' => '1',
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		$category_id = $this->create_test_category();
		$service_id  = $this->create_test_service();
		$this->link_service_to_category( $service_id, $category_id );
		$a = $this->create_test_staff( array( 'first_name' => 'H1' ) );
		$b = $this->create_test_staff( array( 'first_name' => 'H2' ) );
		$this->link_staff_to_service( $a, $service_id );
		$this->link_staff_to_service( $b, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 2 );
		Bookit_Session_Manager::set( 'service_id', $service_id );
		Bookit_Session_Manager::set( 'service_name', 'Hidden Mode' );
		Bookit_Session_Manager::set( 'service_duration', 60 );

		do_shortcode( '[bookit_wizard_v2]' );

		remove_filter( 'wp_redirect', '__return_false', 999 );

		$this->assertSame( 3, (int) Bookit_Session_Manager::get( 'current_step' ) );

		$wpdb->delete( $wpdb->prefix . 'bookings_settings', array( 'setting_key' => 'staff_selection_hidden' ), array( '%s' ) );
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step2_avatar_colour_is_deterministic() {
		$category_id = $this->create_test_category();
		$service_id  = $this->create_test_service();
		$this->link_service_to_category( $service_id, $category_id );
		$a = $this->create_test_staff( array( 'first_name' => 'X1' ) );
		$b = $this->create_test_staff( array( 'first_name' => 'X2' ) );
		$this->link_staff_to_service( $a, $service_id );
		$this->link_staff_to_service( $b, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 2 );
		Bookit_Session_Manager::set( 'service_id', $service_id );
		Bookit_Session_Manager::set( 'service_name', 'Avatar Test' );
		Bookit_Session_Manager::set( 'service_duration', 60 );

		do_shortcode( '[bookit_wizard_v2]' );

		$this->assertTrue( function_exists( 'bookit_v2_avatar_colour' ) );
		$first  = bookit_v2_avatar_colour( 'Elena Torres' );
		$second = bookit_v2_avatar_colour( 'Elena Torres' );
		$this->assertSame( $first, $second );
		$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/i', $first );
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step3_renders_calendar() {
		$category_id = $this->create_test_category();
		$service_id  = $this->create_test_service();
		$this->link_service_to_category( $service_id, $category_id );
		$staff_id = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 3 );
		Bookit_Session_Manager::set( 'service_id', $service_id );
		Bookit_Session_Manager::set( 'service_name', 'Cal Service' );
		Bookit_Session_Manager::set( 'service_duration', 60 );
		Bookit_Session_Manager::set( 'staff_id', $staff_id );
		Bookit_Session_Manager::set( 'staff_name', 'Test Staff' );

		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertTrue(
			strpos( $output, 'bookit-v2-day--available' ) !== false || strpos( $output, 'bookit-v2-day--disabled' ) !== false,
			'Calendar should render at least one day cell state'
		);
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step3_morning_group_hidden_when_empty() {
		$category_id = $this->create_test_category();
		$service_id  = $this->create_test_service();
		$this->link_service_to_category( $service_id, $category_id );
		$staff_id = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 3 );
		Bookit_Session_Manager::set( 'service_id', $service_id );
		Bookit_Session_Manager::set( 'service_name', 'Holiday Test' );
		Bookit_Session_Manager::set( 'service_duration', 60 );
		Bookit_Session_Manager::set( 'staff_id', $staff_id );
		Bookit_Session_Manager::set( 'staff_name', 'Staff' );
		Bookit_Session_Manager::set( 'date', '2026-01-01' );

		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertStringNotContainsString( 'bookit-v2-time-section"', $output, 'Morning/Afternoon/Evening wrappers should be absent (not the time-sections container id)' );
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step3_slots_not_rendered_when_no_date_selected() {
		$category_id = $this->create_test_category();
		$service_id  = $this->create_test_service();
		$this->link_service_to_category( $service_id, $category_id );
		$staff_id = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 3 );
		Bookit_Session_Manager::set( 'service_id', $service_id );
		Bookit_Session_Manager::set( 'service_name', 'No Date' );
		Bookit_Session_Manager::set( 'service_duration', 45 );
		Bookit_Session_Manager::set( 'staff_id', $staff_id );
		Bookit_Session_Manager::set( 'staff_name', 'Anyone' );

		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertStringNotContainsString( 'bookit-v2-slot--available', $output );
		$this->assertStringNotContainsString( 'bookit-v2-slot--selected', $output );
	}

	/**
	 * @coversNothing
	 */
	public function test_v2_step3_continue_button_disabled_when_no_slot_selected() {
		$category_id = $this->create_test_category();
		$service_id  = $this->create_test_service();
		$this->link_service_to_category( $service_id, $category_id );
		$staff_id = $this->create_test_staff();
		$this->link_staff_to_service( $staff_id, $service_id );

		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'current_step', 3 );
		Bookit_Session_Manager::set( 'service_id', $service_id );
		Bookit_Session_Manager::set( 'service_name', 'No Time' );
		Bookit_Session_Manager::set( 'service_duration', 30 );
		Bookit_Session_Manager::set( 'staff_id', $staff_id );
		Bookit_Session_Manager::set( 'staff_name', 'Pro' );
		Bookit_Session_Manager::set( 'date', '2026-12-15' );

		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertMatchesRegularExpression( '/<button[^>]*bookit-v2-cta-btn[^>]*\sdisabled/', $output );
	}

	/**
	 * Create a category row.
	 *
	 * @param array $args Overrides.
	 * @return int Category ID.
	 */
	private function create_test_category( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'name'           => 'Cat ' . wp_generate_password( 4, false ),
			'description'    => '',
			'display_order'  => 0,
			'is_active'      => 1,
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
			'deleted_at'     => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_categories',
			$data,
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Link service to category.
	 *
	 * @param int $service_id   Service ID.
	 * @param int $category_id Category ID.
	 */
	private function link_service_to_category( $service_id, $category_id ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_service_categories',
			array(
				'service_id'   => $service_id,
				'category_id'  => $category_id,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);
	}

	/**
	 * Create test service.
	 *
	 * @param array $args Overrides.
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
	 * Create test staff.
	 *
	 * @param array $args Overrides.
	 * @return int Staff ID.
	 */
	private function create_test_staff( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'              => 'staff-' . wp_generate_password( 8, false ) . '@test.com',
			'password_hash'      => wp_hash_password( 'password123' ),
			'first_name'         => 'Test',
			'last_name'          => 'Staff',
			'phone'              => '07700900000',
			'photo_url'          => null,
			'bio'                => 'Bio',
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
	 * Link staff to service.
	 *
	 * @param int        $staff_id   Staff ID.
	 * @param int        $service_id Service ID.
	 * @param float|null $custom_price Optional custom price.
	 */
	private function link_staff_to_service( $staff_id, $service_id, $custom_price = null ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff_services',
			array(
				'staff_id'      => $staff_id,
				'service_id'    => $service_id,
				'custom_price'  => $custom_price,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', $custom_price === null ? '%s' : '%f', '%s' )
		);
	}
}
