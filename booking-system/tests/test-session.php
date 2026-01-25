<?php
/**
 * Session management tests.
 *
 * @package Booking_System
 */

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test session management functionality.
 */
class Test_Session extends TestCase {

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();
		require_once BOOKING_SYSTEM_PATH . 'includes/class-booking-session.php';
	}

	/**
	 * Tear down test.
	 */
	public function tearDown(): void {
		// Clean up session
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			Booking_Session::destroy();
		}
		parent::tearDown();
	}

	/**
	 * Test session initialization.
	 */
	public function test_session_initialization() {
		Booking_Session::init();
		
		$this->assertEquals( PHP_SESSION_ACTIVE, session_status() );
		$this->assertEquals( 'booking_dashboard_session', session_name() );
	}

	/**
	 * Test setting and getting session values.
	 */
	public function test_session_set_and_get() {
		Booking_Session::set( 'test_key', 'test_value' );
		$value = Booking_Session::get( 'test_key' );
		
		$this->assertEquals( 'test_value', $value );
	}

	/**
	 * Test getting default value when key doesn't exist.
	 */
	public function test_session_get_default() {
		$value = Booking_Session::get( 'non_existent_key', 'default_value' );
		
		$this->assertEquals( 'default_value', $value );
	}

	/**
	 * Test checking if session key exists.
	 */
	public function test_session_has() {
		Booking_Session::set( 'test_key', 'test_value' );
		
		$this->assertTrue( Booking_Session::has( 'test_key' ) );
		$this->assertFalse( Booking_Session::has( 'non_existent_key' ) );
	}

	/**
	 * Test deleting session value.
	 */
	public function test_session_delete() {
		Booking_Session::set( 'test_key', 'test_value' );
		$this->assertTrue( Booking_Session::has( 'test_key' ) );
		
		Booking_Session::delete( 'test_key' );
		$this->assertFalse( Booking_Session::has( 'test_key' ) );
	}

	/**
	 * Test session regeneration.
	 */
	public function test_session_regenerate() {
		Booking_Session::init();
		$old_id = session_id();
		
		Booking_Session::regenerate();
		$new_id = session_id();
		
		$this->assertNotEquals( $old_id, $new_id );
	}

	/**
	 * Test session expiration check.
	 */
	public function test_session_expiration() {
		// Set last activity to 9 hours ago (expired)
		Booking_Session::set( 'last_activity', time() - 32400 );
		
		$this->assertTrue( Booking_Session::is_expired() );
		
		// Set last activity to now (not expired)
		Booking_Session::update_activity();
		
		$this->assertFalse( Booking_Session::is_expired() );
	}

	/**
	 * Test updating activity timestamp.
	 */
	public function test_session_update_activity() {
		$before = Booking_Session::get( 'last_activity', 0 );
		
		// Wait a moment
		sleep( 1 );
		
		Booking_Session::update_activity();
		$after = Booking_Session::get( 'last_activity', 0 );
		
		$this->assertGreaterThan( $before, $after );
	}

	/**
	 * Test session destruction.
	 */
	public function test_session_destroy() {
		Booking_Session::set( 'test_key', 'test_value' );
		$this->assertTrue( Booking_Session::has( 'test_key' ) );
		
		Booking_Session::destroy();
		
		// After destruction, session should be empty
		$this->assertFalse( Booking_Session::has( 'test_key' ) );
	}
}
