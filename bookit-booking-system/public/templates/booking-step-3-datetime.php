<?php
/**
 * Step 3: Date & Time Selection Template
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
$session   = Bookit_Session_Manager::get_data();
$service_id = isset( $session['service_id'] ) ? absint( $session['service_id'] ) : 0;
$staff_id   = isset( $session['staff_id'] ) ? absint( $session['staff_id'] ) : 0;

if ( ! $service_id ) {
	echo '<p>' . esc_html__( 'Please select a service first.', 'bookit-booking-system' ) . '</p>';
	return;
}
?>

<div class="bookit-step bookit-step-3">
	<h2><?php esc_html_e( 'Choose Your Date & Time', 'bookit-booking-system' ); ?></h2>

	<!-- Calendar Component -->
	<div class="bookit-calendar-container">
		<div class="calendar-header">
			<button type="button" class="btn-prev-month" aria-label="<?php esc_attr_e( 'Previous month', 'bookit-booking-system' ); ?>">&larr;</button>
			<h3 class="current-month-year" aria-live="polite"><?php echo esc_html( date_i18n( 'F Y', strtotime( 'first day of this month' ) ) ); ?></h3>
			<button type="button" class="btn-next-month" aria-label="<?php esc_attr_e( 'Next month', 'bookit-booking-system' ); ?>">&rarr;</button>
		</div>

		<div class="calendar-grid" role="grid" aria-label="<?php esc_attr_e( 'Calendar', 'bookit-booking-system' ); ?>">
			<!-- Weekday headers: Mo Tu We Th Fr Sa Su (JS will populate) -->
			<div class="calendar-weekdays" role="row"></div>
			<div class="calendar-days" role="rowgroup"></div>
		</div>

		<div class="selected-date-display" aria-live="polite">
			<!-- Shows: "Selected: Thursday, 15 May 2026" -->
		</div>
	</div>

	<!-- Time Slots -->
	<div class="bookit-timeslots-container" style="display: none;">
		<h3><?php esc_html_e( 'Available Times for', 'bookit-booking-system' ); ?> <span class="selected-date-text"></span></h3>

		<div class="timeslots-loading" aria-live="polite" style="display: none;">
			<span><?php esc_html_e( 'Loading available times...', 'bookit-booking-system' ); ?></span>
		</div>

		<div class="timeslots-content" style="display: none;">
			<!-- Morning Slots -->
			<div class="timeslot-period" data-period="morning">
				<h4><?php esc_html_e( 'Morning', 'bookit-booking-system' ); ?></h4>
				<div class="timeslot-grid" role="group"></div>
			</div>

			<!-- Afternoon Slots -->
			<div class="timeslot-period" data-period="afternoon">
				<h4><?php esc_html_e( 'Afternoon', 'bookit-booking-system' ); ?></h4>
				<div class="timeslot-grid" role="group"></div>
			</div>

			<!-- Evening Slots -->
			<div class="timeslot-period" data-period="evening">
				<h4><?php esc_html_e( 'Evening', 'bookit-booking-system' ); ?></h4>
				<div class="timeslot-grid" role="group"></div>
			</div>
		</div>

		<div class="timeslots-error" style="display: none;" role="alert"></div>
	</div>

	<!-- Navigation -->
	<div class="bookit-step-navigation bookit-step-3-nav">
		<button type="button" class="bookit-btn bookit-btn-back bookit-btn-back-step-3" data-step="2" aria-label="<?php esc_attr_e( 'Back to Staff', 'bookit-booking-system' ); ?>">&larr; <?php esc_html_e( 'Back to Staff', 'bookit-booking-system' ); ?></button>
		<button type="button" class="bookit-btn bookit-btn-continue-datetime" disabled data-step="4" aria-label="<?php esc_attr_e( 'Continue to Contact Details', 'bookit-booking-system' ); ?>"><?php esc_html_e( 'Continue to Contact Details', 'bookit-booking-system' ); ?> &rarr;</button>
	</div>
</div>
