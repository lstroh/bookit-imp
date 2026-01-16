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
                'callback'            => function () {
                    return new \WP_Error(
                        'not_implemented',
                        'Not implemented',
                        ['status' => 501]
                    );
                },
                'args'                => [
                    'accept' => [
                        'default' => 'application/json',
                    ],
                ],
            ]
        );
    }
}
