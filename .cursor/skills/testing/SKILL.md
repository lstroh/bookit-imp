---
name: testing
description: PHPUnit testing conventions for the BookIt booking system plugin. Use when writing tests, running tests, creating test files, debugging test failures, or working with the wp-env test environment.
---

# Testing Conventions

## Context7 Integration

Use Context7 to look up PHPUnit or WordPress testing APIs when unsure:
- Resolve `phpunit/phpunit` or `wordpress` for current assertion methods and test case APIs.
- Query specific topics like "WordPress WP_UnitTestCase methods" or "PHPUnit data providers".

## Running Tests

Tests run **inside the wp-env Docker container**, not on the host machine.

```bash
# Start the environment first
npm run wp-env:start

# Run all tests
npm test

# Verbose output
npm run test:verbose

# Coverage report (HTML output to coverage/)
npm run test:coverage
```

**Important**: `composer test` runs PHPUnit directly and will fail outside the container. Always use `npm test` which wraps the command inside `wp-env run tests-wordpress`.

The test WordPress instance runs on **port 8889**, the main site on **port 8888**.

## Test File Structure

```
tests/
├── bootstrap.php                    # Test bootstrap (loads WP + plugin)
├── test-*.php                       # Feature/integration tests (root level)
├── unit/
│   ├── test-*-model.php             # Model unit tests
│   ├── test-*-api.php               # REST API unit tests
│   └── test-*.php                   # Other unit tests
└── integration/
    └── test-*.php                   # Full integration tests
```

### File Naming

- Test files: `test-{feature-name}.php` (kebab-case with `test-` prefix)
- Test classes: `Test_{Feature_Name}` extending `WP_UnitTestCase`
- Test methods: `test_{what_is_being_tested}` (snake_case with `test_` prefix)
- Use `@covers` annotations on every test method

### When to Use Each Location

| Location | When to use |
|----------|------------|
| `tests/unit/` | Model classes, API endpoints, isolated logic |
| `tests/integration/` | Multi-step workflows (e.g., wizard flow) |
| `tests/` (root) | Feature tests: auth, database, sessions, payments |

## Test Class Pattern

All tests extend `WP_UnitTestCase` (which provides WordPress context) or `Yoast\PHPUnitPolyfills\TestCases\TestCase` (for tests not requiring WordPress).

```php
<?php
/**
 * Tests for {Feature Name}
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test {Class_Name}.
 */
class Test_Feature_Name extends WP_UnitTestCase {

    /**
     * Set up each test.
     */
    public function setUp(): void {
        parent::setUp();

        global $wpdb;

        // Clean tables relevant to this test
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );

        // Initialize required state
    }

    /**
     * Tear down each test.
     */
    public function tearDown(): void {
        global $wpdb;

        // Clean up
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_services" );

        parent::tearDown();
    }

    /**
     * Test description.
     *
     * @covers Class_Name::method_name
     */
    public function test_descriptive_name() {
        // Arrange
        // Act
        // Assert
    }

    // ========== HELPER METHODS ==========

    /**
     * Create a test service.
     */
    private function create_service( $name, $price, $is_active = true ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'bookings_services',
            array(
                'name'       => $name,
                'price'      => $price,
                'duration'   => 45,
                'is_active'  => $is_active ? 1 : 0,
                'deleted_at' => null,
            ),
            array( '%s', '%f', '%d', '%d', '%s' )
        );
        return $wpdb->insert_id;
    }
}
```

## Key Patterns

### Database Setup/Teardown

- TRUNCATE relevant tables in both `setUp()` and `tearDown()`.
- Use `$wpdb->insert()` with format arrays to create test data.
- Use private helper methods (`create_service`, `create_staff`, etc.) for test data.

### REST API Testing

```php
// Register routes
do_action( 'rest_api_init' );

// Create request
$request = new WP_REST_Request( 'POST', '/bookit/v1/service/select' );
$request->set_param( 'service_id', $service_id );

// Set nonce for authenticated requests
$nonce = wp_create_nonce( 'wp_rest' );
$request->set_header( 'X-WP-Nonce', $nonce );

// Dispatch and assert
$response = rest_get_server()->dispatch( $request );
$data = $response->get_data();

$this->assertEquals( 200, $response->get_status() );
$this->assertTrue( $data['success'] );
```

### Session Testing

- Call `Bookit_Session_Manager::init()` before session-dependent tests.
- Call `Bookit_Session_Manager::clear()` in setUp/tearDown.
- Destroy PHP sessions: `session_destroy()` and `$_SESSION = array()`.

### TDD Pattern (Tests Before Implementation)

The project uses TDD for new features. When a class doesn't exist yet:

```php
private $skip_tests = false;

public function setUp(): void {
    parent::setUp();
    if ( ! file_exists( $handler_file ) ) {
        $this->skip_tests = true;
        return;
    }
    // ... normal setup
}

private function maybe_skip_test(): void {
    if ( $this->skip_tests ) {
        $this->markTestSkipped( 'Handler not implemented yet.' );
    }
}
```

### Stripe Mock Testing

Use WordPress filters to bypass real Stripe API calls:

```php
// Bypass signature verification
add_filter( 'bookit_verify_stripe_signature', '__return_true' );

// Mock Stripe API mode
add_filter( 'bookit_stripe_api_mode', function() { return 'mock'; } );
add_filter( 'bookit_mock_stripe_session', function( $data ) {
    return (object) array( 'id' => 'cs_test_mock_123' );
} );
```

## Registering New Tests

Add new test files to `phpunit.xml` manually:

```xml
<testsuite name="Booking System Test Suite">
    <!-- Add your new test file here -->
    <file>./tests/unit/test-your-feature.php</file>
</testsuite>
```

## PHPUnit Configuration

- Bootstrap: `tests/bootstrap.php`
- PHP 8.2, PHPUnit 9.6
- Yoast PHPUnit Polyfills for cross-version compatibility
- Coverage includes: `includes/`, `admin/`, `public/`
- Coverage excludes: `vendor/`, `tests/`
