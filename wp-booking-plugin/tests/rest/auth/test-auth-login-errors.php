<?php

declare(strict_types=1);

namespace BookingPlugin\Tests\Rest\Auth;

/**
 * Tests for auth login error semantics.
 *
 * Ensures that the login endpoint returns only two error scenarios:
 * - Validation failure → 401 invalid_credentials
 * - Rate limit breach → 429 rate_limited
 *
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class Test_Auth_Login_Errors extends \WP_UnitTestCase
{
    /**
     * REST Server instance.
     *
     * @var \WP_REST_Server|null
     */
    private $server;

    /**
     * Set up test environment.
     */
    public function setUp(): void
    {
        parent::setUp();

        // Reset REST server for clean state
        global $wp_rest_server;
        $wp_rest_server = null;

        // Ensure plugin is booted (registers hooks)
        $plugin = new \BookingPlugin\Infrastructure\Plugin();
        $plugin->boot();

        // Trigger rest_api_init to register routes
        do_action('rest_api_init');

        // Store server reference
        $this->server = rest_get_server();
    }

    /**
     * Tear down test environment.
     */
    public function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        $this->server = null;

        // Clean up any rate limit transients
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $this->clear_rate_limit_for_ip($_SERVER['REMOTE_ADDR']);
        }

        parent::tearDown();
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
     * Test that validation failure returns 401 invalid_credentials.
     *
     * Verifies that validation failures return 401 with invalid_credentials,
     * and NOT 429 or rate_limited.
     */
    public function test_validation_failure_returns_401_invalid_credentials(): void
    {
        $request = new \WP_REST_Request('POST', '/bookit/v1/auth/login');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(
            json_encode(
                [
                    'identifier'  => '', // Empty identifier triggers validation failure
                    'secret'      => 'test_secret',
                    'client_type' => 'web',
                ]
            )
        );

        $response = $this->server->dispatch($request);

        // Extract primitive values immediately
        $status = $response->get_status();
        $data = $response->get_data();

        // Assert status code is 401
        $this->assertEquals(401, $status, 'Validation failure should return 401 status');

        // Assert error code is invalid_credentials
        $this->assertIsArray($data);
        $this->assertEquals('invalid_credentials', $data['code'], 'Should return invalid_credentials error code');

        // Verify it's NOT 429 or rate_limited
        $this->assertNotEquals(429, $status, 'Validation failure should NOT return 429 status');
        $this->assertNotEquals('rate_limited', $data['code'] ?? '', 'Should NOT return rate_limited error code');
    }

    /**
     * Test that rate limit breach returns 429 rate_limited.
     *
     * Verifies that rate limit breaches return 429 with rate_limited,
     * and NOT 401 or invalid_credentials.
     */
    public function test_rate_limit_breach_returns_429_rate_limited(): void
    {
        $test_ip = '192.168.1.200';
        $_SERVER['REMOTE_ADDR'] = $test_ip;
        $this->clear_rate_limit_for_ip($test_ip);

        $route = '/bookit/v1/auth/login';

        // Make 5 requests to reach the limit
        for ($i = 1; $i <= 5; $i++) {
            $request = new \WP_REST_Request('POST', $route);
            $request->set_header('Content-Type', 'application/json');
            $request->set_body(
                json_encode(
                    [
                        'identifier'  => 'test_id',
                        'secret'      => 'test_secret',
                        'client_type' => 'web',
                    ]
                )
            );
            $this->server->dispatch($request);
        }

        // 6th request should be rate limited
        $request = new \WP_REST_Request('POST', $route);
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(
            json_encode(
                [
                    'identifier'  => 'test_id',
                    'secret'      => 'test_secret',
                    'client_type' => 'web',
                ]
            )
        );
        $response = $this->server->dispatch($request);

        // Extract primitive values immediately
        $status = $response->get_status();
        $data = $response->get_data();

        // Assert status code is 429
        $this->assertEquals(429, $status, 'Rate limit breach should return 429 status');

        // Assert error code is rate_limited
        $this->assertIsArray($data);
        $this->assertEquals('rate_limited', $data['code'], 'Should return rate_limited error code');

        // Verify it's NOT 401 or invalid_credentials
        $this->assertNotEquals(401, $status, 'Rate limit breach should NOT return 401 status');
        $this->assertNotEquals('invalid_credentials', $data['code'] ?? '', 'Should NOT return invalid_credentials error code');

        $this->clear_rate_limit_for_ip($test_ip);
    }

    /**
     * Test that no other status codes are possible.
     *
     * Ensures that only 401 (validation) and 429 (rate limit) status codes
     * are returned from the login endpoint in error scenarios.
     */
    public function test_no_other_status_codes_possible(): void
    {
        $route = '/bookit/v1/auth/login';

        // Test 1: Validation failure should only return 401, not other codes
        $request1 = new \WP_REST_Request('POST', $route);
        $request1->set_header('Content-Type', 'application/json');
        $request1->set_body(
            json_encode(
                [
                    'identifier' => 'test_id',
                    // Missing secret and client_type
                ]
            )
        );
        $response1 = $this->server->dispatch($request1);
        $status1 = $response1->get_status();

        $this->assertEquals(401, $status1, 'Validation failure should return 401');
        $this->assertNotEquals(403, $status1, 'Validation failure should NOT return 403');
        $this->assertNotEquals(404, $status1, 'Validation failure should NOT return 404');
        $this->assertNotEquals(429, $status1, 'Validation failure should NOT return 429');
        $this->assertNotEquals(500, $status1, 'Validation failure should NOT return 500');

        // Test 2: Rate limit breach should only return 429, not other codes
        $test_ip = '192.168.1.201';
        $_SERVER['REMOTE_ADDR'] = $test_ip;
        $this->clear_rate_limit_for_ip($test_ip);

        // Make 5 requests to reach the limit
        for ($i = 1; $i <= 5; $i++) {
            $request = new \WP_REST_Request('POST', $route);
            $request->set_header('Content-Type', 'application/json');
            $request->set_body(
                json_encode(
                    [
                        'identifier'  => 'test_id',
                        'secret'      => 'test_secret',
                        'client_type' => 'web',
                    ]
                )
            );
            $this->server->dispatch($request);
        }

        // 6th request should be rate limited
        $request2 = new \WP_REST_Request('POST', $route);
        $request2->set_header('Content-Type', 'application/json');
        $request2->set_body(
            json_encode(
                [
                    'identifier'  => 'test_id',
                    'secret'      => 'test_secret',
                    'client_type' => 'web',
                ]
            )
        );
        $response2 = $this->server->dispatch($request2);
        $status2 = $response2->get_status();

        $this->assertEquals(429, $status2, 'Rate limit breach should return 429');
        $this->assertNotEquals(401, $status2, 'Rate limit breach should NOT return 401');
        $this->assertNotEquals(403, $status2, 'Rate limit breach should NOT return 403');
        $this->assertNotEquals(404, $status2, 'Rate limit breach should NOT return 404');
        $this->assertNotEquals(500, $status2, 'Rate limit breach should NOT return 500');

        $this->clear_rate_limit_for_ip($test_ip);
    }
}
