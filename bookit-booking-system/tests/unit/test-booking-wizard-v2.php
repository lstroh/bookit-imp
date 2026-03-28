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
}
