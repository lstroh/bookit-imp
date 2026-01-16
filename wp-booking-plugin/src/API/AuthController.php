<?php

declare(strict_types=1);

namespace BookingPlugin\API;

/**
 * Authentication controller for REST API routes.
 */
class AuthController
{
    /**
     * Register REST API routes.
     */
    public function register_routes(): void
    {
        register_rest_route(
            'bookit/v1',
            'auth/login',
            [
                'methods'             => ['POST'],
                'permission_callback' => function () {
                    return \BookingPlugin\Security\LoginRateLimiter::check();
                },
                'callback'            => [$this, 'handle_login'],
                'args'                => [
                    'accept' => [
                        'default' => 'application/json',
                    ],
                ],
            ]
        );
    }

    /**
     * Handle login request with validation.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_Error|\WP_REST_Response Response object.
     */
    public function handle_login(\WP_REST_Request $request)
    {
        // Get JSON parameters from request body
        $params = $request->get_json_params();

        // Validate all required fields in a single pass
        $identifier = $params['identifier'] ?? null;
        $secret = $params['secret'] ?? null;
        $client_type = $params['client_type'] ?? null;

        // Check if any validation fails (fail-closed approach)
        $is_valid = (
            isset($identifier) &&
            is_string($identifier) &&
            $identifier !== '' &&
            isset($secret) &&
            is_string($secret) &&
            $secret !== '' &&
            isset($client_type) &&
            is_string($client_type) &&
            in_array($client_type, ['mobile', 'web'], true)
        );

        if (!$is_valid) {
            // Return generic error without revealing which field failed
            return new \WP_Error(
                'invalid_credentials',
                'Invalid credentials',
                ['status' => 401]
            );
        }

        // Validation passed - return not implemented for now
        return new \WP_Error(
            'not_implemented',
            'Not implemented',
            ['status' => 501]
        );
    }
}
