<?php
/**
 * TEMPORARY: Migration Runner
 * Run this once, then DELETE this file
 * 
 * Usage: Visit in browser: http://your-site.local/wp-content/plugins/bookit-booking-system/run-migration.php
 * 
 * @package    Bookit_Booking_System
 */

// Load WordPress.
require_once '../../../wp-load.php';

// Security check.
if ( ! current_user_can( 'manage_options' ) ) {
	die( '❌ Unauthorized. You must be an administrator.' );
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Bookit Database Migration</title>
	<style>
		body {
			font-family: monospace;
			padding: 20px;
			background: #f5f5f5;
			max-width: 1200px;
			margin: 0 auto;
		}
		h1 {
			color: #333;
		}
		h2 {
			margin-top: 30px;
		}
		.success {
			color: green;
		}
		.error {
			color: red;
		}
		.warning {
			color: orange;
			font-weight: bold;
		}
		pre {
			background: #fff;
			padding: 15px;
			border-radius: 5px;
			overflow-x: auto;
		}
		ul {
			line-height: 1.8;
		}
	</style>
</head>
<body>
	<h1>Bookit Database Migration</h1>
	<?php

	// Load migration class.
	require_once __DIR__ . '/database/migrations/migration-add-staff-fields.php';

	// Run migration.
	$migration = new Bookit_Migration_Add_Staff_Fields();
	$success   = $migration->up();

	if ( $success ) {
		echo '<h2 class="success">✅ Migration Completed Successfully!</h2>';
		echo '<p>The following columns have been added:</p>';
		echo '<ul>';
		echo '<li>wp_bookings_staff.photo_url</li>';
		echo '<li>wp_bookings_staff.bio</li>';
		echo '<li>wp_bookings_staff.title</li>';
		echo '<li>wp_bookings_staff_services.custom_price</li>';
		echo '</ul>';
	} else {
		echo '<h2 class="error">❌ Migration Completed with Errors</h2>';
		echo '<p>Check your error log for details: wp-content/debug.log</p>';
	}

	echo '<hr>';
	echo '<p class="warning">⚠️  DELETE THIS FILE (run-migration.php) NOW FOR SECURITY!</p>';

	// Show updated table structures.
	global $wpdb;
	echo '<h3>Updated Table Structure: wp_bookings_staff</h3>';
	echo '<pre>';
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$staff_columns = $wpdb->get_results( "DESCRIBE {$wpdb->prefix}bookings_staff" );
	foreach ( $staff_columns as $column ) {
		echo sprintf(
			"%-20s %-30s %-5s %-5s %-10s %-10s\n",
			$column->Field,
			$column->Type,
			$column->Null,
			$column->Key,
			$column->Default ?? 'NULL',
			$column->Extra ?? ''
		);
	}
	echo '</pre>';

	echo '<h3>Updated Table Structure: wp_bookings_staff_services</h3>';
	echo '<pre>';
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$services_columns = $wpdb->get_results( "DESCRIBE {$wpdb->prefix}bookings_staff_services" );
	foreach ( $services_columns as $column ) {
		echo sprintf(
			"%-20s %-30s %-5s %-5s %-10s %-10s\n",
			$column->Field,
			$column->Type,
			$column->Null,
			$column->Key,
			$column->Default ?? 'NULL',
			$column->Extra ?? ''
		);
	}
	echo '</pre>';

	?>
</body>
</html>
