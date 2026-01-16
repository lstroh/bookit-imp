<?php

declare(strict_types=1);

namespace BookingPlugin\Security;

/**
 * Cryptographic configuration constants.
 *
 * PRE-07: Security defaults locked. No executable behavior.
 * This class contains only configuration constants for JWT operations.
 */
class CryptoConfig
{
    /**
     * Allowed JWT algorithms for signing and verification.
     *
     * Only algorithms listed here are permitted for JWT operations.
     */
    public const ALLOWED_JWT_ALGOS = ['HS256'];

    /**
     * Leeway in seconds for clock skew tolerance in JWT validation.
     *
     * This accounts for minor time differences between servers.
     */
    public const JWT_LEEWAY_SECONDS = 60;
}
