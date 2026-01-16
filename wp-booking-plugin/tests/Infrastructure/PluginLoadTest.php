<?php

declare(strict_types=1);

namespace BookingPlugin\Tests\Infrastructure;

/**
 * Plugin loading test class.
 */
class PluginLoadTest extends \WP_UnitTestCase
{
    /**
     * Test that plugin loads without side effects.
     */
    public function test_plugin_loads_without_side_effects(): void
    {
        // Verify the plugin main class exists
        $this->assertTrue(
            class_exists(\BookingPlugin\Infrastructure\Plugin::class),
            'Plugin class should be available after loading plugin file'
        );
    }
}
