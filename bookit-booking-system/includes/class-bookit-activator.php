<?php
/**
 * Fired during plugin activation.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Fired during plugin activation.
 */
class Bookit_Activator {

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
			if ( defined( 'BOOKIT_PLUGIN_FILE' ) ) {
				deactivate_plugins( plugin_basename( BOOKIT_PLUGIN_FILE ) );
			}

			wp_die(
				esc_html__( 'Booking System requires PHP 8.0 or higher.', 'bookit-booking-system' )
			);
		}

		// Check WordPress version.
		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			if ( defined( 'BOOKIT_PLUGIN_FILE' ) ) {
				deactivate_plugins( plugin_basename( BOOKIT_PLUGIN_FILE ) );
			}

			wp_die(
				esc_html__( 'Booking System requires WordPress 6.0 or higher.', 'bookit-booking-system' )
			);
		}

		// Set plugin version option.
		update_option( 'bookit_version', BOOKIT_VERSION );

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

		add_option( 'bookit_settings', $default_settings );

		// Create database tables (Part 1: Tables 1-5).
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-database.php';
		Bookit_Database::create_tables();

		// Run migrations for additional tables (e.g. staff working hours).
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/migration-add-staff-working-hours.php';
		$staff_working_hours_migration = new Bookit_Migration_Add_Staff_Working_Hours();
		$staff_working_hours_migration->up();

		// Schedule log cleanup (daily at 3 AM)
		if ( ! wp_next_scheduled( 'bookit_cleanup_logs' ) ) {
			wp_schedule_event( strtotime( '03:00:00' ), 'daily', 'bookit_cleanup_logs' );
		}

		// Schedule abandoned session cleanup (daily at 3:30 AM)
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-session-cleanup.php';
		Bookit_Session_Cleanup::register_cron();

		// Schedule idempotency cleanup (daily at 3:00 AM) - Sprint 2, Task 6.
		require_once BOOKIT_PLUGIN_DIR . 'includes/cron/class-idempotency-cleanup.php';
		Bookit_Idempotency_Cleanup::register_cron();

		// Initialize logger (creates log directory in best location)
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';
		Bookit_Logger::init();

		// Migrate existing logs if needed
		Bookit_Logger::migrate_logs_if_needed();

		// Test logging system
		global $wp_version;
		if ( Bookit_Logger::test_logging() ) {
			Bookit_Logger::info( 'Plugin activated successfully', array(
				'version'       => BOOKIT_VERSION,
				'php_version'   => PHP_VERSION,
				'wp_version'    => $wp_version,
				'log_directory' => Bookit_Logger::get_log_directory(),
				'is_secure'     => Bookit_Logger::is_secure_location() ? 'YES (outside web root)' : 'NO (inside uploads)',
			) );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Bookit Booking System] WARNING: Log directory not writable' );
		}

		// Create confirmation page on activation.
		$page = get_page_by_path( 'booking-confirmed' );
		if ( ! $page ) {
			wp_insert_post(
				array(
					'post_title'   => 'Booking Confirmed',
					'post_name'    => 'booking-confirmed',
					'post_content' => '[bookit_confirmation]',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		}

		// Flush rewrite rules (for dashboard endpoints).
		flush_rewrite_rules();
	}
}
