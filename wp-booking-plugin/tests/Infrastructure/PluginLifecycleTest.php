<?php

declare(strict_types=1);

namespace BookingPlugin\Tests\Infrastructure;

/**
 * Plugin lifecycle test class.
 */
class PluginLifecycleTest extends \WP_UnitTestCase
{
    /**
     * Test that plugin activation does not fail.
     */
    public function test_plugin_activation_does_not_fail(): void
    {
        $plugin = new \BookingPlugin\Infrastructure\Plugin();
        $plugin->activate();
        
        $this->assertTrue(true);
    }

    /**
     * Test that plugin deactivation does not fail.
     */
    public function test_plugin_deactivation_does_not_fail(): void
    {
        $plugin = new \BookingPlugin\Infrastructure\Plugin();
        $plugin->deactivate();
        
        $this->assertTrue(true);
    }
}
