<?php

/**
 * Admin Configuration List Template
 *
 * Displays all DMN configurations with professional styling
 * and management controls.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}
?>

<!-- Debug Tools Section - Now positioned after header with consistent styling -->
<div class="operaton-update-section">
    <h3><?php _e('Debug Tools', 'operaton-dmn'); ?></h3>
    <p><?php _e('Development and troubleshooting tools for plugin diagnostics and testing.', 'operaton-dmn'); ?></p>

    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
        <button type="button" id="get-plugin-status" class="button"
            title="<?php _e('Get current plugin status and configuration information', 'operaton-dmn'); ?>">
            <?php _e('Get Plugin Status', 'operaton-dmn'); ?>
        </button>

        <button type="button" id="run-dmn-debug" class="button"
            title="<?php _e('Run comprehensive DMN debug tests (check error logs)', 'operaton-dmn'); ?>">
            <?php _e('Run DMN Debug Tests', 'operaton-dmn'); ?>
        </button>
    </div>

    <div id="debug-operation-result" style="margin-top: 10px;"></div>

    <details style="margin-top: 15px;">
        <summary style="cursor: pointer; font-weight: 600; color: #0073aa;"><?php _e('Debug Tools Help', 'operaton-dmn'); ?></summary>
        <div style="padding: 10px 0; font-size: 14px; color: #666; line-height: 1.5;">
            <ul style="margin-left: 20px;">
                <li><strong><?php _e('Plugin Status:', 'operaton-dmn'); ?></strong> <?php _e('Shows current plugin version, configuration status, and system information', 'operaton-dmn'); ?></li>
                <li><strong><?php _e('DMN Debug Tests:', 'operaton-dmn'); ?></strong> <?php _e('Runs comprehensive server configuration, plugin initialization, and REST API tests (results logged to error log)', 'operaton-dmn'); ?></li>
            </ul>
            <p style="margin-top: 10px;"><strong><?php _e('When to use:', 'operaton-dmn'); ?></strong></p>
            <ul style="margin-left: 20px;">
                <li><?php _e('When troubleshooting plugin issues', 'operaton-dmn'); ?></li>
                <li><?php _e('Before contacting support', 'operaton-dmn'); ?></li>
                <li><?php _e('After plugin updates to verify functionality', 'operaton-dmn'); ?></li>
                <li><?php _e('When DMN evaluations are failing', 'operaton-dmn'); ?></li>
            </ul>
        </div>
    </details>
</div>

<!-- Enhanced Decision Flow Cache Management Section -->
<div class="operaton-update-section">
    <h3>Configuration & Cache Management</h3>
    <p>Manage cached decision flow data and configuration settings. Clear cache when you update DMN endpoints or experience configuration issues.</p>

    <!-- Remove the PHP success messages since we're using AJAX now -->

    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
        <button type="button" id="clear-decision-cache" class="button"
            title="Clear decision flow summaries and temporary data">
            Clear Decision Flow Cache
        </button>

        <button type="button" id="clear-all-cache" class="button button-secondary"
            title="Clear all cached configurations, decision flows, and force database reload">
            Clear All Configuration Cache
        </button>

        <button type="button" id="force-reload-configs" class="button button-secondary"
            title="Force reload all configurations from database without using cache">
            Force Reload Configurations
        </button>
        <!-- NEW: Connection Efficiency Button -->
        <button type="button" id="check-connection-stats" class="button button-secondary"
            title="Check HTTP connection reuse efficiency for API calls">
            Check Connection Efficiency
        </button>
    </div>

    <div id="cache-operation-result" style="margin-top: 10px;"></div>

    <details style="margin-top: 15px;">
        <summary style="cursor: pointer; font-weight: 600; color: #0073aa;">Cache Management Help</summary>
        <div style="padding: 10px 0; font-size: 14px; color: #666; line-height: 1.5;">
            <ul style="margin-left: 20px;">
                <li><strong>Decision Flow Cache:</strong> Clears cached decision flow summaries and temporary evaluation data</li>
                <li><strong>All Configuration Cache:</strong> Clears ALL cached data including form configurations, transients, and forces fresh database reads</li>
                <li><strong>Force Reload:</strong> Bypasses cache and reloads all configurations directly from database</li>
                <li><strong>Connection Efficiency:</strong> Shows HTTP connection reuse statistics and optimization performance</li>
            </ul>
            <p style="margin-top: 10px;"><strong>When to use:</strong></p>
            <ul style="margin-left: 20px;">
                <li>After updating DMN endpoint URLs in form configurations</li>
                <li>When forms show old evaluation results</li>
                <li>If configuration changes aren't being reflected</li>
                <li>After plugin updates or database schema changes</li>
                <li>To monitor API call efficiency and connection reuse performance</li>
            </ul>
        </div>
    </details>
</div>

<!-- Update Management Section -->
<?php if (current_user_can('manage_options')): ?>
    <div class="operaton-update-section">
        <h3><?php _e('Plugin Updates', 'operaton-dmn'); ?></h3>

        <p><strong><?php _e('Current Version:', 'operaton-dmn'); ?></strong> <?php echo esc_html(OPERATON_DMN_VERSION); ?></p>

        <?php
        $update_plugins = get_site_transient('update_plugins');
        $has_update = false;
        $new_version = '';

        if (isset($update_plugins->response))
        {
            foreach ($update_plugins->response as $plugin => $data)
            {
                if (strpos($plugin, 'operaton-dmn') !== false)
                {
                    $has_update = true;
                    $new_version = $data->new_version;
                    break;
                }
            }
        }
        ?>

        <?php if ($has_update): ?>
            <div class="operaton-update-available">
                <p><strong><?php _e('Update Available:', 'operaton-dmn'); ?></strong> <?php echo esc_html($new_version); ?></p>
                <p>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-primary">
                        <?php _e('Go to Plugins Page to Update', 'operaton-dmn'); ?>
                    </a>
                </p>
            </div>
        <?php else: ?>
            <p class="operaton-update-current">✓ <?php _e('You are running the latest version', 'operaton-dmn'); ?></p>
        <?php endif; ?>

        <p>
            <button type="button" id="operaton-check-updates" class="button">
                <?php _e('Check for Updates Now', 'operaton-dmn'); ?>
            </button>
            <span id="operaton-update-status" style="margin-left: 10px;"></span>
        </p>
    </div>
<?php endif; ?>

<!-- Add New Configuration Button -->
<div style="margin: 20px 0;">
    <a href="<?php echo admin_url('admin.php?page=operaton-dmn-add'); ?>" class="button button-primary">
        <?php _e('Add New Configuration', 'operaton-dmn'); ?>
    </a>
</div>

<!-- Configurations Table -->
<?php if (empty($configs)): ?>
    <div class="operaton-config-form-wrap">
        <div style="padding: 40px; text-align: center; color: #666;">
            <h3><?php _e('No Configurations Found', 'operaton-dmn'); ?></h3>
            <p><?php _e('You haven\'t created any DMN configurations yet.', 'operaton-dmn'); ?></p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=operaton-dmn-add'); ?>" class="button button-primary">
                    <?php _e('Create Your First Configuration', 'operaton-dmn'); ?>
                </a>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="operaton-config-form-wrap">
        <table class="operaton-config-table">
            <thead>
                <tr>
                    <th><?php _e('Name', 'operaton-dmn'); ?></th>
                    <th><?php _e('Form', 'operaton-dmn'); ?></th>
                    <th><?php _e('Mode', 'operaton-dmn'); ?></th>
                    <th><?php _e('Endpoint', 'operaton-dmn'); ?></th>
                    <th><?php _e('Key', 'operaton-dmn'); ?></th>
                    <th><?php _e('Status', 'operaton-dmn'); ?></th>
                    <th><?php _e('Actions', 'operaton-dmn'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($configs as $config): ?>
                    <tr>
                        <td class="operaton-config-name">
                            <?php echo esc_html($config->name); ?>
                            <?php if (!empty($config->button_text) && $config->button_text !== 'Evaluate'): ?>
                                <br><small style="color: #666;"><?php _e('Button:', 'operaton-dmn'); ?> "<?php echo esc_html($config->button_text); ?>"</small>
                            <?php endif; ?>
                        </td>
                        <td class="operaton-config-form">
                            <?php
                            // Get form title if possible
                            $form_title = 'Form #' . $config->form_id;
                            if (class_exists('GFAPI'))
                            {
                                $form = GFAPI::get_form($config->form_id);
                                if ($form)
                                {
                                    $form_title = esc_html($form['title']) . ' (#' . $config->form_id . ')';
                                }
                            }
                            echo $form_title;
                            ?>
                        </td>
                        <td>
                            <?php if (isset($config->use_process) && $config->use_process): ?>
                                <span style="color: #0073aa; font-weight: 600;">
                                    <?php _e('Process', 'operaton-dmn'); ?>
                                </span>
                                <?php if (isset($config->show_decision_flow) && $config->show_decision_flow): ?>
                                    <br><small style="color: #46b450;">+ <?php _e('Decision Flow', 'operaton-dmn'); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #666;">
                                    <?php _e('Direct', 'operaton-dmn'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="operaton-config-endpoint">
                            <?php
                            $endpoint = esc_html($config->dmn_endpoint);
                            // Truncate long URLs for display
                            if (strlen($endpoint) > 50)
                            {
                                echo substr($endpoint, 0, 47) . '...';
                            }
                            else
                            {
                                echo $endpoint;
                            }
                            ?>
                        </td>
                        <td style="font-family: 'Courier New', monospace; font-size: 12px;">
                            <?php if (isset($config->use_process) && $config->use_process): ?>
                                <strong><?php _e('Process:', 'operaton-dmn'); ?></strong><br>
                                <?php echo esc_html($config->process_key ?: __('Not set', 'operaton-dmn')); ?>
                            <?php else: ?>
                                <strong><?php _e('Decision:', 'operaton-dmn'); ?></strong><br>
                                <?php echo esc_html($config->decision_key ?: __('Not set', 'operaton-dmn')); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            // Determine status based on configuration completeness
                            $is_complete = !empty($config->dmn_endpoint) &&
                                !empty($config->field_mappings) &&
                                !empty($config->result_mappings);

                            if (isset($config->use_process) && $config->use_process)
                            {
                                $is_complete = $is_complete && !empty($config->process_key);
                            }
                            else
                            {
                                $is_complete = $is_complete && !empty($config->decision_key);
                            }
                            ?>

                            <?php if ($is_complete): ?>
                                <span class="operaton-config-status active">
                                    <?php _e('Active', 'operaton-dmn'); ?>
                                </span>
                            <?php else: ?>
                                <span class="operaton-config-status inactive">
                                    <?php _e('Incomplete', 'operaton-dmn'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="operaton-config-actions">
                            <a href="<?php echo admin_url('admin.php?page=operaton-dmn-add&edit=' . $config->id); ?>"
                                class="button button-small" title="<?php _e('Edit Configuration', 'operaton-dmn'); ?>">
                                <?php _e('Edit', 'operaton-dmn'); ?>
                            </a>

                            <button type="button" class="button button-small button-link-delete"
                                onclick="deleteConfig(<?php echo $config->id; ?>, '<?php echo esc_js($config->name); ?>')"
                                title="<?php _e('Delete Configuration', 'operaton-dmn'); ?>">
                                <?php _e('Delete', 'operaton-dmn'); ?>
                            </button>

                            <?php if ($is_complete): ?>
                                <br style="margin-bottom: 5px;">
                                <button type="button" class="button button-small"
                                    onclick="testConfig(<?php echo $config->id; ?>)"
                                    title="<?php _e('Test Configuration', 'operaton-dmn'); ?>">
                                    <?php _e('Test', 'operaton-dmn'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Configuration Summary -->
    <div class="operaton-notice info" style="margin-top: 20px;">
        <h4><?php _e('Configuration Summary', 'operaton-dmn'); ?></h4>
        <p>
            <?php
            printf(
                __('Total configurations: %d | Active: %d | Incomplete: %d', 'operaton-dmn'),
                count($configs),
                count(array_filter($configs, function ($config)
                {
                    $is_complete = !empty($config->dmn_endpoint) &&
                        !empty($config->field_mappings) &&
                        !empty($config->result_mappings);
                    if (isset($config->use_process) && $config->use_process)
                    {
                        return $is_complete && !empty($config->process_key);
                    }
                    else
                    {
                        return $is_complete && !empty($config->decision_key);
                    }
                })),
                count(array_filter($configs, function ($config)
                {
                    $is_complete = !empty($config->dmn_endpoint) &&
                        !empty($config->field_mappings) &&
                        !empty($config->result_mappings);
                    if (isset($config->use_process) && $config->use_process)
                    {
                        return !($is_complete && !empty($config->process_key));
                    }
                    else
                    {
                        return !($is_complete && !empty($config->decision_key));
                    }
                }))
            );
            ?>
        </p>
    </div>
<?php endif; ?>

<!-- Help Section -->
<div class="operaton-notice info" style="margin-top: 30px;">
    <h4><?php _e('Need Help?', 'operaton-dmn'); ?></h4>
    <p><?php _e('Each configuration connects a Gravity Form to an Operaton DMN decision table or process.', 'operaton-dmn'); ?></p>
    <ul>
        <li><strong><?php _e('Direct Mode:', 'operaton-dmn'); ?></strong> <?php _e('Evaluates a single DMN decision table', 'operaton-dmn'); ?></li>
        <li><strong><?php _e('Process Mode:', 'operaton-dmn'); ?></strong> <?php _e('Executes a BPMN process with multiple decisions and provides decision flow summary', 'operaton-dmn'); ?></li>
    </ul>
    <p>
        <a href="https://docs.operaton.org/" target="_blank" class="button">
            <?php _e('View Documentation', 'operaton-dmn'); ?>
        </a>
    </p>
</div>
</div>

<!-- Confirmation Dialog and JavaScript -->
<div id="delete-confirmation" style="display: none;">
    <p><?php _e('Are you sure you want to delete this configuration?', 'operaton-dmn'); ?></p>
    <p><strong id="config-name-display"></strong></p>
    <p style="color: #d63638;"><em><?php _e('This action cannot be undone.', 'operaton-dmn'); ?></em></p>
</div>

<script>
    // Global functions that need to be called from HTML
    // Delete configuration function
    function deleteConfig(configId, configName) {
        if (confirm('<?php _e('Are you sure you want to delete the configuration', 'operaton-dmn'); ?> "' + configName + '"?\n\n<?php _e('This action cannot be undone.', 'operaton-dmn'); ?>')) {
            // Create a form and submit it
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            var configInput = document.createElement('input');
            configInput.type = 'hidden';
            configInput.name = 'config_id';
            configInput.value = configId;

            var deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_config';
            deleteInput.value = '1';

            var nonceInput = document.createElement('input');
            nonceInput.type = 'hidden';
            nonceInput.name = '_wpnonce';
            nonceInput.value = '<?php echo wp_create_nonce('delete_config'); ?>';

            form.appendChild(configInput);
            form.appendChild(deleteInput);
            form.appendChild(nonceInput);

            document.body.appendChild(form);
            form.submit();
        }
    }

    // Test configuration function
    function testConfig(configId) {
        alert('<?php _e('Testing functionality will be implemented in the next update.', 'operaton-dmn'); ?>');
        // TODO: Implement AJAX testing functionality
    }

    // Global variables to manage feedback timers
    var cacheOperationTimer = null;
    var debugOperationTimer = null;

    // Global function to show full debug data
    function showFullDebugData() {
        if (window.operatonDebugData) {
            var existingFullData = document.getElementById('full-debug-data');
            if (existingFullData) {
                existingFullData.remove();
                return;
            }

            // Append to the parent container instead of the auto-fading result div
            var parentContainer = jQuery('#debug-operation-result').parent();
            var fullDataHtml = '<div id="full-debug-data" style="margin-top: 10px; max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 11px; white-space: pre-wrap; border: 1px solid #ddd;">' +
                '<div style="margin-bottom: 10px; font-family: sans-serif; font-weight: bold; color: #0073aa;">Complete Debug Information:</div>' +
                JSON.stringify(window.operatonDebugData, null, 2) +
                '</div>';

            parentContainer.append(fullDataHtml);

            // Change button text
            var button = jQuery('#debug-operation-result').find('button');
            if (button.length) {
                button.text('Hide Full Details');
            }
        }
    }

    // NEW: Global function to show full DMN debug data
    function showFullDmnDebugData() {
        if (window.operatonDmnDebugData) {
            var existingFullData = document.getElementById('full-dmn-debug-data');
            if (existingFullData) {
                existingFullData.remove();
                return;
            }

            // Append to the parent container instead of the auto-fading result div
            var parentContainer = jQuery('#debug-operation-result').parent();
            var fullDataHtml = '<div id="full-dmn-debug-data" style="margin-top: 10px; max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 11px; white-space: pre-wrap; border: 1px solid #ddd;">' +
                '<div style="margin-bottom: 10px; font-family: sans-serif; font-weight: bold; color: #0073aa;">Complete DMN Debug Test Results:</div>' +
                JSON.stringify(window.operatonDmnDebugData, null, 2) +
                '</div>';

            parentContainer.append(fullDataHtml);

            // Change button text - find the specific button for DMN debug
            var buttons = jQuery('#debug-operation-result').find('button');
            buttons.each(function() {
                if (jQuery(this).text().indexOf('Show Full Test Results') !== -1) {
                    jQuery(this).text('Hide Full Test Results');
                } else if (jQuery(this).text().indexOf('Hide Full Test Results') !== -1) {
                    jQuery(this).text('Show Full Test Results');
                }
            });
        }
    }

    jQuery(document).ready(function($) {
        // Helper function to show feedback with proper timer management
        function showCacheOperationFeedback(html, fadeOut = true) {
            var result = $('#cache-operation-result');

            // Clear any existing timer
            if (cacheOperationTimer) {
                clearTimeout(cacheOperationTimer);
                cacheOperationTimer = null;
            }

            // Make sure the div is visible and show content
            result.stop(true, true).show().html(html);

            // Set new timer if fadeOut is requested
            if (fadeOut) {
                cacheOperationTimer = setTimeout(function() {
                    result.fadeOut(500);
                    cacheOperationTimer = null;
                }, 3000);
            }
        }

        // Helper function for debug operation feedback
        function showDebugOperationFeedback(html, fadeOut = true) {
            var result = $('#debug-operation-result');

            // Clear any existing timer
            if (debugOperationTimer) {
                clearTimeout(debugOperationTimer);
                debugOperationTimer = null;
            }

            // Make sure the div is visible and show content
            result.stop(true, true).show().html(html);

            // Set new timer if fadeOut is requested
            if (fadeOut) {
                debugOperationTimer = setTimeout(function() {
                    result.fadeOut(500);
                    debugOperationTimer = null;
                }, 5000); // Longer timeout for debug info
            }
        }

        // Fixed Get Plugin Status functionality
        $('#get-plugin-status').on('click', function() {
            console.log('Get Plugin Status button clicked');
            var button = $(this);

            button.prop('disabled', true).text('<?php _e('Getting Status...', 'operaton-dmn'); ?>');
            showDebugOperationFeedback('<div style="color: #666; padding: 8px 12px; background: #f1f1f1; border-radius: 4px;">⏳ <?php _e('Retrieving plugin status information...', 'operaton-dmn'); ?></div>', false);

            $.post(ajaxurl, {
                action: 'operaton_debug_status',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success && response.data) {
                    console.log('Plugin status response:', response.data);

                    // Create a formatted display instead of raw JSON
                    var statusHtml = '<div style="color: #155724; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">' +
                        '<h4 style="margin: 0 0 10px 0;">✅ <?php _e('Plugin Status Information', 'operaton-dmn'); ?></h4>';

                    // Display key information in a readable format
                    if (response.data.plugin_version) {
                        statusHtml += '<div><strong><?php _e('Plugin Version:', 'operaton-dmn'); ?></strong> ' + response.data.plugin_version + '</div>';
                    }

                    if (response.data.environment) {
                        statusHtml += '<div><strong><?php _e('WordPress:', 'operaton-dmn'); ?></strong> ' + response.data.environment.wordpress + '</div>';
                        statusHtml += '<div><strong><?php _e('PHP:', 'operaton-dmn'); ?></strong> ' + response.data.environment.php + '</div>';
                    }

                    if (response.data.managers) {
                        var activeManagers = Object.keys(response.data.managers).filter(function(key) {
                            return response.data.managers[key] === 'loaded' || response.data.managers[key] === true;
                        });
                        statusHtml += '<div><strong><?php _e('Active Managers:', 'operaton-dmn'); ?></strong> ' + activeManagers.length + '</div>';
                    }

                    if (response.data.performance && response.data.performance.current_request) {
                        statusHtml += '<div><strong><?php _e('Performance:', 'operaton-dmn'); ?></strong> ' +
                            response.data.performance.current_request.total_time_ms + 'ms, ' +
                            response.data.performance.current_request.peak_memory_formatted + '</div>';
                    }

                    if (response.data.health && Array.isArray(response.data.health)) {
                        var healthStatus = response.data.health.length === 0 ? 'All systems operational' : response.data.health.length + ' issues detected';
                        statusHtml += '<div><strong><?php _e('Health Status:', 'operaton-dmn'); ?></strong> ' + healthStatus + '</div>';
                    }

                    statusHtml += '<div style="margin-top: 10px;"><button onclick="showFullDebugData()" class="button button-small"><?php _e('Show Full Details', 'operaton-dmn'); ?></button></div>';
                    statusHtml += '</div>';

                    showDebugOperationFeedback(statusHtml);

                    // Store the full data globally for the "Show Full Details" button
                    window.operatonDebugData = response.data;

                } else {
                    showDebugOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                        '⚠ <strong><?php _e('Status retrieval failed:', 'operaton-dmn'); ?></strong> ' + (response.data ? response.data.message : '<?php _e('Unknown error', 'operaton-dmn'); ?>') + '</div>', false);
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX request failed:', xhr, status, error);
                showDebugOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    '⚠ <strong><?php _e('Status retrieval failed:', 'operaton-dmn'); ?></strong> <?php _e('Connection error', 'operaton-dmn'); ?> (' + status + ')</div>', false);
            }).always(function() {
                button.prop('disabled', false).text('<?php _e('Get Plugin Status', 'operaton-dmn'); ?>');
            });
        });

        // Connection Stats functionality
        $('#check-connection-stats').on('click', function() {
            console.log('Check Connection Efficiency button clicked');
            var button = $(this);

            button.prop('disabled', true).text('Checking...');
            showCacheOperationFeedback('<div style="color: #666; padding: 8px 12px; background: #f1f1f1; border-radius: 4px;">⏳ Analyzing connection pool efficiency...</div>', false);

            $.post(ajaxurl, {
                action: 'operaton_check_connection_stats',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success && response.data.stats) {
                    console.log('Connection stats response:', response.data);

                    var stats = response.data.stats;
                    var statusHtml = '<div style="color: #155724; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">' +
                        '<h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">' +
                        '<span style="color: ' + stats.efficiency_color + ';">📊</span> Connection Efficiency Report</h4>';

                    // Efficiency summary with colored indicator
                    statusHtml += '<div style="margin-bottom: 12px; padding: 8px; background: rgba(255,255,255,0.7); border-radius: 4px;">' +
                        '<div style="font-size: 16px; font-weight: 600; color: ' + stats.efficiency_color + ';">' +
                        stats.summary + '</div></div>';

                    // Connection details in a grid
                    if (stats.details && Object.keys(stats.details).length > 0) {
                        statusHtml += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin-bottom: 10px;">';

                        Object.entries(stats.details).forEach(function([key, value]) {
                            var isEfficiency = key.includes('Reused');
                            var textColor = isEfficiency ? stats.efficiency_color : '#495057';
                            statusHtml += '<div style="padding: 6px 8px; background: rgba(255,255,255,0.8); border-radius: 3px; border-left: 3px solid ' +
                                (isEfficiency ? stats.efficiency_color : '#dee2e6') + ';">' +
                                '<div style="font-size: 11px; color: #6c757d; text-transform: uppercase; font-weight: 600;">' + key + '</div>' +
                                '<div style="font-size: 14px; font-weight: 600; color: ' + textColor + ';">' + value + '</div>' +
                                '</div>';
                        });

                        statusHtml += '</div>';
                    }

                    // Performance recommendations
                    if (stats.efficiency_percent < 50) {
                        statusHtml += '<div style="margin-top: 10px; padding: 8px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; font-size: 13px;">' +
                            '<strong>💡 Optimization Tip:</strong> Low connection reuse suggests high cache miss rate. ' +
                            'Consider increasing connection pool timeout or checking for endpoint configuration issues.' +
                            '</div>';
                    } else if (stats.efficiency_percent >= 70) {
                        statusHtml += '<div style="margin-top: 10px; padding: 8px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; font-size: 13px;">' +
                            '<strong>🎉 Excellent!</strong> Your connection reuse optimization is working very well. ' +
                            'This indicates efficient API communication with minimal SSL handshake overhead.' +
                            '</div>';
                    }

                    statusHtml += '</div>';

                    showCacheOperationFeedback(statusHtml);

                } else {
                    showCacheOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                        '⚠️ <strong>Connection stats check failed:</strong> ' + (response.data ? response.data.message : 'Unknown error') + '</div>', false);
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX request failed:', xhr, status, error);
                showCacheOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    '⚠️ <strong>Connection stats check failed:</strong> Connection error (' + status + ')</div>', false);
            }).always(function() {
                button.prop('disabled', false).text('Check Connection Efficiency');
            });
        });

        // NEW: Run DMN Debug Tests functionality
        $('#run-dmn-debug').on('click', function() {
            console.log('Run DMN Debug Tests button clicked');
            var button = $(this);

            button.prop('disabled', true).text('<?php _e('Running Tests...', 'operaton-dmn'); ?>');
            showDebugOperationFeedback('<div style="color: #666; padding: 8px 12px; background: #f1f1f1; border-radius: 4px;">⏳ <?php _e('Running comprehensive DMN debug tests...', 'operaton-dmn'); ?></div>', false);

            $.post(ajaxurl, {
                action: 'operaton_dmn_debug',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    console.log('DMN Debug response:', response.data);

                    // Create formatted display with summary and detailed results
                    var debugHtml = '<div style="color: #155724; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">' +
                        '<h4 style="margin: 0 0 10px 0;">✅ <?php _e('DMN Debug Tests Completed', 'operaton-dmn'); ?></h4>';

                    // Add summary if results are available
                    if (response.data.results) {
                        var results = response.data.results;
                        debugHtml += '<div style="margin-bottom: 10px;"><strong><?php _e('Quick Summary:', 'operaton-dmn'); ?></strong></div>';
                        debugHtml += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; margin-bottom: 10px;">';

                        if (results.server_config) {
                            var serverStatus = Object.values(results.server_config).every(val => val === 'Enabled' || val === 'Available') ? '✅' : '⚠️';
                            debugHtml += '<div><strong>Server Config:</strong> ' + serverStatus + '</div>';
                        }
                        if (results.rest_api !== undefined) {
                            debugHtml += '<div><strong>REST API:</strong> ' + (results.rest_api ? '✅ Working' : '❌ Failed') + '</div>';
                        }
                        if (results.api_call !== undefined) {
                            debugHtml += '<div><strong>API Call:</strong> ' + (results.api_call ? '✅ Success' : '❌ Failed') + '</div>';
                        }

                        debugHtml += '</div>';
                    }

                    debugHtml += '<div><strong><?php _e('Status:', 'operaton-dmn'); ?></strong> ' + response.data.message + '</div>';
                    debugHtml += '<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 12px;">' +
                        '<strong><?php _e('Error Log:', 'operaton-dmn'); ?></strong> Detailed test results written to WordPress error log.' +
                        '</div>';

                    // Add "Show Full Details" button similar to plugin status
                    debugHtml += '<div style="margin-top: 10px;"><button onclick="showFullDmnDebugData()" class="button button-small"><?php _e('Show Full Test Results', 'operaton-dmn'); ?></button></div>';
                    debugHtml += '</div>';

                    showDebugOperationFeedback(debugHtml);

                    // Store the full debug data globally for the "Show Full Details" button
                    window.operatonDmnDebugData = response.data;

                } else {
                    showDebugOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                        '⚠ <strong><?php _e('Debug tests failed:', 'operaton-dmn'); ?></strong> ' + (response.data ? response.data.message : '<?php _e('Unknown error', 'operaton-dmn'); ?>') + '</div>', false);
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX request failed:', xhr, status, error);
                showDebugOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    '⚠ <strong><?php _e('Debug tests failed:', 'operaton-dmn'); ?></strong> <?php _e('Connection error', 'operaton-dmn'); ?> (' + status + ')</div>', false);
            }).always(function() {
                button.prop('disabled', false).text('<?php _e('Run DMN Debug Tests', 'operaton-dmn'); ?>');
            });
        });

        // NEW: Global function to show full DMN debug data
        window.showFullDmnDebugData = function() {
            if (window.operatonDmnDebugData) {
                var existingFullData = document.getElementById('full-dmn-debug-data');
                if (existingFullData) {
                    existingFullData.remove();
                    // Change button text back
                    var buttons = jQuery('#debug-operation-result').find('button');
                    buttons.each(function() {
                        if (jQuery(this).text().indexOf('Hide Full Test Results') !== -1) {
                            jQuery(this).text('Show Full Test Results');
                        }
                    });
                    return;
                }

                // Append to the parent container instead of the auto-fading result div
                var parentContainer = jQuery('#debug-operation-result').parent();
                var fullDataHtml = '<div id="full-dmn-debug-data" style="margin-top: 10px; max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 11px; white-space: pre-wrap; border: 1px solid #ddd;">' +
                    '<div style="margin-bottom: 10px; font-family: sans-serif; font-weight: bold; color: #0073aa;">Complete DMN Debug Test Results:</div>' +
                    JSON.stringify(window.operatonDmnDebugData, null, 2) +
                    '</div>';

                parentContainer.append(fullDataHtml);

                // Change button text to "Hide"
                var buttons = jQuery('#debug-operation-result').find('button');
                buttons.each(function() {
                    if (jQuery(this).text().indexOf('Show Full Test Results') !== -1) {
                        jQuery(this).text('Hide Full Test Results');
                    }
                });
            }
        };

        // NEW: Clear Decision Flow Cache functionality (matching other buttons)
        $('#clear-decision-cache').on('click', function() {
            console.log('Clear Decision Flow Cache button clicked');
            var button = $(this);

            button.prop('disabled', true).text('Clearing Cache...');
            showCacheOperationFeedback('<div style="color: #666; padding: 8px 12px; background: #f1f1f1; border-radius: 4px;">⏳ Clearing decision flow cache...</div>', false);

            $.post(ajaxurl, {
                action: 'operaton_clear_decision_cache',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    console.log('AJAX response:', response);
                    showCacheOperationFeedback('<div style="color: #155724; padding: 8px 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">' +
                        '✅ <strong>Decision flow cache cleared successfully!</strong><br>' +
                        '<small>Configurations will reload from database.</small></div>');
                } else {
                    showCacheOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                        '❌ <strong>Cache clear failed:</strong> ' + (response.data ? response.data.message : 'Unknown error') + '</div>', false);
                }
            }).fail(function(xhr, status, error) {
                showCacheOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    '❌ <strong>Cache clear failed:</strong> Connection error (' + status + ')</div>', false);
            }).always(function() {
                button.prop('disabled', false).text('Clear Decision Flow Cache');
            });
        });

        // Update check functionality
        $('#operaton-check-updates').on('click', function() {
            var button = $(this);
            var status = $('#operaton-update-status');

            button.prop('disabled', true).text('<?php _e('Checking...', 'operaton-dmn'); ?>');
            status.html('<span style="color: #666;">⏳ <?php _e('Checking for updates...', 'operaton-dmn'); ?></span>');

            $.post(ajaxurl, {
                action: 'operaton_clear_update_cache',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                    status.html('<span style="color: #46b450;">✓ <?php _e('Update check completed', 'operaton-dmn'); ?></span>');
                } else {
                    status.html('<span style="color: #dc3232;">✗ <?php _e('Update check failed', 'operaton-dmn'); ?></span>');
                    button.prop('disabled', false).text('<?php _e('Check for Updates Now', 'operaton-dmn'); ?>');
                }
            }).fail(function() {
                status.html('<span style="color: #dc3232;">✗ <?php _e('Update check failed', 'operaton-dmn'); ?></span>');
                button.prop('disabled', false).text('<?php _e('Check for Updates Now', 'operaton-dmn'); ?>');
            });
        });

        // Clear All Cache functionality
        $('#clear-all-cache').on('click', function() {
            console.log('Clear All Configuration Cache button clicked, starting AJAX request');
            var button = $(this);

            // Confirm action
            if (!confirm('Clear all cached configurations and decision flows?\n\nThis will:\n• Clear all WordPress transients\n• Clear object cache\n• Force reload all configurations\n\nThis action is safe and recommended when configurations aren\'t updating.')) {
                return;
            }

            button.prop('disabled', true).text('Clearing Cache...');
            showCacheOperationFeedback('<div style="color: #666; padding: 8px 12px; background: #f1f1f1; border-radius: 4px;">⏳ Clearing all configuration cache...</div>', false);

            $.post(ajaxurl, {
                action: 'operaton_clear_all_cache',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    console.log('AJAX response:', response);
                    showCacheOperationFeedback('<div style="color: #155724; padding: 8px 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">' +
                        '✅ <strong>All cache cleared successfully!</strong><br>' +
                        '<small>Cleared ' + (response.data.transients_cleared || 0) + ' transients and ' +
                        (response.data.configs_reloaded || 0) + ' configurations reloaded.</small></div>');
                } else {
                    showCacheOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                        '❌ <strong>Cache clear failed:</strong> ' + (response.data ? response.data.message : 'Unknown error') + '</div>', false);
                }
            }).fail(function(xhr, status, error) {
                showCacheOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    '❌ <strong>Cache clear failed:</strong> Connection error (' + status + ')</div>', false);
            }).always(function() {
                button.prop('disabled', false).text('Clear All Configuration Cache');
            });
        });

        // Force Reload Configurations functionality
        $('#force-reload-configs').on('click', function() {
            console.log('Force Reload Configurations button clicked, starting AJAX request');
            var button = $(this);

            button.prop('disabled', true).text('Reloading...');
            showCacheOperationFeedback('<div style="color: #666; padding: 8px 12px; background: #f1f1f1; border-radius: 4px;">⏳ Force reloading configurations from database...</div>', false);

            $.post(ajaxurl, {
                action: 'operaton_force_reload_configs',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    console.log('AJAX response:', response);
                    showCacheOperationFeedback('<div style="color: #155724; padding: 8px 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">' +
                        '✅ <strong>Configurations reloaded successfully!</strong><br>' +
                        '<small>Reloaded ' + (response.data.configs_reloaded || 0) + ' configurations from database.</small></div>');
                } else {
                    showCacheOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                        '❌ <strong>Reload failed:</strong> ' + (response.data ? response.data.message : 'Unknown error') + '</div>', false);
                }
            }).fail(function(xhr, status, error) {
                showCacheOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    '❌ <strong>Reload failed:</strong> Connection error (' + status + ')</div>', false);
            }).always(function() {
                button.prop('disabled', false).text('Force Reload Configurations');
            });
        });
    });
</script>

<style>
    /* Additional inline styles for this specific page */
    .operaton-config-table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .operaton-config-actions .button {
        margin-bottom: 3px;
    }

    .operaton-config-status.active {
        background: #d1ecf1;
        color: #0c5460;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .operaton-config-status.inactive {
        background: #f8d7da;
        color: #721c24;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    @media (max-width: 782px) {

        .operaton-config-table th,
        .operaton-config-table td {
            padding: 8px 6px;
            font-size: 12px;
        }

        .operaton-config-endpoint {
            max-width: 100px;
        }

        .operaton-config-actions .button {
            padding: 2px 6px;
            font-size: 11px;
        }
    }
</style>
