<?php
/**
 * Settings page (WordPress admin).
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
	<h1><?php esc_html_e( 'Booking System Settings', 'booking-system' ); ?></h1>
	<hr class="wp-header-end">

	<div class="booking-admin-notice notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Sprint 0: Foundation Phase', 'booking-system' ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'Settings pages will be implemented across Sprints 1-3.', 'booking-system' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Expected settings tabs:', 'booking-system' ); ?>
		</p>
		<ul>
			<li><strong><?php esc_html_e( 'General:', 'booking-system' ); ?></strong> <?php esc_html_e( 'Business name, timezone, date/time formats, booking rules', 'booking-system' ); ?></li>
			<li><strong><?php esc_html_e( 'Payment:', 'booking-system' ); ?></strong> <?php esc_html_e( 'Stripe/PayPal API keys, deposit settings, refund policy', 'booking-system' ); ?></li>
			<li><strong><?php esc_html_e( 'Email:', 'booking-system' ); ?></strong> <?php esc_html_e( 'SMTP configuration, email templates, notification settings', 'booking-system' ); ?></li>
			<li><strong><?php esc_html_e( 'Calendar:', 'booking-system' ); ?></strong> <?php esc_html_e( 'Google Calendar sync, working hours, holidays', 'booking-system' ); ?></li>
		</ul>
	</div>

	<h2><?php esc_html_e( 'Current Settings (from activation):', 'booking-system' ); ?></h2>
	<?php
	$settings = get_option( 'booking_system_settings', array() );
	if ( ! empty( $settings ) ) {
		echo '<pre>';
		print_r( $settings );
		echo '</pre>';
	} else {
		echo '<p>' . esc_html__( 'No settings found.', 'booking-system' ) . '</p>';
	}
	?>
</div>
