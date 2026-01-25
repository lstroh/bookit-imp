<?php
/**
 * Fired during plugin activation.
 *
 * @package    Booking_System
 * @subpackage Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Fired during plugin activation.
 */
class Booking_Activator {

	/**
	 * Activation tasks.
	 *
	 * - Create database tables (handled in Tasks 2 & 3)
	 * - Set default options
	 * - Check system requirements
	 * - Create log directory
	 *
	 * @return void
	 */
	public static function activate() {
		// Check PHP version.
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			if ( defined( 'BOOKING_SYSTEM_FILE' ) ) {
				deactivate_plugins( plugin_basename( BOOKING_SYSTEM_FILE ) );
			}

			wp_die(
				esc_html__( 'Booking System requires PHP 8.0 or higher.', 'booking-system' )
			);
		}

		// Check WordPress version.
		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			if ( defined( 'BOOKING_SYSTEM_FILE' ) ) {
				deactivate_plugins( plugin_basename( BOOKING_SYSTEM_FILE ) );
			}

			wp_die(
				esc_html__( 'Booking System requires WordPress 6.0 or higher.', 'booking-system' )
			);
		}

		// Create log directory.
		$upload_dir = wp_upload_dir();
		$base_dir   = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';
		$log_dir    = trailingslashit( $base_dir ) . 'bookings/logs';

		if ( ! empty( $base_dir ) && ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );

			// Add .htaccess to protect logs.
			$htaccess_content = "Deny from all\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( trailingslashit( $log_dir ) . '.htaccess', $htaccess_content );
		}

		// Set plugin version option.
		update_option( 'booking_system_version', BOOKING_SYSTEM_VERSION );

		// Set default settings.
		$default_settings = array(
			'timezone'              => 'Europe/London',
			'currency'              => 'GBP',
			'date_format'           => 'd/m/Y',
			'time_format'           => 'H:i',
			'booking_buffer_before' => 0,
			'booking_buffer_after'  => 0,
			'min_booking_notice'    => 60, // 1 hour in minutes.
			'max_booking_advance'   => 90, // 90 days.
		);

		add_option( 'booking_system_settings', $default_settings );

		// Create database tables (Part 1: Tables 1-5).
		require_once BOOKING_SYSTEM_PATH . 'includes/class-booking-database.php';
		Booking_Database::create_tables();

		// Schedule log cleanup (daily at 3 AM)
		if ( ! wp_next_scheduled( 'booking_system_cleanup_logs' ) ) {
			wp_schedule_event( strtotime( '03:00:00' ), 'daily', 'booking_system_cleanup_logs' );
		}

		// Test logging system
		require_once BOOKING_SYSTEM_PATH . 'includes/class-booking-logger.php';
		if ( Booking_Logger::test_logging() ) {
			global $wp_version;
			Booking_Logger::info(
				'Plugin activated successfully',
				array(
					'version'     => BOOKING_SYSTEM_VERSION,
					'php_version' => PHP_VERSION,
					'wp_version'  => $wp_version,
				)
			);
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Booking System] WARNING: Log directory not writable' );
		}

		// Flush rewrite rules (for dashboard endpoints).
		flush_rewrite_rules();
	}
}
