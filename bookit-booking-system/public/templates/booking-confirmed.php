<?php
/**
 * Booking Confirmation Page
 * Displayed after successful payment
 *
 * @package Booking_System
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get booking ID (Pay on Arrival) or Stripe session ID from URL.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';

if ( 0 === $booking_id && '' === $session_id ) {
	?>
	<div class="bookit-confirmation-error">
		<h2><?php esc_html_e( 'Booking Not Found', 'booking-system' ); ?></h2>
		<p><?php esc_html_e( 'We couldn\'t find your booking. The confirmation link may be invalid or expired.', 'booking-system' ); ?></p>
		<p><a href="<?php echo esc_url( home_url( '/book' ) ); ?>" class="bookit-btn-primary">
			<?php esc_html_e( 'Make a New Booking', 'booking-system' ); ?>
		</a></p>
	</div>
	<?php
	return;
}

// Retrieve booking by ID (Pay on Arrival) or Stripe session ID (Stripe/PayPal).
require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-retriever.php';
require_once BOOKIT_PLUGIN_DIR . 'includes/email/class-email-sender.php';

$retriever = new Booking_System_Booking_Retriever();

if ( $booking_id > 0 ) {
	$booking = $retriever->get_booking_by_id( $booking_id );
} else {
	$booking = $retriever->get_booking_by_stripe_session( $session_id );
}

if ( ! $booking ) {
	?>
	<div class="bookit-confirmation-error">
		<h2><?php esc_html_e( 'Booking Not Found', 'booking-system' ); ?></h2>
		<p><?php esc_html_e( 'We couldn\'t retrieve your booking details. Please contact us if you need assistance.', 'booking-system' ); ?></p>
		<p><a href="<?php echo esc_url( home_url( '/book' ) ); ?>" class="bookit-btn-primary">
			<?php esc_html_e( 'Make a New Booking', 'booking-system' ); ?>
		</a></p>
	</div>
	<?php
	return;
}

// Send confirmation emails (only send once - check if already sent).
// Pay on Arrival emails are already sent during booking creation, so only send here
// for Stripe/PayPal bookings (identified by arriving via session_id parameter).
$email_sent_key      = 'bookit_email_sent_' . $booking['id'];
$emails_already_sent = get_transient( $email_sent_key );
$is_pay_on_arrival   = isset( $booking['payment_method'] ) && 'pay_on_arrival' === $booking['payment_method'];

if ( ! $emails_already_sent && ! $is_pay_on_arrival ) {
	$email_sender = new Booking_System_Email_Sender();

	// Send customer confirmation.
	$customer_result = $email_sender->send_customer_confirmation( $booking );
	if ( is_wp_error( $customer_result ) ) {
		error_log( 'Confirmation Page: Failed to send customer email - ' . $customer_result->get_error_message() );
	}

	// Send business notification.
	$business_result = $email_sender->send_business_notification( $booking );
	if ( is_wp_error( $business_result ) ) {
		error_log( 'Confirmation Page: Failed to send business email - ' . $business_result->get_error_message() );
	}

	// Mark emails as sent (24 hour transient).
	set_transient( $email_sent_key, true, 24 * HOUR_IN_SECONDS );
}

// Clear booking wizard session
$retriever->clear_booking_session();

/**
 * Fires after the booking confirmation page has loaded and emails
 * have been sent. Extensions hook here to generate and store a
 * meeting link for this booking.
 *
 * @param int   $booking_id The booking ID.
 * @param array $booking    The full booking data array.
 */
do_action( 'bookit_after_booking_confirmed', $booking['id'], $booking );

// Format date and time for display
$date_formatted = $retriever->format_date( $booking['booking_date'] );
$time_formatted = $retriever->format_time( $booking['start_time'] );
?>

<div class="bookit-confirmation-page">
	<div class="bookit-confirmation-header">
		<div class="bookit-success-icon">✓</div>
		<h1><?php esc_html_e( 'Booking Confirmed!', 'booking-system' ); ?></h1>
		<p class="bookit-confirmation-message">
			<?php esc_html_e( 'Thank you for your booking. A confirmation email has been sent to', 'booking-system' ); ?>
			<strong><?php echo esc_html( $booking['customer_email'] ); ?></strong>
		</p>
	</div>

	<div class="bookit-confirmation-details">
		<h2><?php esc_html_e( 'Booking Details', 'booking-system' ); ?></h2>

		<div class="bookit-detail-card">
			<div class="bookit-detail-row">
				<span class="bookit-detail-label"><?php esc_html_e( 'Booking Reference:', 'booking-system' ); ?></span>
				<span class="bookit-detail-value">#<?php echo esc_html( (string) $booking['id'] ); ?></span>
			</div>

			<div class="bookit-detail-row">
				<span class="bookit-detail-label"><?php esc_html_e( 'Service:', 'booking-system' ); ?></span>
				<span class="bookit-detail-value"><?php echo esc_html( $booking['service_name'] ); ?></span>
			</div>

			<div class="bookit-detail-row">
				<span class="bookit-detail-label"><?php esc_html_e( 'Date:', 'booking-system' ); ?></span>
				<span class="bookit-detail-value"><?php echo esc_html( $date_formatted ); ?></span>
			</div>

			<div class="bookit-detail-row">
				<span class="bookit-detail-label"><?php esc_html_e( 'Time:', 'booking-system' ); ?></span>
				<span class="bookit-detail-value"><?php echo esc_html( $time_formatted ); ?></span>
			</div>

			<div class="bookit-detail-row">
				<span class="bookit-detail-label"><?php esc_html_e( 'Staff Member:', 'booking-system' ); ?></span>
				<span class="bookit-detail-value"><?php echo esc_html( $booking['staff_name'] ); ?></span>
			</div>

			<div class="bookit-detail-row">
				<span class="bookit-detail-label"><?php esc_html_e( 'Customer:', 'booking-system' ); ?></span>
				<span class="bookit-detail-value"><?php echo esc_html( $booking['customer_name'] ); ?></span>
			</div>

			<?php if ( ! empty( $booking['cooling_off_waiver_given'] ) ) : ?>
				<div class="bookit-detail-row">
					<span class="bookit-detail-value">
						<?php esc_html_e( '✓ You have waived your 14-day right to cancel for this booking (Consumer Contracts Regulations 2013).', 'bookit-booking-system' ); ?>
					</span>
				</div>
			<?php endif; ?>
		</div>

		<div class="bookit-payment-summary">
			<h3><?php esc_html_e( 'Payment Summary', 'booking-system' ); ?></h3>

			<div class="bookit-detail-row">
				<span class="bookit-detail-label"><?php esc_html_e( 'Total Price:', 'booking-system' ); ?></span>
				<span class="bookit-detail-value">&pound;<?php echo esc_html( number_format( (float) $booking['total_price'], 2 ) ); ?></span>
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
				<div class="bookit-detail-row bookit-paid">
					<span class="bookit-detail-label"><?php esc_html_e( 'Paid Today:', 'booking-system' ); ?></span>
					<span class="bookit-detail-value">&pound;<?php echo esc_html( number_format( (float) $booking['deposit_paid'], 2 ) ); ?></span>
				</div>

				<?php if ( (float) $booking['balance_due'] > 0 ) : ?>
					<div class="bookit-detail-row bookit-balance">
						<span class="bookit-detail-label"><?php esc_html_e( 'Balance Due (pay on arrival):', 'booking-system' ); ?></span>
						<span class="bookit-detail-value">&pound;<?php echo esc_html( number_format( (float) $booking['balance_due'], 2 ) ); ?></span>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<div class="bookit-detail-row">
				<span class="bookit-detail-label"><?php esc_html_e( 'Payment Method:', 'booking-system' ); ?></span>
				<span class="bookit-detail-value"><?php echo esc_html( ucwords( str_replace( '_', ' ', $booking['payment_method'] ?? '' ) ) ); ?></span>
			</div>
		</div>

		<?php if ( ! empty( $booking['special_requests'] ) ) : ?>
			<div class="bookit-special-requests">
				<h3><?php esc_html_e( 'Special Requests', 'booking-system' ); ?></h3>
				<p><?php echo esc_html( $booking['special_requests'] ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<?php
	/**
	 * Filter the meeting section HTML on the confirmation page.
	 * Return non-empty HTML from an extension to display a meeting link.
	 * Return empty string (default) to show nothing.
	 *
	 * @param string $html    The meeting section HTML. Default ''.
	 * @param array  $booking The full booking data array.
	 */
	$bookit_meeting_section_html = apply_filters(
		'bookit_confirmation_meeting_section',
		'',
		$booking
	);
	if ( '' !== $bookit_meeting_section_html ) {
		echo wp_kses_post( $bookit_meeting_section_html );
	}
	?>

	<div class="bookit-confirmation-actions">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="bookit-btn-secondary">
			<?php esc_html_e( '← Back to Home', 'booking-system' ); ?>
		</a>
		<a href="<?php echo esc_url( home_url( '/book' ) ); ?>" class="bookit-btn-primary">
			<?php esc_html_e( 'Make Another Booking', 'booking-system' ); ?>
		</a>
	</div>

	<div class="bookit-confirmation-help">
		<p><?php esc_html_e( 'Need to cancel or reschedule? Please contact us.', 'booking-system' ); ?></p>
	</div>
</div>
