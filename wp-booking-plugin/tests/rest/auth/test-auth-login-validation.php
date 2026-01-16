<?php

declare(strict_types=1);

namespace BookingPlugin\Tests\Rest\Auth;

/**
 * Tests for auth login request validation.
 *
 * @backupGlobals disabled
 * @backupStaticAttributes disabled
 */
class Test_Auth_Login_Validation extends \WP_UnitTestCase
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
     * Test missing identifier returns 401 invalid_credentials.
     */
    public function test_missing_identifier_returns_401_invalid_credentials(): void
    {
        $request = new \WP_REST_Request('POST', '/bookit/v1/auth/login');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(
            json_encode(
                [
                    'secret'      => 'test_secret',
                    'client_type' => 'web',
                ]
            )
        );

        $response = $this->server->dispatch($request);

        // Extract primitive values immediately
        $status = $response->get_status();
        $data = $response->get_data();

        $this->assertEquals(401, $status, 'Missing identifier should return 401 status');
        $this->assertIsArray($data);
        $this->assertEquals('invalid_credentials', $data['code'], 'Should return invalid_credentials error code');
    }

    /**
     * Test missing secret returns 401 invalid_credentials.
     */
    public function test_missing_secret_returns_401_invalid_credentials(): void
    {
        $request = new \WP_REST_Request('POST', '/bookit/v1/auth/login');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(
            json_encode(
                [
                    'identifier'  => 'test_id',
                    'client_type' => 'web',
                ]
            )
        );

        $response = $this->server->dispatch($request);

        // Extract primitive values immediately
        $status = $response->get_status();
        $data = $response->get_data();

        $this->assertEquals(401, $status, 'Missing secret should return 401 status');
        $this->assertIsArray($data);
        $this->assertEquals('invalid_credentials', $data['code'], 'Should return invalid_credentials error code');
    }

    /**
     * Test missing client_type returns 401 invalid_credentials.
     */
    public function test_missing_client_type_returns_401_invalid_credentials(): void
    {
        $request = new \WP_REST_Request('POST', '/bookit/v1/auth/login');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(
            json_encode(
                [
                    'identifier' => 'test_id',
                    'secret'     => 'test_secret',
                ]
            )
        );

        $response = $this->server->dispatch($request);

        // Extract primitive values immediately
        $status = $response->get_status();
        $data = $response->get_data();

        $this->assertEquals(401, $status, 'Missing client_type should return 401 status');
        $this->assertIsArray($data);
        $this->assertEquals('invalid_credentials', $data['code'], 'Should return invalid_credentials error code');
    }

    /**
     * Test invalid client_type returns 401 invalid_credentials.
     */
    public function test_invalid_client_type_returns_401_invalid_credentials(): void
    {
        $request = new \WP_REST_Request('POST', '/bookit/v1/auth/login');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(
            json_encode(
                [
                    'identifier'  => 'test_id',
                    'secret'      => 'test_secret',
                    'client_type' => 'invalid',
                ]
            )
        );

        $response = $this->server->dispatch($request);

        // Extract primitive values immediately
        $status = $response->get_status();
        $data = $response->get_data();

        $this->assertEquals(401, $status, 'Invalid client_type should return 401 status');
        $this->assertIsArray($data);
        $this->assertEquals('invalid_credentials', $data['code'], 'Should return invalid_credentials error code');
    }

    /**
     * Test all fields present passes validation.
     */
    public function test_all_fields_present_passes_validation(): void
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

        // Validation should pass - should NOT return 401 invalid_credentials
        $this->assertNotEquals(401, $status, 'All fields present should not return 401 status');
        $this->assertIsArray($data);
        $this->assertNotEquals('invalid_credentials', $data['code'], 'Should not return invalid_credentials error code when all fields are valid');
    }
}
