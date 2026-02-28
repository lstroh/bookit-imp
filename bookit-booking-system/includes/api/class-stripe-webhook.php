<?php
/**
 * Stripe Webhook Handler
 * Receives and processes Stripe webhook events
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stripe Webhook Handler class.
 */
class Booking_System_Stripe_Webhook {

	/**
	 * Whether to write messages to error_log (disabled during unit tests).
	 *
	 * @return bool
	 */
	private static function should_log() {
		return ! defined( 'WP_TESTS_TABLE_PREFIX' ) && function_exists( 'error_log' );
	}

	/**
	 * Constructor - Register REST API routes
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register webhook REST API endpoint
	 */
	public function register_routes() {
		register_rest_route(
			'bookit/v1',
			'/stripe/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Webhook is public, verified by signature.
			)
		);
	}

	/**
	 * Handle incoming webhook request
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( $request ) {
		$payload   = $request->get_body();
		$signature = $request->get_header( 'Stripe-Signature' );
		if ( empty( $signature ) ) {
			$signature = $request->get_header( 'stripe_signature' );
		}

		if ( empty( $signature ) ) {
			if ( self::should_log() ) {
				error_log( 'Stripe Webhook: Missing signature header' );
			}
			return new WP_Error(
				'missing_signature',
				'Missing Stripe signature',
				array( 'status' => 400 )
			);
		}

		$event = $this->verify_webhook_signature( $payload, $signature );

		if ( is_wp_error( $event ) ) {
			if ( self::should_log() ) {
				error_log( 'Stripe Webhook: Invalid signature - ' . $event->get_error_message() );
			}
			return $event;
		}

		if ( self::should_log() ) {
			error_log(
				sprintf(
					'Stripe Webhook: Received event %s (type: %s)',
					$event->id,
					$event->type
				)
			);
		}

		$result = $this->process_event( $event );

		if ( is_wp_error( $result ) ) {
			if ( self::should_log() ) {
				error_log( 'Stripe Webhook: Processing failed - ' . $result->get_error_message() );
			}
			return new WP_REST_Response(
				array(
					'received' => true,
					'error'    => $result->get_error_message(),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'received'   => true,
				'processed'  => true,
			),
			200
		);
	}

	/**
	 * Verify Stripe webhook signature
	 *
	 * @param string $payload   Raw request body.
	 * @param string $signature Stripe-Signature header value.
	 * @return object|WP_Error Stripe event object or error.
	 */
	private function verify_webhook_signature( $payload, $signature ) {
		$webhook_secret = Bookit_Stripe_Config::get_webhook_secret();

		if ( empty( $webhook_secret ) ) {
			return new WP_Error(
				'missing_webhook_secret',
				'Webhook secret not configured',
				array( 'status' => 500 )
			);
		}

		$bypass = apply_filters( 'bookit_verify_stripe_signature', null, $payload, $signature );

		if ( $bypass === false ) {
			return new WP_Error(
				'invalid_signature',
				'Invalid webhook signature',
				array( 'status' => 400 )
			);
		}

		if ( $bypass === true ) {
			$event_data = json_decode( $payload );
			if ( ! $event_data ) {
				return new WP_Error(
					'invalid_payload',
					'Invalid webhook payload',
					array( 'status' => 400 )
				);
			}
			$data_object = isset( $event_data->data->object ) ? (object) (array) $event_data->data->object : (object) array();
			return (object) array(
				'id'   => $event_data->id ?? 'test_event',
				'type' => $event_data->type ?? 'unknown',
				'data' => (object) array( 'object' => $data_object ),
			);
		}

		try {
			\Stripe\Stripe::setApiKey( Bookit_Stripe_Config::get_secret_key() );
			$event = \Stripe\Webhook::constructEvent(
				$payload,
				$signature,
				$webhook_secret
			);
			return $event;
		} catch ( \UnexpectedValueException $e ) {
			return new WP_Error(
				'invalid_payload',
				'Invalid webhook payload: ' . $e->getMessage(),
				array( 'status' => 400 )
			);
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			return new WP_Error(
				'invalid_signature',
				'Invalid webhook signature: ' . $e->getMessage(),
				array( 'status' => 400 )
			);
		}
	}

	/**
	 * Process webhook event based on type
	 *
	 * @param object $event Stripe event object.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function process_event( $event ) {
		switch ( $event->type ) {
			case 'checkout.session.completed':
				return $this->handle_checkout_completed( $event );

			case 'payment_intent.succeeded':
				if ( self::should_log() ) {
					error_log( 'Stripe Webhook: payment_intent.succeeded received (no action needed)' );
				}
				return true;

			case 'payment_intent.payment_failed':
				if ( self::should_log() ) {
					error_log( 'Stripe Webhook: payment_intent.payment_failed received' );
				}
				return true;

			default:
				if ( self::should_log() ) {
					error_log( 'Stripe Webhook: Unhandled event type: ' . $event->type );
				}
				return true;
		}
	}

	/**
	 * Handle checkout.session.completed event
	 * Creates booking after successful payment
	 *
	 * @param object $event Stripe event.
	 * @return bool|WP_Error
	 */
	private function handle_checkout_completed( $event ) {
		$session = $event->data->object;

		if ( $session->payment_status !== 'paid' ) {
			if ( self::should_log() ) {
				error_log(
					sprintf(
						'Stripe Webhook: Checkout session %s not paid (status: %s)',
						$session->id,
						$session->payment_status
					)
				);
			}
			return true;
		}

		$idempotency_key = 'stripe_webhook_' . $session->id;
		$existing        = get_transient( $idempotency_key );

		if ( $existing ) {
			if ( self::should_log() ) {
				error_log( 'Stripe Webhook: Duplicate webhook detected for session ' . $session->id );
			}
			return true;
		}

		$metadata = (array) $session->metadata;

		$required_fields = array(
			'service_id',
			'staff_id',
			'booking_date',
			'booking_time',
			'customer_email',
			'customer_first_name',
			'customer_last_name',
		);

		foreach ( $required_fields as $field ) {
			if ( empty( $metadata[ $field ] ) ) {
				return new WP_Error(
					'missing_metadata',
					sprintf( 'Missing required metadata field: %s', $field )
				);
			}
		}

		$booking_creator = new Booking_System_Booking_Creator();

		$booking_data = array(
			'service_id'           => (int) $metadata['service_id'],
			'staff_id'             => (int) $metadata['staff_id'],
			'booking_date'         => $metadata['booking_date'],
			'booking_time'         => $metadata['booking_time'],
			'customer_first_name'  => $metadata['customer_first_name'],
			'customer_last_name'   => $metadata['customer_last_name'],
			'customer_email'       => $metadata['customer_email'],
			'customer_phone'       => $metadata['customer_phone'] ?? '',
			'special_requests'     => $metadata['special_requests'] ?? '',
			'payment_method'       => 'stripe',
			'payment_intent_id'    => $session->payment_intent ?? '',
			'stripe_session_id'    => $session->id,
			'amount_paid'          => isset( $session->amount_total ) ? $session->amount_total / 100 : 0,
		);

		// Allow extensions to modify wizard booking data before insertion.
		$booking_data = apply_filters( 'bookit_booking_data_before_insert', $booking_data );

		$booking_id = $booking_creator->create_booking( $booking_data );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		// Notify extensions after a booking is created from Stripe checkout completion.
		do_action( 'bookit_after_booking_created', (int) $booking_id, $booking_data );

		$payment_data = array(
			'amount'            => isset( $session->amount_total ) ? (float) $session->amount_total / 100 : 0,
			'currency'          => isset( $session->currency ) ? sanitize_text_field( $session->currency ) : '',
			'payment_intent_id' => isset( $session->payment_intent ) ? sanitize_text_field( (string) $session->payment_intent ) : '',
			'method'            => 'stripe_checkout',
		);

		// Notify extensions after a payment-backed booking is completed.
		do_action( 'bookit_after_payment_completed', (int) $booking_id, $payment_data );

		Bookit_Audit_Logger::log(
			'payment.completed',
			'booking',
			(int) $booking_id,
			array(
				'new_value' => $payment_data,
				'notes'     => 'Payment confirmed via webhook',
			)
		);

		set_transient( $idempotency_key, $booking_id, 24 * HOUR_IN_SECONDS );

		if ( self::should_log() ) {
			error_log(
				sprintf(
					'Stripe Webhook: Created booking #%d from session %s',
					$booking_id,
					$session->id
				)
			);
		}

		return true;
	}
}

new Booking_System_Stripe_Webhook();
