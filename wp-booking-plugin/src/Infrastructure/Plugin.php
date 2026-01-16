<?php

declare(strict_types=1);

namespace BookingPlugin\Infrastructure;

/**
 * Plugin bootstrap class.
 */
class Plugin
{
    /**
     * Boot the plugin.
     */
    public function boot(): void
    {
        // Bootstrap only - no business logic
    }

    /**
     * Plugin activation hook handler.
     *
     * PRE-06: Intentionally no-op. No side effects allowed.
     * This method is called when the plugin is activated.
     */
    public function activate(): void
    {
        // No-op: PRE-06 requirement - zero side effects
        
    }

    /**
     * Plugin deactivation hook handler.
     *
     * PRE-06: Intentionally no-op. No side effects allowed.
     * This method is called when the plugin is deactivated.
     */
    public function deactivate(): void
    {
        // No-op: PRE-06 requirement - zero side effects
    }
}
