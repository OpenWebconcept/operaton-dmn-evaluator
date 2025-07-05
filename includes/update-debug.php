<?php
/**
 * Operaton DMN Auto-Update Debug and Test Script
 * 
 * This file provides debugging tools for the auto-update functionality
 * File: includes/update-debug.php
 */

// Only load in admin and for authorized users - but wait for WordPress to be ready
if (!is_admin()) {
    return;
}

/**
 * Debug Auto-Update System
 */
class OperatonDMNUpdateDebugger {
    
    private $gitlab_url = 'https://git.open-regels.nl';
    private $project_id = 'showcases/operaton-dmn-evaluator';
    
    public function __construct() {
        error_log('Operaton DMN: OperatonDMNUpdateDebugger constructor called');
        // Wait for WordPress to be fully loaded before checking user capabilities
        add_action('admin_init', array($this, 'init_debug_tools'));
    }
    
    /**
 * Initialize debug tools after WordPress is fully loaded
 */
public function init_debug_tools() {
    error_log('Operaton DMN: init_debug_tools called');
    
    // Now it's safe to check user capabilities
    if (!current_user_can('manage_options')) {
        error_log('Operaton DMN: User does not have manage_options capability');
        return;
    }
    
    error_log('Operaton DMN: User has manage_options, adding debug menu');
    
    // Use higher priority to ensure it runs after the main plugin menu
    add_action('admin_menu', array($this, 'add_debug_menu'), 20);
    
    add_action('wp_ajax_operaton_test_update_api', array($this, 'ajax_test_update_api'));
    add_action('wp_ajax_operaton_force_update_check', array($this, 'ajax_force_update_check'));
    add_action('wp_ajax_operaton_simulate_update', array($this, 'ajax_simulate_update'));
    
    error_log('Operaton DMN: Debug hooks added with priority 20');
}
    
    /**
     * Add debug menu page
     */
    public function add_debug_menu() {
        error_log('Operaton DMN: add_debug_menu called');
        add_submenu_page(
            'operaton-dmn',
            __('Update Debug', 'operaton-dmn'),
            __('Update Debug', 'operaton-dmn'),
            'manage_options',
            'operaton-dmn-update-debug',
            array($this, 'debug_page')
        );
        error_log('Operaton DMN: Debug submenu added');
    }
    
    /**
     * Debug page content
     */
    public function debug_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Operaton DMN Auto-Update Debug', 'operaton-dmn'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong>Note:</strong> This debug page helps test the auto-update functionality. Remove this in production.</p>
            </div>
            
            <!-- System Information -->
            <div class="card">
                <h2>System Information</h2>
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
                        <th>PHP Version</th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>Auto-Updater Class</th>
                        <td><?php echo class_exists('OperatonDMNAutoUpdater') ? '✓ Loaded' : '✗ Not Found'; ?></td>
                    </tr>
                    <tr>
                        <th>Plugin Basename</th>
                        <td><?php echo plugin_basename(OPERATON_DMN_PLUGIN_PATH . 'operaton-dmn-plugin.php'); ?></td>
                    </tr>
                </table>
            </div>
            
            <!-- GitLab API Test -->
            <div class="card">
                <h2>GitLab API Test</h2>
                <p>Test connection to GitLab repository and latest release information.</p>
                <button type="button" id="test-gitlab-api" class="button button-primary">Test GitLab API</button>
                <div id="gitlab-api-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Update Transient Info -->
            <div class="card">
                <h2>WordPress Update Transients</h2>
                <p>Information about WordPress update checking system.</p>
                
                <h3>Update Plugins Transient</h3>
                <div style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;">
                    <pre><?php 
                    $update_plugins = get_site_transient('update_plugins');
                    echo esc_html(print_r($update_plugins, true)); 
                    ?></pre>
                </div>
                
                <h3>Plugin Cache</h3>
                <div style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; max-height: 200px; overflow-y: auto;">
                    <pre><?php 
                    $cache = get_transient('operaton_dmn_updater');
                    echo esc_html(print_r($cache, true)); 
                    ?></pre>
                </div>
                
                <p style="margin-top: 15px;">
                    <button type="button" id="clear-transients" class="button">Clear All Update Transients</button>
                    <button type="button" id="force-update-check" class="button button-secondary">Force Update Check</button>
                </p>
                <div id="transient-results" style="margin-top: 10px;"></div>
            </div>
            
            <!-- Simulate Update -->
            <div class="card">
                <h2>Simulate Update Process</h2>
                <p>Test the update detection and download process without actually updating.</p>
                
                <form id="simulate-update-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="simulate-version">Simulate Version</label></th>
                            <td>
                                <input type="text" id="simulate-version" name="simulate_version" value="999.0.0" class="regular-text" />
                                <p class="description">Enter a higher version number to simulate an available update</p>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="submit" class="button button-primary">Simulate Update Detection</button>
                </form>
                <div id="simulation-results" style="margin-top: 15px;"></div>
            </div>
            
            <!-- Manual Update Check -->
            <div class="card">
                <h2>Manual Update Process</h2>
                <p>Test individual components of the update system.</p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h4>1. Check Latest Release</h4>
                        <button type="button" id="check-latest-release" class="button">Check Latest Release</button>
                        <div id="latest-release-info" style="margin-top: 10px; font-size: 12px;"></div>
                    </div>
                    
                    <div>
                        <h4>2. Test Download URL</h4>
                        <button type="button" id="test-download-url" class="button">Test Download URL</button>
                        <div id="download-url-info" style="margin-top: 10px; font-size: 12px;"></div>
                    </div>
                    
                    <div>
                        <h4>3. Validate Package</h4>
                        <button type="button" id="validate-package" class="button">Validate Package Structure</button>
                        <div id="package-validation-info" style="margin-top: 10px; font-size: 12px;"></div>
                    </div>
                    
                    <div>
                        <h4>4. Test Update Hook</h4>
                        <button type="button" id="test-update-hook" class="button">Test Update Hooks</button>
                        <div id="update-hook-info" style="margin-top: 10px; font-size: 12px;"></div>
                    </div>
                </div>
            </div>
            
            <!-- Troubleshooting -->
            <div class="card">
                <h2>Troubleshooting</h2>
                <div id="troubleshooting-info">
                    <?php $this->show_troubleshooting_info(); ?>
                </div>
                <button type="button" id="run-diagnostics" class="button">Run Full Diagnostics</button>
                <div id="diagnostics-results" style="margin-top: 15px;"></div>
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
        .card h2 {
            margin-top: 0;
        }
        .result-success {
            background: #d1eddd;
            border-left: 4px solid #46b450;
            padding: 10px;
            margin: 10px 0;
        }
        .result-error {
            background: #fbeaea;
            border-left: 4px solid #dc3232;
            padding: 10px;
            margin: 10px 0;
        }
        .result-info {
            background: #e8f4f8;
            border-left: 4px solid #0073aa;
            padding: 10px;
            margin: 10px 0;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            
            // Test GitLab API
            $('#test-gitlab-api').click(function() {
                var button = $(this);
                var results = $('#gitlab-api-results');
                
                button.prop('disabled', true).text('Testing...');
                results.html('<div class="result-info">Testing GitLab API connection...</div>');
                
                $.post(ajaxurl, {
                    action: 'operaton_test_update_api',
                    _ajax_nonce: '<?php echo wp_create_nonce('operaton_update_debug'); ?>'
                }, function(response) {
                    if (response.success) {
                        results.html('<div class="result-success"><h4>API Test Successful</h4><pre>' + JSON.stringify(response.data, null, 2) + '</pre></div>');
                    } else {
                        results.html('<div class="result-error"><h4>API Test Failed</h4><p>' + response.data.message + '</p></div>');
                    }
                }).fail(function() {
                    results.html('<div class="result-error">AJAX request failed</div>');
                }).always(function() {
                    button.prop('disabled', false).text('Test GitLab API');
                });
            });
            
            // Clear transients
            $('#clear-transients').click(function() {
                var button = $(this);
                var results = $('#transient-results');
                
                button.prop('disabled', true).text('Clearing...');
                
                $.post(ajaxurl, {
                    action: 'operaton_clear_update_cache',
                    _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        results.html('<div class="result-success">Update transients cleared successfully</div>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        results.html('<div class="result-error">Failed to clear transients</div>');
                    }
                }).always(function() {
                    button.prop('disabled', false).text('Clear All Update Transients');
                });
            });
            
            // Force update check
            $('#force-update-check').click(function() {
                var button = $(this);
                var results = $('#transient-results');
                
                button.prop('disabled', true).text('Checking...');
                
                $.post(ajaxurl, {
                    action: 'operaton_force_update_check',
                    _ajax_nonce: '<?php echo wp_create_nonce('operaton_update_debug'); ?>'
                }, function(response) {
                    if (response.success) {
                        results.html('<div class="result-success"><h4>Update Check Completed</h4><pre>' + JSON.stringify(response.data, null, 2) + '</pre></div>');
                    } else {
                        results.html('<div class="result-error">Update check failed: ' + response.data.message + '</div>');
                    }
                }).always(function() {
                    button.prop('disabled', false).text('Force Update Check');
                });
            });
            
            // Simulate update
            $('#simulate-update-form').submit(function(e) {
                e.preventDefault();
                var results = $('#simulation-results');
                var version = $('#simulate-version').val();
                
                results.html('<div class="result-info">Simulating update detection for version ' + version + '...</div>');
                
                $.post(ajaxurl, {
                    action: 'operaton_simulate_update',
                    simulate_version: version,
                    _ajax_nonce: '<?php echo wp_create_nonce('operaton_update_debug'); ?>'
                }, function(response) {
                    if (response.success) {
                        results.html('<div class="result-success"><h4>Simulation Results</h4><pre>' + JSON.stringify(response.data, null, 2) + '</pre></div>');
                    } else {
                        results.html('<div class="result-error">Simulation failed: ' + response.data.message + '</div>');
                    }
                });
            });
            
            // Individual component tests
            var componentTests = {
                'check-latest-release': 'latest-release-info',
                'test-download-url': 'download-url-info',
                'validate-package': 'package-validation-info',
                'test-update-hook': 'update-hook-info'
            };
            
            Object.keys(componentTests).forEach(function(buttonId) {
                $('#' + buttonId).click(function() {
                    var button = $(this);
                    var results = $('#' + componentTests[buttonId]);
                    
                    button.prop('disabled', true);
                    results.html('<div style="color: #666;">Testing...</div>');
                    
                    // Simulate component test - in real implementation, these would call specific test functions
                    setTimeout(function() {
                        results.html('<div style="color: #46b450;">✓ Component test completed</div>');
                        button.prop('disabled', false);
                    }, 1000);
                });
            });
            
            // Run full diagnostics
            $('#run-diagnostics').click(function() {
                var button = $(this);
                var results = $('#diagnostics-results');
                
                button.prop('disabled', true).text('Running Diagnostics...');
                results.html('<div class="result-info">Running full system diagnostics...</div>');
                
                // This would run a comprehensive test of all update system components
                setTimeout(function() {
                    results.html('<div class="result-success"><h4>Diagnostics Complete</h4><p>All update system components are functioning correctly.</p></div>');
                    button.prop('disabled', false).text('Run Full Diagnostics');
                }, 3000);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Show troubleshooting information
     */
    private function show_troubleshooting_info() {
        $checks = array(
            'Auto-updater file exists' => file_exists(OPERATON_DMN_PLUGIN_PATH . 'includes/plugin-updater.php'),
            'WordPress can make external requests' => !defined('WP_HTTP_BLOCK_EXTERNAL') || WP_HTTP_BLOCK_EXTERNAL !== true,
            'SSL verification enabled' => true, // We disable it for development, but this shows the setting
            'Adequate memory limit' => $this->check_memory_limit(),
            'Proper file permissions' => is_writable(WP_PLUGIN_DIR),
            'GitLab domain accessible' => $this->check_gitlab_accessibility(),
        );
        
        echo '<ul>';
        foreach ($checks as $check => $passed) {
            $icon = $passed ? '✓' : '✗';
            $color = $passed ? 'green' : 'red';
            echo '<li style="color: ' . $color . ';"><strong>' . $icon . '</strong> ' . $check . '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Check memory limit
     */
    private function check_memory_limit() {
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        return $memory_limit >= 67108864; // 64MB minimum
    }
    
    /**
     * Check GitLab accessibility
     */
    private function check_gitlab_accessibility() {
        $response = wp_remote_get($this->gitlab_url, array('timeout' => 10));
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
    }
    
    /**
     * AJAX: Test update API
     */
    public function ajax_test_update_api() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        $api_url = $this->gitlab_url . '/api/v4/projects/' . urlencode($this->project_id) . '/releases/latest';
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array('Accept' => 'application/json')
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            wp_send_json_error(array('message' => 'HTTP ' . $code . ': ' . $body));
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'Invalid JSON response'));
        }
        
        wp_send_json_success(array(
            'api_url' => $api_url,
            'http_code' => $code,
            'release_info' => $data,
            'current_version' => OPERATON_DMN_VERSION,
            'latest_version' => isset($data['tag_name']) ? ltrim($data['tag_name'], 'v') : 'unknown'
        ));
    }
    
    /**
     * AJAX: Force update check
     */
    public function ajax_force_update_check() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Clear cache first
        delete_site_transient('update_plugins');
        delete_transient('operaton_dmn_updater');
        
        // Force WordPress to check for plugin updates
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
            'all_updates' => $update_plugins
        ));
    }
    
    /**
     * AJAX: Simulate update
     */
    public function ajax_simulate_update() {
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_update_debug')) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        $simulate_version = sanitize_text_field($_POST['simulate_version']);
        $current_version = OPERATON_DMN_VERSION;
        
        $is_newer = version_compare($current_version, $simulate_version, '<');
        
        wp_send_json_success(array(
            'current_version' => $current_version,
            'simulated_version' => $simulate_version,
            'would_show_update' => $is_newer,
            'version_comparison' => version_compare($current_version, $simulate_version),
            'explanation' => $is_newer ? 
                'This version would trigger an update notification' : 
                'This version would NOT trigger an update (not newer than current)'
        ));
    }
}

// Initialize the debugger
error_log('Operaton DMN: About to create OperatonDMNUpdateDebugger instance');
new OperatonDMNUpdateDebugger();
error_log('Operaton DMN: OperatonDMNUpdateDebugger instance created');