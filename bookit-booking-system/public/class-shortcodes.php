<?php
/**
 * Shortcode handler for booking wizard.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Shortcode handler class.
 */
class Bookit_Shortcodes {

	/**
	 * Initialize shortcodes.
	 *
	 * @return void
	 */
	public function __construct() {
		add_shortcode( 'bookit_booking_wizard', array( $this, 'render_booking_wizard' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_wizard_assets' ) );
	}

	/**
	 * Render booking wizard shortcode.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Wizard HTML.
	 */
	public function render_booking_wizard( $atts = array(), $content = '' ) {
		// Initialize session.
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();

		// Check if session expired.
		if ( Bookit_Session_Manager::is_expired() ) {
			Bookit_Session_Manager::clear();
		}

		// Get current step.
		$current_step = (int) Bookit_Session_Manager::get( 'current_step', 1 );

		// Validate step range.
		if ( $current_step < 1 || $current_step > 4 ) {
			$current_step = 1;
			Bookit_Session_Manager::set( 'current_step', 1 );
		}

		// Start output buffering.
		ob_start();

		// Load wizard shell template.
		$template_path = BOOKIT_PLUGIN_DIR . 'public/templates/booking-wizard-shell.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<p>' . esc_html__( 'Booking wizard template not found.', 'bookit-booking-system' ) . '</p>';
		}

		return ob_get_clean();
	}

	/**
	 * Enqueue wizard-specific assets.
	 *
	 * @return void
	 */
	public function enqueue_wizard_assets() {
		// Only enqueue on pages with the shortcode.
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'bookit_booking_wizard' ) ) {
			return;
		}

		// Enqueue wizard CSS.
		wp_enqueue_style(
			'bookit-wizard',
			BOOKIT_PLUGIN_URL . 'public/assets/css/booking-wizard.css',
			array(),
			BOOKIT_VERSION,
			'all'
		);

		// Enqueue wizard JavaScript.
		wp_enqueue_script(
			'bookit-wizard',
			BOOKIT_PLUGIN_URL . 'public/assets/js/booking-wizard.js',
			array( 'jquery' ),
			BOOKIT_VERSION,
			true
		);

		// Get current step from session if available.
		$current_step = 1;
		if ( class_exists( 'Bookit_Session_Manager' ) ) {
			Bookit_Session_Manager::init();
			$current_step = (int) Bookit_Session_Manager::get( 'current_step', 1 );
		}

		// Localize script with AJAX data.
		wp_localize_script(
			'bookit-wizard',
			'bookitWizard',
			array(
				'restUrl'    => rest_url(),
				'ajaxUrl'    => rest_url( 'bookit/v1/wizard/session' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'currentStep' => $current_step,
			)
		);
	}
}
