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
}

// Initialize the API.
new Bookit_Dashboard_Bookings_API();
