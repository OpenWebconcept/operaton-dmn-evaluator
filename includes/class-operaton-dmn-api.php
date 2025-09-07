<?php

/**
 * API Handler for Operaton DMN Plugin
 *
 * Handles all external API interactions including REST endpoints, DMN evaluation,
 * process execution, and endpoint testing. Manages communication with Operaton
 * decision engines and provides AJAX handlers for admin functionality.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Operaton_DMN_API
{
    /**
     * Core plugin instance reference
     * Provides access to main plugin functionality and configuration
     *
     * @var OperatonDMNEvaluator
     * @since 1.0.0
     */
    private $core;

    /**
     * Database manager instance
     * Handles configuration retrieval and process tracking
     *
     * @var Operaton_DMN_Database
     * @since 1.0.0
     */
    private $database;

    /**
     * API request timeout in seconds
     * Default timeout for external API calls
     *
     * @var int
     * @since 1.0.0
     */
    private $api_timeout = 30;

    /**
     * SSL verification setting for API calls
     * Should be true in production, false for development
     *
     * @var bool
     * @since 1.0.0
     */
    private $ssl_verify = false;

    /**
     * HTTP connection pool for reusing connections to the same host
     * @var array
     */
    private static $connection_pool = array();

    /**
     * Connection pool statistics for monitoring
     * @var array
     */
    private static $pool_stats = array(
        'hits' => 0,
        'misses' => 0,
        'created' => 0,
        'cleaned' => 0
    );

    /**
     * Maximum age for pooled connections (in seconds)
     * @var int
     */
    private $connection_max_age = 300; // 5 minutes

    /**
     * Maximum number of connections per host
     * @var int
     */
    private $max_connections_per_host = 3;

    /**
     * Get optimized HTTP client options with connection reuse
     *
     * @param string $endpoint_url Full endpoint URL
     * @return array HTTP client options optimized for connection reuse
     */
    private function get_optimized_http_options($endpoint_url)
    {
        $host = parse_url($endpoint_url, PHP_URL_HOST);
        $connection_key = $this->get_connection_key($host);

        // Check if we have a valid cached connection
        if ($this->has_valid_connection($connection_key))
        {
            self::$pool_stats['hits']++;
            $this->update_connection_stats('hits');  // Wordpress persistence for admin dashboard
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Reusing connection for host: ' . $host);
            }
            return $this->get_cached_connection_options($connection_key);
        }

        // Create new optimized connection
        self::$pool_stats['misses']++;
        self::$pool_stats['created']++;
        $this->update_connection_stats('misses'); // Wordpress persistence for admin dashboard
        $this->update_connection_stats('created');

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Creating new connection for host: ' . $host);
        }

        $options = $this->create_optimized_connection_options($host);
        $this->cache_connection($connection_key, $options);

        return $options;
    }

    /**
     * Create optimized HTTP connection options
     *
     * @param string $host Hostname
     * @return array Optimized HTTP options
     */
    private function create_optimized_connection_options($host)
    {
        return array(
            'timeout' => $this->api_timeout,
            'sslverify' => $this->ssl_verify,
            'headers' => $this->get_api_headers_with_keepalive(),
            'httpversion' => '1.1',
            'blocking' => true,
            'stream' => false,
            'decompress' => true,
            'redirection' => 3,
            // Connection reuse optimizations
            'user-agent' => $this->get_optimized_user_agent(),
            // Force HTTP/1.1 with keep-alive
            'curl_options' => array(
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_TCP_KEEPALIVE => 1,
                CURLOPT_TCP_KEEPIDLE => 60,
                CURLOPT_TCP_KEEPINTVL => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_DNS_CACHE_TIMEOUT => 300,
                CURLOPT_MAXCONNECTS => $this->max_connections_per_host,
                // Reuse connections
                CURLOPT_FORBID_REUSE => false,
                CURLOPT_FRESH_CONNECT => false,
            )
        );
    }

    /**
     * Get API headers optimized for connection reuse
     *
     * @return array Headers with keep-alive directives
     */
    private function get_api_headers_with_keepalive()
    {
        $headers = $this->get_api_headers();

        // Add connection keep-alive headers
        $headers['Connection'] = 'keep-alive';
        $headers['Keep-Alive'] = 'timeout=60, max=10';

        return $headers;
    }

    /**
     * Get optimized user agent string
     *
     * @return string User agent with connection info
     */
    private function get_optimized_user_agent()
    {
        return sprintf(
            'WordPress/%s; Operaton-DMN/%s; Connection-Pool/1.0',
            get_bloginfo('version'),
            OPERATON_DMN_VERSION ?? '1.0.0'
        );
    }

    /**
     * Generate connection pool key
     *
     * @param string $host Hostname
     * @return string Connection key
     */
    private function get_connection_key($host)
    {
        return 'operaton_conn_' . md5($host . $this->ssl_verify);
    }

    /**
     * Check if we have a valid cached connection
     *
     * @param string $connection_key Connection cache key
     * @return bool True if valid connection exists
     */
    private function has_valid_connection($connection_key)
    {
        if (!isset(self::$connection_pool[$connection_key]))
        {
            return false;
        }

        $connection = self::$connection_pool[$connection_key];
        $age = time() - $connection['created_at'];

        if ($age > $this->connection_max_age)
        {
            unset(self::$connection_pool[$connection_key]);
            self::$pool_stats['cleaned']++;
            return false;
        }

        return true;
    }

    /**
     * Get cached connection options
     *
     * @param string $connection_key Connection cache key
     * @return array Cached connection options
     */
    private function get_cached_connection_options($connection_key)
    {
        $connection = self::$connection_pool[$connection_key];
        $connection['last_used'] = time();
        $connection['use_count']++;

        // Update the cached connection
        self::$connection_pool[$connection_key] = $connection;

        return $connection['options'];
    }

    /**
     * Cache connection options
     *
     * @param string $connection_key Connection cache key
     * @param array $options HTTP options to cache
     */
    private function cache_connection($connection_key, $options)
    {
        // Clean old connections first
        $this->cleanup_old_connections();
        $this->update_connection_stats('cleaned'); // Wordpress persistence for admin dashboard

        self::$connection_pool[$connection_key] = array(
            'options' => $options,
            'created_at' => time(),
            'last_used' => time(),
            'use_count' => 1,
            'host' => parse_url($options['user-agent'] ?? '', PHP_URL_HOST)
        );
    }

    /**
     * Clean up old connections from the pool
     */
    private function cleanup_old_connections()
    {
        $current_time = time();
        $cleaned = 0;

        foreach (self::$connection_pool as $key => $connection)
        {
            $age = $current_time - $connection['created_at'];
            $idle_time = $current_time - $connection['last_used'];

            // Remove if too old or idle too long
            if ($age > $this->connection_max_age || $idle_time > 120)
            {
                unset(self::$connection_pool[$key]);
                $cleaned++;
            }
        }

        if ($cleaned > 0)
        {
            self::$pool_stats['cleaned'] += $cleaned;
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Cleaned ' . $cleaned . ' old connections');
            }
        }
    }

    /**
     * Enhanced API call method with connection reuse
     * Replace your existing wp_remote_post calls with this method
     *
     * @param string $endpoint Full endpoint URL
     * @param array $data Request data
     * @param string $method HTTP method (POST, GET, etc.)
     * @return array|WP_Error HTTP response
     */
    private function make_optimized_api_call($endpoint, $data = array(), $method = 'POST')
    {
        // Get optimized connection options
        $options = $this->get_optimized_http_options($endpoint);

        // Add request-specific data
        if (!empty($data))
        {
            $options['body'] = is_array($data) ? wp_json_encode($data) : $data;
        }

        // Make the request using the appropriate method
        if ($method === 'POST')
        {
            return wp_remote_post($endpoint, $options);
        }
        elseif ($method === 'GET')
        {
            return wp_remote_get($endpoint, $options);
        }
        else
        {
            $options['method'] = $method;
            return wp_remote_request($endpoint, $options);
        }
    }

    /**
     * Batch multiple API operations for the same host
     * Use this for process execution that requires multiple API calls
     *
     * @param object $config Configuration object
     * @param array $form_data Form data
     * @return array|WP_Error Batched execution results
     */
    private function handle_process_execution_optimized($config, $form_data)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Starting optimized process execution for key: ' . $config->process_key);
        }

        // Parse field mappings
        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE)
        {
            return new WP_Error(
                'invalid_mappings',
                __('Invalid field mappings configuration', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        // Process input variables
        $variables = $this->process_input_variables($field_mappings, $form_data);
        if (is_wp_error($variables))
        {
            return $variables;
        }

        $base_url = $this->get_engine_rest_base_url($config->dmn_endpoint);

        // Prepare batch API calls
        $api_calls = array(
            'start_process' => array(
                'endpoint' => $this->build_process_endpoint($config->dmn_endpoint, $config->process_key),
                'data' => array('variables' => $variables),
                'method' => 'POST'
            )
        );

        // Execute process start with optimized connection
        $process_response = $this->make_optimized_api_call(
            $api_calls['start_process']['endpoint'],
            $api_calls['start_process']['data'],
            'POST'
        );

        if (is_wp_error($process_response))
        {
            return new WP_Error(
                'api_error',
                sprintf(__('Failed to start process: %s', 'operaton-dmn'), $process_response->get_error_message()),
                array('status' => 500)
            );
        }

        $http_code = wp_remote_retrieve_response_code($process_response);
        $body = wp_remote_retrieve_body($process_response);

        if ($http_code !== 200 && $http_code !== 201)
        {
            return new WP_Error(
                'api_error',
                sprintf(__('Process start failed with status %d: %s', 'operaton-dmn'), $http_code, $body),
                array('status' => 500)
            );
        }

        $process_result = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE)
        {
            return new WP_Error(
                'invalid_response',
                __('Invalid JSON response from process start', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        $process_instance_id = $process_result['id'];
        $process_ended = isset($process_result['ended']) ? $process_result['ended'] : false;

        // Immediately get variables using the same optimized connection
        $variables_endpoint = $base_url . '/history/variable-instance?processInstanceId=' . $process_instance_id;
        $variables_response = $this->make_optimized_api_call($variables_endpoint, array(), 'GET');

        if (is_wp_error($variables_response))
        {
            // Fallback to original method if optimized call fails
            return $this->get_process_variables($config, $process_instance_id, $process_ended);
        }

        // Process the variables response
        $variables_body = wp_remote_retrieve_body($variables_response);
        $historical_variables = json_decode($variables_body, true);

        $final_variables = array();
        if (is_array($historical_variables))
        {
            foreach ($historical_variables as $var)
            {
                if (isset($var['name']) && array_key_exists('value', $var))
                {
                    $final_variables[$var['name']] = array(
                        'value' => $var['value'],
                        'type' => isset($var['type']) ? $var['type'] : 'String'
                    );
                }
            }
        }

        // Extract results
        $results = $this->extract_process_results($config, $final_variables);

        // Store process instance ID
        $this->database->store_process_instance_id($config->form_id, $process_instance_id);

        return array(
            'success' => true,
            'results' => $results,
            'process_instance_id' => $process_instance_id,
            'debug_info' => $this->get_debug_info() ? array(
                'variables_sent' => $variables,
                'process_result' => $process_result,
                'final_variables' => $final_variables,
                'endpoint_used' => $api_calls['start_process']['endpoint'],
                'process_ended_immediately' => $process_ended,
                'connection_stats' => self::$pool_stats,
                'optimized_calls_used' => 2
            ) : null
        );
    }

    /**
     * Get connection pool statistics with WordPress persistence
     */
    public function get_connection_pool_stats()
    {
        // Get stats from WordPress options (persistent storage)
        $stored_stats = get_option('operaton_connection_stats', array(
            'hits' => 0,
            'misses' => 0,
            'created' => 0,
            'cleaned' => 0
        ));

        return array(
            'stats' => $stored_stats,
            'active_connections' => count(self::$connection_pool),
            'pool_details' => array_map(function ($conn)
            {
                return array(
                    'age' => time() - $conn['created_at'],
                    'idle_time' => time() - $conn['last_used'],
                    'use_count' => $conn['use_count']
                );
            }, self::$connection_pool)
        );
    }

    /**
     * Update persistent statistics
     */
    private function update_connection_stats($type)
    {
        $stats = get_option('operaton_connection_stats', array(
            'hits' => 0,
            'misses' => 0,
            'created' => 0,
            'cleaned' => 0
        ));

        $stats[$type]++;
        update_option('operaton_connection_stats', $stats);
    }

    /**
     * Clear connection pool (useful for testing or debugging)
     */
    public function clear_connection_pool()
    {
        $cleared = count(self::$connection_pool);
        self::$connection_pool = array();
        self::$pool_stats['cleaned'] += $cleared;

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Manually cleared ' . $cleared . ' connections');
        }

        return $cleared;
    }

    /**
     * Constructor for API handler
     * Initializes API functionality with required dependencies
     *
     * @param OperatonDMNEvaluator $core Core plugin instance
     * @param Operaton_DMN_Database $database Database manager instance
     * @since 1.0.0
     */
    public function __construct($core, $database)
    {
        $this->core = $core;
        $this->database = $database;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Handler initialized');
        }

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks for API functionality
     * Sets up REST API routes and AJAX handlers
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // REST API hooks
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // AJAX handlers
        add_action('wp_ajax_operaton_test_endpoint', array($this, 'ajax_test_endpoint'));
        add_action('wp_ajax_nopriv_operaton_test_endpoint', array($this, 'ajax_test_endpoint'));
        add_action('wp_ajax_operaton_test_full_config', array($this, 'ajax_test_full_config'));
        add_action('wp_ajax_operaton_clear_update_cache', array($this, 'ajax_clear_update_cache'));

        // API Debug tests
        add_action('wp_ajax_operaton_dmn_debug', array($this, 'handle_dmn_debug_ajax'));
        add_action('wp_ajax_nopriv_operaton_dmn_debug', array($this, 'run_operaton_dmn_debug'));

        // Decision flow REST endpoint
        add_action('rest_api_init', array($this, 'register_decision_flow_endpoint'));
    }

    // =============================================================================
    // REST API REGISTRATION METHODS
    // =============================================================================

    /**
     * Register health endpoint
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

    // =============================================================================
    // MAIN EVALUATION METHODS
    // =============================================================================

    /**
     * Enhanced evaluation handler that routes to either process execution or direct decision evaluation
     * Main REST API endpoint that determines evaluation method based on configuration settings
     *
     * @param WP_REST_Request $request REST API request object containing config ID and form data
     * @return WP_REST_Response|WP_Error Evaluation results or error response
     * @since 1.0.0
     */
    public function handle_evaluation($request)
    {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN API: Handling evaluation request');
            }

            $params = $request->get_json_params();

            // Validate required parameters
            if (!isset($params['config_id']) || !isset($params['form_data'])) {
                return new WP_Error(
                    'missing_params',
                    __('Configuration ID and form data are required', 'operaton-dmn'),
                    array('status' => 400)
                );
            }

            // Get configuration
            $config = $this->database->get_configuration($params['config_id']);
            if (!$config) {
                return new WP_Error(
                    'invalid_config',
                    __('Configuration not found', 'operaton-dmn'),
                    array('status' => 404)
                );
            }

            // Determine evaluation method
            $use_process = isset($config->use_process) ? $config->use_process : false;

            if ($use_process && !empty($config->process_key)) {
            // OLD:
            //    return $this->handle_process_execution($config, $params['form_data']);
            // NEW:
                return $this->handle_process_execution_optimized($config, $params['form_data']);
            } else {
                return $this->handle_decision_evaluation($config, $params['form_data']);
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN API: Evaluation error: ' . $e->getMessage());
            }

            return new WP_Error(
                'server_error',
                __('An error occurred during evaluation', 'operaton-dmn'),
                array('status' => 500)
            );
        }
    }

    /**
     * Handle process execution using Operaton's process engine with variable extraction and storage
     * Starts a process instance, waits for completion, and extracts results from process variables
     *
     * @param object $config Configuration object containing process settings
     * @param array $form_data Form data to be passed as process variables
     * @return array|WP_Error Process execution results with extracted variables
     * @since 1.0.0
     */
    private function handle_process_execution($config, $form_data)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Starting process execution for key: ' . $config->process_key);
        }

        // Parse and validate field mappings
        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'invalid_mappings',
                __('Invalid field mappings configuration', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        // Process input variables
        $variables = $this->process_input_variables($field_mappings, $form_data);
        if (is_wp_error($variables)) {
            return $variables;
        }

        // Build process start endpoint
        $process_endpoint = $this->build_process_endpoint($config->dmn_endpoint, $config->process_key);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Starting process at: ' . $process_endpoint);
        }

        // Start the process
        $process_data = array('variables' => $variables);

        $response = wp_remote_post($process_endpoint, array(
            'headers' => $this->get_api_headers(),
            'body' => wp_json_encode($process_data),
            'timeout' => $this->api_timeout,
            'sslverify' => $this->ssl_verify,
        ));

        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                sprintf(__('Failed to start process: %s', 'operaton-dmn'), $response->get_error_message()),
                array('status' => 500)
            );
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($http_code !== 200 && $http_code !== 201) {
            return new WP_Error(
                'api_error',
                sprintf(__('Process start failed with status %d: %s', 'operaton-dmn'), $http_code, $body),
                array('status' => 500)
            );
        }

        $process_result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'invalid_response',
                __('Invalid JSON response from process start', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        $process_instance_id = $process_result['id'];
        $process_ended = isset($process_result['ended']) ? $process_result['ended'] : false;

        // Get final variables from process
        $final_variables = $this->get_process_variables($config, $process_instance_id, $process_ended);
        if (is_wp_error($final_variables)) {
            return $final_variables;
        }

        // Process results based on configured mappings
        $results = $this->extract_process_results($config, $final_variables);

        // Store process instance ID for decision flow retrieval
        $this->database->store_process_instance_id($config->form_id, $process_instance_id);

        return array(
            'success' => true,
            'results' => $results,
            'process_instance_id' => $process_instance_id,
            'debug_info' => $this->get_debug_info() ? array(
                'variables_sent' => $variables,
                'process_result' => $process_result,
                'final_variables' => $final_variables,
                'endpoint_used' => $process_endpoint,
                'process_ended_immediately' => $process_ended,
                'extraction_summary' => array(
                    'total_variables_found' => count($final_variables),
                    'results_extracted' => count($results),
                    'result_fields_searched' => array_keys($this->parse_result_mappings($config))
                )
            ) : null
        );
    }

    /**
     * Handle direct decision evaluation using Operaton's decision engine endpoint
     * Sends form data to DMN evaluation endpoint and processes decision table results
     *
     * @param object $config Configuration object containing decision settings
     * @param array $form_data Form data to be evaluated by the decision table
     * @return array|WP_Error Decision evaluation results with mapped field values
     * @since 1.0.0
     */
    private function handle_decision_evaluation($config, $form_data)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Starting direct decision evaluation for key: ' . $config->decision_key);
        }

        // Parse and validate mappings
        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'invalid_mappings',
                __('Invalid field mappings configuration', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        $result_mappings = json_decode($config->result_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($result_mappings)) {
            return new WP_Error(
                'invalid_result_mappings',
                __('Invalid or missing result mappings configuration', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        // Process input variables
        $variables = $this->process_input_variables($field_mappings, $form_data);
        if (is_wp_error($variables)) {
            return $variables;
        }

        if (empty($variables)) {
            return new WP_Error(
                'no_data',
                __('No valid form data provided', 'operaton-dmn'),
                array('status' => 400)
            );
        }

        // Build the full evaluation endpoint
        $evaluation_endpoint = $this->build_evaluation_endpoint($config->dmn_endpoint, $config->decision_key);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Using evaluation endpoint: ' . $evaluation_endpoint);
        }

        // Make API call
        $operaton_data = array('variables' => $variables);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: About to make HTTP request to: ' . $evaluation_endpoint);
            error_log('Operaton DMN API: Request data: ' . wp_json_encode($operaton_data));
        }

        // OLD:
        //$response = wp_remote_post($evaluation_endpoint, array(
        //    'headers' => $this->get_api_headers(),
        //    'body' => wp_json_encode($operaton_data),
        //    'timeout' => $this->api_timeout,
        //    'sslverify' => $this->ssl_verify,
        //));

        // NEW:
        $response = $this->make_optimized_api_call($evaluation_endpoint, $operaton_data);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (is_wp_error($response)) {
                error_log('Operaton DMN API: HTTP request failed: ' . $response->get_error_message());
                error_log('Operaton DMN API: Error codes: ' . print_r($response->get_error_codes(), true));
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                error_log('Operaton DMN API: HTTP response code: ' . $response_code);
                error_log('Operaton DMN API: HTTP response body: ' . substr($response_body, 0, 500)); // First 500 chars
            }
        }

        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                sprintf(__('Failed to connect to Operaton API: %s', 'operaton-dmn'), $response->get_error_message()),
                array('status' => 500)
            );
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($http_code !== 200) {
            return new WP_Error(
                'api_error',
                sprintf(__('API returned status code %d: %s', 'operaton-dmn'), $http_code, $body),
                array('status' => 500)
            );
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'invalid_response',
                __('Invalid JSON response from Operaton API', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        // Process results based on configured mappings
        $results = $this->extract_decision_results($result_mappings, $data);

        if (empty($results)) {
            return new WP_Error(
                'no_results',
                __('No valid results found in API response', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        return array(
            'success' => true,
            'results' => $results,
            'debug_info' => $this->get_debug_info() ? array(
                'variables_sent' => $variables,
                'api_response' => $data,
                'endpoint_used' => $evaluation_endpoint,
                'result_mappings' => $result_mappings
            ) : null
        );
    }

    // =============================================================================
    // DECISION FLOW METHODS
    // =============================================================================

    /**
     * REST endpoint handler for decision flow summary
     * Wrapper for get_decision_flow_summary_html for REST API access
     *
     * @param WP_REST_Request $request REST request with form_id parameter
     * @return array REST response with HTML content
     * @since 1.0.0
     */
    public function rest_get_decision_flow($request)
    {
        $form_id = $request['form_id'];
        $html = $this->get_decision_flow_summary_html($form_id);

        return array(
            'success' => true,
            'html' => $html,
            'form_id' => $form_id
        );
    }

    /**
     * Get decision flow summary HTML with caching and cache busting support
     * Retrieves process execution decision history and formats it for display in the frontend
     *
     * @param int $form_id Gravity Forms form ID
     * @return string Formatted HTML for decision flow summary display
     * @since 1.0.0
     */
    public function get_decision_flow_summary_html($form_id)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Getting decision flow summary for form ' . $form_id);
        }

        // Check configuration and requirements
        $config = $this->database->get_config_by_form_id($form_id);
        if (!$config || !$config->show_decision_flow || !$config->use_process) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN API: Decision flow not available - not using process execution or disabled');
            }

            return $this->get_decision_flow_placeholder();
        }

        // Handle cache busting
        $cache_bust = isset($_GET['cache_bust']) ? sanitize_text_field($_GET['cache_bust']) : '';
        $cache_key = 'operaton_decision_flow_' . $form_id;

        if (empty($cache_bust)) {
            $cached_result = get_transient($cache_key);
            if ($cached_result !== false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN API: Returning cached decision flow for form ' . $form_id);
                }
                return $cached_result;
            }
        } else {
            delete_transient($cache_key);
        }

        // Get process instance ID
        $process_instance_id = $this->database->get_process_instance_id($form_id);
        if (!$process_instance_id) {
            $result = $this->get_decision_flow_loading_message();

            if (empty($cache_bust)) {
                set_transient($cache_key, $result, 60); // Cache for 1 minute
            }
            return $result;
        }

        // Rate limiting for API calls
        $api_cache_key = 'operaton_api_call_' . $process_instance_id;
        if (empty($cache_bust) && get_transient($api_cache_key)) {
            return $this->get_decision_flow_loading_message();
        }

        if (empty($cache_bust)) {
            set_transient($api_cache_key, true, 5); // 5 second rate limit
        }

        // Fetch decision flow data
        $decision_instances = $this->fetch_decision_flow_data($config, $process_instance_id);

        if (is_wp_error($decision_instances)) {
            $result = $this->format_decision_flow_error($decision_instances->get_error_message());

            if (empty($cache_bust)) {
                set_transient($cache_key, $result, 120); // Cache error for 2 minutes
            }
            return $result;
        }

        // Format and cache successful result
        $result = $this->format_decision_flow_summary($decision_instances, $process_instance_id);

        if (empty($cache_bust)) {
            set_transient($cache_key, $result, 600); // Cache for 10 minutes
        }

        return $result;
    }

    // =============================================================================
    // TESTING AND VALIDATION METHODS
    // =============================================================================

    /**
     * Test complete endpoint configuration with minimal DMN payload for validation
     * Sends test data to verify decision key exists and endpoint responds correctly
     *
     * @param string $base_endpoint Base DMN endpoint URL
     * @param string $decision_key Decision definition key to test
     * @return array Test results with success status and detailed messages
     * @since 1.0.0
     */
    public function test_full_endpoint_configuration($base_endpoint, $decision_key)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Testing full endpoint configuration for decision: ' . $decision_key);
        }

        $full_endpoint = $this->build_evaluation_endpoint($base_endpoint, $decision_key);

        // Test with minimal DMN evaluation payload
        $test_data = array(
            'variables' => array(
                'test' => array(
                    'value' => 'test',
                    'type' => 'String'
                )
            )
        );

        // OLD:
        //$response = wp_remote_post($full_endpoint, array(
        //    'headers' => $this->get_api_headers(),
        //    'body' => wp_json_encode($test_data),
        //    'timeout' => 15,
        //    'sslverify' => $this->ssl_verify,
        //));

        // NEW:
        $response = $this->make_optimized_api_call($full_endpoint, $test_data);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Connection failed: %s', 'operaton-dmn'), $response->get_error_message()),
                'endpoint' => $full_endpoint
            );
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        return $this->analyze_test_response($http_code, $body, $full_endpoint);
    }

    // =============================================================================
    // AJAX HANDLERS
    // =============================================================================

    /**
     * AJAX handler for testing DMN endpoint connectivity and basic response validation
     * Validates endpoint accessibility using OPTIONS or HEAD requests for configuration testing
     *
     * @since 1.0.0
     */
    public function ajax_test_endpoint()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Testing endpoint connectivity');
        }

        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'operaton_test_endpoint') || !current_user_can('manage_options')) {
            wp_die(__('Security check failed', 'operaton-dmn'));
        }

        $endpoint = sanitize_url($_POST['endpoint']);

        if (empty($endpoint)) {
            wp_send_json_error(array(
                'message' => __('Endpoint URL is required.', 'operaton-dmn')
            ));
        }

        // Test the endpoint
        $test_result = $this->test_endpoint_connectivity($endpoint);

        if ($test_result['success']) {
            wp_send_json_success($test_result);
        } else {
            wp_send_json_error($test_result);
        }
    }

    /**
     * AJAX handler for comprehensive endpoint configuration testing with DMN payload validation
     * Tests complete endpoint setup including decision key validation and response parsing
     *
     * @since 1.0.0
     */
    public function ajax_test_full_config()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Testing full configuration');
        }

        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'operaton_test_endpoint') || !current_user_can('manage_options')) {
            wp_die(__('Security check failed', 'operaton-dmn'));
        }

        $base_endpoint = sanitize_url($_POST['base_endpoint']);
        $decision_key = sanitize_text_field($_POST['decision_key']);

        if (empty($base_endpoint) || empty($decision_key)) {
            wp_send_json_error(array(
                'message' => __('Both base endpoint and decision key are required.', 'operaton-dmn')
            ));
        }

        $test_result = $this->test_full_endpoint_configuration($base_endpoint, $decision_key);

        if ($test_result['success']) {
            wp_send_json_success($test_result);
        } else {
            wp_send_json_error($test_result);
        }
    }

    /**
     * AJAX handler for clearing WordPress update cache and forcing update checks
     * Removes cached update information to trigger fresh plugin update detection
     *
     * @since 1.0.0
     */
    public function ajax_clear_update_cache()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Clearing update cache');
        }

        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'operaton-dmn')
            ));
        }

        // Clear WordPress update transients
        delete_site_transient('update_plugins');
        delete_transient('operaton_dmn_updater');
        delete_transient('operaton_dmn_fallback_check');

        // Force WordPress to check for updates
        wp_update_plugins();

        wp_send_json_success(array(
            'message' => __('Update cache cleared', 'operaton-dmn')
        ));
    }

    // =============================================================================
    // HELPER AND UTILITY METHODS
    // =============================================================================

    /**
     * Process input variables with type conversion and validation
     * Converts form data to properly typed variables for DMN evaluation
     *
     * @param array $field_mappings Field mapping configuration
     * @param array $form_data Raw form data
     * @return array|WP_Error Processed variables or error
     * @since 1.0.0
     */
    private function process_input_variables($field_mappings, $form_data)
    {
        $variables = array();

        foreach ($field_mappings as $dmn_variable => $form_field) {
            $value = isset($form_data[$dmn_variable]) ? $form_data[$dmn_variable] : null;

            if ($value === null || $value === 'null' || $value === '') {
                $variables[$dmn_variable] = array(
                    'value' => null,
                    'type' => $form_field['type']
                );
                continue;
            }

            // Type conversion with validation
            $converted_value = $this->convert_variable_type($value, $form_field['type'], $dmn_variable);
            if (is_wp_error($converted_value)) {
                return $converted_value;
            }

            $variables[$dmn_variable] = array(
                'value' => $converted_value,
                'type' => $form_field['type']
            );
        }

        return $variables;
    }

    /**
     * Convert variable to the correct type with validation
     * Handles type conversion for DMN variables
     *
     * @param mixed $value Raw value from form
     * @param string $type Target type (Integer, Double, Boolean, String)
     * @param string $variable_name Variable name for error messages
     * @return mixed|WP_Error Converted value or error
     * @since 1.0.0
     */
    private function convert_variable_type($value, $type, $variable_name)
    {
        switch ($type) {
            case 'Integer':
                if (!is_numeric($value)) {
                    return new WP_Error(
                        'invalid_type',
                        sprintf(__('Value for %s must be numeric', 'operaton-dmn'), $variable_name),
                        array('status' => 400)
                    );
                }
                return intval($value);

            case 'Double':
                if (!is_numeric($value)) {
                    return new WP_Error(
                        'invalid_type',
                        sprintf(__('Value for %s must be numeric', 'operaton-dmn'), $variable_name),
                        array('status' => 400)
                    );
                }
                return floatval($value);

            case 'Boolean':
                if (is_string($value)) {
                    $value = strtolower($value);
                    if ($value === 'true' || $value === '1') {
                        return true;
                    } elseif ($value === 'false' || $value === '0') {
                        return false;
                    } else {
                        $converted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if ($converted === null) {
                            return new WP_Error(
                                'invalid_type',
                                sprintf(__('Value for %s must be boolean', 'operaton-dmn'), $variable_name),
                                array('status' => 400)
                            );
                        }
                        return $converted;
                    }
                }
                return (bool) $value;

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Build the full DMN evaluation endpoint URL from base endpoint and decision key
     * Constructs complete evaluation URL following Operaton REST API conventions
     *
     * @param string $base_endpoint Base DMN endpoint URL
     * @param string $decision_key Decision definition key
     * @return string Complete evaluation endpoint URL
     * @since 1.0.0
     */
    private function build_evaluation_endpoint($base_endpoint, $decision_key)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Building evaluation endpoint for decision: ' . $decision_key);
        }

        // Ensure base endpoint ends with /
        if (!empty($base_endpoint) && substr($base_endpoint, -1) !== '/') {
            $base_endpoint .= '/';
        }

        return $base_endpoint . $decision_key . '/evaluate';
    }

    /**
     * Build process execution endpoint URL from base endpoint and process key
     * Constructs complete process start URL following Operaton REST API conventions
     *
     * @param string $base_endpoint Base DMN endpoint URL
     * @param string $process_key Process definition key
     * @return string Complete process start endpoint URL
     * @since 1.0.0
     */
    private function build_process_endpoint($base_endpoint, $process_key)
    {
        // Clean up base endpoint to get engine-rest base
        $base_url = rtrim($base_endpoint, '/');
        $base_url = str_replace('/decision-definition/key', '', $base_url);
        $base_url = str_replace('/decision-definition', '', $base_url);

        if (strpos($base_url, '/engine-rest') === false) {
            $base_url .= '/engine-rest';
        }

        return $base_url . '/process-definition/key/' . $process_key . '/start';
    }

    /**
     * Get process variables from completed or running process instance
     * Retrieves variables from process execution for result extraction
     *
     * @param object $config Configuration object
     * @param string $process_instance_id Process instance identifier
     * @param bool $process_ended Whether process has ended
     * @return array|WP_Error Process variables or error
     * @since 1.0.0
     */
    private function get_process_variables($config, $process_instance_id, $process_ended)
    {
        $base_url = $this->get_engine_rest_base_url($config->dmn_endpoint);

        if ($process_ended) {
            // Process completed immediately - get variables from history
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN API: Process completed immediately, getting variables from history');
            }

            return $this->get_historical_variables($base_url, $process_instance_id);
        } else {
            // Process is still running - wait and try to get active variables
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN API: Process still running, waiting for completion');
            }

            sleep(3); // Wait for process completion

            $active_variables = $this->get_active_process_variables($base_url, $process_instance_id);

            // If active variables failed, try history as fallback
            if (empty($active_variables)) {
                return $this->get_historical_variables($base_url, $process_instance_id);
            }

            return $active_variables;
        }
    }

    /**
     * Get historical variables from completed process
     * Retrieves variables from process history API
     *
     * @param string $base_url Engine REST base URL
     * @param string $process_instance_id Process instance identifier
     * @return array|WP_Error Historical variables or error
     * @since 1.0.0
     */
    private function get_historical_variables($base_url, $process_instance_id)
    {
        $history_endpoint = $base_url . '/history/variable-instance';
        $history_url = $history_endpoint . '?processInstanceId=' . $process_instance_id;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Getting historical variables from: ' . $history_url);
        }

        $response = wp_remote_get($history_url, array(
            'headers' => array('Accept' => 'application/json'),
            'timeout' => 15,
            'sslverify' => $this->ssl_verify,
        ));

        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                sprintf(__('Failed to get historical variables: %s', 'operaton-dmn'), $response->get_error_message()),
                array('status' => 500)
            );
        }

        $history_body = wp_remote_retrieve_body($response);
        $historical_variables = json_decode($history_body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($historical_variables)) {
            return array();
        }

        // Convert historical variables to expected format
        $final_variables = array();
        foreach ($historical_variables as $var) {
            if (isset($var['name']) && array_key_exists('value', $var)) {
                $final_variables[$var['name']] = array(
                    'value' => $var['value'],
                    'type' => isset($var['type']) ? $var['type'] : 'String'
                );
            }
        }

        return $final_variables;
    }

    /**
     * Get active process variables from running process
     * Retrieves variables from active process instance
     *
     * @param string $base_url Engine REST base URL
     * @param string $process_instance_id Process instance identifier
     * @return array Active process variables
     * @since 1.0.0
     */
    private function get_active_process_variables($base_url, $process_instance_id)
    {
        $variables_endpoint = $base_url . '/process-instance/' . $process_instance_id . '/variables';

        $response = wp_remote_get($variables_endpoint, array(
            'headers' => array('Accept' => 'application/json'),
            'timeout' => 15,
            'sslverify' => $this->ssl_verify,
        ));

        if (is_wp_error($response)) {
            return array();
        }

        $variables_body = wp_remote_retrieve_body($response);
        $variables = json_decode($variables_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array();
        }

        return $variables;
    }

    /**
     * Extract results from process variables based on configuration
     * Processes complex variable structures to extract mapped results
     *
     * @param object $config Configuration object
     * @param array $final_variables Process variables
     * @return array Extracted results
     * @since 1.0.0
     */
    private function extract_process_results($config, $final_variables)
    {
        $result_mappings = $this->parse_result_mappings($config);
        $results = array();

        foreach ($result_mappings as $dmn_result_field => $mapping) {
            $result_value = $this->find_result_value($dmn_result_field, $final_variables);

            if ($result_value !== null) {
                // Handle boolean conversion (DMN often returns 1/0 instead of true/false)
                if (is_numeric($result_value) && ($result_value === 1 || $result_value === 0 || $result_value === '1' || $result_value === '0')) {
                    $result_value = (bool) $result_value;
                }

                $results[$dmn_result_field] = array(
                    'value' => $result_value,
                    'field_id' => $mapping['field_id']
                );

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN API: Extracted result for ' . $dmn_result_field . ': ' . print_r($result_value, true));
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN API: No result found for ' . $dmn_result_field);
                }
            }
        }

        return $results;
    }

    /**
     * Find result value in complex variable structures
     * Searches through nested process variables to find result values
     *
     * @param string $field_name Field name to search for
     * @param array $variables Process variables
     * @return mixed|null Found value or null
     * @since 1.0.0
     */
    private function find_result_value($field_name, $variables)
    {
        // Strategy 1: Direct variable access
        if (isset($variables[$field_name]['value'])) {
            return $variables[$field_name]['value'];
        } elseif (isset($variables[$field_name])) {
            return $variables[$field_name];
        }

        // Strategy 2: Search in nested result objects
        $possible_containers = array(
            'heusdenpasResult',
            'kindpakketResult',
            'finalResult',
            'autoApprovalResult',
            'knockoffsResult'
        );

        foreach ($possible_containers as $container) {
            if (isset($variables[$container]['value']) && is_array($variables[$container]['value'])) {
                $container_data = $variables[$container]['value'];

                // Check if it's an array of results
                if (isset($container_data[0]) && is_array($container_data[0])) {
                    if (isset($container_data[0][$field_name])) {
                        return $container_data[0][$field_name];
                    }
                } elseif (isset($container_data[$field_name])) {
                    return $container_data[$field_name];
                }
            }
        }

        // Strategy 3: Comprehensive search through all variables
        foreach ($variables as $var_name => $var_data) {
            if (isset($var_data['value']) && is_array($var_data['value'])) {
                if (isset($var_data['value'][0]) && is_array($var_data['value'][0])) {
                    if (isset($var_data['value'][0][$field_name])) {
                        return $var_data['value'][0][$field_name];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extract results from direct decision evaluation response
     * Processes DMN decision table results based on configuration
     *
     * @param array $result_mappings Result mapping configuration
     * @param array $data API response data
     * @return array Extracted results
     * @since 1.0.0
     */
    private function extract_decision_results($result_mappings, $data)
    {
        $results = array();

        foreach ($result_mappings as $dmn_result_field => $mapping) {
            $result_value = null;

            if (isset($data[0][$dmn_result_field]['value'])) {
                $result_value = $data[0][$dmn_result_field]['value'];
            } elseif (isset($data[0][$dmn_result_field])) {
                $result_value = $data[0][$dmn_result_field];
            }

            if ($result_value !== null) {
                $results[$dmn_result_field] = array(
                    'value' => $result_value,
                    'field_id' => $mapping['field_id']
                );
            }
        }

        return $results;
    }

    /**
     * Test endpoint connectivity using OPTIONS or HEAD requests
     * Basic connectivity test for endpoint validation
     *
     * @param string $endpoint Endpoint URL to test
     * @return array Test results
     * @since 1.0.0
     */
    private function test_endpoint_connectivity($endpoint)
    {
        // Test with OPTIONS request first
        $response = wp_remote_request($endpoint, array(
            'method' => 'OPTIONS',
            'timeout' => 10,
            'sslverify' => $this->ssl_verify,
        ));

        if (is_wp_error($response)) {
            // Try a HEAD request if OPTIONS fails
            $response = wp_remote_head($endpoint, array(
                'timeout' => 10,
                'sslverify' => $this->ssl_verify,
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => sprintf(__('Connection failed: %s', 'operaton-dmn'), $response->get_error_message())
                );
            }
        }

        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code >= 200 && $http_code < 300) {
            return array(
                'success' => true,
                'message' => __('Connection successful! Endpoint is reachable.', 'operaton-dmn')
            );
        } elseif ($http_code === 405) {
            // Method not allowed is actually good - means endpoint exists
            return array(
                'success' => true,
                'message' => __('Endpoint is reachable (Method Not Allowed is expected for evaluation endpoints).', 'operaton-dmn')
            );
        } elseif ($http_code === 404) {
            return array(
                'success' => false,
                'message' => __('Endpoint not found (404). Please check your base URL and decision key.', 'operaton-dmn')
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('Endpoint returned status code: %d. This may indicate a configuration issue.', 'operaton-dmn'), $http_code)
            );
        }
    }

    /**
     * Analyze test response from endpoint validation
     * Interprets HTTP response codes for endpoint testing
     *
     * @param int $http_code HTTP response code
     * @param string $body Response body
     * @param string $endpoint Tested endpoint URL
     * @return array Analysis results
     * @since 1.0.0
     */
    private function analyze_test_response($http_code, $body, $endpoint)
    {
        if ($http_code === 200) {
            return array(
                'success' => true,
                'message' => __('Endpoint is working correctly and accepts DMN evaluations.', 'operaton-dmn'),
                'endpoint' => $endpoint
            );
        } elseif ($http_code === 400) {
            return array(
                'success' => false,
                'message' => __('Endpoint is reachable but decision key may be incorrect or decision table has different input requirements.', 'operaton-dmn'),
                'endpoint' => $endpoint,
                'http_code' => $http_code,
                'response' => $body
            );
        } elseif ($http_code === 404) {
            return array(
                'success' => false,
                'message' => __('Decision not found. Please check your decision key.', 'operaton-dmn'),
                'endpoint' => $endpoint,
                'http_code' => $http_code
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('Unexpected response code: %d', 'operaton-dmn'), $http_code),
                'endpoint' => $endpoint,
                'http_code' => $http_code,
                'response' => substr($body, 0, 200) // Truncate long responses
            );
        }
    }

    /**
     * Fetch decision flow data from Operaton history API
     * Retrieves decision instance history for process execution
     *
     * @param object $config Configuration object
     * @param string $process_instance_id Process instance identifier
     * @return array|WP_Error Decision instances or error
     * @since 1.0.0
     */
    private function fetch_decision_flow_data($config, $process_instance_id)
    {
        $base_url = $this->get_engine_rest_base_url($config->dmn_endpoint);
        $history_endpoint = $base_url . '/history/decision-instance';
        $history_url = $history_endpoint . '?processInstanceId=' . $process_instance_id . '&includeInputs=true&includeOutputs=true';

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Getting decision flow from: ' . $history_url);
        }

        $response = wp_remote_get($history_url, array(
            'headers' => array('Accept' => 'application/json'),
            'timeout' => 15,
            'sslverify' => $this->ssl_verify,
        ));

        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                sprintf(__('Error retrieving decision flow: %s', 'operaton-dmn'), $response->get_error_message())
            );
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($http_code !== 200) {
            return new WP_Error(
                'api_error',
                sprintf(__('Error loading decision flow (HTTP %d). Please try again.', 'operaton-dmn'), $http_code)
            );
        }

        $decision_instances = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'json_error',
                __('Error parsing decision flow data.', 'operaton-dmn')
            );
        }

        return $decision_instances;
    }

    /**
     * Format decision flow summary with Excel-style table layout
     * Creates formatted HTML display of decision instances
     *
     * @param array $decision_instances Decision instance data
     * @param string $process_instance_id Process instance identifier
     * @return string Formatted HTML
     * @since 1.0.0
     */
    private function format_decision_flow_summary($decision_instances, $process_instance_id)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN API: Formatting decision flow summary with ' . count($decision_instances) . ' instances');
        }

        $html = '<h3>🔍 Decision Flow Results Summary</h3>';
        $html .= '<p><strong>Process Instance:</strong> <code>' . esc_html($process_instance_id) . '</code></p>';

        if (empty($decision_instances) || !is_array($decision_instances)) {
            return $html . '<div class="decision-flow-empty"><p><em>No decision instances found for this process.</em></p></div>';
        }

        // Filter and process decision instances
        $filtered_instances = $this->filter_decision_instances($decision_instances);

        if (empty($filtered_instances)) {
            return $html . '<p><em>No relevant decision instances found.</em></p>';
        }

        // Generate summary and main content
        $html .= $this->generate_decision_flow_header($filtered_instances, $decision_instances);
        $html .= $this->generate_decision_flow_tables($filtered_instances);
        $html .= $this->generate_decision_flow_styles();

        return $html;
    }

    /**
     * Filter decision instances for display
     * Applies filtering logic to show most relevant decision instances
     *
     * @param array $decision_instances All decision instances
     * @return array Filtered instances
     * @since 1.0.0
     */
    private function filter_decision_instances($decision_instances)
    {
        // Filter 1: Only get instances from Activity_FinalResultCompilation if available
        $filtered_instances = array();
        $has_final_compilation = false;

        foreach ($decision_instances as $instance) {
            if (isset($instance['activityId']) && $instance['activityId'] === 'Activity_FinalResultCompilation') {
                $filtered_instances[] = $instance;
                $has_final_compilation = true;
            }
        }

        // If no FinalResultCompilation activity, get the latest evaluation for each decision
        if (!$has_final_compilation) {
            $latest_by_decision = array();

            foreach ($decision_instances as $instance) {
                if (isset($instance['decisionDefinitionKey']) && isset($instance['evaluationTime'])) {
                    $key = $instance['decisionDefinitionKey'];
                    $eval_time = $instance['evaluationTime'];

                    if (
                        !isset($latest_by_decision[$key]) ||
                        strtotime($eval_time) > strtotime($latest_by_decision[$key]['evaluationTime'])
                    ) {
                        $latest_by_decision[$key] = $instance;
                    }
                }
            }

            $filtered_instances = array_values($latest_by_decision);
        }

        // Sort by evaluation time
        usort($filtered_instances, function ($a, $b) {
            $timeA = isset($a['evaluationTime']) ? strtotime($a['evaluationTime']) : 0;
            $timeB = isset($b['evaluationTime']) ? strtotime($b['evaluationTime']) : 0;
            return $timeA - $timeB;
        });

        return $filtered_instances;
    }

    /**
     * Generate decision flow header with summary statistics
     * Creates the summary section at the top of the decision flow
     *
     * @param array $filtered_instances Filtered decision instances
     * @param array $all_instances All decision instances
     * @return string Header HTML
     * @since 1.0.0
     */
    private function generate_decision_flow_header($filtered_instances, $all_instances)
    {
        $decisions_by_key = array();
        foreach ($filtered_instances as $instance) {
            if (isset($instance['decisionDefinitionKey'])) {
                $key = $instance['decisionDefinitionKey'];
                if (!isset($decisions_by_key[$key])) {
                    $decisions_by_key[$key] = array();
                }
                $decisions_by_key[$key][] = $instance;
            }
        }

        $has_final_compilation = false;
        foreach ($all_instances as $instance) {
            if (isset($instance['activityId']) && $instance['activityId'] === 'Activity_FinalResultCompilation') {
                $has_final_compilation = true;
                break;
            }
        }

        $html = '<div class="decision-flow-header" style="background: #f0f8ff; padding: 15px; border-radius: 6px; border-left: 4px solid #0073aa; margin-bottom: 20px;">';

        // Summary statistics
        $html .= '<div class="decision-flow-summary-stats" style="margin-bottom: 15px;">';
        $html .= '<h4 style="margin: 0 0 10px 0;">📊 Summary</h4>';
        $html .= '<ul style="margin: 0; padding-left: 20px;">';
        $html .= '<li><strong>Total Decision Types:</strong> ' . count($decisions_by_key) . '</li>';
        $html .= '<li><strong>Total Evaluations Shown:</strong> ' . count($filtered_instances) . '</li>';
        $html .= '<li><strong>Total Available:</strong> ' . count($all_instances) . '</li>';
        $html .= '<li><strong>Filter Applied:</strong> ' . ($has_final_compilation ? 'Activity_FinalResultCompilation only' : 'Latest evaluation per decision') . '</li>';
        $html .= '</ul>';
        $html .= '</div>';

        // Status and refresh button
        $html .= '<p style="margin: 10px 0;"><strong>Showing:</strong> ' . ($has_final_compilation ? 'Final compilation results' : 'Latest evaluation for each decision') . '</p>';
        $html .= '<button type="button" class="button refresh-decision-flow-controlled" data-form-id="8" style="margin-top: 10px;">';
        $html .= '🔄 Refresh Decision Flow';
        $html .= '</button>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate decision flow tables
     * Creates the main Excel-style tables for decision instances
     *
     * @param array $filtered_instances Filtered decision instances
     * @return string Tables HTML
     * @since 1.0.0
     */
    private function generate_decision_flow_tables($filtered_instances)
    {
        $decisions_by_key = array();
        foreach ($filtered_instances as $instance) {
            if (isset($instance['decisionDefinitionKey'])) {
                $key = $instance['decisionDefinitionKey'];
                if (!isset($decisions_by_key[$key])) {
                    $decisions_by_key[$key] = array();
                }
                $decisions_by_key[$key][] = $instance;
            }
        }

        $html = '<div class="decision-flow-tables">';
        $step = 1;

        foreach ($decisions_by_key as $decision_key => $instances) {
            $instance = $instances[0]; // Only show the first instance for each decision

            $html .= '<div class="decision-table-container">';
            $html .= '<h4 class="decision-table-title">' . $step . '. ' . esc_html($decision_key) . '</h4>';
            $html .= $this->generate_decision_table($instance);
            $html .= $this->generate_decision_metadata($instance);
            $html .= '</div>';

            $step++;
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate individual decision table
     * Creates Excel-style table for a single decision instance
     *
     * @param array $instance Decision instance data
     * @return string Table HTML
     * @since 1.0.0
     */
    private function generate_decision_table($instance)
    {
        $html = '<table class="decision-table excel-style">';

        // Header row
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th class="table-header"></th>'; // Empty top-left cell
        $html .= '<th class="table-header">Variable</th>';
        $html .= '<th class="table-header">Value</th>';
        $html .= '</tr>';
        $html .= '</thead>';

        $html .= '<tbody>';

        // INPUTS Section
        if (isset($instance['inputs']) && is_array($instance['inputs']) && count($instance['inputs']) > 0) {
            $html .= $this->generate_table_section($instance['inputs'], 'inputs', '📥 Inputs');
        }

        // OUTPUTS Section
        if (isset($instance['outputs']) && is_array($instance['outputs']) && count($instance['outputs']) > 0) {
            $html .= $this->generate_table_section($instance['outputs'], 'outputs', '📤 Outputs');
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Generate table section for inputs or outputs
     * Creates table rows for a section of decision data
     *
     * @param array $items Input or output items
     * @param string $type Section type (inputs/outputs)
     * @param string $header Section header text
     * @return string Section HTML
     * @since 1.0.0
     */
    private function generate_table_section($items, $type, $header)
    {
        $html = '';
        $item_count = count($items);
        $first_item = true;
        $row_class = $type === 'inputs' ? 'input-row' : 'output-row';
        $header_class = $type === 'inputs' ? 'inputs-header' : 'outputs-header';

        foreach ($items as $item) {
            $html .= '<tr class="' . $row_class . '">';

            if ($first_item) {
                $html .= '<td class="row-header ' . $header_class . '" rowspan="' . $item_count . '">' . $header . '</td>';
                $first_item = false;
            }

            // Variable name
            $name = 'Unknown ' . ucfirst(rtrim($type, 's'));
            if (isset($item['clauseName']) && !empty($item['clauseName'])) {
                $name = $item['clauseName'];
            } elseif (isset($item['variableName']) && !empty($item['variableName'])) {
                $name = $item['variableName'];
            } elseif (isset($item['name']) && !empty($item['name'])) {
                $name = $item['name'];
            }
            $html .= '<td class="variable-cell">' . esc_html($name) . '</td>';

            // Value with enhanced formatting
            $html .= '<td class="value-cell">' . $this->format_decision_value($item) . '</td>';
            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * Format decision value for display
     * Formats values with appropriate styling and icons
     *
     * @param array $item Decision item with value
     * @return string Formatted value HTML
     * @since 1.0.0
     */
    private function format_decision_value($item)
    {
        if (!array_key_exists('value', $item)) {
            return '<em class="no-value">no value</em>';
        }

        $value = $item['value'];

        if (is_null($value) || $value === '') {
            return '<em class="null-value">null</em>';
        } elseif (is_bool($value)) {
            $icon = $value ? '✅' : '❌';
            $text = $value ? 'true' : 'false';
            $class = $value ? 'true' : 'false';
            return '<span class="boolean-value ' . $class . '">' . $icon . ' ' . $text . '</span>';
        } elseif (is_numeric($value)) {
            return '<span class="numeric-value">' . esc_html((string) $value) . '</span>';
        } elseif (is_array($value)) {
            return '<span class="array-value">' . esc_html(json_encode($value)) . '</span>';
        } else {
            return '<span class="string-value">' . esc_html((string) $value) . '</span>';
        }
    }

    /**
     * Generate decision metadata footer
     * Creates metadata section with timestamp and activity info
     *
     * @param array $instance Decision instance data
     * @return string Metadata HTML
     * @since 1.0.0
     */
    private function generate_decision_metadata($instance)
    {
        $html = '<div class="decision-metadata">';

        if (isset($instance['evaluationTime'])) {
            $formatted_time = $this->format_evaluation_time($instance['evaluationTime']);
            $html .= '<small><strong>⏱️ Evaluation Time:</strong> ' . esc_html($formatted_time) . '</small>';
        }

        if (isset($instance['activityId'])) {
            $html .= '<small style="margin-left: 15px;"><strong>🔧 Activity:</strong> ' . esc_html($instance['activityId']) . '</small>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate CSS styles for decision flow display
     * Creates comprehensive CSS for Excel-style decision flow tables
     *
     * @return string CSS styles
     * @since 1.0.0
     */
    private function generate_decision_flow_styles()
    {
        return '<style>
            .decision-flow-tables {
                margin: 20px 0;
            }

            .decision-table-container {
                margin: 25px 0;
                padding: 0;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                overflow: hidden;
            }

            .decision-table-title {
                margin: 0;
                padding: 15px 20px;
                background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
                color: white;
                font-size: 16px;
                font-weight: 600;
                border-bottom: none;
            }

            .decision-table.excel-style {
                width: 100%;
                border-collapse: collapse;
                margin: 0;
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                font-size: 13px;
                background: white;
            }

            .decision-table.excel-style th {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                padding: 12px 15px;
                text-align: left;
                font-weight: 600;
                color: #495057;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .decision-table.excel-style td {
                border: 1px solid #dee2e6;
                padding: 10px 15px;
                vertical-align: top;
                line-height: 1.4;
            }

            .row-header {
                background: #e8f4f8 !important;
                font-weight: 600;
                text-align: center;
                vertical-align: middle !important;
                width: 100px;
                min-width: 100px;
                border-right: 2px solid #0073aa !important;
            }

            .inputs-header {
                color: #0073aa;
            }

            .outputs-header {
                color: #28a745;
            }

            .variable-cell {
                font-weight: 500;
                color: #343a40;
                background: #f8f9fa;
                font-family: "Courier New", monospace;
                width: 250px;
            }

            .value-cell {
                font-family: "Courier New", monospace;
                color: #495057;
                background: white;
            }

            .input-row:hover {
                background: rgba(0, 115, 170, 0.05);
            }

            .output-row:hover {
                background: rgba(40, 167, 69, 0.05);
            }

            .boolean-value.true {
                color: #28a745;
                font-weight: 600;
            }

            .boolean-value.false {
                color: #dc3545;
                font-weight: 600;
            }

            .numeric-value {
                color: #6f42c1;
                font-weight: 600;
            }

            .string-value {
                color: #495057;
            }

            .array-value {
                color: #fd7e14;
                font-style: italic;
            }

            .null-value, .no-value {
                color: #6c757d;
                font-style: italic;
            }

            .decision-metadata {
                padding: 12px 20px;
                background: #f8f9fa;
                border-top: 1px solid #dee2e6;
                font-size: 11px;
                color: #6c757d;
            }

            .decision-metadata small {
                display: inline-block;
            }

            .decision-flow-header {
                border-left: 4px solid #0073aa !important;
            }

            .decision-flow-summary-stats {
                background: rgba(255, 255, 255, 0.8);
                padding: 12px;
                border-radius: 4px;
                border: 1px solid #e0e0e0;
            }

            .decision-flow-summary-stats h4 {
                color: #0073aa;
                font-size: 14px;
                margin: 0 0 8px 0;
            }

            .decision-flow-summary-stats ul {
                margin: 0;
                padding-left: 18px;
                font-size: 13px;
            }

            .decision-flow-summary-stats li {
                margin: 3px 0;
            }

            .refresh-decision-flow-controlled {
                background-color: #0073aa !important;
                border-color: #0073aa !important;
                color: white !important;
                font-size: 12px;
                padding: 8px 16px;
            }

            .refresh-decision-flow-controlled:hover {
                background-color: #005a87 !important;
            }

            @media (max-width: 768px) {
                .decision-table.excel-style {
                    font-size: 11px;
                }

                .decision-table.excel-style th,
                .decision-table.excel-style td {
                    padding: 8px 10px;
                }

                .row-header {
                    width: 80px;
                    min-width: 80px;
                    font-size: 10px;
                }

                .variable-cell {
                    width: 200px;
                }

                .decision-table-title {
                    font-size: 14px;
                    padding: 12px 15px;
                }
            }

            @media print {
                .decision-table-container {
                    break-inside: avoid;
                    box-shadow: none;
                    border: 1px solid #000;
                }

                .refresh-decision-flow-controlled {
                    display: none;
                }
            }
        </style>';
    }

    /**
     * Get placeholder HTML for when decision flow is not available
     * Returns informational message when decision flow cannot be shown
     *
     * @return string Placeholder HTML
     * @since 1.0.0
     */
    private function get_decision_flow_placeholder()
    {
        return '<div class="decision-flow-placeholder">' .
            '<h3>🔍 Decision Flow Results</h3>' .
            '<p><em>Decision flow summary is only available for process execution mode.</em></p>' .
            '</div>';
    }

    /**
     * Get loading message for decision flow
     * Returns loading state HTML for decision flow retrieval
     *
     * @return string Loading HTML
     * @since 1.0.0
     */
    private function get_decision_flow_loading_message()
    {
        return '<div class="decision-flow-placeholder">' .
            '<h3>🔍 Decision Flow Results</h3>' .
            '<p><em>Complete the evaluation on the previous step to see the detailed decision flow summary here.</em></p>' .
            '</div>';
    }

    /**
     * Format decision flow error message
     * Creates error display for decision flow failures
     *
     * @param string $error_message Error message to display
     * @return string Error HTML
     * @since 1.0.0
     */
    private function format_decision_flow_error($error_message)
    {
        return '<div class="decision-flow-error">' .
            '<h3>🔍 Decision Flow Results</h3>' .
            '<p><em>Error retrieving decision flow: ' . esc_html($error_message) . '</em></p>' .
            '</div>';
    }

    /**
     * Parse result mappings from configuration
     * Extracts and validates result mappings configuration
     *
     * @param object $config Configuration object
     * @return array Parsed result mappings
     * @since 1.0.0
     */
    private function parse_result_mappings($config)
    {
        $result_mappings = json_decode($config->result_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array();
        }
        return $result_mappings;
    }

    /**
     * Get engine REST base URL from endpoint configuration
     * Extracts base engine-rest URL from various endpoint formats
     *
     * @param string $endpoint_url Configured endpoint URL
     * @return string Base engine-rest URL
     * @since 1.0.0
     */
    private function get_engine_rest_base_url($endpoint_url)
    {
        $base_url = rtrim($endpoint_url, '/');
        $base_url = str_replace('/decision-definition/key', '', $base_url);
        $base_url = str_replace('/decision-definition', '', $base_url);

        if (strpos($base_url, '/engine-rest') === false) {
            $base_url .= '/engine-rest';
        }

        return $base_url;
    }

    /**
     * Format evaluation time for display with timezone handling
     * Converts ISO timestamps to WordPress timezone for user-friendly display
     *
     * @param string $iso_timestamp ISO format timestamp from Operaton API
     * @return string Formatted timestamp in site timezone
     * @since 1.0.0
     */
    private function format_evaluation_time($iso_timestamp)
    {
        if (empty($iso_timestamp)) {
            return 'Unknown';
        }

        try {
            // Parse the ISO timestamp
            $datetime = new DateTime($iso_timestamp);

            // Convert to WordPress site timezone
            $wp_timezone = wp_timezone();
            $datetime->setTimezone($wp_timezone);

            // Format in a user-friendly way
            $formatted_date = $datetime->format('Y-m-d H:i:s');
            $timezone_name = $datetime->format('T');

            return $formatted_date . ' (' . $timezone_name . ')';
        } catch (Exception $e) {
            // Fallback: just clean up the original timestamp
            return str_replace(['T', '+0000'], [' ', ' UTC'], $iso_timestamp);
        }
    }

    /**
     * Get standard API headers for Operaton requests
     * Returns consistent headers for all API calls
     *
     * @return array API headers
     * @since 1.0.0
     */
    private function get_api_headers()
    {
        return array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; Operaton DMN Plugin/' . OPERATON_DMN_VERSION
        );
    }

    /**
     * Check if debug mode is enabled
     * Determines if debug information should be included in responses
     *
     * @return bool True if debug mode is enabled
     * @since 1.0.0
     */
    private function get_debug_info()
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Set API timeout for external requests
     * Allows configuration of API timeout settings
     *
     * @param int $timeout Timeout in seconds
     * @since 1.0.0
     */
    public function set_api_timeout($timeout)
    {
        $this->api_timeout = max(5, min(60, intval($timeout))); // Between 5 and 60 seconds
    }

    /**
     * Set SSL verification setting
     * Allows configuration of SSL verification for API calls
     *
     * @param bool $verify Whether to verify SSL certificates
     * @since 1.0.0
     */
    public function set_ssl_verify($verify)
    {
        $this->ssl_verify = (bool) $verify;
    }

    /**
     * Get API configuration status
     * Returns current API configuration for debugging
     *
     * @return array API configuration status
     * @since 1.0.0
     */
    public function get_api_status()
    {
        return array(
            'timeout' => $this->api_timeout,
            'ssl_verify' => $this->ssl_verify,
            'debug_enabled' => $this->get_debug_info(),
            'hooks_registered' => array(
                'rest_api_init' => has_action('rest_api_init', array($this, 'register_rest_routes')),
                'ajax_handlers' => array(
                    'test_endpoint' => has_action('wp_ajax_operaton_test_endpoint', array($this, 'ajax_test_endpoint')),
                    'test_full_config' => has_action('wp_ajax_operaton_test_full_config', array($this, 'ajax_test_full_config')),
                    'clear_update_cache' => has_action('wp_ajax_operaton_clear_update_cache', array($this, 'ajax_clear_update_cache'))
                )
            )
        );
    }

    /**
     * Validate API configuration
     * Checks if API is properly configured
     *
     * @return array|WP_Error Validation results or error
     * @since 1.0.0
     */
    public function validate_configuration()
    {
        $issues = array();

        // Check database connection
        if (!$this->database) {
            $issues[] = __('Database manager not initialized', 'operaton-dmn');
        }

        // Check core plugin connection
        if (!$this->core) {
            $issues[] = __('Core plugin instance not available', 'operaton-dmn');
        }

        // Check if REST API is accessible
        $rest_url = rest_url('operaton-dmn/v1/test');
        $response = wp_remote_get($rest_url, array('timeout' => 5));

        if (is_wp_error($response)) {
            $issues[] = sprintf(__('REST API not accessible: %s', 'operaton-dmn'), $response->get_error_message());
        }

        if (!empty($issues)) {
            return new WP_Error('api_configuration_error', implode('; ', $issues), $issues);
        }

        return array(
            'status' => 'ok',
            'message' => __('API configuration is valid', 'operaton-dmn'),
            'endpoints' => array(
                'evaluate' => rest_url('operaton-dmn/v1/evaluate'),
                'test' => rest_url('operaton-dmn/v1/test'),
                'decision_flow' => rest_url('operaton-dmn/v1/decision-flow/{form_id}')
            )
        );
    }

    /**
     * Get core plugin instance for external access
     * Provides access to core plugin functionality
     *
     * @return OperatonDMNEvaluator Core plugin instance
     * @since 1.0.0
     */
    public function get_core_instance()
    {
        return $this->core;
    }

    /**
     * Get database manager instance for external access
     * Provides access to database functionality
     *
     * @return Operaton_DMN_Database Database manager instance
     * @since 1.0.0
     */
    public function get_database_instance()
    {
        return $this->database;
    }

    /**
     * AJAX handler for debug tests
     */
    public function run_operaton_dmn_debug()
    {
        if (!current_user_can('manage_options'))
        {
            wp_send_json_error('Unauthorized');
            return;
        }

        try
        {
            error_log("Starting Operaton DMN REST API Debug Session");

            $results = array();
            $results['server_config'] = $this->test_server_config();
            $results['plugin_init'] = $this->test_plugin_initialization();
            $results['rest_api'] = $this->test_rest_api_availability();
            $results['api_call'] = $this->test_rest_api_call();

            error_log("=== END OPERATON DMN DEBUG ===");

            wp_send_json_success(array(
                'message' => 'Debug completed successfully',
                'results' => $results,
                'check_logs' => 'See error log for detailed output'
            ));
        }
        catch (Exception $e)
        {
            error_log("Debug error: " . $e->getMessage());
            wp_send_json_error('Debug failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX request for DMN debug tests
     */
    public function handle_dmn_debug_ajax()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce'))
        {
            wp_send_json_error('Invalid nonce');
        }

        // Check permissions
        if (!current_user_can('manage_options'))
        {
            wp_send_json_error('Insufficient permissions');
        }

        try
        {
            // Call your existing debug method and capture any output
            ob_start();
            $this->run_operaton_dmn_debug();
            $debug_output = ob_get_clean();

            // Also get the debug results if your method returns them
            // You might want to modify run_operaton_dmn_debug to return structured data

            wp_send_json_success([
                'message' => 'Debug completed successfully',
                'check_logs' => 'See error log for detailed output',
                'timestamp' => current_time('mysql'),
                'results' => [
                    'server_config' => [
                        'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled',
                        'curl_available' => function_exists('curl_init') ? 'Available' : 'Not Available',
                        'openssl_loaded' => extension_loaded('openssl') ? 'Available' : 'Not Available'
                    ],
                    'plugin_init' => [
                        'api_manager_class' => class_exists('OperatonDMNAPI'),
                        'handle_evaluation_method' => method_exists($this, 'handle_evaluation'),
                        'health_check_method' => method_exists($this, 'health_check')
                    ],
                    'rest_api' => rest_url('operaton-dmn/v1/') ? true : false,
                    'api_call' => $this->test_rest_api_call()
                ]
            ]);
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN Debug AJAX Error: ' . $e->getMessage());
            wp_send_json_error('Debug test execution failed: ' . $e->getMessage());
        }
    }

    private function test_server_config()
    {
        error_log("=== SERVER CONFIGURATION DEBUG ===");

        $config = array(
            'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled',
            'curl_available' => function_exists('curl_init') ? 'Available' : 'Not available',
            'openssl_loaded' => extension_loaded('openssl') ? 'Available' : 'Not available'
        );

        foreach ($config as $key => $value)
        {
            error_log("$key: $value");
        }

        return $config;
    }

    private function test_plugin_initialization()
    {
        error_log("=== PLUGIN INITIALIZATION DEBUG ===");

        $status = array(
            'api_manager_class' => class_exists('OperatonDMNApiManager'),
            'health_check_method' => method_exists($this, 'health_check'),
            'handle_evaluation_method' => method_exists($this, 'handle_evaluation')
        );

        foreach ($status as $check => $result)
        {
            error_log("$check: " . ($result ? 'YES' : 'NO'));
        }

        return $status;
    }

    private function test_rest_api_availability()
    {
        error_log("=== REST API AVAILABILITY DEBUG ===");

        if (!function_exists('rest_get_url_prefix'))
        {
            error_log("ERROR: WordPress REST API not available");
            return false;
        }

        $rest_server = rest_get_server();
        $namespaces = $rest_server->get_namespaces();
        $has_operaton = in_array('operaton-dmn/v1', $namespaces);

        error_log("Available namespaces: " . implode(', ', $namespaces));
        error_log("Operaton namespace registered: " . ($has_operaton ? 'YES' : 'NO'));

        return $has_operaton;
    }

    private function test_rest_api_call()
    {
        error_log("=== REST API CALL TEST ===");

        $test_url = home_url('/wp-json/operaton-dmn/v1/test');
        $response = wp_remote_get($test_url);

        if (is_wp_error($response))
        {
            error_log("REST API Error: " . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        error_log("REST API Response Status: " . $status_code);
        error_log("REST API Response Body: " . $body);

        return $status_code === 200;
    }
}
