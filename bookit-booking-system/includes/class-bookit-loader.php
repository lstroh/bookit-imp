<?php
/**
 * The core plugin class.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 */
class Bookit_Loader {

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
		$this->version     = defined( 'BOOKIT_VERSION' ) ? BOOKIT_VERSION : '1.0.0';
		$this->plugin_name = 'bookit-booking-system';

		$this->load_dependencies();
		$this->define_rewrite_rules();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cron_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		// Logger (load early for use in other classes).
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';

		// Database management.
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-database.php';

		// Dashboard authentication/session.
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-session.php';
		require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-auth.php';

		// Booking wizard session manager.
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';

		// Admin-specific functionality.
		require_once BOOKIT_PLUGIN_DIR . 'admin/class-bookit-admin.php';

		// Public-facing functionality.
		require_once BOOKIT_PLUGIN_DIR . 'public/class-bookit-public.php';

		// Shortcode handler.
		require_once BOOKIT_PLUGIN_DIR . 'public/class-shortcodes.php';

		// REST API endpoints.
		require_once BOOKIT_PLUGIN_DIR . 'includes/api/class-wizard-api.php';
	}

	/**
	 * Register custom rewrite rules for dashboard.
	 *
	 * @return void
	 */
	private function define_rewrite_rules() {
		add_action( 'init', array( $this, 'add_dashboard_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_dashboard_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'dashboard_template_redirect' ) );
	}

	/**
	 * Add dashboard rewrite rules.
	 *
	 * @return void
	 */
	public function add_dashboard_rewrite_rules() {
		// Dashboard login page.
		add_rewrite_rule(
			'^bookit-dashboard/?$',
			'index.php?bookit_dashboard_page=login',
			'top'
		);

		// Dashboard home page.
		add_rewrite_rule(
			'^bookit-dashboard/home/?$',
			'index.php?bookit_dashboard_page=home',
			'top'
		);

		// Dashboard logout.
		add_rewrite_rule(
			'^bookit-dashboard/logout/?$',
			'index.php?bookit_dashboard_page=logout',
			'top'
		);
	}

	/**
	 * Add dashboard query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array Modified query vars.
	 */
	public function add_dashboard_query_vars( $vars ) {
		$vars[] = 'bookit_dashboard_page';
		return $vars;
	}

	/**
	 * Handle dashboard template redirects.
	 *
	 * @return void
	 */
	public function dashboard_template_redirect() {
		$page = get_query_var( 'bookit_dashboard_page', '' );

		if ( empty( $page ) ) {
			return;
		}

		switch ( $page ) {
			case 'login':
				require_once BOOKIT_PLUGIN_DIR . 'dashboard/index.php';
				exit;

			case 'home':
				require_once BOOKIT_PLUGIN_DIR . 'dashboard/dashboard-home.php';
				exit;

			case 'logout':
				require_once BOOKIT_PLUGIN_DIR . 'dashboard/logout.php';
				exit;

			default:
				wp_redirect( home_url( '/bookit-dashboard/' ) );
				exit;
		}
	}

	/**
	 * Register all hooks related to the admin area functionality.
	 *
	 * @return void
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Bookit_Admin( $this->get_plugin_name(), $this->get_version() );

		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );

		// Load admin menu class
		require_once BOOKIT_PLUGIN_DIR . 'admin/class-bookit-admin-menu.php';
		$admin_menu = new Bookit_Admin_Menu();

		// Register admin menu
		add_action( 'admin_menu', array( $admin_menu, 'register_menu' ) );
	}

	/**
	 * Register all hooks related to the public-facing functionality.
	 *
	 * @return void
	 */
	private function define_public_hooks() {
		$plugin_public = new Bookit_Public( $this->get_plugin_name(), $this->get_version() );

		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_scripts' ) );

		// Initialize shortcode handler.
		$shortcodes = new Bookit_Shortcodes();

		// Initialize REST API.
		$wizard_api = new Bookit_Wizard_API();
	}

	/**
	 * Register cron hooks.
	 *
	 * @return void
	 */
	private function define_cron_hooks() {
		// Log cleanup cron
		add_action( 'bookit_cleanup_logs', array( 'Bookit_Logger', 'cleanup_old_logs' ) );
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
