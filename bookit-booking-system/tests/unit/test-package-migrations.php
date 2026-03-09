<?php
/**
 * Tests for package schema presence and error codes.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Lightweight package schema and error registry assertions.
 */
class Test_Package_Migrations extends WP_UnitTestCase {

	/**
	 * @coversNothing
	 */
	public function test_package_types_table_exists_after_migration() {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_package_types';
		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
		);
	}

	/**
	 * @coversNothing
	 */
	public function test_customer_packages_table_exists_after_migration() {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_customer_packages';
		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
		);
	}

	/**
	 * @coversNothing
	 */
	public function test_package_redemptions_table_exists_after_migration() {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_package_redemptions';
		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) )
		);
	}

	/**
	 * @coversNothing
	 */
	public function test_bookings_table_has_customer_package_id_column() {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';
		$column         = $wpdb->get_row(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$bookings_table} LIKE %s",
				'customer_package_id'
			),
			ARRAY_A
		);

		$this->assertNotEmpty( $column );
		$this->assertSame( 'customer_package_id', $column['Field'] );
	}

	/**
	 * @coversNothing
	 */
	public function test_package_table_columns_have_expected_types() {
		global $wpdb;

		$types_table   = $wpdb->prefix . 'bookings_package_types';
		$types_columns = $this->get_full_columns_map( $types_table );

		$this->assertSame( 'bigint(20) unsigned', strtolower( (string) $types_columns['id']['Type'] ) );
		$this->assertSame( 'NO', $types_columns['name']['Null'] );
		$this->assertSame( 'varchar(255)', strtolower( (string) $types_columns['name']['Type'] ) );
		$this->assertSame( 'NO', $types_columns['sessions_count']['Null'] );

		$customer_table   = $wpdb->prefix . 'bookings_customer_packages';
		$customer_columns = $this->get_full_columns_map( $customer_table );
		$status_enum      = $this->get_enum_type( $customer_table, 'status' );

		$this->assertSame( 'NO', $customer_columns['customer_id']['Null'] );
		$this->assertSame( 'bigint(20) unsigned', strtolower( (string) $customer_columns['customer_id']['Type'] ) );
		$this->assertSame( 'NO', $customer_columns['package_type_id']['Null'] );
		$this->assertSame( "enum('active','exhausted','expired','cancelled')", strtolower( (string) $status_enum ) );
	}

	/**
	 * @coversNothing
	 */
	public function test_bookings_customer_package_id_column_type_and_nullability() {
		global $wpdb;

		$table   = $wpdb->prefix . 'bookings';
		$columns = $this->get_full_columns_map( $table );

		$this->assertArrayHasKey( 'customer_package_id', $columns );
		$this->assertSame( 'bigint(20) unsigned', strtolower( (string) $columns['customer_package_id']['Type'] ) );
		$this->assertSame( 'YES', $columns['customer_package_id']['Null'] );
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
	 * Get full column metadata keyed by column name.
	 *
	 * @param string $table_name Full table name.
	 * @return array<string, array<string, mixed>>
	 */
	private function get_full_columns_map( string $table_name ): array {
		global $wpdb;

		$rows = $wpdb->get_results( "SHOW FULL COLUMNS FROM {$table_name}", ARRAY_A );
		$this->assertNotEmpty( $rows, "Expected columns for {$table_name}" );

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (string) $row['Field'] ] = $row;
		}

		return $map;
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
