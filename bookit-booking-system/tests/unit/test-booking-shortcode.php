<?php
/**
 * Tests for Bookit_Shortcodes
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Shortcodes class.
 */
class Test_Booking_Shortcode extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		// Shortcodes and hooks are registered by plugin loader; ensure clean state.
		Bookit_Session_Manager::clear();
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
	 * Test that 'bookit_booking_wizard' shortcode is registered.
	 *
	 * @covers Bookit_Shortcodes::__construct
	 */
	public function test_shortcode_is_registered() {
		global $shortcode_tags;
		$this->assertArrayHasKey( 'bookit_booking_wizard', $shortcode_tags );
	}

	/**
	 * Test that shortcode renders non-empty output.
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_shortcode_renders_output() {
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertNotEmpty( $output );
		$this->assertIsString( $output );
	}

	/**
	 * Test that shortcode output contains wizard container class.
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_shortcode_contains_wizard_container() {
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'bookit-wizard-container', $output );
	}

	/**
	 * Test that shortcode output contains progress indicator.
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_shortcode_contains_progress_indicator() {
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'bookit-progress-indicator', $output );
	}

	/**
	 * Test that shortcode output contains main landmark with id main-content.
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_shortcode_contains_main_landmark() {
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'id="main-content"', $output );
		$this->assertStringContainsString( '<main', $output );
	}

	/**
	 * Test that shortcode output contains navigation.
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_shortcode_contains_navigation() {
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'bookit-wizard-nav', $output );
		$this->assertStringContainsString( 'bookit-btn-next', $output );
	}

	/**
	 * Test that shortcode loads step 1 by default.
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_shortcode_loads_step_1_by_default() {
		Bookit_Session_Manager::clear();
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'bookit-step-1', $output );
		$this->assertStringContainsString( 'Select Service', $output );
	}

	/**
	 * Test that shortcode has skip link.
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_shortcode_has_skip_link() {
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'bookit-skip-link', $output );
		$this->assertStringContainsString( '#main-content', $output );
	}

	/**
	 * Test that shortcode enqueues CSS on page with shortcode.
	 *
	 * @covers Bookit_Shortcodes::enqueue_wizard_assets
	 */
	public function test_shortcode_enqueues_css() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Test Booking Page',
				'post_status' => 'publish',
				'post_content' => '[bookit_booking_wizard]',
			)
		);
		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		do_action( 'wp_enqueue_scripts' );

		$styles = wp_styles();
		$this->assertTrue( isset( $styles->registered['bookit-wizard'] ), 'bookit-wizard CSS should be enqueued' );
		$this->assertStringContainsString( 'booking-wizard.css', $styles->registered['bookit-wizard']->src );

		wp_reset_postdata();
	}

	/**
	 * Test that shortcode enqueues JS on page with shortcode.
	 *
	 * @covers Bookit_Shortcodes::enqueue_wizard_assets
	 */
	public function test_shortcode_enqueues_js() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Test Booking Page JS',
				'post_status'  => 'publish',
				'post_content' => '[bookit_booking_wizard]',
			)
		);
		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		do_action( 'wp_enqueue_scripts' );

		$scripts = wp_scripts();
		$this->assertTrue( isset( $scripts->registered['bookit-wizard'] ), 'bookit-wizard JS should be enqueued' );
		$this->assertStringContainsString( 'booking-wizard.js', $scripts->registered['bookit-wizard']->src );

		wp_reset_postdata();
	}

	/**
	 * Test that shortcode localizes script with nonce and AJAX data.
	 *
	 * @covers Bookit_Shortcodes::enqueue_wizard_assets
	 */
	public function test_shortcode_localizes_ajax_data() {
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Test Booking Page Localize',
				'post_status'  => 'publish',
				'post_content' => '[bookit_booking_wizard]',
			)
		);
		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		do_action( 'wp_enqueue_scripts' );

		$scripts = wp_scripts();
		$this->assertTrue( isset( $scripts->registered['bookit-wizard'] ) );
		$extra = $scripts->get_data( 'bookit-wizard', 'data' );
		$this->assertNotEmpty( $extra, 'bookit-wizard should have localized data' );
		$this->assertStringContainsString( 'bookitWizard', $extra );
		$this->assertStringContainsString( 'nonce', $extra );
		$this->assertStringContainsString( 'ajaxUrl', $extra );
		$this->assertStringContainsString( 'currentStep', $extra );

		wp_reset_postdata();
	}

	/**
	 * Test that shortcode does not enqueue wizard assets on page without shortcode.
	 *
	 * @covers Bookit_Shortcodes::enqueue_wizard_assets
	 */
	public function test_shortcode_does_not_enqueue_on_page_without_shortcode() {
		// Clear any wizard assets from prior tests so we assert this request only.
		wp_dequeue_style( 'bookit-wizard' );
		wp_dequeue_script( 'bookit-wizard' );
		wp_deregister_style( 'bookit-wizard' );
		wp_deregister_script( 'bookit-wizard' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'No Wizard Page',
				'post_status'  => 'publish',
				'post_content' => 'Just some content.',
			)
		);
		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		do_action( 'wp_enqueue_scripts' );

		$styles  = wp_styles();
		$scripts = wp_scripts();
		$this->assertFalse( in_array( 'bookit-wizard', $styles->queue, true ), 'bookit-wizard CSS should not be enqueued' );
		$this->assertFalse( in_array( 'bookit-wizard', $scripts->queue, true ), 'bookit-wizard JS should not be enqueued' );

		wp_reset_postdata();
	}

	/**
	 * Test that shortcode shows step 2 when session has current_step 2.
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard
	 */
	public function test_shortcode_respects_session_step() {
		Bookit_Session_Manager::set( 'current_step', 2 );
		$output = do_shortcode( '[bookit_booking_wizard]' );
		$this->assertStringContainsString( 'bookit-step-2', $output );
		$this->assertStringContainsString( 'Choose Staff', $output );
	}
}
