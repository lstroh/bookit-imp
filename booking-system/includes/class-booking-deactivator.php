<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    Booking_System
 * @subpackage Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Fired during plugin deactivation.
 */
class Booking_Deactivator {

	/**
	 * Deactivation tasks.
	 *
	 * - Clear scheduled events
	 * - Flush rewrite rules
	 * - DO NOT delete database tables (preserve data)
	 * - DO NOT delete settings
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Clear any scheduled cron events.
		$timestamp = wp_next_scheduled( 'booking_system_cleanup_logs' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'booking_system_cleanup_logs' );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Log deactivation.
		require_once BOOKING_SYSTEM_PATH . 'includes/class-booking-logger.php';
		Booking_Logger::info( 'Plugin deactivated', array( 'deactivated_at' => current_time( 'mysql' ) ) );
	}
}
