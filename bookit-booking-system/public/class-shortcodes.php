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
		add_shortcode( 'bookit_wizard_v2', array( $this, 'render_booking_wizard_v2' ) );
		add_shortcode( 'bookit_booking_confirmation', array( $this, 'render_booking_confirmation' ) );
		add_shortcode( 'bookit_booking_confirmed_v2', array( $this, 'render_booking_confirmed_v2' ) );
		add_shortcode( 'bookit_cancel_booking', array( $this, 'render_cancel_booking' ) );
		add_shortcode( 'bookit_reschedule_booking', array( $this, 'render_reschedule_booking' ) );
		add_shortcode( 'bookit_confirmation', array( $this, 'bookit_confirmation_page_shortcode' ) );
		add_shortcode( 'bookit_my_packages', array( $this, 'render_my_packages' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_wizard_assets' ) );
		add_filter( 'theme_page_templates', array( $this, 'register_wizard_v2_page_template' ), 10, 4 );
		add_filter( 'template_include', array( $this, 'load_wizard_v2_page_template' ), 99 );
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
		Bookit_Session_Manager::set( 'wizard_version', 'v1' );

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
		Bookit_Template_Loader::get_template( 'booking-wizard-shell.php' );

		return ob_get_clean();
	}

	/**
	 * Render booking wizard V2 shortcode.
	 *
	 * @param array  $atts
	 * @param string $content
	 * @return string
	 */
	public function render_booking_wizard_v2( $atts = array(), $content = '' ) {
		// Initialize session.
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'wizard_version', 'v2' );

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
		Bookit_Template_Loader::get_template( 'booking-wizard-v2-shell.php' );

		return ob_get_clean();
	}

	/**
	 * Register booking confirmation page shortcode [bookit_confirmation].
	 *
	 * @return string Confirmation HTML.
	 */
	public function bookit_confirmation_page_shortcode() {
		ob_start();
		Bookit_Template_Loader::get_template( 'booking-confirmed.php' );
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
		Bookit_Template_Loader::get_template( 'booking-confirmed.php' );
		return ob_get_clean();
	}

	/**
	 * Render V2 booking confirmation shortcode (parallel layout; used on /booking-confirmed-v2/ or similar).
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Confirmation HTML.
	 */
	public function render_booking_confirmed_v2( $atts = array(), $content = '' ) {
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();

		ob_start();
		Bookit_Template_Loader::get_template( 'booking-confirmed-v2.php' );
		return ob_get_clean();
	}

	/**
	 * Magic-link cancel page shortcode (templates: 5A-3b).
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string
	 */
	public function render_cancel_booking( $atts = array(), $content = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( $booking_id <= 0 || '' === $token ) {
			return '<p class="bookit-error">' . esc_html__( 'Invalid booking link.', 'bookit-booking-system' ) . '</p>';
		}

		$rest_url = rest_url( 'bookit/v1/wizard/' );

		ob_start();
		Bookit_Template_Loader::get_template(
			'cancel-booking.php',
			array(
				'booking_id' => $booking_id,
				'token'      => $token,
				'rest_url'   => $rest_url,
			)
		);
		return ob_get_clean();
	}

	/**
	 * Magic-link reschedule page shortcode (templates: 5A-3b).
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string
	 */
	public function render_reschedule_booking( $atts = array(), $content = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( $booking_id <= 0 || '' === $token ) {
			return '<p class="bookit-error">' . esc_html__( 'Invalid booking link.', 'bookit-booking-system' ) . '</p>';
		}

		$rest_url = rest_url( 'bookit/v1/wizard/' );

		ob_start();
		Bookit_Template_Loader::get_template(
			'reschedule-booking.php',
			array(
				'booking_id' => $booking_id,
				'token'      => $token,
				'rest_url'   => $rest_url,
			)
		);
		return ob_get_clean();
	}

	/**
	 * Render my packages shortcode.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string My packages HTML.
	 */
	public function render_my_packages( $atts = array(), $content = '' ) {
		ob_start();
		Bookit_Template_Loader::get_template( 'my-packages.php' );
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
		$has_confirmation_v2 = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bookit_booking_confirmed_v2' );
		$has_cancel          = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bookit_cancel_booking' );
		$has_reschedule      = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bookit_reschedule_booking' );
		$has_my_packages = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bookit_my_packages' );
		$has_wizard_v2 = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bookit_wizard_v2' );
		if ( ! $has_wizard && ! $has_wizard_v2 && ! $has_confirmation && ! $has_confirmation_v2 && ! $has_my_packages && ! $has_cancel && ! $has_reschedule ) {
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

		if ( $has_confirmation_v2 ) {
			wp_enqueue_style(
				'bookit-confirmation-v2',
				BOOKIT_PLUGIN_URL . 'public/assets/css/confirmation-page-v2.css',
				array( 'bookit-wizard' ),
				BOOKIT_VERSION,
				'all'
			);
		}

		if ( $has_cancel || $has_reschedule ) {
			wp_enqueue_style(
				'bookit-wizard-v2',
				BOOKIT_PLUGIN_URL . 'public/assets/css/booking-wizard-v2.css',
				array( 'bookit-wizard' ),
				BOOKIT_VERSION,
				'all'
			);
			wp_enqueue_style(
				'bookit-confirmation-v2',
				BOOKIT_PLUGIN_URL . 'public/assets/css/confirmation-page-v2.css',
				array( 'bookit-wizard' ),
				BOOKIT_VERSION,
				'all'
			);
			wp_enqueue_style(
				'bookit-magic-link-pages',
				BOOKIT_PLUGIN_URL . 'public/assets/css/magic-link-pages.css',
				array( 'bookit-confirmation-v2', 'bookit-wizard-v2' ),
				BOOKIT_VERSION,
				'all'
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

		if ( $has_my_packages ) {
			wp_enqueue_style(
				'bookit-my-packages',
				BOOKIT_PLUGIN_URL . 'public/assets/css/my-packages.css',
				array(),
				BOOKIT_VERSION,
				'all'
			);
			wp_localize_script(
				'bookit-wizard',
				'bookitMyPackages',
				array(
					'restUrl' => rest_url( 'bookit/v1/wizard/package-redemptions' ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

		if ( $has_wizard_v2 ) {
			require_once BOOKIT_PLUGIN_DIR . 'includes/wizard-v2-payment-amounts.php';
			$v2_deposit_amount = (float) Bookit_Session_Manager::get( 'deposit_due', 0.00 );
			$v2_total_amount   = (float) Bookit_Session_Manager::get( 'total_price', 0.00 );
			if ( 5 === (int) $current_step ) {
				$v2_service_id = (int) Bookit_Session_Manager::get( 'service_id', 0 );
				if ( $v2_service_id > 0 ) {
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$v2_service_row = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
							$v2_service_id
						),
						ARRAY_A
					);
					if ( $v2_service_row ) {
						$v2_amounts      = bookit_v2_compute_payment_amounts_from_service( $v2_service_row );
						$v2_deposit_amount = $v2_amounts['has_deposit'] ? (float) $v2_amounts['deposit_due'] : 0.0;
						$v2_total_amount   = (float) $v2_amounts['total_price'];
					}
				}
			}

			wp_enqueue_style(
				'bookit-wizard-v2',
				BOOKIT_PLUGIN_URL . 'public/assets/css/booking-wizard-v2.css',
				array(),
				BOOKIT_VERSION,
				'all'
			);
			wp_enqueue_script(
				'bookit-wizard-v2',
				BOOKIT_PLUGIN_URL . 'public/assets/js/booking-wizard-v2.js',
				array( 'jquery' ),
				BOOKIT_VERSION,
				true
			);
			wp_localize_script(
				'bookit-wizard-v2',
				'bookitWizardV2',
				array(
					'restUrl'       => rest_url(),
					'ajaxUrl'       => rest_url( 'bookit/v1/wizard/session' ),
					'nonce'         => wp_create_nonce( 'wp_rest' ),
					'bookingNonce'  => Bookit_CSRF_Protection::get_nonce(),
					'currentStep'   => $current_step,
					'depositAmount' => $v2_deposit_amount,
					'totalAmount'   => $v2_total_amount,
					// Default success redirect target; the WordPress page slug must match this path (or override via bookit_confirmed_v2_url).
					'confirmed_v2_url' => home_url( '/booking-confirmed-v2/' ),
				)
			);
		}
	}

	/**
	 * Register the Bookit Wizard V2 page template for the Page editor dropdown.
	 *
	 * @param array       $post_templates Array of template header names keyed by filename.
	 * @param WP_Theme    $theme            Current theme object.
	 * @param WP_Post     $post             The post being edited, null in list context.
	 * @param string      $post_type        Post type.
	 * @return array
	 */
	public function register_wizard_v2_page_template( $post_templates, $theme = null, $post = null, $post_type = 'page' ) {
		$post_templates['bookit-wizard-v2.php'] = __( 'Bookit Wizard V2', 'bookit-booking-system' );
		return $post_templates;
	}

	/**
	 * Load the plugin page template when the Bookit Wizard V2 template is selected.
	 *
	 * @param string $template Path to the template file.
	 * @return string
	 */
	public function load_wizard_v2_page_template( $template ) {
		if ( ! is_singular( 'page' ) ) {
			return $template;
		}
		$slug = get_page_template_slug();
		if ( 'bookit-wizard-v2.php' === $slug ) {
			$path = BOOKIT_PLUGIN_DIR . 'public/templates/page-wizard-v2.php';
			if ( file_exists( $path ) ) {
				return $path;
			}
		}
		return $template;
	}
}
