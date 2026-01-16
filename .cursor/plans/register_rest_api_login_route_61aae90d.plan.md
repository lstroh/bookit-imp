---
name: Register REST API login route
overview: Create a minimal WordPress REST API route handler for POST /wp-json/bookit/v1/auth/login with placeholder permission callback and empty handler stub that returns WP_Error "not_implemented".
todos:
  - id: create-auth-controller
    content: Create AuthController.php in src/API/ with register_routes() method that registers POST /wp-json/bookit/v1/auth/login route
    status: pending
  - id: implement-route-registration
    content: Implement register_rest_route() call with POST-only enforcement, placeholder permission_callback (returns true), and callback returning WP_Error not_implemented
    status: pending
    dependencies:
      - create-auth-controller
  - id: hook-to-rest-api-init
    content: Update Plugin::boot() to hook AuthController::register_routes() to rest_api_init action
    status: pending
    dependencies:
      - create-auth-controller
---

# Register REST API Login Route

## Overview

Register a minimal WordPress REST API route for authentication login endpoint. The route will be a stub that returns "not_implemented" error, with no authentication logic.

## Implementation

### 1. Create Route Handler Class

Create [`wp-booking-plugin/src/API/AuthController.php`](wp-booking-plugin/src/API/AuthController.php):

- Namespace: `BookingPlugin\API`
- Class: `AuthController`
- Method: `register_routes()` - registers the route using `register_rest_route()`
- Hook registration: Attach to `rest_api_init` action

### 2. Route Configuration

- Namespace: `bookit/v1`
- Route: `auth/login`
- Methods: `POST` only (enforce via `methods` parameter)
- Permission callback: Return `true` (placeholder)
- Callback: Return `new WP_Error('not_implemented', 'Not implemented', ['status' => 501])`
- Accept header: `application/json` (enforce via `accept` parameter in route args)

### 3. Bootstrap Integration

Update [`wp-booking-plugin/src/Infrastructure/Plugin.php`](wp-booking-plugin/src/Infrastructure/Plugin.php):

- In `boot()` method, add action hook: `add_action('rest_api_init', [new \BookingPlugin\API\AuthController(), 'register_routes'])`

## Files to Create/Modify

1. **Create**: `wp-booking-plugin/src/API/AuthController.php`

- Class with `register_routes()` method
- Route registration with POST-only enforcement
- Placeholder permission callback
- Empty handler returning WP_Error

2. **Modify**: `wp-booking-plugin/src/Infrastructure/Plugin.php`

- Add `rest_api_init` hook in `boot()` method

## Technical Details

- Use `register_rest_route('bookit/v1', 'auth/login', ...)`
- Set `methods` to `['POST']` to enforce POST-only
- Set `permission_callback` to anonymous function returning `true`
- Set `callback` to anonymous function returning `new WP_Error('not_implemented', 'Not implemented', ['status' => 501])`
- Follow WordPress coding standards (strict types, PSR-4 autoloading)