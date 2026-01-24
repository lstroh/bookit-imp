<?php
/**
 * Dashboard login page.
 *
 * @package Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BOOKING_SYSTEM_PATH . 'includes/class-booking-session.php';
require_once BOOKING_SYSTEM_PATH . 'includes/class-booking-auth.php';

// If already logged in, redirect to dashboard.
if ( Booking_Auth::is_logged_in() ) {
	wp_redirect( home_url( '/booking-dashboard/home/' ) );
	exit;
}

$error_message = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['booking_login_submit'] ) ) {
	// Verify nonce.
	if (
		! isset( $_POST['booking_login_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['booking_login_nonce'] ) ), 'booking_login' )
	) {
		$error_message = 'Security check failed. Please try again.';
	} else {
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( empty( $email ) || empty( $password ) ) {
			$error_message = 'Please enter both email and password.';
		} else {
			$staff = Booking_Auth::authenticate( $email, $password );

			if ( $staff ) {
				Booking_Auth::login( $staff );

				$redirect_to = isset( $_GET['redirect_to'] ) ? (string) wp_unslash( $_GET['redirect_to'] ) : '';
				if ( empty( $redirect_to ) ) {
					$redirect_to = home_url( '/booking-dashboard/home/' );
				}

				wp_redirect( $redirect_to );
				exit;
			} else {
				$error_message = 'Invalid email or password.';
			}
		}
	}
}

get_header();
?>

<div class="booking-dashboard-login-wrapper">
	<div class="booking-login-container">
		<div class="booking-login-header">
			<h1><?php echo esc_html__( 'Booking System', 'booking-system' ); ?></h1>
			<p><?php echo esc_html__( 'Staff Dashboard Login', 'booking-system' ); ?></p>
		</div>

		<?php if ( ! empty( $error_message ) ) : ?>
			<div class="booking-login-error">
				<?php echo esc_html( $error_message ); ?>
			</div>
		<?php endif; ?>

		<form method="post" action="" class="booking-login-form">
			<?php wp_nonce_field( 'booking_login', 'booking_login_nonce' ); ?>

			<div class="booking-form-group">
				<label for="email"><?php echo esc_html__( 'Email Address', 'booking-system' ); ?></label>
				<input
					type="email"
					id="email"
					name="email"
					required
					autofocus
					value="<?php echo isset( $_POST['email'] ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>"
				/>
			</div>

			<div class="booking-form-group">
				<label for="password"><?php echo esc_html__( 'Password', 'booking-system' ); ?></label>
				<input
					type="password"
					id="password"
					name="password"
					required
				/>
			</div>

			<div class="booking-form-group">
				<button type="submit" name="booking_login_submit" class="booking-login-button">
					<?php echo esc_html__( 'Log In', 'booking-system' ); ?>
				</button>
			</div>
		</form>

		<div class="booking-login-footer">
			<p>
				<a href="<?php echo esc_url( home_url( '/booking-dashboard/forgot-password/' ) ); ?>">
					<?php echo esc_html__( 'Forgot password?', 'booking-system' ); ?>
				</a>
			</p>
			<p class="booking-login-help">
				<?php echo esc_html__( 'Need help? Contact your administrator.', 'booking-system' ); ?>
			</p>
		</div>
	</div>
</div>

<link rel="stylesheet" href="<?php echo esc_url( BOOKING_SYSTEM_URL . 'dashboard/css/dashboard-auth.css' ); ?>">

<?php
get_footer();

