<?php
/**
 * Booking wizard shell template.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Get current step.
$current_step = (int) Bookit_Session_Manager::get( 'current_step', 1 );

// Step labels.
$step_labels = array(
	1 => __( 'Select Service', 'bookit-booking-system' ),
	2 => __( 'Choose Staff', 'bookit-booking-system' ),
	3 => __( 'Pick Date & Time', 'bookit-booking-system' ),
	4 => __( 'Contact Details', 'bookit-booking-system' ),
);
?>

<a href="#main-content" class="bookit-skip-link"><?php esc_html_e( 'Skip to main content', 'bookit-booking-system' ); ?></a>

<div class="bookit-wizard-container">
	<div class="bookit-progress-indicator" role="navigation" aria-label="<?php esc_attr_e( 'Booking progress', 'bookit-booking-system' ); ?>">
		<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
			<?php
			$step_class = 'bookit-progress-step';
			if ( $i < $current_step ) {
				$step_class .= ' bookit-progress-step-completed';
			} elseif ( $i === $current_step ) {
				$step_class .= ' bookit-progress-step-current';
			} else {
				$step_class .= ' bookit-progress-step-upcoming';
			}
			?>
			<div class="<?php echo esc_attr( $step_class ); ?>" data-step="<?php echo esc_attr( $i ); ?>">
				<div class="bookit-progress-step-number" aria-label="<?php echo esc_attr( sprintf( __( 'Step %d', 'bookit-booking-system' ), $i ) ); ?>">
					<?php if ( $i < $current_step ) : ?>
						<span class="bookit-progress-checkmark" aria-hidden="true">✓</span>
					<?php else : ?>
						<span class="bookit-progress-number"><?php echo esc_html( $i ); ?></span>
					<?php endif; ?>
				</div>
				<div class="bookit-progress-step-label"><?php echo esc_html( $step_labels[ $i ] ); ?></div>
			</div>
		<?php endfor; ?>
	</div>

	<main id="main-content" class="bookit-wizard-content" role="main">
		<?php
		// Get step slug for template filename.
		$step_slugs = array(
			1 => 'services',
			2 => 'staff',
			3 => 'datetime',
			4 => 'checkout',
		);
		$step_slug = isset( $step_slugs[ $current_step ] ) ? $step_slugs[ $current_step ] : 'step-' . $current_step;

		// Load step template based on current step.
		$step_template = BOOKIT_PLUGIN_DIR . 'public/templates/booking-step-' . $current_step . '-' . $step_slug . '.php';
		if ( file_exists( $step_template ) ) {
			include $step_template;
		} else {
			echo '<div class="bookit-step bookit-step-' . esc_attr( $current_step ) . '">';
			echo '<h2>' . esc_html( sprintf( __( 'Step %d: %s', 'bookit-booking-system' ), $current_step, $step_labels[ $current_step ] ) ) . '</h2>';
			echo '<p>' . esc_html__( 'Content will be built in Task ' . $current_step, 'bookit-booking-system' ) . '</p>';
			echo '</div>';
		}
		?>
	</main>

	<nav class="bookit-wizard-nav" aria-label="<?php esc_attr_e( 'Booking navigation', 'bookit-booking-system' ); ?>">
		<?php if ( $current_step > 1 ) : ?>
			<button type="button" class="bookit-btn bookit-btn-back" id="bookit-back-btn" aria-label="<?php esc_attr_e( 'Go to previous step', 'bookit-booking-system' ); ?>">
				← <?php esc_html_e( 'Back', 'bookit-booking-system' ); ?>
			</button>
		<?php endif; ?>
		<button type="button" class="bookit-btn bookit-btn-next" id="bookit-next-btn" aria-label="<?php esc_attr_e( 'Go to next step', 'bookit-booking-system' ); ?>">
			<?php esc_html_e( 'Next', 'bookit-booking-system' ); ?> →
		</button>
	</nav>
</div>
