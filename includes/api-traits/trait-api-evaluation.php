<?php

/**
 * Operaton DMN API Evaluation Trait
 *
 * Contains the core DMN evaluation logic including both decision table
 * evaluation and process execution with comprehensive error handling
 * and result processing.
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
 * Main evaluation methods trait
 *
 * Provides the core DMN evaluation functionality including decision
 * table evaluation, process execution, and optimized batch processing.
 *
 * @since 1.0.0
 */
trait Operaton_DMN_API_Evaluation
{
    // =============================================================================
    // MAIN EVALUATION METHODS - CRITICAL FUNCTIONALITY
    // =============================================================================

    /**
     * Enhanced evaluation handler that routes to either process execution or direct decision evaluation
     *
     * Main REST API endpoint that determines evaluation method based on configuration settings.
     * Routes requests to either process execution (with process engine orchestration) or
     * direct decision evaluation (standalone decision evaluation). Includes comprehensive
     * error handling and parameter validation.
     *
     * @param WP_REST_Request $request REST API request object containing config ID and form data
     * @return WP_REST_Response|WP_Error Evaluation results or error response
     * @since 1.0.0
     */
    public function handle_evaluation($request)
    {
        try
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Handling evaluation request');
            }

            $params = $request->get_json_params();

            // Validate required parameters
            if (!isset($params['config_id']) || !isset($params['form_data']))
            {
                return new WP_Error(
                    'missing_params',
                    __('Configuration ID and form data are required', 'operaton-dmn'),
                    array('status' => 400)
                );
            }

            // Get configuration
            $config = $this->database->get_configuration($params['config_id']);
            if (!$config)
            {
                return new WP_Error(
                    'invalid_config',
                    __('Configuration not found', 'operaton-dmn'),
                    array('status' => 404)
                );
            }

            // Determine evaluation method
            $use_process = isset($config->use_process) ? $config->use_process : false;

            if ($use_process && !empty($config->process_key))
            {
                return $this->handle_process_execution_optimized($config, $params['form_data']);
            }
            else
            {
                return $this->handle_decision_evaluation($config, $params['form_data']);
            }
        }
        catch (Exception $e)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
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
     *
     * Starts a process instance, waits for completion, and extracts results from process variables.
     * Uses the Operaton process engine to orchestrate complex business logic that may involve
     * multiple decision tables, user tasks, or external service integrations.
     *
     * @param object $config Configuration object containing process settings
     * @param array $form_data Form data to be passed as process variables
     * @return array|WP_Error Process execution results with extracted variables
     * @since 1.0.0
     */
    private function handle_process_execution($config, $form_data)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Starting process execution for key: ' . $config->process_key);
        }

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

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Batch Step 1 - Starting process at: ' . $api_batch['start_process']['endpoint']);
        }

        $start_response = $this->make_api_call(
            $api_batch['start_process']['endpoint'],
            $api_batch['start_process']['data'],
            $api_batch['start_process']['method']
        );

        if (is_wp_error($start_response))
        {
            return $start_response;
        }

        if (empty($start_response['id']))
        {
            return new WP_Error(
                'process_start_failed',
                __('Process instance creation failed', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        $process_instance_id = $start_response['id'];

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Process started with ID: ' . $process_instance_id);
        }

        // Update endpoints with process instance ID
        $api_batch['get_variables_active']['endpoint'] = $base_url . '/process-instance/' . $process_instance_id . '/variables';
        $api_batch['get_variables_history']['endpoint'] = $base_url . '/history/variable-instance?processInstanceId=' . $process_instance_id;

        // Execute Step 2: Get Variables (with fallback strategy)
        $variables_result = null;

        // Try active variables first (faster, but may not exist if process completed)
        $active_response = $this->make_api_call(
            $api_batch['get_variables_active']['endpoint'],
            $api_batch['get_variables_active']['data'],
            $api_batch['get_variables_active']['method']
        );

        if (!is_wp_error($active_response) && !empty($active_response))
        {
            $variables_result = $active_response;
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Retrieved variables from active process instance');
            }
        }
        else
        {
            // Fallback to historical variables
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Active variables failed, trying historical variables');
            }

            $history_response = $this->make_api_call(
                $api_batch['get_variables_history']['endpoint'],
                $api_batch['get_variables_history']['data'],
                $api_batch['get_variables_history']['method']
            );

            if (!is_wp_error($history_response))
            {
                $variables_result = $this->transform_history_to_variables($history_response);
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('Operaton DMN API: Retrieved variables from process history');
                }
            }
        }

        if (empty($variables_result))
        {
            return new WP_Error(
                'variables_retrieval_failed',
                __('Failed to retrieve process variables', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        // Process result mappings
        $result_mappings = json_decode($config->result_mappings, true);
        $results = $this->extract_mapped_results($variables_result, $result_mappings);

        return array(
            'success' => true,
            'results' => $results,
            'process_instance_id' => $process_instance_id,
            'execution_time_ms' => round((microtime(true) - $start_time) * 1000, 2),
            'method' => 'process_execution',
            'variables_source' => isset($active_response) && !is_wp_error($active_response) ? 'active' : 'history'
        );
    }

    /**
     * Optimized process execution with improved batch processing and error recovery
     *
     * Enhanced version of process execution that implements intelligent batching,
     * improved error recovery, connection pooling, and performance monitoring.
     * Provides better handling of long-running processes and complex orchestration scenarios.
     *
     * @param object $config Configuration object containing process settings
     * @param array $form_data Form data to be passed as process variables
     * @return array|WP_Error Process execution results with enhanced error handling
     * @since 1.0.0
     */
    private function handle_process_execution_optimized($config, $form_data)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Starting OPTIMIZED process execution for key: ' . $config->process_key);
        }

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
        $start_time = microtime(true);

        // Step 1: Start Process
        $process_endpoint = $this->build_process_endpoint($config->dmn_endpoint, $config->process_key);
        $start_response = $this->make_api_call($process_endpoint, array('variables' => $variables), 'POST');

        if (is_wp_error($start_response))
        {
            return $start_response;
        }

        if (empty($start_response['id']))
        {
            return new WP_Error(
                'process_start_failed',
                __('Process instance creation failed', 'operaton-dmn'),
                array('status' => 500)
            );
        }

        $process_instance_id = $start_response['id'];

        // Step 2: Optimized variable retrieval with intelligent fallback
        $variables_result = $this->retrieve_process_variables_with_fallback($base_url, $process_instance_id);

        if (is_wp_error($variables_result))
        {
            return $variables_result;
        }

        // Process result mappings
        $result_mappings = json_decode($config->result_mappings, true);
        $results = $this->extract_mapped_results($variables_result['variables'], $result_mappings);

        return array(
            'success' => true,
            'results' => $results,
            'process_instance_id' => $process_instance_id,
            'execution_time_ms' => round((microtime(true) - $start_time) * 1000, 2),
            'method' => 'process_execution_optimized',
            'variables_source' => $variables_result['source'],
            'optimization_used' => true
        );
    }

    /**
     * Handle direct decision evaluation without process orchestration
     *
     * Evaluates a decision table directly using the Operaton DMN engine without
     * process orchestration. Suitable for standalone decision logic that doesn't
     * require complex workflows or external integrations.
     *
     * @param object $config Configuration object containing decision settings
     * @param array $form_data Form data for decision evaluation
     * @return array|WP_Error Decision evaluation results
     * @since 1.0.0
     */
    private function handle_decision_evaluation($config, $form_data)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Starting decision evaluation for key: ' . $config->decision_key);
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

        // Build evaluation endpoint
        $endpoint = $this->build_evaluation_endpoint($config->dmn_endpoint, $config->decision_key);

        // Prepare evaluation data
        $evaluation_data = array('variables' => $variables);

        $start_time = microtime(true);

        // Make API call
        $response = $this->make_api_call($endpoint, $evaluation_data, 'POST');

        if (is_wp_error($response))
        {
            return $response;
        }

        // Process result mappings
        $result_mappings = json_decode($config->result_mappings, true);
        $results = $this->extract_mapped_results($response, $result_mappings);

        return array(
            'success' => true,
            'results' => $results,
            'execution_time_ms' => round((microtime(true) - $start_time) * 1000, 2),
            'method' => 'decision_evaluation',
            'decision_key' => $config->decision_key
        );
    }
}
