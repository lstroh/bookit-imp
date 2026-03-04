<?php
/**
 * Step 4: Contact Details Template
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Get session data.
require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
Bookit_Session_Manager::init();
$session = Bookit_Session_Manager::get_data();

// Check prerequisites (session uses 'date' and 'time', not booking_date/booking_time).
if ( ! isset( $session['service_id'], $session['staff_id'], $session['date'], $session['time'] ) ) {
	echo '<p class="bookit-error">' . esc_html__( 'Please complete the previous steps first.', 'bookit-booking-system' ) . '</p>';
	return;
}

// Pre-fill from session if user came back.
$first_name        = isset( $session['customer_first_name'] ) ? $session['customer_first_name'] : '';
$last_name         = isset( $session['customer_last_name'] ) ? $session['customer_last_name'] : '';
$email             = isset( $session['customer_email'] ) ? $session['customer_email'] : '';
$phone             = isset( $session['customer_phone'] ) ? $session['customer_phone'] : '';
$special_requests  = isset( $session['customer_special_requests'] ) ? $session['customer_special_requests'] : '';
$marketing_consent = isset( $session['marketing_consent'] ) ? (int) $session['marketing_consent'] : 0;
$booking_date      = isset( $session['date'] ) ? $session['date'] : '';
$requires_waiver   = ! empty( $booking_date ) && bookit_booking_requires_waiver( $booking_date );
$waiver_given      = isset( $session['cooling_off_waiver'] ) ? (bool) $session['cooling_off_waiver'] : false;
?>

<div class="bookit-step bookit-step-4">
	<h2><?php esc_html_e( 'Your Details', 'bookit-booking-system' ); ?></h2>
	<p class="step-intro"><?php esc_html_e( 'Almost there! Just a few more details to confirm your booking.', 'bookit-booking-system' ); ?></p>

	<form id="bookit-contact-form" class="bookit-contact-form" novalidate>
		<?php
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-csrf-protection.php';
		Bookit_CSRF_Protection::nonce_field( true, true );
		?>

		<!-- First Name -->
		<div class="form-group">
			<label for="first-name">
				<?php esc_html_e( 'First Name', 'bookit-booking-system' ); ?>
				<span class="required" aria-label="<?php esc_attr_e( 'required', 'bookit-booking-system' ); ?>">*</span>
			</label>
			<input
				type="text"
				id="first-name"
				name="first_name"
				value="<?php echo esc_attr( $first_name ); ?>"
				required
				autocomplete="given-name"
				maxlength="100"
				aria-required="true"
				aria-describedby="first-name-error"
			/>
			<span id="first-name-error" class="error-message" role="alert"></span>
		</div>

		<!-- Last Name -->
		<div class="form-group">
			<label for="last-name">
				<?php esc_html_e( 'Last Name', 'bookit-booking-system' ); ?>
				<span class="required" aria-label="<?php esc_attr_e( 'required', 'bookit-booking-system' ); ?>">*</span>
			</label>
			<input
				type="text"
				id="last-name"
				name="last_name"
				value="<?php echo esc_attr( $last_name ); ?>"
				required
				autocomplete="family-name"
				maxlength="100"
				aria-required="true"
				aria-describedby="last-name-error"
			/>
			<span id="last-name-error" class="error-message" role="alert"></span>
		</div>

		<!-- Email Address -->
		<div class="form-group">
			<label for="email">
				<?php esc_html_e( 'Email Address', 'bookit-booking-system' ); ?>
				<span class="required" aria-label="<?php esc_attr_e( 'required', 'bookit-booking-system' ); ?>">*</span>
			</label>
			<input
				type="email"
				id="email"
				name="email"
				value="<?php echo esc_attr( $email ); ?>"
				required
				autocomplete="email"
				maxlength="255"
				aria-required="true"
				aria-describedby="email-help email-error"
			/>
			<p id="email-help" class="field-help">
				<?php esc_html_e( "We'll send your confirmation here", 'bookit-booking-system' ); ?>
			</p>
			<span id="email-error" class="error-message" role="alert"></span>
		</div>

		<!-- Phone Number -->
		<div class="form-group">
			<label for="phone">
				<?php esc_html_e( 'Phone Number', 'bookit-booking-system' ); ?>
				<span class="required" aria-label="<?php esc_attr_e( 'required', 'bookit-booking-system' ); ?>">*</span>
			</label>
			<input
				type="tel"
				id="phone"
				name="phone"
				value="<?php echo esc_attr( $phone ); ?>"
				required
				autocomplete="tel"
				placeholder="07700 900123"
				maxlength="20"
				aria-required="true"
				aria-describedby="phone-help phone-error"
			/>
			<p id="phone-help" class="field-help">
				<?php esc_html_e( 'For appointment reminders', 'bookit-booking-system' ); ?>
			</p>
			<span id="phone-error" class="error-message" role="alert"></span>
		</div>

		<!-- Special Requests -->
		<div class="form-group">
			<label for="special-requests">
				<?php esc_html_e( 'Special Requests', 'bookit-booking-system' ); ?>
				<span class="optional">(<?php esc_html_e( 'Optional', 'bookit-booking-system' ); ?>)</span>
			</label>
			<textarea
				id="special-requests"
				name="special_requests"
				rows="3"
				maxlength="500"
				placeholder="<?php esc_attr_e( 'Any allergies, preferences, or special requirements...', 'bookit-booking-system' ); ?>"
				aria-describedby="special-requests-help"
			><?php echo esc_textarea( $special_requests ); ?></textarea>
			<p id="special-requests-help" class="field-help">
				<span id="char-count">500</span> <?php esc_html_e( 'characters remaining', 'bookit-booking-system' ); ?>
			</p>
		</div>

		<!-- Marketing Consent (GDPR) -->
		<div class="form-group checkbox-group">
			<label class="checkbox-label">
				<input
					type="checkbox"
					id="marketing-consent"
					name="marketing_consent"
					value="1"
					<?php checked( $marketing_consent, 1 ); ?>
				/>
				<span>
					<?php esc_html_e( 'Send me special offers and updates', 'bookit-booking-system' ); ?>
				</span>
			</label>
			<p class="field-help">
				<?php
				printf(
					/* translators: %s: link to Privacy Policy */
					esc_html__( 'You can unsubscribe at any time. See our %s.', 'bookit-booking-system' ),
					'<a href="' . esc_url( home_url( '/privacy-policy' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Privacy Policy', 'bookit-booking-system' ) . '</a>'
				);
				?>
			</p>
		</div>

		<?php if ( $requires_waiver ) : ?>
			<div class="form-group bookit-waiver-group" id="cooling-off-waiver-group">
				<div class="bookit-legal-notice">
					<p class="bookit-legal-notice__heading">
						<?php esc_html_e( 'Important: Right to Cancel', 'bookit-booking-system' ); ?>
					</p>
					<p class="bookit-legal-notice__body">
						<?php esc_html_e( 'Your appointment is scheduled within 14 days. Under the Consumer Contracts Regulations 2013, you normally have a 14-day right to cancel. By checking the box below, you request that we begin the service before this period expires and acknowledge that you will lose this cancellation right once the service has been performed.', 'bookit-booking-system' ); ?>
					</p>
				</div>
				<label class="checkbox-label bookit-checkbox-label bookit-checkbox-label--legal">
					<input
						type="checkbox"
						id="cooling-off-waiver"
						name="cooling_off_waiver"
						value="1"
						<?php checked( $waiver_given, true ); ?>
						aria-required="true"
						aria-describedby="cooling-off-waiver-error"
					/>
					<span class="bookit-checkbox-text">
						<?php esc_html_e( 'I expressly request this service to begin before the 14-day cancellation period expires, and I understand that I will lose my right to cancel once the service has begun.', 'bookit-booking-system' ); ?>
					</span>
				</label>
				<span id="cooling-off-waiver-error" class="error-message" role="alert"></span>
			</div>
		<?php endif; ?>

		<!-- Terms Acceptance -->
		<p class="terms-notice">
			<?php
			printf(
				/* translators: 1: link to Terms, 2: link to Privacy Policy */
				esc_html__( 'By continuing, you agree to our %1$s and %2$s.', 'bookit-booking-system' ),
				'<a href="' . esc_url( home_url( '/terms-conditions' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Terms &amp; Conditions', 'bookit-booking-system' ) . '</a>',
				'<a href="' . esc_url( home_url( '/privacy-policy' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Privacy Policy', 'bookit-booking-system' ) . '</a>'
			);
			?>
		</p>

		<!-- Navigation -->
		<div class="bookit-step-navigation bookit-step-4-nav">
			<button type="button" class="bookit-btn bookit-btn-back bookit-btn-back-step-4" data-step="3" aria-label="<?php esc_attr_e( 'Back to Date/Time', 'bookit-booking-system' ); ?>">
				&larr; <?php esc_html_e( 'Back to Date/Time', 'bookit-booking-system' ); ?>
			</button>
			<button type="submit" class="bookit-btn bookit-btn-continue bookit-btn-continue-contact">
				<?php esc_html_e( 'Continue to Review', 'bookit-booking-system' ); ?> &rarr;
			</button>
		</div>

	</form>
</div>
