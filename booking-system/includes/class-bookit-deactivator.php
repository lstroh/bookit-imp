<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Fired during plugin deactivation.
 */
class Bookit_Deactivator {

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
		$timestamp = wp_next_scheduled( 'bookit_cleanup_logs' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'bookit_cleanup_logs' );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Log deactivation.
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';
		Bookit_Logger::info( 'Plugin deactivated', array( 'deactivated_at' => current_time( 'mysql' ) ) );
	}
}
