<?php
/**
 * Plugin Name:       Booking System
 * Plugin URI:        https://example.com/booking-system
 * Description:       Professional appointment booking system for UK service businesses
 * Version:           1.0.0
 * Author:            Liron
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       booking-system
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 *
 * @package Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'BOOKING_SYSTEM_VERSION', '1.0.0' );

/**
 * Absolute path to the main plugin file.
 */
define( 'BOOKING_SYSTEM_FILE', __FILE__ );

/**
 * Plugin directory path.
 */
define( 'BOOKING_SYSTEM_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'BOOKING_SYSTEM_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 *
 * @return void
 */
function booking_activate_system() {
	require_once BOOKING_SYSTEM_PATH . 'includes/class-booking-activator.php';
	Booking_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 *
 * @return void
 */
function booking_deactivate_system() {
	require_once BOOKING_SYSTEM_PATH . 'includes/class-booking-deactivator.php';
	Booking_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'booking_activate_system' );
register_deactivation_hook( __FILE__, 'booking_deactivate_system' );

/**
 * The core plugin class.
 */
require BOOKING_SYSTEM_PATH . 'includes/class-booking-loader.php';

/**
 * Begins execution of the plugin.
 *
 * @return void
 */
function booking_run_system() {
	$plugin = new Booking_Loader();
	$plugin->run();
}

booking_run_system();
