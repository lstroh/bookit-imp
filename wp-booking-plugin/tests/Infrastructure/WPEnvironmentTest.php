<?php

declare(strict_types=1);

namespace BookingPlugin\Tests\Infrastructure;

/**
 * WordPress environment test class.
 */
class WPEnvironmentTest extends \WP_UnitTestCase
{
    /**
     * Test that WordPress test environment is loaded.
     */
    public function test_wordpress_test_environment_loaded(): void
    {
        $this->assertTrue(function_exists('do_action'));
        $this->assertInstanceOf(\WP_UnitTestCase::class, $this);
    }
}
