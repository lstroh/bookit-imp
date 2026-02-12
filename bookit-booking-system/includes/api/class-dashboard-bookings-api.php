<?php
/**
 * Dashboard Bookings REST API Controller
 *
 * Handles dashboard-specific booking endpoints with authentication.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Bookit_Dashboard_Bookings_API
 */
class Bookit_Dashboard_Bookings_API {

	/**
	 * REST API namespace.
	 */
	const NAMESPACE = 'bookit/v1';

	/**
	 * Constructor - Register REST routes.
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
		// Today's bookings.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/today',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_todays_bookings' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
			)
		);

		// Mark booking as complete.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/(?P<id>\d+)/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mark_booking_complete' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// All bookings with filtering.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_all_bookings' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'page'       => array(
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
					'per_page'   => array(
						'default'           => 20,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0 && $param <= 100;
						},
					),
					'date_from'  => array(
						'validate_callback' => function ( $param ) {
							return empty( $param ) || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
						},
					),
					'date_to'    => array(
						'validate_callback' => function ( $param ) {
							return empty( $param ) || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
						},
					),
					'staff_id'   => array(
						'validate_callback' => function ( $param ) {
							return empty( $param ) || is_numeric( $param );
						},
					),
					'service_id' => array(
						'validate_callback' => function ( $param ) {
							return empty( $param ) || is_numeric( $param );
						},
					),
					'status'     => array(
						'validate_callback' => function ( $param ) {
							$valid_statuses = array( 'pending', 'pending_payment', 'confirmed', 'completed', 'cancelled', 'no_show' );
							return empty( $param ) || in_array( $param, $valid_statuses, true );
						},
					),
					'search'     => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'order_by'   => array(
						'default'           => 'booking_date',
						'validate_callback' => function ( $param ) {
							$valid_columns = array( 'booking_date', 'start_time', 'status', 'created_at' );
							return in_array( $param, $valid_columns, true );
						},
					),
					'order'      => array(
						'default'           => 'DESC',
						'validate_callback' => function ( $param ) {
							return in_array( strtoupper( $param ), array( 'ASC', 'DESC' ), true );
						},
					),
				),
			)
		);

		// Get staff list for filter dropdown.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_staff_list' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
			)
		);

		// Get staff list for specific service (filtered by staff_services).
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/by-service/(?P<service_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_staff_by_service' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
			)
		);

		// Get services list for filter dropdown.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/services/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_services_list' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
			)
		);

		// Manual booking creation.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_manual_booking' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'customer_id'         => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return empty( $param ) || is_numeric( $param );
						},
					),
					'customer_email'      => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_email',
						'validate_callback' => function ( $param ) {
							return empty( $param ) || is_email( $param );
						},
					),
					'customer_first_name' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'customer_last_name'  => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'customer_phone'      => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'service_id'          => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'staff_id'            => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'booking_date'        => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
						},
					),
					'booking_time'        => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $param );
						},
					),
					'payment_method'      => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							$valid_methods = array( 'pay_on_arrival', 'manual', 'cash', 'card_external', 'check', 'complimentary', 'stripe' );
							return in_array( $param, $valid_methods, true );
						},
					),
					'amount_paid'         => array(
						'default'           => 0,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param >= 0;
						},
					),
					'special_requests'    => array(
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'send_confirmation'   => array(
						'default'           => true,
						'validate_callback' => function ( $param ) {
							return is_bool( $param ) || in_array( $param, array( 'true', 'false', '1', '0', 1, 0 ), true );
						},
					),
				),
			)
		);

		// Customer search endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/customers/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_customers' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'search' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check if user has dashboard permission.
	 *
	 * @return bool|WP_Error
	 */
	public function check_dashboard_permission() {
		// Load auth classes if not loaded.
		if ( ! class_exists( 'Bookit_Session' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-session.php';
		}
		if ( ! class_exists( 'Bookit_Auth' ) ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-bookit-auth.php';
		}

		// Check if logged in.
		if ( ! Bookit_Auth::is_logged_in() ) {
			return new WP_Error(
				'unauthorized',
				__( 'You must be logged in to access the dashboard.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Get today's bookings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_todays_bookings( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		$today = current_time( 'Y-m-d' );

		// Build query with role-based filtering.
		$query = "
			SELECT
				b.id,
				b.booking_date,
				b.start_time,
				b.end_time,
				b.duration,
				b.status,
				b.total_price,
				b.deposit_paid,
				b.balance_due,
				b.full_amount_paid,
				b.payment_method,
				b.special_requests,
				b.staff_notes,
				c.first_name AS customer_first_name,
				c.last_name AS customer_last_name,
				c.email AS customer_email,
				c.phone AS customer_phone,
				s.name AS service_name,
				st.first_name AS staff_first_name,
				st.last_name AS staff_last_name
			FROM {$wpdb->prefix}bookings b
			INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
			INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
			INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
			WHERE b.booking_date = %s
			AND b.deleted_at IS NULL
		";

		$params = array( $today );

		// Staff role: only see their own bookings.
		// Admin role: see all bookings.
		if ( 'staff' === $current_staff['role'] ) {
			$query   .= ' AND b.staff_id = %d';
			$params[] = $current_staff['id'];
		}

		// Order by start time.
		$query .= ' ORDER BY b.start_time ASC';

		// Execute query.
		$results = $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );

		if ( null === $results ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to retrieve bookings.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		// Format bookings for frontend.
		$bookings = array_map( array( $this, 'format_booking' ), $results );

		return rest_ensure_response(
			array(
				'success'  => true,
				'bookings' => $bookings,
				'date'     => $today,
				'count'    => count( $bookings ),
			)
		);
	}

	/**
	 * Get all bookings with filtering and pagination.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_all_bookings( $request ) {
		global $wpdb;

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				__( 'Could not retrieve staff information.', 'bookit-booking-system' ),
				array( 'status' => 401 )
			);
		}

		// Get parameters.
		$page       = (int) $request->get_param( 'page' );
		$per_page   = (int) $request->get_param( 'per_page' );
		$date_from  = $request->get_param( 'date_from' );
		$date_to    = $request->get_param( 'date_to' );
		$staff_id   = $request->get_param( 'staff_id' );
		$service_id = $request->get_param( 'service_id' );
		$status     = $request->get_param( 'status' );
		$search     = $request->get_param( 'search' );
		$order_by   = $request->get_param( 'order_by' );
		$order      = strtoupper( $request->get_param( 'order' ) );

		// Build base query.
		$query = "
			SELECT
				b.id,
				b.booking_date,
				b.start_time,
				b.end_time,
				b.duration,
				b.status,
				b.total_price,
				b.deposit_paid,
				b.balance_due,
				b.full_amount_paid,
				b.payment_method,
				b.special_requests,
				b.staff_notes,
				b.created_at,
				c.first_name AS customer_first_name,
				c.last_name AS customer_last_name,
				c.email AS customer_email,
				c.phone AS customer_phone,
				s.name AS service_name,
				st.first_name AS staff_first_name,
				st.last_name AS staff_last_name,
				st.id AS staff_id
			FROM {$wpdb->prefix}bookings b
			INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
			INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
			INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
			WHERE b.deleted_at IS NULL
		";

		$params = array();

		// Role-based filtering (staff only see their bookings).
		if ( 'staff' === $current_staff['role'] ) {
			$query   .= ' AND b.staff_id = %d';
			$params[] = $current_staff['id'];
		}

		// Date range filter.
		if ( ! empty( $date_from ) ) {
			$query   .= ' AND b.booking_date >= %s';
			$params[] = $date_from;
		}
		if ( ! empty( $date_to ) ) {
			$query   .= ' AND b.booking_date <= %s';
			$params[] = $date_to;
		}

		// Staff filter (admin can filter by specific staff).
		if ( ! empty( $staff_id ) && 'admin' === $current_staff['role'] ) {
			$query   .= ' AND b.staff_id = %d';
			$params[] = (int) $staff_id;
		}

		// Service filter.
		if ( ! empty( $service_id ) ) {
			$query   .= ' AND b.service_id = %d';
			$params[] = (int) $service_id;
		}

		// Status filter.
		if ( ! empty( $status ) ) {
			$query   .= ' AND b.status = %s';
			$params[] = $status;
		}

		// Search filter (customer name or email).
		if ( ! empty( $search ) ) {
			$search_param = '%' . $wpdb->esc_like( $search ) . '%';
			$query       .= " AND (
				c.first_name LIKE %s OR
				c.last_name LIKE %s OR
				c.email LIKE %s OR
				CONCAT(c.first_name, ' ', c.last_name) LIKE %s
			)";
			$params[] = $search_param;
			$params[] = $search_param;
			$params[] = $search_param;
			$params[] = $search_param;
		}

		// Get total count before pagination.
		$count_query = "SELECT COUNT(*) FROM ({$query}) AS filtered_bookings";
		$total       = ! empty( $params )
			? $wpdb->get_var( $wpdb->prepare( $count_query, $params ) )
			: $wpdb->get_var( $count_query );

		// Add ordering.
		$valid_order_columns = array(
			'booking_date' => 'b.booking_date',
			'start_time'   => 'b.start_time',
			'status'       => 'b.status',
			'created_at'   => 'b.created_at',
		);

		$order_column = isset( $valid_order_columns[ $order_by ] )
			? $valid_order_columns[ $order_by ]
			: 'b.booking_date';

		$query .= " ORDER BY {$order_column} {$order}, b.start_time {$order}";

		// Add pagination.
		$offset   = ( $page - 1 ) * $per_page;
		$query   .= ' LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;

		// Execute query.
		$results = ! empty( $params )
			? $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A )
			: $wpdb->get_results( $query, ARRAY_A );

		if ( null === $results ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to retrieve bookings.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		// Format bookings for frontend.
		$bookings = array_map( array( $this, 'format_booking' ), $results );

		// Calculate pagination info.
		$total_pages = ceil( $total / $per_page );

		return rest_ensure_response(
			array(
				'success'    => true,
				'bookings'   => $bookings,
				'pagination' => array(
					'total'        => (int) $total,
					'per_page'     => $per_page,
					'current_page' => $page,
					'total_pages'  => (int) $total_pages,
					'has_next'     => $page < $total_pages,
					'has_prev'     => $page > 1,
				),
				'filters'    => array(
					'date_from'  => $date_from,
					'date_to'    => $date_to,
					'staff_id'   => $staff_id,
					'service_id' => $service_id,
					'status'     => $status,
					'search'     => $search,
				),
			)
		);
	}

	/**
	 * Get staff list for filter dropdown.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_staff_list() {
		global $wpdb;

		$staff = $wpdb->get_results(
			"SELECT
				id,
				CONCAT(first_name, ' ', last_name) AS name
			FROM {$wpdb->prefix}bookings_staff
			WHERE is_active = 1
			AND deleted_at IS NULL
			ORDER BY first_name ASC",
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'staff'   => $staff,
			)
		);
	}

	/**
	 * Get staff list for a specific service.
	 *
	 * Only returns staff who can provide the service (via staff_services junction table).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_staff_by_service( $request ) {
		global $wpdb;

		$service_id = (int) $request->get_param( 'service_id' );

		$staff = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT
					s.id,
					CONCAT(s.first_name, ' ', s.last_name) AS name,
					s.first_name,
					s.last_name,
					ss.custom_price
				FROM {$wpdb->prefix}bookings_staff s
				INNER JOIN {$wpdb->prefix}bookings_staff_services ss ON s.id = ss.staff_id
				WHERE s.is_active = 1
				AND s.deleted_at IS NULL
				AND ss.service_id = %d
				ORDER BY s.first_name ASC",
				$service_id
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'staff'   => $staff,
			)
		);
	}

	/**
	 * Get services list for filter dropdown.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_services_list() {
		global $wpdb;

		$services = $wpdb->get_results(
			"SELECT
				id,
				name,
				price,
				duration
			FROM {$wpdb->prefix}bookings_services
			WHERE is_active = 1
			AND deleted_at IS NULL
			ORDER BY name ASC",
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success'  => true,
				'services' => $services,
			)
		);
	}

	/**
	 * Format booking data for API response.
	 *
	 * @param array $booking Raw booking from database.
	 * @return array Formatted booking.
	 */
	private function format_booking( $booking ) {
		// Calculate if booking is starting soon (within 15 minutes).
		$current_time = current_time( 'H:i:s' );
		$start_time   = $booking['start_time'];

		$current_timestamp = strtotime( current_time( 'Y-m-d' ) . ' ' . $current_time );
		$start_timestamp   = strtotime( current_time( 'Y-m-d' ) . ' ' . $start_time );

		$time_until_start = ( $start_timestamp - $current_timestamp ) / 60; // minutes.
		$is_starting_soon = $time_until_start > 0 && $time_until_start <= 15;

		// Calculate if booking has passed.
		$has_passed = $current_timestamp > strtotime( current_time( 'Y-m-d' ) . ' ' . $booking['end_time'] );

		return array(
			'id'               => (int) $booking['id'],
			'booking_date'     => $booking['booking_date'],
			'start_time'       => substr( $booking['start_time'], 0, 5 ), // HH:MM format.
			'end_time'         => substr( $booking['end_time'], 0, 5 ),
			'duration'         => (int) $booking['duration'],
			'status'           => $booking['status'],
			'total_price'      => (float) $booking['total_price'],
			'deposit_paid'     => (float) $booking['deposit_paid'],
			'balance_due'      => (float) $booking['balance_due'],
			'full_amount_paid' => (bool) $booking['full_amount_paid'],
			'payment_method'   => $booking['payment_method'],
			'special_requests' => $booking['special_requests'],
			'staff_notes'      => $booking['staff_notes'],
			'customer_name'    => $booking['customer_first_name'] . ' ' . $booking['customer_last_name'],
			'customer_email'   => $booking['customer_email'],
			'customer_phone'   => $booking['customer_phone'],
			'service_name'     => $booking['service_name'],
			'staff_name'       => $booking['staff_first_name'] . ' ' . $booking['staff_last_name'],
			'is_starting_soon' => $is_starting_soon,
			'has_passed'       => $has_passed,
		);
	}

	/**
	 * Mark booking as complete.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function mark_booking_complete( $request ) {
		global $wpdb;

		$booking_id    = (int) $request['id'];
		$current_staff = Bookit_Auth::get_current_staff();

		// Get booking to verify ownership (staff can only complete their own bookings).
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, staff_id, status FROM {$wpdb->prefix}bookings WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return new WP_Error(
				'booking_not_found',
				__( 'Booking not found.', 'bookit-booking-system' ),
				array( 'status' => 404 )
			);
		}

		// Check permission: staff can only complete their own bookings.
		if ( 'staff' === $current_staff['role'] && (int) $booking['staff_id'] !== (int) $current_staff['id'] ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have permission to complete this booking.', 'bookit-booking-system' ),
				array( 'status' => 403 )
			);
		}

		// Check if already completed.
		if ( 'completed' === $booking['status'] ) {
			return new WP_Error(
				'already_completed',
				__( 'This booking is already marked as complete.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		// Update status to completed.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'status'     => 'completed',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'database_error',
				__( 'Failed to update booking status.', 'bookit-booking-system' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'message'    => __( 'Booking marked as complete.', 'bookit-booking-system' ),
				'booking_id' => $booking_id,
			)
		);
	}

	/**
	 * Create manual booking via dashboard.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_manual_booking( $request ) {
		global $wpdb;

		// Verify staff is logged in.
		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		// Get or create customer.
		$customer_id = $request->get_param( 'customer_id' );

		if ( empty( $customer_id ) ) {
			// Create new customer.
			$customer_email = $request->get_param( 'customer_email' );
			$customer_first = $request->get_param( 'customer_first_name' );
			$customer_last  = $request->get_param( 'customer_last_name' );
			$customer_phone = $request->get_param( 'customer_phone' );

			if ( empty( $customer_email ) || empty( $customer_first ) || empty( $customer_last ) ) {
				return new WP_Error(
					'missing_customer_data',
					'Customer email, first name, and last name are required for new customers.',
					array( 'status' => 400 )
				);
			}

			// Check if customer already exists.
			$existing_customer = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}bookings_customers WHERE email = %s AND deleted_at IS NULL",
					$customer_email
				)
			);

			if ( $existing_customer ) {
				$customer_id = $existing_customer;
			} else {
				// Create new customer.
				$result = $wpdb->insert(
					$wpdb->prefix . 'bookings_customers',
					array(
						'email'      => $customer_email,
						'first_name' => $customer_first,
						'last_name'  => $customer_last,
						'phone'      => $customer_phone,
						'created_at' => current_time( 'mysql' ),
						'updated_at' => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%s', '%s', '%s', '%s' )
				);

				if ( ! $result ) {
					return new WP_Error(
						'customer_creation_failed',
						'Failed to create customer.',
						array( 'status' => 500 )
					);
				}

				$customer_id = $wpdb->insert_id;
			}
		}

		// Verify customer exists.
		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_customers WHERE id = %d AND deleted_at IS NULL",
				$customer_id
			),
			ARRAY_A
		);

		if ( ! $customer ) {
			return new WP_Error(
				'customer_not_found',
				'Customer not found.',
				array( 'status' => 404 )
			);
		}

		// Get staff ID (handle "no preference" = 0).
		$requested_staff_id = (int) $request->get_param( 'staff_id' );
		$service_id         = (int) $request->get_param( 'service_id' );
		$booking_date       = $request->get_param( 'booking_date' );
		$booking_time       = $request->get_param( 'booking_time' );

		// If staff_id is 0 (no preference), find first available staff for this service.
		if ( 0 === $requested_staff_id ) {
			// Get all staff who can provide this service.
			$available_staff = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT s.id
					FROM {$wpdb->prefix}bookings_staff s
					INNER JOIN {$wpdb->prefix}bookings_staff_services ss ON s.id = ss.staff_id
					WHERE s.is_active = 1
					AND s.deleted_at IS NULL
					AND ss.service_id = %d
					ORDER BY s.first_name ASC",
					$service_id
				),
				ARRAY_A
			);

			if ( empty( $available_staff ) ) {
				return new WP_Error(
					'no_staff_available',
					'No staff members can provide this service.',
					array( 'status' => 400 )
				);
			}

			// Load datetime model to check availability.
			if ( ! class_exists( 'Bookit_DateTime_Model' ) ) {
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'models/class-datetime-model.php';
			}
			$datetime_model = new Bookit_DateTime_Model();

			// Normalize booking_time to H:i:s for comparison with model output.
			$time_check = $booking_time;
			if ( strlen( $time_check ) === 5 ) {
				$time_check .= ':00';
			}

			// Find first staff with availability at this time.
			$assigned_staff_id = null;
			foreach ( $available_staff as $staff ) {
				$slots = $datetime_model->get_available_slots( $booking_date, $service_id, (int) $staff['id'] );

				if ( ! empty( $slots ) && in_array( $time_check, $slots, true ) ) {
					$assigned_staff_id = (int) $staff['id'];
					break;
				}
			}

			if ( ! $assigned_staff_id ) {
				return new WP_Error(
					'no_staff_available_at_time',
					'No staff members are available at the selected time.',
					array( 'status' => 400 )
				);
			}

			$requested_staff_id = $assigned_staff_id;
		}

		// Prepare booking data for Booking_Creator.
		$booking_data = array(
			'service_id'          => $service_id,
			'staff_id'            => $requested_staff_id,
			'booking_date'        => $booking_date,
			'booking_time'        => $booking_time,
			'customer_email'      => $customer['email'],
			'customer_first_name' => $customer['first_name'],
			'customer_last_name'  => $customer['last_name'],
			'customer_phone'      => $customer['phone'],
			'payment_method'      => $request->get_param( 'payment_method' ),
			'amount_paid'         => (float) $request->get_param( 'amount_paid' ),
			'special_requests'    => $request->get_param( 'special_requests' ),
		);

		// Load booking creator if not loaded.
		if ( ! class_exists( 'Booking_System_Booking_Creator' ) ) {
			require_once plugin_dir_path( dirname( __DIR__ ) ) . 'booking/class-booking-creator.php';
		}

		// Create booking.
		$creator = new Booking_System_Booking_Creator();
		$result  = $creator->create_booking( $booking_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$booking_id = $result;

		// Send confirmation emails if requested.
		$send_confirmation = filter_var( $request->get_param( 'send_confirmation' ), FILTER_VALIDATE_BOOLEAN );

		if ( $send_confirmation ) {
			// Load email sender.
			if ( ! class_exists( 'Booking_System_Email_Sender' ) ) {
				require_once plugin_dir_path( dirname( __DIR__ ) ) . 'email/class-email-sender.php';
			}

			// Get full booking details for email.
			$booking = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						b.*,
						c.first_name AS customer_first_name,
						c.last_name AS customer_last_name,
						c.email AS customer_email,
						c.phone AS customer_phone,
						s.name AS service_name,
						s.duration,
						st.first_name AS staff_first_name,
						st.last_name AS staff_last_name
					FROM {$wpdb->prefix}bookings b
					INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
					INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
					INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
					WHERE b.id = %d",
					$booking_id
				),
				ARRAY_A
			);

			// Add composite name fields expected by the email sender.
			$booking['customer_name'] = $booking['customer_first_name'] . ' ' . $booking['customer_last_name'];
			$booking['staff_name']    = $booking['staff_first_name'] . ' ' . $booking['staff_last_name'];

			$email_sender = new Booking_System_Email_Sender();
			$email_sender->send_customer_confirmation( $booking );
			$email_sender->send_business_notification( $booking );
		}

		// Get created booking for response.
		$created_booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					b.*,
					c.first_name AS customer_first_name,
					c.last_name AS customer_last_name,
					c.email AS customer_email,
					c.phone AS customer_phone,
					s.name AS service_name,
					st.first_name AS staff_first_name,
					st.last_name AS staff_last_name
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
				INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
				INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
				WHERE b.id = %d",
				$booking_id
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success'    => true,
				'message'    => 'Booking created successfully.',
				'booking_id' => $booking_id,
				'booking'    => $this->format_booking( $created_booking ),
				'email_sent' => $send_confirmation,
			)
		);
	}

	/**
	 * Search customers by name or email.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function search_customers( $request ) {
		global $wpdb;

		$search = $request->get_param( 'search' );

		if ( strlen( $search ) < 2 ) {
			return rest_ensure_response(
				array(
					'success'   => true,
					'customers' => array(),
				)
			);
		}

		$search_param = '%' . $wpdb->esc_like( $search ) . '%';

		$customers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					id,
					email,
					first_name,
					last_name,
					phone,
					CONCAT(first_name, ' ', last_name) AS full_name
				FROM {$wpdb->prefix}bookings_customers
				WHERE deleted_at IS NULL
				AND (
					first_name LIKE %s OR
					last_name LIKE %s OR
					email LIKE %s OR
					CONCAT(first_name, ' ', last_name) LIKE %s
				)
				ORDER BY first_name ASC, last_name ASC
				LIMIT 20",
				$search_param,
				$search_param,
				$search_param,
				$search_param
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success'   => true,
				'customers' => $customers,
			)
		);
	}
}

// Initialize the API.
new Bookit_Dashboard_Bookings_API();
