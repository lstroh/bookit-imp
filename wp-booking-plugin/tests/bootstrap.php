<?php

declare(strict_types=1);
/**
 * Detect if we should load the WordPress Test Library
 */
function should_load_wp_suite() {
    // 1. Check if the WP_TESTS_DIR is set (wp-env provides this automatically)
    if ( getenv( 'WP_TESTS_DIR' ) || getenv( 'WP_PHPUNIT__DIR' ) ) {
        return true;
    }

    // 2. Fallback: Check if we are specifically calling a WP test file via CLI
    if ( isset( $_SERVER['argv'] ) ) {
        $command_line = implode( ' ', $_SERVER['argv'] );
        if ( strpos( $command_line, 'WPEnvironmentTest' ) !== false || 
             strpos( $command_line, 'tests/Infrastructure' ) !== false ) {
            return true;
        }
    }

    return false;
}

$is_wordpress_suite = should_load_wp_suite();
echo 'is_wordpress_suite: ' . ($is_wordpress_suite ? 'YES' : 'NO') . "\n";
// If WordPress suite, load WordPress bootstrap instead
if ($is_wordpress_suite) {
    require_once __DIR__ . '/wp-bootstrap.php';
    return;
}

// Otherwise, load standard bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
