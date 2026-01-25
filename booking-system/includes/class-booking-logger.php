<?php
/**
 * Error logging system.
 *
 * @package    Booking_System
 * @subpackage Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Logger class.
 */
class Booking_Logger {

	/**
	 * Log directory path.
	 *
	 * @var string
	 */
	private static $log_dir = '';

	/**
	 * Initialize logger.
	 *
	 * @return void
	 */
	public static function init() {
		$upload_dir    = wp_upload_dir();
		self::$log_dir = $upload_dir['basedir'] . '/bookings/logs';

		// Ensure log directory exists
		if ( ! file_exists( self::$log_dir ) ) {
			wp_mkdir_p( self::$log_dir );

			// Add .htaccess to protect logs
			$htaccess_content = "Deny from all\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( self::$log_dir . '/.htaccess', $htaccess_content );
		}
	}

	/**
	 * Log INFO level message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function info( $message, $context = array() ) {
		self::log( 'INFO', $message, $context );
	}

	/**
	 * Log WARNING level message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function warning( $message, $context = array() ) {
		self::log( 'WARNING', $message, $context );
	}

	/**
	 * Log ERROR level message.
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	public static function error( $message, $context = array() ) {
		self::log( 'ERROR', $message, $context );
	}

	/**
	 * Write log entry.
	 *
	 * @param string $level   Log level (INFO, WARNING, ERROR).
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private static function log( $level, $message, $context = array() ) {
		self::init();

		// Sanitize sensitive data from context
		$context = self::sanitize_context( $context );

		// Format timestamp
		$timestamp = current_time( 'Y-m-d H:i:s' );

		// Build log entry
		$log_entry = sprintf(
			'[%s] [%s] %s',
			$timestamp,
			$level,
			$message
		);

		// Add context if provided
		if ( ! empty( $context ) ) {
			$log_entry .= ' | Context: ' . wp_json_encode( $context );
		}

		$log_entry .= "\n";

		// Get log file path (daily rotation)
		$log_file = self::get_log_file();

		// Write to log file
		// Suppress errors to prevent breaking the application
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $log_file, $log_entry, FILE_APPEND );

		// Also write to WordPress debug.log if WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Booking System] ' . $log_entry );
		}
	}

	/**
	 * Get current log file path.
	 *
	 * @return string Log file path.
	 */
	private static function get_log_file() {
		$date     = current_time( 'Y-m-d' );
		$filename = 'bookings-' . $date . '.log';
		return self::$log_dir . '/' . $filename;
	}

	/**
	 * Sanitize context data to remove sensitive information.
	 *
	 * @param array $context Context data.
	 * @return array Sanitized context.
	 */
	private static function sanitize_context( $context ) {
		if ( ! is_array( $context ) ) {
			return $context;
		}

		// List of sensitive keys to redact
		$sensitive_keys = array(
			'password',
			'password_hash',
			'api_key',
			'secret',
			'secret_key',
			'stripe_secret',
			'paypal_secret',
			'card_number',
			'cvv',
			'cvc',
			'credit_card',
		);

		foreach ( $context as $key => $value ) {
			// Check if key contains sensitive data
			$key_lower = strtolower( $key );
			foreach ( $sensitive_keys as $sensitive ) {
				if ( strpos( $key_lower, $sensitive ) !== false ) {
					$context[ $key ] = '[REDACTED]';
					break;
				}
			}

			// Recursively sanitize nested arrays
			if ( is_array( $value ) ) {
				$context[ $key ] = self::sanitize_context( $value );
			}
		}

		return $context;
	}

	/**
	 * Clean up old log files (keep 28 days).
	 *
	 * Called by scheduled cron job.
	 *
	 * @return void
	 */
	public static function cleanup_old_logs() {
		self::init();

		$retention_days = 28; // Keep 4 weeks
		$cutoff_time    = strtotime( "-{$retention_days} days" );

		// Get all log files
		$log_files = glob( self::$log_dir . '/bookings-*.log' );

		if ( empty( $log_files ) ) {
			return;
		}

		$deleted_count = 0;

		foreach ( $log_files as $log_file ) {
			// Get file modification time
			$file_time = filemtime( $log_file );

			// Delete if older than retention period
			if ( $file_time < $cutoff_time ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				if ( @unlink( $log_file ) ) {
					$deleted_count++;
				}
			}
		}

		if ( $deleted_count > 0 ) {
			self::info(
				"Cleaned up {$deleted_count} old log files (older than {$retention_days} days)",
				array(
					'deleted_count' => $deleted_count,
					'retention_days' => $retention_days,
				)
			);
		}
	}

	/**
	 * Get all log files (for admin viewing).
	 *
	 * @return array Array of log file paths.
	 */
	public static function get_log_files() {
		self::init();
		$log_files = glob( self::$log_dir . '/bookings-*.log' );
		return $log_files ? $log_files : array();
	}

	/**
	 * Get log file contents (for admin viewing).
	 *
	 * @param string $date Date in YYYY-MM-DD format.
	 * @return string|false Log contents or false if not found.
	 */
	public static function get_log_contents( $date ) {
		self::init();
		$filename = 'bookings-' . $date . '.log';
		$filepath = self::$log_dir . '/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return file_get_contents( $filepath );
	}

	/**
	 * Get today's log file path.
	 *
	 * @return string Log file path.
	 */
	public static function get_todays_log_file() {
		return self::get_log_file();
	}

	/**
	 * Check if logging is working.
	 *
	 * @return bool True if can write to log.
	 */
	public static function test_logging() {
		self::init();

		$test_message = 'Test log entry - ' . time();
		$log_file     = self::get_log_file();

		// Try to write test message
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = @file_put_contents( $log_file, $test_message . "\n", FILE_APPEND );

		return $result !== false;
	}
}
