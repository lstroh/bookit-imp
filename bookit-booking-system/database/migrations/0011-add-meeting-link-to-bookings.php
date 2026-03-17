<?php
/**
 * Migration: Add meeting link field to bookings table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Adds per-booking meeting URL field.
 */
class Bookit_Migration_0011_Add_Meeting_Link_To_Bookings extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0011-add-meeting-link-to-bookings';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		if ( $this->column_exists( $bookings_table, 'meeting_link' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"ALTER TABLE {$bookings_table}
				ADD COLUMN meeting_link VARCHAR(2048) NULL
					COMMENT 'Meeting URL for online bookings'"
		);
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		if ( ! $this->column_exists( $bookings_table, 'meeting_link' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"ALTER TABLE {$bookings_table}
				DROP COLUMN meeting_link"
		);
	}

	/**
	 * Check whether a column exists.
	 *
	 * @param string $table_name Full table name.
	 * @param string $column     Column name.
	 * @return bool
	 */
	private function column_exists( string $table_name, string $column ): bool {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SHOW COLUMNS FROM {$table_name} LIKE %s",
			$column
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_var( $sql );
		return ! empty( $result );
	}
}
