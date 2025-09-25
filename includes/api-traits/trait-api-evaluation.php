<?php

/**
 * DMN evaluation processing trait for Operaton DMN Plugin
 *
 * Handles the core DMN evaluation logic including process execution, decision evaluation,
 * and optimized connection pooling. Contains the main evaluation endpoints.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Operaton_DMN_API_Evaluation
{
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
            $this->log_standard('Handling evaluation request');

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
            $this->log_minimal('Evaluation error occurred', [
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => basename($e->getFile())
            ]);

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
        $this->log_standard('Starting process execution', ['process_key' => $config->process_key]);

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

        $this->log_standard('Starting process at endpoint', ['endpoint' => $process_endpoint]);

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
     * Enhanced batching optimization for process execution
     * Replace your existing handle_process_execution_optimized method with this version
     *
     * @param object $config Configuration object containing process settings
     * @param array $form_data Form data to be passed as process variables
     * @return array|WP_Error Process execution results with optimized batching
     * @since 1.0.0
     */
    private function handle_process_execution_optimized($config, $form_data)
    {
        $this->log_standard('Starting enhanced batched process execution', ['process_key' => $config->process_key]);

        // Parse and validate field mappings
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

        // ENHANCED: Prepare ALL possible API calls upfront for optimal batching
        $api_batch = array(
            'start_process' => array(
                'endpoint' => $this->build_process_endpoint($config->dmn_endpoint, $config->process_key),
                'data' => array('variables' => $variables),
                'method' => 'POST',
                'required' => true
            ),
            'get_variables_active' => array(
                'endpoint' => null, // Will be set after process start
                'data' => array(),
                'method' => 'GET',
                'required' => false,
                'fallback_for' => 'get_variables_history'
            ),
            'get_variables_history' => array(
                'endpoint' => null, // Will be set after process start
                'data' => array(),
                'method' => 'GET',
                'required' => true,
                'primary_data_source' => true
            )
        );

        // Execute Step 1: Start Process (required)
        $start_time = microtime(true);

        $this->log_verbose('Batch Step 1 - Starting process', ['endpoint' => $api_batch['start_process']['endpoint']]);

        $process_response = $this->make_optimized_api_call(
            $api_batch['start_process']['endpoint'],
            $api_batch['start_process']['data'],
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

        // ENHANCED: Prepare variable retrieval endpoints based on process state
        if ($process_ended)
        {
            // Process completed immediately - use history endpoint
            $api_batch['get_variables_history']['endpoint'] = $base_url . '/history/variable-instance?processInstanceId=' . $process_instance_id;
            $primary_endpoint = 'get_variables_history';

            $this->log_verbose('Process ended immediately, using history endpoint');
        }
        else
        {
            // Process might still be running - prepare both endpoints for intelligent fallback
            $api_batch['get_variables_active']['endpoint'] = $base_url . '/process-instance/' . $process_instance_id . '/variables';
            $api_batch['get_variables_history']['endpoint'] = $base_url . '/history/variable-instance?processInstanceId=' . $process_instance_id;
            $primary_endpoint = 'get_variables_active';

            $this->log_verbose('Process may be running, preparing intelligent fallback strategy');
        }

        // Execute Step 2: Intelligent Variable Retrieval with Batched Fallback
        $final_variables = $this->execute_variable_retrieval_batch($api_batch, $primary_endpoint, $process_instance_id);

        if (is_wp_error($final_variables))
        {
            return $final_variables;
        }

        // Extract results using the retrieved variables
        $results = $this->extract_process_results($config, $final_variables);

        // Store process instance ID for decision flow
        $this->database->store_process_instance_id($config->form_id, $process_instance_id);

        $total_time = round((microtime(true) - $start_time) * 1000, 2);

        $this->log_standard('Batched execution completed', ['total_time_ms' => $total_time]);

        return array(
            'success' => true,
            'results' => $results,
            'process_instance_id' => $process_instance_id,
            'debug_info' => $this->get_debug_info() ? array(
                'variables_sent' => $variables,
                'process_result' => $process_result,
                'final_variables' => $final_variables,
                'endpoint_used' => $api_batch['start_process']['endpoint'],
                'process_ended_immediately' => $process_ended,
                'total_execution_time_ms' => $total_time,
                'batching_strategy' => $primary_endpoint,
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
        $this->log_standard('Starting direct decision evaluation', ['decision_key' => $config->decision_key]);

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

        $this->log_standard('Using evaluation endpoint', ['endpoint' => $evaluation_endpoint]);

        // Make API call
        $operaton_data = array('variables' => $variables);

        $this->log_api('Making HTTP request', [
            'endpoint' => $evaluation_endpoint,
            'data_keys' => array_keys($operaton_data)
        ]);

        // OLD:
        //$response = wp_remote_post($evaluation_endpoint, array(
        //    'headers' => $this->get_api_headers(),
        //    'body' => wp_json_encode($operaton_data),
        //    'timeout' => $this->api_timeout,
        //    'sslverify' => $this->ssl_verify,
        //));

        // NEW:
        $response = $this->make_optimized_api_call($evaluation_endpoint, $operaton_data);

        if (is_wp_error($response))
        {
            $this->log_minimal('HTTP request failed', [
                'error_message' => $response->get_error_message(),
                'error_codes' => $response->get_error_codes()
            ]);
        }
        else
        {
            $this->log_api('HTTP response received', [
                'status_code' => wp_remote_retrieve_response_code($response),
                'body_length' => strlen(wp_remote_retrieve_body($response)),
                'body_preview' => substr(wp_remote_retrieve_body($response), 0, 100) . '...'
            ]);
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

    /**
     * Execute intelligent variable retrieval with batched fallback strategy
     *
     * @param array $api_batch Prepared API call configurations
     * @param string $primary_endpoint Primary endpoint to try first
     * @param string $process_instance_id Process instance identifier
     * @return array|WP_Error Retrieved variables or error
     * @since 1.0.0
     */
    private function execute_variable_retrieval_batch($api_batch, $primary_endpoint, $process_instance_id)
    {
        $retrieval_start = microtime(true);

        // Try primary strategy first
        $this->log_verbose('Batch Step 2a - Trying primary strategy', ['endpoint' => $primary_endpoint]);

        $primary_response = $this->make_optimized_api_call(
            $api_batch[$primary_endpoint]['endpoint'],
            $api_batch[$primary_endpoint]['data'],
            $api_batch[$primary_endpoint]['method']
        );

        $primary_variables = $this->process_variable_response($primary_response, $primary_endpoint);

        // If primary strategy succeeded and returned data, use it
        if (!is_wp_error($primary_variables) && !empty($primary_variables))
        {
            $retrieval_time = round((microtime(true) - $retrieval_start) * 1000, 2);

            $this->log_verbose('Primary strategy succeeded', [
                'retrieval_time_ms' => $retrieval_time,
                'variables_found' => count($primary_variables)
            ]);

            return $primary_variables;
        }

        // Primary strategy failed or returned no data - try fallback
        $fallback_endpoint = ($primary_endpoint === 'get_variables_active') ? 'get_variables_history' : 'get_variables_active';

        if (!isset($api_batch[$fallback_endpoint]['endpoint']) || empty($api_batch[$fallback_endpoint]['endpoint']))
        {
            return new WP_Error(
                'no_fallback',
                __('Primary variable retrieval failed and no fallback available', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        $this->log_verbose('Batch Step 2b - Primary failed, trying fallback', ['fallback_endpoint' => $fallback_endpoint]);

        // Small delay before fallback to allow process completion if needed
        if ($primary_endpoint === 'get_variables_active')
        {
            usleep(500000); // 0.5 second wait for process completion
        }

        $fallback_response = $this->make_optimized_api_call(
            $api_batch[$fallback_endpoint]['endpoint'],
            $api_batch[$fallback_endpoint]['data'],
            $api_batch[$fallback_endpoint]['method']
        );

        $fallback_variables = $this->process_variable_response($fallback_response, $fallback_endpoint);

        if (is_wp_error($fallback_variables))
        {
            return new WP_Error(
                'variable_retrieval_failed',
                sprintf(__('Both primary and fallback variable retrieval failed: %s', 'operaton-dmn'), $fallback_variables->get_error_message()),
                array('status' => 500)
            );
        }

        $total_retrieval_time = round((microtime(true) - $retrieval_start) * 1000, 2);

        $this->log_verbose('Fallback strategy succeeded', [
            'total_retrieval_time_ms' => $total_retrieval_time,
            'variables_found' => count($fallback_variables)
        ]);

        return $fallback_variables;
    }

    /**
     * Process variable response from API calls
     *
     * @param array|WP_Error $response HTTP response
     * @param string $endpoint_type Type of endpoint called
     * @return array|WP_Error Processed variables or error
     * @since 1.0.0
     */
    private function process_variable_response($response, $endpoint_type)
    {
        if (is_wp_error($response))
        {
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200)
        {
            return new WP_Error(
                'api_error',
                sprintf(__('Variable retrieval failed with status %d', 'operaton-dmn'), $http_code),
                array('status' => 500)
            );
        }

        $variables_body = wp_remote_retrieve_body($response);
        $variables_data = json_decode($variables_body, true);

        if (json_last_error() !== JSON_ERROR_NONE)
        {
            return new WP_Error(
                'json_error',
                __('Invalid JSON response from variable retrieval', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        if (!is_array($variables_data))
        {
            return array(); // Empty but valid response
        }

        // Convert to consistent format based on endpoint type
        if ($endpoint_type === 'get_variables_history')
        {
            // History endpoint returns array of variable objects
            $final_variables = array();
            foreach ($variables_data as $var)
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
        else
        {
            // Active variables endpoint returns object with variable names as keys
            return $variables_data;
        }
    }
}
