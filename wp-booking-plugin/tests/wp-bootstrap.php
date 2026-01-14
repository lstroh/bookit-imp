<?php

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Resolve WordPress test library path
$wp_tests_dir = getenv('WP_TESTS_DIR');

if (!$wp_tests_dir) {
    $wp_tests_dir = '/tmp/wordpress-tests-lib';
}

// Load WordPress test bootstrap
require_once $wp_tests_dir . '/includes/bootstrap.php';
