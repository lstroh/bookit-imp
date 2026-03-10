<?php
/**
 * Payment Processor
 * Handles payment method selection and redirects to appropriate gateway.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/payment
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Payment processor class.
 */
class Booking_System_Payment_Processor {

	/**
	 * Whether to write messages to error_log (disabled during unit tests).
	 *
	 * @return bool
	 */
	private static function should_log() {
		return ! defined( 'WP_TESTS_TABLE_PREFIX' ) && function_exists( 'error_log' );
	}

	/**
	 * Constructor. Registers admin_post handlers.
	 */
	public function __construct() {
		add_action( 'admin_post_bookit_process_payment', array( $this, 'process_payment' ) );
		add_action( 'admin_post_nopriv_bookit_process_payment', array( $this, 'process_payment' ) );
	}

	/**
	 * Process payment form submission (Stripe, PayPal, pay on arrival).
	 *
	 * @return void
	 */
	public function process_payment() {
		if ( ! isset( $_POST['bookit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bookit_nonce'] ) ), 'bookit_booking_action' ) ) {
			wp_die( esc_html__( 'Security check failed', 'bookit-booking-system' ) );
		}

		$payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'stripe';

		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();
		$session_data = Bookit_Session_Manager::get_data();
		$selected_package_id = isset( $_POST['bookit_selected_package_id'] ) ? absint( wp_unslash( $_POST['bookit_selected_package_id'] ) ) : 0;

		if ( empty( $session_data ) ) {
			wp_safe_redirect( home_url( '/book?step=1&error=session_expired' ) );
			exit;
		}

		// Persist optional package selection for later payment-flow wiring.
		if ( $selected_package_id > 0 ) {
			$session_data['package_type_id'] = $selected_package_id;
			Bookit_Session_Manager::set_data( $session_data );
		}

		switch ( $payment_method ) {
			case 'stripe':
				$this->process_stripe_payment( $session_data );
				break;
			case 'paypal':
				wp_die( esc_html__( 'PayPal payment coming soon', 'bookit-booking-system' ) );
				break;
			case 'pay_on_arrival':
				$result = $this->process_pay_on_arrival( $session_data );

				if ( is_wp_error( $result ) ) {
					if ( self::should_log() ) {
						error_log( 'Pay on Arrival Error: ' . $result->get_error_message() );
					}

					// Redirect back to payment step with error code in URL (no session dependency).
					wp_safe_redirect( home_url( '/book?step=5&bookit_error=' . rawurlencode( $result->get_error_code() ) ) );
					exit;
				}

				wp_safe_redirect( $result['redirect_url'] );
				exit;
			default:
				wp_safe_redirect( home_url( '/book?step=5&error=invalid_payment_method' ) );
				exit;
		}
	}

	/**
	 * Create Stripe Checkout Session and redirect to Stripe.
	 *
	 * @param array<string, mixed> $session_data Wizard session data.
	 * @return void
	 */
	private function process_stripe_payment( $session_data ) {
		$stripe_checkout = new Booking_System_Stripe_Checkout();
		$session_id      = $stripe_checkout->create_checkout_session( $session_data );

		if ( is_wp_error( $session_id ) ) {
			if ( self::should_log() ) {
				error_log( 'Stripe Checkout Error: ' . $session_id->get_error_message() );
			}
			wp_safe_redirect( home_url( '/book?step=5&error=' . $session_id->get_error_code() ) );
			exit;
		}

		$stripe_config   = new Bookit_Stripe_Config();
		$publishable_key = $stripe_config->get_publishable_key();

		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<script src="https://js.stripe.com/v3/"></script>
		</head>
		<body>
			<p><?php esc_html_e( 'Redirecting to payment...', 'bookit-booking-system' ); ?></p>
			<script>
				var stripe = Stripe('<?php echo esc_js( $publishable_key ); ?>');
				stripe.redirectToCheckout({
					sessionId: '<?php echo esc_js( $session_id ); ?>'
				}).then(function(result) {
					if (result.error) {
						alert(result.error.message);
						window.location.href = '<?php echo esc_url( home_url( '/book?step=5&error=stripe_redirect' ) ); ?>';
					}
				});
			</script>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Process Pay on Arrival booking.
	 *
	 * Creates booking immediately without calling any external payment gateway.
	 * Sets deposit_paid = 0, balance_due = full price, status = pending_payment.
	 *
	 * @param array<string, mixed> $session_data Booking wizard session data.
	 * @return array{success: bool, booking_id: int, redirect_url: string}|WP_Error
	 */
	public function process_pay_on_arrival( $session_data ) {
		// Validate session data exists.
		if ( empty( $session_data ) ) {
			return new WP_Error( 'invalid_session', __( 'No booking data found', 'bookit-booking-system' ) );
		}

		// Map wizard session fields to booking creator fields.
		$booking_data = array(
			'service_id'          => isset( $session_data['service_id'] ) ? $session_data['service_id'] : '',
			'staff_id'            => isset( $session_data['staff_id'] ) ? $session_data['staff_id'] : '',
			'booking_date'        => isset( $session_data['date'] ) ? $session_data['date'] : '',
			'booking_time'        => isset( $session_data['time'] ) ? $session_data['time'] : '',
			'customer_first_name' => isset( $session_data['customer_first_name'] ) ? $session_data['customer_first_name'] : '',
			'customer_last_name'  => isset( $session_data['customer_last_name'] ) ? $session_data['customer_last_name'] : '',
			'customer_email'      => isset( $session_data['customer_email'] ) ? $session_data['customer_email'] : '',
			'customer_phone'      => isset( $session_data['customer_phone'] ) ? $session_data['customer_phone'] : '',
			'special_requests'    => isset( $session_data['customer_special_requests'] ) ? $session_data['customer_special_requests'] : '',
			'cooling_off_waiver' => isset( $session_data['cooling_off_waiver'] ) ? absint( $session_data['cooling_off_waiver'] ) : 0,
			'payment_method'      => 'pay_on_arrival',
			'payment_intent_id'   => null,
			'stripe_session_id'   => null,
			'amount_paid'         => 0,
		);

		// Allow extensions to modify wizard booking data before insertion.
		$booking_data = apply_filters( 'bookit_booking_data_before_insert', $booking_data );

		// Create booking using Booking Creator (handles customer, conflict check, DB insert).
		require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-creator.php';
		$booking_creator = new Booking_System_Booking_Creator();

		$booking_id = $booking_creator->create_booking( $booking_data );

		if ( is_wp_error( $booking_id ) ) {
			if ( self::should_log() ) {
				error_log( 'Pay on Arrival: Booking creation failed - ' . $booking_id->get_error_message() );
			}
			return $booking_id;
		}

		// Notify extensions after a public wizard booking is created.
		do_action( 'bookit_after_booking_created', (int) $booking_id, $booking_data );

		// Retrieve full booking details for confirmation emails.
		require_once BOOKIT_PLUGIN_DIR . 'includes/booking/class-booking-retriever.php';
		$booking_retriever = new Booking_System_Booking_Retriever();
		$booking           = $booking_retriever->get_booking_by_id( $booking_id );

		if ( $booking ) {
			// Send confirmation emails (best-effort; failures do not block booking).
			$email_sender_file = BOOKIT_PLUGIN_DIR . 'includes/email/class-email-sender.php';
			if ( file_exists( $email_sender_file ) ) {
				require_once $email_sender_file;
				$email_sender = new Booking_System_Email_Sender();

				$customer_result = $email_sender->send_customer_confirmation( $booking );
				if ( is_wp_error( $customer_result ) && self::should_log() ) {
					error_log( 'Pay on Arrival: Failed to send customer email - ' . $customer_result->get_error_message() );
				}

				$business_result = $email_sender->send_business_notification( $booking );
				if ( is_wp_error( $business_result ) && self::should_log() ) {
					error_log( 'Pay on Arrival: Failed to send business email - ' . $business_result->get_error_message() );
				}
			}
		} else {
			if ( self::should_log() ) {
				error_log( 'Pay on Arrival: Could not retrieve booking #' . $booking_id . ' for emails' );
			}
		}

		// Clear booking wizard session.
		if ( class_exists( 'Bookit_Session_Manager' ) ) {
			Bookit_Session_Manager::complete_booking();
		} else {
			$booking_retriever->clear_booking_session();
		}

		if ( self::should_log() ) {
			error_log(
				sprintf(
					'Pay on Arrival: Booking #%d created successfully (customer: %s, date: %s)',
					$booking_id,
					$booking_data['customer_email'],
					$booking_data['booking_date']
				)
			);
		}

		// Return booking info for redirect.
		return array(
			'success'      => true,
			'booking_id'   => $booking_id,
			'redirect_url' => home_url( '/booking-confirmed?booking_id=' . $booking_id ),
		);
	}
}
