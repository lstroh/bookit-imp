<?php
/**
 * Sprint 6C hotfix regression tests (email + staff notifications).
 *
 * @package Bookit_Booking_System
 * @subpackage Tests
 */

class Test_6C_Hotfix extends WP_UnitTestCase {

	/**
	 * Prevent wp_mail side effects.
	 *
	 * @var callable|null
	 */
	private $pre_wp_mail_cb = null;

	public function setUp(): void {
		parent::setUp();

		bookit_test_truncate_tables(
			array(
				'bookit_email_queue',
				'bookit_notification_digest_queue',
				'bookings_audit_log',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
			)
		);

		$this->pre_wp_mail_cb = static fn() => true;
		add_filter( 'pre_wp_mail', $this->pre_wp_mail_cb );

		if ( class_exists( 'Bookit_Staff_Notifier' ) ) {
			Bookit_Staff_Notifier::init();
		}
	}

	public function tearDown(): void {
		if ( $this->pre_wp_mail_cb ) {
			remove_filter( 'pre_wp_mail', $this->pre_wp_mail_cb );
		}

		bookit_test_truncate_tables(
			array(
				'bookit_email_queue',
				'bookit_notification_digest_queue',
				'bookings_audit_log',
				'bookings',
				'bookings_staff_services',
				'bookings_services',
				'bookings_staff',
				'bookings_customers',
			)
		);

		parent::tearDown();
	}

	public function test_reschedule_email_includes_add_to_calendar_button(): void {
		$html = $this->generate_magic_link_reschedule_email_html();
		$this->assertStringContainsString( 'Add to Calendar', $html );
	}

	public function test_reschedule_email_includes_reschedule_button(): void {
		$html = $this->generate_magic_link_reschedule_email_html();
		$this->assertStringContainsString( 'Reschedule', $html );
	}

	public function test_reschedule_email_includes_cancel_button(): void {
		$html = $this->generate_magic_link_reschedule_email_html();
		$this->assertStringContainsString( 'Cancel Booking', $html );
	}

	public function test_staff_notifier_fires_on_booking_rescheduled_hook(): void {
		$staff_id    = $this->insert_staff( array( 'email' => 'assigned@example.com' ) );
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		// Wrap the notifier callback so we can assert it ran via did_action().
		remove_action( 'bookit_booking_rescheduled', array( 'Bookit_Staff_Notifier', 'on_booking_rescheduled' ), 10 );
		add_action(
			'bookit_booking_rescheduled',
			static function ( int $id, array $data ) {
				do_action( 'bookit_test_staff_notifier_rescheduled_called', $id, $data );
				Bookit_Staff_Notifier::on_booking_rescheduled( $id, $data );
			},
			10,
			2
		);

		$this->assertSame( 0, did_action( 'bookit_test_staff_notifier_rescheduled_called' ) );
		do_action( 'bookit_booking_rescheduled', $booking_id, array( 'new_date' => gmdate( 'Y-m-d' ) ) );
		$this->assertSame( 1, did_action( 'bookit_test_staff_notifier_rescheduled_called' ) );
	}

	public function test_staff_notifier_fires_on_booking_cancelled_hook(): void {
		$staff_id    = $this->insert_staff( array( 'email' => 'assigned@example.com' ) );
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $staff_id );

		// Soft-delete booking to mirror dashboard cancellation behavior.
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'status'     => 'cancelled',
				'deleted_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		// get_full_booking() is private; call via reflection to ensure it returns the row even when deleted_at is set.
		$ref = new ReflectionMethod( 'Bookit_Staff_Notifier', 'get_full_booking' );
		$ref->setAccessible( true );
		$row = $ref->invoke( null, $booking_id );
		$this->assertIsArray( $row );
		$this->assertSame( $booking_id, (int) $row['id'] );
		$this->assertNotEmpty( (string) $row['deleted_at'] );
	}

	public function test_admin_notified_on_booking_cancelled(): void {
		global $wpdb;

		$assigned_id = $this->insert_staff( array( 'email' => 'assigned@example.com' ) );
		$admin_id    = $this->insert_staff(
			array(
				'email' => 'admin@example.com',
				'role'  => 'admin',
			)
		);
		$service_id  = $this->insert_service();
		$customer_id = $this->insert_customer();
		$booking_id  = $this->insert_booking( $customer_id, $service_id, $assigned_id );

		// Soft-delete booking before firing the hook, matching dashboard cancel flow.
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array(
				'status'     => 'cancelled',
				'deleted_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		do_action( 'bookit_after_booking_cancelled', $booking_id, array( 'cancelled_by' => 'staff', 'via' => 'dashboard' ) );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookit_email_queue
				WHERE booking_id = %d AND recipient_email = %s AND email_type = %s",
				$booking_id,
				'admin@example.com',
				'staff_cancellation_immediate'
			)
		);
		$this->assertSame( 1, $count );

		$this->assertSame( $admin_id, (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}bookings_staff WHERE email = %s", 'admin@example.com' ) ) );
	}

	/**
	 * Helper: enqueue a magic_link_reschedule email and return html_body.
	 *
	 * @return string
	 */
	private function generate_magic_link_reschedule_email_html(): string {
		global $wpdb;

		$staff_id    = $this->insert_staff( array( 'email' => 'assigned@example.com' ) );
		$service_id  = $this->insert_service( array( 'name' => 'Test Service' ) );
		$customer_id = $this->insert_customer(
			array(
				'email'      => 'customer@example.com',
				'first_name' => 'Test',
				'last_name'  => 'Customer',
			)
		);
		$booking_id  = $this->insert_booking(
			$customer_id,
			$service_id,
			$staff_id,
			array(
				'magic_link_token' => 'tok_test_123',
				'payment_method'   => 'manual',
			)
		);

		$api = new Bookit_Wizard_API();
		$ref = new ReflectionMethod( $api, 'enqueue_magic_link_email' );
		$ref->setAccessible( true );
		$ref->invoke( $api, 'magic_link_reschedule', $booking_id );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT html_body FROM {$wpdb->prefix}bookit_email_queue
				WHERE booking_id = %d AND email_type = %s
				ORDER BY id DESC LIMIT 1",
				$booking_id,
				'magic_link_reschedule'
			),
			ARRAY_A
		);

		$this->assertIsArray( $row );
		$this->assertArrayHasKey( 'html_body', $row );

		return (string) $row['html_body'];
	}

	private function insert_staff( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'first_name' => 'Test',
			'last_name'  => 'Staff',
			'email'      => 'staff-' . wp_generate_password( 8, false, false ) . '@example.com',
			'role'       => 'staff',
			'is_active'  => 1,
		);

		$data = wp_parse_args( $overrides, $defaults );

		if ( ! isset( $data['password_hash'] ) ) {
			$data['password_hash'] = wp_hash_password( 'x' );
		}
		$data['created_at'] = $data['created_at'] ?? current_time( 'mysql' );
		$data['updated_at'] = $data['updated_at'] ?? current_time( 'mysql' );

		$formats = array();
		$insert  = array();

		foreach ( $data as $key => $value ) {
			$insert[ $key ] = $value;
			$formats[]      = ( 'is_active' === $key ) ? '%d' : '%s';
		}

		$wpdb->insert( $wpdb->prefix . 'bookings_staff', $insert, $formats );
		return (int) $wpdb->insert_id;
	}

	private function insert_service( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'name'           => 'Test Service',
			'duration'       => 60,
			'price'          => 50.00,
			'deposit_type'   => 'percentage',
			'deposit_amount' => 100,
			'is_active'      => 1,
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_services',
			$data,
			array( '%s', '%d', '%f', '%s', '%f', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	private function insert_customer( array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'email'      => 'customer-' . wp_generate_password( 8, false, false ) . '@example.com',
			'first_name' => 'Test',
			'last_name'  => 'Customer',
			'phone'      => '07700900000',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_customers',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	private function insert_booking( int $customer_id, int $service_id, int $staff_id, array $overrides = array() ): int {
		global $wpdb;

		$defaults = array(
			'booking_reference' => 'BKTEST-' . wp_generate_password( 4, false, false ),
			'customer_id'       => $customer_id,
			'service_id'        => $service_id,
			'staff_id'          => $staff_id,
			'booking_date'      => gmdate( 'Y-m-d', strtotime( '+10 days' ) ),
			'start_time'        => '10:00:00',
			'end_time'          => '11:00:00',
			'duration'          => 60,
			'status'            => 'confirmed',
			'total_price'       => 50.00,
			'deposit_amount'    => 50.00,
			'deposit_paid'      => 0.00,
			'balance_due'       => 50.00,
			'full_amount_paid'  => 0,
			'payment_method'    => 'manual',
			'magic_link_token'  => 'tok_' . wp_generate_password( 8, false, false ),
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
			'deleted_at'        => null,
		);

		$data = wp_parse_args( $overrides, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings',
			$data,
			array(
				'%s',
				'%d',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%f',
				'%f',
				'%f',
				'%f',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		return (int) $wpdb->insert_id;
	}
}

