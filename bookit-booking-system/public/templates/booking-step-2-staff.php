<?php
/**
 * Booking Wizard - Step 2: Staff Selection
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Get service from session.
require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
Bookit_Session_Manager::init();
$wizard_data = Bookit_Session_Manager::get_data();

if ( empty( $wizard_data['service_id'] ) ) {
	?>
	<div class="bookit-step bookit-step-2-staff">
		<p><?php esc_html_e( 'Please select a service first.', 'bookit-booking-system' ); ?></p>
	</div>
	<?php
	return;
}

$service_id   = absint( $wizard_data['service_id'] );
$service_name = isset( $wizard_data['service_name'] ) ? $wizard_data['service_name'] : '';

// Get staff for this service.
require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-staff-model.php';
$staff_model   = new Bookit_Staff_Model();
$staff_members = $staff_model->get_staff_for_service( $service_id );
$lowest_price  = $staff_model->get_lowest_staff_price_for_service( $service_id );

if ( empty( $staff_members ) ) {
	?>
	<div class="bookit-step bookit-step-2-staff">
		<div class="bookit-no-staff">
			<h2><?php esc_html_e( 'No Staff Available', 'bookit-booking-system' ); ?></h2>
			<p><?php esc_html_e( 'All staff members are currently unavailable for this service.', 'bookit-booking-system' ); ?></p>
		</div>
	</div>
	<?php
	return;
}
?>

<div class="bookit-step bookit-step-2-staff">
	<h2><?php esc_html_e( 'Select Staff Member', 'bookit-booking-system' ); ?></h2>
	<p class="bookit-step-intro">
		<?php
		/* translators: %s: Service name */
		echo esc_html( sprintf( __( 'Who would you like for your %s?', 'bookit-booking-system' ), $service_name ) );
		?>
	</p>
	
	<div class="bookit-staff-grid">
		<?php foreach ( $staff_members as $staff ) : ?>
			<div class="bookit-staff-card" data-staff-id="<?php echo esc_attr( $staff['id'] ); ?>">
				<div class="bookit-staff-card-content">
					<!-- Photo or Initials -->
					<div class="bookit-staff-photo">
						<?php if ( ! empty( $staff['photo_url'] ) ) : ?>
							<img 
								src="<?php echo esc_url( $staff['photo_url'] ); ?>" 
								alt="<?php echo esc_attr( sprintf( __( 'Photo of %s', 'bookit-booking-system' ), $staff['full_name'] ) ); ?>"
								loading="lazy"
							/>
						<?php else : ?>
							<?php
							// Generate initials and color.
							$initials = strtoupper( substr( $staff['first_name'], 0, 1 ) . substr( $staff['last_name'], 0, 1 ) );
							$colors   = array( '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899' );
							$hash     = 0;
							for ( $i = 0; $i < strlen( $staff['full_name'] ); $i++ ) {
								$hash = ord( $staff['full_name'][ $i ] ) + ( ( $hash << 5 ) - $hash );
							}
							$color = $colors[ abs( $hash ) % count( $colors ) ];
							?>
							<div class="bookit-staff-initials" style="background-color: <?php echo esc_attr( $color ); ?>">
								<?php echo esc_html( $initials ); ?>
							</div>
						<?php endif; ?>
					</div>
					
					<!-- Staff Info -->
					<div class="bookit-staff-info">
						<h3 class="bookit-staff-name"><?php echo esc_html( $staff['full_name'] ); ?></h3>
						
						<?php if ( ! empty( $staff['title'] ) ) : ?>
							<p class="bookit-staff-title"><?php echo esc_html( $staff['title'] ); ?></p>
						<?php endif; ?>
						
						<p class="bookit-staff-price">
							<?php
							/* translators: %s: Price amount */
							echo esc_html( sprintf( __( '£%s', 'bookit-booking-system' ), number_format( $staff['price'], 2 ) ) );
							?>
						</p>
						
						<?php if ( ! empty( $staff['bio'] ) ) : ?>
							<p class="bookit-staff-bio"><?php echo esc_html( $staff['bio'] ); ?></p>
						<?php endif; ?>
					</div>
					
					<!-- Select Button -->
					<button 
						type="button" 
						class="bookit-btn-select-staff" 
						data-staff-id="<?php echo esc_attr( $staff['id'] ); ?>"
						data-staff-name="<?php echo esc_attr( $staff['full_name'] ); ?>"
						data-staff-price="<?php echo esc_attr( $staff['price'] ); ?>"
					>
						<?php
						/* translators: %s: Staff first name */
						echo esc_html( sprintf( __( 'Select %s', 'bookit-booking-system' ), $staff['first_name'] ) );
						?>
						 →
					</button>
				</div>
			</div>
		<?php endforeach; ?>
		
		<!-- "No Preference" Card -->
		<div class="bookit-staff-card bookit-staff-card-no-preference" data-staff-id="0">
			<div class="bookit-staff-card-content">
				<div class="bookit-staff-photo">
					<div class="bookit-staff-icon">
						<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
					</div>
				</div>
				
				<div class="bookit-staff-info">
					<h3 class="bookit-staff-name"><?php esc_html_e( 'No Preference', 'bookit-booking-system' ); ?></h3>
					<p class="bookit-staff-title"><?php esc_html_e( 'First Available', 'bookit-booking-system' ); ?></p>
					<p class="bookit-staff-price">
						<?php
						/* translators: %s: Lowest price */
						echo esc_html( sprintf( __( 'from £%s', 'bookit-booking-system' ), number_format( $lowest_price, 2 ) ) );
						?>
					</p>
					<p class="bookit-staff-bio">
						<?php esc_html_e( 'We\'ll assign the first available staff member.', 'bookit-booking-system' ); ?>
					</p>
				</div>
				
				<button 
					type="button" 
					class="bookit-btn-select-staff" 
					data-staff-id="0"
					data-staff-name="No Preference"
					data-staff-price="<?php echo esc_attr( $lowest_price ); ?>"
				>
					<?php esc_html_e( 'Select Anyone', 'bookit-booking-system' ); ?> →
				</button>
			</div>
		</div>
	</div>
</div>
