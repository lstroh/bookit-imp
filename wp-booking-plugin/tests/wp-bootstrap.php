<?php

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Resolve WordPress test library path
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: getenv( 'WP_PHPUNIT__DIR' );

if ( ! $_tests_dir ) {
    // If both are empty, wp-env isn't communicating with PHPUnit correctly
    echo "ERROR: WordPress test directory not found in environment variables.\n";
    exit( 1 );
}

echo "Loading WordPress tests from: " . $_tests_dir . "\n";
// Load the helper functions
require_once $_tests_dir . '/includes/functions.php';


// Load WordPress test bootstrap
require_once $_tests_dir . '/includes/bootstrap.php';

// Load the plugin main file
require_once __DIR__ . '/../booking-plugin.php';


