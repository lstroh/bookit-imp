<?php
/**
 * Reports REST API Controller
 *
 * Handles all dashboard reports endpoints.
 * Admin-only access for all endpoints.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Bookit_Reports_API {

	const NAMESPACE = 'bookit/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		// Overview report.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/reports/overview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_overview' ),
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
	 * GET /dashboard/reports/overview
	 *
	 * Returns summary metrics for three periods:
	 * this_week, this_month, all_time.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_overview( $request ) {
		$tz  = new DateTimeZone( 'Europe/London' );
		$now = new DateTimeImmutable( 'now', $tz );

		// This week: Monday to Sunday.
		$week_start = $now->modify( 'monday this week' )->format( 'Y-m-d' );
		$week_end   = $now->modify( 'sunday this week' )->format( 'Y-m-d' );

		// This month: first to last day.
		$month_start = $now->format( 'Y-m-01' );
		$month_end   = $now->format( 'Y-m-t' );

		$periods = array(
			'this_week'  => array( $week_start, $week_end ),
			'this_month' => array( $month_start, $month_end ),
		);

		$result = array();

		foreach ( $periods as $key => $dates ) {
			$result[ $key ] = $this->get_period_metrics( $dates[0], $dates[1] );
		}

		// All time — no date filter.
		$result['all_time'] = $this->get_period_metrics( null, null );

		// Revenue trend for bar chart.
		$result['revenue_trend'] = array(
			'this_week'  => $this->get_daily_revenue( $week_start, $week_end ),
			'this_month' => $this->get_weekly_revenue( $month_start, $month_end ),
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}

	/**
	 * Get summary metrics for a date range.
	 *
	 * @param string|null $date_from Start date YYYY-MM-DD.
	 * @param string|null $date_to End date YYYY-MM-DD.
	 * @return array
	 */
	private function get_period_metrics( $date_from, $date_to ) {
		global $wpdb;

		$has_dates = ( null !== $date_from && null !== $date_to );

		// Total bookings (exclude cancelled).
		if ( $has_dates ) {
			$total_bookings = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
					WHERE status != 'cancelled'
					AND deleted_at IS NULL
					AND booking_date BETWEEN %s AND %s",
					$date_from,
					$date_to
				)
			);
		} else {
			$total_bookings = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE status != 'cancelled'
				AND deleted_at IS NULL"
			);
		}

		// Total revenue from completed payments.
		if ( $has_dates ) {
			$total_revenue = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(p.amount), 0)
					FROM {$wpdb->prefix}bookings_payments p
					INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
					WHERE p.payment_status = 'completed'
					AND b.deleted_at IS NULL
					AND b.booking_date BETWEEN %s AND %s",
					$date_from,
					$date_to
				)
			);
		} else {
			$total_revenue = (float) $wpdb->get_var(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
				AND b.deleted_at IS NULL"
			);
		}

		// No-show count.
		if ( $has_dates ) {
			$no_show_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
					WHERE status = 'no_show'
					AND deleted_at IS NULL
					AND booking_date BETWEEN %s AND %s",
					$date_from,
					$date_to
				)
			);
		} else {
			$no_show_count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE status = 'no_show'
				AND deleted_at IS NULL"
			);
		}

		// Denominator for no-show rate.
		if ( $has_dates ) {
			$total_for_rate = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
					WHERE status IN ('completed', 'no_show', 'confirmed')
					AND deleted_at IS NULL
					AND booking_date BETWEEN %s AND %s",
					$date_from,
					$date_to
				)
			);
		} else {
			$total_for_rate = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE status IN ('completed', 'no_show', 'confirmed')
				AND deleted_at IS NULL"
			);
		}

		// Cancellation count.
		if ( $has_dates ) {
			$cancellation_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
					WHERE status = 'cancelled'
					AND deleted_at IS NULL
					AND booking_date BETWEEN %s AND %s",
					$date_from,
					$date_to
				)
			);
		} else {
			$cancellation_count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE status = 'cancelled'
				AND deleted_at IS NULL"
			);
		}

		// Calculate rates.
		$no_show_rate = $total_for_rate > 0
			? round( ( $no_show_count / $total_for_rate ) * 100, 1 )
			: 0.0;

		$cancellation_denom = $total_bookings + $cancellation_count;
		$cancellation_rate  = $cancellation_denom > 0
			? round( ( $cancellation_count / $cancellation_denom ) * 100, 1 )
			: 0.0;

		return array(
			'total_bookings'    => $total_bookings,
			'total_revenue'     => $total_revenue,
			'no_show_rate'      => $no_show_rate,
			'cancellation_rate' => $cancellation_rate,
		);
	}

	/**
	 * Get daily revenue for a date range.
	 *
	 * @param string $date_from Start date YYYY-MM-DD.
	 * @param string $date_to End date YYYY-MM-DD.
	 * @return array
	 */
	private function get_daily_revenue( $date_from, $date_to ) {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';
		$payments_table = $wpdb->prefix . 'bookings_payments';

		$query = "
			SELECT DATE(b.booking_date) AS date,
				COALESCE(SUM(p.amount), 0) AS revenue
			FROM {$bookings_table} b
			LEFT JOIN {$payments_table} p
				ON p.booking_id = b.id AND p.payment_status = 'completed'
			WHERE b.booking_date BETWEEN %s AND %s
				AND b.deleted_at IS NULL
				AND b.status != 'cancelled'
			GROUP BY DATE(b.booking_date)
			ORDER BY DATE(b.booking_date) ASC
		";

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$query,
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$revenue_by_date = array();
		foreach ( $rows as $row ) {
			$revenue_by_date[ $row['date'] ] = (float) $row['revenue'];
		}

		$tz        = new DateTimeZone( 'Europe/London' );
		$start     = new DateTimeImmutable( $date_from, $tz );
		$end       = new DateTimeImmutable( $date_to, $tz );
		$end_plus  = $end->modify( '+1 day' );
		$interval  = new DateInterval( 'P1D' );
		$period    = new DatePeriod( $start, $interval, $end_plus );
		$formatted = array();

		foreach ( $period as $date_obj ) {
			$date_key    = $date_obj->format( 'Y-m-d' );
			$formatted[] = array(
				'date'    => $date_key,
				'revenue' => isset( $revenue_by_date[ $date_key ] ) ? (float) $revenue_by_date[ $date_key ] : 0.0,
			);
		}

		return $formatted;
	}

	/**
	 * Get weekly revenue buckets for a month range.
	 *
	 * @param string $date_from Start date YYYY-MM-DD.
	 * @param string $date_to End date YYYY-MM-DD.
	 * @return array
	 */
	private function get_weekly_revenue( $date_from, $date_to ) {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';
		$payments_table = $wpdb->prefix . 'bookings_payments';

		$query = "
			SELECT WEEK(b.booking_date, 1) AS week_num,
				MIN(b.booking_date) AS week_start,
				COALESCE(SUM(p.amount), 0) AS revenue
			FROM {$bookings_table} b
			LEFT JOIN {$payments_table} p
				ON p.booking_id = b.id AND p.payment_status = 'completed'
			WHERE b.booking_date BETWEEN %s AND %s
				AND b.deleted_at IS NULL
				AND b.status != 'cancelled'
			GROUP BY WEEK(b.booking_date, 1)
			ORDER BY week_num ASC
		";

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$query,
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$formatted = array();
		$index     = 1;

		foreach ( $rows as $row ) {
			$formatted[] = array(
				'week_label' => 'Week ' . $index,
				'revenue'    => (float) $row['revenue'],
			);
			++$index;
		}

		return $formatted;
	}
}
