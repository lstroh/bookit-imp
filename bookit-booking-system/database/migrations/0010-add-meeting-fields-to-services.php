<?php
/**
 * Migration: Add meeting fields to services table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Adds online meeting configuration columns to services.
 */
class Bookit_Migration_0010_Add_Meeting_Fields_To_Services extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0010-add-meeting-fields-to-services';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$services_table = $wpdb->prefix . 'bookings_services';

		if (
			$this->column_exists( $services_table, 'meeting_type' ) &&
			$this->column_exists( $services_table, 'preferred_platform' ) &&
			$this->column_exists( $services_table, 'default_meeting_link' )
		) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"ALTER TABLE {$services_table}
				ADD COLUMN meeting_type VARCHAR(20) NOT NULL DEFAULT 'none'
					COMMENT 'none | online | in_person',
				ADD COLUMN preferred_platform VARCHAR(20) NULL
					COMMENT 'zoom | google_meet | whatsapp | teams | generic',
				ADD COLUMN default_meeting_link VARCHAR(2048) NULL
					COMMENT 'Optional default meeting link for this service'"
		);
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$services_table = $wpdb->prefix . 'bookings_services';

		if (
			! $this->column_exists( $services_table, 'meeting_type' ) &&
			! $this->column_exists( $services_table, 'preferred_platform' ) &&
			! $this->column_exists( $services_table, 'default_meeting_link' )
		) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"ALTER TABLE {$services_table}
				DROP COLUMN meeting_type,
				DROP COLUMN preferred_platform,
				DROP COLUMN default_meeting_link"
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
