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

		// Get staff list.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_staff_list' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'search'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'role'       => array(
						'type'    => 'string',
						'enum'    => array( 'admin', 'staff', 'all' ),
						'default' => 'all',
					),
					'status'     => array(
						'type'    => 'string',
						'enum'    => array( 'active', 'inactive', 'all' ),
						'default' => 'all',
					),
					'service_id' => array(
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
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

		// Get/Update/Delete single staff.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_staff_details' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_staff' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'email'               => array(
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => function ( $param ) {
								return is_email( $param );
							},
						),
						'first_name'          => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'last_name'           => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'phone'               => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'photo_url'           => array(
							'type'              => 'string',
							'sanitize_callback' => 'esc_url_raw',
						),
						'bio'                 => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'title'               => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'role'                => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array( 'staff', 'admin' ),
						),
						'google_calendar_id'  => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'is_active'           => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'display_order'       => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'service_assignments' => array(
							'type'              => 'array',
							'default'           => array(),
							'sanitize_callback' => function ( $param ) {
								// Allow the array through, we'll validate in the method.
								return is_array( $param ) ? $param : array();
							},
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_staff' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Create new staff.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_staff' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'email'               => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
					),
					'password'            => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return strlen( $param ) >= 8;
						},
					),
					'first_name'          => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'phone'               => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'photo_url'           => array(
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
					'bio'                 => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'title'               => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'role'                => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'staff', 'admin' ),
					),
					'google_calendar_id'  => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'is_active'           => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'display_order'       => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'service_assignments' => array(
						'type'              => 'array',
						'default'           => array(),
						'sanitize_callback' => function ( $param ) {
							// Allow the array through, we'll validate in the method.
							return is_array( $param ) ? $param : array();
						},
					),
				),
			)
		);

		// Reset staff password.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/(?P<id>\d+)/reset-password',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_staff_password' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'new_password' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return strlen( $param ) >= 8;
						},
					),
					'send_email'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		// Reorder staff.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/staff/reorder',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reorder_staff' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'staff' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'id'            => array( 'type' => 'integer' ),
								'display_order' => array( 'type' => 'integer' ),
							),
						),
					),
				),
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
				'args'                => array(
					'search'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'category_id' => array(
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'status'      => array(
						'type'    => 'string',
						'enum'    => array( 'active', 'inactive', 'all' ),
						'default' => 'all',
					),
					'page'        => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page'    => array(
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			)
		);

		// Get/Update/Delete single service.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/services/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_service_details' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_service' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'name'           => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'    => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'duration'       => array(
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						),
						'price'          => array(
							'required'          => true,
							'type'              => 'number',
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && $param >= 0;
							},
						),
						'deposit_amount' => array(
							'type'              => 'number',
							'validate_callback' => function ( $param ) {
								return null === $param || ( is_numeric( $param ) && $param >= 0 );
							},
						),
						'deposit_type'   => array(
							'type'    => 'string',
							'enum'    => array( 'fixed', 'percentage' ),
							'default' => 'fixed',
						),
						'buffer_before'  => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'buffer_after'   => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'category_ids'   => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'integer',
							),
						),
						'is_active'      => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'display_order'  => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_service' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Create new service.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/services/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_service' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'name'           => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description'    => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'duration'       => array(
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
					'price'          => array(
						'required'          => true,
						'type'              => 'number',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param >= 0;
						},
					),
					'deposit_amount' => array(
						'type'              => 'number',
						'validate_callback' => function ( $param ) {
							return null === $param || ( is_numeric( $param ) && $param >= 0 );
						},
					),
					'deposit_type'   => array(
						'type'    => 'string',
						'enum'    => array( 'fixed', 'percentage' ),
						'default' => 'fixed',
					),
					'buffer_before'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'buffer_after'   => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'category_ids'   => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
					'is_active'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'display_order'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		// Update display order for multiple services.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/services/reorder',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reorder_services' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'services' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'id'            => array( 'type' => 'integer' ),
								'display_order' => array( 'type' => 'integer' ),
							),
						),
					),
				),
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

		// Get single booking details.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_booking_details' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
			)
		);

		// Update booking.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/(?P<id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_booking' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'service_id'        => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'staff_id'          => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'booking_date'      => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
						},
					),
					'booking_time'      => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $param );
						},
					),
					'status'            => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							$valid_statuses = array( 'pending', 'pending_payment', 'confirmed', 'completed', 'cancelled', 'no_show' );
							return in_array( $param, $valid_statuses, true );
						},
					),
					'payment_method'    => array(
						'required'          => true,
					),
					'amount_paid'       => array(
						'default'           => 0,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param >= 0;
						},
					),
					'special_requests'  => array(
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'staff_notes'       => array(
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'send_notification' => array(
						'default'           => false,
						'validate_callback' => function ( $param ) {
							return is_bool( $param ) || in_array( $param, array( 'true', 'false', '1', '0', 1, 0 ), true );
						},
					),
				),
			)
		);

		// Cancel booking.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/bookings/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_booking' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'cancellation_reason' => array(
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'send_notification'   => array(
						'default'           => true,
						'validate_callback' => function ( $param ) {
							return is_bool( $param ) || in_array( $param, array( 'true', 'false', '1', '0', 1, 0 ), true );
						},
					),
				),
			)
		);

		// Get categories list for dropdowns.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/categories/list',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_categories_list' ),
				'permission_callback' => array( $this, 'check_dashboard_permission' ),
				'args'                => array(
					'search'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'      => array(
						'type'    => 'string',
						'enum'    => array( 'active', 'inactive', 'all' ),
						'default' => 'all',
					),
					'include_all' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		// Get/Update/Delete single category.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/categories/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_category_details' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_category' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'name'          => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'is_active'     => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'display_order' => array(
							'type'    => 'integer',
							'default' => 0,
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_category' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		// Create new category.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/categories/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_category' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'name'          => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'description'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'is_active'     => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'display_order' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		// Reorder categories.
		register_rest_route(
			self::NAMESPACE,
			'/dashboard/categories/reorder',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reorder_categories' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'categories' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'id'            => array( 'type' => 'integer' ),
								'display_order' => array( 'type' => 'integer' ),
							),
						),
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
	 * Get staff list with filters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_staff_list( $request ) {
		global $wpdb;

		// Get query parameters.
		$search     = $request->get_param( 'search' );
		$role       = $request->get_param( 'role' );
		$status     = $request->get_param( 'status' );
		$service_id = $request->get_param( 'service_id' );

		// Build WHERE clauses.
		$where_clauses = array( 'st.deleted_at IS NULL' );
		$where_params  = array();

		// Search filter.
		if ( ! empty( $search ) ) {
			$where_clauses[] = '(st.first_name LIKE %s OR st.last_name LIKE %s OR st.email LIKE %s OR st.title LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( $search ) . '%';
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
		}

		// Role filter.
		if ( 'admin' === $role ) {
			$where_clauses[] = "st.role = 'admin'";
		} elseif ( 'staff' === $role ) {
			$where_clauses[] = "st.role = 'staff'";
		}

		// Status filter.
		if ( 'active' === $status ) {
			$where_clauses[] = 'st.is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where_clauses[] = 'st.is_active = 0';
		}

		// Service filter.
		if ( ! empty( $service_id ) ) {
			$where_clauses[] = 'EXISTS (
				SELECT 1 FROM ' . $wpdb->prefix . 'bookings_staff_services ss2
				WHERE ss2.staff_id = st.id
				AND ss2.service_id = %d
			)';
			$where_params[]  = (int) $service_id;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get staff with service count and working hours status.
		$query = "SELECT
					st.id,
					st.email,
					st.first_name,
					st.last_name,
					CONCAT(st.first_name, ' ', st.last_name) as full_name,
					st.phone,
					st.photo_url,
					st.bio,
					st.title,
					st.role,
					st.google_calendar_id,
					st.is_active,
					st.display_order,
					st.created_at,
					st.updated_at,
					COUNT(DISTINCT ss.service_id) as service_count,
					COUNT(DISTINCT wh.id) as working_hours_count,
					COUNT(DISTINCT CASE WHEN b.booking_date >= CURDATE() AND b.deleted_at IS NULL THEN b.id END) as future_bookings_count
				FROM {$wpdb->prefix}bookings_staff st
				LEFT JOIN {$wpdb->prefix}bookings_staff_services ss ON st.id = ss.staff_id
				LEFT JOIN {$wpdb->prefix}bookings_staff_working_hours wh ON st.id = wh.staff_id AND wh.is_working = 1
				LEFT JOIN {$wpdb->prefix}bookings b ON st.id = b.staff_id
				WHERE $where_sql
				GROUP BY st.id
				ORDER BY st.display_order ASC, st.first_name ASC, st.last_name ASC";

		if ( ! empty( $where_params ) ) {
			$query = $wpdb->prepare( $query, $where_params );
		}

		$staff_list = $wpdb->get_results( $query, ARRAY_A );

		// Process each staff member.
		foreach ( $staff_list as &$staff ) {
			$staff['id']                    = (int) $staff['id'];
			$staff['display_order']         = (int) $staff['display_order'];
			$staff['is_active']             = (bool) $staff['is_active'];
			$staff['service_count']         = (int) $staff['service_count'];
			$staff['working_hours_count']   = (int) $staff['working_hours_count'];
			$staff['future_bookings_count'] = (int) $staff['future_bookings_count'];
			$staff['has_working_hours']     = $staff['working_hours_count'] > 0;

			// Remove password hash from response.
			unset( $staff['password_hash'] );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'staff'   => $staff_list,
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
	 * Get single staff details.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_staff_details( $request ) {
		global $wpdb;

		$staff_id = (int) $request->get_param( 'id' );

		// Get staff with counts.
		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					st.*,
					CONCAT(st.first_name, ' ', st.last_name) as full_name,
					COUNT(DISTINCT ss.service_id) as service_count,
					COUNT(DISTINCT wh.id) as working_hours_count,
					COUNT(DISTINCT CASE WHEN b.booking_date >= CURDATE() AND b.deleted_at IS NULL THEN b.id END) as future_bookings_count
				FROM {$wpdb->prefix}bookings_staff st
				LEFT JOIN {$wpdb->prefix}bookings_staff_services ss ON st.id = ss.staff_id
				LEFT JOIN {$wpdb->prefix}bookings_staff_working_hours wh ON st.id = wh.staff_id AND wh.is_working = 1
				LEFT JOIN {$wpdb->prefix}bookings b ON st.id = b.staff_id
				WHERE st.id = %d
				AND st.deleted_at IS NULL
				GROUP BY st.id",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $staff ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		// Get service assignments with custom pricing.
		$service_assignments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ss.service_id,
					ss.custom_price,
					s.name as service_name,
					s.price as base_price
				FROM {$wpdb->prefix}bookings_staff_services ss
				INNER JOIN {$wpdb->prefix}bookings_services s ON ss.service_id = s.id
				WHERE ss.staff_id = %d
				AND s.deleted_at IS NULL
				ORDER BY s.name",
				$staff_id
			),
			ARRAY_A
		);

		// Process service assignments.
		foreach ( $service_assignments as &$assignment ) {
			$assignment['service_id']   = (int) $assignment['service_id'];
			$assignment['custom_price'] = $assignment['custom_price'] ? (float) $assignment['custom_price'] : null;
			$assignment['base_price']   = (float) $assignment['base_price'];
		}

		// Convert numeric fields.
		$staff['id']                    = (int) $staff['id'];
		$staff['display_order']         = (int) $staff['display_order'];
		$staff['is_active']             = (bool) $staff['is_active'];
		$staff['service_count']         = (int) $staff['service_count'];
		$staff['working_hours_count']   = (int) $staff['working_hours_count'];
		$staff['future_bookings_count'] = (int) $staff['future_bookings_count'];
		$staff['has_working_hours']     = $staff['working_hours_count'] > 0;
		$staff['service_assignments']   = $service_assignments;

		// Remove password hash.
		unset( $staff['password_hash'] );

		return rest_ensure_response(
			array(
				'success' => true,
				'staff'   => $staff,
			)
		);
	}

	/**
	 * Create new staff member.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_staff( $request ) {
		global $wpdb;

		$email = $request->get_param( 'email' );

		// Check for duplicate email.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff
				WHERE email = %s AND deleted_at IS NULL",
				$email
			)
		);

		if ( $existing ) {
			return new WP_Error(
				'duplicate_email',
				'A staff member with this email already exists.',
				array( 'status' => 409 )
			);
		}

		// Hash password.
		$password      = $request->get_param( 'password' );
		$password_hash = password_hash( $password, PASSWORD_DEFAULT );

		// Insert staff.
		$result = $wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			array(
				'email'              => $email,
				'password_hash'      => $password_hash,
				'first_name'         => $request->get_param( 'first_name' ),
				'last_name'          => $request->get_param( 'last_name' ),
				'phone'              => $request->get_param( 'phone' ),
				'photo_url'          => $request->get_param( 'photo_url' ),
				'bio'                => $request->get_param( 'bio' ),
				'title'              => $request->get_param( 'title' ),
				'role'               => $request->get_param( 'role' ),
				'google_calendar_id' => $request->get_param( 'google_calendar_id' ),
				'is_active'          => filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN ) ? 1 : 0,
				'display_order'      => (int) $request->get_param( 'display_order' ),
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'creation_failed',
				'Failed to create staff member.',
				array( 'status' => 500 )
			);
		}

		$staff_id = $wpdb->insert_id;

		// Insert service assignments.
		$service_assignments = $request->get_param( 'service_assignments' );
		if ( ! empty( $service_assignments ) ) {
			foreach ( $service_assignments as $assignment ) {
				$wpdb->insert(
					$wpdb->prefix . 'bookings_staff_services',
					array(
						'staff_id'     => $staff_id,
						'service_id'   => (int) $assignment['service_id'],
						'custom_price' => isset( $assignment['custom_price'] ) ? (float) $assignment['custom_price'] : null,
						'created_at'   => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%f', '%s' )
				);
			}
		}

		// Get created staff.
		$get_request = new WP_REST_Request( 'GET', self::NAMESPACE . "/dashboard/staff/{$staff_id}" );
		$get_request->set_url_params( array( 'id' => $staff_id ) );
		$staff_response = $this->get_staff_details( $get_request );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Staff member created successfully.',
				'staff'   => $staff_response->data['staff'],
			)
		);
	}

	/**
	 * Update existing staff member.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_staff( $request ) {
		global $wpdb;

		$staff_id = (int) $request->get_param( 'id' );

		// Check if staff exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		$email = $request->get_param( 'email' );

		// Check for duplicate email (excluding current staff).
		$duplicate = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_staff
				WHERE email = %s AND id != %d AND deleted_at IS NULL",
				$email,
				$staff_id
			)
		);

		if ( $duplicate ) {
			return new WP_Error(
				'duplicate_email',
				'A staff member with this email already exists.',
				array( 'status' => 409 )
			);
		}

		// Update staff.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'email'              => $email,
				'first_name'         => $request->get_param( 'first_name' ),
				'last_name'          => $request->get_param( 'last_name' ),
				'phone'              => $request->get_param( 'phone' ),
				'photo_url'          => $request->get_param( 'photo_url' ),
				'bio'                => $request->get_param( 'bio' ),
				'title'              => $request->get_param( 'title' ),
				'role'               => $request->get_param( 'role' ),
				'google_calendar_id' => $request->get_param( 'google_calendar_id' ),
				'is_active'          => filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN ) ? 1 : 0,
				'display_order'      => (int) $request->get_param( 'display_order' ),
				'updated_at'         => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update staff member.',
				array( 'status' => 500 )
			);
		}

		// Delete existing service assignments.
		$wpdb->delete(
			$wpdb->prefix . 'bookings_staff_services',
			array( 'staff_id' => $staff_id ),
			array( '%d' )
		);

		// Insert new service assignments.
		$service_assignments = $request->get_param( 'service_assignments' );
		if ( ! empty( $service_assignments ) ) {
			foreach ( $service_assignments as $assignment ) {
				$wpdb->insert(
					$wpdb->prefix . 'bookings_staff_services',
					array(
						'staff_id'     => $staff_id,
						'service_id'   => (int) $assignment['service_id'],
						'custom_price' => isset( $assignment['custom_price'] ) ? (float) $assignment['custom_price'] : null,
						'created_at'   => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%f', '%s' )
				);
			}
		}

		// Get updated staff.
		$staff_response = $this->get_staff_details( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Staff member updated successfully.',
				'staff'   => $staff_response->data['staff'],
			)
		);
	}

	/**
	 * Delete staff member (soft delete).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_staff( $request ) {
		global $wpdb;

		$staff_id = (int) $request->get_param( 'id' );

		// Check if staff exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, first_name, last_name FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		// Check for future bookings.
		$future_bookings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE staff_id = %d
				AND booking_date >= CURDATE()
				AND deleted_at IS NULL
				AND status NOT IN ('cancelled', 'no_show')",
				$staff_id
			)
		);

		if ( $future_bookings > 0 ) {
			return new WP_Error(
				'staff_has_bookings',
				sprintf(
					'Cannot delete %s %s because they have %d future booking(s). Please reassign or cancel these bookings first, or deactivate the staff member instead.',
					$existing['first_name'],
					$existing['last_name'],
					$future_bookings
				),
				array( 'status' => 409 )
			);
		}

		// Soft delete the staff member.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'deleted_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'deletion_failed',
				'Failed to delete staff member.',
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Staff member deleted successfully.',
			)
		);
	}

	/**
	 * Reorder staff members.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_staff( $request ) {
		global $wpdb;

		$staff = $request->get_param( 'staff' );

		if ( empty( $staff ) ) {
			return new WP_Error(
				'invalid_data',
				'Staff array is required.',
				array( 'status' => 400 )
			);
		}

		// Update display order for each staff member.
		foreach ( $staff as $staff_data ) {
			if ( ! isset( $staff_data['id'] ) || ! isset( $staff_data['display_order'] ) ) {
				continue;
			}

			$wpdb->update(
				$wpdb->prefix . 'bookings_staff',
				array(
					'display_order' => (int) $staff_data['display_order'],
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => (int) $staff_data['id'] ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Staff reordered successfully.',
			)
		);
	}

	/**
	 * Reset staff member password.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset_staff_password( $request ) {
		global $wpdb;

		$staff_id     = (int) $request->get_param( 'id' );
		$new_password = $request->get_param( 'new_password' );
		$send_email   = filter_var( $request->get_param( 'send_email' ), FILTER_VALIDATE_BOOLEAN );

		// Check if staff exists.
		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name FROM {$wpdb->prefix}bookings_staff
				WHERE id = %d AND deleted_at IS NULL",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $staff ) {
			return new WP_Error(
				'staff_not_found',
				'Staff member not found.',
				array( 'status' => 404 )
			);
		}

		// Hash new password.
		$password_hash = password_hash( $new_password, PASSWORD_DEFAULT );

		// Update password.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_staff',
			array(
				'password_hash' => $password_hash,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'reset_failed',
				'Failed to reset password.',
				array( 'status' => 500 )
			);
		}

		// Send email if requested.
		if ( $send_email ) {
			$to      = $staff['email'];
			$subject = 'Your password has been reset';
			$message = sprintf(
				"Hello %s,\n\nYour password has been reset by an administrator.\n\nNew password: %s\n\nPlease log in and change your password.\n\nBooking System",
				$staff['first_name'],
				$new_password
			);

			wp_mail( $to, $subject, $message );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Password reset successfully.',
			)
		);
	}

	/**
	 * Get services list with filters and pagination.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_services_list( $request ) {
		global $wpdb;

		// Get query parameters.
		$search      = $request->get_param( 'search' );
		$category_id = $request->get_param( 'category_id' );
		$status      = $request->get_param( 'status' ); // 'active', 'inactive', 'all'.
		$page        = max( 1, (int) $request->get_param( 'page' ) );
		$per_page    = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) ); // Default 50, max 100.

		if ( ! $per_page ) {
			$per_page = 50;
		}

		$offset = ( $page - 1 ) * $per_page;

		// Build WHERE clauses.
		$where_clauses = array( 's.deleted_at IS NULL' );
		$where_params  = array();

		// Search filter.
		if ( ! empty( $search ) ) {
			$where_clauses[] = '(s.name LIKE %s OR s.description LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( $search ) . '%';
			$where_params[]  = $search_term;
			$where_params[]  = $search_term;
		}

		// Status filter.
		if ( 'active' === $status ) {
			$where_clauses[] = 's.is_active = 1';
		} elseif ( 'inactive' === $status ) {
			$where_clauses[] = 's.is_active = 0';
		}
		// 'all' or null = no status filter.

		// Category filter.
		if ( ! empty( $category_id ) ) {
			$where_clauses[] = 'EXISTS (
				SELECT 1 FROM ' . $wpdb->prefix . 'bookings_service_categories sc2
				WHERE sc2.service_id = s.id
				AND sc2.category_id = %d
			)';
			$where_params[]  = (int) $category_id;
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get total count.
		$count_query = "SELECT COUNT(DISTINCT s.id)
						FROM {$wpdb->prefix}bookings_services s
						WHERE $where_sql";

		if ( ! empty( $where_params ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_params );
		}

		$total = (int) $wpdb->get_var( $count_query );

		// Get services with category information.
		$query = "SELECT
					s.id,
					s.name,
					s.description,
					s.duration,
					s.price,
					s.deposit_amount,
					s.deposit_type,
					s.buffer_before,
					s.buffer_after,
					s.is_active,
					s.display_order,
					s.created_at,
					s.updated_at,
					GROUP_CONCAT(
						DISTINCT CONCAT(c.id, ':', c.name)
						ORDER BY c.name
						SEPARATOR '||'
					) as categories_data
				FROM {$wpdb->prefix}bookings_services s
				LEFT JOIN {$wpdb->prefix}bookings_service_categories sc ON s.id = sc.service_id
				LEFT JOIN {$wpdb->prefix}bookings_categories c ON sc.category_id = c.id AND c.deleted_at IS NULL
				WHERE $where_sql
				GROUP BY s.id
				ORDER BY s.display_order ASC, s.name ASC
				LIMIT %d OFFSET %d";

		$query_params = array_merge( $where_params, array( $per_page, $offset ) );
		$query        = $wpdb->prepare( $query, $query_params );

		$services = $wpdb->get_results( $query, ARRAY_A );

		// Process categories data for each service.
		foreach ( $services as &$service ) {
			$categories = array();

			if ( ! empty( $service['categories_data'] ) ) {
				$categories_raw = explode( '||', $service['categories_data'] );
				foreach ( $categories_raw as $cat_data ) {
					if ( ! empty( $cat_data ) ) {
						list( $cat_id, $cat_name ) = explode( ':', $cat_data, 2 );
						$categories[] = array(
							'id'   => (int) $cat_id,
							'name' => $cat_name,
						);
					}
				}
			}

			$service['categories'] = $categories;
			unset( $service['categories_data'] );

			// Convert numeric fields to proper types.
			$service['id']             = (int) $service['id'];
			$service['duration']       = (int) $service['duration'];
			$service['price']          = (float) $service['price'];
			$service['deposit_amount'] = $service['deposit_amount'] ? (float) $service['deposit_amount'] : null;
			$service['buffer_before']  = (int) $service['buffer_before'];
			$service['buffer_after']   = (int) $service['buffer_after'];
			$service['is_active']      = (bool) $service['is_active'];
			$service['display_order']  = (int) $service['display_order'];
		}

		// Calculate pagination.
		$total_pages = ceil( $total / $per_page );

		return rest_ensure_response(
			array(
				'success'    => true,
				'services'   => $services,
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
	 * Get single service details.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_service_details( $request ) {
		global $wpdb;

		$service_id = (int) $request->get_param( 'id' );

		// Get service with categories.
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					s.*,
					GROUP_CONCAT(
						DISTINCT CONCAT(c.id, ':', c.name)
						ORDER BY c.name
						SEPARATOR '||'
					) as categories_data
				FROM {$wpdb->prefix}bookings_services s
				LEFT JOIN {$wpdb->prefix}bookings_service_categories sc ON s.id = sc.service_id
				LEFT JOIN {$wpdb->prefix}bookings_categories c ON sc.category_id = c.id AND c.deleted_at IS NULL
				WHERE s.id = %d
				AND s.deleted_at IS NULL
				GROUP BY s.id",
				$service_id
			),
			ARRAY_A
		);

		if ( ! $service ) {
			return new WP_Error(
				'service_not_found',
				'Service not found.',
				array( 'status' => 404 )
			);
		}

		// Process categories.
		$categories = array();

		if ( ! empty( $service['categories_data'] ) ) {
			$categories_raw = explode( '||', $service['categories_data'] );
			foreach ( $categories_raw as $cat_data ) {
				if ( ! empty( $cat_data ) ) {
					list( $cat_id, $cat_name ) = explode( ':', $cat_data, 2 );
					$categories[] = array(
						'id'   => (int) $cat_id,
						'name' => $cat_name,
					);
				}
			}
		}

		$service['categories']   = $categories;
		$service['category_ids'] = array_column( $categories, 'id' );
		unset( $service['categories_data'] );

		// Convert numeric fields.
		$service['id']             = (int) $service['id'];
		$service['duration']       = (int) $service['duration'];
		$service['price']          = (float) $service['price'];
		$service['deposit_amount'] = $service['deposit_amount'] ? (float) $service['deposit_amount'] : null;
		$service['buffer_before']  = (int) $service['buffer_before'];
		$service['buffer_after']   = (int) $service['buffer_after'];
		$service['is_active']      = (bool) $service['is_active'];
		$service['display_order']  = (int) $service['display_order'];

		return rest_ensure_response(
			array(
				'success' => true,
				'service' => $service,
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
			'service_id'       => isset( $booking['service_id'] ) ? (int) $booking['service_id'] : null,
			'staff_id'         => isset( $booking['staff_id'] ) ? (int) $booking['staff_id'] : null,
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
			'customer_phone'   => $booking['customer_phone'] ?? null,
			'service_name'     => $booking['service_name'],
			'staff_name'       => $booking['staff_first_name'] . ' ' . $booking['staff_last_name'],
			'is_starting_soon' => $is_starting_soon,
			'has_passed'       => $has_passed,
			'created_at'       => $booking['created_at'] ?? null,
			'updated_at'       => $booking['updated_at'] ?? null,
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
	 * Get single booking details.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_booking_details( $request ) {
		global $wpdb;

		$booking_id = (int) $request->get_param( 'id' );

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		// Get booking with all related data.
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					b.*,
					c.id as customer_id,
					c.first_name AS customer_first_name,
					c.last_name AS customer_last_name,
					c.email AS customer_email,
					c.phone AS customer_phone,
					s.id as service_id,
					s.name AS service_name,
					s.duration as service_duration,
					s.price as service_price,
					st.id as staff_id,
					st.first_name AS staff_first_name,
					st.last_name AS staff_last_name,
					CONCAT(st.first_name, ' ', st.last_name) as staff_name
				FROM {$wpdb->prefix}bookings b
				INNER JOIN {$wpdb->prefix}bookings_customers c ON b.customer_id = c.id
				INNER JOIN {$wpdb->prefix}bookings_services s ON b.service_id = s.id
				INNER JOIN {$wpdb->prefix}bookings_staff st ON b.staff_id = st.id
				WHERE b.id = %d
				AND b.deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return new WP_Error(
				'booking_not_found',
				'Booking not found.',
				array( 'status' => 404 )
			);
		}

		// Permission check: staff can only view their own bookings.
		if ( 'staff' === $current_staff['role'] && (int) $booking['staff_id'] !== (int) $current_staff['id'] ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to view this booking.',
				array( 'status' => 403 )
			);
		}

		// Format booking for response.
		$formatted = $this->format_booking( $booking );

		return rest_ensure_response(
			array(
				'success' => true,
				'booking' => $formatted,
			)
		);
	}

	/**
	 * Update existing booking.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_booking( $request ) {
		global $wpdb;

		$booking_id = (int) $request->get_param( 'id' );

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		// Get existing booking.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'booking_not_found',
				'Booking not found.',
				array( 'status' => 404 )
			);
		}

		// Permission check: staff can only edit their own bookings.
		if ( 'staff' === $current_staff['role'] && (int) $existing['staff_id'] !== (int) $current_staff['id'] ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to edit this booking.',
				array( 'status' => 403 )
			);
		}

		// Get new values.
		$new_service_id = (int) $request->get_param( 'service_id' );
		$new_staff_id   = (int) $request->get_param( 'staff_id' );
		$new_date       = $request->get_param( 'booking_date' );
		$new_time       = $request->get_param( 'booking_time' );
		$new_status     = $request->get_param( 'status' );

		// Check if date/time/staff/service changed - need to verify availability.
		$datetime_changed =
			$existing['booking_date'] !== $new_date ||
			$existing['start_time'] !== $new_time ||
			(int) $existing['staff_id'] !== $new_staff_id ||
			(int) $existing['service_id'] !== $new_service_id;

		if ( $datetime_changed ) {
			// Load datetime model for availability check.
			if ( ! class_exists( 'Bookit_DateTime_Model' ) ) {
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'models/class-datetime-model.php';
			}
			$datetime_model = new Bookit_DateTime_Model();

			// Normalize booking_time to H:i:s for comparison.
			$time_check = $new_time;
			if ( strlen( $time_check ) === 5 ) {
				$time_check .= ':00';
			}

			// Check if new time slot is available.
			// Pass the booking ID to exclude it from conflict checking.
			$slots = $datetime_model->get_available_slots( $new_date, $new_service_id, $new_staff_id, $booking_id );

			if ( empty( $slots ) || ! in_array( $time_check, $slots, true ) ) {
				return new WP_Error(
					'time_not_available',
					'The selected time slot is not available for this staff member.',
					array( 'status' => 400 )
				);
			}
		}

		// Get service details for duration calculation.
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT duration, price FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				$new_service_id
			),
			ARRAY_A
		);

		if ( ! $service ) {
			return new WP_Error(
				'service_not_found',
				'Service not found.',
				array( 'status' => 404 )
			);
		}

		// Calculate end time.
		$start_datetime = new DateTime( $new_date . ' ' . $new_time );
		$end_datetime   = clone $start_datetime;
		$end_datetime->modify( '+' . $service['duration'] . ' minutes' );

		// Calculate payment values.
		$service_price = (float) $service['price'];
		$amount_paid   = (float) $request->get_param( 'amount_paid' );

		// Update booking.
		$update_data = array(
			'service_id'       => $new_service_id,
			'staff_id'         => $new_staff_id,
			'booking_date'     => $new_date,
			'start_time'       => $new_time,
			'end_time'         => $end_datetime->format( 'H:i:s' ),
			'duration'         => (int) $service['duration'],
			'status'           => $new_status,
			'payment_method'   => $request->get_param( 'payment_method' ),
			'special_requests' => $request->get_param( 'special_requests' ),
			'staff_notes'      => $request->get_param( 'staff_notes' ),
			'updated_at'       => current_time( 'mysql' ),
			'total_price'      => $service_price,
			'deposit_paid'     => $amount_paid,
			'balance_due'      => $service_price - $amount_paid,
			'full_amount_paid' => $amount_paid >= $service_price ? 1 : 0,
		);

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			$update_data,
			array( 'id' => $booking_id ),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update booking.',
				array( 'status' => 500 )
			);
		}

		// Send notification email if requested.
		$send_notification = filter_var( $request->get_param( 'send_notification' ), FILTER_VALIDATE_BOOLEAN );

		if ( $send_notification ) {
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
		}

		// Get updated booking for response.
		$updated_booking = $wpdb->get_row(
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
				'message'    => 'Booking updated successfully.',
				'booking'    => $this->format_booking( $updated_booking ),
				'email_sent' => $send_notification,
			)
		);
	}

	/**
	 * Cancel booking.
	 *
	 * Sets status to 'cancelled' and deleted_at timestamp.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_booking( $request ) {
		global $wpdb;

		$booking_id = (int) $request->get_param( 'id' );

		$current_staff = Bookit_Auth::get_current_staff();
		if ( ! $current_staff ) {
			return new WP_Error(
				'unauthorized',
				'Could not retrieve staff information.',
				array( 'status' => 401 )
			);
		}

		// Get existing booking.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'booking_not_found',
				'Booking not found.',
				array( 'status' => 404 )
			);
		}

		// Permission check: staff can only cancel their own bookings.
		if ( 'staff' === $current_staff['role'] && (int) $existing['staff_id'] !== (int) $current_staff['id'] ) {
			return new WP_Error(
				'forbidden',
				'You do not have permission to cancel this booking.',
				array( 'status' => 403 )
			);
		}

		$cancellation_reason = $request->get_param( 'cancellation_reason' );

		// Update booking: set status to cancelled AND soft delete.
		$update_data = array(
			'status'     => 'cancelled',
			'deleted_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$format = array( '%s', '%s', '%s' );

		// Add cancellation reason to staff notes.
		if ( ! empty( $cancellation_reason ) ) {
			$existing_notes    = $existing['staff_notes'] ?? '';
			$cancellation_note = "\n\n[Cancelled " . current_time( 'Y-m-d H:i:s' ) . "]\n" . $cancellation_reason;
			$update_data['staff_notes'] = $existing_notes . $cancellation_note;
			$format[] = '%s';
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			$update_data,
			array( 'id' => $booking_id ),
			$format,
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'cancellation_failed',
				'Failed to cancel booking.',
				array( 'status' => 500 )
			);
		}

		// Send cancellation email if requested.
		$send_notification = filter_var( $request->get_param( 'send_notification' ), FILTER_VALIDATE_BOOLEAN );

		if ( $send_notification ) {
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
			// TODO: Add specific cancellation email template in future.
			// For now, reuse confirmation template.
			$email_sender->send_customer_confirmation( $booking );
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'message'    => 'Booking cancelled successfully.',
				'email_sent' => $send_notification,
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

		// Determine initial status based on settings and payment.
		$require_approval = get_option( 'bookit_require_approval', false );
		$payment_method   = $request->get_param( 'payment_method' );

		if ( $require_approval ) {
			// When approval required, all bookings start as pending.
			$initial_status = 'pending';
		} else {
			// When no approval required, use payment-based logic.
			if ( 'pay_on_arrival' === $payment_method ) {
				$initial_status = 'confirmed';
			} elseif ( in_array( $payment_method, array( 'cash', 'card_external', 'check', 'complimentary' ), true ) ) {
				$initial_status = 'confirmed';
			} elseif ( 'stripe' === $payment_method && (float) $request->get_param( 'amount_paid' ) > 0 ) {
				$initial_status = 'confirmed';
			} else {
				$initial_status = 'pending_payment';
			}
		}

		// Update booking status.
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'status' => $initial_status ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

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

	/**
	 * Get categories list with optional filters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_categories_list( $request ) {
		global $wpdb;

		// Get query parameters.
		$search      = $request->get_param( 'search' );
		$status      = $request->get_param( 'status' ); // 'active', 'inactive', 'all'.
		$include_all = $request->get_param( 'include_all' ); // Include all for dropdowns.

		// Build WHERE clauses.
		$where_clauses = array( 'deleted_at IS NULL' );
		$where_params  = array();

		// If include_all is not set, apply filters.
		if ( ! $include_all ) {
			// Search filter.
			if ( ! empty( $search ) ) {
				$where_clauses[] = '(name LIKE %s OR description LIKE %s)';
				$search_term     = '%' . $wpdb->esc_like( $search ) . '%';
				$where_params[]  = $search_term;
				$where_params[]  = $search_term;
			}

			// Status filter.
			if ( 'active' === $status ) {
				$where_clauses[] = 'is_active = 1';
			} elseif ( 'inactive' === $status ) {
				$where_clauses[] = 'is_active = 0';
			}
		} else {
			// For dropdowns, only show active categories.
			$where_clauses[] = 'is_active = 1';
		}

		$where_sql = implode( ' AND ', $where_clauses );

		// Get categories with service count.
		$query = "SELECT 
					c.id,
					c.name,
					c.description,
					c.display_order,
					c.is_active,
					c.created_at,
					c.updated_at,
					COUNT(DISTINCT sc.service_id) as service_count
				FROM {$wpdb->prefix}bookings_categories c
				LEFT JOIN {$wpdb->prefix}bookings_service_categories sc ON c.id = sc.category_id
				WHERE $where_sql
				GROUP BY c.id
				ORDER BY c.display_order ASC, c.name ASC";

		if ( ! empty( $where_params ) ) {
			$query = $wpdb->prepare( $query, $where_params );
		}

		$categories = $wpdb->get_results( $query, ARRAY_A );

		// Convert numeric fields.
		foreach ( $categories as &$category ) {
			$category['id']            = (int) $category['id'];
			$category['display_order'] = (int) $category['display_order'];
			$category['is_active']     = (bool) $category['is_active'];
			$category['service_count'] = (int) $category['service_count'];
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'categories' => $categories,
			)
		);
	}

	/**
	 * Get single category details.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_category_details( $request ) {
		global $wpdb;

		$category_id = (int) $request->get_param( 'id' );

		$category = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*,
					COUNT(DISTINCT sc.service_id) as service_count
				FROM {$wpdb->prefix}bookings_categories c
				LEFT JOIN {$wpdb->prefix}bookings_service_categories sc ON c.id = sc.category_id
				WHERE c.id = %d
				AND c.deleted_at IS NULL
				GROUP BY c.id",
				$category_id
			),
			ARRAY_A
		);

		if ( ! $category ) {
			return new WP_Error(
				'category_not_found',
				'Category not found.',
				array( 'status' => 404 )
			);
		}

		// Convert numeric fields.
		$category['id']            = (int) $category['id'];
		$category['display_order'] = (int) $category['display_order'];
		$category['is_active']     = (bool) $category['is_active'];
		$category['service_count'] = (int) $category['service_count'];

		return rest_ensure_response(
			array(
				'success'  => true,
				'category' => $category,
			)
		);
	}

	/**
	 * Create new category.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_category( $request ) {
		global $wpdb;

		$name          = $request->get_param( 'name' );
		$description   = $request->get_param( 'description' );
		$is_active     = filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN );
		$display_order = (int) $request->get_param( 'display_order' );

		// Check for duplicate name.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_categories 
				WHERE name = %s AND deleted_at IS NULL",
				$name
			)
		);

		if ( $existing ) {
			return new WP_Error(
				'duplicate_name',
				'A category with this name already exists.',
				array( 'status' => 409 )
			);
		}

		// Insert category.
		$result = $wpdb->insert(
			$wpdb->prefix . 'bookings_categories',
			array(
				'name'          => $name,
				'description'   => $description,
				'is_active'     => $is_active ? 1 : 0,
				'display_order' => $display_order,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'creation_failed',
				'Failed to create category.',
				array( 'status' => 500 )
			);
		}

		$category_id = $wpdb->insert_id;

		// Get created category.
		$category = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_categories WHERE id = %d",
				$category_id
			),
			ARRAY_A
		);

		$category['service_count'] = 0;

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => 'Category created successfully.',
				'category' => $category,
			)
		);
	}

	/**
	 * Update existing category.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_category( $request ) {
		global $wpdb;

		$category_id = (int) $request->get_param( 'id' );

		// Check if category exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name FROM {$wpdb->prefix}bookings_categories 
				WHERE id = %d AND deleted_at IS NULL",
				$category_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'category_not_found',
				'Category not found.',
				array( 'status' => 404 )
			);
		}

		$name          = $request->get_param( 'name' );
		$description   = $request->get_param( 'description' );
		$is_active     = filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN );
		$display_order = (int) $request->get_param( 'display_order' );

		// Check for duplicate name (excluding current category).
		$duplicate = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_categories 
				WHERE name = %s AND id != %d AND deleted_at IS NULL",
				$name,
				$category_id
			)
		);

		if ( $duplicate ) {
			return new WP_Error(
				'duplicate_name',
				'A category with this name already exists.',
				array( 'status' => 409 )
			);
		}

		// Update category.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_categories',
			array(
				'name'          => $name,
				'description'   => $description,
				'is_active'     => $is_active ? 1 : 0,
				'display_order' => $display_order,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $category_id ),
			array( '%s', '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update category.',
				array( 'status' => 500 )
			);
		}

		// Get updated category with service count.
		$category = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*,
					COUNT(DISTINCT sc.service_id) as service_count
				FROM {$wpdb->prefix}bookings_categories c
				LEFT JOIN {$wpdb->prefix}bookings_service_categories sc ON c.id = sc.category_id
				WHERE c.id = %d
				GROUP BY c.id",
				$category_id
			),
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'success'  => true,
				'message'  => 'Category updated successfully.',
				'category' => $category,
			)
		);
	}

	/**
	 * Delete category (soft delete).
	 * Shows confirmation with service count.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_category( $request ) {
		global $wpdb;

		$category_id = (int) $request->get_param( 'id' );

		// Check if category exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name FROM {$wpdb->prefix}bookings_categories 
				WHERE id = %d AND deleted_at IS NULL",
				$category_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'category_not_found',
				'Category not found.',
				array( 'status' => 404 )
			);
		}

		// Get service count.
		$service_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT service_id) 
				FROM {$wpdb->prefix}bookings_service_categories 
				WHERE category_id = %d",
				$category_id
			)
		);

		// Soft delete the category.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_categories',
			array(
				'deleted_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $category_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'deletion_failed',
				'Failed to delete category.',
				array( 'status' => 500 )
			);
		}

		$message = 'Category deleted successfully.';
		if ( $service_count > 0 ) {
			$message .= sprintf( ' %d service(s) are no longer in this category.', $service_count );
		}

		return rest_ensure_response(
			array(
				'success'       => true,
				'message'       => $message,
				'service_count' => (int) $service_count,
			)
		);
	}

	/**
	 * Reorder categories.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_categories( $request ) {
		global $wpdb;

		$categories = $request->get_param( 'categories' );

		if ( empty( $categories ) ) {
			return new WP_Error(
				'invalid_data',
				'Categories array is required.',
				array( 'status' => 400 )
			);
		}

		// Update display order for each category.
		foreach ( $categories as $category_data ) {
			if ( ! isset( $category_data['id'] ) || ! isset( $category_data['display_order'] ) ) {
				continue;
			}

			$wpdb->update(
				$wpdb->prefix . 'bookings_categories',
				array(
					'display_order' => (int) $category_data['display_order'],
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => (int) $category_data['id'] ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Categories reordered successfully.',
			)
		);
	}

	/**
	 * Create new service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_service( $request ) {
		global $wpdb;

		// Get parameters.
		$name           = $request->get_param( 'name' );
		$description    = $request->get_param( 'description' );
		$duration       = (int) $request->get_param( 'duration' );
		$price          = (float) $request->get_param( 'price' );
		$deposit_amount = $request->get_param( 'deposit_amount' );
		$deposit_type   = $request->get_param( 'deposit_type' ) ?: 'fixed';
		$buffer_before  = (int) $request->get_param( 'buffer_before' );
		$buffer_after   = (int) $request->get_param( 'buffer_after' );
		$category_ids   = $request->get_param( 'category_ids' ) ?: array();
		$is_active      = filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN );
		$display_order  = (int) $request->get_param( 'display_order' );

		// Insert service.
		$result = $wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'           => $name,
				'description'    => $description,
				'duration'       => $duration,
				'price'          => $price,
				'deposit_amount' => $deposit_amount,
				'deposit_type'   => $deposit_type,
				'buffer_before'  => $buffer_before,
				'buffer_after'   => $buffer_after,
				'is_active'      => $is_active ? 1 : 0,
				'display_order'  => $display_order,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%f', '%f', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'creation_failed',
				'Failed to create service.',
				array( 'status' => 500 )
			);
		}

		$service_id = $wpdb->insert_id;

		// Insert category relationships.
		if ( ! empty( $category_ids ) ) {
			foreach ( $category_ids as $category_id ) {
				$wpdb->insert(
					$wpdb->prefix . 'bookings_service_categories',
					array(
						'service_id'  => $service_id,
						'category_id' => (int) $category_id,
						'created_at'  => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s' )
				);
			}
		}

		// Get created service with categories.
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.* FROM {$wpdb->prefix}bookings_services s WHERE s.id = %d",
				$service_id
			),
			ARRAY_A
		);

		// Get categories.
		$categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.name
				FROM {$wpdb->prefix}bookings_categories c
				INNER JOIN {$wpdb->prefix}bookings_service_categories sc ON c.id = sc.category_id
				WHERE sc.service_id = %d
				AND c.deleted_at IS NULL",
				$service_id
			),
			ARRAY_A
		);

		$service['categories']   = $categories;
		$service['category_ids'] = array_column( $categories, 'id' );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Service created successfully.',
				'service' => $service,
			)
		);
	}

	/**
	 * Update existing service.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_service( $request ) {
		global $wpdb;

		$service_id = (int) $request->get_param( 'id' );

		// Check if service exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings_services WHERE id = %d AND deleted_at IS NULL",
				$service_id
			)
		);

		if ( ! $existing ) {
			return new WP_Error(
				'service_not_found',
				'Service not found.',
				array( 'status' => 404 )
			);
		}

		// Get parameters.
		$name           = $request->get_param( 'name' );
		$description    = $request->get_param( 'description' );
		$duration       = (int) $request->get_param( 'duration' );
		$price          = (float) $request->get_param( 'price' );
		$deposit_amount = $request->get_param( 'deposit_amount' );
		$deposit_type   = $request->get_param( 'deposit_type' ) ?: 'fixed';
		$buffer_before  = (int) $request->get_param( 'buffer_before' );
		$buffer_after   = (int) $request->get_param( 'buffer_after' );
		$category_ids   = $request->get_param( 'category_ids' ) ?: array();
		$is_active      = filter_var( $request->get_param( 'is_active' ), FILTER_VALIDATE_BOOLEAN );
		$display_order  = (int) $request->get_param( 'display_order' );

		// Update service.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'           => $name,
				'description'    => $description,
				'duration'       => $duration,
				'price'          => $price,
				'deposit_amount' => $deposit_amount,
				'deposit_type'   => $deposit_type,
				'buffer_before'  => $buffer_before,
				'buffer_after'   => $buffer_after,
				'is_active'      => $is_active ? 1 : 0,
				'display_order'  => $display_order,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $service_id ),
			array( '%s', '%s', '%d', '%f', '%f', '%s', '%d', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'update_failed',
				'Failed to update service.',
				array( 'status' => 500 )
			);
		}

		// Delete existing category relationships.
		$wpdb->delete(
			$wpdb->prefix . 'bookings_service_categories',
			array( 'service_id' => $service_id ),
			array( '%d' )
		);

		// Insert new category relationships.
		if ( ! empty( $category_ids ) ) {
			foreach ( $category_ids as $category_id ) {
				$wpdb->insert(
					$wpdb->prefix . 'bookings_service_categories',
					array(
						'service_id'  => $service_id,
						'category_id' => (int) $category_id,
						'created_at'  => current_time( 'mysql' ),
					),
					array( '%d', '%d', '%s' )
				);
			}
		}

		// Get updated service with categories.
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.* FROM {$wpdb->prefix}bookings_services s WHERE s.id = %d",
				$service_id
			),
			ARRAY_A
		);

		// Get categories.
		$categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id, c.name
				FROM {$wpdb->prefix}bookings_categories c
				INNER JOIN {$wpdb->prefix}bookings_service_categories sc ON c.id = sc.category_id
				WHERE sc.service_id = %d
				AND c.deleted_at IS NULL",
				$service_id
			),
			ARRAY_A
		);

		$service['categories']   = $categories;
		$service['category_ids'] = array_column( $categories, 'id' );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Service updated successfully.',
				'service' => $service,
			)
		);
	}

	/**
	 * Delete service (soft delete).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_service( $request ) {
		global $wpdb;

		$service_id = (int) $request->get_param( 'id' );

		// Check if service exists.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name FROM {$wpdb->prefix}bookings_services WHERE id = %d AND deleted_at IS NULL",
				$service_id
			),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'service_not_found',
				'Service not found.',
				array( 'status' => 404 )
			);
		}

		// Check if service has future bookings.
		$future_bookings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				WHERE service_id = %d
				AND booking_date >= CURDATE()
				AND deleted_at IS NULL
				AND status NOT IN ('cancelled', 'no_show')",
				$service_id
			)
		);

		if ( $future_bookings > 0 ) {
			return new WP_Error(
				'service_has_bookings',
				sprintf(
					'Cannot delete service "%s" because it has %d future booking(s). Please cancel or complete these bookings first, or deactivate the service instead.',
					$existing['name'],
					$future_bookings
				),
				array( 'status' => 409 )
			);
		}

		// Soft delete the service.
		$result = $wpdb->update(
			$wpdb->prefix . 'bookings_services',
			array(
				'deleted_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $service_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'deletion_failed',
				'Failed to delete service.',
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Service deleted successfully.',
			)
		);
	}

	/**
	 * Update display order for multiple services.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_services( $request ) {
		global $wpdb;

		$services = $request->get_param( 'services' );

		if ( empty( $services ) ) {
			return new WP_Error(
				'invalid_data',
				'Services array is required.',
				array( 'status' => 400 )
			);
		}

		// Update display order for each service.
		foreach ( $services as $service_data ) {
			if ( ! isset( $service_data['id'] ) || ! isset( $service_data['display_order'] ) ) {
				continue;
			}

			$wpdb->update(
				$wpdb->prefix . 'bookings_services',
				array(
					'display_order' => (int) $service_data['display_order'],
					'updated_at'    => current_time( 'mysql' ),
				),
				array( 'id' => (int) $service_data['id'] ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => 'Services reordered successfully.',
			)
		);
	}
}

// Initialize the API.
new Bookit_Dashboard_Bookings_API();
