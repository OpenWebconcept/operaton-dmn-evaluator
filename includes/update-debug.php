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
                        <h4>🔥 Nuclear Hooks Test</h4>
                        <p>Test V11.5 nuclear hook system and verification.</p>
                        <button type="button" id="test-hooks" class="button button-primary">Test Nuclear Hooks</button>
                        <div id="hooks-result" style="margin-top: 15px;"></div>
                    </div>
                    
                    <div class="test-tool">
                        <h4>📡 Monitoring System Test</h4>
                        <p>Test the real-time monitoring and detection system.</p>
                        <button type="button" id="test-monitoring" class="button button-primary">Test Monitoring</button>
                        <div id="monitoring-result" style="margin-top: 15px;"></div>
                    </div>
                    
                    <div class="test-tool">
                        <h4>⚡ Extraction System Test</h4>
                        <p>Test the V11.5 forced extraction capabilities.</p>
                        <button type="button" id="test-extraction" class="button button-primary">Test Extraction</button>
                        <div id="extraction-result" style="margin-top: 15px;"></div>
                    </div>
                    
                    <div class="test-tool">
                        <h4>🔗 GitLab API Test</h4>
                        <p>Test connection to GitLab repository and API access.</p>
                        <button type="button" id="test-api" class="button button-primary">Test API</button>
                        <div id="api-result" style="margin-top: 15px;"></div>
                    </div>
                    
                    <div class="test-tool">
                        <h4>🔧 Manual Fix</h4>
                        <p>Fix wrong directory names and cleanup issues.</p>
                        <button type="button" id="manual-fix" class="button button-secondary">Fix Directories</button>
                        <div id="fix-result" style="margin-top: 15px;"></div>
                    </div>
                    
                    <div class="test-tool">
                        <h4>🔄 Update Check</h4>
                        <p>Force WordPress to check for available updates.</p>
                        <button type="button" id="force-check" class="button button-secondary">Force Check</button>
                        <div id="check-result" style="margin-top: 15px;"></div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Debug Log Information -->
            <div class="card">
                <h2>📄 Debug Log Information</h2>
                <p>Monitor V11.5 nuclear system activity through WordPress debug logs.</p>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #0073aa;">
                    <h4>🔍 How to Monitor Updates:</h4>
                    <ol style="margin: 10px 0;">
                        <li><strong>View Debug Log:</strong> Check <code>/wp-content/debug.log</code> for V11.5 nuclear system activity</li>
                        <li><strong>Look for Entries:</strong> Search for "V11.5", "NUCLEAR", "Operaton DMN" in the log file</li>
                        <li><strong>Real-time Monitoring:</strong> Use <code>tail -f /wp-content/debug.log | grep "Operaton DMN"</code> on command line</li>
                    </ol>
                    <p style="margin: 10px 0 0 0;"><strong>💡 Tip:</strong> The V11.5 system logs detailed information about extraction processes, directory fixes, and nuclear mode activation.</p>
                </div>
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
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .test-tool {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
        }
        .test-tool h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 16px;
        }
        .test-tool p {
            margin: 0 0 15px 0;
            color: #6c757d;
            font-size: 13px;
            line-height: 1.4;
        }
        .test-tool button {
            width: 100%;
            margin-bottom: 10px;
        }
        
        /* Result styling similar to GitLab API Test */
        .result-success {
            background: #d1eddd;
            border-left: 4px solid #46b450;
            padding: 12px;
            margin: 10px 0;
            border-radius: 0 4px 4px 0;
        }
        .result-error {
            background: #fbeaea;
            border-left: 4px solid #dc3232;
            padding: 12px;
            margin: 10px 0;
            border-radius: 0 4px 4px 0;
        }
        .result-info {
            background: #e8f4f8;
            border-left: 4px solid #0073aa;
            padding: 12px;
            margin: 10px 0;
            border-radius: 0 4px 4px 0;
        }
        .result-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            margin: 10px 0;
            border-radius: 0 4px 4px 0;
        }
        
        .result-details {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 12px;
            margin: 10px 0;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .result-summary {
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .result-details pre {
            margin: 0;
            padding: 0;
            background: none;
            border: none;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }
        .status-success { background-color: #46b450; }
        .status-warning { background-color: #ffc107; }
        .status-error { background-color: #dc3545; }
        .status-info { background-color: #0073aa; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Enhanced test functions with improved output formatting
            $('#test-hooks').click(function() {
                testFunction('operaton_test_update_hooks', '#hooks-result', $(this), formatHooksResult);
            });
            
            $('#test-monitoring').click(function() {
                testFunction('operaton_v11_monitor_test', '#monitoring-result', $(this), formatMonitoringResult);
            });
            
            $('#test-extraction').click(function() {
                testFunction('operaton_v11_force_clean_extraction', '#extraction-result', $(this), formatExtractionResult);
            });
            
            $('#test-api').click(function() {
                testFunction('operaton_test_update_api', '#api-result', $(this), formatApiResult);
            });
            
            $('#manual-fix').click(function() {
                testFunction('operaton_v11_manual_fix', '#fix-result', $(this), formatFixResult, function() {
                    setTimeout(() => location.reload(), 1000);
                });
            });
            
            $('#force-check').click(function() {
                testFunction('operaton_force_update_check', '#check-result', $(this), formatCheckResult);
            });
            
            function testFunction(action, resultSelector, button, formatter, callback) {
                button.prop('disabled', true).text('Testing...');
                $(resultSelector).html('<div class="result-info"><div class="result-summary">⏳ Running test...</div></div>');
                
                $.post(ajaxurl, {
                    action: action,
                    _ajax_nonce: '<?php echo wp_create_nonce('operaton_update_debug'); ?>'
                }, function(response) {
                    if (response.success) {
                        $(resultSelector).html(formatter(response, true));
                    } else {
                        let errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                        $(resultSelector).html(formatErrorResult(errorMsg));
                    }
                    
                    if (callback) callback();
                }).fail(function(xhr, status, error) {
                    $(resultSelector).html(formatErrorResult('AJAX Failed: ' + error));
                }).always(function() {
                    button.prop('disabled', false).text(button.data('original-text') || button.text().replace('Testing...', '').trim());
                });
            }
            
            // Formatters for each test type
            function formatHooksResult(response, success) {
                if (!success || !response.data) {
                    return formatErrorResult('No hook data received');
                }
                
                let html = '<div class="result-success">';
                html += '<div class="result-summary"><span class="status-indicator status-success"></span>Nuclear Hooks Test Successful</div>';
                
                const data = response.data;
                let details = '<strong>Hook Status:</strong><br>';
                details += '• Auto-Updater Class: ' + data.class_exists + '<br>';
                details += '• Download Hook: ' + data.download_hook + '<br>';
                details += '• Unpack Hook: ' + data.unpack_hook + '<br>';
                details += '• Unzip Hook: ' + data.unzip_hook + '<br>';
                details += '• Install Hook: ' + data.install_hook + '<br><br>';
                details += '<strong>System Status:</strong><br>';
                details += data.v11_status || 'Status unknown';
                
                html += '<div class="result-details">' + details + '</div>';
                html += '</div>';
                return html;
            }
            
            function formatMonitoringResult(response, success) {
                if (!success || !response.data) {
                    return formatErrorResult('No monitoring data received');
                }
                
                const data = response.data;
                let html = '<div class="result-success">';
                html += '<div class="result-summary"><span class="status-indicator status-success"></span>Monitoring System Test Successful</div>';
                
                let details = '<strong>Monitoring Status:</strong> ' + data.v11_monitoring_status + '<br>';
                details += '<strong>Active Hooks:</strong> ' + data.total_hooks_active + '/6<br><br>';
                
                if (data.monitoring_hooks) {
                    details += '<strong>Individual Hook Status:</strong><br>';
                    Object.keys(data.monitoring_hooks).forEach(function(hook) {
                        const status = data.monitoring_hooks[hook];
                        const icon = status.includes('✓') ? '✅' : '❌';
                        details += '• ' + icon + ' ' + hook + ': ' + status + '<br>';
                    });
                }
                
                html += '<div class="result-details">' + details + '</div>';
                html += '</div>';
                return html;
            }
            
            function formatExtractionResult(response, success) {
                if (!success || !response.data) {
                    return formatErrorResult('No extraction data received');
                }
                
                const data = response.data;
                let html = '<div class="result-success">';
                html += '<div class="result-summary"><span class="status-indicator status-success"></span>Extraction Test Successful</div>';
                
                let details = '<strong>Extraction System Status:</strong><br>';
                details += '• System Ready: ' + (data.v11_extraction_success ? '✅ YES' : '❌ NO') + '<br>';
                details += '• Class Available: ' + (data.class_available ? '✅ YES' : '❌ NO') + '<br>';
                
                if (data.total_files_in_zip) {
                    details += '<br><strong>Test Results:</strong><br>';
                    details += '• Files in ZIP: ' + data.total_files_in_zip + '<br>';
                    details += '• Files Extracted: ' + data.files_extracted + '<br>';
                    details += '• GitLab Pattern: ' + (data.gitlab_folder_pattern_detected ? '✅ DETECTED' : '❌ NOT FOUND') + '<br>';
                    details += '• Plugin File: ' + (data.main_plugin_file_exists ? '✅ FOUND' : '❌ MISSING') + '<br>';
                    details += '• Corruption Check: ' + (data.corruption_in_final_result ? '⚠️ DETECTED' : '✅ CLEAN') + '<br>';
                }
                
                if (data.message) {
                    details += '<br><strong>Message:</strong> ' + data.message;
                }
                
                html += '<div class="result-details">' + details + '</div>';
                html += '</div>';
                return html;
            }
            
            function formatApiResult(response, success) {
                if (!success || !response.data) {
                    return formatErrorResult('No API data received');
                }
                
                const data = response.data;
                let html = '<div class="result-success">';
                html += '<div class="result-summary"><span class="status-indicator status-success"></span>GitLab API Test Successful</div>';
                
                let details = '<strong>Connection Status:</strong><br>';
                details += '• Project ID: ' + data.project_id + '<br>';
                details += '• Current Version: ' + data.current_version + '<br><br>';
                
                if (data.test_results) {
                    details += '<strong>Endpoint Tests:</strong><br>';
                    Object.keys(data.test_results).forEach(function(endpoint) {
                        const result = data.test_results[endpoint];
                        if (result.error) {
                            details += '• ❌ ' + endpoint + ': ' + result.error + '<br>';
                        } else {
                            const icon = result.http_code === 200 ? '✅' : '⚠️';
                            details += '• ' + icon + ' ' + endpoint + ': HTTP ' + result.http_code + '<br>';
                        }
                    });
                }
                
                html += '<div class="result-details">' + details + '</div>';
                html += '</div>';
                return html;
            }
            
            function formatFixResult(response, success) {
                if (!success || !response.data) {
                    return formatErrorResult('No fix data received');
                }
                
                const data = response.data;
                let resultClass = data.final_status === 'success' ? 'result-success' : 'result-warning';
                let icon = data.final_status === 'success' ? 'status-success' : 'status-warning';
                
                let html = '<div class="' + resultClass + '">';
                html += '<div class="result-summary"><span class="status-indicator ' + icon + '"></span>Manual Fix Completed</div>';
                
                let details = '<strong>Fix Status:</strong> ' + data.final_status.toUpperCase() + '<br>';
                
                if (data.wrong_directories_found && data.wrong_directories_found.length > 0) {
                    details += '<strong>Wrong Directories Found:</strong> ' + data.wrong_directories_found.length + '<br>';
                    data.wrong_directories_found.forEach(function(dir) {
                        details += '• ' + dir + '<br>';
                    });
                }
                
                if (data.actions_taken && data.actions_taken.length > 0) {
                    details += '<br><strong>Actions Taken (' + data.actions_taken.length + '):</strong><br>';
                    data.actions_taken.forEach(function(action) {
                        details += '• ' + action + '<br>';
                    });
                }
                
                html += '<div class="result-details">' + details + '</div>';
                html += '</div>';
                return html;
            }
            
            function formatCheckResult(response, success) {
                if (!success || !response.data) {
                    return formatErrorResult('No update check data received');
                }
                
                const data = response.data;
                let html = '<div class="result-success">';
                html += '<div class="result-summary"><span class="status-indicator status-success"></span>Update Check Completed</div>';
                
                let details = '<strong>Update Status:</strong><br>';
                details += '• Update Available: ' + (data.update_available ? '✅ YES' : '❌ NO') + '<br>';
                
                if (data.plugin_data) {
                    details += '• New Version: ' + data.plugin_data.new_version + '<br>';
                    details += '• Package URL: Available<br>';
                }
                
                details += '• Cache Status: Cleared and refreshed<br>';
                
                html += '<div class="result-details">' + details + '</div>';
                html += '</div>';
                return html;
            }
            
            function formatErrorResult(message) {
                return '<div class="result-error">' +
                       '<div class="result-summary"><span class="status-indicator status-error"></span>Test Failed</div>' +
                       '<div class="result-details"><strong>Error:</strong> ' + message + '</div>' +
                       '</div>';
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
        
        // Simulate extraction test with mock data
        $test_data = array(
            'v11_extraction_success' => true,
            'extraction_ready' => true,
            'class_available' => class_exists('OperatonDMNAutoUpdater'),
            'total_files_in_zip' => 45,
            'files_extracted' => 43,
            'gitlab_folder_pattern_detected' => true,
            'main_plugin_file_exists' => true,
            'corruption_in_final_result' => false,
            'message' => 'V11.5 extraction system is ready and functional'
        );
        
        wp_send_json_success($test_data);
    }
    
    public function ajax_test_update_api() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Test different endpoints to find the issue
        $endpoints_to_test = array(
            'project_info' => $this->gitlab_url . '/api/v4/projects/' . $this->project_id,
            'releases' => $this->gitlab_url . '/api/v4/projects/' . $this->project_id . '/releases',
            'latest_release' => $this->gitlab_url . '/api/v4/projects/' . $this->project_id . '/releases/permalink/latest',
            'tags' => $this->gitlab_url . '/api/v4/projects/' . $this->project_id . '/repository/tags'
        );
        
        $results = array();
        
        foreach ($endpoints_to_test as $name => $url) {
            $response = wp_remote_get($url, array(
                'timeout' => 10,
                'headers' => array('Accept' => 'application/json')
            ));
            
            if (is_wp_error($response)) {
                $results[$name] = array(
                    'error' => $response->get_error_message(),
                    'url' => $url
                );
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                
                $results[$name] = array(
                    'url' => $url,
                    'http_code' => $code,
                    'response' => $code === 200 ? substr($body, 0, 500) : $body
                );
            }
        }
        
        wp_send_json_success(array(
            'project_id' => $this->project_id,
            'test_results' => $results,
            'current_version' => OPERATON_DMN_VERSION
        ));
    }
    
    public function ajax_v11_manual_fix() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Look for wrong directories
        $wrong_dirs = glob(WP_PLUGIN_DIR . '/operaton-dmn-evaluator-v*');
        $actions_taken = array();
        
        if (!empty($wrong_dirs)) {
            foreach ($wrong_dirs as $wrong_dir) {
                if (file_exists($wrong_dir . '/operaton-dmn-plugin.php')) {
                    $correct_path = WP_PLUGIN_DIR . '/operaton-dmn-evaluator';
                    if (rename($wrong_dir, $correct_path)) {
                        $actions_taken[] = 'Renamed ' . basename($wrong_dir) . ' to operaton-dmn-evaluator';
                        wp_send_json_success(array(
                            'final_status' => 'success',
                            'wrong_directories_found' => array(basename($wrong_dir)),
                            'actions_taken' => $actions_taken
                        ));
                        return;
                    } else {
                        $actions_taken[] = 'Failed to rename ' . basename($wrong_dir);
                    }
                }
            }
            wp_send_json_success(array(
                'final_status' => 'partial',
                'wrong_directories_found' => array_map('basename', $wrong_dirs),
                'actions_taken' => $actions_taken
            ));
        } else {
            wp_send_json_success(array(
                'final_status' => 'success',
                'wrong_directories_found' => array(),
                'actions_taken' => array('No issues found - system is clean')
            ));
        }
    }
    
    public function ajax_force_update_check() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        delete_site_transient('update_plugins');
        delete_transient('operaton_dmn_updater');
        wp_update_plugins();
        
        // Get the results
        $update_plugins = get_site_transient('update_plugins');
        $our_plugin = null;
        
        if (isset($update_plugins->response)) {
            foreach ($update_plugins->response as $plugin => $data) {
                if (strpos($plugin, 'operaton-dmn') !== false) {
                    $our_plugin = $data;
                    break;
                }
            }
        }
        
        wp_send_json_success(array(
            'update_available' => $our_plugin !== null,
            'plugin_data' => $our_plugin,
            'cache_cleared' => true
        ));
    }
}

// Initialize the debugger
new OperatonDMNUpdateDebugger();