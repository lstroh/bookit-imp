<?php

declare(strict_types=1);

namespace BookingPlugin\Tests\Rest\Auth;

/**
 * Test class for rate limiting on the login endpoint.
 *
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class Test_Login_Rate_Limiter extends \WP_UnitTestCase
{
    /**
     * Get REST server instance.
     *
     * @return \WP_REST_Server
     */
    private function get_rest_server(): \WP_REST_Server
    {
        global $wp_rest_server;
        $wp_rest_server = null;

        $plugin = new \BookingPlugin\Infrastructure\Plugin();
        $plugin->boot();

        do_action('rest_api_init');

        return rest_get_server();
    }

    /**
     * Clear rate limit transient for an IP.
     *
     * @param string $ip IP address.
     */
    private function clear_rate_limit_for_ip(string $ip): void
    {
        if (empty($ip)) {
            return;
        }
        $transient_key = 'bookit_login_rl_' . wp_hash($ip);
        delete_transient($transient_key);
    }

    /**
     * Test that first 5 requests succeed.
     */
    public function test_first_five_requests_succeed(): void
    {
        $test_ip = '192.168.1.100';
        $_SERVER['REMOTE_ADDR'] = $test_ip;
        $this->clear_rate_limit_for_ip($test_ip);

        $server = $this->get_rest_server();
        $route  = '/bookit/v1/auth/login';

        for ($i = 1; $i <= 5; $i++) {
            $request  = new \WP_REST_Request('POST', $route);
            $response = $server->dispatch($request);
            $status   = $response->get_status();

            $this->assertNotEquals(
                429,
                $status,
                "Request #{$i} should not be rate limited"
            );
        }

        $this->clear_rate_limit_for_ip($test_ip);
    }

    /**
     * Test that 6th request returns 429.
     */
    public function test_sixth_request_returns_429(): void
    {
        $test_ip = '192.168.1.101';
        $_SERVER['REMOTE_ADDR'] = $test_ip;
        $this->clear_rate_limit_for_ip($test_ip);

        $server = $this->get_rest_server();
        $route  = '/bookit/v1/auth/login';

        for ($i = 1; $i <= 5; $i++) {
            $request = new \WP_REST_Request('POST', $route);
            $server->dispatch($request);
        }

        $request  = new \WP_REST_Request('POST', $route);
        $response = $server->dispatch($request);
        $status   = $response->get_status();

        $this->assertEquals(429, $status, '6th request should return 429');

        $this->clear_rate_limit_for_ip($test_ip);
    }

    /**
     * Test that rate limiter is IP-specific.
     */
    public function test_rate_limiter_is_ip_specific(): void
    {
        $ip1 = '192.168.1.103';
        $ip2 = '192.168.1.104';
        $this->clear_rate_limit_for_ip($ip1);
        $this->clear_rate_limit_for_ip($ip2);

        $server = $this->get_rest_server();
        $route  = '/bookit/v1/auth/login';

        // Exhaust limit for IP1
        $_SERVER['REMOTE_ADDR'] = $ip1;
        for ($i = 1; $i <= 5; $i++) {
            $request = new \WP_REST_Request('POST', $route);
            $server->dispatch($request);
        }

        // IP1 should be rate limited
        $request  = new \WP_REST_Request('POST', $route);
        $response = $server->dispatch($request);
        $this->assertEquals(429, $response->get_status(), 'IP1 should be rate limited');

        // IP2 should not be rate limited
        $_SERVER['REMOTE_ADDR'] = $ip2;
        $request  = new \WP_REST_Request('POST', $route);
        $response = $server->dispatch($request);
        $this->assertNotEquals(429, $response->get_status(), 'IP2 should not be rate limited');

        $this->clear_rate_limit_for_ip($ip1);
        $this->clear_rate_limit_for_ip($ip2);
    }

    /**
     * Test that counter resets after transient expires.
     */
    public function test_counter_resets_after_transient_expires(): void
    {
        $test_ip = '192.168.1.105';
        $_SERVER['REMOTE_ADDR'] = $test_ip;
        $this->clear_rate_limit_for_ip($test_ip);

        $server = $this->get_rest_server();
        $route  = '/bookit/v1/auth/login';

        for ($i = 1; $i <= 5; $i++) {
            $request = new \WP_REST_Request('POST', $route);
            $server->dispatch($request);
        }

        // Should be rate limited
        $request  = new \WP_REST_Request('POST', $route);
        $response = $server->dispatch($request);
        $this->assertEquals(429, $response->get_status());

        // Simulate transient expiration
        $this->clear_rate_limit_for_ip($test_ip);

        // Should succeed again
        $request  = new \WP_REST_Request('POST', $route);
        $response = $server->dispatch($request);
        $this->assertNotEquals(429, $response->get_status(), 'Should succeed after reset');

        $this->clear_rate_limit_for_ip($test_ip);
    }
}
