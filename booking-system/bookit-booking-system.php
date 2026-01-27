<?php
/**
 * Plugin Name:       Bookit Booking System
 * Plugin URI:        https://example.com/bookit-booking-system
 * Description:       Professional appointment booking system for UK service businesses
 * Version:           1.0.0
 * Author:            Liron
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bookit-booking-system
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 *
 * @package Bookit_Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'BOOKIT_VERSION', '1.0.0' );

/**
 * Absolute path to the main plugin file.
 */
define( 'BOOKIT_PLUGIN_FILE', __FILE__ );

/**
 * Plugin directory path.
 */
define( 'BOOKIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'BOOKIT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'BOOKIT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 *
 * @return void
 */
function bookit_activate() {
	require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-activator.php';
	Bookit_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 *
 * @return void
 */
function bookit_deactivate() {
	require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-deactivator.php';
	Bookit_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'bookit_activate' );
register_deactivation_hook( __FILE__, 'bookit_deactivate' );

/**
 * The core plugin class.
 */
require BOOKIT_PLUGIN_DIR . 'includes/class-bookit-loader.php';

/**
 * Begins execution of the plugin.
 *
 * @return void
 */
function bookit_run() {
	$plugin = new Bookit_Loader();
	$plugin->run();
}

bookit_run();
