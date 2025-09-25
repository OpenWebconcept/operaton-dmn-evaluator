<?php

/**
 * REST API endpoints trait for Operaton DMN Plugin
 *
 * Handles all REST API endpoint registration and health check functionality.
 * Provides the core REST endpoints for DMN evaluation and system monitoring.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Operaton_DMN_API_REST_Endpoints
{
    /**
     * Register health endpoint
     * Sets up the system health monitoring endpoint
     *
     * @since 1.0.0
     */
    public function register_health_endpoint()
    {
        register_rest_route('operaton-dmn/v1', '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'health_check'],
            'permission_callback' => '__return_true', // Public endpoint
            'args' => [
                'detailed' => [
                    'description' => 'Include detailed health information',
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ]);
    }

    /**
     * Health check endpoint callback
     * Provides system health status and diagnostic information
     *
     * @param WP_REST_Request $request REST API request object
     * @return WP_REST_Response Health check response
     * @since 1.0.0
     */
    public function health_check($request)
    {
        $detailed = $request->get_param('detailed');
        $start_time = microtime(true);

        // Basic health status
        $health = [
            'status' => 'healthy',
            'timestamp' => current_time('c'),
            'version' => OPERATON_DMN_VERSION ?? '1.0.0-beta.12',
            'environment' => wp_get_environment_type(),
        ];

        // Detailed health information
        if ($detailed) {
            $health['details'] = $this->get_detailed_health_info();
        }

        // Add response time
        $health['response_time'] = round((microtime(true) - $start_time) * 1000, 2); // ms

        // Check for any critical issues
        $critical_issues = $this->check_critical_health();
        if (!empty($critical_issues)) {
            $health['status'] = 'degraded';
            $health['issues'] = $critical_issues;

            return new WP_REST_Response($health, 503); // Service Unavailable
        }

        return new WP_REST_Response($health, 200);
    }

    /**
     * Get detailed health information
     * Retrieves comprehensive system health data
     *
     * @return array Detailed health information
     * @since 1.0.0
     */
    private function get_detailed_health_info()
    {
        global $wpdb;

        $details = [];

        // WordPress status
        $details['wordpress'] = [
            'version' => get_bloginfo('version'),
            'multisite' => is_multisite(),
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
            'php_version' => PHP_VERSION,
        ];

        // Database connectivity
        try {
            $db_check = $wpdb->get_var("SELECT 1");
            $details['database'] = [
                'status' => $db_check === '1' ? 'connected' : 'error',
                'version' => $wpdb->get_var("SELECT VERSION()"),
            ];
        } catch (Exception $e) {
            $details['database'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // Plugin dependencies
        $details['dependencies'] = [
            'gravity_forms' => class_exists('GFCommon'),
            'required_functions' => [
                'curl_init' => function_exists('curl_init'),
                'json_encode' => function_exists('json_encode'),
                'openssl' => extension_loaded('openssl'),
            ],
        ];

        // DMN configurations
        $details['dmn_configs'] = [
            'total_configurations' => $this->count_dmn_configurations(),
            'active_configurations' => $this->count_active_configurations(),
        ];

        // Recent evaluation statistics
        $details['recent_activity'] = $this->get_recent_evaluation_stats();

        return $details;
    }

    /**
     * Check for critical health issues
     * Identifies system problems that affect functionality
     *
     * @return array Array of critical issues
     * @since 1.0.0
     */
    private function check_critical_health()
    {
        $issues = [];

        // Check memory usage
        $memory_usage = memory_get_usage(true);
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        if ($memory_usage > ($memory_limit * 0.9)) {
            $issues[] = 'High memory usage: ' . round($memory_usage / 1024 / 1024, 2) . 'MB';
        }

        // Check database connectivity
        global $wpdb;
        if ($wpdb->last_error) {
            $issues[] = 'Database error: ' . $wpdb->last_error;
        }

        // Check required dependencies
        if (!class_exists('GFCommon')) {
            $issues[] = 'Gravity Forms not available';
        }

        if (!function_exists('curl_init')) {
            $issues[] = 'cURL extension not available';
        }

        return $issues;
    }

    /**
     * Count DMN configurations (implement based on your storage)
     * Returns total number of DMN configurations
     *
     * @return int Number of configurations
     * @since 1.0.0
     */
    private function count_dmn_configurations()
    {
        // TODO: Implement based on how you store configurations
        // This is a placeholder - adjust based on your actual implementation

        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        }

        return 0;
    }

    /**
     * Count active DMN configurations
     * Returns number of active configurations
     *
     * @return int Number of active configurations
     * @since 1.0.0
     */
    private function count_active_configurations()
    {
        // TODO: Implement based on your storage
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE active = 1");
        }

        return 0;
    }

    /**
     * Get recent evaluation statistics
     * Returns statistics about recent DMN evaluations
     *
     * @return array Evaluation statistics
     * @since 1.0.0
     */
    private function get_recent_evaluation_stats()
    {
        // TODO: Implement based on your logging system
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_evaluations';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $last_24h = $wpdb->get_var("
            SELECT COUNT(*)
            FROM $table_name
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");

            $success_rate = $wpdb->get_var("
            SELECT AVG(CASE WHEN success = 1 THEN 1 ELSE 0 END) * 100
            FROM $table_name
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");

            return [
                'evaluations_last_24h' => (int) $last_24h,
                'success_rate_24h' => round((float) $success_rate, 2),
            ];
        }

        return [
            'evaluations_last_24h' => 0,
            'success_rate_24h' => 0,
        ];
    }

    /**
     * Register REST API routes for DMN evaluation and testing endpoints
     * Creates public endpoints for form evaluation and debug functionality
     *
     * @since 1.0.0
     */
    public function register_rest_routes()
    {
        error_log('Operaton DMN API: register_rest_routes() called');

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Registering REST API routes');
        }

        // Main evaluation endpoint
        register_rest_route('operaton-dmn/v1', '/evaluate', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_evaluation'),
            'permission_callback' => '__return_true',
            'args' => array(
                'config_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return $value > 0;
                    }
                ),
                'form_data' => array(
                    'required' => true,
                    'type' => 'object',
                    'validate_callback' => function ($value) {
                        return is_array($value) && !empty($value);
                    }
                )
            )
        ));
        error_log('Operaton DMN API: Evaluate route registered');

        // Test endpoint for debugging
        register_rest_route('operaton-dmn/v1', '/test', array(
            'methods' => 'GET',
            'callback' => function () {
                return array(
                    'status' => 'Plugin REST API is working!',
                    'version' => OPERATON_DMN_VERSION,
                    'timestamp' => current_time('mysql')
                );
            },
            'permission_callback' => '__return_true'
        ));
        error_log('Operaton DMN API: Test route registered');

        // Health endpoint for monitoring and load testing
        $health_registered = register_rest_route('operaton-dmn/v1', '/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true',
            'args' => array(
                'detailed' => array(
                    'description' => 'Include detailed health information',
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ),
            ),
        ));

        if ($health_registered) {
            error_log('Operaton DMN API: Health route registered successfully');
        } else {
            error_log('Operaton DMN API: Health route registration FAILED');
        }

        // Check if the health_check method exists
        if (method_exists($this, 'health_check')) {
            error_log('Operaton DMN API: health_check method exists');
        } else {
            error_log('Operaton DMN API: health_check method DOES NOT EXIST');
        }
    }

    /**
     * Register decision flow REST endpoint separately for modular loading
     * Creates endpoint for decision flow summary retrieval
     *
     * @since 1.0.0
     */
    public function register_decision_flow_endpoint()
    {
        register_rest_route('operaton-dmn/v1', '/decision-flow/(?P<form_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_decision_flow'),
            'permission_callback' => '__return_true',
            'args' => array(
                'form_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));
    }
}