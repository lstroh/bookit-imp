<?php
/**
 * Customer Packages REST API Controller.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Customer packages API class.
 */
class Bookit_Customer_Packages_API {

	/**
	 * REST namespace.
	 */
	const NAMESPACE = 'bookit/v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customer-packages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_customer_packages' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'customer_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return null === $value || ( is_numeric( $value ) && (int) $value > 0 );
						},
					),
					'status'      => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customer-packages',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_customer_package' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'customer_id'       => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && (int) $value >= 1;
						},
					),
					'package_type_id'   => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return is_numeric( $value ) && (int) $value >= 1;
						},
					),
					'payment_method'    => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							if ( null === $value || '' === $value ) {
								return true;
							}
							return in_array( (string) $value, array( 'stripe', 'paypal', 'pay_on_arrival', 'manual', 'other' ), true );
						},
					),
					'payment_reference' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'notes'             => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'purchased_at'      => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customer-packages/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_customer_package' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customer-packages/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_customer_package' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Permission callback for admin-only endpoints.
	 *
	 * @return true|WP_Error
	 */
	public function check_admin_permission() {
		if ( ! class_exists( 'Bookit_Session' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-session.php';
		}
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		if ( ! Bookit_Auth::is_logged_in() ) {
			return Bookit_Error_Registry::to_wp_error( 'E1002' );
		}

		$current_staff = Bookit_Auth::get_current_staff();
		$role          = is_array( $current_staff ) && isset( $current_staff['role'] ) ? (string) $current_staff['role'] : '';

		if ( ! in_array( $role, array( 'admin', 'bookit_admin' ), true ) ) {
			return Bookit_Error_Registry::to_wp_error(
				'E1003',
				array(
					'required_role' => 'bookit_admin',
					'actual_role'   => $role,
				)
			);
		}

		return true;
	}

	/**
	 * GET /dashboard/customer-packages
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_customer_packages( $request ) {
		global $wpdb;

		$customer_id      = absint( $request->get_param( 'customer_id' ) );
		$status           = sanitize_text_field( (string) $request->get_param( 'status' ) );
		$allowed_statuses = array( 'active', 'exhausted', 'expired', 'cancelled' );

		if ( '' !== $status && ! in_array( $status, $allowed_statuses, true ) ) {
			return new WP_Error(
				'invalid_package_status',
				__( 'Invalid package status filter.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$cp_table = $wpdb->prefix . 'bookings_customer_packages';
		$pt_table = $wpdb->prefix . 'bookings_package_types';

		$sql    = "SELECT cp.*, pt.name AS package_type_name
			FROM {$cp_table} cp
			LEFT JOIN {$pt_table} pt ON pt.id = cp.package_type_id";
		$where  = array();
		$params = array();

		if ( $customer_id > 0 ) {
			$where[]  = 'cp.customer_id = %d';
			$params[] = $customer_id;
		}

		if ( '' !== $status ) {
			$where[]  = 'cp.status = %s';
			$params[] = $status;
		}

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY cp.id DESC';

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( null === $rows ) {
			return Bookit_Error_Registry::to_wp_error( 'E9001' );
		}

		$items = array_map( array( $this, 'format_customer_package_row' ), $rows );

		return new WP_REST_Response( $items, 200 );
	}

	/**
	 * POST /dashboard/customer-packages
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_customer_package( $request ) {
		global $wpdb;

		$package_type_id = absint( $request->get_param( 'package_type_id' ) );
		$package_type    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, sessions_count, fixed_price, is_active, expiry_enabled, expiry_days
				FROM {$wpdb->prefix}bookings_package_types
				WHERE id = %d
				LIMIT 1",
				$package_type_id
			),
			ARRAY_A
		);

		if ( ! $package_type ) {
			return Bookit_Error_Registry::to_wp_error( 'E5001' );
		}

		if ( 0 === (int) $package_type['is_active'] ) {
			return new WP_Error(
				'package_type_inactive',
				__( 'Cannot purchase an inactive package type.', 'bookit-booking-system' ),
				array( 'status' => 422 )
			);
		}

		$purchased_at_raw = sanitize_text_field( (string) $request->get_param( 'purchased_at' ) );
		$purchased_at     = '' !== $purchased_at_raw ? $purchased_at_raw : current_time( 'mysql' );
		$expires_at       = null;

		if ( (int) $package_type['expiry_enabled'] === 1 && ! empty( $package_type['expiry_days'] ) ) {
			try {
				$expires_datetime = new DateTime( $purchased_at );
				$expires_datetime->modify( '+' . absint( $package_type['expiry_days'] ) . ' days' );
				$expires_at = $expires_datetime->format( 'Y-m-d H:i:s' );
			} catch ( Exception $exception ) {
				return new WP_Error(
					'invalid_purchased_at',
					__( 'Invalid purchased_at datetime value.', 'bookit-booking-system' ),
					array( 'status' => 400 )
				);
			}
		}

		$table = $wpdb->prefix . 'bookings_customer_packages';
		$now   = current_time( 'mysql' );
		$data  = array(
			'customer_id'        => absint( $request->get_param( 'customer_id' ) ),
			'package_type_id'    => $package_type_id,
			'sessions_total'     => (int) $package_type['sessions_count'],
			'sessions_remaining' => (int) $package_type['sessions_count'],
			'purchase_price'     => null === $package_type['fixed_price'] ? null : (float) $package_type['fixed_price'],
			'purchased_at'       => $purchased_at,
			'expires_at'         => $expires_at,
			'status'             => 'active',
			'payment_method'     => $this->normalize_nullable_string( sanitize_text_field( (string) $request->get_param( 'payment_method' ) ) ),
			'payment_reference'  => $this->normalize_nullable_string( sanitize_text_field( (string) $request->get_param( 'payment_reference' ) ) ),
			'notes'              => $this->normalize_nullable_string( sanitize_textarea_field( (string) $request->get_param( 'notes' ) ) ),
			'created_at'         => $now,
			'updated_at'         => $now,
		);

		$inserted = $wpdb->insert(
			$table,
			$data,
			array( '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return Bookit_Error_Registry::to_wp_error( 'E9001', array( 'db_error' => $wpdb->last_error ) );
		}

		$new_id  = (int) $wpdb->insert_id;
		$new_row = $this->fetch_customer_package_row( $new_id );
		if ( is_wp_error( $new_row ) ) {
			return $new_row;
		}

		Bookit_Audit_Logger::log(
			'customer_package.created',
			'customer_package',
			$new_id,
			array(
				'new_value' => $new_row,
			)
		);

		return new WP_REST_Response( $new_row, 201 );
	}

	/**
	 * GET /dashboard/customer-packages/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_customer_package( $request ) {
		$package_id = absint( $request->get_param( 'id' ) );
		$row        = $this->fetch_customer_package_row( $package_id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		return new WP_REST_Response( $row, 200 );
	}

	/**
	 * POST /dashboard/customer-packages/{id}/cancel
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_customer_package( $request ) {
		global $wpdb;

		$package_id = absint( $request->get_param( 'id' ) );
		$existing   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_customer_packages WHERE id = %d LIMIT 1",
				$package_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return Bookit_Error_Registry::to_wp_error( 'E5001' );
		}

		$old_status = (string) $existing['status'];
		if ( 'cancelled' === $old_status ) {
			return new WP_Error(
				'package_already_cancelled',
				__( 'This package is already cancelled.', 'bookit-booking-system' ),
				array( 'status' => 422 )
			);
		}

		if ( 'exhausted' === $old_status ) {
			return new WP_Error(
				'package_already_exhausted',
				__( 'An exhausted package cannot be cancelled.', 'bookit-booking-system' ),
				array( 'status' => 422 )
			);
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'bookings_customer_packages',
			array(
				'status'     => 'cancelled',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $package_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return Bookit_Error_Registry::to_wp_error( 'E9001', array( 'db_error' => $wpdb->last_error ) );
		}

		Bookit_Audit_Logger::log(
			'customer_package.cancelled',
			'customer_package',
			$package_id,
			array(
				'previous_status' => $old_status,
			)
		);

		$row = $this->fetch_customer_package_row( $package_id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		return new WP_REST_Response( $row, 200 );
	}

	/**
	 * Fetch one customer package row by ID with package type join.
	 *
	 * @param int $package_id Package ID.
	 * @return array|WP_Error
	 */
	private function fetch_customer_package_row( $package_id ) {
		global $wpdb;

		$cp_table = $wpdb->prefix . 'bookings_customer_packages';
		$pt_table = $wpdb->prefix . 'bookings_package_types';
		$row      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cp.*, pt.name AS package_type_name
				FROM {$cp_table} cp
				LEFT JOIN {$pt_table} pt ON pt.id = cp.package_type_id
				WHERE cp.id = %d
				LIMIT 1",
				$package_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return Bookit_Error_Registry::to_wp_error( 'E5001' );
		}

		return $this->format_customer_package_row( $row );
	}

	/**
	 * Convert DB row to API response shape.
	 *
	 * @param array $row Raw DB row.
	 * @return array
	 */
	private function format_customer_package_row( $row ) {
		return array(
			'id'                 => (int) $row['id'],
			'customer_id'        => (int) $row['customer_id'],
			'package_type_id'    => (int) $row['package_type_id'],
			'package_type_name'  => isset( $row['package_type_name'] ) ? ( null === $row['package_type_name'] ? null : (string) $row['package_type_name'] ) : null,
			'sessions_total'     => (int) $row['sessions_total'],
			'sessions_remaining' => (int) $row['sessions_remaining'],
			'purchase_price'     => null === $row['purchase_price'] ? null : number_format( (float) $row['purchase_price'], 2, '.', '' ),
			'purchased_at'       => isset( $row['purchased_at'] ) ? ( null === $row['purchased_at'] ? null : (string) $row['purchased_at'] ) : null,
			'expires_at'         => isset( $row['expires_at'] ) ? ( null === $row['expires_at'] ? null : (string) $row['expires_at'] ) : null,
			'status'             => (string) $row['status'],
			'payment_method'     => isset( $row['payment_method'] ) ? ( null === $row['payment_method'] ? null : (string) $row['payment_method'] ) : null,
			'payment_reference'  => isset( $row['payment_reference'] ) ? ( null === $row['payment_reference'] ? null : (string) $row['payment_reference'] ) : null,
			'notes'              => isset( $row['notes'] ) ? ( null === $row['notes'] ? null : (string) $row['notes'] ) : null,
			'created_at'         => isset( $row['created_at'] ) ? ( null === $row['created_at'] ? null : (string) $row['created_at'] ) : null,
			'updated_at'         => isset( $row['updated_at'] ) ? ( null === $row['updated_at'] ? null : (string) $row['updated_at'] ) : null,
		);
	}

	/**
	 * Convert empty strings to null.
	 *
	 * @param string $value Incoming string.
	 * @return string|null
	 */
	private function normalize_nullable_string( $value ) {
		return '' === $value ? null : $value;
	}
}
