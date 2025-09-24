<?php

/**
 * Operaton DMN API AJAX Handlers Trait
 *
 * Contains all AJAX handler methods for admin interface integration
 * including endpoint testing, configuration validation, and debug operations.
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
 * AJAX handlers trait for admin interface integration
 *
 * Provides AJAX endpoint handlers for configuration testing, validation,
 * debugging, and administrative functions used by the WordPress admin interface.
 *
 * @since 1.0.0
 */
trait Operaton_DMN_API_Ajax_Handlers
{
    // =============================================================================
    // AJAX HANDLERS - ADMIN INTERFACE INTEGRATION
    // =============================================================================

    /**
     * AJAX handler for endpoint connectivity testing
     *
     * Tests basic connectivity to a DMN endpoint with minimal payload.
     * Provides quick validation of endpoint accessibility and basic authentication.
     * Used by admin interface for initial endpoint validation.
     *
     * @since 1.0.0
     */
    public function ajax_test_endpoint()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: AJAX endpoint test initiated');
        }

        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce'))
        {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'operaton-dmn')
            ));
        }

        $endpoint = sanitize_url($_POST['endpoint']);
        if (empty($endpoint))
        {
            wp_send_json_error(array(
                'message' => __('Endpoint URL is required', 'operaton-dmn')
            ));
        }

        // Test basic connectivity
        $test_url = rtrim($endpoint, '/') . '/engine-rest/version';
        $response = $this->make_api_call($test_url, array(), 'GET');

        if (is_wp_error($response))
        {
            wp_send_json_error(array(
                'message' => $response->get_error_message(),
                'endpoint' => $endpoint
            ));
        }

        wp_send_json_success(array(
            'message' => __('Endpoint connectivity test successful', 'operaton-dmn'),
            'endpoint' => $endpoint,
            'engine_version' => isset($response['version']) ? $response['version'] : 'Unknown'
        ));
    }

    /**
     * AJAX handler for comprehensive configuration testing
     *
     * Performs complete configuration validation including endpoint connectivity,
     * decision key availability, field mapping validation, and sample evaluation
     * execution. Provides detailed feedback for troubleshooting configuration issues.
     *
     * @since 1.0.0
     */
    public function ajax_test_configuration_complete()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Complete configuration test initiated');
        }

        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce'))
        {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'operaton-dmn')
            ));
        }

        $config_id = intval($_POST['config_id']);
        if (empty($config_id))
        {
            wp_send_json_error(array(
                'message' => __('Configuration ID is required', 'operaton-dmn')
            ));
        }

        $config = $this->database->get_configuration($config_id);
        if (!$config)
        {
            wp_send_json_error(array(
                'message' => __('Configuration not found', 'operaton-dmn')
            ));
        }

        // Determine test method based on configuration
        $use_process = isset($config->use_process) ? $config->use_process : false;

        if ($use_process && !empty($config->process_key))
        {
            $test_result = $this->test_process_configuration_comprehensive($config);
        }
        else
        {
            $test_result = $this->test_decision_configuration_comprehensive($config);
        }

        if ($test_result['success'])
        {
            wp_send_json_success($test_result);
        }
        else
        {
            wp_send_json_error($test_result);
        }
    }

    /**
     * AJAX handler for full endpoint and decision key configuration testing
     *
     * Legacy AJAX handler that provides backward compatibility for existing
     * admin interfaces. Performs endpoint connectivity and decision key validation
     * with comprehensive error reporting.
     *
     * @since 1.0.0
     */
    public function ajax_test_full_config()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Full config test initiated');
        }

        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce'))
        {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions', 'operaton-dmn')
            ));
        }

        $base_endpoint = sanitize_url($_POST['base_endpoint']);
        $decision_key = sanitize_text_field($_POST['decision_key']);

        if (empty($base_endpoint) || empty($decision_key))
        {
            wp_send_json_error(array(
                'message' => __('Base endpoint and decision key are required', 'operaton-dmn')
            ));
        }

        $test_result = $this->test_full_endpoint_configuration($base_endpoint, $decision_key);

        if ($test_result['success'])
        {
            wp_send_json_success($test_result);
        }
        else
        {
            wp_send_json_error($test_result);
        }
    }

    /**
     * AJAX handler for clearing WordPress update cache and forcing update checks
     *
     * Removes cached update information to trigger fresh plugin update detection.
     * Clears WordPress update transients and forces a fresh check for plugin
     * updates. Used by admin interface for manual update cache management.
     *
     * @since 1.0.0
     */
    public function ajax_clear_update_cache()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Clearing update cache');
        }

        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce'))
        {
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
     * AJAX handler for comprehensive DMN debug operations
     *
     * Provides detailed debugging information including server configuration,
     * plugin initialization status, REST API availability, and API connectivity tests.
     * Used by debug interface for comprehensive system diagnostics.
     *
     * @since 1.0.0
     */
    public function handle_dmn_debug_ajax()
    {
        try
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Debug AJAX handler called');
            }

            wp_send_json_success([
                'message' => 'Debug test completed successfully',
                'debug_data' => [
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
     * Public AJAX handler for DMN debug operations
     *
     * Provides public access to basic debug functionality for testing
     * REST API accessibility from frontend contexts. Used for connectivity
     * validation from public-facing forms and interfaces.
     *
     * @since 1.0.0
     */
    public function run_operaton_dmn_debug()
    {
        try
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN API: Public debug handler called');
            }

            wp_send_json_success([
                'message' => 'Public debug test completed',
                'rest_api_available' => rest_url('operaton-dmn/v1/') ? true : false,
                'timestamp' => current_time('mysql')
            ]);
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN Public Debug Error: ' . $e->getMessage());
            wp_send_json_error('Public debug test failed: ' . $e->getMessage());
        }
    }
}
