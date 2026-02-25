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

		// Revenue report.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/reports/revenue',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_revenue_report' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'date_from' => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'   => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Booking analytics report.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/reports/analytics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_booking_analytics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'date_from' => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'   => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							if ( empty( $param ) ) {
								return true;
							}
							$timezone = new DateTimeZone( 'Europe/London' );
							$date     = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $param, $timezone );
							return $date && $date->format( 'Y-m-d' ) === $param;
						},
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Revenue CSV export.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/reports/revenue/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_revenue_csv' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'date_from' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'date_to'   => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
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
	 * GET /dashboard/reports/revenue
	 *
	 * Returns revenue report data for selected date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_revenue_report( $request ) {
		global $wpdb;

		$tz  = new DateTimeZone( 'Europe/London' );
		$now = new DateTimeImmutable( 'now', $tz );

		$date_from_param = $request->get_param( 'date_from' );
		$date_to_param   = $request->get_param( 'date_to' );

		$date_from = ! empty( $date_from_param ) ? sanitize_text_field( $date_from_param ) : $now->format( 'Y-m-01' );
		$date_to   = ! empty( $date_to_param ) ? sanitize_text_field( $date_to_param ) : $now->format( 'Y-m-d' );

		$total_revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type != 'refund'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$deposits = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type = 'deposit'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$balance_payments = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type = 'full_payment'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$refunds = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(p.refund_amount), 0)
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status IN ('refunded', 'partially_refunded')
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$net_revenue = (float) $total_revenue - (float) $refunds;

		$by_service = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					s.name AS service_name,
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS total_revenue,
					CASE WHEN COUNT(DISTINCT b.id) > 0
						THEN COALESCE(SUM(p.amount), 0) / COUNT(DISTINCT b.id)
						ELSE 0 END AS avg_price
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_services s ON s.id = b.service_id
				LEFT JOIN {$wpdb->prefix}bookings_payments p
					ON p.booking_id = b.id AND p.payment_status = 'completed' AND p.payment_type != 'refund'
				WHERE b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL
					AND b.status != 'cancelled'
				GROUP BY b.service_id, s.name
				ORDER BY total_revenue DESC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$by_staff = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					CONCAT(st.first_name, ' ', st.last_name) AS staff_name,
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS total_revenue,
					CASE WHEN COUNT(DISTINCT b.id) > 0
						THEN COALESCE(SUM(p.amount), 0) / COUNT(DISTINCT b.id)
						ELSE 0 END AS avg_per_booking
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_staff st ON st.id = b.staff_id
				LEFT JOIN {$wpdb->prefix}bookings_payments p
					ON p.booking_id = b.id AND p.payment_status = 'completed' AND p.payment_type != 'refund'
				WHERE b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL
					AND b.status != 'cancelled'
				GROUP BY b.staff_id, st.first_name, st.last_name
				ORDER BY total_revenue DESC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$by_method = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.payment_method,
					COUNT(DISTINCT b.id) AS booking_count,
					COALESCE(SUM(p.amount), 0) AS total_revenue
				FROM {$wpdb->prefix}bookings_payments p
				INNER JOIN {$wpdb->prefix}bookings b ON b.id = p.booking_id
				WHERE p.payment_status = 'completed'
					AND p.payment_type != 'refund'
					AND b.booking_date BETWEEN %s AND %s
					AND b.deleted_at IS NULL
				GROUP BY p.payment_method
				ORDER BY total_revenue DESC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$formatted_by_service = array_map(
			function ( $row ) {
				return array(
					'service_name'   => isset( $row['service_name'] ) ? (string) $row['service_name'] : '',
					'booking_count'  => (int) $row['booking_count'],
					'total_revenue'  => (float) $row['total_revenue'],
					'avg_price'      => (float) $row['avg_price'],
				);
			},
			$by_service
		);

		$formatted_by_staff = array_map(
			function ( $row ) {
				return array(
					'staff_name'       => isset( $row['staff_name'] ) ? (string) $row['staff_name'] : '',
					'booking_count'    => (int) $row['booking_count'],
					'total_revenue'    => (float) $row['total_revenue'],
					'avg_per_booking'  => (float) $row['avg_per_booking'],
				);
			},
			$by_staff
		);

		$formatted_by_method = array_map(
			function ( $row ) {
				return array(
					'payment_method' => isset( $row['payment_method'] ) ? (string) $row['payment_method'] : '',
					'booking_count'  => (int) $row['booking_count'],
					'total_revenue'  => (float) $row['total_revenue'],
				);
			},
			$by_method
		);

		$revenue_trend = $this->get_daily_revenue( $date_from, $date_to );
		$today         = $now->format( 'Y-m-d' );

		return rest_ensure_response(
			array(
				'success'           => true,
				'date_from'         => $date_from,
				'date_to'           => $date_to,
				'is_today_in_range' => ( $today >= $date_from && $today <= $date_to ),
				'summary'           => array(
					'total_revenue'    => (float) $total_revenue,
					'deposits'         => (float) $deposits,
					'balance_payments' => (float) $balance_payments,
					'refunds'          => (float) $refunds,
					'net_revenue'      => (float) $net_revenue,
				),
				'by_service'        => $formatted_by_service,
				'by_staff'          => $formatted_by_staff,
				'by_payment_method' => $formatted_by_method,
				'revenue_trend'     => $revenue_trend,
			)
		);
	}

	/**
	 * GET /dashboard/reports/analytics
	 *
	 * Returns booking analytics for selected date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_booking_analytics( $request ) {
		global $wpdb;

		$tz  = new DateTimeZone( 'Europe/London' );
		$now = new DateTimeImmutable( 'now', $tz );

		$date_from_param = $request->get_param( 'date_from' );
		$date_to_param   = $request->get_param( 'date_to' );

		$date_from = ! empty( $date_from_param ) ? sanitize_text_field( $date_from_param ) : $now->modify( '-30 days' )->format( 'Y-m-d' );
		$date_to   = ! empty( $date_to_param ) ? sanitize_text_field( $date_to_param ) : $now->format( 'Y-m-d' );

		if ( $date_from > $date_to ) {
			return new WP_Error(
				'invalid_date_range',
				__( 'Start date must be on or before end date.', 'bookit-booking-system' ),
				array( 'status' => 400 )
			);
		}

		$bookings_table = $wpdb->prefix . 'bookings';

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s AND deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bookings_table}
				WHERE status = 'completed' AND booking_date BETWEEN %s AND %s AND deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$cancelled = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bookings_table}
				WHERE status = 'cancelled' AND booking_date BETWEEN %s AND %s AND deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$no_show = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$bookings_table}
				WHERE status = 'no_show' AND booking_date BETWEEN %s AND %s AND deleted_at IS NULL",
				$date_from,
				$date_to
			)
		);

		$completion_rate   = $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 0.0;
		$cancellation_rate = $total > 0 ? round( ( $cancelled / $total ) * 100, 1 ) : 0.0;
		$no_show_rate      = $total > 0 ? round( ( $no_show / $total ) * 100, 1 ) : 0.0;

		$by_dow = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(booking_date) AS dow_mysql,
					COUNT(*) AS booking_count
				FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
					AND status NOT IN ('cancelled')
				GROUP BY DAYOFWEEK(booking_date)
				ORDER BY DAYOFWEEK(booking_date) ASC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$day_labels = array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' );
		$dow_to_uk  = array(
			2 => 0,
			3 => 1,
			4 => 2,
			5 => 3,
			6 => 4,
			7 => 5,
			1 => 6,
		);
		$dow_data   = array_fill( 0, 7, 0 );

		foreach ( $by_dow as $row ) {
			$dow_mysql = (int) $row['dow_mysql'];
			if ( isset( $dow_to_uk[ $dow_mysql ] ) ) {
				$dow_data[ $dow_to_uk[ $dow_mysql ] ] = (int) $row['booking_count'];
			}
		}

		$by_hour = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					HOUR(start_time) AS hour,
					COUNT(*) AS booking_count
				FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
					AND status NOT IN ('cancelled')
				GROUP BY HOUR(start_time)
				ORDER BY HOUR(start_time) ASC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$hour_labels = array();
		$hour_data   = array();
		for ( $hour = 7; $hour <= 21; $hour++ ) {
			$hour_labels[] = sprintf( '%02d:00', $hour );
			$hour_data[]   = 0;
		}

		foreach ( $by_hour as $row ) {
			$hour = (int) $row['hour'];
			if ( $hour >= 7 && $hour <= 21 ) {
				$hour_data[ $hour - 7 ] = (int) $row['booking_count'];
			}
		}

		$heatmap_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(booking_date) AS dow_mysql,
					HOUR(start_time) AS hour,
					COUNT(*) AS booking_count
				FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
					AND status NOT IN ('cancelled')
				GROUP BY DAYOFWEEK(booking_date), HOUR(start_time)",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$heatmap_lookup = array();
		foreach ( $heatmap_raw as $row ) {
			$dow_mysql = (int) $row['dow_mysql'];
			$hour      = (int) $row['hour'];
			if ( isset( $dow_to_uk[ $dow_mysql ] ) && $hour >= 7 && $hour <= 21 ) {
				$day_key                    = $day_labels[ $dow_to_uk[ $dow_mysql ] ];
				$hour_key                   = sprintf( '%02d:00', $hour );
				$heatmap_lookup[ $day_key . '|' . $hour_key ] = (int) $row['booking_count'];
			}
		}

		$heatmap = array();
		foreach ( $day_labels as $day_label ) {
			for ( $hour = 7; $hour <= 21; $hour++ ) {
				$hour_key  = sprintf( '%02d:00', $hour );
				$lookup_key = $day_label . '|' . $hour_key;
				$heatmap[] = array(
					'day'   => $day_label,
					'hour'  => $hour_key,
					'count' => isset( $heatmap_lookup[ $lookup_key ] ) ? (int) $heatmap_lookup[ $lookup_key ] : 0,
				);
			}
		}

		$lead_times = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATEDIFF(booking_date, DATE(created_at)) AS lead_days
				FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
					AND status NOT IN ('cancelled')
					AND DATEDIFF(booking_date, DATE(created_at)) >= 0",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$buckets = array(
			'same_day'            => 0,
			'one_to_three'        => 0,
			'four_to_seven'       => 0,
			'eight_to_fourteen'   => 0,
			'fifteen_plus'        => 0,
		);
		$total_lead = 0;
		$sum_lead   = 0;

		foreach ( $lead_times as $row ) {
			$lead_days = (int) $row['lead_days'];
			++$total_lead;
			$sum_lead += $lead_days;

			if ( 0 === $lead_days ) {
				++$buckets['same_day'];
			} elseif ( $lead_days <= 3 ) {
				++$buckets['one_to_three'];
			} elseif ( $lead_days <= 7 ) {
				++$buckets['four_to_seven'];
			} elseif ( $lead_days <= 14 ) {
				++$buckets['eight_to_fourteen'];
			} else {
				++$buckets['fifteen_plus'];
			}
		}

		$avg_lead_days = $total_lead > 0 ? round( $sum_lead / $total_lead, 1 ) : 0.0;

		$daily_count_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(booking_date) AS date,
					COUNT(*) AS booking_count
				FROM {$bookings_table}
				WHERE booking_date BETWEEN %s AND %s
					AND deleted_at IS NULL
					AND status NOT IN ('cancelled')
				GROUP BY DATE(booking_date)
				ORDER BY DATE(booking_date) ASC",
				$date_from,
				$date_to
			),
			ARRAY_A
		);

		$count_by_date = array();
		foreach ( $daily_count_rows as $row ) {
			$count_by_date[ $row['date'] ] = (int) $row['booking_count'];
		}

		$start     = new DateTimeImmutable( $date_from, $tz );
		$end       = new DateTimeImmutable( $date_to, $tz );
		$end_plus  = $end->modify( '+1 day' );
		$interval  = new DateInterval( 'P1D' );
		$period    = new DatePeriod( $start, $interval, $end_plus );

		$daily_trend = array();
		foreach ( $period as $date_obj ) {
			$date_key      = $date_obj->format( 'Y-m-d' );
			$daily_trend[] = array(
				'date'  => $date_key,
				'count' => isset( $count_by_date[ $date_key ] ) ? (int) $count_by_date[ $date_key ] : 0,
			);
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'date_from'      => $date_from,
				'date_to'        => $date_to,
				'summary'        => array(
					'total_bookings'    => $total,
					'completed'         => $completed,
					'cancelled'         => $cancelled,
					'no_show'           => $no_show,
					'completion_rate'   => (float) $completion_rate,
					'cancellation_rate' => (float) $cancellation_rate,
					'no_show_rate'      => (float) $no_show_rate,
					'avg_lead_days'     => (float) $avg_lead_days,
				),
				'by_day_of_week' => array(
					'labels' => $day_labels,
					'data'   => $dow_data,
				),
				'by_hour'        => array(
					'labels' => $hour_labels,
					'data'   => $hour_data,
				),
				'heatmap'        => $heatmap,
				'lead_time'      => array(
					'avg_days' => (float) $avg_lead_days,
					'buckets'  => $buckets,
				),
				'daily_trend'    => $daily_trend,
			)
		);
	}

	/**
	 * GET /dashboard/reports/revenue/export
	 *
	 * Export revenue report as CSV.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_revenue_csv( $request ) {
		$tz  = new DateTimeZone( 'Europe/London' );
		$now = new DateTimeImmutable( 'now', $tz );

		$date_from_param = $request->get_param( 'date_from' );
		$date_to_param   = $request->get_param( 'date_to' );

		$date_from = ! empty( $date_from_param ) ? sanitize_text_field( $date_from_param ) : $now->format( 'Y-m-01' );
		$date_to   = ! empty( $date_to_param ) ? sanitize_text_field( $date_to_param ) : $now->format( 'Y-m-d' );

		$report_response = $this->get_revenue_report( $request );
		if ( is_wp_error( $report_response ) ) {
			return $report_response;
		}
		$report_data = rest_ensure_response( $report_response )->get_data();

		$handle = fopen( 'php://temp', 'r+' );

		fputcsv(
			$handle,
			array(
				__( 'Date From', 'bookit-booking-system' ),
				__( 'Date To', 'bookit-booking-system' ),
				__( 'Total Revenue', 'bookit-booking-system' ),
				__( 'Deposits', 'bookit-booking-system' ),
				__( 'Balance Payments', 'bookit-booking-system' ),
				__( 'Refunds', 'bookit-booking-system' ),
				__( 'Net Revenue', 'bookit-booking-system' ),
			)
		);

		fputcsv(
			$handle,
			array(
				$date_from,
				$date_to,
				(float) $report_data['summary']['total_revenue'],
				(float) $report_data['summary']['deposits'],
				(float) $report_data['summary']['balance_payments'],
				(float) $report_data['summary']['refunds'],
				(float) $report_data['summary']['net_revenue'],
			)
		);

		fputcsv( $handle, array() );
		fputcsv(
			$handle,
			array(
				__( 'Service Name', 'bookit-booking-system' ),
				__( 'Bookings', 'bookit-booking-system' ),
				__( 'Total Revenue', 'bookit-booking-system' ),
				__( 'Avg Price', 'bookit-booking-system' ),
			)
		);

		foreach ( $report_data['by_service'] as $service_row ) {
			fputcsv(
				$handle,
				array(
					$service_row['service_name'],
					(int) $service_row['booking_count'],
					(float) $service_row['total_revenue'],
					(float) $service_row['avg_price'],
				)
			);
		}

		rewind( $handle );
		$csv_string = stream_get_contents( $handle );
		fclose( $handle );

		$filename = 'revenue-report-' . $date_from . '-to-' . $date_to . '.csv';
		$response = new WP_REST_Response( $csv_string );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' );

		return $response;
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
