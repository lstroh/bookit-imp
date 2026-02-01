<?php
/**
 * Stripe configuration and SDK initialization.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/payment
 */

declare( strict_types=1 );

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stripe configuration class.
 *
 * Provides mode, API keys, webhook secret, SDK initialization, and validation.
 */
class Bookit_Stripe_Config {

	/**
	 * Option name for settings group.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'bookit_stripe_settings';

	/**
	 * Stripe API instance (lazy-initialized).
	 *
	 * @var \Stripe\StripeClient|null
	 */
	private static $stripe_client = null;

	/**
	 * Get current mode: 'test' or 'live'.
	 *
	 * @return string 'test' or 'live'
	 */
	public static function get_mode(): string {
		$test_mode = get_option( 'bookit_stripe_test_mode', true );
		// WordPress may store false as '0', 0, or ''; treat as live.
		if ( $test_mode === false || $test_mode === 0 || $test_mode === '0' || $test_mode === '' ) {
			return 'live';
		}
		return 'test';
	}

	/**
	 * Check if test mode is enabled.
	 *
	 * @return bool
	 */
	public static function is_test_mode(): bool {
		return self::get_mode() === 'test';
	}

	/**
	 * Get publishable key for current mode.
	 *
	 * @return string
	 */
	public static function get_publishable_key(): string {
		if ( self::is_test_mode() ) {
			return (string) get_option( 'bookit_stripe_test_publishable_key', '' );
		}
		return (string) get_option( 'bookit_stripe_live_publishable_key', '' );
	}

	/**
	 * Get secret key for current mode.
	 *
	 * @return string
	 */
	public static function get_secret_key(): string {
		if ( self::is_test_mode() ) {
			return (string) get_option( 'bookit_stripe_test_secret_key', '' );
		}
		return (string) get_option( 'bookit_stripe_live_secret_key', '' );
	}

	/**
	 * Get webhook secret for current mode.
	 *
	 * @return string
	 */
	public static function get_webhook_secret(): string {
		if ( self::is_test_mode() ) {
			return (string) get_option( 'bookit_stripe_test_webhook_secret', '' );
		}
		return (string) get_option( 'bookit_stripe_live_webhook_secret', '' );
	}

	/**
	 * Initialize Stripe SDK with correct API key for current mode.
	 *
	 * @return \Stripe\StripeClient|null Stripe client or null if SDK not available or key missing.
	 */
	public static function get_stripe_client(): ?\Stripe\StripeClient {
		if ( ! class_exists( '\Stripe\StripeClient' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				error_log( '[Bookit] Stripe SDK not loaded. Run composer install.' );
			}
			return null;
		}

		$secret_key = self::get_secret_key();
		if ( empty( $secret_key ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				error_log( '[Bookit] Stripe secret key not configured.' );
			}
			return null;
		}

		if ( self::$stripe_client === null ) {
			try {
				\Stripe\Stripe::setApiKey( $secret_key );
				self::$stripe_client = new \Stripe\StripeClient( $secret_key );
			} catch ( \Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
					error_log( '[Bookit] Stripe SDK init error: ' . $e->getMessage() );
				}
				return null;
			}
		}

		return self::$stripe_client;
	}

	/**
	 * Validate publishable key format (pk_test_ or pk_live_).
	 *
	 * @param string $key Key to validate.
	 * @param string $mode Optional. 'test' or 'live'. Default 'test'.
	 * @return bool
	 */
	public static function validate_publishable_key( string $key, string $mode = 'test' ): bool {
		if ( empty( $key ) ) {
			return true; // Empty is allowed (optional until used).
		}
		$prefix = $mode === 'live' ? 'pk_live_' : 'pk_test_';
		return str_starts_with( $key, $prefix );
	}

	/**
	 * Validate secret key format (sk_test_ or sk_live_).
	 *
	 * @param string $key Key to validate.
	 * @param string $mode Optional. 'test' or 'live'. Default 'test'.
	 * @return bool
	 */
	public static function validate_secret_key( string $key, string $mode = 'test' ): bool {
		if ( empty( $key ) ) {
			return true;
		}
		$prefix = $mode === 'live' ? 'sk_live_' : 'sk_test_';
		return str_starts_with( $key, $prefix );
	}

	/**
	 * Validate webhook secret format (whsec_).
	 *
	 * @param string $key Key to validate.
	 * @return bool
	 */
	public static function validate_webhook_secret( string $key ): bool {
		if ( empty( $key ) ) {
			return true;
		}
		return str_starts_with( $key, 'whsec_' );
	}

	/**
	 * Test API connection with current secret key.
	 *
	 * @return array{ success: bool, message: string }
	 */
	public static function test_connection(): array {
		$client = self::get_stripe_client();
		if ( ! $client ) {
			return array(
				'success' => false,
				'message' => __( 'Stripe SDK not available or secret key not set.', 'bookit-booking-system' ),
			);
		}

		try {
			$client->balance->retrieve();
			return array(
				'success' => true,
				'message' => __( 'Connection successful.', 'bookit-booking-system' ),
			);
		} catch ( \Stripe\Exception\AuthenticationException $e ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid API key.', 'bookit-booking-system' ) . ' ' . $e->getMessage(),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}
}
