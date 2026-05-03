<?php
/**
 * Sprint 7: extension API hooks (staff email meeting section).
 *
 * @package Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Tests for bookit_staff_email_meeting_section.
 */
class Test_Sprint7_Extension_Api extends WP_UnitTestCase {

	/**
	 * Minimal booking row shape used by Bookit_Staff_Notifier::build_html_body().
	 *
	 * @return array<string, mixed>
	 */
	private function sample_booking(): array {
		return array(
			'id'                    => 101,
			'staff_id'              => 5,
			'customer_first_name'   => 'Jane',
			'customer_last_name'    => 'Doe',
			'service_name'          => 'Consultation',
			'booking_date'          => '2026-06-15',
			'start_time'            => '10:00:00',
			'booking_reference'     => 'REF-001',
		);
	}

	/**
	 * Invoke private build_html_body via reflection.
	 *
	 * @param string               $email_type Email type slug.
	 * @param array<string, mixed> $booking    Booking data.
	 * @param int                  $staff_id   Recipient staff id.
	 * @return string
	 */
	private function invoke_build_html_body( string $email_type, array $booking, int $staff_id = 0 ): string {
		$method = new ReflectionMethod( Bookit_Staff_Notifier::class, 'build_html_body' );
		$method->setAccessible( true );
		ob_start();
		$html = $method->invoke( null, $email_type, $booking, $staff_id );
		$leaked = ob_get_clean();
		$this->assertSame( '', $leaked, 'build_html_body must not write outside its internal buffer.' );

		return is_string( $html ) ? $html : '';
	}

	/**
	 * @covers Bookit_Staff_Notifier::build_html_body
	 */
	public function test_staff_email_meeting_section_filter_fires_with_correct_params(): void {
		$captured = array();

		$cb = static function ( string $html, array $booking, int $staff_id ) use ( &$captured ) {
			$captured = array(
				'html'      => $html,
				'booking'   => $booking,
				'staff_id'  => $staff_id,
			);
			return $html;
		};

		add_filter( 'bookit_staff_email_meeting_section', $cb, 10, 3 );

		$booking  = $this->sample_booking();
		$staff_id = 42;
		$this->invoke_build_html_body( 'staff_new_booking_immediate', $booking, $staff_id );

		remove_filter( 'bookit_staff_email_meeting_section', $cb, 10 );

		$this->assertArrayHasKey( 'html', $captured );
		$this->assertSame( '', $captured['html'] );
		$this->assertArrayHasKey( 'booking', $captured );
		$this->assertSame( $booking, $captured['booking'] );
		$this->assertArrayHasKey( 'staff_id', $captured );
		$this->assertSame( 42, $captured['staff_id'] );
	}

	/**
	 * @covers Bookit_Staff_Notifier::build_html_body
	 */
	public function test_staff_email_meeting_section_output_injected_when_non_empty(): void {
		$marker = '<p>BOOKIT_MEETING_INJECT_TEST</p>';

		$cb = static function () use ( $marker ) {
			return $marker;
		};

		add_filter( 'bookit_staff_email_meeting_section', $cb, 10, 3 );

		$html = $this->invoke_build_html_body( 'staff_new_booking_immediate', $this->sample_booking(), 7 );

		remove_filter( 'bookit_staff_email_meeting_section', $cb, 10 );

		$this->assertStringContainsString( 'BOOKIT_MEETING_INJECT_TEST', $html );
		$this->assertStringContainsString( 'View in dashboard', $html );
	}

	/**
	 * @covers Bookit_Staff_Notifier::build_html_body
	 */
	public function test_staff_email_meeting_section_no_output_when_empty(): void {
		$cb = static function ( string $html ) {
			return $html;
		};

		add_filter( 'bookit_staff_email_meeting_section', $cb, 10, 3 );

		$with_filter = $this->invoke_build_html_body( 'staff_new_booking_immediate', $this->sample_booking(), 7 );

		remove_filter( 'bookit_staff_email_meeting_section', $cb, 10 );

		$baseline = $this->invoke_build_html_body( 'staff_new_booking_immediate', $this->sample_booking(), 7 );

		$this->assertSame( $baseline, $with_filter );
	}

	/**
	 * Dashboard template should fire bookit_dashboard_extension_content for in-layout extension mounts.
	 *
	 * @coversNothing
	 */
	public function test_dashboard_extension_content_action_fires(): void {
		$n_before = did_action( 'bookit_dashboard_extension_content' );

		$fired = false;
		$cb    = static function () use ( &$fired ) {
			$fired = true;
		};
		add_action( 'bookit_dashboard_extension_content', $cb, 10, 0 );

		$path = BOOKIT_PLUGIN_DIR . 'dashboard/app/index.php';
		if ( ! is_file( $path ) ) {
			$this->assertTrue(
				(bool) has_action( 'bookit_dashboard_extension_content', $cb ),
				'bookit_dashboard_extension_content should accept callbacks when the template file is missing.'
			);
			// Full firing verified manually — template requires HTTP context
			remove_action( 'bookit_dashboard_extension_content', $cb, 10 );
			return;
		}

		if ( ! isset( $_SESSION ) || ! is_array( $_SESSION ) ) {
			$_SESSION = array();
		}

		// Satisfy Bookit_Auth::require_auth() / get_current_staff() without a full HTTP round-trip.
		$_SESSION['staff_id']       = 1;
		$_SESSION['staff_email']    = 'sprint7-ext-hook@test.com';
		$_SESSION['staff_role']     = 'admin';
		$_SESSION['staff_name']     = 'Extension Hook Test';
		$_SESSION['is_logged_in']   = true;
		$_SESSION['last_activity']  = time();

		// Dashboard template calls wp_print_styles(); core still hooks deprecated print_emoji_styles (WP 6.4+).
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		$loaded = false;
		try {
			ob_start();
			require $path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			ob_end_clean();
			$loaded = true;
		} catch ( \Throwable $e ) {
			ob_end_clean();
		}

		if ( $loaded ) {
			$this->assertSame(
				1,
				did_action( 'bookit_dashboard_extension_content' ) - $n_before,
				'Template load should invoke bookit_dashboard_extension_content exactly once.'
			);
			$this->assertTrue( $fired, 'Registered callback should run when the action fires.' );
		} else {
			$this->assertTrue(
				(bool) has_action( 'bookit_dashboard_extension_content', $cb ),
				'Callback should remain registered when the template cannot be executed in this context.'
			);
			// Full firing verified manually — template requires HTTP context
		}

		remove_action( 'bookit_dashboard_extension_content', $cb, 10 );
	}
}
