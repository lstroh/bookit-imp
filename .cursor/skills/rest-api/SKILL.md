---
name: rest-api
description: WordPress REST API conventions for the BookIt booking system plugin. Use when creating or editing REST API endpoints, registering routes, handling API requests, validating parameters, or implementing API authentication.
---

# REST API Conventions

## Context7 Integration

Use Context7 to look up WordPress REST API functions when needed:
- Resolve `wordpress` and query topics like "register_rest_route", "WP_REST_Request methods", or "REST API permission callbacks".

## Namespace and Structure

All endpoints use the namespace `bookit/v1`. API classes live in `includes/api/`.

| File | Class | Endpoint(s) |
|------|-------|-------------|
| `class-service-api.php` | `Bookit_Service_API` | `POST /service/select` |
| `class-staff-api.php` | `Bookit_Staff_API` | Staff selection endpoints |
| `class-datetime-api.php` | `Bookit_DateTime_API` | Availability/datetime endpoints |
| `class-wizard-api.php` | `Bookit_Wizard_API` | `GET/POST /wizard/session` |
| `class-contact-api.php` | `Bookit_Contact_API` | Contact form endpoints |
| `class-stripe-webhook.php` | `Booking_System_Stripe_Webhook` | `POST /stripe/webhook` |

## API Class Template

Every API class follows this pattern:

```php
<?php
/**
 * Feature API
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/api
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Feature API class.
 */
class Bookit_Feature_API {

    /**
     * Initialize API.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            'bookit/v1',
            '/feature/action',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_action' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'param_name' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param ) && $param > 0;
                        },
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
    }

    /**
     * Handle the action.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_action( $request ) {
        // 1. Verify nonce
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid security token', 'bookit-booking-system' ),
                array( 'status' => 403 )
            );
        }

        // 2. Get and sanitize parameters
        $param = absint( $request->get_param( 'param_name' ) );

        // 3. Business logic

        // 4. Return response
        return rest_ensure_response( array(
            'success' => true,
            'data'    => $result,
        ) );
    }
}

// Initialize.
new Bookit_Feature_API();
```

## Authentication

### Public Endpoints (Booking Wizard)

Public-facing endpoints use nonce verification, not WordPress login:

```php
'permission_callback' => '__return_true',  // Allow unauthenticated access
```

Then verify the nonce manually inside the callback:

```php
$nonce = $request->get_header( 'X-WP-Nonce' );
if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
    return new WP_Error( 'invalid_nonce', 'Invalid security token', array( 'status' => 403 ) );
}
```

### Webhook Endpoints (Stripe)

Webhooks use Stripe signature verification instead of nonces:

```php
'permission_callback' => '__return_true',  // Verified by Stripe signature
```

The `bookit_verify_stripe_signature` filter allows tests to bypass signature verification.

### Admin Endpoints (Future)

For admin-only endpoints, use WordPress capabilities:

```php
'permission_callback' => function() {
    return current_user_can( 'manage_options' );
},
```

## Parameter Validation

Always define `args` with validation and sanitization:

```php
'args' => array(
    'service_id' => array(
        'required'          => true,
        'type'              => 'integer',
        'validate_callback' => function( $param ) {
            return is_numeric( $param ) && $param > 0;
        },
        'sanitize_callback' => 'absint',
    ),
    'email' => array(
        'required'          => true,
        'type'              => 'string',
        'validate_callback' => function( $param ) {
            return is_email( $param );
        },
        'sanitize_callback' => 'sanitize_email',
    ),
),
```

## Response Format

### Success Response

```php
return rest_ensure_response( array(
    'success'   => true,
    'service'   => array( 'id' => $id, 'name' => $name ),
    'next_step' => 2,
) );
```

### Error Response

```php
return new WP_Error(
    'error_code',                                          // Machine-readable code
    __( 'Human-readable message', 'bookit-booking-system' ), // Translatable message
    array( 'status' => 404 )                               // HTTP status code
);
```

### Standard HTTP Status Codes

| Code | Usage |
|------|-------|
| 200 | Success |
| 400 | Missing/invalid parameters |
| 403 | Invalid nonce or unauthorized |
| 404 | Resource not found or inactive |
| 500 | Server/configuration error |

## Session Integration

API endpoints that are part of the booking wizard interact with `Bookit_Session_Manager`:

```php
// Initialize session
Bookit_Session_Manager::init();

// Check expiry
if ( Bookit_Session_Manager::is_expired() ) {
    Bookit_Session_Manager::clear();
}

// Read/write wizard state
$wizard_data = Bookit_Session_Manager::get_data();
$wizard_data['current_step'] = 2;
Bookit_Session_Manager::set_data( $wizard_data );

// Regenerate session ID after state changes
Bookit_Session_Manager::regenerate();
```

## Frontend Integration (Vue)

The Vue frontend calls these endpoints using the WordPress nonce:

```javascript
// Nonce is passed via wp_localize_script()
const response = await fetch('/wp-json/bookit/v1/service/select', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': bookitData.nonce,
    },
    body: JSON.stringify({ service_id: selectedId }),
});
```

## Key Rules

- Always use `bookit/v1` namespace.
- Always verify nonces in the callback (not the permission callback) for public endpoints.
- Always use `rest_ensure_response()` for success responses.
- Always use `WP_Error` with status codes for error responses.
- Always sanitize inputs with WordPress sanitization functions.
- Always use `__()` for translatable error messages with the `bookit-booking-system` text domain.
- Load model dependencies inside the callback with `require_once`.
- Instantiate the class at the bottom of the file: `new Bookit_Feature_API();`
