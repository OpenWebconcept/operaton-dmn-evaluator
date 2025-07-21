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
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="operaton-dmn-admin-wrap">
    <div class="operaton-dmn-header">
        <h1><?php _e('Operaton DMN Configurations', 'operaton-dmn'); ?></h1>
        <p><?php _e('Manage your DMN decision table configurations for Gravity Forms integration.', 'operaton-dmn'); ?></p>
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
        
        if (isset($update_plugins->response)) {
            foreach ($update_plugins->response as $plugin => $data) {
                if (strpos($plugin, 'operaton-dmn') !== false) {
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
                            if (class_exists('GFAPI')) {
                                $form = GFAPI::get_form($config->form_id);
                                if ($form) {
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
                            if (strlen($endpoint) > 50) {
                                echo substr($endpoint, 0, 47) . '...';
                            } else {
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
                            
                            if (isset($config->use_process) && $config->use_process) {
                                $is_complete = $is_complete && !empty($config->process_key);
                            } else {
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
                    count(array_filter($configs, function($config) {
                        $is_complete = !empty($config->dmn_endpoint) && 
                                      !empty($config->field_mappings) && 
                                      !empty($config->result_mappings);
                        if (isset($config->use_process) && $config->use_process) {
                            return $is_complete && !empty($config->process_key);
                        } else {
                            return $is_complete && !empty($config->decision_key);
                        }
                    })),
                    count(array_filter($configs, function($config) {
                        $is_complete = !empty($config->dmn_endpoint) && 
                                      !empty($config->field_mappings) && 
                                      !empty($config->result_mappings);
                        if (isset($config->use_process) && $config->use_process) {
                            return !($is_complete && !empty($config->process_key));
                        } else {
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
jQuery(document).ready(function($) {
    // Update check functionality
    $('#operaton-check-updates').click(function() {
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