<?php
/**
 * Booking Step 5: Payment Method Selection
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
Bookit_Session_Manager::init();
$session_data = Bookit_Session_Manager::get_data();

if ( empty( $session_data ) || (int) ( $session_data['current_step'] ?? 0 ) < 4 ) {
	wp_safe_redirect( home_url( '/book?step=1' ) );
	exit;
}

global $wpdb;
$service = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
		(int) $session_data['service_id']
	),
	ARRAY_A
);

if ( ! $service ) {
	echo '<p class="bookit-error">' . esc_html__( 'Service not found.', 'bookit-booking-system' ) . '</p>';
	return;
}

$stripe_checkout = new Booking_System_Stripe_Checkout();
$deposit_amount  = $stripe_checkout->calculate_deposit( $service );
$total_price     = isset( $service['base_price'] ) ? (float) $service['base_price'] : (float) $service['price'];
$balance         = $total_price - $deposit_amount;
?>

<div class="bookit-payment-step bookit-step bookit-step-5">
	<h2><?php esc_html_e( 'Step 5: Payment', 'bookit-booking-system' ); ?></h2>

	<div class="bookit-booking-summary">
		<h3><?php esc_html_e( 'Booking Summary', 'bookit-booking-system' ); ?></h3>
		<p><strong><?php echo esc_html( $service['name'] ); ?></strong></p>
		<p><?php echo esc_html( gmdate( 'l, j F Y', strtotime( $session_data['date'] ) ) ); ?></p>
		<p><?php echo esc_html( gmdate( 'g:i A', strtotime( $session_data['time'] ) ) ); ?></p>
	</div>

	<div class="bookit-payment-options">
		<h3><?php esc_html_e( 'Choose Payment Method', 'bookit-booking-system' ); ?></h3>

		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bookit-payment-form">
			<?php wp_nonce_field( 'bookit_booking_action', 'bookit_nonce' ); ?>
			<input type="hidden" name="action" value="bookit_process_payment" />

			<div class="bookit-payment-option">
				<input type="radio"
					name="payment_method"
					id="payment-stripe"
					value="stripe"
					checked
				/>
				<label for="payment-stripe">
					<span class="payment-title"><?php esc_html_e( 'Credit/Debit Card', 'bookit-booking-system' ); ?></span>
					<span class="payment-description"><?php esc_html_e( 'Secure payment via Stripe', 'bookit-booking-system' ); ?></span>
				</label>
			</div>

			<div class="bookit-payment-option" style="opacity: 0.5;">
				<input type="radio"
					name="payment_method"
					id="payment-paypal"
					value="paypal"
					disabled
				/>
				<label for="payment-paypal">
					<span class="payment-title"><?php esc_html_e( 'PayPal', 'bookit-booking-system' ); ?></span>
					<span class="payment-description"><?php esc_html_e( 'Coming soon', 'bookit-booking-system' ); ?></span>
				</label>
			</div>

			<div class="bookit-payment-option" style="opacity: 0.5;">
				<input type="radio"
					name="payment_method"
					id="payment-arrival"
					value="pay_on_arrival"
					disabled
				/>
				<label for="payment-arrival">
					<span class="payment-title"><?php esc_html_e( 'Pay on Arrival', 'bookit-booking-system' ); ?></span>
					<span class="payment-description"><?php esc_html_e( 'Coming soon', 'bookit-booking-system' ); ?></span>
				</label>
			</div>

			<div class="bookit-payment-summary">
				<div class="price-row">
					<span><?php esc_html_e( 'Total:', 'bookit-booking-system' ); ?></span>
					<span>£<?php echo esc_html( number_format( $total_price, 2 ) ); ?></span>
				</div>
				<div class="price-row deposit">
					<span><?php esc_html_e( 'Deposit:', 'bookit-booking-system' ); ?></span>
					<span>£<?php echo esc_html( number_format( $deposit_amount, 2 ) ); ?></span>
				</div>
				<div class="price-row balance">
					<span><?php esc_html_e( 'Balance (pay on arrival):', 'bookit-booking-system' ); ?></span>
					<span>£<?php echo esc_html( number_format( $balance, 2 ) ); ?></span>
				</div>
			</div>

			<div class="bookit-form-actions">
				<a href="<?php echo esc_url( home_url( '/book?step=4' ) ); ?>" class="bookit-btn-secondary">
					<?php esc_html_e( '← Back', 'bookit-booking-system' ); ?>
				</a>
				<button type="submit" class="bookit-btn-primary">
					<?php esc_html_e( 'Complete Booking →', 'bookit-booking-system' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
