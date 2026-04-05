<?php
/**
 * V2 wizard Stripe Checkout wiring (complete_booking REST).
 *
 * @package Bookit_Booking_System
 */

/**
 * @covers Bookit_Wizard_API::complete_booking
 */
class Test_Stripe_V2_Wiring extends WP_UnitTestCase {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Mock filter priority.
	 *
	 * @var int
	 */
	private $mock_priority = 999;

	/**
	 * @var callable|null
	 */
	private $stripe_mode_cb;

	/**
	 * @var callable|null
	 */
	private $stripe_session_cb;

	/**
	 * Upsert a row in wp_bookings_settings (same storage as dashboard).
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value String or bool.
	 */
	private function upsert_booking_setting( string $key, $value ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bookings_settings';
		$type  = 'string';
		if ( is_bool( $value ) ) {
			$type  = 'boolean';
			$value = $value ? '1' : '0';
		} else {
			$value = (string) $value;
		}

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE setting_key = %s", $key ) );
		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'setting_value' => $value,
					'setting_type'  => $type,
				),
				array( 'setting_key' => $key ),
				array( '%s', '%s' ),
				array( '%s' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'setting_key'   => $key,
					'setting_value' => $value,
					'setting_type'  => $type,
				),
				array( '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Set up.
	 */
	public function setUp(): void {
		parent::setUp();
		Bookit_Session_Manager::clear();
		$ip = Bookit_Rate_Limiter::get_client_ip();
		delete_transient( Bookit_Rate_Limiter::KEY_PREFIX . 'wizard_book_' . md5( $ip ) );
		do_action( 'rest_api_init' );

		$autoload = dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}

		// create_checkout_session() requires a configured secret key before mock mode runs (see class-stripe-checkout.php).
		$this->upsert_booking_setting( 'stripe_test_mode', true );
		$this->upsert_booking_setting( 'stripe_secret_key', 'sk_test_51234567890abcdef' );
		$this->upsert_booking_setting( 'stripe_publishable_key', 'pk_test_51234567890abcdef' );
	}

	/**
	 * Tear down.
	 */
	public function tearDown(): void {
		if ( $this->stripe_mode_cb ) {
			remove_filter( 'bookit_stripe_api_mode', $this->stripe_mode_cb, $this->mock_priority );
			$this->stripe_mode_cb = null;
		}
		if ( $this->stripe_session_cb ) {
			remove_filter( 'bookit_mock_stripe_session', $this->stripe_session_cb, $this->mock_priority );
			$this->stripe_session_cb = null;
		}
		global $wpdb;
		foreach ( array( 'stripe_test_mode', 'stripe_secret_key', 'stripe_publishable_key' ) as $sk ) {
			$wpdb->delete( $wpdb->prefix . 'bookings_settings', array( 'setting_key' => $sk ), array( '%s' ) );
		}
		Bookit_Session_Manager::clear();
		parent::tearDown();
	}

	/**
	 * Insert minimal service and staff for Stripe session validation.
	 *
	 * @return array{service_id: int, staff_id: int}
	 */
	private function insert_service_and_staff(): array {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			array(
				'name'            => 'V2 Stripe Wiring',
				'duration'        => 60,
				'price'           => 50.00,
				'deposit_type'    => 'percentage',
				'deposit_amount'  => 100,
				'is_active'       => 1,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%f', '%s', '%f', '%d', '%s', '%s' )
		);
		$service_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			array(
				'first_name'    => 'Test',
				'last_name'     => 'Stylist',
				'email'         => 'v2-stripe-wiring@example.com',
				'password_hash' => wp_hash_password( 'x' ),
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		$staff_id = (int) $wpdb->insert_id;

		return array(
			'service_id' => $service_id,
			'staff_id'   => $staff_id,
		);
	}

	/**
	 * Stripe mock: success with checkout URL.
	 */
	private function enable_stripe_mock_success(): void {
		$this->stripe_mode_cb = function () {
			return 'mock';
		};
		add_filter( 'bookit_stripe_api_mode', $this->stripe_mode_cb, $this->mock_priority );

		$this->stripe_session_cb = function () {
			return (object) array(
				'id'           => 'cs_test_mock123456',
				'url'          => 'https://checkout.stripe.com/c/pay/cs_test_mock123456',
				'amount_total' => 5000,
				'currency'     => 'gbp',
			);
		};
		add_filter( 'bookit_mock_stripe_session', $this->stripe_session_cb, $this->mock_priority );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_stripe_returns_redirect_url(): void {
		$ids = $this->insert_service_and_staff();
		$this->enable_stripe_mock_success();

		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );

		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'              => 5,
				'service_id'                => $ids['service_id'],
				'staff_id'                  => $ids['staff_id'],
				'date'                      => $booking_date,
				'time'                      => '10:00:00',
				'customer_first_name'       => 'Stripe',
				'customer_last_name'        => 'Tester',
				'customer_email'            => 'stripe-v2-wiring@example.com',
				'customer_phone'            => '07700900111',
				'customer_special_requests' => '',
				'cooling_off_waiver'        => 1,
				'payment_method'            => 'stripe',
				'wizard_version'            => 'v2',
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status(), 'Expected HTTP 200 for Stripe redirect' );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( ! empty( $data['success'] ) );
		$this->assertArrayHasKey( 'redirect_url', $data );
		$this->assertStringStartsWith( 'https://checkout.stripe.com/', $data['redirect_url'] );

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_paypal_returns_501(): void {
		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'   => 5,
				'payment_method' => 'paypal',
				'service_id'     => 1,
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 501, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'PAYMENT_METHOD_NOT_SUPPORTED', $data['code'] );
	}

	/**
	 * @covers Bookit_Wizard_API::complete_booking
	 */
	public function test_complete_booking_stripe_exception_returns_500(): void {
		global $wpdb;

		$ids = $this->insert_service_and_staff();

		$this->stripe_mode_cb = function () {
			return 'mock';
		};
		add_filter( 'bookit_stripe_api_mode', $this->stripe_mode_cb, $this->mock_priority );

		$this->stripe_session_cb = function () {
			throw \Stripe\Exception\InvalidRequestException::factory( 'Simulated Stripe API failure' );
		};
		add_filter( 'bookit_mock_stripe_session', $this->stripe_session_cb, $this->mock_priority );

		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );

		$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bookings" );

		Bookit_Session_Manager::clear();
		Bookit_Session_Manager::set_data(
			array(
				'current_step'              => 5,
				'service_id'                => $ids['service_id'],
				'staff_id'                  => $ids['staff_id'],
				'date'                      => $booking_date,
				'time'                      => '10:00:00',
				'customer_first_name'       => 'Ex',
				'customer_last_name'        => 'ception',
				'customer_email'            => 'stripe-v2-exception@example.com',
				'customer_phone'            => '07700900222',
				'customer_special_requests' => '',
				'cooling_off_waiver'        => 1,
				'payment_method'            => 'stripe',
				'wizard_version'            => 'v2',
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/wizard/complete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 500, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( 'E3010', $data['code'] );

		$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bookings" );
		$this->assertSame( $before, $after, 'No booking row should be created when Stripe fails' );

		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
	}

	/**
	 * StripeObject metadata must use toArray() in the webhook; (array) cast does not expose keys like service_id.
	 *
	 * @covers Booking_System_Stripe_Webhook::handle_checkout_completed
	 */
	public function test_webhook_metadata_toArray_finds_service_id(): void {
		$plugin_dir = dirname( dirname( __DIR__ ) );
		$webhook_file = $plugin_dir . '/includes/api/class-stripe-webhook.php';
		$creator_file = $plugin_dir . '/includes/booking/class-booking-creator.php';
		if ( ! file_exists( $webhook_file ) || ! file_exists( $creator_file ) ) {
			$this->markTestSkipped( 'Stripe webhook or booking creator not available.' );
			return;
		}
		require_once $plugin_dir . '/includes/payment/class-stripe-config.php';
		require_once $webhook_file;
		require_once $creator_file;

		$ids = $this->insert_service_and_staff();
		$booking_date = wp_date( 'Y-m-d', strtotime( '+30 days' ), wp_timezone() );

		$session_id = 'cs_test_metadata_toArray_' . wp_generate_password( 12, false );
		delete_transient( 'stripe_webhook_' . $session_id );

		$session = \Stripe\Checkout\Session::constructFrom(
			array(
				'id'             => $session_id,
				'payment_status' => 'paid',
				'payment_intent' => 'pi_test_metadata_toArray',
				'amount_total'   => 5000,
				'currency'       => 'gbp',
				'metadata'       => array(
					'service_id'           => (string) $ids['service_id'],
					'staff_id'             => (string) $ids['staff_id'],
					'booking_date'         => $booking_date,
					'booking_time'         => '10:00:00',
					'customer_email'       => 'metadata-toarray@example.com',
					'customer_first_name'  => 'Meta',
					'customer_last_name'   => 'Data',
					'customer_phone'       => '07700900999',
				),
			)
		);

		$event  = (object) array(
			'data' => (object) array(
				'object' => $session,
			),
		);
		$handler = new Booking_System_Stripe_Webhook();
		$method  = new ReflectionMethod( Booking_System_Stripe_Webhook::class, 'handle_checkout_completed' );
		$method->setAccessible( true );
		$result = $method->invoke( $handler, $event );

		$this->assertTrue(
			! ( is_wp_error( $result ) && 'missing_metadata' === $result->get_error_code() && false !== strpos( $result->get_error_message(), 'service_id' ) ),
			'StripeObject metadata must resolve service_id (use metadata->toArray(), not (array) cast).'
		);

		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'bookings', array( 'stripe_session_id' => $session_id ), array( '%s' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_services', array( 'id' => $ids['service_id'] ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bookings_staff', array( 'id' => $ids['staff_id'] ), array( '%d' ) );
	}
}
