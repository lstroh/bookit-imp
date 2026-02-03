<?php
/**
 * Stripe Checkout Session Handler
 * Creates Stripe Checkout Sessions for booking payments.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/payment
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stripe Checkout Session handler class.
 */
class Booking_System_Stripe_Checkout {

	/**
	 * Create Stripe Checkout Session
	 *
	 * @param array<string, mixed> $session_data Booking wizard session data.
	 * @return string|\WP_Error Stripe session ID or error.
	 */
	public function create_checkout_session( $session_data ) {
		$validation = $this->validate_session_data( $session_data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$service = $this->get_service( isset( $session_data['service_id'] ) ? (int) $session_data['service_id'] : 0 );
		if ( ! $service ) {
			return new WP_Error( 'missing_service', __( 'Service not found', 'bookit-booking-system' ) );
		}

		$staff = $this->get_staff( isset( $session_data['staff_id'] ) ? (int) $session_data['staff_id'] : 0 );
		if ( ! $staff ) {
			return new WP_Error( 'missing_staff', __( 'Staff member not found', 'bookit-booking-system' ) );
		}

		$deposit_amount = $this->calculate_deposit( $service );
		if ( is_wp_error( $deposit_amount ) ) {
			return $deposit_amount;
		}
		if ( $deposit_amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Deposit amount must be greater than zero', 'bookit-booking-system' ) );
		}

		$stripe_config = new Bookit_Stripe_Config();
		$secret_key    = $stripe_config->get_secret_key();
		if ( empty( $secret_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'Stripe API key not configured', 'bookit-booking-system' ) );
		}

		if ( apply_filters( 'bookit_stripe_api_mode', 'live' ) === 'mock' ) {
			$mock_result = apply_filters( 'bookit_mock_stripe_session', $session_data );
			if ( is_object( $mock_result ) && isset( $mock_result->id ) ) {
				return $mock_result->id;
			}
			return is_string( $mock_result ) ? $mock_result : new WP_Error( 'mock_error', __( 'Mock session failed', 'bookit-booking-system' ) );
		}

		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			$autoload = dirname( dirname( __DIR__ ) ) . '/vendor/autoload.php';
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
			}
		}
		\Stripe\Stripe::setApiKey( $secret_key );

		$params = $this->build_session_params( $session_data, $service, $staff, $deposit_amount );

		try {
			$checkout_session = \Stripe\Checkout\Session::create( $params );
			return $checkout_session->id;
		} catch ( \Exception $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Stripe Checkout Session Error: ' . $e->getMessage() );
			}
			return new WP_Error( 'stripe_error', __( 'Unable to create checkout session: ', 'bookit-booking-system' ) . $e->getMessage() );
		}
	}

	/**
	 * Validate session data (required fields and email).
	 *
	 * @param array<string, mixed> $session_data Session data.
	 * @return true|\WP_Error
	 */
	private function validate_session_data( $session_data ) {
		$required_fields = array(
			'service_id',
			'staff_id',
			'date',
			'time',
			'customer_email',
			'customer_first_name',
			'customer_last_name',
		);

		foreach ( $required_fields as $field ) {
			if ( ! isset( $session_data[ $field ] ) || (string) $session_data[ $field ] === '' ) {
				if ( $field === 'service_id' ) {
					return new WP_Error( 'missing_service', __( 'Service not found', 'bookit-booking-system' ) );
				}
				if ( $field === 'staff_id' ) {
					return new WP_Error( 'missing_staff', __( 'Staff member not found', 'bookit-booking-system' ) );
				}
				return new WP_Error( 'missing_field', sprintf( __( 'Missing required field: %s', 'bookit-booking-system' ), $field ) );
			}
		}

		if ( ! is_email( $session_data['customer_email'] ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address', 'bookit-booking-system' ) );
		}

		return true;
	}

	/**
	 * Get service from database.
	 *
	 * @param int $service_id Service ID.
	 * @return array<string, mixed>|false Service row or false.
	 */
	private function get_service( $service_id ) {
		if ( $service_id <= 0 ) {
			return false;
		}
		global $wpdb;
		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
				$service_id
			),
			ARRAY_A
		);
		return $service ?: false;
	}

	/**
	 * Get staff member from database.
	 *
	 * @param int $staff_id Staff ID.
	 * @return array<string, mixed>|false Staff row or false.
	 */
	private function get_staff( $staff_id ) {
		if ( $staff_id <= 0 ) {
			return false;
		}
		global $wpdb;
		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);
		return $staff ?: false;
	}

	/**
	 * Calculate deposit amount based on service settings
	 *
	 * @param array $service Service data from database
	 * @return float|\WP_Error Deposit amount in pounds or error
	 */
	public function calculate_deposit( $service ) {
		// Validate service price
		$price = floatval( $service['price'] ?? 0 );

		if ( $price <= 0 ) {
			return new WP_Error( 'invalid_price', 'Service price must be greater than zero' );
		}

		$deposit_type  = $service['deposit_type'] ?? null;
		$deposit_amount = $service['deposit_amount'] ?? null;

		// If no deposit configuration, default to full payment
		if ( empty( $deposit_type ) || is_null( $deposit_amount ) ) {
			return $price;
		}

		switch ( $deposit_type ) {
			case 'percentage':
				$percentage = floatval( $deposit_amount );

				// Validate percentage range (0-100)
				if ( $percentage < 0 || $percentage > 100 ) {
					error_log( "Invalid deposit percentage: {$percentage}. Using 100%." );
					$percentage = 100;
				}

				$deposit = ( $price * $percentage ) / 100;

				// Round to 2 decimal places
				return round( $deposit, 2 );

			case 'fixed':
				$fixed = floatval( $deposit_amount );

				// Validate fixed amount is positive
				if ( $fixed < 0 ) {
					error_log( "Invalid fixed deposit: {$fixed}. Using full price." );
					return $price;
				}

				// Don't exceed service price
				$deposit = min( $fixed, $price );

				// Round to 2 decimal places
				return round( $deposit, 2 );

			default:
				// Unknown deposit type - log and use full payment
				error_log( "Unknown deposit type: {$deposit_type}. Using full payment." );
				return $price;
		}
	}

	/**
	 * Build Stripe Checkout Session parameters.
	 *
	 * @param array<string, mixed> $session_data  Session data.
	 * @param array<string, mixed> $service       Service row.
	 * @param array<string, mixed> $staff         Staff row.
	 * @param float                $deposit_amount Deposit in pounds.
	 * @return array<string, mixed> Stripe session parameters.
	 */
	private function build_session_params( $session_data, $service, $staff, $deposit_amount ) {
		$date_formatted = ! empty( $session_data['date'] ) ? gmdate( 'd/m/Y', strtotime( $session_data['date'] ) ) : '';
		$time_formatted = ! empty( $session_data['time'] ) ? gmdate( 'g:i A', strtotime( $session_data['time'] ) ) : ( $session_data['time'] ?? '' );
		$staff_first   = $staff['first_name'] ?? '';
		$staff_last    = $staff['last_name'] ?? '';
		$service_name  = $service['name'] ?? '';

		$description = sprintf(
			/* translators: 1: staff first name, 2: staff last name, 3: date, 4: time */
			__( 'with %1$s %2$s on %3$s at %4$s', 'bookit-booking-system' ),
			$staff_first,
			$staff_last,
			$date_formatted,
			$time_formatted
		);

		$metadata = array(
			'booking_temp_id'       => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : 'temp-' . uniqid(),
			'service_id'            => (string) $session_data['service_id'],
			'staff_id'              => (string) $session_data['staff_id'],
			'booking_date'          => $session_data['date'],
			'booking_time'          => $session_data['time'],
			'customer_first_name'   => $session_data['customer_first_name'],
			'customer_last_name'    => $session_data['customer_last_name'],
			'customer_email'        => $session_data['customer_email'],
			'customer_phone'        => isset( $session_data['customer_phone'] ) ? $session_data['customer_phone'] : '',
		);
		if ( ! empty( $session_data['customer_special_requests'] ) ) {
			$metadata['special_requests'] = substr( $session_data['customer_special_requests'], 0, 500 );
		}

		$unit_amount_pence = (int) round( $deposit_amount * 100 );

		$line_items = array(
			array(
				'price_data' => array(
					'currency'     => 'gbp',
					'product_data' => array(
						'name'        => $service_name,
						'description' => $description,
					),
					'unit_amount'  => $unit_amount_pence,
				),
				'quantity'   => 1,
			),
		);

		return array(
			'payment_method_types' => array( 'card' ),
			'line_items'          => $line_items,
			'mode'                => 'payment',
			'success_url'         => home_url( '/booking-confirmed?session_id={CHECKOUT_SESSION_ID}' ),
			'cancel_url'          => home_url( '/book?step=5&cancelled=1' ),
			'customer_email'     => $session_data['customer_email'],
			'metadata'            => $metadata,
		);
	}
}
