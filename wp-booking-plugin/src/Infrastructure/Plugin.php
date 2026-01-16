<?php

declare(strict_types=1);

namespace BookingPlugin\Infrastructure;

/**
 * Plugin bootstrap class.
 */
class Plugin
{
    /**
     * Boot the plugin.
     */
    public function boot(): void
    {
        add_action('rest_api_init', [new \BookingPlugin\API\AuthController(), 'register_routes']);
        add_filter('rest_prepare_response', [$this, 'normalize_login_errors'], 10, 2);
    }

    /**
     * Normalize all login error responses to match specification.
     *
     * Ensures only 401 invalid_credentials or 429 rate_limited responses
     * are returned for the login endpoint, preventing internal error leakage.
     *
     * @param \WP_REST_Response|\WP_Error $response The response object.
     * @param \WP_REST_Request            $request  The request object.
     * @return \WP_REST_Response|\WP_Error Modified response.
     */
    public function normalize_login_errors($response, \WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        // Only normalize errors for the login endpoint
        if ($request->get_route() !== '/bookit/v1/auth/login') {
            return $response;
        }

        // If not a WP_Error, return as-is
        if (!is_wp_error($response)) {
            return $response;
        }

        $error_code = $response->get_error_code();
        $status_code = $response->get_error_data();

        // Extract status code from error data (can be array or int)
        if (is_array($status_code) && isset($status_code['status'])) {
            $status_code = $status_code['status'];
        } elseif (!is_int($status_code)) {
            $status_code = null;
        }

        // Normalize rate_limited errors to 429
        if ($error_code === 'rate_limited') {
            return new \WP_REST_Response(
                [
                    'error'      => 'rate_limited',
                    'retry_after' => 60,
                ],
                429
            );
        }

        // Normalize invalid_credentials errors to 401 (regardless of original status code)
        if ($error_code === 'invalid_credentials') {
            return new \WP_REST_Response(
                [
                    'error' => 'invalid_credentials',
                ],
                401
            );
        }

        // Catch-all: Any other error (including wrong status codes) normalized to 401 invalid_credentials
        // This prevents internal error messages, stack traces, or other status codes from leaking
        return new \WP_REST_Response(
            [
                'error' => 'invalid_credentials',
            ],
            401
        );
    }

    /**
     * Plugin activation hook handler.
     *
     * PRE-06: Intentionally no-op. No side effects allowed.
     * This method is called when the plugin is activated.
     */
    public function activate(): void
    {
        // No-op: PRE-06 requirement - zero side effects
        
    }

    /**
     * Plugin deactivation hook handler.
     *
     * PRE-06: Intentionally no-op. No side effects allowed.
     * This method is called when the plugin is deactivated.
     */
    public function deactivate(): void
    {
        // No-op: PRE-06 requirement - zero side effects
    }
}
