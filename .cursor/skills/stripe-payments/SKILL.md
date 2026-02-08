---
name: stripe-payments
description: Stripe payment integration patterns for the BookIt booking system plugin. Use when working with Stripe Checkout, webhooks, payment processing, deposit calculations, idempotency handling, or payment-related API endpoints.
---

# Stripe Payments

## Context7 Integration

Use Context7 to look up current Stripe PHP SDK APIs:
- Resolve `stripe-php` for Checkout Session creation, PaymentIntent methods, and webhook handling.
- Resolve `stripe` (Stripe docs site) for broader topics like webhook event types, testing, and Stripe CLI usage.

## Architecture Overview

```
includes/
├── payment/
│   ├── class-stripe-config.php       # Config: keys, mode, SDK init
│   ├── class-stripe-checkout.php     # Checkout Session creation
│   └── class-payment-processor.php   # Payment processing logic
├── api/
│   └── class-stripe-webhook.php      # Webhook endpoint handler
├── core/
│   └── class-idempotency-handler.php # Idempotency key tracking
└── booking/
    └── class-booking-creator.php     # Creates booking after payment
```

## Stripe Configuration

Keys are stored as WordPress options, accessed via `Bookit_Stripe_Config`:

```php
// Mode: 'test' or 'live'
$mode = Bookit_Stripe_Config::get_mode();

// Keys for current mode
$publishable = Bookit_Stripe_Config::get_publishable_key();
$secret      = Bookit_Stripe_Config::get_secret_key();
$webhook_sec = Bookit_Stripe_Config::get_webhook_secret();

// SDK client (lazy-initialized singleton)
$client = Bookit_Stripe_Config::get_stripe_client();

// Key validation
Bookit_Stripe_Config::validate_publishable_key( $key, 'test' ); // pk_test_
Bookit_Stripe_Config::validate_secret_key( $key, 'test' );      // sk_test_
Bookit_Stripe_Config::validate_webhook_secret( $key );           // whsec_
```

**Option names**:
- `bookit_stripe_test_mode` (bool)
- `bookit_stripe_test_publishable_key` / `bookit_stripe_live_publishable_key`
- `bookit_stripe_test_secret_key` / `bookit_stripe_live_secret_key`
- `bookit_stripe_test_webhook_secret` / `bookit_stripe_live_webhook_secret`

## Checkout Session Flow

1. Customer completes booking wizard (service, staff, date/time, contact).
2. `Booking_System_Stripe_Checkout::create_checkout_session()` is called.
3. Idempotency check runs -- if duplicate request, cached session ID returned.
4. Session data validated, service/staff fetched, deposit calculated.
5. Stripe Checkout Session created with booking metadata.
6. Customer redirected to Stripe-hosted checkout page.
7. After payment, Stripe sends `checkout.session.completed` webhook.
8. Webhook handler creates the booking in the database.

### Checkout Session Parameters

```php
$params = array(
    'payment_method_types' => array( 'card' ),
    'mode'                => 'payment',
    'line_items'          => array( array(
        'price_data' => array(
            'currency'     => 'gbp',                  // Always GBP
            'unit_amount'  => $amount_in_pence,        // Convert £ to pence
            'product_data' => array(
                'name'        => $service_name,
                'description' => "with {$staff_name} on {$date} at {$time}",
            ),
        ),
        'quantity' => 1,
    ) ),
    'success_url'     => home_url( '/booking-confirmed?session_id={CHECKOUT_SESSION_ID}' ),
    'cancel_url'      => home_url( '/book?step=5&cancelled=1' ),
    'customer_email'  => $email,
    'metadata'        => $booking_metadata,  // Used by webhook to create booking
);
```

**Critical**: All booking data must be in `metadata` -- this is the only data the webhook receives.

### Required Metadata Fields

| Field | Example |
|-------|---------|
| `service_id` | "1" |
| `staff_id` | "2" |
| `booking_date` | "2026-02-15" |
| `booking_time` | "10:00" |
| `customer_email` | "jane@example.com" |
| `customer_first_name` | "Jane" |
| `customer_last_name` | "Smith" |
| `customer_phone` | "+447700900000" (optional) |
| `special_requests` | "..." (optional, max 500 chars) |

## Deposit Calculation

`Booking_System_Stripe_Checkout::calculate_deposit()` handles two deposit types:

| Type | Logic |
|------|-------|
| `percentage` | `(price * percentage) / 100`, clamped 0-100% |
| `fixed` | `min(fixed_amount, price)` |
| None configured | Full price charged |

Always round to 2 decimal places. Convert to pence for Stripe: `(int) round( $amount * 100 )`.

## Webhook Handler

Endpoint: `POST /wp-json/bookit/v1/stripe/webhook`

### Signature Verification

```php
$event = \Stripe\Webhook::constructEvent( $payload, $signature, $webhook_secret );
```

The `bookit_verify_stripe_signature` filter allows test bypass:
- Return `true` to skip verification (tests).
- Return `false` to force rejection.
- Return `null` for normal verification.

### Handled Events

| Event | Action |
|-------|--------|
| `checkout.session.completed` | Create booking if `payment_status === 'paid'` |
| `payment_intent.succeeded` | Logged only (no action) |
| `payment_intent.payment_failed` | Logged only (no action) |

### Webhook Idempotency

Duplicate webhooks are prevented using WordPress transients:

```php
$idempotency_key = 'stripe_webhook_' . $session->id;
$existing = get_transient( $idempotency_key );

if ( $existing ) {
    return true;  // Already processed
}

// ... create booking ...

set_transient( $idempotency_key, $booking_id, 24 * HOUR_IN_SECONDS );
```

### Important: Always Return 200

Even on processing errors, return HTTP 200 to Stripe to prevent retries:

```php
return new WP_REST_Response(
    array( 'received' => true, 'error' => $error_message ),
    200
);
```

## Idempotency Handler

`Booking_System_Idempotency_Handler` prevents duplicate operations (checkout sessions, emails, etc.)

### Lifecycle

```
start_operation() → processing → complete_operation() → completed
                                → fail_operation()    → failed (allows retry)
```

### Key Methods

```php
$handler = new Booking_System_Idempotency_Handler();

// Generate a unique key
$key = $handler->generate_key();  // URL-safe, 32+ chars

// Start tracking (returns existing record if duplicate)
$result = $handler->start_operation( 'stripe_checkout', $key, $request_data );

// Complete with cached response
$handler->complete_operation( $key, array( 'session_id' => 'cs_test_...' ) );

// Or mark as failed (allows retry with same key)
$handler->fail_operation( $key, 'Connection timeout' );

// Get cached response for completed operations
$cached = $handler->get_completed_response( $key );

// Check status
$status = $handler->get_operation_status( $key );  // processing|completed|failed|null

// Clean up expired records (24h TTL)
$handler->cleanup_expired();
```

### Idempotency Key Generation for Checkout

Keys are derived from booking identity (not random):

```php
$key_data = array(
    'service_id'     => $session_data['service_id'],
    'staff_id'       => $session_data['staff_id'],
    'date'           => $session_data['date'],
    'time'           => $session_data['time'],
    'customer_email' => $session_data['customer_email'],
);
$key = 'stripe_checkout_' . hash( 'sha256', wp_json_encode( $key_data ) );
```

### Data Mismatch Protection

If the same key is reused with different request data, returns `WP_Error` with code `idempotency_data_mismatch`.

## Testing Stripe

### Mock Patterns

```php
// Bypass real Stripe API
add_filter( 'bookit_stripe_api_mode', function() { return 'mock'; } );

// Return mock session
add_filter( 'bookit_mock_stripe_session', function( $data ) {
    return (object) array(
        'id'           => 'cs_test_mock_' . uniqid(),
        'amount_total' => 5000,
        'currency'     => 'gbp',
    );
} );

// Bypass webhook signature
add_filter( 'bookit_verify_stripe_signature', '__return_true' );
```

### Logging

Production logging is suppressed during tests:

```php
private static function should_log() {
    return ! defined( 'WP_TESTS_TABLE_PREFIX' ) && function_exists( 'error_log' );
}
```

## Key Rules

- Currency is always `gbp` (British pounds). Amounts to Stripe are always in **pence**.
- Never store raw Stripe secret keys in code -- always use `Bookit_Stripe_Config`.
- Always use idempotency for checkout session creation.
- Always include all booking data in Stripe metadata -- the webhook depends on it.
- Always return HTTP 200 from webhooks, even on errors.
- Use `Booking_System_Booking_Creator` to create bookings from webhook data -- never insert directly.
- Graceful degradation: if the idempotency handler is unavailable, proceed without it.
