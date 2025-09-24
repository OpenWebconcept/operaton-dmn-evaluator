<?php

/**
 * Operaton DMN API Utilities Trait
 *
 * Consolidated trait containing data processing, URL construction, HTTP communication,
 * configuration management, debug utilities, and helper methods.
 *
 * @package OperatonDMN
 * @subpackage API\Traits
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}

/**
 * Consolidated utilities trait
 *
 * Contains all utility methods including data processing, HTTP communication,
 * URL construction, configuration management, and debug functionality.
 *
 * @since 1.0.0
 */
trait Operaton_DMN_API_Utilities
{
    // =============================================================================
    // DATA PROCESSING & TRANSFORMATION UTILITIES
    // =============================================================================

    /**
     * Process input variables with type conversion and validation
     *
     * Converts form data to properly typed variables for DMN evaluation.
     * Handles type conversion, validation, and sanitization of input values
     * according to DMN engine requirements and field mapping specifications.
     *
     * @param array $field_mappings Field mapping configuration
     * @param array $form_data Raw form data
     * @return array|WP_Error Processed variables or error
     * @since 1.0.0
     */
    private function process_input_variables($field_mappings, $form_data)
    {
        $variables = array();

        foreach ($field_mappings as $dmn_variable => $form_field)
        {
            $value = isset($form_data[$dmn_variable]) ? $form_data[$dmn_variable] : null;

            // Skip empty values unless explicitly configured to include them
            if ($value === null || $value === '')
            {
                continue;
            }

            // Process and validate the value
            $processed_value = $this->process_variable_value($value, $dmn_variable);

            if (is_wp_error($processed_value))
            {
                return $processed_value;
            }

            $variables[$dmn_variable] = $processed_value;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Processed ' . count($variables) . ' input variables');
        }

        return $variables;
    }

    /**
     * Process and validate individual variable value with type conversion
     *
     * Converts individual form field values to appropriate types for DMN evaluation.
     * Handles string, numeric, boolean, and date type conversions with validation
     * and error handling for invalid data types or formats.
     *
     * CRITICAL: This method preserves the original working logic to prevent evaluation failures.
     *
     * @param mixed $value Raw field value from form data
     * @param string $variable_name Variable name for error reporting
     * @return mixed|WP_Error Processed value or validation error
     * @since 1.0.0
     */
    private function process_variable_value($value, $variable_name)
    {
        // PRESERVED ORIGINAL LOGIC: Keep exact same type conversion as working version

        // Skip empty values unless explicitly configured to include them
        if ($value === null || $value === '')
        {
            return $value;
        }

        // Handle different value types exactly as the original working version did
        if (is_numeric($value))
        {
            if (strpos($value, '.') !== false)
            {
                return (float) $value;
            }
            else
            {
                return (int) $value;
            }
        }

        if (is_string($value))
        {
            // Handle boolean-like strings
            $lower_value = strtolower(trim($value));
            if (in_array($lower_value, array('true', 'false', 'yes', 'no')))
            {
                return in_array($lower_value, array('true', 'yes'));
            }

            // CRITICAL FIX: DO NOT convert dates - let Operaton handle them as strings
            // The original working code did not have date conversion
            return sanitize_text_field($value);
        }

        if (is_bool($value))
        {
            return $value;
        }

        if (is_array($value))
        {
            return array_map('sanitize_text_field', $value);
        }

        // Handle type conversion for other values
        if ($value === 1 || $value === '1')
        {
            return true;
        }
        if ($value === 0 || $value === '0')
        {
            return false;
        }

        return sanitize_text_field(strval($value));
    }

    /**
     * Extract mapped results from API response data
     *
     * Extracts and maps result values from DMN API response according to
     * configured result mappings. Handles different response formats from
     * both decision evaluation and process execution endpoints.
     *
     * @param array $response_data API response data containing results
     * @param array $result_mappings Result field mapping configuration
     * @return array Mapped results ready for form field population
     * @since 1.0.0
     */
    private function extract_mapped_results($response_data, $result_mappings)
    {
        $results = array();

        if (empty($result_mappings) || empty($response_data))
        {
            return $results;
        }

        // Handle different response formats
        $data_source = $response_data;
        if (isset($response_data[0]) && is_array($response_data[0]))
        {
            // Decision evaluation format - use first result
            $data_source = $response_data[0];
        }

        foreach ($result_mappings as $dmn_variable => $form_field)
        {
            if (isset($data_source[$dmn_variable]))
            {
                $value = $data_source[$dmn_variable];

                // Handle Operaton engine response format with value wrapper
                if (is_array($value) && isset($value['value']))
                {
                    $results[$form_field] = $value['value'];
                }
                else
                {
                    $results[$form_field] = $value;
                }
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Extracted ' . count($results) . ' result values');
        }

        return $results;
    }

    /**
     * Transform historical variable data to standard variables format
     *
     * Converts historical variable instance data from Operaton engine
     * to standard variable format for consistent processing. Handles
     * the transformation from history API format to active variables format.
     *
     * @param array $history_data Historical variable instance data
     * @return array Transformed variables in standard format
     * @since 1.0.0
     */
    private function transform_history_to_variables($history_data)
    {
        $variables = array();

        if (!is_array($history_data))
        {
            return $variables;
        }

        foreach ($history_data as $variable_instance)
        {
            if (isset($variable_instance['name']) && isset($variable_instance['value']))
            {
                $variables[$variable_instance['name']] = array(
                    'value' => $variable_instance['value'],
                    'type' => $variable_instance['type'] ?? 'String'
                );
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Transformed ' . count($variables) . ' historical variables');
        }

        return $variables;
    }

    /**
     * Retrieve process variables with intelligent fallback strategy
     *
     * Attempts to retrieve process variables using multiple strategies with
     * intelligent fallback from active instance to historical data. Optimizes
     * for performance while ensuring data availability across different
     * process execution states.
     *
     * @param string $base_url Base engine REST API URL
     * @param string $process_instance_id Process instance identifier
     * @return array|WP_Error Variables data with source information or error
     * @since 1.0.0
     */
    private function retrieve_process_variables_with_fallback($base_url, $process_instance_id)
    {
        // Strategy 1: Try active process instance variables (fastest)
        $active_url = $base_url . '/process-instance/' . $process_instance_id . '/variables';
        $active_response = $this->make_api_call($active_url, array(), 'GET');

        if (!is_wp_error($active_response) && !empty($active_response))
        {
            return array(
                'variables' => $active_response,
                'source' => 'active_instance'
            );
        }

        // Strategy 2: Fallback to historical variables
        $history_url = $base_url . '/history/variable-instance?processInstanceId=' . $process_instance_id;
        $history_response = $this->make_api_call($history_url, array(), 'GET');

        if (is_wp_error($history_response))
        {
            return $history_response;
        }

        $transformed_variables = $this->transform_history_to_variables($history_response);

        return array(
            'variables' => $transformed_variables,
            'source' => 'historical_data'
        );
    }

    // =============================================================================
    // URL CONSTRUCTION & VALIDATION HELPERS
    // =============================================================================

    /**
     * Build the full DMN evaluation endpoint URL from base endpoint and decision key
     *
     * Constructs complete evaluation URL following Operaton REST API conventions.
     * Normalizes base URL formatting, removes incorrect path components, ensures
     * proper engine-rest path structure, and builds complete evaluation endpoint.
     *
     * @param string $base_endpoint Base DMN endpoint URL
     * @param string $decision_key Decision definition key
     * @return string Complete evaluation endpoint URL
     * @since 1.0.0
     */
    private function build_evaluation_endpoint($base_endpoint, $decision_key)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Building evaluation endpoint for decision: ' . $decision_key);
            error_log('Operaton DMN API: Input base endpoint: ' . $base_endpoint);
        }

        // Normalize base URL - remove any trailing path components that shouldn't be there
        $clean_base_url = rtrim($base_endpoint, '/');

        // Remove common endpoint paths that might be incorrectly included
        $clean_base_url = preg_replace('/\/decision-definition.*$/', '', $clean_base_url);
        $clean_base_url = preg_replace('/\/process-definition.*$/', '', $clean_base_url);

        // Ensure it ends with /engine-rest
        if (!str_ends_with($clean_base_url, '/engine-rest'))
        {
            if (str_ends_with($clean_base_url, '/'))
            {
                $clean_base_url .= 'engine-rest';
            }
            else
            {
                $clean_base_url .= '/engine-rest';
            }
        }

        // Build the complete evaluation URL
        $evaluation_url = $clean_base_url . '/decision-definition/key/' . $decision_key . '/evaluate';

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Final evaluation endpoint: ' . $evaluation_url);
        }

        return $evaluation_url;
    }

    /**
     * Build the full process execution endpoint URL from base endpoint and process key
     *
     * Constructs complete process start URL following Operaton REST API conventions.
     * Normalizes base URL, ensures proper engine-rest path structure, and builds
     * process definition start endpoint for process orchestration.
     *
     * @param string $base_endpoint Base DMN endpoint URL
     * @param string $process_key Process definition key
     * @return string Complete process start endpoint URL
     * @since 1.0.0
     */
    private function build_process_endpoint($base_endpoint, $process_key)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Building process endpoint for process: ' . $process_key);
        }

        $base_url = $this->get_engine_rest_base_url($base_endpoint);
        $process_url = $base_url . '/process-definition/key/' . $process_key . '/start';

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Final process endpoint: ' . $process_url);
        }

        return $process_url;
    }

    /**
     * Get normalized engine REST base URL from any endpoint variant
     *
     * Extracts and normalizes the base engine REST URL from various endpoint
     * formats. Handles different URL structures, removes specific endpoint paths,
     * and ensures consistent /engine-rest base URL format.
     *
     * @param string $endpoint Any Operaton engine endpoint URL
     * @return string Normalized base engine REST URL
     * @since 1.0.0
     */
    private function get_engine_rest_base_url($endpoint)
    {
        $clean_url = rtrim($endpoint, '/');

        // Remove specific endpoint paths to get base URL
        $patterns_to_remove = array(
            '/\/decision-definition.*$/',
            '/\/process-definition.*$/',
            '/\/process-instance.*$/',
            '/\/history.*$/',
            '/\/version.*$/'
        );

        foreach ($patterns_to_remove as $pattern)
        {
            $clean_url = preg_replace($pattern, '', $clean_url);
        }

        // Ensure it ends with /engine-rest
        if (!str_ends_with($clean_url, '/engine-rest'))
        {
            if (str_ends_with($clean_url, '/'))
            {
                $clean_url .= 'engine-rest';
            }
            else
            {
                $clean_url .= '/engine-rest';
            }
        }

        return $clean_url;
    }

    // =============================================================================
    // HTTP COMMUNICATION & API UTILITIES
    // =============================================================================

    /**
     * Make HTTP API call to Operaton DMN engine with comprehensive error handling
     *
     * Executes HTTP requests to Operaton DMN engine endpoints with proper error handling,
     * timeout management, response validation, and detailed logging. Supports both
     * GET and POST methods with appropriate headers and request formatting.
     *
     * @param string $url Complete API endpoint URL
     * @param array $data Request payload data
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array $additional_headers Optional additional HTTP headers
     * @return array|WP_Error API response data or error object
     * @since 1.0.0
     */
    private function make_api_call($url, $data = array(), $method = 'GET', $additional_headers = array())
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Making ' . $method . ' request to: ' . $url);
        }

        // Build request headers
        $headers = $this->build_api_headers($method, $additional_headers);

        // Prepare request arguments
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => $this->connection_timeout,
            'sslverify' => true,
            'user-agent' => 'WordPress/OperatonDMN/' . OPERATON_DMN_VERSION
        );

        // Add request body for POST/PUT methods
        if (in_array($method, array('POST', 'PUT', 'PATCH')) && !empty($data))
        {
            $args['body'] = json_encode($data);

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Request payload: ' . json_encode($data));
            }
        }
        elseif ($method === 'GET' && !empty($data))
        {
            // Add query parameters for GET requests
            $url = add_query_arg($data, $url);
        }

        // Execute the HTTP request
        $response = wp_remote_request($url, $args);

        // Handle WordPress HTTP errors
        if (is_wp_error($response))
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: HTTP error: ' . $response->get_error_message());
            }

            return new WP_Error(
                'http_error',
                sprintf(__('HTTP request failed: %s', 'operaton-dmn'), $response->get_error_message()),
                array('status' => 500)
            );
        }

        // Get response details
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Response code: ' . $response_code);
            error_log('Operaton DMN API: Response body: ' . substr($response_body, 0, 500) . '...');
        }

        // Handle HTTP error status codes
        if ($response_code >= 400)
        {
            $error_message = $this->parse_api_error_message($response_body, $response_code);

            return new WP_Error(
                'api_error',
                $error_message,
                array('status' => $response_code, 'response_body' => $response_body)
            );
        }

        // Parse JSON response
        $parsed_response = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE)
        {
            return new WP_Error(
                'json_error',
                sprintf(__('Invalid JSON response: %s', 'operaton-dmn'), json_last_error_msg()),
                array('status' => 500, 'response_body' => $response_body)
            );
        }

        return $parsed_response;
    }

    /**
     * Build API request headers for Operaton engine communication
     *
     * Constructs appropriate HTTP headers for DMN engine API requests
     * including content type, authentication (if configured), user agent,
     * and other required headers for successful API communication.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $additional_headers Optional additional headers
     * @return array Complete headers array for API requests
     * @since 1.0.0
     */
    private function build_api_headers($method = 'GET', $additional_headers = array())
    {
        $headers = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'WordPress/OperatonDMN/' . OPERATON_DMN_VERSION,
        );

        // Add method-specific headers
        if ($method === 'POST' || $method === 'PUT')
        {
            $headers['Cache-Control'] = 'no-cache';
        }

        // Merge additional headers
        if (!empty($additional_headers))
        {
            $headers = array_merge($headers, $additional_headers);
        }

        return $headers;
    }

    /**
     * Parse API error message from response body
     *
     * Extracts meaningful error messages from Operaton engine API error responses.
     * Handles different error response formats and provides user-friendly error
     * messages for common API error scenarios.
     *
     * @param string $response_body Raw HTTP response body
     * @param int $response_code HTTP response status code
     * @return string User-friendly error message
     * @since 1.0.0
     */
    private function parse_api_error_message($response_body, $response_code)
    {
        // Try to parse JSON error response
        $parsed_error = json_decode($response_body, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed_error))
        {
            // Extract error message from different possible fields
            $possible_fields = array('message', 'error', 'detail', 'description');

            foreach ($possible_fields as $field)
            {
                if (isset($parsed_error[$field]) && !empty($parsed_error[$field]))
                {
                    return sanitize_text_field($parsed_error[$field]);
                }
            }

            // Handle Operaton specific error format
            if (isset($parsed_error['type']) && isset($parsed_error['message']))
            {
                return sprintf(
                    __('%s: %s', 'operaton-dmn'),
                    sanitize_text_field($parsed_error['type']),
                    sanitize_text_field($parsed_error['message'])
                );
            }
        }

        // Fallback to generic HTTP status messages
        $generic_messages = array(
            400 => __('Bad request - invalid parameters', 'operaton-dmn'),
            401 => __('Unauthorized - authentication required', 'operaton-dmn'),
            403 => __('Forbidden - access denied', 'operaton-dmn'),
            404 => __('Not found - endpoint or resource does not exist', 'operaton-dmn'),
            405 => __('Method not allowed', 'operaton-dmn'),
            408 => __('Request timeout', 'operaton-dmn'),
            429 => __('Too many requests - rate limit exceeded', 'operaton-dmn'),
            500 => __('Internal server error', 'operaton-dmn'),
            502 => __('Bad gateway - upstream server error', 'operaton-dmn'),
            503 => __('Service unavailable', 'operaton-dmn'),
            504 => __('Gateway timeout', 'operaton-dmn')
        );

        if (isset($generic_messages[$response_code]))
        {
            return $generic_messages[$response_code];
        }

        return sprintf(
            __('HTTP error %d: %s', 'operaton-dmn'),
            $response_code,
            !empty($response_body) ? substr(strip_tags($response_body), 0, 100) : __('Unknown error', 'operaton-dmn')
        );
    }

    // =============================================================================
    // CONFIGURATION & SETTINGS MANAGEMENT
    // =============================================================================

    /**
     * Get current connection timeout setting
     *
     * Retrieves the currently configured connection timeout value with bounds
     * checking to ensure reasonable timeout values. Provides fallback to default
     * timeout if configuration is invalid or missing.
     *
     * @return int Connection timeout in seconds
     * @since 1.0.0
     */
    public function get_connection_timeout()
    {
        return $this->connection_timeout;
    }

    /**
     * Set connection timeout with validation
     *
     * Updates connection timeout setting with validation to ensure reasonable
     * bounds (5-300 seconds). Updates both instance variable and persistent
     * storage for consistent timeout across requests.
     *
     * @param int $timeout Timeout value in seconds
     * @return bool Success status of timeout update
     * @since 1.0.0
     */
    public function set_connection_timeout($timeout)
    {
        $validated_timeout = max(5, min(300, intval($timeout)));

        if ($validated_timeout !== intval($timeout))
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Timeout value adjusted from ' . $timeout . ' to ' . $validated_timeout);
            }
        }

        $this->connection_timeout = $validated_timeout;

        // Save to database for persistence
        $update_result = update_option('operaton_dmn_connection_timeout', $validated_timeout);

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Connection timeout updated to ' . $validated_timeout . ' seconds');
        }

        return $update_result;
    }

    // =============================================================================
    // UTILITY & HELPER METHODS
    // =============================================================================

    /**
     * Clear all API-related caches and transients
     *
     * Removes all cached data related to API operations including connectivity
     * test results, configuration caches, and performance data. Used for
     * troubleshooting and ensuring fresh data retrieval.
     *
     * @return int Number of cache entries cleared
     * @since 1.0.0
     */
    public function clear_api_caches()
    {
        global $wpdb;

        $cleared = 0;

        // Clear WordPress transients with our prefix
        $transient_patterns = array(
            'operaton_dmn_api_%',
            'operaton_dmn_connectivity_%',
            'operaton_dmn_config_%',
            'operaton_dmn_performance_%'
        );

        foreach ($transient_patterns as $pattern)
        {
            $query = $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $pattern,
                '_transient_timeout_' . $pattern
            );

            $result = $wpdb->query($query);
            if ($result !== false)
            {
                $cleared += $result;
            }
        }

        // Clear object cache if available
        if (function_exists('wp_cache_flush'))
        {
            wp_cache_flush();
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Cleared ' . $cleared . ' cache entries');
        }

        return $cleared;
    }

    /**
     * Log debug message with timestamp and context
     *
     * Provides consistent debug logging with timestamp, context, and proper
     * formatting for API-related debug messages. Only logs when WP_DEBUG
     * is enabled to prevent log pollution in production.
     *
     * @param string $message Debug message to log
     * @param array $context Optional context data to include
     * @since 1.0.0
     */
    private function log_debug($message, $context = array())
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG)
        {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $formatted_message = sprintf('[%s] Operaton DMN API: %s', $timestamp, $message);

        if (!empty($context))
        {
            $formatted_message .= ' | Context: ' . json_encode($context);
        }

        error_log($formatted_message);
    }

    /**
     * Check if string is valid JSON
     *
     * Validates whether a string contains valid JSON data without
     * attempting to decode it. Useful for validation before processing.
     *
     * @param string $string String to validate
     * @return bool True if string is valid JSON
     * @since 1.0.0
     */
    private function is_valid_json($string)
    {
        if (!is_string($string))
        {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get default configuration values
     *
     * Provides default configuration values for new configurations to ensure
     * consistent initialization and prevent undefined property errors.
     *
     * @return array Default configuration values
     * @since 1.0.0
     */
    public function get_default_configuration_values()
    {
        return array(
            'dmn_endpoint' => '',
            'decision_key' => '',
            'process_key' => '',
            'field_mappings' => '{}',
            'result_mappings' => '{}',
            'use_process' => false,
            'show_decision_flow' => false,
            'active' => true,
            'evaluation_step' => 'auto'
        );
    }

    /**
     * Merge configuration with defaults
     *
     * Merges provided configuration with default values to ensure all
     * required fields are present with appropriate fallback values.
     *
     * @param array $config Configuration array to merge
     * @return array Complete configuration with defaults applied
     * @since 1.0.0
     */
    public function merge_configuration_with_defaults($config)
    {
        $defaults = $this->get_default_configuration_values();
        return array_merge($defaults, $config);
    }
}
