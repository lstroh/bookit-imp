<?php
/**
 * Tests for Bookit_Brevo_Email_Provider (Brevo PHP SDK v4).
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Brevo email provider configuration and send guardrails.
 */
class Test_Brevo_Email_Provider extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clear_settings();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		$this->clear_settings();
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::send
	 */
	public function test_send_returns_wp_error_when_api_key_empty() {
		$this->set_setting( 'brevo_api_key', '' );

		$provider = new Bookit_Brevo_Email_Provider();
		$result   = $provider->send(
			[
				'email' => 'test@example.com',
				'name'  => 'Test',
			],
			'Subject',
			'<p>HTML</p>'
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'brevo_not_configured', $result->get_error_code() );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::is_configured
	 */
	public function test_is_configured_returns_false_when_no_api_key() {
		$this->set_setting( 'brevo_api_key', '' );

		$provider = new Bookit_Brevo_Email_Provider();
		$this->assertFalse( $provider->is_configured() );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::is_configured
	 */
	public function test_is_configured_returns_true_when_api_key_set() {
		$this->set_setting( 'brevo_api_key', 'sk_test_brevo_key' );

		$provider = new Bookit_Brevo_Email_Provider();
		$this->assertTrue( $provider->is_configured() );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::get_name
	 */
	public function test_get_name_returns_brevo() {
		$provider = new Bookit_Brevo_Email_Provider();
		$this->assertSame( 'Brevo', $provider->get_name() );
	}

	/**
	 * @covers Bookit_Brevo_Email_Provider::get_slug
	 */
	public function test_get_slug_returns_brevo() {
		$provider = new Bookit_Brevo_Email_Provider();
		$this->assertSame( 'brevo', $provider->get_slug() );
	}

	/**
	 * Insert/update a single setting row in bookings_settings.
	 *
	 * @param string $key   Setting key.
	 * @param string $value Setting value.
	 * @return void
	 */
	private function set_setting( string $key, string $value ): void {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'bookings_settings';

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$settings_table} WHERE setting_key = %s",
				$key
			)
		);

		$wpdb->insert(
			$settings_table,
			[
				'setting_key'   => $key,
				'setting_value' => $value,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Clear settings table rows used by these tests.
	 *
	 * @return void
	 */
	private function clear_settings(): void {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'bookings_settings';
		$wpdb->query( "TRUNCATE TABLE {$settings_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
}
