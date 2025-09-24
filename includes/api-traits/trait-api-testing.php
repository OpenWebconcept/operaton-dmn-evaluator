<?php

/**
 * Operaton DMN API Testing Trait
 *
 * Comprehensive testing and configuration validation methods for
 * DMN endpoint connectivity, decision validation, and system diagnostics.
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
 * Testing and configuration validation trait
 *
 * Provides comprehensive testing methods for DMN configurations,
 * endpoint validation, and system capability verification.
 *
 * @since 1.0.0
 */
trait Operaton_DMN_API_Testing
{
    // =============================================================================
    // TESTING & CONFIGURATION VALIDATION
    // =============================================================================

    /**
     * Comprehensive testing of full endpoint configuration including connectivity and decision evaluation
     *
     * Performs complete validation of DMN configuration including endpoint accessibility,
     * decision key validation, sample evaluation execution, and result field verification.
     * Provides detailed diagnostic information for troubleshooting configuration issues.
     *
     * @param string $base_endpoint Base DMN endpoint URL
     * @param string $decision_key Decision definition key to test
     * @return array Comprehensive test results with success status and detailed feedback
     * @since 1.0.0
     */
    public function test_full_endpoint_configuration($base_endpoint, $decision_key)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Starting full endpoint configuration test');
            error_log('Operaton DMN API: Base endpoint: ' . $base_endpoint);
            error_log('Operaton DMN API: Decision key: ' . $decision_key);
        }

        $test_results = array(
            'success' => false,
            'message' => '',
            'endpoint' => $base_endpoint,
            'decision_key' => $decision_key,
            'tests' => array(),
            'performance' => array()
        );

        $start_time = microtime(true);

        try
        {
            // Test 1: Basic endpoint connectivity
            $version_url = rtrim($base_endpoint, '/') . '/engine-rest/version';
            $version_response = $this->make_api_call($version_url, array(), 'GET');

            if (is_wp_error($version_response))
            {
                $test_results['tests']['connectivity'] = array(
                    'success' => false,
                    'message' => $version_response->get_error_message(),
                    'url' => $version_url
                );
                $test_results['message'] = __('Endpoint connectivity test failed', 'operaton-dmn');
                return $test_results;
            }

            $test_results['tests']['connectivity'] = array(
                'success' => true,
                'message' => __('Endpoint accessible', 'operaton-dmn'),
                'engine_version' => $version_response['version'] ?? 'Unknown'
            );

            // Test 2: Decision definition accessibility
            $decision_url = $this->build_evaluation_endpoint($base_endpoint, $decision_key);
            $decision_info_url = str_replace('/evaluate', '', $decision_url);

            $decision_response = $this->make_api_call($decision_info_url, array(), 'GET');

            if (is_wp_error($decision_response))
            {
                $test_results['tests']['decision_accessibility'] = array(
                    'success' => false,
                    'message' => __('Decision definition not found or not accessible', 'operaton-dmn'),
                    'url' => $decision_info_url
                );
                $test_results['message'] = __('Decision definition test failed', 'operaton-dmn');
                return $test_results;
            }

            $test_results['tests']['decision_accessibility'] = array(
                'success' => true,
                'message' => __('Decision definition accessible', 'operaton-dmn'),
                'decision_name' => $decision_response['name'] ?? $decision_key
            );

            // Test 3: Sample evaluation with empty variables
            $evaluation_response = $this->make_api_call($decision_url, array('variables' => array()), 'POST');

            if (is_wp_error($evaluation_response))
            {
                $test_results['tests']['evaluation'] = array(
                    'success' => false,
                    'message' => $evaluation_response->get_error_message(),
                    'note' => __('This might be expected if the decision requires input variables', 'operaton-dmn')
                );
            }
            else
            {
                $test_results['tests']['evaluation'] = array(
                    'success' => true,
                    'message' => __('Sample evaluation successful', 'operaton-dmn'),
                    'result_count' => is_array($evaluation_response) ? count($evaluation_response) : 0
                );
            }

            $test_results['success'] = true;
            $test_results['message'] = __('All configuration tests completed successfully', 'operaton-dmn');
        }
        catch (Exception $e)
        {
            $test_results['message'] = sprintf(__('Test execution error: %s', 'operaton-dmn'), $e->getMessage());
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Configuration test error: ' . $e->getMessage());
            }
        }

        $test_results['performance']['total_time_ms'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test_results;
    }

    /**
     * Comprehensive testing of decision configuration with full validation pipeline
     *
     * Performs complete validation of decision-based configuration including endpoint
     * connectivity, decision key validation, field mapping verification, sample
     * evaluation execution, and result field validation with detailed diagnostics.
     *
     * @param object $config Configuration object to test
     * @return array Comprehensive test results with detailed validation feedback
     * @since 1.0.0
     */
    private function test_decision_configuration_comprehensive($config)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Starting comprehensive decision configuration test');
        }

        $test_results = array(
            'success' => false,
            'message' => '',
            'config_id' => $config->id,
            'decision_key' => $config->decision_key,
            'tests' => array(),
            'performance' => array()
        );

        $start_time = microtime(true);

        try
        {
            // Step 1: Basic endpoint connectivity test
            $version_url = rtrim($config->dmn_endpoint, '/') . '/engine-rest/version';
            $version_response = $this->make_api_call($version_url, array(), 'GET');

            if (is_wp_error($version_response))
            {
                $test_results['tests']['connectivity'] = array(
                    'success' => false,
                    'message' => $version_response->get_error_message()
                );
                $test_results['message'] = __('Endpoint connectivity failed', 'operaton-dmn');
                return $test_results;
            }

            $test_results['tests']['connectivity'] = array(
                'success' => true,
                'message' => __('Endpoint connectivity successful', 'operaton-dmn'),
                'engine_version' => $version_response['version'] ?? 'Unknown'
            );

            // Step 2: Decision definition validation
            $decision_endpoint = $this->build_evaluation_endpoint($config->dmn_endpoint, $config->decision_key);
            $decision_info_url = str_replace('/evaluate', '', $decision_endpoint);

            $decision_response = $this->make_api_call($decision_info_url, array(), 'GET');
            if (is_wp_error($decision_response))
            {
                $test_results['tests']['decision_validation'] = array(
                    'success' => false,
                    'message' => __('Decision definition not accessible', 'operaton-dmn')
                );
                $test_results['message'] = __('Decision validation failed', 'operaton-dmn');
                return $test_results;
            }

            $test_results['tests']['decision_validation'] = array(
                'success' => true,
                'message' => __('Decision definition validated', 'operaton-dmn'),
                'decision_name' => $decision_response['name'] ?? $config->decision_key
            );

            // Step 3: Field mappings validation
            $field_mappings = json_decode($config->field_mappings, true);
            $test_results['tests']['field_mappings'] = array(
                'success' => !empty($field_mappings),
                'message' => !empty($field_mappings) ? __('Field mappings configured', 'operaton-dmn') : __('No field mappings configured', 'operaton-dmn'),
                'input_variable_count' => count($field_mappings)
            );

            // Step 4: Sample evaluation with test variables
            $test_variables = array();
            foreach ($field_mappings as $dmn_variable => $form_field)
            {
                $test_variables[$dmn_variable] = 'test_value';
            }

            $decision_result = $this->make_api_call($decision_endpoint, array('variables' => $test_variables), 'POST');
            $test_results['tests']['sample_evaluation'] = array(
                'success' => !is_wp_error($decision_result),
                'message' => !is_wp_error($decision_result) ? __('Sample evaluation successful', 'operaton-dmn') : $decision_result->get_error_message(),
                'result_count' => !is_wp_error($decision_result) && is_array($decision_result) ? count($decision_result) : 0
            );

            // Step 5: Validate expected result fields in actual response
            $result_mappings = json_decode($config->result_mappings, true);
            $field_validation = $this->validate_result_fields_in_response($result_mappings, $decision_result);
            $test_results['validation'] = $field_validation;

            $test_results['success'] = true;
            $test_results['message'] = __('Decision configuration test completed successfully', 'operaton-dmn');
            $test_results['summary'] = sprintf(
                __('Tested decision "%s" with %d input variables, found %d of %d expected result fields', 'operaton-dmn'),
                $config->decision_key,
                count($test_variables),
                $field_validation['found_fields'],
                $field_validation['total_expected']
            );
        }
        catch (Exception $e)
        {
            $test_results['message'] = sprintf(__('Test execution error: %s', 'operaton-dmn'), $e->getMessage());
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Decision test error: ' . $e->getMessage());
            }
        }

        $test_results['performance']['total_time_ms'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test_results;
    }

    /**
     * Comprehensive testing of process configuration with full validation pipeline
     *
     * Performs complete validation of process-based configuration including endpoint
     * connectivity, process definition validation, process execution testing,
     * variable retrieval validation, and result field verification.
     *
     * @param object $config Configuration object to test
     * @return array Comprehensive test results with detailed validation feedback
     * @since 1.0.0
     */
    private function test_process_configuration_comprehensive($config)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Starting comprehensive process configuration test');
        }

        $test_results = array(
            'success' => false,
            'message' => '',
            'config_id' => $config->id,
            'process_key' => $config->process_key,
            'tests' => array(),
            'performance' => array()
        );

        $start_time = microtime(true);

        try
        {
            // Step 1: Basic endpoint connectivity
            $version_url = rtrim($config->dmn_endpoint, '/') . '/engine-rest/version';
            $version_response = $this->make_api_call($version_url, array(), 'GET');

            if (is_wp_error($version_response))
            {
                $test_results['tests']['connectivity'] = array(
                    'success' => false,
                    'message' => $version_response->get_error_message()
                );
                $test_results['message'] = __('Endpoint connectivity failed', 'operaton-dmn');
                return $test_results;
            }

            $test_results['tests']['connectivity'] = array(
                'success' => true,
                'message' => __('Endpoint connectivity successful', 'operaton-dmn'),
                'engine_version' => $version_response['version'] ?? 'Unknown'
            );

            // Step 2: Process definition validation
            $base_url = $this->get_engine_rest_base_url($config->dmn_endpoint);
            $process_def_url = $base_url . '/process-definition/key/' . $config->process_key;

            $process_response = $this->make_api_call($process_def_url, array(), 'GET');
            if (is_wp_error($process_response))
            {
                $test_results['tests']['process_validation'] = array(
                    'success' => false,
                    'message' => __('Process definition not accessible', 'operaton-dmn')
                );
                $test_results['message'] = __('Process validation failed', 'operaton-dmn');
                return $test_results;
            }

            $test_results['tests']['process_validation'] = array(
                'success' => true,
                'message' => __('Process definition validated', 'operaton-dmn'),
                'process_name' => $process_response['name'] ?? $config->process_key
            );

            // Step 3: Field mappings validation
            $field_mappings = json_decode($config->field_mappings, true);
            $test_results['tests']['field_mappings'] = array(
                'success' => !empty($field_mappings),
                'message' => !empty($field_mappings) ? __('Field mappings configured', 'operaton-dmn') : __('No field mappings configured', 'operaton-dmn'),
                'input_variable_count' => count($field_mappings)
            );

            // Step 4: Sample process execution
            $test_variables = array();
            foreach ($field_mappings as $dmn_variable => $form_field)
            {
                $test_variables[$dmn_variable] = 'test_value';
            }

            $process_endpoint = $this->build_process_endpoint($config->dmn_endpoint, $config->process_key);
            $process_result = $this->make_api_call($process_endpoint, array('variables' => $test_variables), 'POST');

            if (is_wp_error($process_result))
            {
                $test_results['tests']['process_execution'] = array(
                    'success' => false,
                    'message' => $process_result->get_error_message()
                );
            }
            else
            {
                $test_results['tests']['process_execution'] = array(
                    'success' => true,
                    'message' => __('Process execution successful', 'operaton-dmn'),
                    'process_instance_id' => $process_result['id'] ?? 'Unknown'
                );

                // Step 5: Variable retrieval validation
                if (!empty($process_result['id']))
                {
                    $variables_result = $this->retrieve_process_variables_with_fallback($base_url, $process_result['id']);

                    $test_results['tests']['variable_retrieval'] = array(
                        'success' => !is_wp_error($variables_result),
                        'message' => !is_wp_error($variables_result) ? __('Variable retrieval successful', 'operaton-dmn') : $variables_result->get_error_message(),
                        'variables_source' => !is_wp_error($variables_result) ? $variables_result['source'] : 'failed'
                    );

                    // Step 6: Result field validation
                    if (!is_wp_error($variables_result))
                    {
                        $result_mappings = json_decode($config->result_mappings, true);
                        $field_validation = $this->validate_result_fields_in_response($result_mappings, $variables_result['variables']);
                        $test_results['validation'] = $field_validation;
                    }
                }
            }

            $test_results['success'] = true;
            $test_results['message'] = __('Process configuration test completed successfully', 'operaton-dmn');
        }
        catch (Exception $e)
        {
            $test_results['message'] = sprintf(__('Test execution error: %s', 'operaton-dmn'), $e->getMessage());
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Process test error: ' . $e->getMessage());
            }
        }

        $test_results['performance']['total_time_ms'] = round((microtime(true) - $start_time) * 1000, 2);
        return $test_results;
    }

    /**
     * Validate result fields in actual API response
     *
     * Compares expected result fields from configuration with actual fields
     * present in API response data. Provides detailed analysis of field
     * availability and data type validation for troubleshooting purposes.
     *
     * @param array $result_mappings Expected result field mappings
     * @param array|object $response_data Actual API response data
     * @return array Field validation results with detailed analysis
     * @since 1.0.0
     */
    private function validate_result_fields_in_response($result_mappings, $response_data)
    {
        $validation = array(
            'total_expected' => count($result_mappings),
            'found_fields' => 0,
            'missing_fields' => array(),
            'available_fields' => array(),
            'field_details' => array()
        );

        if (empty($result_mappings))
        {
            $validation['message'] = __('No result mappings configured', 'operaton-dmn');
            return $validation;
        }

        // Handle different response data formats
        $data_to_check = array();
        if (is_array($response_data))
        {
            if (isset($response_data[0]) && is_array($response_data[0]))
            {
                // Decision evaluation result format
                $data_to_check = $response_data[0];
            }
            else
            {
                // Process variables format or direct array
                $data_to_check = $response_data;
            }
        }
        elseif (is_object($response_data))
        {
            $data_to_check = (array) $response_data;
        }

        // Extract available field names
        foreach ($data_to_check as $field_name => $field_data)
        {
            if (is_array($field_data) && isset($field_data['value']))
            {
                // Operaton engine format with value wrapper
                $validation['available_fields'][] = $field_name;
                $validation['field_details'][$field_name] = array(
                    'type' => $field_data['type'] ?? 'unknown',
                    'value' => $field_data['value']
                );
            }
            else
            {
                // Direct value format
                $validation['available_fields'][] = $field_name;
                $validation['field_details'][$field_name] = array(
                    'type' => gettype($field_data),
                    'value' => $field_data
                );
            }
        }

        // Check for expected fields
        foreach ($result_mappings as $expected_field => $form_mapping)
        {
            if (in_array($expected_field, $validation['available_fields']))
            {
                $validation['found_fields']++;
            }
            else
            {
                $validation['missing_fields'][] = $expected_field;
            }
        }

        $validation['success'] = $validation['found_fields'] > 0;
        $validation['message'] = sprintf(
            __('Found %d of %d expected result fields', 'operaton-dmn'),
            $validation['found_fields'],
            $validation['total_expected']
        );

        return $validation;
    }

    /**
     * Test REST API call functionality
     *
     * Internal method for testing REST API accessibility and basic functionality.
     * Used by debug handlers to validate REST API configuration and connectivity.
     *
     * @return array Test results with success status and diagnostic information
     * @since 1.0.0
     */
    private function test_rest_api_call()
    {
        try
        {
            $test_url = rest_url('operaton-dmn/v1/test');
            $response = wp_remote_get($test_url, array(
                'timeout' => 10,
                'sslverify' => false
            ));

            if (is_wp_error($response))
            {
                return array(
                    'success' => false,
                    'message' => $response->get_error_message(),
                    'test_url' => $test_url
                );
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            return array(
                'success' => $response_code === 200,
                'response_code' => $response_code,
                'test_url' => $test_url,
                'response_body' => $response_body
            );
        }
        catch (Exception $e)
        {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'exception'
            );
        }
    }
}
