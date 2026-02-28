<?php
/**
 * Central error registry for API responses.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Error registry class.
 */
class Bookit_Error_Registry {

	/**
	 * Static registry of error definitions.
	 * Keyed by error code string.
	 *
	 * @var array<string, array>
	 */
	private static array $errors = array();

	/**
	 * Register a custom error code.
	 * Extensions must prefix their codes with their slug.
	 *
	 * @param string $code       Unique error code (e.g. 'E1001').
	 * @param array  $definition Error definition.
	 * @return void
	 */
	public static function register( string $code, array $definition ): void {
		// Do not allow overwriting existing codes.
		if ( isset( self::$errors[ $code ] ) ) {
			return;
		}

		self::$errors[ $code ] = $definition;
	}

	/**
	 * Get an error definition by code.
	 * Returns a default system error if code not found.
	 *
	 * @param string $code Error code.
	 * @return array
	 */
	public static function get( string $code ): array {
		return self::$errors[ $code ] ?? self::$errors['E9999'];
	}

	/**
	 * Create a WP_Error from a registry code.
	 *
	 * @param string $code    Error code.
	 * @param array  $context Placeholder values for substitution.
	 * @return WP_Error
	 */
	public static function to_wp_error( string $code, array $context = array() ): WP_Error {
		$definition = self::get( $code );
		$message    = $definition['user_message'];

		foreach ( $context as $key => $value ) {
			$message = str_replace( '{' . $key . '}', (string) $value, $message );
		}

		return new WP_Error(
			$code,
			$message,
			array( 'status' => $definition['http_status'] )
		);
	}

	/**
	 * Return all registered error definitions.
	 *
	 * @return array
	 */
	public static function all(): array {
		return self::$errors;
	}
}
