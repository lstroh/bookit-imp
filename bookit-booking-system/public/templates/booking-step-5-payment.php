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

// Fetch service deposit config.
$service_deposit_type   = $service['deposit_type'] ?? 'none';
$service_deposit_amount = (float) ( $service['deposit_amount'] ?? 0 );
$total_price            = (float) ( $service['price'] ?? 0 );

// Load staff for summary display.
$staff = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT first_name, last_name FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
		(int) $session_data['staff_id']
	),
	ARRAY_A
);

$staff_name = '';
if ( ! empty( $staff ) ) {
	$staff_name = trim( (string) ( $staff['first_name'] ?? '' ) . ' ' . (string) ( $staff['last_name'] ?? '' ) );
}

// Calculate deposit due today and balance due on arrival for summary display.
$has_deposit   = false;
$deposit_due   = 0.00;
$balance_due   = $total_price;
$deposit_label = '';

if ( 'percentage' === $service_deposit_type && $service_deposit_amount > 0 ) {
	$has_deposit   = true;
	$deposit_due   = round( $total_price * ( $service_deposit_amount / 100 ), 2 );
	$balance_due   = round( $total_price - $deposit_due, 2 );
	$deposit_label = number_format( $service_deposit_amount, 0 ) . '%';
} elseif ( 'fixed' === $service_deposit_type && $service_deposit_amount > 0 ) {
	$has_deposit   = true;
	$deposit_due   = min( $service_deposit_amount, $total_price );
	$balance_due   = round( $total_price - $deposit_due, 2 );
	$deposit_label = '';
}
// deposit_type "none" (or empty/unknown): no deposit split, full amount due today.
?>

<div class="bookit-payment-step bookit-step bookit-step-5">

	<?php
	// Display error message if booking failed (error code passed via URL, no session dependency).
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$bookit_error_code = isset( $_GET['bookit_error'] ) ? sanitize_text_field( wp_unslash( $_GET['bookit_error'] ) ) : '';

	if ( '' !== $bookit_error_code ) :
		// Map error codes to user-friendly messages.
		$bookit_error_messages = array(
			'slot_unavailable' => __( 'Sorry, this time slot was just booked by another customer. Please choose a different time.', 'bookit-booking-system' ),
			'invalid_service'  => __( 'The selected service is no longer available. Please start your booking again.', 'bookit-booking-system' ),
			'invalid_staff'    => __( 'The selected staff member is no longer available. Please choose another.', 'bookit-booking-system' ),
			'invalid_email'    => __( 'The email address provided is invalid. Please go back and correct it.', 'bookit-booking-system' ),
			'missing_field'    => __( 'Some required information is missing. Please go back and fill in all fields.', 'bookit-booking-system' ),
			'database_error'   => __( 'A system error occurred. Please try again or contact us for assistance.', 'bookit-booking-system' ),
		);
		$bookit_error_message = isset( $bookit_error_messages[ $bookit_error_code ] )
			? $bookit_error_messages[ $bookit_error_code ]
			: __( 'Something went wrong with your booking. Please try again.', 'bookit-booking-system' );
		?>
		<div class="bookit-error-message" style="background: #fee; border-left: 4px solid #d33; padding: 15px; margin: 0 0 20px; border-radius: 4px;">
			<strong style="color: #d33;"><?php esc_html_e( 'Booking Failed', 'bookit-booking-system' ); ?></strong>
			<p style="margin: 10px 0 0; color: #333;">
				<?php echo esc_html( $bookit_error_message ); ?>
			</p>

			<?php if ( 'slot_unavailable' === $bookit_error_code ) : ?>
				<p style="margin: 10px 0 0; font-size: 14px; color: #666;">
					<?php esc_html_e( 'Please select a different date or time and try again.', 'bookit-booking-system' ); ?>
				</p>
				<a href="<?php echo esc_url( home_url( '/book?step=3' ) ); ?>"
					style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px;">
					<?php esc_html_e( '← Choose Different Time', 'bookit-booking-system' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Step 5: Payment', 'bookit-booking-system' ); ?></h2>

	<div class="bookit-booking-summary">
		<h3><?php esc_html_e( 'Booking Summary', 'bookit-booking-system' ); ?></h3>
		<p class="bookit-summary-service-line">
			<strong><?php echo esc_html( $service['name'] ); ?></strong>
			<span>£<?php echo esc_html( number_format( $total_price, 2 ) ); ?></span>
		</p>
		<p>
			<?php
			printf(
				/* translators: 1: booking date, 2: booking time */
				esc_html__( '%1$s at %2$s', 'bookit-booking-system' ),
				esc_html( gmdate( 'D, j F Y', strtotime( $session_data['date'] ) ) ),
				esc_html( gmdate( 'g:i A', strtotime( $session_data['time'] ) ) )
			);
			?>
		</p>
		<?php if ( '' !== $staff_name ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: staff name */
					esc_html__( 'with %s', 'bookit-booking-system' ),
					esc_html( $staff_name )
				);
				?>
			</p>
		<?php endif; ?>
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

			<div class="bookit-payment-option">
				<input type="radio"
					name="payment_method"
					id="payment-arrival"
					value="pay_on_arrival"
				/>
				<label for="payment-arrival">
					<span class="payment-title"><?php esc_html_e( 'Pay on Arrival', 'bookit-booking-system' ); ?></span>
					<span class="payment-description"><?php esc_html_e( 'Pay the full amount when you arrive for your appointment', 'bookit-booking-system' ); ?></span>
				</label>
			</div>

			<div id="bookit-payment-info" class="bookit-payment-info" style="display: none;">
				<div id="bookit-stripe-info" style="display: none;">
					<p><?php esc_html_e( "You'll be redirected to Stripe's secure payment page to complete your booking.", 'bookit-booking-system' ); ?></p>
				</div>

				<div id="bookit-poa-info" style="display: none;">
					<p>
						<?php
						if ( $has_deposit ) {
							printf(
								/* translators: %s: formatted total price */
								esc_html__( 'Pay %s when you arrive. No deposit required for this payment method.', 'bookit-booking-system' ),
								'<strong>&pound;' . esc_html( number_format( $total_price, 2 ) ) . '</strong>'
							);
						} else {
							printf(
								/* translators: %s: formatted total price */
								esc_html__( "No payment required now. You'll pay %s when you arrive for your appointment.", 'bookit-booking-system' ),
								'<strong>&pound;' . esc_html( number_format( $total_price, 2 ) ) . '</strong>'
							);
						}
						?>
					</p>
					<p class="bookit-poa-note">
						<?php esc_html_e( 'Your booking will be confirmed immediately. Please arrive 5-10 minutes early.', 'bookit-booking-system' ); ?>
					</p>
				</div>
			</div>

			<div class="bookit-payment-summary">
				<?php if ( $has_deposit ) : ?>
					<div class="price-row deposit">
						<span>
							<?php esc_html_e( 'Due today (deposit):', 'bookit-booking-system' ); ?>
							<?php if ( '' !== $deposit_label ) : ?>
								<small>(<?php echo esc_html( $deposit_label ); ?>)</small>
							<?php endif; ?>
						</span>
						<span>£<?php echo esc_html( number_format( $deposit_due, 2 ) ); ?></span>
					</div>
					<div class="price-row balance">
						<span><?php esc_html_e( 'Due on arrival (balance):', 'bookit-booking-system' ); ?></span>
						<span>£<?php echo esc_html( number_format( $balance_due, 2 ) ); ?></span>
					</div>
					<div class="price-row">
						<span><?php esc_html_e( 'Total:', 'bookit-booking-system' ); ?></span>
						<span>£<?php echo esc_html( number_format( $total_price, 2 ) ); ?></span>
					</div>
					<div class="bookit-deposit-notice">
						<?php
						printf(
							/* translators: %s: formatted balance amount */
							esc_html__( 'You are paying a deposit today. The remaining balance of %s is due when you arrive for your appointment.', 'bookit-booking-system' ),
							'£' . esc_html( number_format( $balance_due, 2 ) )
						);
						?>
					</div>
				<?php else : ?>
					<div class="price-row">
						<span><?php esc_html_e( 'Total due today:', 'bookit-booking-system' ); ?></span>
						<span>£<?php echo esc_html( number_format( $total_price, 2 ) ); ?></span>
					</div>
				<?php endif; ?>
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

<script>
(function() {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function() {
		var hasDeposit       = <?php echo wp_json_encode( $has_deposit ); ?>;
		var paymentOptions    = document.querySelectorAll( 'input[name="payment_method"]' );
		var paymentInfo       = document.getElementById( 'bookit-payment-info' );
		var stripeInfo        = document.getElementById( 'bookit-stripe-info' );
		var poaInfo           = document.getElementById( 'bookit-poa-info' );
		var depositRow        = document.querySelector( '.price-row.deposit' );
		var balanceRow        = document.querySelector( '.price-row.balance' );
		var submitBtn         = document.querySelector( '#bookit-payment-form .bookit-btn-primary' );

		function updatePaymentUI( value ) {
			/* Show/hide the info panel */
			paymentInfo.style.display = 'block';
			stripeInfo.style.display  = 'none';
			poaInfo.style.display     = 'none';

			if ( value === 'stripe' ) {
				stripeInfo.style.display = 'block';
				if ( hasDeposit && depositRow ) depositRow.style.display = '';
				if ( hasDeposit && balanceRow ) balanceRow.style.display = '';
				if ( submitBtn )  submitBtn.textContent = '<?php echo esc_js( __( 'Complete Booking →', 'bookit-booking-system' ) ); ?>';
			} else if ( value === 'pay_on_arrival' ) {
				poaInfo.style.display = 'block';
				if ( hasDeposit && depositRow ) depositRow.style.display = 'none';
				if ( hasDeposit && balanceRow ) balanceRow.style.display = 'none';
				if ( submitBtn )  submitBtn.textContent = '<?php echo esc_js( __( 'Confirm Booking →', 'bookit-booking-system' ) ); ?>';
			}
		}

		paymentOptions.forEach( function( option ) {
			option.addEventListener( 'change', function() {
				updatePaymentUI( this.value );
			});
		});

		/* Initialise for the pre-selected option */
		var checked = document.querySelector( 'input[name="payment_method"]:checked' );
		if ( checked ) {
			updatePaymentUI( checked.value );
		}
	});
})();
</script>
