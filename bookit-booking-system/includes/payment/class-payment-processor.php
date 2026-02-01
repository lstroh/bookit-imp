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

		if ( empty( $session_data ) ) {
			wp_safe_redirect( home_url( '/book?step=1&error=session_expired' ) );
			exit;
		}

		switch ( $payment_method ) {
			case 'stripe':
				$this->process_stripe_payment( $session_data );
				break;
			case 'paypal':
				wp_die( esc_html__( 'PayPal payment coming soon', 'bookit-booking-system' ) );
				break;
			case 'pay_on_arrival':
				wp_die( esc_html__( 'Pay on arrival coming soon', 'bookit-booking-system' ) );
				break;
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
			if ( function_exists( 'error_log' ) ) {
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
}
