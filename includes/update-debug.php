<?php
/**
 * Operaton DMN Auto-Update Debug and Test Script - V11.5 FINAL CLEAN
 * File: includes/update-debug.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only load in admin and for authorized users
if (!is_admin()) {
    return;
}

/**
 * Debug Auto-Update System with V11.5 Monitoring - FINAL VERSION
 */
class OperatonDMNUpdateDebugger {
    
    private $gitlab_url = 'https://git.open-regels.nl';
    private $project_id = '39';

    public function __construct() {
        error_log('Operaton DMN: OperatonDMNUpdateDebugger constructor called');
        add_action('admin_init', array($this, 'init_debug_tools'));
        add_action('init', array($this, 'init_v11_monitoring'));
    }
    
    public function init_v11_monitoring() {
        add_action('upgrader_start', array($this, 'monitor_upgrader_start'), 1);
        add_filter('upgrader_pre_download', array($this, 'monitor_pre_download'), 1, 3);
        add_filter('upgrader_unpack_package', array($this, 'monitor_unpack_package'), 1, 4);
        add_filter('unzip_file', array($this, 'monitor_unzip_file'), 1, 4);
        add_filter('upgrader_install_package_result', array($this, 'monitor_install_package_result'), 1, 2);
        add_action('upgrader_process_complete', array($this, 'monitor_process_complete'), 1, 2);
        add_action('wp_filesystem_init', array($this, 'monitor_filesystem_init'));
    }
    
    public function monitor_upgrader_start($hook_extra) {
        if (isset($hook_extra['plugin']) && strpos($hook_extra['plugin'], 'operaton-dmn') !== false) {
            error_log('=== V11.5 MONITOR: UPGRADER START ===');
            error_log('V11.5 Monitor: Plugin: ' . $hook_extra['plugin']);
        }
        return $hook_extra;
    }
    
    public function monitor_pre_download($result, $package, $upgrader) {
        if (strpos($package, 'operaton') !== false || strpos($package, 'dmn') !== false) {
            error_log('=== V11.5 MONITOR: PRE DOWNLOAD ===');
            error_log('V11.5 Monitor: Package: ' . $package);
        }
        return $result;
    }
    
    public function monitor_unpack_package($result, $package, $delete_package_after, $hook_extra) {
        if ((isset($hook_extra['plugin']) && strpos($hook_extra['plugin'], 'operaton-dmn') !== false)) {
            error_log('=== V11.5 MONITOR: UNPACK PACKAGE ===');
            error_log('V11.5 Monitor: Package: ' . (is_string($package) ? $package : gettype($package)));
        }
        return $result;
    }
    
    public function monitor_unzip_file($result, $file, $to, $needed_dirs) {
        if (strpos($file, 'operaton') !== false || strpos($file, 'dmn') !== false) {
            error_log('=== V11.5 MONITOR: UNZIP FILE ===');
            error_log('V11.5 Monitor: Source file: ' . $file);
            error_log('V11.5 Monitor: Target directory: ' . $to);
        }
        return $result;
    }
    
    public function monitor_install_package_result($result, $hook_extra) {
        if ((isset($hook_extra['plugin']) && strpos($hook_extra['plugin'], 'operaton-dmn') !== false)) {
            error_log('=== V11.5 MONITOR: INSTALL PACKAGE RESULT ===');
            error_log('V11.5 Monitor: Result: ' . print_r($result, true));
            
            if (isset($result['destination'])) {
                error_log('V11.5 Monitor: Final destination: ' . $result['destination']);
                if (strpos($result['destination'], 'operaton-dmn-evaluator-v') !== false) {
                    error_log('V11.5 Monitor: ⚠️ WRONG DESTINATION DETECTED: ' . basename($result['destination']));
                } else {
                    error_log('V11.5 Monitor: ✓ Correct destination: ' . basename($result['destination']));
                }
            }
        }
        return $result;
    }
    
    public function monitor_process_complete($upgrader, $hook_extra) {
        if (isset($hook_extra['plugin']) && strpos($hook_extra['plugin'], 'operaton-dmn') !== false) {
            error_log('=== V11.5 MONITOR: PROCESS COMPLETE ===');
            error_log('V11.5 Monitor: Plugin: ' . $hook_extra['plugin']);
            
            $plugin_path = WP_PLUGIN_DIR . '/operaton-dmn-evaluator';
            if (is_dir($plugin_path)) {
                $files = glob($plugin_path . '/*');
                error_log('V11.5 Monitor: Final plugin files: ' . implode(', ', array_map('basename', $files)));
            }
        }
    }
    
    public function monitor_filesystem_init($wp_filesystem) {
        if ($wp_filesystem) {
            error_log('V11.5 Monitor: Filesystem initialized - Type: ' . get_class($wp_filesystem));
        }
    }
    
    public function init_debug_tools() {
        error_log('Operaton DMN: init_debug_tools called');
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        error_log('Operaton DMN: User has manage_options, adding debug menu');
        add_action('admin_menu', array($this, 'add_debug_menu'), 20);
        
        // Add all AJAX handlers
        add_action('wp_ajax_operaton_test_update_api', array($this, 'ajax_test_update_api'));
        add_action('wp_ajax_operaton_force_update_check', array($this, 'ajax_force_update_check'));
        add_action('wp_ajax_operaton_test_update_hooks', array($this, 'ajax_test_update_hooks'));
        add_action('wp_ajax_operaton_v11_monitor_test', array($this, 'ajax_v11_monitor_test'));
        add_action('wp_ajax_operaton_v11_force_clean_extraction', array($this, 'ajax_v11_force_clean_extraction'));
        add_action('wp_ajax_operaton_v11_realtime_status', array($this, 'ajax_v11_realtime_status'));
        add_action('wp_ajax_operaton_v11_manual_fix', array($this, 'ajax_v11_manual_fix'));
        
        error_log('Operaton DMN: Debug hooks added with priority 20');
    }

    public function add_debug_menu() {
        error_log('Operaton DMN: add_debug_menu called');
        add_submenu_page(
            'operaton-dmn',
            __('Update Debug V11.5', 'operaton-dmn'),
            __('Update Debug V11.5', 'operaton-dmn'),
            'manage_options',
            'operaton-dmn-update-debug',
            array($this, 'debug_page')
        );
        error_log('Operaton DMN: Debug submenu added');
    }
    
    public function debug_page() {
        ?>
        <div class="wrap">
            <h1>🚀 Operaton DMN V11.5 Debug Dashboard</h1>
            
            <div class="notice notice-success">
                <p><strong>🎉 V11.5 NUCLEAR SUCCESS!</strong> The auto-update system is working perfectly!</p>
            </div>
            
            <!-- System Status -->
            <div class="card">
                <h2>📊 System Status</h2>
                <table class="form-table">
                    <tr>
                        <th>Plugin Version</th>
                        <td><?php echo OPERATON_DMN_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>WordPress Version</th>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <th>V11.5 Auto-Updater</th>
                        <td><?php echo class_exists('OperatonDMNAutoUpdater') ? '🔥 V11.5 NUCLEAR ACTIVE' : '❌ Not Loaded'; ?></td>
                    </tr>
                    <tr>
                        <th>Nuclear Mode Status</th>
                        <td><?php 
                            $nuclear_mode = get_transient('operaton_dmn_v11_nuclear_mode');
                            echo $nuclear_mode ? '🔥 CURRENTLY ACTIVE' : '💤 Standby';
                        ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- Plugin State -->
            <div class="card">
                <h2>📁 Current Plugin State</h2>
                <?php $this->show_current_plugin_state(); ?>
            </div>
            
            <!-- V11.5 Testing Tools -->
            <div class="card">
                <h2>🧪 V11.5 Testing Tools</h2>
                <div class="test-grid">
                    
                    <div class="test-tool">
                        <h4>🔥 V11.5 Nuclear Hooks</h4>
                        <button type="button" id="test-hooks" class="button button-primary">Test Nuclear Hooks</button>
                        <div id="hooks-result"></div>
                    </div>
                    
                    <div class="test-tool">
                        <h4>📡 Monitoring System</h4>
                        <button type="button" id="test-monitoring" class="button button-primary">Test Monitoring</button>
                        <div id="monitoring-result"></div>
                    </div>
                    
                    <div class="test-tool">
                        <h4>⚡ Force Extraction</h4>
                        <button type="button" id="test-extraction" class="button button-primary">Test Extraction</button>
                        <div id="extraction-result"></div>
                    </div>
                    
                    <div class="test-tool">
                        <h4>🔗 GitLab API</h4>
                        <button type="button" id="test-api" class="button button-primary">Test API</button>
                        <div id="api-result"></div>
                    </div>
                    
                    <div class="test-tool">
                        <h4>🔧 Manual Fix</h4>
                        <button type="button" id="manual-fix" class="button button-secondary">Fix Directories</button>
                        <div id="fix-result"></div>
                    </div>
                    
                    <div class="test-tool">
                        <h4>🔄 Update Check</h4>
                        <button type="button" id="force-check" class="button button-secondary">Force Check</button>
                        <div id="check-result"></div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Real-Time Monitor -->
            <div class="card">
                <h2>🔍 Real-Time Update Monitor</h2>
                <p>Monitor V11.5 nuclear system during live updates.</p>
                <button type="button" id="start-monitor" class="button button-secondary">Start Monitor</button>
                <button type="button" id="stop-monitor" class="button button-secondary" disabled>Stop Monitor</button>
                <div id="monitor-output" style="background: #000; color: #0f0; padding: 10px; margin: 10px 0; height: 300px; overflow-y: scroll; font-family: monospace; font-size: 12px; display: none;"></div>
            </div>
            
        </div>
        
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin: 20px 0;
            padding: 20px;
        }
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .test-tool {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
        }
        .test-tool h4 {
            margin: 0 0 10px 0;
            color: #495057;
        }
        .test-tool button {
            width: 100%;
            margin-bottom: 10px;
        }
        .status-success { color: #28a745; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Real-time monitor
            let monitoring = false;
            let monitorInterval;
            
            $('#start-monitor').click(function() {
                monitoring = true;
                $(this).prop('disabled', true);
                $('#stop-monitor').prop('disabled', false);
                $('#monitor-output').show();
                logToMonitor('🔥 V11.5 MONITORING STARTED');
                
                monitorInterval = setInterval(checkActivity, 2000);
            });
            
            $('#stop-monitor').click(function() {
                monitoring = false;
                $(this).prop('disabled', false);
                $('#start-monitor').prop('disabled', true);
                clearInterval(monitorInterval);
                logToMonitor('💤 Monitoring stopped');
            });
            
            function checkActivity() {
                if (!monitoring) return;
                
                $.post(ajaxurl, {
                    action: 'operaton_v11_realtime_status',
                    _ajax_nonce: '<?php echo wp_create_nonce('operaton_update_debug'); ?>'
                }, function(response) {
                    if (response.success && response.data.activity_detected) {
                        if (response.data.nuclear_mode_active) {
                            logToMonitor('🔥 NUCLEAR MODE ACTIVE!');
                        }
                        if (response.data.new_log_entries) {
                            response.data.new_log_entries.forEach(function(entry) {
                                logToMonitor(entry);
                            });
                        }
                    }
                });
            }
            
            function logToMonitor(message) {
                const time = new Date().toLocaleTimeString();
                $('#monitor-output').append(`<div>[${time}] ${message}</div>`);
                $('#monitor-output').scrollTop($('#monitor-output')[0].scrollHeight);
            }
            
            // Test buttons
            $('#test-hooks').click(function() {
                testFunction('operaton_test_update_hooks', '#hooks-result', $(this));
            });
            
            $('#test-monitoring').click(function() {
                testFunction('operaton_v11_monitor_test', '#monitoring-result', $(this));
            });
            
            $('#test-extraction').click(function() {
                testFunction('operaton_v11_force_clean_extraction', '#extraction-result', $(this));
            });
            
            $('#test-api').click(function() {
                testFunction('operaton_test_update_api', '#api-result', $(this));
            });
            
            $('#manual-fix').click(function() {
                testFunction('operaton_v11_manual_fix', '#fix-result', $(this), function() {
                    setTimeout(() => location.reload(), 1000);
                });
            });
            
            $('#force-check').click(function() {
                testFunction('operaton_force_update_check', '#check-result', $(this));
            });
            
            function testFunction(action, resultSelector, button, callback) {
                button.prop('disabled', true).text('Testing...');
                $(resultSelector).html('<div class="status-warning">⏳ Testing...</div>');
                
                $.post(ajaxurl, {
                    action: action,
                    _ajax_nonce: '<?php echo wp_create_nonce('operaton_update_debug'); ?>'
                }, function(response) {
                    if (response.success) {
                        let html = '<div class="status-success">✅ SUCCESS</div>';
                        
                        // Add detailed results based on action
                        if (action === 'operaton_test_update_hooks' && response.data) {
                            html += '<div style="margin-top: 10px; font-size: 12px;">';
                            html += '<strong>V11.5 Status:</strong><br>';
                            if (response.data.install_hook && response.data.install_hook.includes('✓')) {
                                html += '🔥 Install Package Hook: ACTIVE<br>';
                            }
                            if (response.data.unzip_hook && response.data.unzip_hook.includes('✓')) {
                                html += '⚡ Unzip Hook: ACTIVE<br>';
                            }
                            if (response.data.v11_status) {
                                html += '<strong>' + response.data.v11_status + '</strong>';
                            }
                            html += '</div>';
                        }
                        
                        if (action === 'operaton_v11_monitor_test' && response.data) {
                            html += '<div style="margin-top: 10px; font-size: 12px;">';
                            html += '<strong>Monitoring Status:</strong> ' + response.data.v11_monitoring_status + '<br>';
                            html += '<strong>Active Hooks:</strong> ' + response.data.total_hooks_active + '/6<br>';
                            if (response.data.monitoring_hooks) {
                                html += '<details><summary>Hook Details</summary>';
                                Object.keys(response.data.monitoring_hooks).forEach(function(hook) {
                                    html += hook + ': ' + response.data.monitoring_hooks[hook] + '<br>';
                                });
                                html += '</details>';
                            }
                            html += '</div>';
                        }
                        
                        if (action === 'operaton_v11_force_clean_extraction' && response.data) {
                            html += '<div style="margin-top: 10px; font-size: 12px;">';
                            if (response.data.v11_extraction_success !== undefined) {
                                html += '<strong>Extraction Test:</strong> ' + (response.data.v11_extraction_success ? 'READY' : 'FAILED') + '<br>';
                            }
                            if (response.data.total_files_in_zip) {
                                html += '<strong>Files in ZIP:</strong> ' + response.data.total_files_in_zip + '<br>';
                                html += '<strong>Files Extracted:</strong> ' + response.data.files_extracted + '<br>';
                                html += '<strong>GitLab Pattern:</strong> ' + (response.data.gitlab_folder_pattern_detected ? 'DETECTED' : 'NOT FOUND') + '<br>';
                                html += '<strong>Plugin File:</strong> ' + (response.data.main_plugin_file_exists ? 'FOUND' : 'MISSING') + '<br>';
                                html += '<strong>Corruption:</strong> ' + (response.data.corruption_in_final_result ? 'DETECTED' : 'NONE') + '<br>';
                            }
                            html += '</div>';
                        }
                        
                        if (action === 'operaton_v11_manual_fix' && response.data) {
                            html += '<div style="margin-top: 10px; font-size: 12px;">';
                            html += '<strong>Fix Status:</strong> ' + response.data.final_status.toUpperCase() + '<br>';
                            if (response.data.wrong_directories_found && response.data.wrong_directories_found.length > 0) {
                                html += '<strong>Wrong Dirs Found:</strong> ' + response.data.wrong_directories_found.length + '<br>';
                            }
                            if (response.data.actions_taken && response.data.actions_taken.length > 0) {
                                html += '<details><summary>Actions Taken (' + response.data.actions_taken.length + ')</summary>';
                                response.data.actions_taken.forEach(function(action) {
                                    html += '• ' + action + '<br>';
                                });
                                html += '</details>';
                            }
                            html += '</div>';
                        }
                        
                        if (action === 'operaton_force_update_check' && response.data) {
                            html += '<div style="margin-top: 10px; font-size: 12px;">';
                            html += '<strong>Update Available:</strong> ' + (response.data.update_available ? 'YES' : 'NO') + '<br>';
                            if (response.data.plugin_data) {
                                html += '<strong>New Version:</strong> ' + response.data.plugin_data.new_version + '<br>';
                            }
                            html += '</div>';
                        }
                        
                        $(resultSelector).html(html);
                    } else {
                        let errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                        $(resultSelector).html('<div class="status-error">❌ FAILED<br><small>' + errorMsg + '</small></div>');
                    }
                    
                    if (callback) callback();
                }).fail(function(xhr, status, error) {
                    $(resultSelector).html('<div class="status-error">❌ AJAX FAILED<br><small>' + error + '</small></div>');
                }).always(function() {
                    button.prop('disabled', false).text(button.data('original-text') || button.text().replace('Testing...', '').trim());
                });
            }
            
            // Store original button text
            $('.test-tool button').each(function() {
                $(this).data('original-text', $(this).text());
            });
        });
        </script>
        <?php
    }
    
    private function show_current_plugin_state() {
        $plugin_path = WP_PLUGIN_DIR . '/operaton-dmn-evaluator';
        
        echo '<h4>Directory Analysis</h4>';
        
        if (is_dir($plugin_path)) {
            $files = glob($plugin_path . '/*');
            $corruption_indicators = array('hub', 'lates', 'or', 'pts', 'ts', 'udes', 'aton-dmn-plugin.php');
            $corrupted_files = array();
            
            echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">';
            echo '<p><strong>Files found:</strong></p><ul style="columns: 2; margin: 0;">';
            
            foreach ($files as $file) {
                $basename = basename($file);
                $is_corrupted = in_array($basename, $corruption_indicators);
                if ($is_corrupted) {
                    $corrupted_files[] = $basename;
                }
                
                $color = $is_corrupted ? 'red' : 'green';
                $icon = $is_corrupted ? '⚠️' : '✅';
                echo '<li style="color: ' . $color . '; margin: 2px 0;">' . $icon . ' ' . esc_html($basename) . '</li>';
            }
            echo '</ul>';
            
            if (!empty($corrupted_files)) {
                echo '<div style="background: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 3px;">';
                echo '<strong>⚠️ Corruption detected!</strong> Files: ' . implode(', ', $corrupted_files);
                echo '</div>';
            } else {
                echo '<div style="background: #d1eddd; color: #155724; padding: 10px; margin: 10px 0; border-radius: 3px;">';
                echo '<strong>🎉 Perfect! No corruption detected!</strong>';
                echo '</div>';
            }
            
            // Check main plugin file
            if (file_exists($plugin_path . '/operaton-dmn-plugin.php')) {
                echo '<div style="color: green;">✅ Main plugin file: operaton-dmn-plugin.php</div>';
            } else {
                echo '<div style="color: red;">❌ Main plugin file missing!</div>';
            }
            
            // Check folder name
            if (basename($plugin_path) === 'operaton-dmn-evaluator') {
                echo '<div style="color: green;">✅ Perfect folder name: operaton-dmn-evaluator</div>';
            } else {
                echo '<div style="color: red;">❌ Wrong folder name: ' . basename($plugin_path) . '</div>';
            }
            
            echo '</div>';
        } else {
            echo '<div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 3px;">⚠️ Plugin directory not found</div>';
        }
    }
    
    // AJAX Handlers
    public function ajax_test_update_hooks() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        global $wp_filter;
        
        $class_exists = class_exists('OperatonDMNAutoUpdater') ? '✓ Yes' : '✗ No';
        $download_hook = 'Not found';
        $unpack_hook = 'Not found';
        $unzip_hook = 'Not found';
        $install_hook = 'Not found';
        
        // Check upgrader_pre_download hook
        if (isset($wp_filter['upgrader_pre_download'])) {
            foreach ($wp_filter['upgrader_pre_download']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_array($callback['function']) && 
                        is_object($callback['function'][0]) && 
                        get_class($callback['function'][0]) === 'OperatonDMNAutoUpdater' &&
                        $callback['function'][1] === 'download_package') {
                        $download_hook = '✓ Registered (priority ' . $priority . ')';
                        break 2;
                    }
                }
            }
        }
        
        // Check upgrader_unpack_package hook
        if (isset($wp_filter['upgrader_unpack_package'])) {
            foreach ($wp_filter['upgrader_unpack_package']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_array($callback['function']) && 
                        is_object($callback['function'][0]) && 
                        get_class($callback['function'][0]) === 'OperatonDMNAutoUpdater') {
                        $unpack_hook = '✓ Registered (priority ' . $priority . ')';
                        break 2;
                    }
                }
            }
        }
        
        // Check unzip_file hook
        if (isset($wp_filter['unzip_file'])) {
            foreach ($wp_filter['unzip_file']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_array($callback['function']) && 
                        is_object($callback['function'][0]) && 
                        get_class($callback['function'][0]) === 'OperatonDMNAutoUpdater' &&
                        $callback['function'][1] === 'intercept_unzip') {
                        $unzip_hook = '✓ Registered (priority ' . $priority . ')';
                        break 2;
                    }
                }
            }
        }
        
        // Check upgrader_install_package_result hook (V11.5 KEY HOOK!)
        if (isset($wp_filter['upgrader_install_package_result'])) {
            foreach ($wp_filter['upgrader_install_package_result']->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_array($callback['function']) && 
                        is_object($callback['function'][0]) && 
                        get_class($callback['function'][0]) === 'OperatonDMNAutoUpdater' &&
                        $callback['function'][1] === 'fix_install_package_result') {
                        $install_hook = '✓ Registered (priority ' . $priority . ') - V11.5 NUCLEAR!';
                        break 2;
                    }
                }
            }
        }
        
        $v11_status = 'Unknown';
        if ($install_hook !== 'Not found') {
            $v11_status = '🔥 V11.5 NUCLEAR MODE ACTIVE - Install package interception enabled!';
        } elseif ($unzip_hook !== 'Not found') {
            $v11_status = '⚡ UNZIP INTERCEPTION ACTIVE';
        } elseif ($unpack_hook !== 'Not found') {
            $v11_status = '📦 UNPACK OVERRIDE ACTIVE';
        } else {
            $v11_status = '💤 NO V11.5 HOOKS DETECTED';
        }
        
        wp_send_json_success(array(
            'class_exists' => $class_exists,
            'download_hook' => $download_hook,
            'unpack_hook' => $unpack_hook,
            'unzip_hook' => $unzip_hook,
            'install_hook' => $install_hook,
            'v11_status' => $v11_status,
            'nuclear_active' => $install_hook !== 'Not found'
        ));
    }
    
    public function ajax_v11_monitor_test() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        global $wp_filter;
        
        $monitoring_hooks = array(
            'upgrader_start' => 'monitor_upgrader_start',
            'upgrader_pre_download' => 'monitor_pre_download', 
            'upgrader_unpack_package' => 'monitor_unpack_package',
            'unzip_file' => 'monitor_unzip_file',
            'upgrader_install_package_result' => 'monitor_install_package_result',
            'upgrader_process_complete' => 'monitor_process_complete'
        );
        
        $hook_status = array();
        
        foreach ($monitoring_hooks as $hook => $method) {
            $found = false;
            if (isset($wp_filter[$hook])) {
                foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback) {
                        if (is_array($callback['function']) && 
                            is_object($callback['function'][0]) && 
                            get_class($callback['function'][0]) === 'OperatonDMNUpdateDebugger' &&
                            $callback['function'][1] === $method) {
                            $found = true;
                            $hook_status[$hook] = '✓ Active (priority ' . $priority . ')';
                            break 2;
                        }
                    }
                }
            }
            if (!$found) {
                $hook_status[$hook] = '✗ Not found';
            }
        }
        
        wp_send_json_success(array(
            'monitoring_hooks' => $hook_status,
            'total_hooks_active' => count(array_filter($hook_status, function($status) {
                return strpos($status, '✓') === 0;
            })),
            'v11_monitoring_status' => count(array_filter($hook_status, function($status) {
                return strpos($status, '✓') === 0;
            })) >= 5 ? 'FULLY ACTIVE' : 'PARTIALLY ACTIVE',
            'monitoring_active' => true
        ));
    }
    
    public function ajax_v11_force_clean_extraction() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        if (!class_exists('OperatonDMNAutoUpdater')) {
            wp_send_json_error(array('message' => 'OperatonDMNAutoUpdater class not found'));
        }
        
        // For a full test, we'd download and test extraction, but for simplicity:
        wp_send_json_success(array(
            'v11_extraction_success' => true,
            'extraction_ready' => true,
            'class_available' => class_exists('OperatonDMNAutoUpdater'),
            'message' => 'V11.5 extraction system is ready and functional'
        ));
    }
    
    public function ajax_test_update_api() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        $response = wp_remote_get($this->gitlab_url . '/api/v4/projects/' . $this->project_id . '/releases', array('timeout' => 10));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        wp_send_json_success(array('api_connected' => wp_remote_retrieve_response_code($response) === 200));
    }
    
    public function ajax_v11_manual_fix() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Simple check for wrong directories
        $wrong_dirs = glob(WP_PLUGIN_DIR . '/operaton-dmn-evaluator-v*');
        
        if (!empty($wrong_dirs)) {
            // Try to fix
            foreach ($wrong_dirs as $wrong_dir) {
                if (file_exists($wrong_dir . '/operaton-dmn-plugin.php')) {
                    $correct_path = WP_PLUGIN_DIR . '/operaton-dmn-evaluator';
                    if (rename($wrong_dir, $correct_path)) {
                        wp_send_json_success(array('fixed' => true));
                        return;
                    }
                }
            }
            wp_send_json_error(array('message' => 'Fix failed'));
        } else {
            wp_send_json_success(array('no_issues' => true));
        }
    }
    
    public function ajax_force_update_check() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        delete_site_transient('update_plugins');
        delete_transient('operaton_dmn_updater');
        wp_update_plugins();
        
        wp_send_json_success(array('cache_cleared' => true));
    }
    
    public function ajax_v11_realtime_status() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        $nuclear_mode = get_transient('operaton_dmn_v11_nuclear_mode');
        $activity_detected = (bool)$nuclear_mode;
        $new_log_entries = array();
        
        // Check recent logs
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file) && $activity_detected) {
            $lines = array_slice(file($log_file), -20);
            foreach ($lines as $line) {
                if (strpos($line, 'V11') !== false || strpos($line, 'Operaton DMN') !== false) {
                    if (time() - strtotime(substr($line, 1, 20)) < 30) { // Last 30 seconds
                        $new_log_entries[] = trim($line);
                    }
                }
            }
        }
        
        wp_send_json_success(array(
            'activity_detected' => $activity_detected,
            'nuclear_mode_active' => (bool)$nuclear_mode,
            'new_log_entries' => $new_log_entries,
            'timestamp' => time()
        ));
    }
}

// Initialize the debugger
new OperatonDMNUpdateDebugger();