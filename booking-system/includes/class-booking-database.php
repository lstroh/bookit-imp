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

		Booking_Logger::info(
			'Database tables creation started',
			array(
				'db_version' => self::DB_VERSION,
			)
		);

		// Only create tables if not already at current version.
		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			// Load WordPress upgrade functions.
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Create tables.
			self::create_services_table( $table_prefix, $charset_collate );
			self::create_categories_table( $table_prefix, $charset_collate );
			self::create_service_categories_table( $table_prefix, $charset_collate );
			self::create_staff_table( $table_prefix, $charset_collate );
			self::create_staff_services_table( $table_prefix, $charset_collate );

			// Part 2: Tables 6-10
			self::create_customers_table( $table_prefix, $charset_collate );
			self::create_bookings_table( $table_prefix, $charset_collate );
			self::create_payments_table( $table_prefix, $charset_collate );
			self::create_working_hours_table( $table_prefix, $charset_collate );
			self::create_settings_table( $table_prefix, $charset_collate );

			// Update database version.
			update_option( 'booking_system_db_version', self::DB_VERSION );

			Booking_Logger::info(
				'Database tables created successfully',
				array(
					'tables_created' => 10,
				)
			);
		} else {
			Booking_Logger::info(
				'Database already at current version, skipping table creation',
				array(
					'current_version' => $installed_version,
				)
			);
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
	 * Create wp_bookings_customers table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 */
	private static function create_customers_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_customers';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(255) NOT NULL,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NOT NULL,
			phone VARCHAR(20) NOT NULL,
			marketing_consent TINYINT(1) DEFAULT 0 COMMENT 'GDPR marketing consent',
			marketing_consent_date DATETIME NULL COMMENT 'When consent was given',
			notes TEXT NULL COMMENT 'Internal staff notes about customer',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_email (email),
			KEY idx_deleted_at (deleted_at),
			KEY idx_phone (phone)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings table (MAIN BOOKINGS TABLE).
	 *
	 * CRITICAL: Includes UNIQUE constraint on (staff_id, booking_date, start_time)
	 * to prevent double-booking at database level (Gap #1 resolution).
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 */
	private static function create_bookings_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_id BIGINT UNSIGNED NOT NULL,
			service_id BIGINT UNSIGNED NOT NULL,
			staff_id BIGINT UNSIGNED NOT NULL,
			booking_date DATE NOT NULL,
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			duration INT UNSIGNED NOT NULL COMMENT 'Duration in minutes (cached from service)',
			status ENUM('pending','confirmed','cancelled','completed','no_show') DEFAULT 'pending',
			total_price DECIMAL(10,2) NOT NULL,
			deposit_amount DECIMAL(10,2) NULL DEFAULT NULL,
			deposit_paid TINYINT(1) DEFAULT 0,
			full_amount_paid TINYINT(1) DEFAULT 0,
			payment_method VARCHAR(50) NULL COMMENT 'stripe, paypal, cash, card',
			customer_notes TEXT NULL COMMENT 'Notes from customer during booking',
			staff_notes TEXT NULL COMMENT 'Internal staff notes',
			cancellation_reason TEXT NULL,
			cancelled_at DATETIME NULL,
			cancelled_by VARCHAR(50) NULL COMMENT 'customer, staff, system',
			google_calendar_event_id VARCHAR(255) NULL COMMENT 'For calendar sync',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME NULL DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_booking_slot (staff_id, booking_date, start_time),
			KEY idx_customer_id (customer_id),
			KEY idx_service_id (service_id),
			KEY idx_staff_id (staff_id),
			KEY idx_booking_date (booking_date),
			KEY idx_status (status),
			KEY idx_deleted_at (deleted_at),
			KEY idx_date_time (booking_date, start_time)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_payments table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 */
	private static function create_payments_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_payments';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			customer_id BIGINT UNSIGNED NOT NULL,
			amount DECIMAL(10,2) NOT NULL,
			payment_type ENUM('deposit','full_payment','refund') DEFAULT 'full_payment',
			payment_method VARCHAR(50) NOT NULL COMMENT 'stripe, paypal, cash, card',
			payment_status ENUM('pending','completed','failed','refunded','partially_refunded') DEFAULT 'pending',
			stripe_payment_intent_id VARCHAR(255) NULL COMMENT 'Stripe PaymentIntent ID',
			stripe_charge_id VARCHAR(255) NULL COMMENT 'Stripe Charge ID',
			paypal_order_id VARCHAR(255) NULL COMMENT 'PayPal Order ID',
			paypal_capture_id VARCHAR(255) NULL COMMENT 'PayPal Capture ID',
			refund_amount DECIMAL(10,2) NULL DEFAULT NULL,
			refund_reason TEXT NULL,
			refunded_at DATETIME NULL,
			transaction_date DATETIME NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_booking_id (booking_id),
			KEY idx_customer_id (customer_id),
			KEY idx_payment_status (payment_status),
			KEY idx_transaction_date (transaction_date),
			KEY idx_stripe_payment_intent (stripe_payment_intent_id),
			KEY idx_paypal_order (paypal_order_id)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_working_hours table.
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 */
	private static function create_working_hours_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_working_hours';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			staff_id BIGINT UNSIGNED NOT NULL,
			day_of_week TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday, 6=Saturday',
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			is_active TINYINT(1) DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_staff_id (staff_id),
			KEY idx_day_of_week (day_of_week),
			KEY idx_is_active (is_active)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create wp_bookings_settings table (key-value store).
	 *
	 * @param string $table_prefix    WordPress table prefix.
	 * @param string $charset_collate Database charset collation.
	 */
	private static function create_settings_table( $table_prefix, $charset_collate ) {
		$table_name = $table_prefix . 'bookings_settings';

		$sql = "CREATE TABLE $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			setting_key VARCHAR(100) NOT NULL,
			setting_value LONGTEXT NULL,
			autoload TINYINT(1) DEFAULT 1 COMMENT 'Load on plugin init like wp_options',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_setting_key (setting_key),
			KEY idx_autoload (autoload)
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
			// Part 2 tables (drop first due to dependencies).
			$table_prefix . 'bookings_payments',
			$table_prefix . 'bookings',
			$table_prefix . 'bookings_working_hours',
			$table_prefix . 'bookings_customers',
			$table_prefix . 'bookings_settings',
			// Part 1 tables (existing).
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

