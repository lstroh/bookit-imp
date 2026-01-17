<?php

declare(strict_types=1);

namespace BookingPlugin\Tests\Rest\Auth;

/**
 * Tests for auth login success response shape.
 *
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class Test_Auth_Login_Success_Shape extends \WP_UnitTestCase
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
     * Test that success response returns 200 status code.
     */
    public function test_success_response_is_200(): void
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

        $this->assertEquals(200, $status, 'Success response should return 200 status code');
    }

    /**
     * Test that success response contains all required keys.
     */
    public function test_success_response_has_all_required_keys(): void
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
        $data = $response->get_data();

        $this->assertIsArray($data, 'Response data should be an array');
        
        // Required keys from AuthController success response
        $required_keys = ['access_token', 'expires_in', 'refresh_token', 'token_type', 'scope'];
        
        foreach ($required_keys as $key) {
            $this->assertArrayHasKey(
                $key,
                $data,
                sprintf('Response should contain required key: %s', $key)
            );
        }
    }

    /**
     * Test that success response contains no extra keys.
     */
    public function test_success_response_has_no_extra_keys(): void
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
        $data = $response->get_data();

        $this->assertIsArray($data, 'Response data should be an array');
        
        // Expected keys from AuthController success response
        $expected_keys = ['access_token', 'expires_in', 'refresh_token', 'token_type', 'scope'];
        $actual_keys = array_keys($data);
        
        sort($expected_keys);
        sort($actual_keys);
        
        $this->assertEquals(
            $expected_keys,
            $actual_keys,
            'Response should contain exactly the 5 required keys with no extra keys'
        );
    }
}
