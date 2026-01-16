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
        add_filter('rest_prepare_response', [$this, 'format_rate_limit_error'], 10, 2);
    }

    /**
     * Format rate limit error response to match specification.
     *
     * @param \WP_REST_Response|\WP_Error $response The response object.
     * @param \WP_REST_Request            $request  The request object.
     * @return \WP_REST_Response|\WP_Error Modified response.
     */
    public function format_rate_limit_error($response, \WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        // Only format errors for the login endpoint
        if ($request->get_route() !== '/bookit/v1/auth/login') {
            return $response;
        }

        // Check if this is a rate limit error
        if (is_wp_error($response) && $response->get_error_code() === 'rate_limited') {
            // Create a new response with the exact format specified
            $rest_response = new \WP_REST_Response(
                [
                    'error'      => 'rate_limited',
                    'retry_after' => 60,
                ],
                429
            );

            return $rest_response;
        }

        // Check if this is an invalid credentials error
        if (is_wp_error($response) && $response->get_error_code() === 'invalid_credentials') {
            // Create a new response with the exact format specified
            $rest_response = new \WP_REST_Response(
                [
                    'error' => 'invalid_credentials',
                ],
                401
            );

            return $rest_response;
        }

        return $response;
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
