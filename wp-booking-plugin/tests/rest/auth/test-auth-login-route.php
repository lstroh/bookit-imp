<?php

declare(strict_types=1);

namespace BookingPlugin\Tests\Rest\Auth;

/**
 * Test class for REST API route registration and HTTP method restrictions.
 */
class Test_Auth_Login_Route extends \WP_UnitTestCase
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
     * Test that the route is registered at the expected path.
     */
    public function test_route_exists_at_expected_path(): void
    {
        $routes = $this->server->get_routes();
        $expected_route = '/bookit/v1/auth/login';
        
        // Check if route exists
        $route_exists = array_key_exists($expected_route, $routes);
        
        $this->assertTrue(
            $route_exists,
            'Route should be registered at /bookit/v1/auth/login'
        );
    }

    /**
     * Test that only POST method is allowed.
     */
    public function test_only_post_method_is_allowed(): void
    {
        $routes = $this->server->get_routes();
        $expected_route = '/bookit/v1/auth/login';
        
        $this->assertTrue(
            array_key_exists($expected_route, $routes),
            'Route should exist'
        );
        
        // Get the allowed methods from route registration
        // Route data is an array of endpoints, each with 'methods' key
        $route_data = $routes[$expected_route];
        $allowed_methods = [];
        
        foreach ($route_data as $endpoint) {
            if (isset($endpoint['methods'])) {
                $methods = $endpoint['methods'];
                if (is_array($methods)) {
                    $allowed_methods = array_merge($allowed_methods, array_keys(array_filter($methods)));
                }
            }
        }
        
        // POST should be in the allowed methods
        $this->assertContains(
            'POST',
            $allowed_methods,
            'POST method should be allowed for /bookit/v1/auth/login'
        );
    }

    /**
     * Test that GET method returns 404 or method_not_allowed.
     */
    public function test_get_returns_404_or_method_not_allowed(): void
    {
        $routes = $this->server->get_routes();
        $expected_route = '/bookit/v1/auth/login';
        
        $this->assertTrue(
            array_key_exists($expected_route, $routes),
            'Route should exist'
        );
        
        // Get the allowed methods from route registration
        $route_data = $routes[$expected_route];
        $allowed_methods = [];
        
        foreach ($route_data as $endpoint) {
            if (isset($endpoint['methods'])) {
                $methods = $endpoint['methods'];
                if (is_array($methods)) {
                    $allowed_methods = array_merge($allowed_methods, array_keys(array_filter($methods)));
                }
            }
        }
        
        // GET should NOT be in the allowed methods
        $this->assertNotContains(
            'GET',
            $allowed_methods,
            'GET method should not be allowed for /bookit/v1/auth/login'
        );
    }

    /**
     * Test that the route is publicly accessible (no authentication required).
     */
    public function test_route_is_publicly_accessible(): void
    {
        // Ensure no user is logged in
        wp_set_current_user(0);
        
        $routes = $this->server->get_routes();
        $expected_route = '/bookit/v1/auth/login';
        
        $this->assertTrue(
            array_key_exists($expected_route, $routes),
            'Route should be registered'
        );
        
        // Check that permission_callback returns true (route is public)
        // by checking we don't get rest_forbidden when making a POST request
        $request = new \WP_REST_Request('POST', $expected_route);
        $response = $this->server->dispatch($request);
        
        // Extract status code - this is a primitive value
        $status_code = $response->get_status();
        
        // 403 would indicate permission denied (rest_forbidden)
        // We expect 501 (not implemented) or any non-403 status
        $this->assertNotEquals(
            403,
            $status_code,
            'Route should be publicly accessible without authentication (should not return 403 Forbidden)'
        );
    }
}
