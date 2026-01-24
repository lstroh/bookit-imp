<?php
/**
 * Dashboard logout handler.
 *
 * @package Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BOOKING_SYSTEM_PATH . 'includes/class-booking-session.php';
require_once BOOKING_SYSTEM_PATH . 'includes/class-booking-auth.php';

Booking_Auth::logout();

wp_redirect( home_url( '/booking-dashboard/?logged_out=1' ) );
exit;

