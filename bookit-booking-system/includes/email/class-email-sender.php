<?php
/**
 * Email Sender
 * Sends booking confirmation and notification emails.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/email
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Email Sender class.
 *
 * Sends booking confirmation emails to customers and business notification emails to admin.
 */
class Booking_System_Email_Sender {

	/**
	 * Whether to write messages to error_log (disabled during unit tests).
	 *
	 * @return bool
	 */
	private static function should_log() {
		return ! defined( 'WP_TESTS_TABLE_PREFIX' ) && function_exists( 'error_log' );
	}

	/**
	 * Send customer confirmation email.
	 *
	 * @param array $booking Booking data with customer, service, staff details.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function send_customer_confirmation( $booking ) {
		// Allow tests to bypass actual email sending.
		$bypass = apply_filters( 'bookit_send_email', true );
		if ( $bypass === false ) {
			return true; // Test mode - don't send.
		}

		$to      = $booking['customer_email'];
		$subject = sprintf(
			__( 'Booking Confirmed - %s', 'booking-system' ),
			$booking['service_name']
		);

		$body = $this->generate_customer_email( $booking );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( ! $sent ) {
			if ( self::should_log() ) {
				error_log( 'Email Sender: Failed to send customer confirmation to ' . $to );
			}
			return new WP_Error( 'email_failed', 'Failed to send confirmation email' );
		}

		if ( self::should_log() ) {
			error_log( 'Email Sender: Customer confirmation sent to ' . $to );
		}
		return true;
	}

	/**
	 * Send business notification email.
	 *
	 * @param array $booking Booking data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function send_business_notification( $booking ) {
		// Allow tests to bypass.
		$bypass = apply_filters( 'bookit_send_email', true );
		if ( $bypass === false ) {
			return true;
		}

		$to      = get_option( 'admin_email' );
		$subject = sprintf(
			__( 'New Booking - %s on %s', 'booking-system' ),
			$booking['service_name'],
			$this->format_date( $booking['booking_date'] )
		);

		$body    = $this->generate_business_email( $booking );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( ! $sent ) {
			if ( self::should_log() ) {
				error_log( 'Email Sender: Failed to send business notification to ' . $to );
			}
			return new WP_Error( 'email_failed', 'Failed to send notification email' );
		}

		if ( self::should_log() ) {
			error_log( 'Email Sender: Business notification sent to ' . $to );
		}
		return true;
	}

	/**
	 * Generate customer confirmation email HTML.
	 *
	 * @param array $booking Booking data.
	 * @return string HTML email body.
	 */
	public function generate_customer_email( $booking ) {
		$date_formatted = $this->format_date( $booking['booking_date'] );
		$time_formatted = $this->format_time( $booking['start_time'] );
		$cancellation_policy_text = $this->get_cancellation_policy_text();

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<style>
				body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: #0073aa; color: white; padding: 20px; text-align: center; }
				.content { background: #f9f9f9; padding: 20px; }
				.booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa; }
				.detail-row { padding: 8px 0; border-bottom: 1px solid #eee; }
				.label { font-weight: bold; color: #666; }
				.value { color: #333; }
				.payment-summary { background: #e8f5e9; padding: 15px; margin: 15px 0; }
				.footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h1><?php esc_html_e( 'Booking Confirmed!', 'booking-system' ); ?></h1>
				</div>

				<div class="content">
					<p><?php printf( esc_html__( 'Hi %s,', 'booking-system' ), esc_html( $booking['customer_first_name'] ) ); ?></p>
					<p><?php esc_html_e( 'Your booking has been confirmed. Here are the details:', 'booking-system' ); ?></p>

					<div class="booking-details">
						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Service:', 'booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $booking['service_name'] ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Date:', 'booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $date_formatted ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Time:', 'booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $time_formatted ); ?></span>
						</div>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Staff:', 'booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( $booking['staff_name'] ); ?></span>
						</div>
					</div>

					<?php if ( ! empty( $booking['cooling_off_waiver_given'] ) ) : ?>
						<p><strong><?php esc_html_e( '✓ You have waived your 14-day right to cancel for this booking (Consumer Contracts Regulations 2013).', 'bookit-booking-system' ); ?></strong></p>
					<?php endif; ?>

					<div class="payment-summary">
						<h3><?php esc_html_e( 'Payment Summary', 'booking-system' ); ?></h3>
						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Total:', 'booking-system' ); ?></span>
							<span class="value">&pound;<?php echo esc_html( number_format( (float) $booking['total_price'], 2 ) ); ?></span>
						</div>

						<?php if ( isset( $booking['payment_method'] ) && 'pay_on_arrival' === $booking['payment_method'] ) : ?>
							<div style="background: #fff3cd; padding: 15px; margin: 15px 0; border-left: 4px solid #ffc107; border-radius: 4px;">
								<strong style="color: #856404;"><?php esc_html_e( 'Payment Due on Arrival', 'booking-system' ); ?></strong>
								<p style="margin: 10px 0 0; color: #856404;">
									<?php
									printf(
										/* translators: %s: formatted total price */
										esc_html__( 'Please bring %s to pay when you arrive for your appointment.', 'booking-system' ),
										'<strong>&pound;' . esc_html( number_format( (float) $booking['total_price'], 2 ) ) . '</strong>'
									);
									?>
								</p>
								<p style="margin: 10px 0 0; font-size: 14px; color: #856404;">
									<?php esc_html_e( 'We accept cash and card payments.', 'booking-system' ); ?>
								</p>
							</div>
						<?php else : ?>
							<div class="detail-row">
								<span class="label"><?php esc_html_e( 'Paid:', 'booking-system' ); ?></span>
								<span class="value">&pound;<?php echo esc_html( number_format( (float) $booking['deposit_paid'], 2 ) ); ?></span>
							</div>
							<div class="detail-row">
								<span class="label"><?php esc_html_e( 'Balance Due:', 'booking-system' ); ?></span>
								<span class="value">&pound;<?php echo esc_html( number_format( (float) $booking['balance_due'], 2 ) ); ?></span>
							</div>
						<?php endif; ?>

						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Payment Method:', 'booking-system' ); ?></span>
							<span class="value"><?php echo esc_html( ucwords( str_replace( '_', ' ', $booking['payment_method'] ?? '' ) ) ); ?></span>
						</div>
					</div>

					<?php if ( ! empty( $booking['special_requests'] ) ) : ?>
						<div class="detail-row">
							<span class="label"><?php esc_html_e( 'Special Requests:', 'booking-system' ); ?></span>
							<p><?php echo esc_html( $booking['special_requests'] ); ?></p>
						</div>
					<?php endif; ?>

					<?php
					$is_package_payment_method = isset( $booking['payment_method'] ) && in_array( $booking['payment_method'], array( 'package_redemption', 'use_package' ), true );
					if ( ! empty( $booking['customer_package_id'] ) && $is_package_payment_method ) :
						$package_info = $this->get_package_info_for_email( (int) $booking['customer_package_id'] );
						if ( $package_info ) :
							$package_name = isset( $package_info['package_type_name'] ) ? (string) $package_info['package_type_name'] : '';
							$expires_at   = isset( $package_info['expires_at'] ) ? (string) $package_info['expires_at'] : '';
							?>
							<div style="background:#eef7ff; border:1px solid #c7ddf5; border-left:4px solid #0073aa; border-radius:6px; padding:12px 16px; margin:20px 0;">
								<p style="margin:0; font-size:14px; color:#1f3d56;">
									<?php
									printf(
										/* translators: 1: package name, 2: sessions remaining, 3: total sessions */
										esc_html__( 'Sessions remaining on your %1$s package: %2$d of %3$d', 'booking-system' ),
										esc_html( $package_name ),
										(int) $package_info['sessions_remaining'],
										(int) $package_info['sessions_total']
									);
									?>
								</p>
								<?php if ( ! empty( $expires_at ) ) : ?>
									<p style="margin:8px 0 0; font-size:14px; color:#1f3d56;">
										<?php
										printf(
											/* translators: %s: package expiry date */
											esc_html__( 'Your package expires on: %s', 'booking-system' ),
											esc_html( date_i18n( get_option( 'date_format' ), strtotime( $expires_at ) ) )
										);
										?>
									</p>
								<?php endif; ?>
							</div>
							<?php
						endif;
					endif;
					?>

					<?php if ( ! empty( $cancellation_policy_text ) ) : ?>
						<div style="background:#f0f4f8; border:1px solid #b0c4d8; border-left:4px solid #0073aa; border-radius:6px; padding:12px 16px; margin:20px 0;">
							<p style="margin:0 0 6px; font-weight:600; font-size:13px; color:#1a3a52;">
								📋 <?php esc_html_e( 'Cancellation Policy', 'bookit-booking-system' ); ?>
							</p>
							<p style="margin:0; font-size:13px; color:#2c5282; line-height:1.6;">
								<?php echo nl2br( esc_html( $cancellation_policy_text ) ); ?>
							</p>
						</div>
					<?php endif; ?>

					<?php
					/**
					 * Filter the meeting section HTML in the customer confirmation email.
					 * Return non-empty HTML from an extension to display a meeting link row.
					 * Return empty string (default) to show nothing.
					 *
					 * @param string $html    The meeting section HTML. Default ''.
					 * @param array  $booking The booking data array passed to this method.
					 */
					$bookit_email_meeting_html = apply_filters(
						'bookit_email_meeting_section',
						'',
						$booking
					);
					if ( '' !== $bookit_email_meeting_html ) {
						echo wp_kses_post( $bookit_email_meeting_html );
					}
					?>

					<p><?php esc_html_e( 'We look forward to seeing you!', 'booking-system' ); ?></p>
				</div>

				<div class="footer">
					<p><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
					<p><?php esc_html_e( 'If you need to cancel or reschedule, please contact us.', 'booking-system' ); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate business notification email HTML.
	 *
	 * @param array $booking Booking data.
	 * @return string HTML email body.
	 */
	public function generate_business_email( $booking ) {
		$date_formatted = $this->format_date( $booking['booking_date'] );
		$time_formatted = $this->format_time( $booking['start_time'] );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
		</head>
		<body>
			<h2><?php esc_html_e( 'New Booking Received', 'booking-system' ); ?></h2>

			<p><strong><?php esc_html_e( 'Customer:', 'booking-system' ); ?></strong> <?php echo esc_html( $booking['customer_name'] ); ?></p>
			<p><strong><?php esc_html_e( 'Email:', 'booking-system' ); ?></strong> <?php echo esc_html( $booking['customer_email'] ); ?></p>
			<p><strong><?php esc_html_e( 'Phone:', 'booking-system' ); ?></strong> <?php echo esc_html( $booking['customer_phone'] ); ?></p>

			<hr>

			<p><strong><?php esc_html_e( 'Service:', 'booking-system' ); ?></strong> <?php echo esc_html( $booking['service_name'] ); ?></p>
			<p><strong><?php esc_html_e( 'Date:', 'booking-system' ); ?></strong> <?php echo esc_html( $date_formatted ); ?></p>
			<p><strong><?php esc_html_e( 'Time:', 'booking-system' ); ?></strong> <?php echo esc_html( $time_formatted ); ?></p>
			<p><strong><?php esc_html_e( 'Staff:', 'booking-system' ); ?></strong> <?php echo esc_html( $booking['staff_name'] ); ?></p>

			<hr>

			<p><strong><?php esc_html_e( 'Payment:', 'booking-system' ); ?></strong> &pound;<?php echo esc_html( number_format( (float) $booking['deposit_paid'], 2 ) ); ?> via <?php echo esc_html( ucwords( str_replace( '_', ' ', $booking['payment_method'] ?? '' ) ) ); ?></p>

			<?php if ( isset( $booking['payment_method'] ) && 'pay_on_arrival' === $booking['payment_method'] ) : ?>
				<p style="background: #fff3cd; padding: 10px; border-left: 3px solid #ffc107;">
					<strong><?php esc_html_e( 'Payment Due on Arrival:', 'booking-system' ); ?></strong>
					<?php
					printf(
						/* translators: %s: formatted total price */
						esc_html__( 'Customer will pay %s when they arrive.', 'booking-system' ),
						'&pound;' . esc_html( number_format( (float) $booking['total_price'], 2 ) )
					);
					?>
				</p>
			<?php endif; ?>

			<p><strong><?php esc_html_e( 'Balance Due:', 'booking-system' ); ?></strong> &pound;<?php echo esc_html( number_format( (float) $booking['balance_due'], 2 ) ); ?></p>

			<?php if ( ! empty( $booking['special_requests'] ) ) : ?>
				<hr>
				<p><strong><?php esc_html_e( 'Special Requests:', 'booking-system' ); ?></strong></p>
				<p><?php echo esc_html( $booking['special_requests'] ); ?></p>
			<?php endif; ?>
		</body>
		</html>
		<?php

		return ob_get_clean();
	}

	/**
	 * Format date for email display.
	 *
	 * @param string $date Date string.
	 * @return string Formatted date.
	 */
	private function format_date( $date ) {
		return date( 'l, j F Y', strtotime( $date ) );
	}

	/**
	 * Format time for email display.
	 *
	 * @param string $time Time string.
	 * @return string Formatted time.
	 */
	private function format_time( $time ) {
		return date( 'g:i A', strtotime( $time ) );
	}

	/**
	 * Get cancellation policy text from settings storage.
	 *
	 * @return string
	 */
	private function get_cancellation_policy_text() {
		global $wpdb;

		$default_policy = __( 'Please contact us if you need to cancel or reschedule your appointment.', 'bookit-booking-system' );

		$policy_text = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
				'cancellation_policy_text'
			)
		);

		if ( null === $policy_text || '' === trim( (string) $policy_text ) ) {
			$policy_text = get_option( 'bookit_setting_cancellation_policy_text', '' );
		}

		if ( '' === trim( (string) $policy_text ) ) {
			return $default_policy;
		}

		return (string) $policy_text;
	}

	/**
	 * Fetch customer package details for customer email context.
	 *
	 * @param int $customer_package_id Customer package ID.
	 * @return array<string,mixed>|null
	 */
	private function get_package_info_for_email( int $customer_package_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT cp.sessions_remaining, cp.sessions_total, cp.expires_at,
						pt.name AS package_type_name
				FROM {$wpdb->prefix}bookings_customer_packages cp
				JOIN {$wpdb->prefix}bookings_package_types pt ON pt.id = cp.package_type_id
				WHERE cp.id = %d
				LIMIT 1",
				$customer_package_id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}
}
