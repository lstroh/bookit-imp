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
					),
				),
			)
		);
	}

	/**
	 * Check permission for session updates.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if allowed.
	 */
	public function check_permission( $request ) {
		// Verify nonce for security.
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}

		// Public endpoint - allow for booking wizard.
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
		return $value >= 1 && $value <= 4;
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

		if ( isset( $params['customer'] ) && is_array( $params['customer'] ) ) {
			$current_customer = Bookit_Session_Manager::get( 'customer', array() );
			$update_data['customer'] = array_merge( $current_customer, $params['customer'] );
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
}
