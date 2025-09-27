<?php

/**
 * Decision flow and health monitoring trait for Operaton DMN Plugin
 *
 * Handles decision flow visualization, health monitoring, and related
 * REST endpoints for process execution summaries and system diagnostics.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}

trait Operaton_DMN_API_Decision_Flow
{
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
        operaton_debug('API', 'Getting decision flow summary', ['form_id' => $form_id]);

        // Check configuration and requirements
        $config = $this->database->get_config_by_form_id($form_id);
        if (!$config || !$config->show_decision_flow || !$config->use_process)
        {
            operaton_debug_verbose('API', 'Decision flow not available - not using process execution or disabled');

            return $this->get_decision_flow_placeholder();
        }

        // Handle cache busting
        $cache_bust = isset($_GET['cache_bust']) ? sanitize_text_field($_GET['cache_bust']) : '';
        $cache_key = 'operaton_decision_flow_' . $form_id;

        if (empty($cache_bust))
        {
            $cached_result = get_transient($cache_key);
            if ($cached_result !== false)
            {
                operaton_debug_verbose('API', 'Returning cached decision flow', ['form_id' => $form_id]);
                return $cached_result;
            }
        }
        else
        {
            delete_transient($cache_key);
        }

        // Get process instance ID
        $process_instance_id = $this->database->get_process_instance_id($form_id);
        if (!$process_instance_id)
        {
            $result = $this->get_decision_flow_loading_message();

            if (empty($cache_bust))
            {
                set_transient($cache_key, $result, 60); // Cache for 1 minute
            }
            return $result;
        }

        // Rate limiting for API calls
        $api_cache_key = 'operaton_api_call_' . $process_instance_id;
        if (empty($cache_bust) && get_transient($api_cache_key))
        {
            return $this->get_decision_flow_loading_message();
        }

        if (empty($cache_bust))
        {
            set_transient($api_cache_key, true, 5); // 5 second rate limit
        }

        // Fetch decision flow data
        $decision_instances = $this->fetch_decision_flow_data($config, $process_instance_id);

        if (is_wp_error($decision_instances))
        {
            $result = $this->format_decision_flow_error($decision_instances->get_error_message());

            if (empty($cache_bust))
            {
                set_transient($cache_key, $result, 120); // Cache error for 2 minutes
            }
            return $result;
        }

        // Format and cache successful result
        $result = $this->format_decision_flow_summary($decision_instances, $process_instance_id);

        if (empty($cache_bust))
        {
            set_transient($cache_key, $result, 600); // Cache for 10 minutes
        }

        return $result;
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

        operaton_debug_verbose('API', 'Getting decision flow from URL', ['url' => $history_url]);

        $response = wp_remote_get($history_url, array(
            'headers' => array('Accept' => 'application/json'),
            'timeout' => 15,
            'sslverify' => $this->ssl_verify,
        ));

        if (is_wp_error($response))
        {
            return new WP_Error(
                'api_error',
                sprintf(__('Error retrieving decision flow: %s', 'operaton-dmn'), $response->get_error_message())
            );
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($http_code !== 200)
        {
            return new WP_Error(
                'api_error',
                sprintf(__('Error loading decision flow (HTTP %d). Please try again.', 'operaton-dmn'), $http_code)
            );
        }

        $decision_instances = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE)
        {
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
        operaton_debug_verbose('API', 'Formatting decision flow summary', ['instances_count' => count($decision_instances)]);

        $html = '<h3>📋 Decision Flow Results Summary</h3>';
        $html .= '<p><strong>Process Instance:</strong> <code>' . esc_html($process_instance_id) . '</code></p>';

        if (empty($decision_instances) || !is_array($decision_instances))
        {
            return $html . '<div class="decision-flow-empty"><p><em>No decision instances found for this process.</em></p></div>';
        }

        // Filter and process decision instances
        $filtered_instances = $this->filter_decision_instances($decision_instances);

        if (empty($filtered_instances))
        {
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

        foreach ($decision_instances as $instance)
        {
            if (isset($instance['activityId']) && $instance['activityId'] === 'Activity_FinalResultCompilation')
            {
                $filtered_instances[] = $instance;
                $has_final_compilation = true;
            }
        }

        // If no FinalResultCompilation activity, get the latest evaluation for each decision
        if (!$has_final_compilation)
        {
            $latest_by_decision = array();

            foreach ($decision_instances as $instance)
            {
                if (isset($instance['decisionDefinitionKey']) && isset($instance['evaluationTime']))
                {
                    $key = $instance['decisionDefinitionKey'];
                    $eval_time = $instance['evaluationTime'];

                    if (
                        !isset($latest_by_decision[$key]) ||
                        strtotime($eval_time) > strtotime($latest_by_decision[$key]['evaluationTime'])
                    )
                    {
                        $latest_by_decision[$key] = $instance;
                    }
                }
            }

            $filtered_instances = array_values($latest_by_decision);
        }

        // Sort by evaluation time
        usort($filtered_instances, function ($a, $b)
        {
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
        foreach ($filtered_instances as $instance)
        {
            if (isset($instance['decisionDefinitionKey']))
            {
                $key = $instance['decisionDefinitionKey'];
                if (!isset($decisions_by_key[$key]))
                {
                    $decisions_by_key[$key] = array();
                }
                $decisions_by_key[$key][] = $instance;
            }
        }

        $has_final_compilation = false;
        foreach ($all_instances as $instance)
        {
            if (isset($instance['activityId']) && $instance['activityId'] === 'Activity_FinalResultCompilation')
            {
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
        foreach ($filtered_instances as $instance)
        {
            if (isset($instance['decisionDefinitionKey']))
            {
                $key = $instance['decisionDefinitionKey'];
                if (!isset($decisions_by_key[$key]))
                {
                    $decisions_by_key[$key] = array();
                }
                $decisions_by_key[$key][] = $instance;
            }
        }

        $html = '<div class="decision-flow-tables">';
        $step = 1;

        foreach ($decisions_by_key as $decision_key => $instances)
        {
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
        if (isset($instance['inputs']) && is_array($instance['inputs']) && count($instance['inputs']) > 0)
        {
            $html .= $this->generate_table_section($instance['inputs'], 'inputs', '📥 Inputs');
        }

        // OUTPUTS Section
        if (isset($instance['outputs']) && is_array($instance['outputs']) && count($instance['outputs']) > 0)
        {
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

        foreach ($items as $item)
        {
            $html .= '<tr class="' . $row_class . '">';

            if ($first_item)
            {
                $html .= '<td class="row-header ' . $header_class . '" rowspan="' . $item_count . '">' . $header . '</td>';
                $first_item = false;
            }

            // Variable name
            $name = 'Unknown ' . ucfirst(rtrim($type, 's'));
            if (isset($item['clauseName']) && !empty($item['clauseName']))
            {
                $name = $item['clauseName'];
            }
            elseif (isset($item['variableName']) && !empty($item['variableName']))
            {
                $name = $item['variableName'];
            }
            elseif (isset($item['name']) && !empty($item['name']))
            {
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
        if (!array_key_exists('value', $item))
        {
            return '<em class="no-value">no value</em>';
        }

        $value = $item['value'];

        if (is_null($value) || $value === '')
        {
            return '<em class="null-value">null</em>';
        }
        elseif (is_bool($value))
        {
            $icon = $value ? '✅' : '❌';
            $text = $value ? 'true' : 'false';
            $class = $value ? 'true' : 'false';
            return '<span class="boolean-value ' . $class . '">' . $icon . ' ' . $text . '</span>';
        }
        elseif (is_numeric($value))
        {
            return '<span class="numeric-value">' . esc_html((string) $value) . '</span>';
        }
        elseif (is_array($value))
        {
            return '<span class="array-value">' . esc_html(json_encode($value)) . '</span>';
        }
        else
        {
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

        if (isset($instance['evaluationTime']))
        {
            $formatted_time = $this->format_evaluation_time($instance['evaluationTime']);
            $html .= '<small><strong>⏱️ Evaluation Time:</strong> ' . esc_html($formatted_time) . '</small>';
        }

        if (isset($instance['activityId']))
        {
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
            '<h3>📋 Decision Flow Results</h3>' .
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
            '<h3>📋 Decision Flow Results</h3>' .
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
            '<h3>📋 Decision Flow Results</h3>' .
            '<p><em>Error retrieving decision flow: ' . esc_html($error_message) . '</em></p>' .
            '</div>';
    }
}
