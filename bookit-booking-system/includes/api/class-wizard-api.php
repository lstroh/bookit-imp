<?php
/**
 * REST API endpoints for booking wizard.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Wizard API class.
 */
class Bookit_Wizard_API {

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'bookit/v1',
			'/wizard/session',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_session' ),
					'permission_callback' => '__return_true', // Public endpoint for booking wizard.
					'args'                => array(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_session' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'current_step' => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'validate_callback' => array( $this, 'validate_step' ),
						),
						'service_id'   => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'staff_id'     => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'date'         => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'time'         => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'customer'      => array(
							'required'          => false,
							'type'              => 'object',
							'sanitize_callback' => array( $this, 'sanitize_customer' ),
						),
						'service_name'   => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'service_duration' => array(
							'required'          => false,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'payment_method' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			'bookit/v1',
			'/wizard/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete_booking' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(),
			)
		);
	}

	/**
	 * Check permission for session updates.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if allowed, WP_Error on failure.
	 */
	public function check_permission( $request ) {
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-csrf-protection.php';

		if ( ! Bookit_CSRF_Protection::verify_rest_request( $request ) ) {
			return Bookit_CSRF_Protection::get_rest_error();
		}

		return true;
	}

	/**
	 * Validate step number.
	 *
	 * @param int             $value Step number.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param Parameter name.
	 * @return bool True if valid.
	 */
	public function validate_step( $value, $request, $param ) {
		return $value >= 1 && $value <= 5;
	}

	/**
	 * Sanitize customer data.
	 *
	 * @param array $customer Customer data.
	 * @return array Sanitized customer data.
	 */
	public function sanitize_customer( $customer ) {
		if ( ! is_array( $customer ) ) {
			return array();
		}

		$sanitized = array();
		$allowed_fields = array( 'name', 'email', 'phone', 'notes' );

		foreach ( $allowed_fields as $field ) {
			if ( isset( $customer[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $customer[ $field ] );
			}
		}

		// Validate email if provided.
		if ( isset( $sanitized['email'] ) && ! empty( $sanitized['email'] ) ) {
			$sanitized['email'] = sanitize_email( $sanitized['email'] );
		}

		return $sanitized;
	}

	/**
	 * Get session data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_session( $request ) {
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();

		// Check if session expired.
		if ( Bookit_Session_Manager::is_expired() ) {
			Bookit_Session_Manager::clear();
		}

		$data = Bookit_Session_Manager::get_data();
		$data['time_remaining'] = Bookit_Session_Manager::get_time_remaining();

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Update session data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_session( $request ) {
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();

		// Check if session expired.
		if ( Bookit_Session_Manager::is_expired() ) {
			Bookit_Session_Manager::clear();
		}

		// Get parameters.
		$params = $request->get_params();

		// Prepare update data.
		$update_data = array();

		if ( isset( $params['current_step'] ) ) {
			$update_data['current_step'] = (int) $params['current_step'];
		}

		if ( isset( $params['service_id'] ) ) {
			$update_data['service_id'] = (int) $params['service_id'];
		}

		if ( isset( $params['staff_id'] ) ) {
			$update_data['staff_id'] = (int) $params['staff_id'];
		}

		if ( isset( $params['date'] ) ) {
			$update_data['date'] = sanitize_text_field( $params['date'] );
		}

		if ( isset( $params['time'] ) ) {
			$update_data['time'] = sanitize_text_field( $params['time'] );
		}

		if ( isset( $params['service_name'] ) ) {
			$update_data['service_name'] = sanitize_text_field( $params['service_name'] );
		}

		if ( isset( $params['service_duration'] ) ) {
			$update_data['service_duration'] = absint( $params['service_duration'] );
		}

		if ( isset( $params['payment_method'] ) ) {
			$update_data['payment_method'] = sanitize_text_field( $params['payment_method'] );
		}

		if ( isset( $params['customer'] ) && is_array( $params['customer'] ) ) {
			$current_customer = Bookit_Session_Manager::get( 'customer', array() );
			$update_data['customer'] = array_merge( $current_customer, $params['customer'] );
		}

		if ( isset( $update_data['service_id'] ) && (int) $update_data['service_id'] > 0 ) {
			$this->maybe_fill_service_meta_from_db( $update_data );
		}

		// Update session.
		if ( ! empty( $update_data ) ) {
			Bookit_Session_Manager::set_data( $update_data );

			// Regenerate session ID on step changes for security.
			if ( isset( $update_data['current_step'] ) ) {
				Bookit_Session_Manager::regenerate();
			}
		}

		// Return updated session data.
		$data = Bookit_Session_Manager::get_data();
		$data['time_remaining'] = Bookit_Session_Manager::get_time_remaining();

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Fill service name/duration from DB when missing.
	 *
	 * @param array $update_data Update payload (by ref).
	 * @return void
	 */
	private function maybe_fill_service_meta_from_db( array &$update_data ) {
		if ( ! empty( $update_data['service_name'] ) && isset( $update_data['service_duration'] ) && (int) $update_data['service_duration'] > 0 ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT name, duration FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				(int) $update_data['service_id']
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return;
		}
		if ( empty( $update_data['service_name'] ) ) {
			$update_data['service_name'] = $row['name'];
		}
		if ( ! isset( $update_data['service_duration'] ) || (int) $update_data['service_duration'] <= 0 ) {
			$update_data['service_duration'] = (int) $row['duration'];
		}
	}

	/**
	 * Complete booking from wizard session (pay on arrival / package); returns redirect URL.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function complete_booking( $request ) {
		$ip = Bookit_Rate_Limiter::get_client_ip();
		if ( ! Bookit_Rate_Limiter::check( 'wizard_book', $ip, 10, HOUR_IN_SECONDS ) ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Too many requests. Please wait before trying again.', 'bookit-booking-system' ),
				array( 'status' => 429 )
			);
		}

		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();

		if ( Bookit_Session_Manager::is_expired() ) {
			return new WP_Error(
				'session_expired',
				__( 'Your session has expired. Please start again.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$session_data   = Bookit_Session_Manager::get_data();
		$payment_method = isset( $session_data['payment_method'] )
			? sanitize_text_field( $session_data['payment_method'] )
			: '';

		if ( empty( $session_data ) ) {
			return new WP_Error(
				'invalid_session',
				__( 'No booking data found. Please start again.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		// Step 5 stores package choice as use_package_{customer_package_id}; map for the processor.
		if ( preg_match( '/^use_package_(\d+)$/', $payment_method, $pkg_match ) ) {
			$session_data['customer_package_id'] = (int) $pkg_match[1];
			$payment_method                      = 'use_package';
		}

		require_once BOOKIT_PLUGIN_DIR . 'includes/payment/class-payment-processor.php';
		$processor = new Booking_System_Payment_Processor();

		switch ( $payment_method ) {
			case 'pay_on_arrival':
			case 'person':
				$result = $processor->process_pay_on_arrival( $session_data );
				break;

			case 'use_package':
				$result = $processor->process_use_package( $session_data );
				break;

			case 'card':
			case 'stripe':
			case 'paypal':
				return new WP_Error(
					'payment_method_not_available',
					__( 'Online payment is not yet available. Please select Pay in Person or use a package.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);

			default:
				return new WP_Error(
					'invalid_payment_method',
					__( 'Invalid payment method.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
		}

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'booking_id'   => $result['booking_id'],
				'redirect_url' => $result['redirect_url'],
			)
		);
	}
}
