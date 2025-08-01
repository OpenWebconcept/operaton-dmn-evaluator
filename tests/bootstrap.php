<?php

/**
 * Clean PHPUnit bootstrap for Operaton DMN Evaluator
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Test utilities and helpers
require_once __DIR__ . '/helpers/test-helper.php';
require_once __DIR__ . '/fixtures/mock-classes.php';

// Optional: Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
