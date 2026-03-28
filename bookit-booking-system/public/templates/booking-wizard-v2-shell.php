<?php
/**
 * Booking Wizard V2 shell template.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$current_step = (int) Bookit_Session_Manager::get( 'current_step', 1 );
?>
<div class="bookit-v2-wizard-container" data-step="<?php echo esc_attr( $current_step ); ?>">

	<?php Bookit_Template_Loader::get_template( 'partials/booking-wizard-v2-progress.php', array( 'current_step' => $current_step ) ); ?>

	<?php if ( 1 === $current_step ) : ?>
		<?php Bookit_Template_Loader::get_template( 'booking-wizard-v2-step-1.php' ); ?>
	<?php elseif ( 2 === $current_step ) : ?>
		<?php Bookit_Template_Loader::get_template( 'booking-wizard-v2-step-2.php' ); ?>
	<?php elseif ( 3 === $current_step ) : ?>
		<?php Bookit_Template_Loader::get_template( 'booking-wizard-v2-step-3.php' ); ?>
	<?php elseif ( 4 === $current_step ) : ?>
		<?php Bookit_Template_Loader::get_template( 'booking-wizard-v2-step-4.php' ); ?>
	<?php elseif ( 5 === $current_step ) : ?>
		<?php Bookit_Template_Loader::get_template( 'booking-wizard-v2-step-5.php' ); ?>
	<?php endif; ?>

</div>
