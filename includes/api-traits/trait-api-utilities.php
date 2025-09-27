<?php

/**
 * Utilities and helper methods trait for Operaton DMN Plugin
 *
 * Contains all utility methods including connection pooling, HTTP optimization,
 * data processing helpers, and configuration management functions.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}

trait Operaton_DMN_API_Utilities
{
    /**
     * Get optimized HTTP client options with connection reuse
     *
     * @param string $endpoint_url Full endpoint URL
     * @return array HTTP client options optimized for connection reuse
     * @since 1.0.0
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

            operaton_debug_verbose('API', 'Reusing connection for host', ['host' => $host]);

            return $this->get_cached_connection_options($connection_key);
        }

        // Create new optimized connection
        self::$pool_stats['misses']++;
        self::$pool_stats['created']++;
        $this->update_connection_stats('misses'); // Wordpress persistence for admin dashboard
        $this->update_connection_stats('created');

        operaton_debug_verbose('API', 'Creating new connection for host', ['host' => $host]);

        $options = $this->create_optimized_connection_options($host);
        $this->cache_connection($connection_key, $options);

        return $options;
    }

    /**
     * Create optimized HTTP connection options
     *
     * @param string $host Hostname
     * @return array Optimized HTTP options
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
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
     * @since 1.0.0
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
     *
     * @since 1.0.0
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
            operaton_debug_verbose('API', 'Cleaned old connections', ['count' => $cleaned]);
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
     * @since 1.0.0
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
     * Set connection pool timeout from admin setting
     *
     * @param int $timeout Timeout in seconds
     * @since 1.0.0
     */
    public function set_connection_pool_timeout($timeout)
    {
        $this->connection_max_age = max(60, min(1800, intval($timeout)));

        operaton_debug_verbose('API', 'Connection pool timeout updated', ['timeout_seconds' => $this->connection_max_age]);

        // Clear existing connections to apply new timeout immediately
        $cleared = $this->clear_connection_pool();

        operaton_debug_verbose('API', 'Cleared connections to apply new timeout', ['count' => $cleared]);
    }

    /**
     * Clear connection pool cache for testing/debugging
     * Add this method for manual cache management
     *
     * @return int Number of connections cleared
     * @since 1.0.0
     */
    public function clear_connection_pool_for_batching()
    {
        $cleared = $this->clear_connection_pool();

        operaton_debug_verbose('API', 'Cleared connection pool for batching optimization', ['count' => $cleared]);

        return $cleared;
    }

    /**
     * Get connection pool statistics with WordPress persistence
     *
     * @return array Connection pool statistics and details
     * @since 1.0.0
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
     *
     * @param string $type Type of statistic to update
     * @since 1.0.0
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
     *
     * @return int Number of connections cleared
     * @since 1.0.0
     */
    public function clear_connection_pool()
    {
        $cleared = count(self::$connection_pool);
        self::$connection_pool = array();
        self::$pool_stats['cleaned'] += $cleared;

        operaton_debug_verbose('API', 'Manually cleared connections', ['count' => $cleared]);

        return $cleared;
    }

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

        foreach ($field_mappings as $dmn_variable => $form_field)
        {
            $value = isset($form_data[$dmn_variable]) ? $form_data[$dmn_variable] : null;

            if ($value === null || $value === 'null' || $value === '')
            {
                $variables[$dmn_variable] = array(
                    'value' => null,
                    'type' => $form_field['type']
                );
                continue;
            }

            // Type conversion with validation
            $converted_value = $this->convert_variable_type($value, $form_field['type'], $dmn_variable);
            if (is_wp_error($converted_value))
            {
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
        switch ($type)
        {
            case 'Integer':
                if (!is_numeric($value))
                {
                    return new WP_Error(
                        'invalid_type',
                        sprintf(__('Value for %s must be numeric', 'operaton-dmn'), $variable_name),
                        array('status' => 400)
                    );
                }
                return intval($value);

            case 'Double':
                if (!is_numeric($value))
                {
                    return new WP_Error(
                        'invalid_type',
                        sprintf(__('Value for %s must be numeric', 'operaton-dmn'), $variable_name),
                        array('status' => 400)
                    );
                }
                return floatval($value);

            case 'Boolean':
                if (is_string($value))
                {
                    $value = strtolower($value);
                    if ($value === 'true' || $value === '1')
                    {
                        return true;
                    }
                    elseif ($value === 'false' || $value === '0')
                    {
                        return false;
                    }
                    else
                    {
                        $converted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if ($converted === null)
                        {
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
        operaton_debug_verbose('API', 'Building evaluation endpoint', [
            'decision_key' => $decision_key,
            'base_endpoint' => $base_endpoint
        ]);

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

        operaton_debug_verbose('API', 'Final evaluation endpoint built', ['endpoint' => $evaluation_url]);

        return $evaluation_url;
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

        if (strpos($base_url, '/engine-rest') === false)
        {
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

        if ($process_ended)
        {
            // Process completed immediately - get variables from history
            operaton_debug_verbose('API', 'Process completed immediately, getting variables from history');

            return $this->get_historical_variables($base_url, $process_instance_id);
        }
        else
        {
            // Process is still running - wait and try to get active variables
            operaton_debug_verbose('API', 'Process still running, waiting for completion');

            sleep(3); // Wait for process completion

            $active_variables = $this->get_active_process_variables($base_url, $process_instance_id);

            // If active variables failed, try history as fallback
            if (empty($active_variables))
            {
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

        operaton_debug_verbose('API', 'Getting historical variables', ['url' => $history_url]);

        $response = wp_remote_get($history_url, array(
            'headers' => array('Accept' => 'application/json'),
            'timeout' => 15,
            'sslverify' => $this->ssl_verify,
        ));

        if (is_wp_error($response))
        {
            return new WP_Error(
                'api_error',
                sprintf(__('Failed to get historical variables: %s', 'operaton-dmn'), $response->get_error_message()),
                array('status' => 500)
            );
        }

        $history_body = wp_remote_retrieve_body($response);
        $historical_variables = json_decode($history_body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($historical_variables))
        {
            return array();
        }

        // Convert historical variables to expected format
        $final_variables = array();
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

        if (is_wp_error($response))
        {
            return array();
        }

        $variables_body = wp_remote_retrieve_body($response);
        $variables = json_decode($variables_body, true);

        if (json_last_error() !== JSON_ERROR_NONE)
        {
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

        foreach ($result_mappings as $dmn_result_field => $mapping)
        {
            $result_value = $this->find_result_value($dmn_result_field, $final_variables);

            if ($result_value !== null)
            {
                // Handle boolean conversion (DMN often returns 1/0 instead of true/false)
                if (is_numeric($result_value) && ($result_value === 1 || $result_value === 0 || $result_value === '1' || $result_value === '0'))
                {
                    $result_value = (bool) $result_value;
                }

                $results[$dmn_result_field] = array(
                    'value' => $result_value,
                    'field_id' => $mapping['field_id']
                );

                operaton_debug_diagnostic('API', 'Extracted result field', [
                    'field' => $dmn_result_field,
                    'value' => $result_value,
                    'type' => gettype($result_value)
                ]);
            }
            else
            {
                operaton_debug_diagnostic('API', 'No result found for field', ['field' => $dmn_result_field]);
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
        if (isset($variables[$field_name]['value']))
        {
            return $variables[$field_name]['value'];
        }
        elseif (isset($variables[$field_name]))
        {
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

        foreach ($possible_containers as $container)
        {
            if (isset($variables[$container]['value']) && is_array($variables[$container]['value']))
            {
                $container_data = $variables[$container]['value'];

                // Check if it's an array of results
                if (isset($container_data[0]) && is_array($container_data[0]))
                {
                    if (isset($container_data[0][$field_name]))
                    {
                        return $container_data[0][$field_name];
                    }
                }
                elseif (isset($container_data[$field_name]))
                {
                    return $container_data[$field_name];
                }
            }
        }

        // Strategy 3: Comprehensive search through all variables
        foreach ($variables as $var_name => $var_data)
        {
            if (isset($var_data['value']) && is_array($var_data['value']))
            {
                if (isset($var_data['value'][0]) && is_array($var_data['value'][0]))
                {
                    if (isset($var_data['value'][0][$field_name]))
                    {
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

        foreach ($result_mappings as $dmn_result_field => $mapping)
        {
            $result_value = null;

            if (isset($data[0][$dmn_result_field]['value']))
            {
                $result_value = $data[0][$dmn_result_field]['value'];
            }
            elseif (isset($data[0][$dmn_result_field]))
            {
                $result_value = $data[0][$dmn_result_field];
            }

            if ($result_value !== null)
            {
                $results[$dmn_result_field] = array(
                    'value' => $result_value,
                    'field_id' => $mapping['field_id']
                );
            }
        }

        return $results;
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
        if (json_last_error() !== JSON_ERROR_NONE)
        {
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

        if (strpos($base_url, '/engine-rest') === false)
        {
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
        if (empty($iso_timestamp))
        {
            return 'Unknown';
        }

        try
        {
            // Parse the ISO timestamp
            $datetime = new DateTime($iso_timestamp);

            // Convert to WordPress site timezone
            $wp_timezone = wp_timezone();
            $datetime->setTimezone($wp_timezone);

            // Format in a user-friendly way
            $formatted_date = $datetime->format('Y-m-d H:i:s');
            $timezone_name = $datetime->format('T');

            return $formatted_date . ' (' . $timezone_name . ')';
        }
        catch (Exception $e)
        {
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
        if (!$this->database)
        {
            $issues[] = __('Database manager not initialized', 'operaton-dmn');
        }

        // Check core plugin connection
        if (!$this->core)
        {
            $issues[] = __('Core plugin instance not available', 'operaton-dmn');
        }

        // Check if REST API is accessible
        $rest_url = rest_url('operaton-dmn/v1/test');
        $response = wp_remote_get($rest_url, array('timeout' => 5));

        if (is_wp_error($response))
        {
            $issues[] = sprintf(__('REST API not accessible: %s', 'operaton-dmn'), $response->get_error_message());
        }

        if (!empty($issues))
        {
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
}
