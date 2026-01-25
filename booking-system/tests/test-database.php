<?php
/**
 * Database tests.
 *
 * @package Booking_System
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test database functionality.
 */
class Test_Database extends TestCase {

	/**
	 * Test all 10 tables exist.
	 */
	public function test_all_tables_exist() {
		global $wpdb;

		$tables = array(
			'bookings_services',
			'bookings_categories',
			'bookings_service_categories',
			'bookings_staff',
			'bookings_staff_services',
			'bookings_customers',
			'bookings',
			'bookings_payments',
			'bookings_working_hours',
			'bookings_settings',
		);

		foreach ( $tables as $table ) {
			$table_name = $wpdb->prefix . $table;
			$exists     = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
			
			$this->assertEquals( $table_name, $exists, "Table $table_name should exist" );
		}
	}

	/**
	 * Test bookings table has unique constraint.
	 */
	public function test_bookings_table_unique_constraint() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings';
		
		// Get table indexes
		$indexes = $wpdb->get_results( "SHOW INDEX FROM $table_name" );
		
		// Look for unique_booking_slot index
		$found_unique = false;
		foreach ( $indexes as $index ) {
			if ( $index->Key_name === 'unique_booking_slot' && $index->Non_unique == 0 ) {
				$found_unique = true;
				break;
			}
		}
		
		$this->assertTrue( $found_unique, 'Bookings table should have unique_booking_slot constraint' );
	}

	/**
	 * Test services table structure.
	 */
	public function test_services_table_structure() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_services';
		
		// Get table columns
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM $table_name" );
		
		$column_names = array_column( $columns, 'Field' );
		
		$required_columns = array(
			'id',
			'name',
			'description',
			'duration',
			'price',
			'deposit_amount',
			'is_active',
			'created_at',
			'updated_at',
			'deleted_at',
		);
		
		foreach ( $required_columns as $column ) {
			$this->assertContains( $column, $column_names, "Services table should have $column column" );
		}
	}

	/**
	 * Test staff table has unique email constraint.
	 */
	public function test_staff_table_unique_email() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_staff';
		
		// Get table indexes
		$indexes = $wpdb->get_results( "SHOW INDEX FROM $table_name" );
		
		// Look for unique email index
		$found_unique = false;
		foreach ( $indexes as $index ) {
			if ( $index->Key_name === 'unique_email' && $index->Non_unique == 0 ) {
				$found_unique = true;
				break;
			}
		}
		
		$this->assertTrue( $found_unique, 'Staff table should have unique email constraint' );
	}

	/**
	 * Test can insert and retrieve data.
	 */
	public function test_database_insert_and_retrieve() {
		global $wpdb;

		// Insert test service
		$table_name = $wpdb->prefix . 'bookings_services';
		
		$result = $wpdb->insert(
			$table_name,
			array(
				'name'     => 'Test Service',
				'duration' => 60,
				'price'    => 50.00,
			),
			array( '%s', '%d', '%f' )
		);
		
		$this->assertNotFalse( $result, 'Should insert test service' );
		
		$inserted_id = $wpdb->insert_id;
		
		// Retrieve the service
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$inserted_id
			),
			ARRAY_A
		);
		
		$this->assertNotNull( $service );
		$this->assertEquals( 'Test Service', $service['name'] );
		$this->assertEquals( 60, $service['duration'] );
		$this->assertEquals( '50.00', $service['price'] );
		
		// Cleanup
		$wpdb->delete( $table_name, array( 'id' => $inserted_id ), array( '%d' ) );
	}
}
