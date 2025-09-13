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

// Add to your admin page temporarily
if (defined('WP_DEBUG') && WP_DEBUG)
{
    global $wp_filter;
    error_log('AJAX handlers: ' . print_r($wp_filter['wp_ajax_operaton_test_configuration_complete'], true));
}
?>

<!-- Add New Configuration Button - Now at the top -->
<div style="margin: 20px 0;">
    <a href="<?php echo admin_url('admin.php?page=operaton-dmn-add'); ?>" class="button button-primary button-hero">
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
    <!-- Configuration Stats Bar -->
    <div class="operaton-stats-bar" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #0073aa;">
        <div>
            <?php
            $total_configs = count($configs);
            $active_configs = count(array_filter($configs, function ($config)
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
            }));
            $incomplete_configs = $total_configs - $active_configs;
            ?>
            <strong><?php _e('Configuration Summary:', 'operaton-dmn'); ?></strong>
            <?php printf(__('Total: %d | Active: %d | Incomplete: %d', 'operaton-dmn'), $total_configs, $active_configs, $incomplete_configs); ?>
        </div>

        <?php if ($total_configs > 10): ?>
            <div class="operaton-search-filters">
                <input type="text" id="config-search" placeholder="<?php _e('Search configurations...', 'operaton-dmn'); ?>" style="margin-right: 10px; padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px;">
                <select id="status-filter" style="padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value=""><?php _e('All Statuses', 'operaton-dmn'); ?></option>
                    <option value="active"><?php _e('Active Only', 'operaton-dmn'); ?></option>
                    <option value="incomplete"><?php _e('Incomplete Only', 'operaton-dmn'); ?></option>
                </select>
                <select id="mode-filter" style="margin-left: 10px; padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value=""><?php _e('All Modes', 'operaton-dmn'); ?></option>
                    <option value="process"><?php _e('Process Mode', 'operaton-dmn'); ?></option>
                    <option value="direct"><?php _e('Direct Mode', 'operaton-dmn'); ?></option>
                </select>
            </div>
        <?php endif; ?>
    </div>

    <div class="operaton-config-form-wrap">
        <table class="operaton-config-table" id="configurations-table">
            <thead>
                <tr>
                    <th class="sortable" data-sort="name"><?php _e('Name', 'operaton-dmn'); ?> <span class="sort-indicator"></span></th>
                    <th class="sortable" data-sort="form"><?php _e('Form', 'operaton-dmn'); ?> <span class="sort-indicator"></span></th>
                    <th class="sortable" data-sort="mode"><?php _e('Mode', 'operaton-dmn'); ?> <span class="sort-indicator"></span></th>
                    <th><?php _e('Endpoint', 'operaton-dmn'); ?></th>
                    <th><?php _e('Key', 'operaton-dmn'); ?></th>
                    <th class="sortable" data-sort="status"><?php _e('Status', 'operaton-dmn'); ?> <span class="sort-indicator"></span></th>
                    <th><?php _e('Actions', 'operaton-dmn'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($configs as $config): ?>
                    <tr data-config-id="<?php echo $config->id; ?>"
                        data-name="<?php echo esc_attr(strtolower($config->name)); ?>"
                        data-mode="<?php echo (isset($config->use_process) && $config->use_process) ? 'process' : 'direct'; ?>"
                        data-status="<?php
                                        $is_complete = !empty($config->dmn_endpoint) &&
                                            !empty($config->field_mappings) &&
                                            !empty($config->result_mappings);
                                        if (isset($config->use_process) && $config->use_process)
                                        {
                                            echo ($is_complete && !empty($config->process_key)) ? 'active' : 'incomplete';
                                        }
                                        else
                                        {
                                            echo ($is_complete && !empty($config->decision_key)) ? 'active' : 'incomplete';
                                        }
                                        ?>">
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
                                echo '<span title="' . $endpoint . '">' . substr($endpoint, 0, 47) . '...</span>';
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
                                    onclick="testConfig(<?php echo $config->id; ?>, '<?php echo esc_js($config->name); ?>')"
                                    title="<?php _e('Test Configuration', 'operaton-dmn'); ?>">
                                    <?php _e('Test', 'operaton-dmn'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_configs > 20): ?>
            <div class="operaton-pagination" style="margin-top: 20px; text-align: center;">
                <div id="pagination-info" style="margin-bottom: 10px; color: #666; font-size: 14px;">
                    <?php printf(__('Showing %d configurations', 'operaton-dmn'), $total_configs); ?>
                </div>
                <div id="pagination-controls">
                    <!-- Pagination will be added via JavaScript if needed -->
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Enhanced Decision Flow Cache Management Section -->
<div class="operaton-update-section">
    <h3>Configuration & Cache Management</h3>
    <p>Manage cached decision flow data and configuration settings. Clear cache when you update DMN endpoints or experience configuration issues.</p>

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

        <button type="button" id="check-connection-stats" class="button button-secondary"
            title="Check HTTP connection reuse efficiency for API calls">
            Check Connection Efficiency
        </button>
    </div>

    <!-- Connection Pool Settings -->
    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #0073aa;">
        <h4 style="margin: 0 0 10px 0; color: #0073aa;">Connection Pool Settings</h4>
        <p style="margin: 0 0 15px 0; font-size: 14px; color: #666;">Configure how long connections are kept alive for reuse optimization.</p>

        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <label for="connection-timeout" style="font-weight: 600; color: #333;">Connection Timeout:</label>
                <select id="connection-timeout" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #ddd;">
                    <?php
                    $current_timeout = get_option('operaton_connection_timeout', 300);
                    $timeout_options = array(
                        300 => '5 minutes (Default)',
                        600 => '10 minutes (Recommended)',
                        900 => '15 minutes (High efficiency)',
                        1200 => '20 minutes (Maximum)',
                        1800 => '30 minutes (Development only)'
                    );

                    foreach ($timeout_options as $seconds => $label)
                    {
                        $selected = ($current_timeout == $seconds) ? 'selected' : '';
                        echo '<option value="' . $seconds . '" ' . $selected . '>' . esc_html($label) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <button type="button" id="save-connection-timeout" class="button button-primary" style="padding: 6px 12px;">
                Save Settings
            </button>

            <div id="timeout-save-result" style="margin-left: 10px;"></div>
        </div>

        <details style="margin-top: 10px;">
            <summary style="cursor: pointer; font-weight: 600; color: #0073aa; font-size: 13px;">Connection Timeout Help</summary>
            <div style="padding: 8px 0; font-size: 13px; color: #666; line-height: 1.4;">
                <ul style="margin: 5px 0 5px 20px;">
                    <li><strong>5 minutes:</strong> Best for high-traffic sites with frequent evaluations</li>
                    <li><strong>10 minutes:</strong> Recommended for most production sites</li>
                    <li><strong>15-20 minutes:</strong> Maximum efficiency for lower-traffic sites</li>
                    <li><strong>30 minutes:</strong> Development/testing only (may cause memory issues)</li>
                </ul>
                <p style="margin: 8px 0 0 0;"><strong>Note:</strong> Longer timeouts increase connection reuse but use more memory. Choose based on your evaluation frequency.</p>
            </div>
        </details>
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

<!-- Debug Tools Section -->
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

<!-- Confirmation Dialog and JavaScript -->
<div id="delete-confirmation" style="display: none;">
    <p><?php _e('Are you sure you want to delete this configuration?', 'operaton-dmn'); ?></p>
    <p><strong id="config-name-display"></strong></p>
    <p style="color: #d63638;"><em><?php _e('This action cannot be undone.', 'operaton-dmn'); ?></em></p>
</div>

<script>
    // Global functions that need to be called from HTML
    function deleteConfig(configId, configName) {
        if (confirm('<?php _e('Are you sure you want to delete the configuration', 'operaton-dmn'); ?> "' + configName + '"?\n\n<?php _e('This action cannot be undone.', 'operaton-dmn'); ?>')) {
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
    function testConfig(configId, configName) {
        // Check if the testing module is available
        if (typeof window.OperatonDMNTesting !== 'undefined') {
            window.OperatonDMNTesting.testConfig(configId, configName || 'Configuration #' + configId);
        } else {
            // Fallback for when api-test.js hasn't loaded yet
            console.warn('Operaton DMN Testing module not loaded yet, retrying...');

            // Try again after a short delay
            setTimeout(function() {
                if (typeof window.OperatonDMNTesting !== 'undefined') {
                    window.OperatonDMNTesting.testConfig(configId, configName || 'Configuration #' + configId);
                } else {
                    alert('Testing functionality is not available. Please refresh the page and try again.');
                }
            }, 500);
        }
    }

    // Global variables to manage feedback timers
    var cacheOperationTimer = null;
    var debugOperationTimer = null;

    function showFullDebugData() {
        if (window.operatonDebugData) {
            var existingFullData = document.getElementById('full-debug-data');
            if (existingFullData) {
                existingFullData.remove();
                return;
            }

            var parentContainer = jQuery('#debug-operation-result').parent();
            var fullDataHtml = '<div id="full-debug-data" style="margin-top: 10px; max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 11px; white-space: pre-wrap; border: 1px solid #ddd;">' +
                '<div style="margin-bottom: 10px; font-family: sans-serif; font-weight: bold; color: #0073aa;">Complete Debug Information:</div>' +
                JSON.stringify(window.operatonDebugData, null, 2) +
                '</div>';

            parentContainer.append(fullDataHtml);

            var button = jQuery('#debug-operation-result').find('button');
            if (button.length) {
                button.text('Hide Full Details');
            }
        }
    }

    function showFullDmnDebugData() {
        if (window.operatonDmnDebugData) {
            var existingFullData = document.getElementById('full-dmn-debug-data');
            if (existingFullData) {
                existingFullData.remove();
                return;
            }

            var parentContainer = jQuery('#debug-operation-result').parent();
            var fullDataHtml = '<div id="full-dmn-debug-data" style="margin-top: 10px; max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 11px; white-space: pre-wrap; border: 1px solid #ddd;">' +
                '<div style="margin-bottom: 10px; font-family: sans-serif; font-weight: bold; color: #0073aa;">Complete DMN Debug Test Results:</div>' +
                JSON.stringify(window.operatonDmnDebugData, null, 2) +
                '</div>';

            parentContainer.append(fullDataHtml);

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
        function showCacheOperationFeedback(html, fadeOut = true) {
            var result = $('#cache-operation-result');
            if (cacheOperationTimer) {
                clearTimeout(cacheOperationTimer);
                cacheOperationTimer = null;
            }
            result.stop(true, true).show().html(html);
            if (fadeOut) {
                cacheOperationTimer = setTimeout(function() {
                    result.fadeOut(500);
                    cacheOperationTimer = null;
                }, 3000);
            }
        }

        function showDebugOperationFeedback(html, fadeOut = true) {
            var result = $('#debug-operation-result');
            if (debugOperationTimer) {
                clearTimeout(debugOperationTimer);
                debugOperationTimer = null;
            }
            result.stop(true, true).show().html(html);
            if (fadeOut) {
                debugOperationTimer = setTimeout(function() {
                    result.fadeOut(500);
                    debugOperationTimer = null;
                }, 5000);
            }
        }

        function filterConfigurations() {
            var searchTerm = $('#config-search').val().toLowerCase();
            var statusFilter = $('#status-filter').val();
            var modeFilter = $('#mode-filter').val();
            var visibleCount = 0;

            $('#configurations-table tbody tr').each(function() {
                var $row = $(this);
                var name = $row.data('name');
                var status = $row.data('status');
                var mode = $row.data('mode');

                var showRow = true;

                if (searchTerm && name.indexOf(searchTerm) === -1) {
                    showRow = false;
                }

                if (statusFilter && status !== statusFilter) {
                    showRow = false;
                }

                if (modeFilter && mode !== modeFilter) {
                    showRow = false;
                }

                if (showRow) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });

            $('#pagination-info').text('Showing ' + visibleCount + ' configurations');
        }

        $('.sortable').on('click', function() {
            var $this = $(this);
            var sortBy = $this.data('sort');
            var $tbody = $('#configurations-table tbody');
            var $rows = $tbody.find('tr').toArray();

            var isAsc = $this.hasClass('sort-asc');

            $('.sortable').removeClass('sort-asc sort-desc');
            $this.addClass(isAsc ? 'sort-desc' : 'sort-asc');

            $rows.sort(function(a, b) {
                var aVal, bVal;

                switch (sortBy) {
                    case 'name':
                        aVal = $(a).find('.operaton-config-name').text().toLowerCase();
                        bVal = $(b).find('.operaton-config-name').text().toLowerCase();
                        break;
                    case 'form':
                        aVal = $(a).find('.operaton-config-form').text().toLowerCase();
                        bVal = $(b).find('.operaton-config-form').text().toLowerCase();
                        break;
                    case 'mode':
                        aVal = $(a).data('mode');
                        bVal = $(b).data('mode');
                        break;
                    case 'status':
                        aVal = $(a).data('status');
                        bVal = $(b).data('status');
                        break;
                    default:
                        return 0;
                }

                if (aVal < bVal) return isAsc ? 1 : -1;
                if (aVal > bVal) return isAsc ? -1 : 1;
                return 0;
            });

            $.each($rows, function(index, row) {
                $tbody.append(row);
            });
        });

        $('#config-search, #status-filter, #mode-filter').on('input change', function() {
            filterConfigurations();
        });

        $('#get-plugin-status').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('<?php _e('Getting Status...', 'operaton-dmn'); ?>');
            showDebugOperationFeedback('<div style="color: #666; padding: 8px 12px; background: #f1f1f1; border-radius: 4px;">⏳ <?php _e('Retrieving plugin status information...', 'operaton-dmn'); ?></div>', false);

            $.post(ajaxurl, {
                action: 'operaton_debug_status',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success && response.data) {
                    var statusHtml = '<div style="color: #155724; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">' +
                        '<h4 style="margin: 0 0 10px 0;">✅ <?php _e('Plugin Status Information', 'operaton-dmn'); ?></h4>';

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
                    window.operatonDebugData = response.data;

                } else {
                    showDebugOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                        '⚠ <strong><?php _e('Status retrieval failed:', 'operaton-dmn'); ?></strong> ' + (response.data ? response.data.message : '<?php _e('Unknown error', 'operaton-dmn'); ?>') + '</div>', false);
                }
            }).fail(function(xhr, status, error) {
                showDebugOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    '⚠ <strong><?php _e('Status retrieval failed:', 'operaton-dmn'); ?></strong> <?php _e('Connection error', 'operaton-dmn'); ?> (' + status + ')</div>', false);
            }).always(function() {
                button.prop('disabled', false).text('<?php _e('Get Plugin Status', 'operaton-dmn'); ?>');
            });
        });

        $('#run-dmn-debug').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('<?php _e('Running Tests...', 'operaton-dmn'); ?>');
            showDebugOperationFeedback('<div style="color: #666; padding: 8px 12px; background: #f1f1f1; border-radius: 4px;">⏳ <?php _e('Running comprehensive DMN debug tests...', 'operaton-dmn'); ?></div>', false);

            $.post(ajaxurl, {
                action: 'operaton_dmn_debug',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    var debugHtml = '<div style="color: #155724; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">' +
                        '<h4 style="margin: 0 0 10px 0;">✅ <?php _e('DMN Debug Tests Completed', 'operaton-dmn'); ?></h4>';

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

                    debugHtml += '<div style="margin-top: 10px;"><button onclick="showFullDmnDebugData()" class="button button-small"><?php _e('Show Full Test Results', 'operaton-dmn'); ?></button></div>';
                    debugHtml += '</div>';

                    showDebugOperationFeedback(debugHtml);
                    window.operatonDmnDebugData = response.data;

                } else {
                    showDebugOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                        '⚠ <strong><?php _e('Debug tests failed:', 'operaton-dmn'); ?></strong> ' + (response.data ? response.data.message : '<?php _e('Unknown error', 'operaton-dmn'); ?>') + '</div>', false);
                }
            }).fail(function(xhr, status, error) {
                showDebugOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    '⚠ <strong><?php _e('Debug tests failed:', 'operaton-dmn'); ?></strong> <?php _e('Connection error', 'operaton-dmn'); ?> (' + status + ')</div>', false);
            }).always(function() {
                button.prop('disabled', false).text('<?php _e('Run DMN Debug Tests', 'operaton-dmn'); ?>');
            });
        });

        $('#check-connection-stats').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('Checking...');
            showCacheOperationFeedback('<div style="color: #666; padding: 8px 12px; background: #f1f1f1; border-radius: 4px;">⏳ Analyzing connection pool efficiency...</div>', false);

            $.post(ajaxurl, {
                action: 'operaton_check_connection_stats',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success && response.data.stats) {
                    var stats = response.data.stats;
                    var statusHtml = '<div style="color: #155724; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">' +
                        '<h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">' +
                        '<span style="color: ' + stats.efficiency_color + ';">📊</span> Connection Efficiency Report</h4>';

                    statusHtml += '<div style="margin-bottom: 12px; padding: 8px; background: rgba(255,255,255,0.7); border-radius: 4px;">' +
                        '<div style="font-size: 16px; font-weight: 600; color: ' + stats.efficiency_color + ';">' +
                        stats.summary + '</div></div>';

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
                showCacheOperationFeedback('<div style="color: #721c24; padding: 8px 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    '⚠️ <strong>Connection stats check failed:</strong> Connection error (' + status + ')</div>', false);
            }).always(function() {
                button.prop('disabled', false).text('Check Connection Efficiency');
            });
        });

        $('#save-connection-timeout').on('click', function() {
            var button = $(this);
            var timeout = $('#connection-timeout').val();
            var resultDiv = $('#timeout-save-result');

            if (!timeout) {
                resultDiv.html('<span style="color: #dc3545;">Please select a timeout value</span>');
                return;
            }

            button.prop('disabled', true).text('Saving...');
            resultDiv.html('<span style="color: #666;">Saving setting...</span>');

            $.post(ajaxurl, {
                action: 'operaton_save_connection_timeout',
                timeout: timeout,
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    resultDiv.html('<span style="color: #28a745; font-weight: 600;">✓ ' + response.data.message + '</span>');
                    setTimeout(function() {
                        resultDiv.fadeOut(300, function() {
                            $(this).html('').show();
                        });
                    }, 4000);
                } else {
                    resultDiv.html('<span style="color: #dc3545;">✗ ' + (response.data ? response.data.message : 'Save failed') + '</span>');
                }
            }).fail(function(xhr, status, error) {
                resultDiv.html('<span style="color: #dc3545;">✗ Connection error: ' + status + '</span>');
            }).always(function() {
                button.prop('disabled', false).text('Save Settings');
            });
        });

        $('#clear-decision-cache').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('Clearing Cache...');
            showCacheOperationFeedback('<div style="color: #666; padding: 8px 12px; background: #f1f1f1; border-radius: 4px;">⏳ Clearing decision flow cache...</div>', false);

            $.post(ajaxurl, {
                action: 'operaton_clear_decision_cache',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
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

        $('#clear-all-cache').on('click', function() {
            var button = $(this);
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

        $('#force-reload-configs').on('click', function() {
            var button = $(this);
            button.prop('disabled', true).text('Reloading...');
            showCacheOperationFeedback('<div style="color: #666; padding: 8px 12px; background: #f1f1f1; border-radius: 4px;">⏳ Force reloading configurations from database...</div>', false);

            $.post(ajaxurl, {
                action: 'operaton_force_reload_configs',
                _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
            }, function(response) {
                if (response.success) {
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
    });
</script>

<style>
    /* Enhanced styles for the reorganized interface */
    .operaton-stats-bar {
        font-size: 14px;
    }

    .operaton-search-filters {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .operaton-config-table th.sortable {
        cursor: pointer;
        position: relative;
        user-select: none;
    }

    .operaton-config-table th.sortable:hover {
        background-color: #f1f1f1;
    }

    .sort-indicator {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0.5;
        font-size: 12px;
    }

    .sort-indicator::after {
        content: '↕';
    }

    .sort-asc .sort-indicator::after {
        content: '↑';
        opacity: 1;
    }

    .sort-desc .sort-indicator::after {
        content: '↓';
        opacity: 1;
    }

    /* Improved button styling */
    .button-hero {
        font-size: 16px;
        padding: 12px 24px;
        height: auto;
        line-height: 1.2;
    }

    /* Better visual hierarchy */
    .operaton-update-section {
        border: 1px solid #e1e1e1;
        border-radius: 6px;
        padding: 20px;
        margin-bottom: 25px;
        background: #fff;
    }

    .operaton-update-section h3 {
        margin-top: 0;
        color: #1d2327;
        border-bottom: 2px solid #0073aa;
        padding-bottom: 8px;
        margin-bottom: 15px;
    }

    /* Configuration status badges */
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

    /* Table improvements */
    .operaton-config-table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .operaton-config-actions .button {
        margin-bottom: 3px;
    }

    /* Responsive improvements */
    @media (max-width: 1200px) {
        .operaton-search-filters {
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
        }

        .operaton-search-filters input,
        .operaton-search-filters select {
            width: 100%;
        }
    }

    @media (max-width: 782px) {
        .operaton-config-table {
            font-size: 12px;
        }

        .operaton-config-table th,
        .operaton-config-table td {
            padding: 6px 4px;
        }

        .operaton-search-filters input {
            font-size: 16px;
            /* Prevents zoom on iOS */
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
