<?php
/**
 * Booking Wizard - Step 1: Service Selection
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load service model.
require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-service-model.php';

// Get services organized by category.
$service_model = new Bookit_Service_Model();
$services_by_category = $service_model->get_active_services_by_category();
?>

<div class="bookit-step bookit-step-1 bookit-step-1-services">
<?php
// Check if any services exist.
if ( empty( $services_by_category ) ) {
	// Show "no services available" message.
	?>
	<div class="bookit-no-services">
		<h2><?php esc_html_e( 'No Services Available', 'bookit-booking-system' ); ?></h2>
		<p><?php esc_html_e( 'We\'re currently not taking new bookings. Please check back soon!', 'bookit-booking-system' ); ?></p>
	</div>
	<?php
} else {
	?>
	<h2><?php esc_html_e( 'Select a Service', 'bookit-booking-system' ); ?></h2>
	<p class="bookit-step-intro"><?php esc_html_e( 'Choose the service you\'d like to book', 'bookit-booking-system' ); ?></p>
	
	<?php foreach ( $services_by_category as $category_name => $services ) : ?>
		<div class="bookit-category-section">
			<h3 class="bookit-category-title"><?php echo esc_html( $category_name ); ?></h3>
			
			<div class="bookit-services-grid">
				<?php foreach ( $services as $service ) : ?>
					<div class="bookit-service-card" data-service-id="<?php echo esc_attr( $service['id'] ); ?>">
						<div class="bookit-service-card-content">
							<h4 class="bookit-service-name"><?php echo esc_html( $service['name'] ); ?></h4>
							
							<div class="bookit-service-meta">
								<span class="bookit-service-duration">
									<span class="dashicons dashicons-clock" aria-hidden="true"></span>
									<?php echo esc_html( $service['duration'] ); ?> <?php esc_html_e( 'min', 'bookit-booking-system' ); ?>
								</span>
								
								<span class="bookit-service-price">
									<?php if ( $service['has_variable_pricing'] ) : ?>
										<?php
										/* translators: %s: minimum price */
										echo esc_html( sprintf( __( 'from £%s', 'bookit-booking-system' ), number_format( $service['min_staff_price'], 2 ) ) );
										?>
									<?php else : ?>
										<?php
										/* translators: %s: price */
										echo esc_html( sprintf( __( '£%s', 'bookit-booking-system' ), number_format( $service['base_price'], 2 ) ) );
										?>
									<?php endif; ?>
								</span>
							</div>
							
							<?php if ( ! empty( $service['description'] ) ) : ?>
								<p class="bookit-service-description"><?php echo esc_html( $service['description'] ); ?></p>
							<?php endif; ?>
							
							<?php if ( count( $service['categories'] ) > 1 ) : ?>
								<p class="bookit-service-multi-category">
									<?php esc_html_e( 'Also in:', 'bookit-booking-system' ); ?>
									<?php
									$other_categories = array_filter(
										$service['categories'],
										function( $cat ) use ( $category_name ) {
											return $cat !== $category_name;
										}
									);
									echo esc_html( implode( ', ', $other_categories ) );
									?>
								</p>
							<?php endif; ?>
							
							<button 
								type="button" 
								class="bookit-btn-select-service" 
								data-service-id="<?php echo esc_attr( $service['id'] ); ?>"
								data-service-name="<?php echo esc_attr( $service['name'] ); ?>"
								data-service-duration="<?php echo esc_attr( $service['duration'] ); ?>"
								data-service-price="<?php echo esc_attr( $service['base_price'] ); ?>"
								aria-label="<?php echo esc_attr( sprintf( __( 'Book %s', 'bookit-booking-system' ), $service['name'] ) ); ?>"
							>
								<?php esc_html_e( 'Book Now', 'bookit-booking-system' ); ?> →
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
	<?php
}
?>
</div>
