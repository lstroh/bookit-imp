<?php
/**
 * PHPUnit bootstrap file for wp-env.
 *
 * @package Bookit_Booking_System
 */

// Composer autoloader
$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
} else {
	echo "Error: Run 'composer install' first\n";
	exit( 1 );
}

// Load Yoast PHPUnit Polyfills
if ( ! class_exists( 'Yoast\PHPUnitPolyfills\Autoload' ) ) {
	$polyfills = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
	if ( file_exists( $polyfills ) ) {
		require_once $polyfills;
	}
}

// WordPress test library directory
$_tests_dir = getenv( 'WP_TESTS_DIR' );

// If not set, use wp-env default location
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Fallback for wp-env (WordPress installed in container)
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// Try wp-env location
	$_tests_dir = '/wordpress-phpunit';
}

// Final fallback - load WordPress directly
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// Load WordPress from wp-env installation
	$wp_core_dir = getenv( 'WP_CORE_DIR' );
	if ( ! $wp_core_dir ) {
		$wp_core_dir = '/var/www/html';
	}

	if ( file_exists( $wp_core_dir . '/wp-load.php' ) ) {
		define( 'WP_USE_THEMES', false );
		require_once $wp_core_dir . '/wp-load.php';

		// Manually load the plugin
		require_once dirname( __DIR__ ) . '/bookit-booking-system.php';

		// Activate plugin programmatically
		if ( ! function_exists( 'bookit_activate' ) ) {
			die( "Error: Plugin not loaded correctly\n" );
		}

		// Ensure tables exist
		global $wpdb;
		$table_name = $wpdb->prefix . 'bookings_services';
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( $table_exists !== $table_name ) {
			bookit_activate();
		}

		// Stop here - WordPress loaded directly
		return;
	}

	die( "Error: WordPress test library not found. Make sure wp-env is running.\n" );
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/bookit-booking-system.php';
}

// Load plugin before running tests
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Ensure plugin is activated
activate_plugin( 'bookit-booking-system/bookit-booking-system.php' );
