<?php
/**
 * PHPUnit tests for Stripe configuration and admin settings.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Stripe configuration class and settings.
 *
 * @covers Bookit_Stripe_Config
 */
class Test_Stripe_Config extends WP_UnitTestCase {

	/**
	 * Test publishable key (pk_test_...).
	 *
	 * @var string
	 */
	const TEST_PUBLISHABLE_KEY = 'pk_test_51234567890abcdefghijklmnopqrstuvwxyz';

	/**
	 * Test secret key (sk_test_...).
	 *
	 * @var string
	 */
	const TEST_SECRET_KEY = 'sk_test_51234567890abcdefghijklmnopqrstuvwxyz';

	/**
	 * Test webhook secret (whsec_...).
	 *
	 * @var string
	 */
	const TEST_WEBHOOK_SECRET = 'whsec_1234567890abcdefghijklmnopqrstuvwxyz';

	/**
	 * Stripe option names used by the plugin.
	 *
	 * @var array<string>
	 */
	private static $stripe_options = array(
		'bookit_stripe_test_mode',
		'bookit_stripe_test_publishable_key',
		'bookit_stripe_test_secret_key',
		'bookit_stripe_test_webhook_secret',
		'bookit_stripe_live_publishable_key',
		'bookit_stripe_live_secret_key',
		'bookit_stripe_live_webhook_secret',
	);

	/**
	 * Saved option values before tests (for restore in tearDown).
	 *
	 * @var array<string, mixed>
	 */
	private $saved_options = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Save current Stripe options so we can restore in tearDown.
		foreach ( self::$stripe_options as $option ) {
			$value = get_option( $option, '__not_set__' );
			$this->saved_options[ $option ] = $value;
		}

		$this->reset_stripe_client_static();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		// Restore original options.
		foreach ( $this->saved_options as $option => $value ) {
			if ( $value === '__not_set__' ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}

		$this->reset_stripe_client_static();
		parent::tearDown();
	}

	/**
	 * Reset Bookit_Stripe_Config::$stripe_client via reflection so tests run in isolation.
	 */
	private function reset_stripe_client_static(): void {
		$reflection = new ReflectionClass( Bookit_Stripe_Config::class );
		$property  = $reflection->getProperty( 'stripe_client' );
		$property->setAccessible( true );
		$property->setValue( null );
		$property->setAccessible( false );
	}

	/**
	 * Force live mode by directly setting the option to '0' in the database,
	 * bypassing all WordPress filters. Clears caches so get_option reads fresh.
	 */
	private function force_live_mode(): void {
		global $wpdb;

		// Check if option exists.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
				'bookit_stripe_test_mode'
			)
		);

		if ( $exists ) {
			$wpdb->update(
				$wpdb->options,
				array( 'option_value' => '0' ),
				array( 'option_name' => 'bookit_stripe_test_mode' )
			);
		} else {
			$wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => 'bookit_stripe_test_mode',
					'option_value' => '0',
					'autoload'     => 'yes',
				)
			);
		}

		// Clear all option caches so get_option reads from DB.
		wp_cache_delete( 'bookit_stripe_test_mode', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_flush();
	}

	// -------------------------------------------------------------------------
	// 1. TEST STRIPE CONFIG CLASS - Get Mode
	// -------------------------------------------------------------------------

	/**
	 * Test get_mode returns 'test' when test mode is enabled.
	 *
	 * @covers Bookit_Stripe_Config::get_mode
	 */
	public function test_get_mode_returns_test_when_test_mode_enabled(): void {
		update_option( 'bookit_stripe_test_mode', true );
		$this->assertSame( 'test', Bookit_Stripe_Config::get_mode() );
	}

	/**
	 * Test get_mode returns 'live' when test mode is disabled.
	 *
	 * @covers Bookit_Stripe_Config::get_mode
	 */
	public function test_get_mode_returns_live_when_test_mode_disabled(): void {
		$this->force_live_mode();
		$this->assertSame( 'live', Bookit_Stripe_Config::get_mode() );
	}

	/**
	 * Test get_mode defaults to 'test' when no setting exists.
	 *
	 * @covers Bookit_Stripe_Config::get_mode
	 */
	public function test_get_mode_defaults_to_test_when_no_setting_exists(): void {
		delete_option( 'bookit_stripe_test_mode' );
		$this->assertSame( 'test', Bookit_Stripe_Config::get_mode() );
	}

	// -------------------------------------------------------------------------
	// 1. TEST STRIPE CONFIG CLASS - Get Publishable Key
	// -------------------------------------------------------------------------

	/**
	 * Test get_publishable_key returns test key when in test mode.
	 *
	 * @covers Bookit_Stripe_Config::get_publishable_key
	 */
	public function test_get_publishable_key_returns_test_key_when_in_test_mode(): void {
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_publishable_key', self::TEST_PUBLISHABLE_KEY );
		$this->assertSame( self::TEST_PUBLISHABLE_KEY, Bookit_Stripe_Config::get_publishable_key() );
	}

	/**
	 * Test get_publishable_key returns empty string when no key set.
	 *
	 * @covers Bookit_Stripe_Config::get_publishable_key
	 */
	public function test_get_publishable_key_returns_empty_string_when_no_key_set(): void {
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_publishable_key', '' );
		$this->assertSame( '', Bookit_Stripe_Config::get_publishable_key() );
	}

	/**
	 * Test get_publishable_key returns correct key after saving.
	 *
	 * @covers Bookit_Stripe_Config::get_publishable_key
	 */
	public function test_get_publishable_key_returns_correct_key_after_saving(): void {
		update_option( 'bookit_stripe_test_mode', true );
		$key = self::TEST_PUBLISHABLE_KEY;
		update_option( 'bookit_stripe_test_publishable_key', $key );
		$this->assertSame( $key, get_option( 'bookit_stripe_test_publishable_key' ) );
		$this->assertSame( $key, Bookit_Stripe_Config::get_publishable_key() );
	}

	/**
	 * Test get_publishable_key returns live key when in live mode.
	 *
	 * @covers Bookit_Stripe_Config::get_publishable_key
	 */
	public function test_get_publishable_key_returns_live_key_when_in_live_mode(): void {
		$live_key = 'pk_live_51234567890abcdefghijklmnopqrstuvwxyz';
		update_option( 'bookit_stripe_live_publishable_key', $live_key );
		$this->force_live_mode();
		$this->assertSame( $live_key, Bookit_Stripe_Config::get_publishable_key() );
	}

	// -------------------------------------------------------------------------
	// 1. TEST STRIPE CONFIG CLASS - Get Secret Key
	// -------------------------------------------------------------------------

	/**
	 * Test get_secret_key returns test secret key when in test mode.
	 *
	 * @covers Bookit_Stripe_Config::get_secret_key
	 */
	public function test_get_secret_key_returns_test_key_when_in_test_mode(): void {
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_secret_key', self::TEST_SECRET_KEY );
		$this->assertSame( self::TEST_SECRET_KEY, Bookit_Stripe_Config::get_secret_key() );
	}

	/**
	 * Test get_secret_key returns empty string when no key set.
	 *
	 * @covers Bookit_Stripe_Config::get_secret_key
	 */
	public function test_get_secret_key_returns_empty_string_when_no_key_set(): void {
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_secret_key', '' );
		$this->assertSame( '', Bookit_Stripe_Config::get_secret_key() );
	}

	/**
	 * Test get_secret_key handles missing option gracefully (returns empty string).
	 *
	 * @covers Bookit_Stripe_Config::get_secret_key
	 */
	public function test_get_secret_key_handles_missing_option_gracefully(): void {
		update_option( 'bookit_stripe_test_mode', true );
		delete_option( 'bookit_stripe_test_secret_key' );
		$this->assertSame( '', Bookit_Stripe_Config::get_secret_key() );
	}

	// -------------------------------------------------------------------------
	// 1. TEST STRIPE CONFIG CLASS - Get Webhook Secret
	// -------------------------------------------------------------------------

	/**
	 * Test get_webhook_secret returns webhook secret when set.
	 *
	 * @covers Bookit_Stripe_Config::get_webhook_secret
	 */
	public function test_get_webhook_secret_returns_secret_when_set(): void {
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_webhook_secret', self::TEST_WEBHOOK_SECRET );
		$this->assertSame( self::TEST_WEBHOOK_SECRET, Bookit_Stripe_Config::get_webhook_secret() );
	}

	/**
	 * Test get_webhook_secret returns empty string when not set.
	 *
	 * @covers Bookit_Stripe_Config::get_webhook_secret
	 */
	public function test_get_webhook_secret_returns_empty_string_when_not_set(): void {
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_webhook_secret', '' );
		$this->assertSame( '', Bookit_Stripe_Config::get_webhook_secret() );
	}

	// -------------------------------------------------------------------------
	// 1. TEST STRIPE CONFIG CLASS - SDK Initialization
	// -------------------------------------------------------------------------

	/**
	 * Test get_stripe_client initializes with correct API key when SDK is loaded.
	 *
	 * @covers Bookit_Stripe_Config::get_stripe_client
	 */
	public function test_get_stripe_client_initializes_with_correct_api_key_when_sdk_loaded(): void {
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_secret_key', self::TEST_SECRET_KEY );

		if ( ! class_exists( '\Stripe\StripeClient' ) ) {
			$this->markTestSkipped( 'Stripe SDK not loaded (run composer install).' );
		}

		$client = Bookit_Stripe_Config::get_stripe_client();
		$this->assertInstanceOf( \Stripe\StripeClient::class, $client );
	}

	/**
	 * Test get_stripe_client returns null when API key is missing.
	 *
	 * @covers Bookit_Stripe_Config::get_stripe_client
	 */
	public function test_get_stripe_client_returns_null_when_api_key_missing(): void {
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_secret_key', '' );

		$client = Bookit_Stripe_Config::get_stripe_client();
		$this->assertNull( $client );
	}

	/**
	 * Test get_stripe_client returns same instance on subsequent calls (singleton-like).
	 *
	 * @covers Bookit_Stripe_Config::get_stripe_client
	 */
	public function test_get_stripe_client_returns_same_instance_on_subsequent_calls(): void {
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_secret_key', self::TEST_SECRET_KEY );

		if ( ! class_exists( '\Stripe\StripeClient' ) ) {
			$this->markTestSkipped( 'Stripe SDK not loaded.' );
		}

		$client1 = Bookit_Stripe_Config::get_stripe_client();
		$client2 = Bookit_Stripe_Config::get_stripe_client();
		$this->assertSame( $client1, $client2 );
	}

	/**
	 * Test get_stripe_client handles missing option (empty secret) gracefully.
	 *
	 * @covers Bookit_Stripe_Config::get_stripe_client
	 */
	public function test_get_stripe_client_handles_missing_option_gracefully(): void {
		update_option( 'bookit_stripe_test_mode', true );
		delete_option( 'bookit_stripe_test_secret_key' );

		$client = Bookit_Stripe_Config::get_stripe_client();
		$this->assertNull( $client );
	}

	// -------------------------------------------------------------------------
	// 2. TEST SETTINGS VALIDATION - Key Format Validation
	// -------------------------------------------------------------------------

	/**
	 * Test validate_publishable_key accepts valid pk_test_ key.
	 *
	 * @covers Bookit_Stripe_Config::validate_publishable_key
	 */
	public function test_validate_publishable_key_accepts_valid_pk_test(): void {
		$this->assertTrue( Bookit_Stripe_Config::validate_publishable_key( self::TEST_PUBLISHABLE_KEY, 'test' ) );
	}

	/**
	 * Test validate_publishable_key accepts valid pk_live_ key.
	 *
	 * @covers Bookit_Stripe_Config::validate_publishable_key
	 */
	public function test_validate_publishable_key_accepts_valid_pk_live(): void {
		$this->assertTrue( Bookit_Stripe_Config::validate_publishable_key( 'pk_live_51234567890abcdef', 'live' ) );
	}

	/**
	 * Test validate_secret_key accepts valid sk_test_ key.
	 *
	 * @covers Bookit_Stripe_Config::validate_secret_key
	 */
	public function test_validate_secret_key_accepts_valid_sk_test(): void {
		$this->assertTrue( Bookit_Stripe_Config::validate_secret_key( self::TEST_SECRET_KEY, 'test' ) );
	}

	/**
	 * Test validate_secret_key accepts valid sk_live_ key.
	 *
	 * @covers Bookit_Stripe_Config::validate_secret_key
	 */
	public function test_validate_secret_key_accepts_valid_sk_live(): void {
		$this->assertTrue( Bookit_Stripe_Config::validate_secret_key( 'sk_live_51234567890abcdef', 'live' ) );
	}

	/**
	 * Test validate_webhook_secret accepts valid whsec_ key.
	 *
	 * @covers Bookit_Stripe_Config::validate_webhook_secret
	 */
	public function test_validate_webhook_secret_accepts_valid_whsec(): void {
		$this->assertTrue( Bookit_Stripe_Config::validate_webhook_secret( self::TEST_WEBHOOK_SECRET ) );
	}

	/**
	 * Test validate_publishable_key rejects invalid key format.
	 *
	 * @covers Bookit_Stripe_Config::validate_publishable_key
	 */
	public function test_validate_publishable_key_rejects_invalid_format(): void {
		$this->assertFalse( Bookit_Stripe_Config::validate_publishable_key( 'sk_test_abc', 'test' ) );
		$this->assertFalse( Bookit_Stripe_Config::validate_publishable_key( 'pk_live_abc', 'test' ) );
		$this->assertFalse( Bookit_Stripe_Config::validate_publishable_key( 'invalid', 'test' ) );
	}

	/**
	 * Test validate_secret_key rejects invalid key format.
	 *
	 * @covers Bookit_Stripe_Config::validate_secret_key
	 */
	public function test_validate_secret_key_rejects_invalid_format(): void {
		$this->assertFalse( Bookit_Stripe_Config::validate_secret_key( 'pk_test_abc', 'test' ) );
		$this->assertFalse( Bookit_Stripe_Config::validate_secret_key( 'sk_live_abc', 'test' ) );
		$this->assertFalse( Bookit_Stripe_Config::validate_secret_key( 'invalid', 'test' ) );
	}

	/**
	 * Test validate_webhook_secret rejects invalid format.
	 *
	 * @covers Bookit_Stripe_Config::validate_webhook_secret
	 */
	public function test_validate_webhook_secret_rejects_invalid_format(): void {
		$this->assertFalse( Bookit_Stripe_Config::validate_webhook_secret( 'whsec' ) );
		$this->assertFalse( Bookit_Stripe_Config::validate_webhook_secret( 'pk_test_abc' ) );
		$this->assertFalse( Bookit_Stripe_Config::validate_webhook_secret( 'invalid' ) );
	}

	/**
	 * Test validation methods allow empty strings (optional until used).
	 *
	 * @covers Bookit_Stripe_Config::validate_publishable_key
	 * @covers Bookit_Stripe_Config::validate_secret_key
	 * @covers Bookit_Stripe_Config::validate_webhook_secret
	 */
	public function test_validation_allows_empty_strings(): void {
		$this->assertTrue( Bookit_Stripe_Config::validate_publishable_key( '', 'test' ) );
		$this->assertTrue( Bookit_Stripe_Config::validate_secret_key( '', 'test' ) );
		$this->assertTrue( Bookit_Stripe_Config::validate_webhook_secret( '' ) );
	}

	// -------------------------------------------------------------------------
	// 2. TEST SETTINGS VALIDATION - Required Fields / Mode
	// -------------------------------------------------------------------------

	/**
	 * Test config getters return correct keys in test mode (all three key types).
	 *
	 * @covers Bookit_Stripe_Config::get_publishable_key
	 * @covers Bookit_Stripe_Config::get_secret_key
	 * @covers Bookit_Stripe_Config::get_webhook_secret
	 */
	public function test_test_mode_returns_all_three_key_types_when_set(): void {
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_publishable_key', self::TEST_PUBLISHABLE_KEY );
		update_option( 'bookit_stripe_test_secret_key', self::TEST_SECRET_KEY );
		update_option( 'bookit_stripe_test_webhook_secret', self::TEST_WEBHOOK_SECRET );

		$this->assertSame( self::TEST_PUBLISHABLE_KEY, Bookit_Stripe_Config::get_publishable_key() );
		$this->assertSame( self::TEST_SECRET_KEY, Bookit_Stripe_Config::get_secret_key() );
		$this->assertSame( self::TEST_WEBHOOK_SECRET, Bookit_Stripe_Config::get_webhook_secret() );
	}

	/**
	 * Test in live mode getters return live options (can be empty).
	 *
	 * @covers Bookit_Stripe_Config::get_publishable_key
	 * @covers Bookit_Stripe_Config::get_secret_key
	 * @covers Bookit_Stripe_Config::get_webhook_secret
	 */
	public function test_live_mode_allows_empty_keys(): void {
		update_option( 'bookit_stripe_live_publishable_key', '' );
		update_option( 'bookit_stripe_live_secret_key', '' );
		update_option( 'bookit_stripe_live_webhook_secret', '' );
		$this->force_live_mode();

		$this->assertSame( '', Bookit_Stripe_Config::get_publishable_key() );
		$this->assertSame( '', Bookit_Stripe_Config::get_secret_key() );
		$this->assertSame( '', Bookit_Stripe_Config::get_webhook_secret() );
	}

	// -------------------------------------------------------------------------
	// 3. TEST SETTINGS STORAGE - Save & Retrieve
	// -------------------------------------------------------------------------

	/**
	 * Test settings are saved to wp_options.
	 */
	public function test_settings_saved_to_wp_options(): void {
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_publishable_key', self::TEST_PUBLISHABLE_KEY );
		update_option( 'bookit_stripe_test_secret_key', self::TEST_SECRET_KEY );
		update_option( 'bookit_stripe_test_webhook_secret', self::TEST_WEBHOOK_SECRET );

		$this->assertTrue( get_option( 'bookit_stripe_test_mode' ) );
		$this->assertSame( self::TEST_PUBLISHABLE_KEY, get_option( 'bookit_stripe_test_publishable_key' ) );
		$this->assertSame( self::TEST_SECRET_KEY, get_option( 'bookit_stripe_test_secret_key' ) );
		$this->assertSame( self::TEST_WEBHOOK_SECRET, get_option( 'bookit_stripe_test_webhook_secret' ) );
	}

	/**
	 * Test settings persist after retrieval.
	 */
	public function test_settings_persist_after_retrieval(): void {
		// Save test keys.
		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_publishable_key', self::TEST_PUBLISHABLE_KEY );

		// Verify test key persists and is returned in test mode.
		$key = get_option( 'bookit_stripe_test_publishable_key' );
		$this->assertSame( self::TEST_PUBLISHABLE_KEY, $key );
		$this->assertSame( self::TEST_PUBLISHABLE_KEY, Bookit_Stripe_Config::get_publishable_key() );

		// Force live mode - config should return live key (empty).
		$this->force_live_mode();
		$this->assertSame( '', Bookit_Stripe_Config::get_publishable_key() );

		// Test key still exists in DB.
		$key_after = get_option( 'bookit_stripe_test_publishable_key' );
		$this->assertSame( self::TEST_PUBLISHABLE_KEY, $key_after );
	}

	/**
	 * Test retrieve settings returns defaults when options are missing.
	 */
	public function test_retrieve_handles_missing_options_returns_defaults(): void {
		delete_option( 'bookit_stripe_test_mode' );
		delete_option( 'bookit_stripe_test_publishable_key' );
		delete_option( 'bookit_stripe_test_secret_key' );
		delete_option( 'bookit_stripe_test_webhook_secret' );

		$this->assertSame( 'test', Bookit_Stripe_Config::get_mode() );
		$this->assertSame( '', Bookit_Stripe_Config::get_publishable_key() );
		$this->assertSame( '', Bookit_Stripe_Config::get_secret_key() );
		$this->assertSame( '', Bookit_Stripe_Config::get_webhook_secret() );
	}

	/**
	 * Test validation filter rejects invalid key and keeps old value.
	 */
	public function test_validation_filter_rejects_invalid_key_keeps_old_value(): void {
		if ( ! function_exists( 'bookit_stripe_validate_option_update' ) ) {
			require_once BOOKIT_PLUGIN_DIR . 'admin/settings/stripe-settings.php';
		}

		update_option( 'bookit_stripe_test_mode', true );
		update_option( 'bookit_stripe_test_publishable_key', self::TEST_PUBLISHABLE_KEY );

		$old_value = get_option( 'bookit_stripe_test_publishable_key' );
		$invalid   = 'sk_test_invalid_format_for_publishable';
		$result    = bookit_stripe_validate_option_update( $invalid, 'bookit_stripe_test_publishable_key', $old_value );

		$this->assertSame( $old_value, $result );
		$this->assertSame( self::TEST_PUBLISHABLE_KEY, get_option( 'bookit_stripe_test_publishable_key' ) );
	}

	/**
	 * Test validation filter accepts valid key and returns new value.
	 */
	public function test_validation_filter_accepts_valid_key(): void {
		if ( ! function_exists( 'bookit_stripe_validate_option_update' ) ) {
			require_once BOOKIT_PLUGIN_DIR . 'admin/settings/stripe-settings.php';
		}

		$new_value = self::TEST_PUBLISHABLE_KEY;
		$result    = bookit_stripe_validate_option_update( $new_value, 'bookit_stripe_test_publishable_key', '' );
		$this->assertSame( $new_value, $result );
	}

	// -------------------------------------------------------------------------
	// 4. TEST SECURITY - Capability Check
	// -------------------------------------------------------------------------

	/**
	 * Test only users with manage_options can see settings form (form returns content).
	 */
	public function test_user_with_manage_options_can_access_settings_form(): void {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue( current_user_can( 'manage_options' ) );

		if ( ! function_exists( 'bookit_render_stripe_settings_form' ) ) {
			require_once BOOKIT_PLUGIN_DIR . 'admin/settings/stripe-settings.php';
		}
		// Ensure settings are registered (admin_init may have run before this file was required).
		bookit_register_stripe_settings();

		ob_start();
		bookit_render_stripe_settings_form();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'bookit-stripe-settings-form', $output );
		$this->assertStringContainsString( 'bookit_stripe_test_mode', $output );
	}

	/**
	 * Test non-admin users cannot access settings form (output empty or minimal).
	 */
	public function test_non_admin_user_cannot_access_settings_form(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( current_user_can( 'manage_options' ) );

		if ( ! function_exists( 'bookit_render_stripe_settings_form' ) ) {
			require_once BOOKIT_PLUGIN_DIR . 'admin/settings/stripe-settings.php';
		}

		ob_start();
		bookit_render_stripe_settings_form();
		$output = ob_get_clean();

		$this->assertEmpty( trim( $output ) );
	}

	// -------------------------------------------------------------------------
	// EDGE CASES
	// -------------------------------------------------------------------------

	/**
	 * Test is_test_mode returns true when mode is test.
	 *
	 * @covers Bookit_Stripe_Config::is_test_mode
	 */
	public function test_is_test_mode_returns_true_when_mode_is_test(): void {
		update_option( 'bookit_stripe_test_mode', true );
		$this->assertTrue( Bookit_Stripe_Config::is_test_mode() );
	}

	/**
	 * Test is_test_mode returns false when mode is live.
	 *
	 * @covers Bookit_Stripe_Config::is_test_mode
	 */
	public function test_is_test_mode_returns_false_when_mode_is_live(): void {
		$this->force_live_mode();
		$this->assertFalse( Bookit_Stripe_Config::is_test_mode() );
	}

	/**
	 * Test get_* key methods cast non-string option to string (e.g. null from get_option default).
	 *
	 * @covers Bookit_Stripe_Config::get_publishable_key
	 */
	public function test_get_publishable_key_returns_string_when_option_missing(): void {
		update_option( 'bookit_stripe_test_mode', true );
		delete_option( 'bookit_stripe_test_publishable_key' );
		$key = Bookit_Stripe_Config::get_publishable_key();
		$this->assertIsString( $key );
		$this->assertSame( '', $key );
	}

	/**
	 * Test OPTION_GROUP constant is defined.
	 *
	 * @covers Bookit_Stripe_Config::OPTION_GROUP
	 */
	public function test_option_group_constant_defined(): void {
		$this->assertSame( 'bookit_stripe_settings', Bookit_Stripe_Config::OPTION_GROUP );
	}

	/**
	 * Test allowed_options filter includes Stripe settings for options.php save.
	 */
	public function test_allowed_options_includes_stripe_settings(): void {
		if ( ! function_exists( 'bookit_stripe_allowed_options' ) ) {
			require_once BOOKIT_PLUGIN_DIR . 'admin/settings/stripe-settings.php';
		}

		$allowed = bookit_stripe_allowed_options( array() );
		$this->assertArrayHasKey( 'bookit_stripe_settings', $allowed );
		$this->assertContains( 'bookit_stripe_test_mode', $allowed['bookit_stripe_settings'] );
		$this->assertContains( 'bookit_stripe_test_publishable_key', $allowed['bookit_stripe_settings'] );
		$this->assertContains( 'bookit_stripe_test_secret_key', $allowed['bookit_stripe_settings'] );
		$this->assertContains( 'bookit_stripe_test_webhook_secret', $allowed['bookit_stripe_settings'] );
	}
}
