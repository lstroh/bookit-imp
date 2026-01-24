<?php
/**
 * Database setup and management.
 *
 * @package    Booking_System
 * @subpackage Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Database setup and management class.
 */
class Booking_Database {

	/**
	 * Current database version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0';

	/**
	 * Log a debug message (only when WP_DEBUG_LOG is enabled).
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	private static function log_debug( $message ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Booking System][DB] ' . $message );
		}
	}

	/**
	 * Create all database tables.
	 *
	 * Uses dbDelta() function for safe table creation/updates.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_prefix    = $wpdb->prefix;

		// Get current database version.
		$installed_version = get_option( 'booking_system_db_version', '0' );

		self::log_debug( 'create_tables() start. Installed version: ' . $installed_version . '. Target version: ' . self::DB_VERSION . '.' );

		// Only create tables if not already at current version.
		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			self::log_debug( 'Version upgrade needed. Loading upgrade functions.' );

			// Load WordPress upgrade functions.
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Create tables.
			self::log_debug( 'Creating table: bookings_services.' );
			self::create_services_table( $table_prefix, $charset_collate );
			self::log_debug( 'Creating table: bookings_categories.' );
			self::create_categories_table( $table_prefix, $charset_collate );
			self::log_debug( 'Creating table: bookings_service_categories.' );
			self::create_service_categories_table( $table_prefix, $charset_collate );
			self::log_debug( 'Creating table: bookings_staff.' );
			self::create_staff_table( $table_prefix, $charset_collate );
			self::log_debug( 'Creating table: bookings_staff_services.' );
			self::create_staff_services_table( $table_prefix, $charset_collate );

			// Update database version.
			self::log_debug( 'Updating booking_system_db_version option to ' . self::DB_VERSION . '.' );
			update_option( 'booking_system_db_version', self::DB_VERSION );

			self::log_debug( 'create_tables() complete.' );
		} else {
			self::log_debug( 'No upgrade needed. Skipping table creation.' );
		}
	}

	/**
	 * Create wp_bookings_services table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_services_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_services';

		// NOTE: dbDelta() is picky about index formatting/spaces.
		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			description TEXT NULL,
			duration INT UNSIGNED NOT NULL COMMENT 'Duration in minutes',
			price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			deposit_amount DECIMAL(10,2) NULL DEFAULT NULL COMMENT 'Optional deposit amount',
			deposit_type ENUM('fixed','percentage') DEFAULT 'fixed',
			buffer_before INT UNSIGNED DEFAULT 0 COMMENT 'Buffer time before appointment (minutes)',
			buffer_after INT UNSIGNED DEFAULT 0 COMMENT 'Buffer time after appointment (minutes)',
			is_active TINYINT(1) DEFAULT 1,
			display_order INT DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
			PRIMARY KEY  (id),
			KEY idx_is_active  (is_active),
			KEY idx_deleted_at  (deleted_at),
			KEY idx_display_order  (display_order)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_categories table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_categories_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_categories';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			description TEXT NULL,
			display_order INT DEFAULT 0,
			is_active TINYINT(1) DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_is_active  (is_active),
			KEY idx_deleted_at  (deleted_at),
			KEY idx_display_order  (display_order)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_service_categories table (junction table).
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_service_categories_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_service_categories';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			service_id BIGINT UNSIGNED NOT NULL,
			category_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_service_category  (service_id, category_id),
			KEY idx_service_id  (service_id),
			KEY idx_category_id  (category_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_staff table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_staff_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_staff';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(255) NOT NULL,
			password_hash VARCHAR(255) NOT NULL,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NOT NULL,
			phone VARCHAR(20) NULL,
			role ENUM('staff','admin') DEFAULT 'staff',
			google_calendar_id VARCHAR(255) NULL COMMENT 'For calendar sync',
			is_active TINYINT(1) DEFAULT 1,
			display_order INT DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_email  (email),
			KEY idx_role  (role),
			KEY idx_is_active  (is_active),
			KEY idx_deleted_at  (deleted_at)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_staff_services table (junction table).
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 * @return void
	 */
	private static function create_staff_services_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_staff_services';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			staff_id BIGINT UNSIGNED NOT NULL,
			service_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_staff_service  (staff_id, service_id),
			KEY idx_staff_id  (staff_id),
			KEY idx_service_id  (service_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Drop all plugin tables.
	 *
	 * WARNING: This deletes all data. Only call from uninstall.php.
	 *
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;

		$table_prefix = $wpdb->prefix;

		$tables = array(
			$table_prefix . 'bookings_staff_services',
			$table_prefix . 'bookings_service_categories',
			$table_prefix . 'bookings_staff',
			$table_prefix . 'bookings_categories',
			$table_prefix . 'bookings_services',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}
}

