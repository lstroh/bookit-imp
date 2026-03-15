<?php
/**
 * Customer Package Lookup API
 *
 * Public endpoint for the booking wizard to fetch customer's active packages.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Customer package lookup API class.
 */
class Bookit_Customer_Package_Lookup_API {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'bookit/v1',
			'/wizard/my-packages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_my_packages' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'customer_email' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'service_id'      => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Get active, usable packages for a customer email.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_my_packages( $request ) {
		global $wpdb;

		$ip = Bookit_Rate_Limiter::get_client_ip();
		if ( ! Bookit_Rate_Limiter::check( 'wizard_my_pkgs', $ip, 60, HOUR_IN_SECONDS ) ) {
			return Bookit_Rate_Limiter::handle_exceeded( 'wizard_my_pkgs', $ip );
		}

		$customer_email = sanitize_email( (string) $request->get_param( 'customer_email' ) );
		$service_id     = absint( $request->get_param( 'service_id' ) );

		if ( empty( $customer_email ) || ! is_email( $customer_email ) ) {
			return new WP_Error(
				'invalid_customer_email',
				__( 'A valid customer email is required.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_customers WHERE email = %s LIMIT 1",
				$customer_email
			),
			ARRAY_A
		);

		if ( ! $customer ) {
			return new WP_REST_Response( array(), 200 );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cp.id, cp.sessions_remaining, cp.sessions_total, cp.expires_at,
					pt.name AS package_type_name, pt.applicable_service_ids
				FROM {$wpdb->prefix}bookings_customer_packages cp
				JOIN {$wpdb->prefix}bookings_package_types pt ON pt.id = cp.package_type_id
				WHERE cp.customer_id = %d
					AND cp.status = 'active'
					AND (cp.expires_at IS NULL OR cp.expires_at > NOW())
					AND cp.sessions_remaining > 0
				ORDER BY cp.id DESC",
				(int) $customer['id']
			),
			ARRAY_A
		);

		$items = array();
		foreach ( (array) $rows as $row ) {
			if ( $service_id > 0 && ! $this->package_matches_service( $row['applicable_service_ids'] ?? null, $service_id ) ) {
				continue;
			}

			$items[] = array(
				'id'                 => (int) $row['id'],
				'package_type_name'  => (string) $row['package_type_name'],
				'sessions_remaining' => (int) $row['sessions_remaining'],
				'sessions_total'     => (int) $row['sessions_total'],
				'expires_at'         => empty( $row['expires_at'] ) ? null : (string) $row['expires_at'],
			);
		}

		return new WP_REST_Response( $items, 200 );
	}

	/**
	 * Check whether package is valid for service.
	 *
	 * @param string|null $applicable_service_ids JSON array or null.
	 * @param int         $service_id Service ID.
	 * @return bool
	 */
	private function package_matches_service( $applicable_service_ids, $service_id ) {
		if ( null === $applicable_service_ids || '' === $applicable_service_ids ) {
			return true;
		}

		$decoded = json_decode( (string) $applicable_service_ids, true );
		if ( ! is_array( $decoded ) ) {
			return true;
		}

		$service_ids = array_values( array_map( 'absint', $decoded ) );
		return in_array( (int) $service_id, $service_ids, true );
	}
}
