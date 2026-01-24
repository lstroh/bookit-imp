<?php
/**
 * The core plugin class.
 *
 * @package    Booking_System
 * @subpackage Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 */
class Booking_Loader {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->version     = defined( 'BOOKING_SYSTEM_VERSION' ) ? BOOKING_SYSTEM_VERSION : '1.0.0';
		$this->plugin_name = 'booking-system';

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		// Database management.
		require_once BOOKING_SYSTEM_PATH . 'includes/class-booking-database.php';

		// Admin-specific functionality.
		require_once BOOKING_SYSTEM_PATH . 'admin/class-booking-admin.php';

		// Public-facing functionality.
		require_once BOOKING_SYSTEM_PATH . 'public/class-booking-public.php';
	}

	/**
	 * Register all hooks related to the admin area functionality.
	 *
	 * @return void
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Booking_Admin( $this->get_plugin_name(), $this->get_version() );

		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );

		// Admin menu will be added in Task 5.
	}

	/**
	 * Register all hooks related to the public-facing functionality.
	 *
	 * @return void
	 */
	private function define_public_hooks() {
		$plugin_public = new Booking_Public( $this->get_plugin_name(), $this->get_version() );

		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_scripts' ) );

		// Public hooks will be added in later sprints.
	}

	/**
	 * Run the loader to execute all hooks with WordPress.
	 *
	 * @return void
	 */
	public function run() {
		// Hooks are registered during construction.
	}

	/**
	 * The name of the plugin.
	 *
	 * @return string The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @return string The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
