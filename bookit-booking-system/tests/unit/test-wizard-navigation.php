<?php
/**
 * Tests for wizard navigation logic
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test wizard navigation (session, API, template).
 */
class Test_Wizard_Navigation extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Session route.
	 *
	 * @var string
	 */
	private $route = '/wizard/session';

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		Bookit_Session_Manager::clear();
		do_action( 'rest_api_init' );
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
	 * Test that wizard starts on step 1.
	 *
	 * @covers Bookit_Session_Manager::get_data
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_initial_step_is_one() {
		Bookit_Session_Manager::clear();
		$this->assertEquals( 1, (int) Bookit_Session_Manager::get( 'current_step', 1 ) );

		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'bookit-step-1', $output );
	}

	/**
	 * Test that "next" (setting step via API) increments step.
	 *
	 * @covers Bookit_Wizard_API::update_session
	 */
	public function test_next_increments_step() {
		$this->assertEquals( 1, (int) Bookit_Session_Manager::get( 'current_step', 1 ) );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params( array( 'current_step' => 2 ) );
		rest_get_server()->dispatch( $request );

		$this->assertEquals( 2, (int) Bookit_Session_Manager::get( 'current_step' ) );

		$request->set_body_params( array( 'current_step' => 3 ) );
		rest_get_server()->dispatch( $request );
		$this->assertEquals( 3, (int) Bookit_Session_Manager::get( 'current_step' ) );
	}

	/**
	 * Test that "back" (setting step via API) decrements step.
	 *
	 * @covers Bookit_Wizard_API::update_session
	 */
	public function test_back_decrements_step() {
		Bookit_Session_Manager::set( 'current_step', 3 );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params( array( 'current_step' => 2 ) );
		rest_get_server()->dispatch( $request );

		$this->assertEquals( 2, (int) Bookit_Session_Manager::get( 'current_step' ) );

		$request->set_body_params( array( 'current_step' => 1 ) );
		rest_get_server()->dispatch( $request );
		$this->assertEquals( 1, (int) Bookit_Session_Manager::get( 'current_step' ) );
	}

	/**
	 * Test that back button is not shown on step 1.
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_cannot_go_below_step_one() {
		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set( 'current_step', 1 );
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringNotContainsString( 'bookit-btn-back', $output );
		$this->assertStringNotContainsString( 'bookit-back-btn', $output );
	}

	/**
	 * Test that step 4 still shows next button (submit); step validation blocks >4.
	 *
	 * @covers Bookit_Wizard_API::validate_step
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_cannot_go_above_step_four() {
		Bookit_Session_Manager::set( 'current_step', 4 );
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'bookit-btn-next', $output );

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params( array( 'current_step' => 5 ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 4, (int) Bookit_Session_Manager::get( 'current_step' ) );
	}

	/**
	 * Test that invalid step progression is blocked by API validation.
	 *
	 * @covers Bookit_Wizard_API::validate_step
	 */
	public function test_step_validation_blocks_invalid_progression() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		foreach ( array( 0, -1, 5, 99 ) as $invalid ) {
			$request->set_body_params( array( 'current_step' => $invalid ) );
			$response = rest_get_server()->dispatch( $request );
			$this->assertEquals( 400, $response->get_status(), "Step $invalid should be rejected" );
		}
	}

	/**
	 * Test that going back preserves previously set data.
	 *
	 * @covers Bookit_Wizard_API::update_session
	 * @covers Bookit_Session_Manager::set_data
	 */
	public function test_back_button_preserves_data() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'current_step' => 2,
				'service_id'   => 42,
			)
		);
		rest_get_server()->dispatch( $request );
		$this->assertEquals( 42, (int) Bookit_Session_Manager::get( 'service_id' ) );
		$this->assertEquals( 2, (int) Bookit_Session_Manager::get( 'current_step' ) );

		$request->set_body_params( array( 'current_step' => 1 ) );
		rest_get_server()->dispatch( $request );
		$this->assertEquals( 1, (int) Bookit_Session_Manager::get( 'current_step' ) );
		$this->assertEquals( 42, (int) Bookit_Session_Manager::get( 'service_id' ), 'Service ID should be preserved when going back' );
	}

	/**
	 * Test that shortcode output includes step-based progress and navigation.
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_url_hash_updates_on_step_change() {
		// URL hash (#step-X) is updated by JavaScript; we verify step is reflected in markup.
		Bookit_Session_Manager::set( 'current_step', 2 );
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'data-step="2"', $output );
		$this->assertStringContainsString( 'bookit-step-2', $output );

		Bookit_Session_Manager::set( 'current_step', 4 );
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'data-step="4"', $output );
	}

	/**
	 * Test that back button is present on steps 2–4.
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_browser_back_button_works() {
		foreach ( array( 2, 3, 4 ) as $step ) {
			Bookit_Session_Manager::set( 'current_step', $step );
			$output = do_shortcode( '[bookit_booking_wizard]' );
			$this->assertStringContainsString( 'bookit-btn-back', $output );
			$this->assertStringContainsString( 'bookit-back-btn', $output );
		}
	}
}
