<?php
/**
 * Brevo transactional email provider.
 *
 * Uses the getbrevo/brevo-php v4 SDK to send transactional emails.
 *
 * @package Bookit_Booking_System
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bookit_Brevo_Email_Provider implements Bookit_Email_Provider_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function get_name(): string {
		return 'Brevo';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_slug(): string {
		return 'brevo';
	}

	/**
	 * Read a single value from wp_bookings_settings.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	private static function get_setting( string $key, mixed $default = '' ): mixed {
		global $wpdb;
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
				$key
			)
		);
		return ( null !== $value && '' !== $value ) ? $value : $default;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Returns true when brevo_api_key is set in wp_bookings_settings.
	 */
	public function is_configured(): bool {
		$api_key = self::get_setting( 'brevo_api_key', '' );
		return ! empty( trim( (string) $api_key ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Sends via Brevo TransactionalEmailsClient (getbrevo/brevo-php v4).
	 *
	 * @throws nothing all exceptions are caught and returned as WP_Error.
	 */
	public function send( array $to, string $subject, string $html_body, array $params = [] ): bool|\WP_Error {
		$api_key    = self::get_setting( 'brevo_api_key', '' );
		$from_name  = self::get_setting( 'brevo_from_name', get_bloginfo( 'name' ) );
		$from_email = self::get_setting( 'brevo_from_email', get_option( 'admin_email' ) );

		if ( empty( trim( (string) $api_key ) ) ) {
			return new \WP_Error(
				'brevo_not_configured',
				__( 'Brevo API key is not configured.', 'booking-system' )
			);
		}

		try {
			$brevo = new \Brevo\Brevo( (string) $api_key );

			$request_values = [
				'sender'     => new \Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender(
					[
						'email' => sanitize_email( (string) $from_email ),
						'name'  => sanitize_text_field( (string) $from_name ),
					]
				),
				'to'         => [
					new \Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem(
						[
							'email' => sanitize_email( (string) ( $to['email'] ?? '' ) ),
							'name'  => sanitize_text_field( (string) ( $to['name'] ?? '' ) ),
						]
					),
				],
				'subject'     => $subject,
				'htmlContent' => $html_body,
			];

			// Optional template ID override from $params.
			if ( ! empty( $params['template_id'] ) ) {
				$request_values['templateId'] = (int) $params['template_id'];
			}

			$request = new \Brevo\TransactionalEmails\Requests\SendTransacEmailRequest( $request_values );

			$brevo->transactionalEmails->sendTransacEmail( $request );

			return true;

		} catch ( \Brevo\Exceptions\BrevoApiException $e ) {
			// Distinguish rate-limit responses so the dispatcher can retry.
			if ( 429 === $e->getCode() ) {
				return new \WP_Error(
					'brevo_rate_limited',
					__( 'Brevo rate limit reached (429). Will retry shortly.', 'booking-system' )
				);
			}

			return new \WP_Error(
				'brevo_send_failed',
				sprintf(
					/* translators: %s: exception message */
					__( 'Brevo send failed: %s', 'booking-system' ),
					$e->getMessage()
				)
			);
		} catch ( \Brevo\Exceptions\BrevoException $e ) {
			return new \WP_Error(
				'brevo_send_failed',
				sprintf(
					/* translators: %s: exception message */
					__( 'Brevo send failed: %s', 'booking-system' ),
					$e->getMessage()
				)
			);
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'brevo_send_failed',
				sprintf(
					/* translators: %s: exception message */
					__( 'Brevo send failed: %s', 'booking-system' ),
					$e->getMessage()
				)
			);
		}
	}
}
