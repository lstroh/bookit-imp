<?php

declare(strict_types=1);

namespace BookingPlugin\Security;

/**
 * IP-based rate limiter for the login endpoint.
 *
 * Enforces a limit of 5 requests per 60 seconds per IP address.
 */
class LoginRateLimiter
{
    /**
     * Maximum number of requests allowed per time window.
     */
    private const MAX_REQUESTS = 5;

    /**
     * Time window in seconds.
     */
    private const TIME_WINDOW = 60;

    /**
     * Transient key prefix.
     */
    private const TRANSIENT_PREFIX = 'bookit_login_rl_';

    /**
     * Check if the current IP address has exceeded the rate limit.
     *
     * @return true|\WP_Error Returns true if within limit, WP_Error with status 429 if exceeded.
     */
    public static function check(): bool|\WP_Error
    {
        // Get client IP address
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (empty($ip)) {
            // If IP cannot be determined, fail closed for security
            return new \WP_Error(
                'rate_limited',
                wp_json_encode(
                    [
                        'error'      => 'rate_limited',
                        'retry_after' => self::TIME_WINDOW,
                    ]
                ),
                ['status' => 429]
            );
        }

        // Hash IP to avoid storing raw IP addresses
        $transient_key = self::TRANSIENT_PREFIX . wp_hash($ip);

        // Get current request count
        $count = (int) get_transient($transient_key);

        // Check if limit exceeded
        if ($count >= self::MAX_REQUESTS) {
            return new \WP_Error(
                'rate_limited',
                wp_json_encode(
                    [
                        'error'      => 'rate_limited',
                        'retry_after' => self::TIME_WINDOW,
                    ]
                ),
                ['status' => 429]
            );
        }

        // Increment counter and set/update transient
        set_transient($transient_key, $count + 1, self::TIME_WINDOW);

        return true;
    }
}
