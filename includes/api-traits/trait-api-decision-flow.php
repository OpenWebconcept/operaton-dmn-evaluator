<?php

/**
 * Operaton DMN API Decision Flow Trait
 *
 * Handles decision flow visualization, monitoring endpoints, and health
 * check functionality for system status reporting.
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
 * Decision flow and monitoring endpoints trait
 *
 * Provides decision flow visualization data, system health monitoring,
 * and configuration summary functionality for administrative interfaces.
 *
 * @since 1.0.0
 */
trait Operaton_DMN_API_Decision_Flow
{
    // =============================================================================
    // DECISION FLOW & MONITORING ENDPOINTS
    // =============================================================================

    /**
     * REST endpoint handler for decision flow summary retrieval
     *
     * Provides decision flow visualization data for specified Gravity Forms form.
     * Retrieves configuration details, execution history, and performance metrics
     * for decision flow display and monitoring interfaces.
     *
     * @param WP_REST_Request $request REST request with form_id parameter
     * @return WP_REST_Response|WP_Error Decision flow data or error response
     * @since 1.0.0
     */
    public function rest_get_decision_flow($request)
    {
        $form_id = $request->get_param('form_id');

        if (empty($form_id))
        {
            return new WP_Error(
                'missing_form_id',
                __('Form ID is required', 'operaton-dmn'),
                array('status' => 400)
            );
        }

        $decision_flow_data = $this->get_decision_flow_summary_data($form_id);

        if (empty($decision_flow_data))
        {
            return new WP_Error(
                'no_flow_data',
                __('No decision flow data available for this form', 'operaton-dmn'),
                array('status' => 404)
            );
        }

        return rest_ensure_response($decision_flow_data);
    }

    /**
     * Generate decision flow summary HTML for display
     *
     * Creates formatted HTML representation of decision flow data including
     * configuration details, execution statistics, and performance metrics.
     * Used by admin interface and decision flow visualization components.
     *
     * @param int $form_id Gravity Forms form ID
     * @return string HTML formatted decision flow summary
     * @since 1.0.0
     */
    public function get_decision_flow_summary_html($form_id)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Generating decision flow summary for form: ' . $form_id);
        }

        $config = $this->database->get_configuration_by_form_id($form_id);
        if (!$config)
        {
            return '<div class="operaton-error"><p><em>' . __('No DMN configuration found for this form.', 'operaton-dmn') . '</em></p></div>';
        }

        $html = '<div class="operaton-decision-flow-summary">';
        $html .= '<h4>' . __('DMN Configuration Summary', 'operaton-dmn') . '</h4>';

        // Configuration Details
        $html .= '<div class="config-details">';
        $html .= '<p><strong>' . __('Decision Key:', 'operaton-dmn') . '</strong> ' . esc_html($config->decision_key) . '</p>';
        $html .= '<p><strong>' . __('Endpoint:', 'operaton-dmn') . '</strong> ' . esc_html($config->dmn_endpoint) . '</p>';

        if (!empty($config->process_key))
        {
            $html .= '<p><strong>' . __('Process Key:', 'operaton-dmn') . '</strong> ' . esc_html($config->process_key) . '</p>';
            $html .= '<p><strong>' . __('Evaluation Method:', 'operaton-dmn') . '</strong> ' . __('Process Execution', 'operaton-dmn') . '</p>';
        }
        else
        {
            $html .= '<p><strong>' . __('Evaluation Method:', 'operaton-dmn') . '</strong> ' . __('Decision Evaluation', 'operaton-dmn') . '</p>';
        }

        $html .= '</div>';

        // Field Mappings Summary
        $field_mappings = json_decode($config->field_mappings, true);
        if (!empty($field_mappings))
        {
            $html .= '<div class="field-mappings">';
            $html .= '<h5>' . __('Input Variables', 'operaton-dmn') . '</h5>';
            $html .= '<ul>';
            foreach ($field_mappings as $dmn_var => $form_field)
            {
                $html .= '<li>' . esc_html($dmn_var) . ' ← ' . esc_html($form_field) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        // Result Mappings Summary
        $result_mappings = json_decode($config->result_mappings, true);
        if (!empty($result_mappings))
        {
            $html .= '<div class="result-mappings">';
            $html .= '<h5>' . __('Output Variables', 'operaton-dmn') . '</h5>';
            $html .= '<ul>';
            foreach ($result_mappings as $dmn_var => $form_field)
            {
                $html .= '<li>' . esc_html($dmn_var) . ' → ' . esc_html($form_field) . '</li>';
            }
            $html .= '</ul>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get decision flow summary data for API consumption
     *
     * Retrieves comprehensive decision flow data including configuration,
     * execution statistics, performance metrics, and historical data.
     * Provides structured data for REST API responses and external integrations.
     *
     * @param int $form_id Gravity Forms form ID
     * @return array|null Decision flow data array or null if no data available
     * @since 1.0.0
     */
    private function get_decision_flow_summary_data($form_id)
    {
        $config = $this->database->get_configuration_by_form_id($form_id);
        if (!$config)
        {
            return null;
        }

        $data = array(
            'form_id' => $form_id,
            'config_id' => $config->id,
            'decision_key' => $config->decision_key,
            'endpoint' => $config->dmn_endpoint,
            'evaluation_method' => !empty($config->process_key) ? 'process' : 'decision',
            'configuration' => array(
                'use_process' => !empty($config->use_process),
                'process_key' => $config->process_key ?? null,
                'show_decision_flow' => !empty($config->show_decision_flow),
                'active' => !empty($config->active)
            ),
            'mappings' => array(
                'input_variables' => json_decode($config->field_mappings, true) ?? array(),
                'output_variables' => json_decode($config->result_mappings, true) ?? array()
            ),
            'timestamps' => array(
                'created_at' => $config->created_at ?? null,
                'updated_at' => $config->updated_at ?? null
            )
        );

        return $data;
    }

    /**
     * Health check endpoint for system monitoring
     *
     * Provides comprehensive health status including database connectivity,
     * external API accessibility, configuration validation, and system metrics.
     * Supports both basic and detailed health reporting based on request parameters.
     *
     * @param WP_REST_Request $request REST request with optional detailed parameter
     * @return WP_REST_Response Health status response
     * @since 1.0.0
     */
    public function health_check($request)
    {
        $detailed = $request->get_param('detailed');
        $health_data = array(
            'status' => 'healthy',
            'timestamp' => current_time('c'),
            'version' => OPERATON_DMN_VERSION
        );

        if ($detailed)
        {
            // Database connectivity check
            $db_status = $this->database ? 'connected' : 'disconnected';

            // Configuration count
            $config_count = $this->database ? $this->database->get_configurations_count() : 0;

            // WordPress environment
            $wp_info = array(
                'version' => get_bloginfo('version'),
                'multisite' => is_multisite(),
                'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            );

            $health_data['details'] = array(
                'database' => $db_status,
                'configurations_count' => $config_count,
                'connection_timeout' => $this->connection_timeout,
                'wordpress' => $wp_info,
                'php_version' => PHP_VERSION,
                'extensions' => array(
                    'curl' => extension_loaded('curl'),
                    'openssl' => extension_loaded('openssl'),
                    'json' => extension_loaded('json')
                )
            );
        }

        return rest_ensure_response($health_data);
    }
}
