<?php
/**
 * Session manager for booking wizard.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/core
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Session manager class for booking wizard.
 */
class Bookit_Session_Manager {

	/**
	 * Session key for wizard data.
	 *
	 * @var string
	 */
	const SESSION_KEY = 'bookit_wizard';

	/**
	 * Initialize session with security settings.
	 *
	 * @return void
	 */
	public static function init() {
		// Only start session if not already started.
		if ( session_status() === PHP_SESSION_NONE ) {
			// Check if headers have already been sent (e.g., in test environment).
			if ( headers_sent() ) {
				// In test environment, just ensure $_SESSION is available.
				if ( ! isset( $_SESSION ) || ! is_array( $_SESSION ) ) {
					$_SESSION = array();
				}
				return;
			}

			// Session security configuration.
			@ini_set( 'session.cookie_httponly', '1' ); // Prevent JavaScript access.
			@ini_set( 'session.cookie_samesite', 'Lax' ); // CSRF protection.
			@ini_set( 'session.gc_maxlifetime', '28800' ); // 8 hours.
			@ini_set( 'session.use_only_cookies', '1' ); // No session ID in URL.

			// HTTPS only in production (not localhost).
			if ( ! self::is_localhost() ) {
				@ini_set( 'session.cookie_secure', '1' );
			}

			session_name( 'bookit_wizard_session' );
			session_start();

			// Initialize wizard data if not exists.
			if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
				$_SESSION[ self::SESSION_KEY ] = self::get_default_data();
			}

			// Update last activity timestamp.
			self::update_activity();
		}
	}

	/**
	 * Check if running on localhost.
	 *
	 * @return bool True if localhost.
	 */
	private static function is_localhost() {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$whitelist   = array( '127.0.0.1', '::1', 'localhost' );

		return in_array( $remote_addr, $whitelist, true );
	}

	/**
	 * Get default wizard data structure.
	 *
	 * @return array Default wizard data.
	 */
	private static function get_default_data() {
		return array(
			'current_step' => 1,
			'service_id'   => null,
			'staff_id'     => null,
			'date'         => null,
			'time'         => null,
			'customer'     => array(),
			'created_at'   => time(),
			'last_activity' => time(),
		);
	}

	/**
	 * Get wizard data.
	 *
	 * @return array Wizard data.
	 */
	public static function get_data() {
		self::init();
		return isset( $_SESSION[ self::SESSION_KEY ] ) ? $_SESSION[ self::SESSION_KEY ] : self::get_default_data();
	}

	/**
	 * Get specific wizard field value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $default Default value if not set.
	 * @return mixed Field value or default.
	 */
	public static function get( $field, $default = null ) {
		$data = self::get_data();
		return isset( $data[ $field ] ) ? $data[ $field ] : $default;
	}

	/**
	 * Set wizard data.
	 *
	 * @param array $data Wizard data to set.
	 * @return void
	 */
	public static function set_data( $data ) {
		self::init();
		$current_data = self::get_data();
		$_SESSION[ self::SESSION_KEY ] = array_merge( $current_data, $data );
		self::update_activity();
	}

	/**
	 * Set specific wizard field value.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Field value.
	 * @return void
	 */
	public static function set( $field, $value ) {
		self::init();
		if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
			$_SESSION[ self::SESSION_KEY ] = self::get_default_data();
		}
		$_SESSION[ self::SESSION_KEY ][ $field ] = $value;
		self::update_activity();
	}

	/**
	 * Clear wizard data.
	 *
	 * @return void
	 */
	public static function clear() {
		self::init();
		$_SESSION[ self::SESSION_KEY ] = self::get_default_data();
	}

	/**
	 * Regenerate session ID (prevent session fixation).
	 *
	 * @return void
	 */
	public static function regenerate() {
		self::init();
		
		// Only regenerate if session is active.
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_regenerate_id( true );
		}
	}

	/**
	 * Check if session is expired.
	 *
	 * @return bool True if expired.
	 */
	public static function is_expired() {
		$last_activity = (int) self::get( 'last_activity', 0 );
		$timeout       = 28800; // 8 hours in seconds.

		if ( $last_activity > 0 && ( time() - $last_activity > $timeout ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Update last activity timestamp.
	 *
	 * @return void
	 */
	private static function update_activity() {
		self::init();
		if ( ! isset( $_SESSION[ self::SESSION_KEY ] ) ) {
			$_SESSION[ self::SESSION_KEY ] = self::get_default_data();
		}
		$_SESSION[ self::SESSION_KEY ]['last_activity'] = time();
	}

	/**
	 * Get time remaining until session expires (in seconds).
	 *
	 * @return int Seconds until expiry, or 0 if expired.
	 */
	public static function get_time_remaining() {
		$last_activity = (int) self::get( 'last_activity', 0 );
		$timeout       = 28800; // 8 hours in seconds.
		$elapsed       = time() - $last_activity;
		$remaining     = $timeout - $elapsed;

		return max( 0, $remaining );
	}
}
