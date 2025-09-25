<?php

/**
 * AJAX handlers trait for Operaton DMN Plugin
 *
 * Contains all AJAX endpoint handlers for admin functionality including
 * endpoint testing, configuration validation, and debug operations.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Operaton_DMN_API_AJAX_Handlers
{
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

    /**
     * AJAX handler for debug tests
     * Handles debug test execution requests
     *
     * @since 1.0.0
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
     * Main AJAX handler for comprehensive debug testing
     *
     * @since 1.0.0
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

    /**
     * AJAX handler for testing complete configuration
     * Enhanced version of the existing test functionality
     *
     * @since 1.0.0
     */
    public function ajax_test_configuration_complete()
    {
        // Prevent any output before JSON response
        ob_clean();

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Testing complete configuration via AJAX');
        }

        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce') || !current_user_can('manage_options'))
        {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'operaton-dmn')
            ));
            return;
        }

        $config_id = intval($_POST['config_id']);

        if (empty($config_id))
        {
            wp_send_json_error(array(
                'message' => __('Configuration ID is required', 'operaton-dmn')
            ));
            return;
        }

        try
        {
            $test_result = $this->test_configuration_complete($config_id);

            if ($test_result['success'])
            {
                wp_send_json_success($test_result);
            }
            else
            {
                wp_send_json_error($test_result);
            }
        }
        catch (Exception $e)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Test configuration error: ' . $e->getMessage());
            }

            wp_send_json_error(array(
                'message' => 'Test execution failed: ' . $e->getMessage()
            ));
        }
    }
}