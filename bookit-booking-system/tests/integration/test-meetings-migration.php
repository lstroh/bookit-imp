<?php
/**
 * Integration tests for meetings-related schema migrations.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test meetings migrations 0010 and 0011.
 */
class Test_Meetings_Migration extends WP_UnitTestCase {

	/**
	 * Services migration.
	 *
	 * @var Bookit_Migration_0010_Add_Meeting_Fields_To_Services
	 */
	private Bookit_Migration_0010_Add_Meeting_Fields_To_Services $migration_0010;

	/**
	 * Bookings migration.
	 *
	 * @var Bookit_Migration_0011_Add_Meeting_Link_To_Bookings
	 */
	private Bookit_Migration_0011_Add_Meeting_Link_To_Bookings $migration_0011;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/0010-add-meeting-fields-to-services.php';
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/0011-add-meeting-link-to-bookings.php';

		$this->migration_0010 = new Bookit_Migration_0010_Add_Meeting_Fields_To_Services();
		$this->migration_0011 = new Bookit_Migration_0011_Add_Meeting_Link_To_Bookings();

		$this->cleanup_meeting_columns();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		$this->cleanup_meeting_columns();
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Migration_0010_Add_Meeting_Fields_To_Services::up
	 */
	public function test_migration_0010_up_adds_all_three_columns() {
		global $wpdb;

		$this->migration_0010->up();

		$services_table = $wpdb->prefix . 'bookings_services';
		$this->assertTrue( $this->column_exists( $services_table, 'meeting_type' ) );
		$this->assertTrue( $this->column_exists( $services_table, 'preferred_platform' ) );
		$this->assertTrue( $this->column_exists( $services_table, 'default_meeting_link' ) );
	}

	/**
	 * @covers Bookit_Migration_0010_Add_Meeting_Fields_To_Services::down
	 */
	public function test_migration_0010_down_removes_all_three_columns() {
		global $wpdb;

		$this->migration_0010->up();
		$this->migration_0010->down();

		$services_table = $wpdb->prefix . 'bookings_services';
		$this->assertFalse( $this->column_exists( $services_table, 'meeting_type' ) );
		$this->assertFalse( $this->column_exists( $services_table, 'preferred_platform' ) );
		$this->assertFalse( $this->column_exists( $services_table, 'default_meeting_link' ) );
	}

	/**
	 * @covers Bookit_Migration_0010_Add_Meeting_Fields_To_Services::up
	 */
	public function test_migration_0010_up_is_idempotent() {
		global $wpdb;

		$this->migration_0010->up();
		$wpdb->last_error = '';
		$this->migration_0010->up();

		$services_table = $wpdb->prefix . 'bookings_services';
		$this->assertEmpty( $wpdb->last_error );
		$this->assertTrue( $this->column_exists( $services_table, 'meeting_type' ) );
		$this->assertTrue( $this->column_exists( $services_table, 'preferred_platform' ) );
		$this->assertTrue( $this->column_exists( $services_table, 'default_meeting_link' ) );
	}

	/**
	 * @covers Bookit_Migration_0011_Add_Meeting_Link_To_Bookings::up
	 */
	public function test_migration_0011_up_adds_meeting_link_column() {
		global $wpdb;

		$this->migration_0011->up();

		$bookings_table = $wpdb->prefix . 'bookings';
		$this->assertTrue( $this->column_exists( $bookings_table, 'meeting_link' ) );
	}

	/**
	 * @covers Bookit_Migration_0011_Add_Meeting_Link_To_Bookings::down
	 */
	public function test_migration_0011_down_removes_meeting_link_column() {
		global $wpdb;

		$this->migration_0011->up();
		$this->migration_0011->down();

		$bookings_table = $wpdb->prefix . 'bookings';
		$this->assertFalse( $this->column_exists( $bookings_table, 'meeting_link' ) );
	}

	/**
	 * @covers Bookit_Migration_0011_Add_Meeting_Link_To_Bookings::up
	 */
	public function test_migration_0011_up_is_idempotent() {
		global $wpdb;

		$this->migration_0011->up();
		$wpdb->last_error = '';
		$this->migration_0011->up();

		$bookings_table = $wpdb->prefix . 'bookings';
		$this->assertEmpty( $wpdb->last_error );
		$this->assertTrue( $this->column_exists( $bookings_table, 'meeting_link' ) );
	}

	/**
	 * @covers Bookit_Migration_0010_Add_Meeting_Fields_To_Services::up
	 */
	public function test_meeting_type_default_value() {
		global $wpdb;

		$this->migration_0010->up();

		$services_table = $wpdb->prefix . 'bookings_services';
		$wpdb->insert(
			$services_table,
			array(
				'name'     => 'Meeting Default Test',
				'duration' => 30,
				'price'    => '99.00',
			),
			array( '%s', '%d', '%f' )
		);

		$service_id = (int) $wpdb->insert_id;
		$value      = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meeting_type FROM {$services_table} WHERE id = %d",
				$service_id
			)
		);

		$this->assertSame( 'none', $value );
	}

	/**
	 * Remove meeting-related columns so every test starts clean.
	 *
	 * @return void
	 */
	private function cleanup_meeting_columns(): void {
		$this->migration_0011->down();
		$this->migration_0010->down();
	}

	/**
	 * Check whether a table column exists.
	 *
	 * @param string $table_name Full table name.
	 * @param string $column     Column name.
	 * @return bool
	 */
	private function column_exists( string $table_name, string $column ): bool {
		global $wpdb;

		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table_name} LIKE %s",
				$column
			)
		);

		return ! empty( $row );
	}
}
