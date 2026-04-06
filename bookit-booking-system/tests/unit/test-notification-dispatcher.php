<?php
/**
 * Tests for notification dispatcher enqueue and email sender queue wiring.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test enqueue behavior through Booking_System_Email_Sender and queue processing outcomes.
 */
class Test_Notification_Dispatcher extends WP_UnitTestCase {

	/**
	 * Whether wp_mail was attempted (via pre_wp_mail short-circuit).
	 *
	 * @var int
	 */
	private $pre_wp_mail_calls = 0;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->ensure_queue_table_exists();
		$this->clear_queue_table();
		$this->pre_wp_mail_calls = 0;
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		$this->clear_queue_table();
		remove_all_filters( 'pre_wp_mail' );
		remove_all_filters( 'bookit_send_email' );
		parent::tearDown();
	}

	/**
	 * @covers Booking_System_Email_Sender::send_customer_confirmation
	 * @covers bookit_enqueue_email
	 * @covers Bookit_Email_Queue::insert
	 */
	public function test_send_customer_confirmation_enqueues_pending_row() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();

		$result = $email_sender->send_customer_confirmation( $booking );
		$this->assertTrue( $result );

		$row = $this->get_latest_queue_row_by_type( 'customer_confirmation' );
		$this->assertIsArray( $row );
		$this->assertSame( 'customer_confirmation', $row['email_type'] );
		$this->assertSame( 'pending', $row['status'] );
	}

	/**
	 * @covers Booking_System_Email_Sender::send_business_notification
	 * @covers bookit_enqueue_email
	 * @covers Bookit_Email_Queue::insert
	 */
	public function test_send_business_notification_enqueues_pending_row() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();

		$result = $email_sender->send_business_notification( $booking );
		$this->assertTrue( $result );

		$row = $this->get_latest_queue_row_by_type( 'business_notification' );
		$this->assertIsArray( $row );
		$this->assertSame( 'business_notification', $row['email_type'] );
		$this->assertSame( 'pending', $row['status'] );
	}

	/**
	 * @covers Booking_System_Email_Sender::send_customer_confirmation
	 */
	public function test_send_customer_confirmation_returns_true_on_success() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();

		$result = $email_sender->send_customer_confirmation( $booking );

		$this->assertTrue( $result );
		$this->assertFalse( is_wp_error( $result ) );
	}

	/**
	 * @covers Booking_System_Email_Sender::send_customer_confirmation
	 */
	public function test_send_customer_confirmation_does_not_call_wp_mail() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();

		add_filter(
			'pre_wp_mail',
			function () {
				$this->pre_wp_mail_calls++;
				return null;
			},
			10,
			2
		);

		$email_sender->send_customer_confirmation( $booking );

		$this->assertSame( 0, $this->pre_wp_mail_calls );
	}

	/**
	 * @covers Booking_System_Email_Sender::generate_customer_email
	 */
	public function test_confirmation_email_contains_cancel_link() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();
		$booking['magic_link_token'] = 'email-test-magic-token';

		$html = $email_sender->generate_customer_email( $booking );

		$this->assertStringContainsString( 'bookit-cancel', $html );
		$this->assertStringContainsString( 'bookit-reschedule', $html );
	}

	/**
	 * @covers Booking_System_Email_Sender::generate_customer_email
	 */
	public function test_customer_email_includes_add_to_calendar_link() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();
		$booking['magic_link_token'] = 'ical-email-test-token';

		$html = $email_sender->generate_customer_email( $booking );

		$has_ical_path = ( str_contains( $html, 'wizard/ical' ) || str_contains( $html, 'wizard%2Fical' ) );
		$this->assertTrue( $has_ical_path, 'Expected bookit/v1/wizard/ical in customer email (pretty or rest_route form).' );
		$this->assertStringContainsString( 'booking_id=123', $html );
		$this->assertStringContainsString( 'ical-email-test-token', $html );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::process_email_queue_item
	 * @covers Bookit_WP_Mail_Fallback_Provider::send
	 */
	public function test_process_item_marks_sent_on_provider_success() {
		$queue_id = (int) Bookit_Email_Queue::insert(
			array(
				'booking_id'      => 123,
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'test@example.com',
				'recipient_name'  => 'Test User',
				'subject'         => 'Subject',
				'html_body'       => '<p>Body</p>',
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			)
		);

		add_filter( 'pre_wp_mail', fn() => true, 10, 2 );

		Bookit_Notification_Dispatcher::process_email_queue_item( $queue_id );
		$row = Bookit_Email_Queue::get_row( $queue_id );

		$this->assertIsArray( $row );
		$this->assertSame( 'sent', $row['status'] );
	}

	/**
	 * @covers Bookit_Notification_Dispatcher::process_email_queue_item
	 * @covers Bookit_WP_Mail_Fallback_Provider::send
	 */
	public function test_process_item_marks_failed_after_max_attempts() {
		$queue_id = (int) Bookit_Email_Queue::insert(
			array(
				'booking_id'      => 123,
				'email_type'      => 'customer_confirmation',
				'recipient_email' => 'test@example.com',
				'recipient_name'  => 'Test User',
				'subject'         => 'Subject',
				'html_body'       => '<p>Body</p>',
				'max_attempts'    => 1,
				'scheduled_at'    => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			)
		);

		add_filter( 'pre_wp_mail', fn() => false, 10, 2 );

		Bookit_Notification_Dispatcher::process_email_queue_item( $queue_id );
		$row = Bookit_Email_Queue::get_row( $queue_id );

		$this->assertIsArray( $row );
		$this->assertSame( 'failed', $row['status'] );
	}

	/**
	 * @covers Booking_System_Email_Sender::send_customer_confirmation
	 * @covers bookit_enqueue_email
	 */
	public function test_bookit_send_email_filter_bypasses_queue() {
		$email_sender = new Booking_System_Email_Sender();
		$booking      = $this->build_minimal_booking();

		add_filter( 'bookit_send_email', fn() => false );

		$result = $email_sender->send_customer_confirmation( $booking );
		$this->assertTrue( $result );

		$row = $this->get_latest_queue_row_by_type( 'customer_confirmation' );
		$this->assertNull( $row );
	}

	/**
	 * Build a minimal booking array that can render both email templates.
	 *
	 * @return array<string,mixed>
	 */
	private function build_minimal_booking(): array {
		return array(
			'id'                 => 123,
			'customer_email'     => 'customer@test.com',
			'customer_first_name'=> 'Test',
			'customer_last_name' => 'Customer',
			'customer_name'      => 'Test Customer',
			'customer_phone'     => '07700900111',
			'service_name'       => 'Example Service',
			'booking_date'       => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
			'start_time'         => '10:00:00',
			'staff_name'         => 'Example Staff',
			'total_price'        => 50.00,
			'deposit_paid'       => 50.00,
			'balance_due'        => 0.00,
			'payment_method'     => 'stripe',
			'special_requests'   => '',
		);
	}

	/**
	 * Fetch the most recently inserted queue row for an email type.
	 *
	 * @param string $email_type Email type.
	 * @return array<string,mixed>|null
	 */
	private function get_latest_queue_row_by_type( string $email_type ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'bookit_email_queue';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE email_type = %s ORDER BY id DESC LIMIT 1",
				$email_type
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Ensure queue table exists for unit tests.
	 *
	 * @return void
	 */
	private function ensure_queue_table_exists(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookit_email_queue';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table_name} (
				id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				booking_id      BIGINT UNSIGNED NULL,
				email_type      VARCHAR(50) NOT NULL,
				recipient_email VARCHAR(255) NOT NULL,
				recipient_name  VARCHAR(255) NOT NULL DEFAULT '',
				subject         VARCHAR(500) NOT NULL DEFAULT '',
				html_body       LONGTEXT NOT NULL,
				params          LONGTEXT NULL,
				status          ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
				attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
				max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 3,
				scheduled_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				sent_at         DATETIME NULL,
				last_error      TEXT NULL,
				created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_status_scheduled (status, scheduled_at),
				KEY idx_booking_id (booking_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
		);
	}

	/**
	 * Clear queue rows created by these tests.
	 *
	 * @return void
	 */
	private function clear_queue_table(): void {
		global $wpdb;

		$queue_table = $wpdb->prefix . 'bookit_email_queue';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DELETE FROM {$queue_table}" );
	}
}

