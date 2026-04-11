<?php
/**
 * Google Calendar OAuth (per staff) — auth URL and token storage only.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/integrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * OAuth helpers for Google Calendar.
 */
class Bookit_Google_Calendar_Api {

	/**
	 * Redirect URI registered with Google (must match console + token exchange).
	 */
	private const OAUTH_REDIRECT_URI = 'https://test.wimbledonsmart.co.uk/wp-json/bookit/v1/google-calendar/callback';

	/**
	 * Calendar events scope (read/write events).
	 */
	private const SCOPE_CALENDAR_EVENTS = 'https://www.googleapis.com/auth/calendar.events';

	/**
	 * Build Google OAuth consent URL for a staff member.
	 *
	 * @param int $staff_id Staff row ID.
	 * @return string Authorization URL or empty string if misconfigured.
	 */
	public static function get_auth_url( int $staff_id ): string {
		$staff_id = absint( $staff_id );
		if ( $staff_id < 1 ) {
			return '';
		}

		$client = self::create_configured_client();
		if ( ! $client ) {
			return '';
		}

		$nonce = wp_create_nonce( 'google_oauth_' . $staff_id );
		$state = $nonce . ':' . $staff_id;

		$client->setState( $state );
		$client->setAccessType( 'offline' );
		$client->setPrompt( 'consent' );
		$client->addScope( self::get_calendar_events_scope() );

		return (string) $client->createAuthUrl();
	}

	/**
	 * OAuth callback: exchange code, store encrypted tokens, set connected flag.
	 *
	 * @param string $code  Authorization code.
	 * @param string $state State parameter (nonce:staff_id).
	 * @return int Staff ID on success, 0 on failure.
	 */
	public static function handle_callback( string $code, string $state ): int {
		$state = sanitize_text_field( $state );
		$parts = explode( ':', $state, 2 );
		if ( count( $parts ) < 2 ) {
			return 0;
		}

		$nonce    = $parts[0];
		$staff_id = absint( $parts[1] );
		if ( $staff_id < 1 ) {
			return 0;
		}

		if ( ! wp_verify_nonce( $nonce, 'google_oauth_' . $staff_id ) ) {
			return 0;
		}

		$client = self::create_configured_client();
		if ( ! $client ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				$staff_id,
				array(
					'notes' => 'Missing Google OAuth client configuration.',
				)
			);
			return 0;
		}

		$token = static::exchange_auth_code_for_tokens( $client, $code );
		if ( isset( $token['error'] ) ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				$staff_id,
				array(
					'notes' => isset( $token['error_description'] ) ? (string) $token['error_description'] : (string) $token['error'],
				)
			);
			return 0;
		}

		$access_token  = isset( $token['access_token'] ) ? (string) $token['access_token'] : '';
		$refresh_token = isset( $token['refresh_token'] ) ? (string) $token['refresh_token'] : '';
		$expires_in    = isset( $token['expires_in'] ) ? (int) $token['expires_in'] : 3600;
		if ( '' === $access_token ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				$staff_id,
				array(
					'notes' => 'Token response missing access_token.',
				)
			);
			return 0;
		}

		$client->setAccessToken( $token );

		$email = static::fetch_google_account_email( $client );
		if ( '' === $email ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				$staff_id,
				array(
					'notes' => 'Could not read Google account email.',
				)
			);
			return 0;
		}

		$expiry_mysql = date( 'Y-m-d H:i:s', time() + max( 1, $expires_in ) );

		global $wpdb;
		$table = $wpdb->prefix . 'bookings_staff';

		$updated = $wpdb->update(
			$table,
			array(
				'google_oauth_access_token'  => Bookit_Encryption::encrypt( $access_token ),
				'google_oauth_refresh_token'   => '' !== $refresh_token ? Bookit_Encryption::encrypt( $refresh_token ) : null,
				'google_oauth_token_expiry'    => $expiry_mysql,
				'google_calendar_email'        => $email,
				'google_calendar_connected'    => 1,
				'updated_at'                   => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			Bookit_Audit_Logger::log(
				'google_calendar.oauth_failed',
				'staff',
				$staff_id,
				array(
					'notes' => 'Database update failed.',
				)
			);
			return 0;
		}

		Bookit_Audit_Logger::log(
			'google_calendar.connected',
			'staff',
			$staff_id,
			array()
		);

		return $staff_id;
	}

	/**
	 * Disconnect Google Calendar for a staff member.
	 *
	 * @param int $staff_id Staff ID.
	 * @return void
	 */
	public static function disconnect( int $staff_id ): void {
		$staff_id = absint( $staff_id );
		if ( $staff_id < 1 ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bookings_staff';

		$wpdb->update(
			$table,
			array(
				'google_oauth_access_token'  => null,
				'google_oauth_refresh_token' => null,
				'google_oauth_token_expiry'  => null,
				'google_calendar_email'      => null,
				'google_calendar_connected'  => 0,
				'updated_at'                 => current_time( 'mysql' ),
			),
			array( 'id' => $staff_id ),
			array( '%s', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		Bookit_Audit_Logger::log(
			'google_calendar.disconnected',
			'staff',
			$staff_id,
			array()
		);
	}

	/**
	 * Exchange authorization code for tokens (test override).
	 *
	 * @param \Google\Client $client OAuth client.
	 * @param string         $code   Auth code.
	 * @return array Token payload or error shape from Google client.
	 */
	protected static function exchange_auth_code_for_tokens( \Google\Client $client, string $code ): array {
		$result = $client->fetchAccessTokenWithAuthCode( $code );
		return is_array( $result ) ? $result : array();
	}

	/**
	 * Load primary Google account email via OAuth2 userinfo (test override).
	 *
	 * Uses the userinfo endpoint so we do not require a separate apiclient-services
	 * package beyond Calendar (Composer cleanup does not ship a standalone Oauth2 service).
	 *
	 * @param \Google\Client $client Authorized client.
	 * @return string Email or empty.
	 */
	protected static function fetch_google_account_email( \Google\Client $client ): string {
		$token = $client->getAccessToken();
		if ( empty( $token['access_token'] ) || ! is_string( $token['access_token'] ) ) {
			return '';
		}

		$response = wp_remote_get(
			'https://www.googleapis.com/oauth2/v2/userinfo',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token['access_token'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return '';
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['email'] ) ) {
			return '';
		}

		return sanitize_email( (string) $data['email'] );
	}

	/**
	 * Scope string for Calendar events.
	 *
	 * @return string
	 */
	private static function get_calendar_events_scope(): string {
		if ( class_exists( \Google\Service\Calendar::class ) ) {
			return \Google\Service\Calendar::CALENDAR_EVENTS;
		}
		return self::SCOPE_CALENDAR_EVENTS;
	}

	/**
	 * Create Google Client with plugin settings (or null if not configured).
	 *
	 * @return \Google\Client|null
	 */
	private static function create_configured_client(): ?\Google\Client {
		global $wpdb;

		$client_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
				'google_client_id'
			)
		);
		$secret = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s",
				'google_client_secret'
			)
		);

		$client_id = is_string( $client_id ) ? trim( $client_id ) : '';
		$secret    = is_string( $secret ) ? trim( $secret ) : '';

		if ( '' === $client_id || '' === $secret ) {
			return null;
		}

		$client = new \Google\Client();
		$client->setClientId( $client_id );
		$client->setClientSecret( $secret );
		$client->setRedirectUri( self::OAUTH_REDIRECT_URI );

		return $client;
	}
}
