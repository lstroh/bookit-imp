<?php
/**
 * Booking Wizard V2 progress bar partial.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 *
 * @var int $current_step Current wizard step (1–5).
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$step_labels = array(
	1 => __( 'Service',      'bookit-booking-system' ),
	2 => __( 'Staff',        'bookit-booking-system' ),
	3 => __( 'Date & Time',  'bookit-booking-system' ),
	4 => __( 'Your Details', 'bookit-booking-system' ),
	5 => __( 'Payment',      'bookit-booking-system' ),
);
?>
<nav class="bookit-v2-progress" aria-label="<?php esc_attr_e( 'Booking progress', 'bookit-booking-system' ); ?>">
	<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
		<?php
		if ( $i < $current_step ) {
			$item_class = 'bookit-v2-step-item bookit-v2-step-item--done';
		} elseif ( $i === $current_step ) {
			$item_class = 'bookit-v2-step-item bookit-v2-step-item--active';
		} else {
			$item_class = 'bookit-v2-step-item bookit-v2-step-item--inactive';
		}
		?>
		<span class="<?php echo esc_attr( $item_class ); ?>">
			<span class="bookit-v2-step-label"><?php echo esc_html( $step_labels[ $i ] ); ?></span>
		</span>
	<?php endfor; ?>
</nav>
