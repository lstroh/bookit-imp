<?php
/**
 * Services list page (WordPress admin).
 *
 * @package Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Check user capabilities
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'booking-system' ) );
}
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Services', 'booking-system' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=booking-add-service' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'booking-system' ); ?>
	</a>
	<hr class="wp-header-end">

	<div class="booking-admin-notice notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Sprint 0: Foundation Phase', 'booking-system' ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'Service management will be implemented in Sprint 1.', 'booking-system' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Expected features:', 'booking-system' ); ?>
		</p>
		<ul>
			<li><?php esc_html_e( 'List all services with name, duration, price', 'booking-system' ); ?></li>
			<li><?php esc_html_e( 'Add/Edit/Delete services', 'booking-system' ); ?></li>
			<li><?php esc_html_e( 'Assign services to categories', 'booking-system' ); ?></li>
			<li><?php esc_html_e( 'Set deposit requirements', 'booking-system' ); ?></li>
		</ul>
	</div>
</div>
