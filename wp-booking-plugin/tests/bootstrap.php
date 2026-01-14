<?php

declare(strict_types=1);

// Check if WordPress test suite is being run
$is_wordpress_suite = false;
if (isset($_SERVER['argv'])) {
    $argv_string = implode(' ', $_SERVER['argv']);
    if (strpos($argv_string, '--testsuite') !== false && strpos($argv_string, 'WordPress') !== false) {
        $is_wordpress_suite = true;
    } elseif (strpos($argv_string, 'WPEnvironmentTest') !== false) {
        $is_wordpress_suite = true;
    }
}

// If WordPress suite, load WordPress bootstrap instead
if ($is_wordpress_suite) {
    require_once __DIR__ . '/wp-bootstrap.php';
    return;
}

// Otherwise, load standard bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
