<?php
/**
 * Migration: Create notification digest queue table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Queue rows for staff digest notification processing.
 */
class Bookit_Migration_0017_Create_Notification_Digest_Queue extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0017-create-notification-digest-queue';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookit_notification_digest_queue';

		if ( $this->table_exists( $table_name ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"CREATE TABLE {$table_name} (
				id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				staff_id   BIGINT UNSIGNED NOT NULL,
				event_type ENUM('new_booking','reschedule','cancellation') NOT NULL,
				booking_id BIGINT UNSIGNED NOT NULL,
				processed  TINYINT(1) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_staff_event_processed (staff_id, event_type, processed),
				KEY idx_booking_id (booking_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
		);
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookit_notification_digest_queue';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DROP TABLE {$table_name}" );
	}

	/**
	 * Check whether a table exists.
	 *
	 * @param string $table_name Full table name.
	 * @return bool
	 */
	private function table_exists( string $table_name ): bool {
		global $wpdb;

		// Avoid SHOW TABLES LIKE: '_' is a wildcard in SQL LIKE patterns.
		// Also avoid information_schema in case the connection DB differs from DB_NAME in wp-env.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( ! is_array( $tables ) ) {
			return false;
		}

		return in_array( $table_name, $tables, true );
	}
}
