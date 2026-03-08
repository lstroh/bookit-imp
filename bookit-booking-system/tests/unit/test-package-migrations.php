<?php
/**
 * Tests for package feature migrations and error codes.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test package migrations.
 */
class Test_Package_Migrations extends WP_UnitTestCase {

	/**
	 * Ordered migration instances.
	 *
	 * @var array<int, Bookit_Migration_Base>
	 */
	private array $migrations = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->load_package_migration_classes();
		$this->migrations = array(
			new Bookit_Migration_0005_Create_Package_Types_Table(),
			new Bookit_Migration_0006_Create_Customer_Packages_Table(),
			new Bookit_Migration_0007_Create_Package_Redemptions_Table(),
			new Bookit_Migration_0008_Add_Customer_Package_Id_To_Bookings(),
		);

		$this->rollback_package_migrations();
		require_once BOOKIT_PLUGIN_DIR . 'includes/config/error-codes.php';
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		$this->rollback_package_migrations();
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Migration_0005_Create_Package_Types_Table::up
	 */
	public function test_package_types_table_exists_after_migration() {
		global $wpdb;

		$this->run_package_migrations();

		$table_name = $wpdb->prefix . 'bookings_package_types';
		$this->assertTrue( $this->table_exists( $table_name ) );

		$columns = $this->get_column_names( $table_name );
		$expected = array(
			'id',
			'name',
			'description',
			'sessions_count',
			'price_mode',
			'fixed_price',
			'discount_percentage',
			'expiry_enabled',
			'expiry_days',
			'applicable_service_ids',
			'is_active',
			'created_at',
			'updated_at',
		);

		foreach ( $expected as $column_name ) {
			$this->assertContains( $column_name, $columns );
		}
	}

	/**
	 * @covers Bookit_Migration_0006_Create_Customer_Packages_Table::up
	 */
	public function test_customer_packages_table_exists_after_migration() {
		global $wpdb;

		$this->run_package_migrations();

		$table_name = $wpdb->prefix . 'bookings_customer_packages';
		$this->assertTrue( $this->table_exists( $table_name ) );

		$columns = $this->get_column_names( $table_name );
		$expected = array(
			'id',
			'customer_id',
			'package_type_id',
			'sessions_total',
			'sessions_remaining',
			'purchase_price',
			'purchased_at',
			'expires_at',
			'status',
			'payment_method',
			'payment_reference',
			'notes',
			'created_at',
			'updated_at',
		);

		foreach ( $expected as $column_name ) {
			$this->assertContains( $column_name, $columns );
		}

		$status_enum = $this->get_enum_type( $table_name, 'status' );
		$this->assertSame( "enum('active','exhausted','expired','cancelled')", strtolower( (string) $status_enum ) );
	}

	/**
	 * @covers Bookit_Migration_0007_Create_Package_Redemptions_Table::up
	 */
	public function test_package_redemptions_table_exists_after_migration() {
		global $wpdb;

		$this->run_package_migrations();

		$table_name = $wpdb->prefix . 'bookings_package_redemptions';
		$this->assertTrue( $this->table_exists( $table_name ) );

		$columns = $this->get_column_names( $table_name );
		$expected = array(
			'id',
			'customer_package_id',
			'booking_id',
			'redeemed_at',
			'redeemed_by',
			'notes',
			'created_at',
		);

		foreach ( $expected as $column_name ) {
			$this->assertContains( $column_name, $columns );
		}
	}

	/**
	 * @covers Bookit_Migration_0008_Add_Customer_Package_Id_To_Bookings::up
	 */
	public function test_bookings_table_has_customer_package_id_column() {
		global $wpdb;

		$this->run_package_migrations();

		$bookings_table = $wpdb->prefix . 'bookings';
		$column = $wpdb->get_row(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$bookings_table} LIKE %s",
				'customer_package_id'
			),
			ARRAY_A
		);

		$this->assertNotEmpty( $column );
		$this->assertSame( 'YES', $column['Null'] );
	}

	/**
	 * @covers Bookit_Migration_0007_Create_Package_Redemptions_Table::down
	 * @covers Bookit_Migration_0006_Create_Customer_Packages_Table::down
	 * @covers Bookit_Migration_0005_Create_Package_Types_Table::down
	 * @covers Bookit_Migration_0008_Add_Customer_Package_Id_To_Bookings::down
	 */
	public function test_rollback_drops_package_tables() {
		global $wpdb;

		$this->run_package_migrations();
		$this->rollback_package_migrations();

		$this->assertFalse( $this->table_exists( $wpdb->prefix . 'bookings_package_redemptions' ) );
		$this->assertFalse( $this->table_exists( $wpdb->prefix . 'bookings_customer_packages' ) );
		$this->assertFalse( $this->table_exists( $wpdb->prefix . 'bookings_package_types' ) );
		$this->assertFalse( $this->column_exists( $wpdb->prefix . 'bookings', 'customer_package_id' ) );
	}

	/**
	 * @covers Bookit_Migration_0007_Create_Package_Redemptions_Table::down
	 * @covers Bookit_Migration_0006_Create_Customer_Packages_Table::down
	 * @covers Bookit_Migration_0005_Create_Package_Types_Table::down
	 * @covers Bookit_Migration_0008_Add_Customer_Package_Id_To_Bookings::down
	 */
	public function test_rollback_is_idempotent() {
		global $wpdb;

		$this->run_package_migrations();
		$this->rollback_package_migrations();
		$this->rollback_package_migrations();

		$this->assertFalse( $this->table_exists( $wpdb->prefix . 'bookings_package_redemptions' ) );
		$this->assertFalse( $this->table_exists( $wpdb->prefix . 'bookings_customer_packages' ) );
		$this->assertFalse( $this->table_exists( $wpdb->prefix . 'bookings_package_types' ) );
		$this->assertFalse( $this->column_exists( $wpdb->prefix . 'bookings', 'customer_package_id' ) );
	}

	/**
	 * @covers Bookit_Error_Registry::register_package_errors
	 * @covers Bookit_Error_Registry::get
	 */
	public function test_error_codes_registered() {
		$cases = array(
			'E5001' => 'Package not found.',
			'E5002' => 'This package has no sessions remaining.',
			'E5003' => 'This package has expired.',
			'E5004' => 'This package cannot be used for the selected service.',
			'E5005' => 'Insufficient package sessions to complete this booking.',
		);

		foreach ( $cases as $code => $expected_message ) {
			$definition = Bookit_Error_Registry::get( $code );

			$this->assertSame( $expected_message, (string) $definition['user_message'] );
			$this->assertSame( 'packages', $definition['category'] );
		}
	}

	/**
	 * @coversNothing
	 */
	public function test_error_code_constants_defined() {
		$this->assertTrue( defined( 'BOOKIT_E5001' ) );
		$this->assertTrue( defined( 'BOOKIT_E5002' ) );
		$this->assertTrue( defined( 'BOOKIT_E5003' ) );
		$this->assertTrue( defined( 'BOOKIT_E5004' ) );
		$this->assertTrue( defined( 'BOOKIT_E5005' ) );

		$this->assertSame( 'E5001', BOOKIT_E5001 );
		$this->assertSame( 'E5002', BOOKIT_E5002 );
		$this->assertSame( 'E5003', BOOKIT_E5003 );
		$this->assertSame( 'E5004', BOOKIT_E5004 );
		$this->assertSame( 'E5005', BOOKIT_E5005 );
	}

	/**
	 * Load migration files under test.
	 *
	 * @return void
	 */
	private function load_package_migration_classes(): void {
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/0005-create-package-types-table.php';
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/0006-create-customer-packages-table.php';
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/0007-create-package-redemptions-table.php';
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/0008-add-customer-package-id-to-bookings.php';
	}

	/**
	 * Run all package migrations in forward order.
	 *
	 * @return void
	 */
	private function run_package_migrations(): void {
		foreach ( $this->migrations as $migration ) {
			$migration->up();
		}
	}

	/**
	 * Roll back package migrations in reverse order.
	 *
	 * @return void
	 */
	private function rollback_package_migrations(): void {
		$reverse_order = array_reverse( $this->migrations );

		foreach ( $reverse_order as $migration ) {
			$migration->down();
		}
	}

	/**
	 * Check whether a table exists.
	 *
	 * @param string $table_name Full table name.
	 * @return bool
	 */
	private function table_exists( string $table_name ): bool {
		global $wpdb;

		$table = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		return $table === $table_name;
	}

	/**
	 * Get all column names for a table.
	 *
	 * @param string $table_name Full table name.
	 * @return string[]
	 */
	private function get_column_names( string $table_name ): array {
		global $wpdb;

		$rows = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}", ARRAY_A );
		if ( empty( $rows ) ) {
			return array();
		}

		return array_values( wp_list_pluck( $rows, 'Field' ) );
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

	/**
	 * Get enum type definition for a specific column.
	 *
	 * @param string $table_name Full table name.
	 * @param string $column     Column name.
	 * @return string|null
	 */
	private function get_enum_type( string $table_name, string $column ): ?string {
		global $wpdb;

		$column_type = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COLUMN_TYPE
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = DATABASE()
					AND TABLE_NAME = %s
					AND COLUMN_NAME = %s',
				$table_name,
				$column
			)
		);

		return is_string( $column_type ) ? $column_type : null;
	}
}
