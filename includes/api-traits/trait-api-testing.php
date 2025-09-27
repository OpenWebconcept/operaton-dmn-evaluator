<?php

/**
 * Testing and validation trait for Operaton DMN Plugin
 *
 * Contains all testing methods including endpoint validation, configuration testing,
 * debug functionality, and comprehensive system diagnostics.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Operaton_DMN_API_Testing
{
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
        operaton_debug('API', 'Testing full endpoint configuration', ['decision_key' => $decision_key]);

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
     * Test complete configuration with realistic data simulation
     * Tests the full evaluation flow using the configuration's actual mappings
     *
     * @param int $config_id Configuration ID to test
     * @return array Test results with detailed validation
     * @since 1.0.0
     */
    public function test_configuration_complete($config_id)
    {
        operaton_debug('API', 'Testing complete configuration ID: ' . $config_id);

        // Get configuration
        $config = $this->database->get_configuration($config_id);
        if (!$config)
        {
            return array(
                'success' => false,
                'message' => __('Configuration not found', 'operaton-dmn'),
                'test_type' => 'configuration_validation'
            );
        }

        // Validate configuration completeness
        $validation_result = $this->validate_configuration_for_testing($config);
        if (!$validation_result['success'])
        {
            return $validation_result;
        }

        // Generate realistic test data based on field mappings
        $test_data = $this->generate_test_data_from_mappings($config);
        if (is_wp_error($test_data))
        {
            return array(
                'success' => false,
                'message' => sprintf(__('Failed to generate test data: %s', 'operaton-dmn'), $test_data->get_error_message()),
                'test_type' => 'test_data_generation'
            );
        }

        // Determine test method based on configuration
        $use_process = isset($config->use_process) ? $config->use_process : false;

        if ($use_process && !empty($config->process_key))
        {
            return $this->test_process_execution_complete($config, $test_data);
        }
        else
        {
            return $this->test_decision_evaluation_complete($config, $test_data);
        }
    }

    /**
     * Validate configuration for testing
     * Checks if configuration has all required fields for testing
     *
     * @param object $config Configuration object
     * @return array Validation results
     * @since 1.0.0
     */
    private function validate_configuration_for_testing($config)
    {
        $issues = array();

        // Check basic required fields
        if (empty($config->dmn_endpoint))
        {
            $issues[] = __('DMN endpoint URL is required', 'operaton-dmn');
        }

        if (empty($config->field_mappings))
        {
            $issues[] = __('Input field mappings are required', 'operaton-dmn');
        }

        if (empty($config->result_mappings))
        {
            $issues[] = __('Result field mappings are required', 'operaton-dmn');
        }

        // Check mode-specific requirements
        $use_process = isset($config->use_process) ? $config->use_process : false;

        if ($use_process)
        {
            if (empty($config->process_key))
            {
                $issues[] = __('Process key is required for process mode', 'operaton-dmn');
            }
        }
        else
        {
            if (empty($config->decision_key))
            {
                $issues[] = __('Decision key is required for direct mode', 'operaton-dmn');
            }
        }

        // Validate JSON mappings
        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE)
        {
            $issues[] = __('Field mappings contain invalid JSON', 'operaton-dmn');
        }

        $result_mappings = json_decode($config->result_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE)
        {
            $issues[] = __('Result mappings contain invalid JSON', 'operaton-dmn');
        }

        if (!empty($issues))
        {
            return array(
                'success' => false,
                'message' => __('Configuration validation failed', 'operaton-dmn'),
                'issues' => $issues,
                'test_type' => 'configuration_validation'
            );
        }

        return array(
            'success' => true,
            'message' => __('Configuration is valid for testing', 'operaton-dmn')
        );
    }

    /**
     * Generate realistic test data based on field mappings
     * Creates test form data that matches the expected input variables
     *
     * @param object $config Configuration object
     * @return array|WP_Error Test form data or error
     * @since 1.0.0
     */
    private function generate_test_data_from_mappings($config)
    {
        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE)
        {
            return new WP_Error(
                'invalid_mappings',
                __('Invalid field mappings JSON', 'operaton-dmn')
            );
        }

        $test_data = array();

        foreach ($field_mappings as $dmn_variable => $mapping)
        {
            $type = isset($mapping['type']) ? $mapping['type'] : 'String';
            $test_data[$dmn_variable] = $this->generate_test_value_for_type($type, $dmn_variable);
        }

        operaton_debug_verbose('API', 'Generated test data', $test_data);

        return $test_data;
    }

    /**
     * Generate appropriate test value for variable type
     * Creates realistic test values based on DMN variable types
     *
     * @param string $type Variable type (Integer, Double, Boolean, String)
     * @param string $variable_name Variable name for context-aware generation
     * @return mixed Generated test value
     * @since 1.0.0
     */
    private function generate_test_value_for_type($type, $variable_name)
    {
        switch ($type)
        {
            case 'Integer':
                // Generate contextual integers based on variable name
                if (stripos($variable_name, 'guest') !== false || stripos($variable_name, 'count') !== false)
                {
                    return rand(2, 12); // Realistic guest count
                }
                elseif (stripos($variable_name, 'age') !== false)
                {
                    return rand(18, 65);
                }
                elseif (stripos($variable_name, 'income') !== false || stripos($variable_name, 'salary') !== false)
                {
                    return rand(30000, 120000);
                }
                elseif (stripos($variable_name, 'year') !== false)
                {
                    return rand(2020, 2024);
                }
                else
                {
                    return rand(1, 10); // Smaller range for better DMN rule matching
                }

            case 'Double':
                // Generate contextual doubles
                if (stripos($variable_name, 'percentage') !== false || stripos($variable_name, 'rate') !== false)
                {
                    return round(rand(1, 100) + (rand(0, 99) / 100), 2);
                }
                elseif (stripos($variable_name, 'amount') !== false || stripos($variable_name, 'price') !== false)
                {
                    return round(rand(100, 10000) + (rand(0, 99) / 100), 2);
                }
                else
                {
                    return round(rand(1, 100) + (rand(0, 99) / 100), 2);
                }

            case 'Boolean':
                return (bool) rand(0, 1);

            default: // String
                // Generate contextual strings based on variable name
                if (stripos($variable_name, 'season') !== false)
                {
                    $seasons = array('Spring', 'Summer', 'Fall', 'Winter');
                    return $seasons[array_rand($seasons)];
                }
                elseif (stripos($variable_name, 'name') !== false)
                {
                    $names = array('John Doe', 'Jane Smith', 'Alex Johnson', 'Maria Garcia', 'Test User');
                    return $names[array_rand($names)];
                }
                elseif (stripos($variable_name, 'email') !== false)
                {
                    return 'test' . rand(100, 999) . '@example.com';
                }
                elseif (stripos($variable_name, 'city') !== false || stripos($variable_name, 'location') !== false)
                {
                    $cities = array('Amsterdam', 'Rotterdam', 'The Hague', 'Utrecht', 'Eindhoven');
                    return $cities[array_rand($cities)];
                }
                elseif (stripos($variable_name, 'status') !== false || stripos($variable_name, 'type') !== false)
                {
                    $statuses = array('active', 'pending', 'approved', 'standard', 'premium');
                    return $statuses[array_rand($statuses)];
                }
                else
                {
                    return 'test_value_' . rand(1, 5); // Simple test values
                }
        }
    }

    /**
     * Test complete process execution with validation
     * Tests process mode configuration with comprehensive validation
     *
     * @param object $config Configuration object
     * @param array $test_data Generated test data
     * @return array Test results
     * @since 1.0.0
     */
    private function test_process_execution_complete($config, $test_data)
    {
        $start_time = microtime(true);
        $test_results = array(
            'success' => false,
            'test_type' => 'process_execution',
            'mode' => 'Process Mode',
            'process_key' => $config->process_key,
            'endpoint_tested' => null,
            'steps' => array(),
            'validation' => array(),
            'performance' => array()
        );

        try
        {
            // Step 1: Test endpoint connectivity
            $process_endpoint = $this->build_process_endpoint($config->dmn_endpoint, $config->process_key);
            $test_results['endpoint_tested'] = $process_endpoint;

            $connectivity_test = $this->test_endpoint_connectivity($process_endpoint);
            $test_results['steps']['connectivity'] = $connectivity_test;

            if (!$connectivity_test['success'] && !$this->is_acceptable_response_code($connectivity_test))
            {
                $test_results['message'] = __('Process endpoint connectivity failed', 'operaton-dmn');
                return $test_results;
            }

            // Step 2: Validate input variables processing
            $field_mappings = json_decode($config->field_mappings, true);
            $variables = $this->process_input_variables($field_mappings, $test_data);

            if (is_wp_error($variables))
            {
                $test_results['steps']['input_processing'] = array(
                    'success' => false,
                    'message' => $variables->get_error_message()
                );
                $test_results['message'] = __('Input variable processing failed', 'operaton-dmn');
                return $test_results;
            }

            $test_results['steps']['input_processing'] = array(
                'success' => true,
                'message' => sprintf(__('Successfully processed %d input variables', 'operaton-dmn'), count($variables)),
                'variables_count' => count($variables),
                'variables' => array_keys($variables)
            );

            // Step 3: Execute actual process call
            $process_data = array('variables' => $variables);

            $response = $this->make_optimized_api_call($process_endpoint, $process_data, 'POST');

            $response_time = round((microtime(true) - $start_time) * 1000, 2);
            $test_results['performance']['response_time_ms'] = $response_time;

            if (is_wp_error($response))
            {
                $test_results['steps']['api_call'] = array(
                    'success' => false,
                    'message' => sprintf(__('API call failed: %s', 'operaton-dmn'), $response->get_error_message())
                );
                $test_results['message'] = __('Process execution API call failed', 'operaton-dmn');
                return $test_results;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            $test_results['steps']['api_call'] = array(
                'success' => ($http_code === 200 || $http_code === 201),
                'http_code' => $http_code,
                'message' => sprintf(__('API responded with status %d', 'operaton-dmn'), $http_code)
            );

            if ($http_code !== 200 && $http_code !== 201)
            {
                $test_results['message'] = sprintf(__('Process execution failed with HTTP %d', 'operaton-dmn'), $http_code);
                $test_results['api_response_body'] = substr($body, 0, 500); // Truncated response
                return $test_results;
            }

            // Step 4: Validate response structure
            $process_result = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE)
            {
                $test_results['steps']['response_parsing'] = array(
                    'success' => false,
                    'message' => __('Invalid JSON response from API', 'operaton-dmn')
                );
                $test_results['message'] = __('Response parsing failed', 'operaton-dmn');
                return $test_results;
            }

            $test_results['steps']['response_parsing'] = array(
                'success' => true,
                'message' => __('Successfully parsed API response', 'operaton-dmn'),
                'has_process_id' => isset($process_result['id'])
            );

            // Step 5: Validate expected result mappings (simulated)
            $result_mappings = json_decode($config->result_mappings, true);
            $validation_results = $this->validate_expected_result_fields($result_mappings, 'process');
            $test_results['validation'] = $validation_results;

            $test_results['success'] = true;
            $test_results['message'] = __('Process execution test completed successfully', 'operaton-dmn');
            $test_results['summary'] = sprintf(
                __('Tested process "%s" with %d input variables expecting %d result fields', 'operaton-dmn'),
                $config->process_key,
                count($variables),
                count($result_mappings)
            );
        }
        catch (Exception $e)
        {
            $test_results['message'] = sprintf(__('Test execution error: %s', 'operaton-dmn'), $e->getMessage());
            operaton_debug_minimal('API', 'Process test error: ' . $e->getMessage());
        }

        $test_results['performance']['total_time_ms'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test_results;
    }

    /**
     * Test complete decision evaluation with validation
     * Tests direct mode configuration with comprehensive validation
     *
     * @param object $config Configuration object
     * @param array $test_data Generated test data
     * @return array Test results
     * @since 1.0.0
     */
    private function test_decision_evaluation_complete($config, $test_data)
    {
        $start_time = microtime(true);
        $test_results = array(
            'success' => false,
            'test_type' => 'decision_evaluation',
            'mode' => 'Direct Mode',
            'decision_key' => $config->decision_key,
            'endpoint_tested' => null,
            'steps' => array(),
            'validation' => array(),
            'performance' => array()
        );

        try
        {
            // Step 1: Test endpoint connectivity
            $evaluation_endpoint = $this->build_evaluation_endpoint($config->dmn_endpoint, $config->decision_key);
            $test_results['endpoint_tested'] = $evaluation_endpoint;

            $connectivity_test = $this->test_endpoint_connectivity($evaluation_endpoint);
            $test_results['steps']['connectivity'] = $connectivity_test;

            // Step 2: Validate input variables processing
            $field_mappings = json_decode($config->field_mappings, true);
            $variables = $this->process_input_variables($field_mappings, $test_data);

            if (is_wp_error($variables))
            {
                $test_results['steps']['input_processing'] = array(
                    'success' => false,
                    'message' => $variables->get_error_message()
                );
                $test_results['message'] = __('Input variable processing failed', 'operaton-dmn');
                return $test_results;
            }

            $test_results['steps']['input_processing'] = array(
                'success' => true,
                'message' => sprintf(__('Successfully processed %d input variables', 'operaton-dmn'), count($variables)),
                'variables_count' => count($variables),
                'variables' => array_keys($variables)
            );

            // Step 3: Execute actual decision call
            $evaluation_data = array('variables' => $variables);

            $response = $this->make_optimized_api_call($evaluation_endpoint, $evaluation_data, 'POST');

            $response_time = round((microtime(true) - $start_time) * 1000, 2);
            $test_results['performance']['response_time_ms'] = $response_time;

            if (is_wp_error($response))
            {
                $test_results['steps']['api_call'] = array(
                    'success' => false,
                    'message' => sprintf(__('API call failed: %s', 'operaton-dmn'), $response->get_error_message())
                );
                $test_results['message'] = __('Decision evaluation API call failed', 'operaton-dmn');
                return $test_results;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            $test_results['steps']['api_call'] = array(
                'success' => ($http_code === 200),
                'http_code' => $http_code,
                'message' => sprintf(__('API responded with status %d', 'operaton-dmn'), $http_code)
            );

            if ($http_code !== 200)
            {
                $test_results['message'] = sprintf(__('Decision evaluation failed with HTTP %d', 'operaton-dmn'), $http_code);
                $test_results['api_response_body'] = substr($body, 0, 500);

                // Provide helpful error interpretation
                if ($http_code === 400)
                {
                    $test_results['suggestion'] = __('This may indicate missing or invalid input variables. Check that all required DMN input variables are mapped.', 'operaton-dmn');
                }
                elseif ($http_code === 404)
                {
                    $test_results['suggestion'] = __('Decision key not found. Verify the decision key matches your deployed DMN model.', 'operaton-dmn');
                }

                return $test_results;
            }

            // Step 4: Validate response structure
            $decision_result = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE)
            {
                $test_results['steps']['response_parsing'] = array(
                    'success' => false,
                    'message' => __('Invalid JSON response from API', 'operaton-dmn')
                );
                $test_results['message'] = __('Response parsing failed', 'operaton-dmn');
                return $test_results;
            }

            $test_results['steps']['response_parsing'] = array(
                'success' => true,
                'message' => __('Successfully parsed API response', 'operaton-dmn'),
                'result_count' => is_array($decision_result) ? count($decision_result) : 0
            );

            // Step 5: Validate expected result fields in actual response
            $result_mappings = json_decode($config->result_mappings, true);
            $field_validation = $this->validate_result_fields_in_response($result_mappings, $decision_result);
            $test_results['validation'] = $field_validation;

            $test_results['success'] = true;
            $test_results['message'] = __('Decision evaluation test completed successfully', 'operaton-dmn');
            $test_results['summary'] = sprintf(
                __('Tested decision "%s" with %d input variables, found %d of %d expected result fields', 'operaton-dmn'),
                $config->decision_key,
                count($variables),
                $field_validation['found_fields'],
                $field_validation['total_expected']
            );
        }
        catch (Exception $e)
        {
            $test_results['message'] = sprintf(__('Test execution error: %s', 'operaton-dmn'), $e->getMessage());
            operaton_debug_minimal('API', 'Decision test error: ' . $e->getMessage());
        }

        $test_results['performance']['total_time_ms'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test_results;
    }

    /**
     * Validate expected result fields configuration
     * Checks if result mappings are properly configured
     *
     * @param array $result_mappings Result mappings configuration
     * @param string $mode Testing mode (process/decision)
     * @return array Validation results
     * @since 1.0.0
     */
    private function validate_expected_result_fields($result_mappings, $mode)
    {
        $validation = array(
            'total_expected' => count($result_mappings),
            'configured_fields' => array_keys($result_mappings),
            'validation_method' => ($mode === 'process') ? 'Configuration check only (process variables checked at runtime)' : 'Response validation',
            'warnings' => array()
        );

        // Check for common field naming patterns
        foreach ($result_mappings as $field_name => $mapping)
        {
            if (empty($field_name))
            {
                $validation['warnings'][] = __('Empty result field name found', 'operaton-dmn');
            }

            if (!isset($mapping['field_id']) || empty($mapping['field_id']))
            {
                $validation['warnings'][] = sprintf(__('Missing Gravity Forms field ID for result field "%s"', 'operaton-dmn'), $field_name);
            }
        }

        return $validation;
    }

    /**
     * Validate result fields in actual API response
     * Checks if expected result fields are present in the response
     *
     * @param array $result_mappings Expected result mappings
     * @param array $api_response Actual API response
     * @return array Validation results
     * @since 1.0.0
     */
    private function validate_result_fields_in_response($result_mappings, $api_response)
    {
        operaton_debug_diagnostic('API', 'DMN Test Debug - API Response', $api_response);
        operaton_debug_diagnostic('API', 'DMN Test Debug - Expected Fields', array_keys($result_mappings));

        $validation = array(
            'total_expected' => count($result_mappings),
            'found_fields' => 0,
            'missing_fields' => array(),
            'found_field_details' => array(),
            'response_structure' => 'Unknown'
        );

        if (!is_array($api_response) || empty($api_response))
        {
            $validation['response_structure'] = 'Empty or invalid response';
            $validation['missing_fields'] = array_keys($result_mappings);
            return $validation;
        }

        // Check multiple response structure possibilities
        $result_data = null;

        if (isset($api_response[0]) && is_array($api_response[0]))
        {
            $validation['response_structure'] = 'Array of results (standard DMN response)';
            $result_data = $api_response[0];
        }
        elseif (is_array($api_response) && !empty($api_response))
        {
            // Try treating the response as a direct result object
            $validation['response_structure'] = 'Direct result object';
            $result_data = $api_response;
        }

        if ($result_data)
        {
            foreach ($result_mappings as $field_name => $mapping)
            {
                $found = false;
                $value = null;

                // Check direct field access
                if (isset($result_data[$field_name]))
                {
                    $found = true;
                    $value = $result_data[$field_name];
                }
                // Check for nested DMN format
                elseif (isset($result_data[$field_name]['value']))
                {
                    $found = true;
                    $value = $result_data[$field_name];
                }
                // Check all fields in case of different naming
                else
                {
                    foreach ($result_data as $response_field => $response_value)
                    {
                        if (stripos($response_field, $field_name) !== false || stripos($field_name, $response_field) !== false)
                        {
                            $found = true;
                            $value = $response_value;
                            break;
                        }
                    }
                }

                if ($found)
                {
                    $validation['found_fields']++;

                    // Extract actual value if it's in DMN format
                    if (is_array($value) && isset($value['value']))
                    {
                        $actual_value = $value['value'];
                        $value_type = isset($value['type']) ? $value['type'] : 'Unknown';
                    }
                    else
                    {
                        $actual_value = $value;
                        $value_type = gettype($value);
                    }

                    $validation['found_field_details'][$field_name] = array(
                        'value' => $actual_value,
                        'type' => $value_type,
                        'gravity_field' => $mapping['field_id']
                    );
                }
                else
                {
                    $validation['missing_fields'][] = $field_name;
                }
            }
        }
        else
        {
            $validation['missing_fields'] = array_keys($result_mappings);
        }

        return $validation;
    }

    /**
     * Check if response code is acceptable for testing
     * Some HTTP codes indicate reachable endpoints even if not successful
     *
     * @param array $test_result Test result with HTTP details
     * @return bool True if response indicates reachable endpoint
     * @since 1.0.0
     */
    private function is_acceptable_response_code($test_result)
    {
        // Method Not Allowed (405) means endpoint exists but doesn't accept our method
        // This is actually good for a connectivity test
        return isset($test_result['http_code']) && $test_result['http_code'] === 405;
    }

    /**
     * Test server configuration for debug purposes
     * Returns server configuration information for debugging
     *
     * @return array Server configuration data
     * @since 1.0.0
     */
    private function test_server_config()
    {
        operaton_debug('API', '=== SERVER CONFIGURATION DEBUG ===');

        $config = array(
            'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled',
            'curl_available' => function_exists('curl_init') ? 'Available' : 'Not available',
            'openssl_loaded' => extension_loaded('openssl') ? 'Available' : 'Not available'
        );

        operaton_debug_diagnostic('API', 'Server configuration', $config);

        return $config;
    }

    /**
     * Test plugin initialization for debug purposes
     * Checks if plugin classes and methods are properly initialized
     *
     * @return array Plugin initialization status
     * @since 1.0.0
     */
    private function test_plugin_initialization()
    {
        operaton_debug('API', '=== PLUGIN INITIALIZATION DEBUG ===');

        $status = array(
            'api_manager_class' => class_exists('OperatonDMNApiManager'),
            'health_check_method' => method_exists($this, 'health_check'),
            'handle_evaluation_method' => method_exists($this, 'handle_evaluation')
        );

        operaton_debug_diagnostic('API', 'Plugin initialization status', $status);

        return $status;
    }

    /**
     * Test REST API availability for debug purposes
     * Checks if WordPress REST API is properly configured
     *
     * @return bool True if REST API is available
     * @since 1.0.0
     */
    private function test_rest_api_availability()
    {
        operaton_debug('API', '=== REST API AVAILABILITY DEBUG ===');

        if (!function_exists('rest_get_url_prefix'))
        {
            operaton_debug_minimal('API', 'ERROR: WordPress REST API not available');
            return false;
        }

        $rest_server = rest_get_server();
        $namespaces = $rest_server->get_namespaces();
        $has_operaton = in_array('operaton-dmn/v1', $namespaces);

        operaton_debug_verbose('API', 'Available namespaces: ' . implode(', ', $namespaces));
        operaton_debug_verbose('API', 'Operaton namespace registered: ' . ($has_operaton ? 'YES' : 'NO'));

        return $has_operaton;
    }

    /**
     * Test REST API call functionality for debug purposes
     * Makes actual REST API call to test endpoint
     *
     * @return bool True if REST API call succeeds
     * @since 1.0.0
     */
    private function test_rest_api_call()
    {
        operaton_debug('API', '=== REST API CALL TEST ===');

        $test_url = home_url('/wp-json/operaton-dmn/v1/test');
        $response = wp_remote_get($test_url);

        if (is_wp_error($response))
        {
            operaton_debug_minimal('API', 'REST API Error: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        operaton_debug_verbose('API', 'REST API Response Status: ' . $status_code);
        operaton_debug_verbose('API', 'REST API Response Body: ' . $body);

        return $status_code === 200;
    }
}
