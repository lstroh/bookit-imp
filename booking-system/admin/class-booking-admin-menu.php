<?php
/**
 * Admin menu structure.
 *
 * @package    Booking_System
 * @subpackage Booking_System/admin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Admin menu class.
 */
class Booking_Admin_Menu {

	/**
	 * Register admin menu.
	 */
	public function register_menu() {
		// Main menu page
		add_menu_page(
			__( 'Booking System', 'booking-system' ),           // Page title
			__( 'Booking System', 'booking-system' ),           // Menu title
			'manage_options',                                   // Capability
			'booking-system',                                   // Menu slug
			array( $this, 'render_bookings_page' ),            // Callback
			'dashicons-calendar-alt',                          // Icon
			30                                                  // Position
		);

		// Bookings submenu
		add_submenu_page(
			'booking-system',
			__( 'Bookings', 'booking-system' ),
			__( 'Bookings', 'booking-system' ),
			'manage_options',
			'booking-system',
			array( $this, 'render_bookings_page' )
		);

		add_submenu_page(
			'booking-system',
			__( 'Calendar View', 'booking-system' ),
			__( 'Calendar View', 'booking-system' ),
			'manage_options',
			'booking-calendar',
			array( $this, 'render_calendar_page' )
		);

		add_submenu_page(
			'booking-system',
			__( 'Add New Booking', 'booking-system' ),
			__( 'Add New', 'booking-system' ),
			'manage_options',
			'booking-add-new',
			array( $this, 'render_add_booking_page' )
		);

		// Services submenu
		add_submenu_page(
			'booking-system',
			__( 'Services', 'booking-system' ),
			__( 'Services', 'booking-system' ),
			'manage_options',
			'booking-services',
			array( $this, 'render_services_page' )
		);

		add_submenu_page(
			'booking-system',
			__( 'Service Categories', 'booking-system' ),
			__( 'Categories', 'booking-system' ),
			'manage_options',
			'booking-service-categories',
			array( $this, 'render_categories_page' )
		);

		add_submenu_page(
			'booking-system',
			__( 'Add New Service', 'booking-system' ),
			__( 'Add New', 'booking-system' ),
			'manage_options',
			'booking-add-service',
			array( $this, 'render_add_service_page' )
		);

		// Staff submenu
		add_submenu_page(
			'booking-system',
			__( 'Staff', 'booking-system' ),
			__( 'Staff', 'booking-system' ),
			'manage_options',
			'booking-staff',
			array( $this, 'render_staff_page' )
		);

		add_submenu_page(
			'booking-system',
			__( 'Add New Staff', 'booking-system' ),
			__( 'Add New', 'booking-system' ),
			'manage_options',
			'booking-add-staff',
			array( $this, 'render_add_staff_page' )
		);

		// Customers submenu
		add_submenu_page(
			'booking-system',
			__( 'Customers', 'booking-system' ),
			__( 'Customers', 'booking-system' ),
			'manage_options',
			'booking-customers',
			array( $this, 'render_customers_page' )
		);

		add_submenu_page(
			'booking-system',
			__( 'Export Customers', 'booking-system' ),
			__( 'Export', 'booking-system' ),
			'manage_options',
			'booking-export-customers',
			array( $this, 'render_export_page' )
		);

		// Settings submenu
		add_submenu_page(
			'booking-system',
			__( 'Settings', 'booking-system' ),
			__( 'Settings', 'booking-system' ),
			'manage_options',
			'booking-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render bookings page.
	 */
	public function render_bookings_page() {
		require_once BOOKING_SYSTEM_PATH . 'admin/pages/bookings.php';
	}

	/**
	 * Render calendar page.
	 */
	public function render_calendar_page() {
		require_once BOOKING_SYSTEM_PATH . 'admin/pages/calendar.php';
	}

	/**
	 * Render add booking page.
	 */
	public function render_add_booking_page() {
		require_once BOOKING_SYSTEM_PATH . 'admin/pages/add-booking.php';
	}

	/**
	 * Render services page.
	 */
	public function render_services_page() {
		require_once BOOKING_SYSTEM_PATH . 'admin/pages/services.php';
	}

	/**
	 * Render categories page.
	 */
	public function render_categories_page() {
		require_once BOOKING_SYSTEM_PATH . 'admin/pages/categories.php';
	}

	/**
	 * Render add service page.
	 */
	public function render_add_service_page() {
		require_once BOOKING_SYSTEM_PATH . 'admin/pages/add-service.php';
	}

	/**
	 * Render staff page.
	 */
	public function render_staff_page() {
		require_once BOOKING_SYSTEM_PATH . 'admin/pages/staff.php';
	}

	/**
	 * Render add staff page.
	 */
	public function render_add_staff_page() {
		require_once BOOKING_SYSTEM_PATH . 'admin/pages/add-staff.php';
	}

	/**
	 * Render customers page.
	 */
	public function render_customers_page() {
		require_once BOOKING_SYSTEM_PATH . 'admin/pages/customers.php';
	}

	/**
	 * Render export page.
	 */
	public function render_export_page() {
		require_once BOOKING_SYSTEM_PATH . 'admin/pages/export.php';
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		require_once BOOKING_SYSTEM_PATH . 'admin/pages/settings.php';
	}
}
