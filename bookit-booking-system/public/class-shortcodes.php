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
		add_shortcode( 'bookit_booking_confirmation', array( $this, 'render_booking_confirmation' ) );
		add_shortcode( 'bookit_confirmation', array( $this, 'bookit_confirmation_page_shortcode' ) );
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

		// Get current step from session.
		$current_step = (int) Bookit_Session_Manager::get( 'current_step', 1 );

		// Allow backward navigation via ?step= URL parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['step'] ) ) {
			$requested_step = (int) $_GET['step'];

			// Only allow navigating backwards (to a step already completed) or to the current step.
			if ( $requested_step >= 1 && $requested_step <= $current_step ) {
				$current_step = $requested_step;
				Bookit_Session_Manager::set( 'current_step', $current_step );
			}
		}

		// Validate step range.
		if ( $current_step < 1 || $current_step > 5 ) {
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
	 * Register booking confirmation page shortcode [bookit_confirmation].
	 *
	 * @return string Confirmation HTML.
	 */
	public function bookit_confirmation_page_shortcode() {
		ob_start();
		include BOOKIT_PLUGIN_DIR . 'public/templates/booking-confirmed.php';
		return ob_get_clean();
	}

	/**
	 * Render booking confirmation shortcode (success page after payment).
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Confirmation HTML.
	 */
	public function render_booking_confirmation( $atts = array(), $content = '' ) {
		// Initialize session so clear_booking_session can run if needed.
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();

		ob_start();
		$template_path = BOOKIT_PLUGIN_DIR . 'public/templates/booking-confirmed.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		} else {
			echo '<p>' . esc_html__( 'Confirmation template not found.', 'bookit-booking-system' ) . '</p>';
		}
		return ob_get_clean();
	}

	/**
	 * Enqueue wizard-specific assets.
	 *
	 * @return void
	 */
	public function enqueue_wizard_assets() {
		// Only enqueue on pages with the wizard or confirmation shortcode.
		global $post;
		$has_wizard = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bookit_booking_wizard' );
		$has_confirmation = is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'bookit_booking_confirmation' ) || has_shortcode( $post->post_content, 'bookit_confirmation' ) );
		if ( ! $has_wizard && ! $has_confirmation ) {
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

		// Step 3: Date & Time picker.
		wp_enqueue_style(
			'bookit-datetime-picker',
			BOOKIT_PLUGIN_URL . 'public/assets/css/datetime-picker.css',
			array(),
			BOOKIT_VERSION,
			'all'
		);
		wp_enqueue_script(
			'bookit-datetime-picker',
			BOOKIT_PLUGIN_URL . 'public/assets/js/datetime-picker.js',
			array( 'bookit-wizard' ),
			BOOKIT_VERSION,
			true
		);

		// Step 4: Contact form.
		wp_enqueue_style(
			'bookit-contact-form',
			BOOKIT_PLUGIN_URL . 'public/assets/css/contact-form.css',
			array(),
			BOOKIT_VERSION,
			'all'
		);
		wp_enqueue_script(
			'bookit-contact-form',
			BOOKIT_PLUGIN_URL . 'public/assets/js/contact-form.js',
			array( 'bookit-wizard' ),
			BOOKIT_VERSION,
			true
		);

		// Step 5: Payment.
		wp_enqueue_style(
			'bookit-payment-step',
			BOOKIT_PLUGIN_URL . 'public/assets/css/payment-step.css',
			array(),
			BOOKIT_VERSION,
			'all'
		);

		// Enqueue confirmation page styles when this page has the confirmation shortcode.
		if ( $has_confirmation ) {
			wp_enqueue_style(
				'bookit-confirmation',
				BOOKIT_PLUGIN_URL . 'public/assets/css/confirmation-page.css',
				array(),
				'1.0.0'
			);
		}

		// Get current step from session if available.
		$current_step = 1;
		if ( class_exists( 'Bookit_Session_Manager' ) ) {
			Bookit_Session_Manager::init();
			$current_step = (int) Bookit_Session_Manager::get( 'current_step', 1 );
		}

		// Localize script with AJAX data (wp_rest for REST API, bookit_booking for CSRF).
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-csrf-protection.php';
		wp_localize_script(
			'bookit-wizard',
			'bookitWizard',
			array(
				'restUrl'     => rest_url(),
				'ajaxUrl'     => rest_url( 'bookit/v1/wizard/session' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'bookingNonce' => Bookit_CSRF_Protection::get_nonce(),
				'currentStep' => $current_step,
			)
		);
	}
}
