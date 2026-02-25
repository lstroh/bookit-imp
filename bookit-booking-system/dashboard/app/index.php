<?php
/**
 * Dashboard Vue App Entry Point.
 *
 * This file checks authentication and serves the Vue 3 SPA.
 *
 * @package Bookit_Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-session.php';
require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-auth.php';

// Require authentication.
Bookit_Auth::require_auth();

// Get current staff.
$current_staff = Bookit_Auth::get_current_staff();

if ( ! $current_staff ) {
	wp_redirect( home_url( '/bookit-dashboard/' ) );
	exit;
}

// Get WordPress REST API nonce.
$rest_nonce = wp_create_nonce( 'wp_rest' );

// Enqueue WordPress media library for photo uploads.
wp_enqueue_media();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Bookit Dashboard</title>

	<?php if ( file_exists( BOOKIT_PLUGIN_DIR . 'dashboard/dist/style.css' ) ) : ?>
		<link rel="stylesheet" href="<?php echo esc_url( BOOKIT_PLUGIN_URL . 'dashboard/dist/style.css' ); ?>">
	<?php endif; ?>

	<?php wp_print_styles(); ?>
</head>
<body>
	<div id="app"></div>

	<!-- Inject session data for Vue -->
	<script>
		window.BOOKIT_DASHBOARD = {
			staff: <?php echo wp_json_encode( $current_staff ); ?>,
			apiBase: '<?php echo esc_js( rest_url( 'bookit/v1/dashboard' ) ); ?>',
			restBase: '<?php echo esc_js( rest_url( 'bookit/v1/' ) ); ?>',
			nonce: '<?php echo esc_js( $rest_nonce ); ?>',
			pluginUrl: '<?php echo esc_url( BOOKIT_PLUGIN_URL ); ?>',
			logoutUrl: '<?php echo esc_url( home_url( '/bookit-dashboard/logout/' ) ); ?>'
		};
	</script>

	<?php
	// Print WordPress media library scripts and templates.
	wp_print_scripts();
	wp_print_media_templates();
	?>

	<?php if ( file_exists( BOOKIT_PLUGIN_DIR . 'dashboard/dist/index.js' ) ) : ?>
		<script type="module" src="<?php echo esc_url( BOOKIT_PLUGIN_URL . 'dashboard/dist/index.js' ); ?>"></script>
	<?php else : ?>
		<script type="module" src="http://localhost:5173/@vite/client"></script>
		<script type="module" src="http://localhost:5173/src/main.js"></script>
	<?php endif; ?>
</body>
</html>
