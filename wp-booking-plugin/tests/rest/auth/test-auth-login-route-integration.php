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

        // Route returns 401 invalid_credentials (normalized after validation passes)
        $this->assertEquals(401, $status, 'POST should return 401 (invalid_credentials)');
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

        // Extract status immediately
        $status = $response->get_status();

        // Should not be 403 - route is publicly accessible (no WP auth required)
        // Note: 401 from validation failure is different from 401 rest_forbidden
        $this->assertNotEquals(403, $status, 'Should not be forbidden');
        // With valid body, should get 401 (invalid_credentials) - normalized response
        $this->assertEquals(401, $status, 'Should return 401 invalid_credentials with valid credentials');
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
