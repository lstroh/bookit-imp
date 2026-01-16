<?php

declare(strict_types=1);

namespace BookingPlugin\Tests\Rest\Auth;

/**
 * Test class for REST API route registration and HTTP method restrictions.
 */
class Test_Auth_Login_Route extends \WP_UnitTestCase
{
    /**
     * Set up test environment.
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Ensure routes are registered before each test
        do_action('rest_api_init');
    }

    /**
     * Test that the route is registered at the expected path.
     */
    public function test_route_exists_at_expected_path(): void
    {
        $server = rest_get_server();
        $routes = $server->get_routes();
        
        $expected_route = '/bookit/v1/auth/login';
        
        $this->assertArrayHasKey(
            $expected_route,
            $routes,
            'Route should be registered at /bookit/v1/auth/login'
        );
    }

    /**
     * Test that only POST method is allowed.
     */
    public function test_only_post_method_is_allowed(): void
    {
        $request = new \WP_REST_Request('POST', '/bookit/v1/auth/login');
        $response = rest_do_request($request);
        
        // POST should not return method_not_allowed or no_route error
        $error_code = $response->get_error_code();
        $this->assertNotEquals(
            'rest_method_not_allowed',
            $error_code,
            'POST method should be allowed for /bookit/v1/auth/login'
        );
        
        $this->assertNotEquals(
            'rest_no_route',
            $error_code,
            'POST method should not return rest_no_route error'
        );
    }

    /**
     * Test that GET method returns 404 or method_not_allowed.
     */
    public function test_get_returns_404_or_method_not_allowed(): void
    {
        $request = new \WP_REST_Request('GET', '/bookit/v1/auth/login');
        $response = rest_do_request($request);
        
        // GET should return either 404 or method_not_allowed
        $error_code = $response->get_error_code();
        $is_404_or_method_not_allowed = in_array(
            $error_code,
            ['rest_no_route', 'rest_method_not_allowed'],
            true
        );
        
        $this->assertTrue(
            $is_404_or_method_not_allowed,
            sprintf(
                'GET method should return rest_no_route or rest_method_not_allowed, got: %s',
                $error_code ?: 'no error'
            )
        );
    }

    /**
     * Test that the route is publicly accessible (no authentication required).
     */
    public function test_route_is_publicly_accessible(): void
    {
        // Ensure no user is logged in
        wp_set_current_user(0);
        
        $server = rest_get_server();
        $routes = $server->get_routes();
        $expected_route = '/bookit/v1/auth/login';
        
        $this->assertArrayHasKey($expected_route, $routes, 'Route should be registered');
        
        // Get the route options
        $route_options = $routes[$expected_route];
        
        // Check that permission_callback exists and returns true
        // The route should have at least one endpoint
        $this->assertNotEmpty($route_options, 'Route should have endpoint options');
        
        // Test that we can make a request without authentication
        $request = new \WP_REST_Request('POST', '/bookit/v1/auth/login');
        $response = rest_do_request($request);
        
        // If permission_callback returns false, we'd get a rest_forbidden error
        // Since permission_callback returns true, we should not get rest_forbidden
        $this->assertNotEquals(
            'rest_forbidden',
            $response->get_error_code(),
            'Route should be publicly accessible without authentication'
        );
    }
}
