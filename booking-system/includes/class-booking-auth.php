<?php
/**
 * Authentication for dashboard users.
 *
 * @package    Booking_System
 * @subpackage Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Authentication class.
 */
class Booking_Auth {

	/**
	 * Authenticate user credentials.
	 *
	 * @param string $email    User email.
	 * @param string $password User password (plain text).
	 * @return array|false Staff data array on success, false on failure.
	 */
	public static function authenticate( $email, $password ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bookings_staff';

		$email = sanitize_email( $email );

		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE email = %s AND is_active = 1 AND deleted_at IS NULL",
				$email
			),
			ARRAY_A
		);

		if ( ! $staff ) {
			Booking_Logger::warning(
				'Login failed: Email not found',
				array(
					'email' => $email,
				)
			);
			return false;
		}

		if ( empty( $staff['password_hash'] ) || ! password_verify( $password, $staff['password_hash'] ) ) {
			Booking_Logger::warning(
				'Login failed: Invalid password',
				array(
					'email' => $email,
				)
			);
			return false;
		}

		Booking_Logger::info(
			'User login successful',
			array(
				'staff_id' => $staff['id'],
				'email'    => $email,
				'role'     => $staff['role'],
			)
		);
		return $staff;
	}

	/**
	 * Log in user (create session).
	 *
	 * @param array $staff Staff data from database.
	 * @return void
	 */
	public static function login( $staff ) {
		Booking_Session::init();
		Booking_Session::regenerate(); // Prevent session fixation.

		Booking_Session::set( 'staff_id', (int) $staff['id'] );
		Booking_Session::set( 'staff_email', (string) $staff['email'] );
		Booking_Session::set( 'staff_role', (string) $staff['role'] );
		Booking_Session::set(
			'staff_name',
			trim( (string) $staff['first_name'] . ' ' . (string) $staff['last_name'] )
		);
		Booking_Session::set( 'is_logged_in', true );
		Booking_Session::update_activity();
	}

	/**
	 * Log out user (destroy session).
	 *
	 * @return void
	 */
	public static function logout() {
		$staff_id = Booking_Session::get( 'staff_id', 'unknown' );
		Booking_Session::destroy();

		Booking_Logger::info(
			'User logged out',
			array(
				'staff_id' => $staff_id,
			)
		);
	}

	/**
	 * Check if user is logged in.
	 *
	 * @return bool True if logged in.
	 */
	public static function is_logged_in() {
		Booking_Session::init();

		if ( Booking_Session::is_expired() ) {
			self::logout();
			return false;
		}

		$is_logged_in = (bool) Booking_Session::get( 'is_logged_in', false );

		if ( $is_logged_in ) {
			Booking_Session::update_activity();
		}

		return $is_logged_in;
	}

	/**
	 * Get current logged-in staff data.
	 *
	 * @return array|null Staff data or null if not logged in.
	 */
	public static function get_current_staff() {
		if ( ! self::is_logged_in() ) {
			return null;
		}

		return array(
			'id'    => Booking_Session::get( 'staff_id' ),
			'email' => Booking_Session::get( 'staff_email' ),
			'role'  => Booking_Session::get( 'staff_role' ),
			'name'  => Booking_Session::get( 'staff_name' ),
		);
	}

	/**
	 * Check if current user is admin.
	 *
	 * @return bool True if admin role.
	 */
	public static function is_admin() {
		if ( ! self::is_logged_in() ) {
			return false;
		}

		return Booking_Session::get( 'staff_role' ) === 'admin';
	}

	/**
	 * Require authentication (redirect to login if not logged in).
	 *
	 * @param string $redirect_to URL to redirect to after login.
	 * @return void
	 */
	public static function require_auth( $redirect_to = '' ) {
		if ( ! self::is_logged_in() ) {
			if ( empty( $redirect_to ) && isset( $_SERVER['REQUEST_URI'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$redirect_to = wp_unslash( $_SERVER['REQUEST_URI'] );
			}

			$login_url = home_url( '/booking-dashboard/?redirect_to=' . rawurlencode( $redirect_to ) );
			wp_redirect( $login_url );
			exit;
		}
	}

	/**
	 * Hash password (for creating staff accounts).
	 *
	 * @param string $password Plain text password.
	 * @return string Hashed password.
	 */
	public static function hash_password( $password ) {
		return password_hash( $password, PASSWORD_BCRYPT, array( 'cost' => 12 ) );
	}
}

