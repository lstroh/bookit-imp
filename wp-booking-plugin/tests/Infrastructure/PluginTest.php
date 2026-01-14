<?php

declare(strict_types=1);

namespace BookingPlugin\Tests\Infrastructure;

use PHPUnit\Framework\TestCase;

/**
 * Plugin test class.
 */
class PluginTest extends TestCase
{
    /**
     * Test that the Plugin class exists.
     */
    public function test_plugin_class_exists(): void
    {
        $this->assertTrue(
            class_exists(\BookingPlugin\Infrastructure\Plugin::class)
        );
    }
}
