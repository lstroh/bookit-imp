<?php

declare(strict_types=1);

namespace BookingPlugin\Tests\Rest\Auth;

/**
 * Integration tests for REST API routes using actual requests.
 *
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class Test_Auth_Login_Route_Integration extends \WP_UnitTestCase
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

        parent::tearDown();
    }

    /**
     * Test POST request returns expected response.
     */
    public function test_post_request_returns_response(): void
    {
        $request = new \WP_REST_Request('POST', '/bookit/v1/auth/login');
        $response = $this->server->dispatch($request);

        // Extract primitive values immediately
        $status = $response->get_status();
        $data = $response->get_data();

        // Route returns 501 Not Implemented currently
        $this->assertEquals(501, $status, 'POST should return 501 (not implemented)');
        $this->assertIsArray($data);
    }

    /**
     * Test GET request returns method not allowed or not found.
     */
    public function test_get_request_returns_error(): void
    {
        $request = new \WP_REST_Request('GET', '/bookit/v1/auth/login');
        $response = $this->server->dispatch($request);

        // Extract primitive values immediately
        $status = $response->get_status();

        // GET should return 404 (no route) or 405 (method not allowed)
        $this->assertTrue(
            in_array($status, [404, 405], true),
            sprintf('GET should return 404 or 405, got %d', $status)
        );
    }

    /**
     * Test unauthenticated POST request is allowed (no 401/403).
     */
    public function test_unauthenticated_post_request_allowed(): void
    {
        // Ensure no user is logged in
        wp_set_current_user(0);

        $request = new \WP_REST_Request('POST', '/bookit/v1/auth/login');
        $response = $this->server->dispatch($request);

        // Extract status immediately
        $status = $response->get_status();

        // Should not be 401 or 403 - route is publicly accessible
        $this->assertNotEquals(401, $status, 'Should not require authentication');
        $this->assertNotEquals(403, $status, 'Should not be forbidden');
    }

    /**
     * Test PUT request is not allowed.
     */
    public function test_put_request_returns_error(): void
    {
        $request = new \WP_REST_Request('PUT', '/bookit/v1/auth/login');
        $response = $this->server->dispatch($request);

        // Extract primitive values immediately
        $status = $response->get_status();

        // PUT should return 404 (no route) or 405 (method not allowed)
        $this->assertTrue(
            in_array($status, [404, 405], true),
            sprintf('PUT should return 404 or 405, got %d', $status)
        );
    }

    /**
     * Test DELETE request is not allowed.
     */
    public function test_delete_request_returns_error(): void
    {
        $request = new \WP_REST_Request('DELETE', '/bookit/v1/auth/login');
        $response = $this->server->dispatch($request);

        // Extract primitive values immediately
        $status = $response->get_status();

        // DELETE should return 404 (no route) or 405 (method not allowed)
        $this->assertTrue(
            in_array($status, [404, 405], true),
            sprintf('DELETE should return 404 or 405, got %d', $status)
        );
    }
}
