<?php
/**
 * Plugin Name: Booking Plugin
 * Plugin URI: https://example.com/booking-plugin
 * Description: A WordPress booking plugin
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook(__FILE__, function (): void {
    $plugin = new \BookingPlugin\Infrastructure\Plugin();
    $plugin->activate();
});

register_deactivation_hook(__FILE__, function (): void {
    $plugin = new \BookingPlugin\Infrastructure\Plugin();
    $plugin->deactivate();
});

add_action('plugins_loaded', function (): void {
    $plugin = new \BookingPlugin\Infrastructure\Plugin();
    $plugin->boot();
});