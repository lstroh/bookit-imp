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

// Load cancellation policy text from settings storage.
$default_cancellation_policy_text = __( 'Please contact us if you need to cancel or reschedule your appointment.', 'bookit-booking-system' );
$cancellation_policy_text         = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
		'cancellation_policy_text'
	)
);

if ( null === $cancellation_policy_text || '' === trim( (string) $cancellation_policy_text ) ) {
	// Backward-compatible fallback if a site stores this in wp_options.
	$cancellation_policy_text = get_option( 'bookit_setting_cancellation_policy_text', '' );
}

if ( '' === trim( (string) $cancellation_policy_text ) ) {
	$cancellation_policy_text = $default_cancellation_policy_text;
}

// Package options (feature-gated).
$packages_enabled   = function_exists( 'bookit_get_setting' ) ? (string) bookit_get_setting( 'packages_enabled' ) : '';
$available_packages = array();

if ( '' === $packages_enabled ) {
	$packages_enabled = (string) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
			'packages_enabled'
		)
	);
}

if ( '1' === $packages_enabled ) {
	$package_rows = $wpdb->get_results(
		"SELECT id, name, sessions_count, price_mode, fixed_price, expiry_enabled, expiry_days, applicable_service_ids
		FROM {$wpdb->prefix}bookings_package_types
		WHERE is_active = 1",
		ARRAY_A
	);
	$service_id   = isset( $session_data['service_id'] ) ? (int) $session_data['service_id'] : 0;

	foreach ( (array) $package_rows as $row ) {
		$service_ids = null;
		if ( ! empty( $row['applicable_service_ids'] ) ) {
			$decoded = json_decode( (string) $row['applicable_service_ids'], true );
			if ( is_array( $decoded ) ) {
				$service_ids = array_values( array_map( 'absint', $decoded ) );
			}
		}

		if ( null === $service_ids || in_array( $service_id, (array) $service_ids, true ) ) {
			$available_packages[] = $row;
		}
	}
}

$existing_packages = array();
if ( '1' === $packages_enabled && ! empty( $session_data['customer_email'] ) ) {
	$customer = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}bookings_customers WHERE email = %s LIMIT 1",
			$session_data['customer_email']
		)
	);

	if ( $customer ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cp.id, cp.sessions_remaining, cp.sessions_total, cp.expires_at,
					pt.name AS package_type_name, pt.applicable_service_ids
				FROM {$wpdb->prefix}bookings_customer_packages cp
				JOIN {$wpdb->prefix}bookings_package_types pt ON pt.id = cp.package_type_id
				WHERE cp.customer_id = %d
					AND cp.status = 'active'
					AND cp.sessions_remaining > 0
					AND (cp.expires_at IS NULL OR cp.expires_at > NOW())",
				$customer->id
			),
			ARRAY_A
		);

		$current_service_id = isset( $session_data['service_id'] ) ? (int) $session_data['service_id'] : 0;
		foreach ( (array) $rows as $row ) {
			$applicable = null;
			if ( ! empty( $row['applicable_service_ids'] ) ) {
				$applicable = json_decode( (string) $row['applicable_service_ids'], true );
			}
			if ( null === $applicable || in_array( $current_service_id, (array) $applicable, true ) ) {
				$existing_packages[] = $row;
			}
		}
	}
}
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
			<input type="hidden" name="payment_method" id="bookit-payment-method" value="stripe" />

			<div class="bookit-payment-option">
				<input type="radio"
					name="bookit_payment_method_choice"
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
					name="bookit_payment_method_choice"
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
					name="bookit_payment_method_choice"
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

			<?php if ( ! empty( $cancellation_policy_text ) ) : ?>
				<div class="bookit-policy-notice" role="note">
					<p class="bookit-policy-notice__heading">
						📋 <?php esc_html_e( 'Cancellation Policy', 'bookit-booking-system' ); ?>
					</p>
					<p class="bookit-policy-notice__body">
						<?php echo wp_kses_post( nl2br( (string) $cancellation_policy_text ) ); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( '1' === $packages_enabled && ! empty( $existing_packages ) ) : ?>
				<div class="bookit-existing-packages" id="bookit-existing-packages">
					<h3><?php esc_html_e( 'Use one of your packages', 'bookit-booking-system' ); ?></h3>

					<div class="bookit-existing-package-list" role="radiogroup" aria-label="<?php esc_attr_e( 'Your packages', 'bookit-booking-system' ); ?>">
						<?php foreach ( $existing_packages as $pkg ) : ?>
							<label class="bookit-existing-package-item">
								<input
									type="radio"
									name="bookit_existing_package_selection"
									class="bookit-existing-package-radio"
									value="<?php echo esc_attr( $pkg['id'] ); ?>"
									data-package-id="<?php echo esc_attr( $pkg['id'] ); ?>"
								>
								<span class="bookit-existing-package-label">
									<strong><?php echo esc_html( $pkg['package_type_name'] ); ?></strong>
									&mdash; <?php echo esc_html( (string) $pkg['sessions_remaining'] ); ?>/<?php echo esc_html( (string) $pkg['sessions_total'] ); ?> <?php esc_html_e( 'sessions remaining', 'bookit-booking-system' ); ?>
									<?php if ( ! empty( $pkg['expires_at'] ) ) : ?>
										<span class="bookit-package-expiry">
											(<?php
											echo esc_html(
												sprintf(
													/* translators: %s: expiry date */
													__( 'Expires %s', 'bookit-booking-system' ),
													date_i18n( get_option( 'date_format' ), strtotime( (string) $pkg['expires_at'] ) )
												)
											);
											?>)
										</span>
									<?php endif; ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>

					<input type="hidden" name="bookit_selected_existing_package_id" id="bookit-selected-existing-package-id" value="">
				</div>
			<?php endif; ?>

			<?php if ( '1' === $packages_enabled && ! empty( $available_packages ) ) : ?>
				<div class="bookit-package-options" id="bookit-package-options">
					<h3><?php esc_html_e( 'Or buy a session package', 'bookit-booking-system' ); ?></h3>
					<p class="bookit-package-note">
						<?php esc_html_e( 'Purchase a bundle of sessions at a discounted rate. One session will be applied to today\'s booking.', 'bookit-booking-system' ); ?>
					</p>

					<div class="bookit-package-list" role="radiogroup" aria-label="<?php esc_attr_e( 'Available packages', 'bookit-booking-system' ); ?>">
						<?php foreach ( $available_packages as $pkg ) : ?>
							<label class="bookit-package-item">
								<input
									type="radio"
									name="bookit_package_selection"
									class="bookit-package-radio"
									value="<?php echo esc_attr( $pkg['id'] ); ?>"
									data-package-id="<?php echo esc_attr( $pkg['id'] ); ?>"
									data-package-name="<?php echo esc_attr( $pkg['name'] ); ?>"
								>
								<span class="bookit-package-label">
									<strong><?php echo esc_html( $pkg['name'] ); ?></strong>
									&mdash; <?php echo esc_html( (string) $pkg['sessions_count'] ); ?> <?php esc_html_e( 'sessions', 'bookit-booking-system' ); ?>
									<?php if ( 'fixed' === $pkg['price_mode'] && ! empty( $pkg['fixed_price'] ) ) : ?>
										&mdash; <?php echo esc_html( sprintf( '£%s', number_format( (float) $pkg['fixed_price'], 2 ) ) ); ?>
									<?php endif; ?>
									<?php if ( ! empty( $pkg['expiry_enabled'] ) && ! empty( $pkg['expiry_days'] ) ) : ?>
										<span class="bookit-package-expiry">
											(<?php echo esc_html( sprintf( __( 'Valid for %d days', 'bookit-booking-system' ), (int) $pkg['expiry_days'] ) ); ?>)
										</span>
									<?php endif; ?>
								</span>
							</label>
						<?php endforeach; ?>
					</div>

					<input type="hidden" name="bookit_selected_package_id" id="bookit-selected-package-id" value="">

					<div class="bookit-package-payment-notice" id="bookit-package-payment-notice" style="display:none;">
						<p><?php esc_html_e( 'Package payment will be collected when you proceed. Your booking slot is held for you.', 'bookit-booking-system' ); ?></p>
					</div>
				</div>
			<?php endif; ?>

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
		var paymentOptions    = document.querySelectorAll( 'input[name="bookit_payment_method_choice"]' );
		var paymentInfo       = document.getElementById( 'bookit-payment-info' );
		var stripeInfo        = document.getElementById( 'bookit-stripe-info' );
		var poaInfo           = document.getElementById( 'bookit-poa-info' );
		var depositRow        = document.querySelector( '.price-row.deposit' );
		var balanceRow        = document.querySelector( '.price-row.balance' );
		var submitBtn         = document.querySelector( '#bookit-payment-form .bookit-btn-primary' );
		var paymentMethodInput = document.getElementById( 'bookit-payment-method' );
		var packageRadios     = document.querySelectorAll( '.bookit-package-radio' );
		var selectedPackageId = document.getElementById( 'bookit-selected-package-id' );
		var packageNotice     = document.getElementById( 'bookit-package-payment-notice' );
		var existingPackageRadios = document.querySelectorAll( '.bookit-existing-package-radio' );
		var selectedExistingPackageId = document.getElementById( 'bookit-selected-existing-package-id' );

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

		function clearPackageSelection() {
			packageRadios.forEach( function( packageOption ) {
				packageOption.checked = false;
			} );
			if ( selectedPackageId ) {
				selectedPackageId.value = '';
			}
			if ( packageNotice ) {
				packageNotice.style.display = 'none';
			}
		}

		function clearExistingPackageSelection() {
			existingPackageRadios.forEach( function( packageOption ) {
				packageOption.checked = false;
			} );
			if ( selectedExistingPackageId ) {
				selectedExistingPackageId.value = '';
			}
		}

		function clearPaymentSelection() {
			paymentOptions.forEach( function( option ) {
				option.checked = false;
			} );
		}

		paymentOptions.forEach( function( option ) {
			option.addEventListener( 'change', function() {
				if ( paymentMethodInput ) {
					paymentMethodInput.value = this.value;
				}
				clearPackageSelection();
				clearExistingPackageSelection();
				updatePaymentUI( this.value );
			});
		});

		packageRadios.forEach( function( packageOption ) {
			packageOption.addEventListener( 'change', function() {
				if ( this.checked ) {
					if ( paymentMethodInput ) {
						paymentMethodInput.value = 'stripe';
					}
					if ( selectedPackageId ) {
						selectedPackageId.value = this.getAttribute( 'data-package-id' ) || '';
					}
					if ( packageNotice ) {
						packageNotice.style.display = 'block';
					}
					clearExistingPackageSelection();
					clearPaymentSelection();
				}
			} );
		} );

		existingPackageRadios.forEach( function( packageOption ) {
			packageOption.addEventListener( 'change', function() {
				if ( this.checked ) {
					if ( selectedExistingPackageId ) {
						selectedExistingPackageId.value = this.getAttribute( 'data-package-id' ) || '';
					}
					if ( paymentMethodInput ) {
						paymentMethodInput.value = 'use_package';
					}
					clearPaymentSelection();
					clearPackageSelection();
				}
			} );
		} );

		/* Initialise for the pre-selected option */
		var checked = document.querySelector( 'input[name="bookit_payment_method_choice"]:checked' );
		if ( checked ) {
			if ( paymentMethodInput ) {
				paymentMethodInput.value = checked.value;
			}
			updatePaymentUI( checked.value );
		}
	});
})();
</script>
