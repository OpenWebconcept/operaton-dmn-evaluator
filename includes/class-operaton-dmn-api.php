<?php

/**
 * Operaton DMN API Manager - Main Class File
 *
 * This is the main API class that orchestrates all DMN-related functionality
 * through a trait-based architecture. Each major functional area is implemented
 * as a separate trait for better maintainability and organization.
 *
 * @package OperatonDMN
 * @subpackage API
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}

// Load all required API traits before class definition
$trait_files = array(
    __DIR__ . '/api-traits/trait-api-core.php',
    __DIR__ . '/api-traits/trait-api-rest-endpoints.php',
    __DIR__ . '/api-traits/trait-api-evaluation.php',
    __DIR__ . '/api-traits/trait-api-ajax-handlers.php',
    __DIR__ . '/api-traits/trait-api-decision-flow.php',
    __DIR__ . '/api-traits/trait-api-testing.php',
    __DIR__ . '/api-traits/trait-api-utilities.php',
    __DIR__ . '/api-traits/trait-api-debug-enhanced.php',
);

foreach ($trait_files as $trait_file)
{
    if (file_exists($trait_file))
    {
        require_once $trait_file;

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Loaded trait: ' . basename($trait_file));
        }
    }
    else
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Missing trait file: ' . $trait_file);
        }
    }
}

/**
 * Operaton DMN API Manager Class
 *
 * Main API class that uses traits to organize functionality into logical
 * components. Each trait handles a specific aspect of DMN integration:
 * - Core initialization and WordPress integration
 * - REST API endpoint registration and handling
 * - Main evaluation logic (decision and process execution)
 * - AJAX handlers for admin interface integration
 * - Decision flow monitoring and visualization
 * - Testing and configuration validation
 * - Data processing and transformation utilities
 * - URL construction and validation helpers
 * - HTTP communication and error handling
 * - Configuration and settings management
 * - Debug and monitoring utilities
 * - Additional utility methods and helpers
 *
 * @since 1.0.0
 */
class Operaton_DMN_API
{
    // Debug level constants - moved from trait
    const DEBUG_LEVEL_NONE = 0;                 // No debug output
    const DEBUG_LEVEL_MINIMAL = 1;              // Only critical errors and warnings
    const DEBUG_LEVEL_STANDARD = 2;             // Standard operations and results
    const DEBUG_LEVEL_VERBOSE = 3;              // Detailed debug information
    const DEBUG_LEVEL_DIAGNOSTIC = 4;           // Full diagnostic including sensitive data (sanitized)

    // Load all functional traits in logical order
    use Operaton_DMN_API_Core;                  // Core properties, constructor, WordPress integration
    use Operaton_DMN_API_Rest_Endpoints;        // REST API route registration
    use Operaton_DMN_API_Evaluation;            // Main DMN evaluation logic
    use Operaton_DMN_API_Ajax_Handlers;         // AJAX handlers for admin interface
    use Operaton_DMN_API_Decision_Flow;         // Decision flow monitoring & visualization
    use Operaton_DMN_API_Testing;               // Testing & configuration validation
    use Operaton_DMN_API_Utilities;             // Consolidated utilities (data processing, HTTP, etc.)
    use Operaton_DMN_API_Debug_Enhanced;        // Enhanced debug system
}

// End of main API class file
