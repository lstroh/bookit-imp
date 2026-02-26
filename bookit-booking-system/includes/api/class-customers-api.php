<?php
/**
 * Customers REST API Controller
 *
 * Handles dashboard customer database endpoints (including GDPR erasure).
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Bookit_Customers_API {

	const NAMESPACE = 'bookit/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customers' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'search'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'   => array(
						'required'          => false,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							return in_array( $param, array( 'active', 'inactive', 'new' ), true );
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 25,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_customers_csv' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customer' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/(?P<id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_customer' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'first_name'        => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'         => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'phone'             => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'marketing_consent' => array(
						'required'          => false,
						'type'              => 'boolean',
						'sanitize_callback' => function ( $value ) {
							return rest_sanitize_boolean( $value );
						},
					),
					'notes'             => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_customer' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Check if user has admin permission.
	 * Only admins can manage services.
	 *
	 * @return bool|WP_Error
	 */
	public function check_admin_permission() {
		// Load auth classes if not loaded.
		if ( ! class_exists( 'Bookit_Session' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-session.php';
		}
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		if ( ! Bookit_Auth::is_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				'You must be logged in to access the dashboard.',
				array( 'status' => 401 )
			);
		}

		$current_staff = Bookit_Auth::get_current_staff();

		if ( ! $current_staff || 'admin' !== $current_staff['role'] ) {
			return new WP_Error(
				'forbidden',
				'Only administrators can manage services.',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /dashboard/customers
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_customers( $request ) {
		global $wpdb;

		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$status   = sanitize_text_field( (string) $request->get_param( 'status' ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = max( 1, absint( $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$base_query = "
			SELECT
				c.id,
				c.first_name,
				c.last_name,
				c.email,
				c.phone,
				c.marketing_consent,
				c.created_at,
				COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL THEN b.id END) AS total_bookings,
				COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) AS total_spent,
				MAX(CASE WHEN b.status = 'completed' THEN b.booking_date END) AS last_visit,
				COUNT(DISTINCT CASE WHEN b.status IN ('confirmed','pending_payment') AND b.booking_date >= CURDATE() AND b.deleted_at IS NULL THEN b.id END) AS upcoming_count
			FROM {$wpdb->prefix}bookings_customers c
			LEFT JOIN {$wpdb->prefix}bookings b ON b.customer_id = c.id
			LEFT JOIN {$wpdb->prefix}bookings_payments p ON p.booking_id = b.id
			WHERE c.deleted_at IS NULL
		";

		$params        = array();
		$where_clauses = array();
		$having_clause = '';

		if ( ! empty( $search ) ) {
			$like            = '%' . $wpdb->esc_like( $search ) . '%';
			$where_clauses[] = '(c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s)';
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
			$params[]        = $like;
		}

		if ( ! empty( $where_clauses ) ) {
			$base_query .= ' AND ' . implode( ' AND ', $where_clauses );
		}

		if ( 'active' === $status ) {
			$having_clause = " HAVING COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) THEN b.id END) > 0";
		} elseif ( 'inactive' === $status ) {
			$having_clause = " HAVING COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) THEN b.id END) = 0";
		} elseif ( 'new' === $status ) {
			$having_clause = " HAVING COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL THEN b.id END) = 1";
		}

		$group_order_sql = '
			GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone, c.marketing_consent, c.created_at
			' . $having_clause . '
			ORDER BY c.created_at DESC
		';

		$data_query = $base_query . ' ' . $group_order_sql . ' LIMIT %d OFFSET %d';

		$data_params   = $params;
		$data_params[] = $per_page;
		$data_params[] = $offset;
		$prepared_data = $wpdb->prepare( $data_query, $data_params );
		$rows          = $wpdb->get_results( $prepared_data, ARRAY_A );

		$count_query = 'SELECT COUNT(*) FROM (' . $base_query . ' ' . $group_order_sql . ') AS customers_count';
		if ( empty( $params ) ) {
			$total = (int) $wpdb->get_var( $count_query );
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $params ) );
		}

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );

		$customers = array_map(
			function ( $row ) {
				$total_bookings = (int) $row['total_bookings'];
				$last_visit     = ! empty( $row['last_visit'] ) ? substr( (string) $row['last_visit'], 0, 10 ) : null;
				$status_label   = $this->determine_customer_status( $total_bookings, $last_visit );

				return array(
					'id'                => (int) $row['id'],
					'first_name'        => (string) $row['first_name'],
					'last_name'         => (string) $row['last_name'],
					'full_name'         => trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] ),
					'email'             => (string) $row['email'],
					'phone'             => (string) $row['phone'],
					'marketing_consent' => (bool) (int) $row['marketing_consent'],
					'member_since'      => isset( $row['created_at'] ) ? substr( (string) $row['created_at'], 0, 10 ) : '',
					'total_bookings'    => $total_bookings,
					'total_spent'       => (float) $row['total_spent'],
					'last_visit'        => $last_visit,
					'upcoming_count'    => (int) $row['upcoming_count'],
					'status'            => $status_label,
				);
			},
			$rows
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'customers'  => $customers,
				'pagination' => array(
					'total'        => $total,
					'per_page'     => $per_page,
					'current_page' => $page,
					'total_pages'  => $total_pages,
				),
			)
		);
	}

	/**
	 * GET /dashboard/customers/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_customer( $request ) {
		global $wpdb;

		$customer_id = absint( $request->get_param( 'id' ) );
		if ( $customer_id <= 0 ) {
			return new WP_Error(
				'invalid_customer_id',
				__( 'A valid customer ID is required.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$query = "
			SELECT
				c.id,
				c.first_name,
				c.last_name,
				c.email,
				c.phone,
				c.notes,
				c.marketing_consent,
				c.marketing_consent_date,
				c.created_at,
				COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL THEN b.id END) AS total_bookings,
				COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) AS total_spent,
				MAX(CASE WHEN b.status = 'completed' THEN b.booking_date END) AS last_visit,
				COUNT(DISTINCT CASE WHEN b.status IN ('confirmed','pending_payment') AND b.booking_date >= CURDATE() AND b.deleted_at IS NULL THEN b.id END) AS upcoming_count
			FROM {$wpdb->prefix}bookings_customers c
			LEFT JOIN {$wpdb->prefix}bookings b ON b.customer_id = c.id
			LEFT JOIN {$wpdb->prefix}bookings_payments p ON p.booking_id = b.id
			WHERE c.deleted_at IS NULL AND c.id = %d
			GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone, c.notes, c.marketing_consent, c.marketing_consent_date, c.created_at
			ORDER BY c.created_at DESC
		";

		$customer = $wpdb->get_row(
			$wpdb->prepare( $query, $customer_id ),
			ARRAY_A
		);

		if ( ! $customer ) {
			return new WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					b.id, b.booking_date, b.start_time, b.end_time, b.status,
					b.total_price, b.deposit_paid, b.balance_due, b.payment_method,
					s.name AS service_name,
					CONCAT(st.first_name, ' ', st.last_name) AS staff_name
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_services s ON s.id = b.service_id
				INNER JOIN {$wpdb->prefix}bookings_staff st ON st.id = b.staff_id
				WHERE b.customer_id = %d AND b.deleted_at IS NULL
				ORDER BY b.booking_date DESC, b.start_time DESC
				LIMIT 20",
				$customer_id
			),
			ARRAY_A
		);

		$payments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.id, p.amount, p.payment_method, p.payment_type,
					p.payment_status, p.transaction_date, p.booking_id
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE b.customer_id = %d AND b.deleted_at IS NULL
				ORDER BY p.transaction_date DESC
				LIMIT 10",
				$customer_id
			),
			ARRAY_A
		);

		$total_bookings = (int) $customer['total_bookings'];
		$last_visit     = ! empty( $customer['last_visit'] ) ? substr( (string) $customer['last_visit'], 0, 10 ) : null;

		return rest_ensure_response(
			array(
				'success'  => true,
				'customer' => array(
					'id'                     => (int) $customer['id'],
					'first_name'             => (string) $customer['first_name'],
					'last_name'              => (string) $customer['last_name'],
					'full_name'              => trim( (string) $customer['first_name'] . ' ' . (string) $customer['last_name'] ),
					'email'                  => (string) $customer['email'],
					'phone'                  => (string) $customer['phone'],
					'notes'                  => isset( $customer['notes'] ) ? (string) $customer['notes'] : '',
					'marketing_consent'      => (bool) (int) $customer['marketing_consent'],
					'marketing_consent_date' => $customer['marketing_consent_date'],
					'member_since'           => isset( $customer['created_at'] ) ? substr( (string) $customer['created_at'], 0, 10 ) : '',
					'total_bookings'         => $total_bookings,
					'total_spent'            => (float) $customer['total_spent'],
					'last_visit'             => $last_visit,
					'upcoming_count'         => (int) $customer['upcoming_count'],
					'status'                 => $this->determine_customer_status( $total_bookings, $last_visit ),
					'bookings'               => $bookings,
					'payments'               => $payments,
				),
			)
		);
	}

	/**
	 * PUT /dashboard/customers/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_customer( $request ) {
		global $wpdb;

		$customer_id = absint( $request->get_param( 'id' ) );
		if ( $customer_id <= 0 ) {
			return new WP_Error(
				'invalid_customer_id',
				__( 'A valid customer ID is required.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_customers WHERE id = %d AND deleted_at IS NULL",
				$customer_id
			)
		);

		if ( $exists <= 0 ) {
			return new WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		$data   = array();
		$format = array();

		if ( null !== $request->get_param( 'first_name' ) ) {
			$data['first_name'] = sanitize_text_field( (string) $request->get_param( 'first_name' ) );
			$format[]           = '%s';
		}

		if ( null !== $request->get_param( 'last_name' ) ) {
			$data['last_name'] = sanitize_text_field( (string) $request->get_param( 'last_name' ) );
			$format[]          = '%s';
		}

		if ( null !== $request->get_param( 'phone' ) ) {
			$data['phone'] = sanitize_text_field( (string) $request->get_param( 'phone' ) );
			$format[]      = '%s';
		}

		if ( null !== $request->get_param( 'notes' ) ) {
			$data['notes'] = sanitize_textarea_field( (string) $request->get_param( 'notes' ) );
			$format[]      = '%s';
		}

		if ( null !== $request->get_param( 'marketing_consent' ) ) {
			$marketing_consent                  = (int) rest_sanitize_boolean( $request->get_param( 'marketing_consent' ) );
			$data['marketing_consent']          = $marketing_consent;
			$format[]                           = '%d';
			$data['marketing_consent_date']     = $marketing_consent ? current_time( 'mysql' ) : null;
			$format[]                           = $marketing_consent ? '%s' : null;
		}

		if ( empty( $data ) ) {
			return new WP_Error(
				'no_fields_to_update',
				__( 'No valid fields were provided for update.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$data['updated_at'] = current_time( 'mysql' );
		$format[]           = '%s';

		$updated = $wpdb->update(
			$wpdb->prefix . 'bookings_customers',
			$data,
			array( 'id' => $customer_id ),
			$format,
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error(
				'customer_update_failed',
				__( 'Failed to update customer.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Customer updated successfully.', 'bookit-booking-system' ),
			)
		);
	}

	/**
	 * DELETE /dashboard/customers/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_customer( $request ) {
		global $wpdb;

		$customer_id = absint( $request->get_param( 'id' ) );
		if ( $customer_id <= 0 ) {
			return new WP_Error(
				'invalid_customer_id',
				__( 'A valid customer ID is required.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_customers WHERE id = %d AND deleted_at IS NULL",
				$customer_id
			)
		);

		if ( $exists <= 0 ) {
			return new WP_Error(
				'customer_not_found',
				__( 'Customer not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		$upcoming = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE customer_id = %d
					AND status IN ('confirmed', 'pending_payment')
					AND booking_date >= CURDATE()
					AND deleted_at IS NULL",
				$customer_id
			)
		);

		if ( $upcoming > 0 ) {
			return new WP_Error(
				'has_upcoming_bookings',
				sprintf(
					__( 'Cannot delete customer with %d upcoming booking(s). Cancel them first.', 'bookit-booking-system' ),
					$upcoming
				),
				array( 'status' => 409 )
			);
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'bookings_customers',
			array(
				'first_name'             => 'Deleted',
				'last_name'              => 'Customer',
				'email'                  => 'deleted_' . $customer_id . '@deleted.invalid',
				'phone'                  => '',
				'marketing_consent'      => 0,
				'marketing_consent_date' => null,
				'notes'                  => null,
				'deleted_at'             => current_time( 'mysql' ),
				'updated_at'             => current_time( 'mysql' ),
			),
			array( 'id' => $customer_id ),
			array( '%s', '%s', '%s', '%s', '%d', null, null, '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error(
				'customer_delete_failed',
				__( 'Failed to anonymise customer data.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Customer data has been anonymised in compliance with GDPR Article 17.', 'bookit-booking-system' ),
			)
		);
	}

	/**
	 * GET /dashboard/customers/export
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_customers_csv( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $wpdb;

		$query = "
			SELECT
				c.id,
				c.first_name,
				c.last_name,
				c.email,
				c.phone,
				c.marketing_consent,
				c.created_at,
				COUNT(DISTINCT CASE WHEN b.status != 'cancelled' AND b.deleted_at IS NULL THEN b.id END) AS total_bookings,
				COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) AS total_spent,
				MAX(CASE WHEN b.status = 'completed' THEN b.booking_date END) AS last_visit,
				COUNT(DISTINCT CASE WHEN b.status IN ('confirmed','pending_payment') AND b.booking_date >= CURDATE() AND b.deleted_at IS NULL THEN b.id END) AS upcoming_count
			FROM {$wpdb->prefix}bookings_customers c
			LEFT JOIN {$wpdb->prefix}bookings b ON b.customer_id = c.id
			LEFT JOIN {$wpdb->prefix}bookings_payments p ON p.booking_id = b.id
			WHERE c.deleted_at IS NULL
			GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone, c.marketing_consent, c.created_at
			ORDER BY c.created_at DESC
		";

		$customers = $wpdb->get_results( $query, ARRAY_A );

		$stream = fopen( 'php://temp', 'r+' );

		fputcsv(
			$stream,
			array(
				'Customer ID',
				'First Name',
				'Last Name',
				'Email',
				'Phone',
				'Member Since',
				'Total Bookings',
				'Total Spent',
				'Last Visit',
				'Upcoming Bookings',
				'Status',
				'Marketing Consent',
			)
		);

		foreach ( $customers as $customer ) {
			$total_bookings = (int) $customer['total_bookings'];
			$last_visit     = ! empty( $customer['last_visit'] ) ? substr( (string) $customer['last_visit'], 0, 10 ) : null;
			$status         = $this->determine_customer_status( $total_bookings, $last_visit );

			$member_since = ! empty( $customer['created_at'] ) ? date( 'd/m/Y', strtotime( (string) $customer['created_at'] ) ) : '';
			$last_visit_f = $last_visit ? date( 'd/m/Y', strtotime( $last_visit ) ) : 'Never';

			fputcsv(
				$stream,
				array(
					(int) $customer['id'],
					(string) $customer['first_name'],
					(string) $customer['last_name'],
					(string) $customer['email'],
					(string) $customer['phone'],
					$member_since,
					$total_bookings,
					number_format( (float) $customer['total_spent'], 2, '.', '' ),
					$last_visit_f,
					(int) $customer['upcoming_count'],
					ucfirst( $status ),
					(bool) (int) $customer['marketing_consent'] ? 'Yes' : 'No',
				)
			);
		}

		rewind( $stream );
		$csv_string = stream_get_contents( $stream );
		fclose( $stream );

		$filename = 'customers-export-' . current_time( 'Y-m-d' ) . '.csv';

		add_filter(
			'rest_pre_serve_request',
			function( $served ) use ( $csv_string, $filename ) {
				if ( ! $served ) {
					header( 'Content-Type: text/csv; charset=utf-8' );
					header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
					header( 'Cache-Control: no-cache, no-store, must-revalidate' );
					header( 'Content-Length: ' . strlen( $csv_string ) );
					echo $csv_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				return true;
			}
		);

		return new WP_REST_Response( null, 200 );
	}

	/**
	 * Determine customer status with required priority.
	 *
	 * @param int         $total_bookings Total bookings count.
	 * @param string|null $last_visit Last visit date (Y-m-d) or null.
	 * @return string
	 */
	private function determine_customer_status( $total_bookings, $last_visit ) {
		if ( 1 === (int) $total_bookings ) {
			return 'new';
		}

		if ( empty( $last_visit ) ) {
			return 'inactive';
		}

		$six_months_ago = gmdate( 'Y-m-d', strtotime( '-6 months' ) );
		if ( $last_visit >= $six_months_ago ) {
			return 'active';
		}

		return 'inactive';
	}
}
