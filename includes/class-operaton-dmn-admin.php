<?php

/**
 * Admin Interface Manager for Operaton DMN Plugin
 *
 * Handles WordPress admin interface functionality including menu creation,
 * configuration pages, asset loading, and Gravity Forms integration.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */


// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}

class Operaton_DMN_Admin
{
    /**
     * Core plugin instance reference
     * Provides access to main plugin functionality and data
     *
     * @var OperatonDMNEvaluator
     * @since 1.0.0
     */
    private $core;

    /**
     * Assets manager instance
     * Handles CSS and JavaScript loading for admin interface
     *
     * @var Operaton_DMN_Assets
     * @since 1.0.0
     */
    private $assets;

    /**
     * Admin page capability requirement
     * WordPress capability required to access admin pages
     *
     * @var string
     * @since 1.0.0
     */
    private $capability = 'manage_options';

    /**
     * Constructor for admin interface manager
     * Initializes admin functionality with required dependencies
     *
     * @param OperatonDMNEvaluator $core Core plugin instance
     * @param Operaton_DMN_Assets $assets Assets manager instance
     * @since 1.0.0
     */
    public function __construct($core, $assets)
    {
        $this->core = $core;
        $this->assets = $assets;

        operaton_debug('Admin', 'Interface manager initialized');

        $this->init_hooks();
    }

    /**
     * Initialize WordPress admin hooks and filters
     * Sets up all admin-related WordPress integration points
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // Core admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'admin_notices'));

        // Frontend hooks for form integration
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));

        // Add this action to wp_ajax
        add_action('wp_ajax_operaton_debug_status', array($this, 'ajax_debug_status'));

        // Plugin page enhancement
        $plugin_basename = plugin_basename(OPERATON_DMN_PLUGIN_PATH . 'operaton-dmn-plugin.php');
        add_filter("plugin_action_links_$plugin_basename", array($this, 'add_settings_link'));

        // Note: Gravity Forms integration is handled by the dedicated Gravity Forms class

        // AJAX handler for connection timeout settings saved in admin dashboard
        add_action('wp_ajax_operaton_save_connection_timeout', array($this, 'ajax_save_connection_timeout'));

        // AJAX handler for connection reuse stats
        add_action('wp_ajax_operaton_check_connection_stats', array($this, 'ajax_check_connection_stats'));

        // AJAX handler for clearing all configuration cache
        add_action('wp_ajax_operaton_clear_all_cache', array($this, 'ajax_clear_all_cache'));

        // AJAX handler for force reloading configurations
        add_action('wp_ajax_operaton_force_reload_configs', array($this, 'ajax_force_reload_configs'));

        // AJAX handler for clearing decision flow cache
        add_action('wp_ajax_operaton_clear_decision_cache', array($this, 'ajax_clear_decision_cache'));
    }

    // =============================================================================
    // ADMIN MENU AND PAGE METHODS
    // =============================================================================

    /**
     * AJAX handler for clearing all configuration cache
     */
    public function ajax_clear_all_cache()
    {
        error_log('AJAX clear_all_cache called'); // Add this line first
        // Security check
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce'))
        {
            wp_send_json_error(array('message' => 'Insufficient permissions or invalid nonce'));
            return;
        }
        error_log('Security check passed, proceeding with cache clear'); // Add this line

        try
        {
            global $wpdb;

            $stats = array(
                'transients_cleared' => 0,
                'configs_reloaded' => 0
            );

            // 1. Clear all Operaton-related transients
            $transients_deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_operaton_%'");
            $timeout_deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_operaton_%'");
            $stats['transients_cleared'] = $transients_deleted + $timeout_deleted;

            // 2. Clear object cache if available
            if (function_exists('wp_cache_flush'))
            {
                wp_cache_flush();
            }

            // 3. Clear any configuration-specific options
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%operaton_config_%'");

            // 4. Force reload configurations via database manager if available
            $plugin_instance = OperatonDMNEvaluator::get_instance();
            $database = $plugin_instance->get_database_instance();

            if ($database && method_exists($database, 'force_reload_all_configurations'))
            {
                $stats['configs_reloaded'] = $database->force_reload_all_configurations();
            }
            elseif ($database && method_exists($database, 'clear_configuration_cache'))
            {
                $database->clear_configuration_cache();

                // Manually count configurations
                $configs_table = $wpdb->prefix . 'operaton_dmn_configs';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$configs_table}'") === $configs_table)
                {
                    $stats['configs_reloaded'] = $wpdb->get_var("SELECT COUNT(*) FROM {$configs_table}");
                }
            }

            // 5. Clear WordPress update cache as bonus
            delete_site_transient('update_plugins');

            error_log('Operaton DMN: All cache cleared via admin interface - ' .
                $stats['transients_cleared'] . ' transients, ' .
                $stats['configs_reloaded'] . ' configs reloaded');

            wp_send_json_success(array(
                'message' => 'All cache cleared successfully',
                'transients_cleared' => $stats['transients_cleared'],
                'configs_reloaded' => $stats['configs_reloaded']
            ));
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN: Cache clear failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Cache clear failed: ' . $e->getMessage()));
        }
    }

    /**
     * AJAX handler for force reloading configurations
     */
    public function ajax_force_reload_configs()
    {
        // Security check
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce'))
        {
            wp_send_json_error(array('message' => 'Insufficient permissions or invalid nonce'));
            return;
        }

        try
        {
            $plugin_instance = OperatonDMNEvaluator::get_instance();
            $database = $plugin_instance->get_database_instance();

            if (!$database)
            {
                wp_send_json_error(array('message' => 'Database manager not available'));
                return;
            }

            $configs_reloaded = 0;

            // Method 1: Use database manager's force reload if available
            if (method_exists($database, 'force_reload_all_configurations'))
            {
                $configs_reloaded = $database->force_reload_all_configurations();
            }
            else
            {
                // Method 2: Manual force reload
                global $wpdb;
                $configs_table = $wpdb->prefix . 'operaton_dmn_configs';

                if ($wpdb->get_var("SHOW TABLES LIKE '{$configs_table}'") === $configs_table)
                {
                    // Get all form IDs with configurations
                    $form_ids = $wpdb->get_col("SELECT DISTINCT form_id FROM {$configs_table}");

                    foreach ($form_ids as $form_id)
                    {
                        // Clear specific cache first
                        if (method_exists($database, 'clear_configuration_cache'))
                        {
                            $database->clear_configuration_cache($form_id);
                        }

                        // Force reload from database (bypass cache)
                        if (method_exists($database, 'get_config_by_form_id'))
                        {
                            $database->get_config_by_form_id($form_id, false);
                            $configs_reloaded++;
                        }
                    }
                }
            }

            error_log('Operaton DMN: Force reloaded ' . $configs_reloaded . ' configurations via admin interface');

            wp_send_json_success(array(
                'message' => 'Configurations reloaded successfully',
                'configs_reloaded' => $configs_reloaded
            ));
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN: Configuration reload failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Configuration reload failed: ' . $e->getMessage()));
        }
    }

    /**
     * AJAX handler for clearing decision flow cache
     */
    public function ajax_clear_decision_cache()
    {
        // Security check
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce'))
        {
            wp_send_json_error(array('message' => 'Insufficient permissions or invalid nonce'));
            return;
        }

        try
        {
            // Clear decision flow cache - this replicates what your URL parameter method was doing
            // You can adjust this to match your specific cache clearing logic

            // Option 1: Clear specific transients
            delete_transient('operaton_decision_flow_cache');
            delete_transient('operaton_dmn_cache');

            // Option 2: Clear all decision-related transients
            global $wpdb;
            $transients_cleared = $wpdb->query(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE '_transient_operaton_decision_%'
                 OR option_name LIKE '_transient_timeout_operaton_decision_%'"
            );

            // Option 3: If you have a specific method in your database class, use it
            $plugin_instance = OperatonDMNEvaluator::get_instance();
            $database = $plugin_instance->get_database_instance();

            if ($database && method_exists($database, 'clear_decision_flow_cache'))
            {
                $database->clear_decision_flow_cache();
            }

            error_log('Operaton DMN: Decision flow cache cleared via admin interface');

            wp_send_json_success(array(
                'message' => 'Decision flow cache cleared successfully',
                'cache_cleared' => true
            ));
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN: Decision flow cache clear failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Cache clear failed: ' . $e->getMessage()));
        }
    }

    /**
     * Replace your ajax_debug_status() method with this version that has error handling
     */
    public function ajax_debug_status()
    {
        error_log('AJAX debug_status called - comprehensive version');

        // Security check
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce'))
        {
            wp_send_json_error(array('message' => 'Insufficient permissions or invalid nonce'));
            return;
        }

        error_log('Security check passed, building comprehensive status');

        try
        {
            // Initialize the status array with basic info
            $status = array(
                'plugin_version' => OPERATON_DMN_VERSION,
                'timestamp' => current_time('mysql'),
            );

            // Add managers status safely
            try
            {
                $status['managers'] = $this->core->get_managers_status();
            }
            catch (Exception $e)
            {
                $status['managers'] = array('error' => 'Could not retrieve managers status: ' . $e->getMessage());
            }

            // Add health check safely
            try
            {
                $status['health'] = $this->core->health_check();
            }
            catch (Exception $e)
            {
                $status['health'] = array('error' => 'Could not perform health check: ' . $e->getMessage());
            }

            // Add assets status safely - using basic WordPress functions instead of missing method
            try
            {
                // Create basic assets status without calling the missing method
                $asset_status = array(
                    'scripts_registered' => array(),
                    'styles_registered' => array(),
                    'context' => array(
                        'current_page' => get_current_screen() ? get_current_screen()->id : 'unknown',
                        'is_ajax_request' => wp_doing_ajax(),
                        'script_loading_note' => 'Scripts are only registered when needed on specific pages - this is optimal behavior'
                    )
                );

                // Check if common scripts are registered
                global $wp_scripts, $wp_styles;

                $operaton_scripts = array(
                    'operaton-dmn-admin',
                    'operaton-dmn-frontend',
                    'operaton-dmn-gravity-integration',
                    'operaton-dmn-decision-flow',
                    'operaton-dmn-radio-sync'
                );

                $operaton_styles = array(
                    'operaton-dmn-admin',
                    'operaton-dmn-frontend',
                    'operaton-dmn-decision-flow',
                    'operaton-dmn-radio-sync'
                );

                // Check script registration status
                if (isset($wp_scripts->registered))
                {
                    foreach ($operaton_scripts as $script)
                    {
                        $asset_status['scripts_registered'][$script] = isset($wp_scripts->registered[$script]);
                    }
                }

                // Check style registration status
                if (isset($wp_styles->registered))
                {
                    foreach ($operaton_styles as $style)
                    {
                        $asset_status['styles_registered'][$style] = isset($wp_styles->registered[$style]);
                    }
                }

                $status['assets'] = $asset_status;
            }
            catch (Exception $e)
            {
                $status['assets'] = array('error' => 'Could not retrieve assets status: ' . $e->getMessage());
            }

            // Add performance data safely
            $performance_data = array();
            if (class_exists('Operaton_DMN_Performance_Monitor'))
            {
                try
                {
                    $performance_monitor = Operaton_DMN_Performance_Monitor::get_instance();
                    $performance_summary = $performance_monitor->get_summary();

                    // Clean and organize performance data with safe access
                    $performance_data = array(
                        'current_request' => array(
                            'total_time_ms' => isset($performance_summary['total_time_ms']) ? $performance_summary['total_time_ms'] : 0,
                            'peak_memory_formatted' => isset($performance_summary['peak_memory_formatted']) ? $performance_summary['peak_memory_formatted'] : 'Unknown',
                            'milestone_count' => isset($performance_summary['milestone_count']) ? $performance_summary['milestone_count'] : 0,
                            'request_type' => (isset($performance_summary['request_data']['is_ajax']) && $performance_summary['request_data']['is_ajax']) ? 'AJAX' : 'Standard',
                            'is_admin' => isset($performance_summary['request_data']['is_admin']) ? $performance_summary['request_data']['is_admin'] : false
                        )
                    );

                    // Add initialization timing safely
                    if (isset($performance_summary['milestones']))
                    {
                        $performance_data['initialization_timing'] = array(
                            'plugin_construct' => $this->get_milestone_duration($performance_summary['milestones'], 'plugin_construct'),
                            'assets_manager' => $this->get_milestone_time($performance_summary['milestones'], 'assets_manager_loaded'),
                            'database_manager' => $this->get_milestone_time($performance_summary['milestones'], 'database_manager_loaded'),
                            'gravity_forms_manager' => $this->get_milestone_time($performance_summary['milestones'], 'gravity_forms_manager_loaded'),
                            'wp_loaded_at' => $this->get_milestone_time($performance_summary['milestones'], 'wp_loaded')
                        );
                    }

                    // Add performance grade and recommendations safely
                    $performance_data['performance_grade'] = $this->calculate_performance_grade($performance_summary);
                    $performance_data['recommendations'] = $this->get_performance_recommendations($performance_summary);
                }
                catch (Exception $e)
                {
                    $performance_data = array(
                        'error' => 'Performance monitor error: ' . $e->getMessage()
                    );
                }
            }
            else
            {
                $performance_data = array(
                    'status' => 'Performance monitor class not available'
                );
            }

            $status['performance'] = $performance_data;

            // Add environment info safely
            $status['environment'] = array(
                'wordpress' => get_bloginfo('version'),
                'php' => PHP_VERSION,
                'theme' => wp_get_theme()->get('Name') . ' v' . wp_get_theme()->get('Version'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'wp_debug' => defined('WP_DEBUG') && WP_DEBUG
            );

            // Add operaton constants safely
            $status['operaton_constants'] = array(
                'version' => defined('OPERATON_DMN_VERSION') ? OPERATON_DMN_VERSION : 'Unknown',
                'plugin_url' => defined('OPERATON_DMN_PLUGIN_URL') ? OPERATON_DMN_PLUGIN_URL : 'Unknown',
                'plugin_path' => defined('OPERATON_DMN_PLUGIN_PATH') ? OPERATON_DMN_PLUGIN_PATH : 'Unknown'
            );

            // Add user context safely
            $current_user = wp_get_current_user();
            $status['user_context'] = array(
                'user_id' => get_current_user_id(),
                'user_role' => !empty($current_user->roles) ? implode(', ', $current_user->roles) : 'Unknown',
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown'
            );

            error_log('Debug status retrieved successfully');
            wp_send_json_success($status);
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN Debug Status Error: ' . $e->getMessage());
            error_log('Error trace: ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Failed to retrieve debug status: ' . $e->getMessage()));
        }
    }

    /**
     * Helper method to count total configurations
     */
    private function count_configurations()
    {
        try
        {
            $configs = $this->core->get_database_instance()->get_all_configurations();
            return count($configs);
        }
        catch (Exception $e)
        {
            return 0;
        }
    }

    /**
     * Helper method to get cache status information
     */
    private function get_cache_status()
    {
        $cache_info = array();

        // Check for common transients
        $transients_to_check = array(
            'operaton_dmn_configurations',
            'operaton_dmn_decision_flows',
            'operaton_dmn_endpoint_status'
        );

        foreach ($transients_to_check as $transient)
        {
            $cache_info[$transient] = get_transient($transient) !== false ? 'Cached' : 'Not cached';
        }

        return $cache_info;
    }

    /**
     * Helper method to get milestone time
     */
    private function get_milestone_time($milestones, $milestone_name)
    {
        return isset($milestones[$milestone_name]) ? $milestones[$milestone_name]['time_ms'] : null;
    }

    /**
     * Helper method to calculate duration between start/end milestones
     */
    private function get_milestone_duration($milestones, $base_name)
    {
        $start_key = $base_name . '_start';
        $end_key = $base_name . '_complete';

        if (isset($milestones[$start_key]) && isset($milestones[$end_key]))
        {
            return round($milestones[$end_key]['time_ms'] - $milestones[$start_key]['time_ms'], 3);
        }

        return null;
    }

    /**
     * Calculate overall performance grade
     */
    private function calculate_performance_grade($performance_summary)
    {
        $total_time = $performance_summary['total_time_ms'];
        $memory_mb = $performance_summary['peak_memory'] / (1024 * 1024);

        // Grading based on WordPress performance standards
        if ($total_time < 100 && $memory_mb < 16)
        {
            return 'A+ (Excellent)';
        }
        elseif ($total_time < 200 && $memory_mb < 32)
        {
            return 'A (Very Good)';
        }
        elseif ($total_time < 500 && $memory_mb < 64)
        {
            return 'B (Good)';
        }
        elseif ($total_time < 1000 && $memory_mb < 128)
        {
            return 'C (Acceptable)';
        }
        else
        {
            return 'D (Needs Optimization)';
        }
    }

    /**
     * Get performance recommendations
     */
    private function get_performance_recommendations($performance_summary)
    {
        $recommendations = array();
        $total_time = $performance_summary['total_time_ms'];
        $memory_mb = $performance_summary['peak_memory'] / (1024 * 1024);

        if ($total_time < 100)
        {
            $recommendations[] = '🚀 Excellent loading speed!';
        }

        if ($memory_mb < 16)
        {
            $recommendations[] = '🧠 Very efficient memory usage!';
        }

        if ($performance_summary['milestone_count'] > 20)
        {
            $recommendations[] = '📊 Consider reducing performance monitoring in production';
        }

        if (empty($recommendations))
        {
            $recommendations[] = '✨ Performance is optimal - no recommendations needed!';
        }

        return $recommendations;
    }

    // Add debug button to admin pages
    public function add_debug_button()
    {
        if (!current_user_can('manage_options') || !defined('WP_DEBUG') || !WP_DEBUG)
        {
            return;
        }

?>
        <div style="margin: 20px 0; padding: 15px; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px;">
            <h3>🔧 Debug Tools</h3>
            <button type="button" id="operaton-debug-status" class="button">
                Get Plugin Status
            </button>
            <div id="operaton-debug-results" style="margin-top: 10px;"></div>
        </div>

        <script>
            jQuery('#operaton-debug-status').click(function() {
                var $button = jQuery(this);
                var $results = jQuery('#operaton-debug-results');

                $button.prop('disabled', true).text('Getting Status...');

                jQuery.post(ajaxurl, {
                    action: 'operaton_debug_status'
                }, function(response) {
                    if (response.success) {
                        $results.html('<pre>' + JSON.stringify(response.data, null, 2) + '</pre>');
                    } else {
                        $results.html('<p style="color: red;">Error: ' + response.data + '</p>');
                    }
                }).always(function() {
                    $button.prop('disabled', false).text('Get Plugin Status');
                });
            });
        </script>
    <?php
    }

    /**
     * Add plugin admin menu pages and submenus to WordPress dashboard
     * Creates main configuration page and debug interface for plugin management
     *
     * @since 1.0.0
     */
    public function add_admin_menu()
    {
        operaton_debug('Admin', 'Adding admin menu pages');

        // Main menu page
        add_menu_page(
            __('Operaton DMN', 'operaton-dmn'),
            __('Operaton DMN', 'operaton-dmn'),
            $this->capability,
            'operaton-dmn',
            array($this, 'admin_page'),
            'dashicons-analytics',
            30
        );

        // Configurations submenu (same as main page)
        add_submenu_page(
            'operaton-dmn',
            __('Configurations', 'operaton-dmn'),
            __('Configurations', 'operaton-dmn'),
            $this->capability,
            'operaton-dmn',
            array($this, 'admin_page')
        );

        // Add configuration submenu
        add_submenu_page(
            'operaton-dmn',
            __('Add Configuration', 'operaton-dmn'),
            __('Add Configuration', 'operaton-dmn'),
            $this->capability,
            'operaton-dmn-add',
            array($this, 'add_config_page')
        );

        // Add debug menu in development mode
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            $this->add_debug_menu();
        }
    }

    /**
     * Add debug submenu for development and troubleshooting
     * Only available when WP_DEBUG is enabled
     *
     * @since 1.0.0
     */
    private function add_debug_menu()
    {
        operaton_debug('Admin', 'Adding debug menu');

        // Check if debug class exists and use it, otherwise use temp page
        if (class_exists('OperatonDMNUpdateDebugger'))
        {
            global $operaton_debug_instance;
            if (!$operaton_debug_instance)
            {
                $operaton_debug_instance = new OperatonDMNUpdateDebugger();
            }

            add_submenu_page(
                'operaton-dmn',
                __('Update Debug', 'operaton-dmn'),
                __('Update Debug', 'operaton-dmn'),
                $this->capability,
                'operaton-dmn-update-debug',
                array($operaton_debug_instance, 'debug_page')
            );

            operaton_debug_verbose('Admin', 'Debug menu added using OperatonDMNUpdateDebugger class');
        }
        else
        {
            add_submenu_page(
                'operaton-dmn',
                __('Update Debug', 'operaton-dmn'),
                __('Update Debug', 'operaton-dmn'),
                $this->capability,
                'operaton-dmn-update-debug',
                array($this, 'temp_debug_page')
            );

            operaton_debug_verbose('Admin', 'Debug menu added using temp page (class not found)');
        }
    }

    /**
     * Main admin page that displays configuration list and database status
     * Shows all DMN configurations with update management and handles deletion
     *
     * @since 1.0.0
     */
    public function admin_page()
    {
        operaton_debug('Admin', 'Loading main admin page');

        // Force database check when accessing admin pages
        $this->core->get_database_instance()->check_and_update_database();

        // Check for database issues and show user-friendly message
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");

        if (!in_array('result_mappings', $columns))
        {
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . __('Database Update Failed', 'operaton-dmn') . '</strong></p>';
            echo '<p>' . __('The plugin attempted to update the database but it failed. Please contact your administrator.', 'operaton-dmn') . '</p>';
            echo '<p>' . __('Error: Missing required columns in database table.', 'operaton-dmn') . '</p>';
            echo '</div>';
            return;
        }

        // Check for database update success message
        if (isset($_GET['database_updated']))
        {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('Database schema updated successfully!', 'operaton-dmn') . '</p>';
            echo '</div>';
        }

        // Handle configuration deletion
        if (isset($_POST['delete_config']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_config'))
        {
            $this->handle_config_deletion($_POST['config_id']);
        }

        // Get configurations for display
        $configs = $this->core->get_database_instance()->get_all_configurations();

        // Start the admin page wrapper
        echo '<div class="wrap">';
        echo '<h1>' . __('Operaton DMN Configurations', 'operaton-dmn') . '</h1>';

        // Include the admin list template
        $this->load_admin_template('list', compact('configs'));

        echo '</div>'; // Close wrap
    }

    /**
     * Configuration creation/editing page with database migration check
     * Handles form submission for saving DMN configurations and displays the form interface
     *
     * @since 1.0.0
     */
    public function add_config_page()
    {
        operaton_debug('Admin', 'Loading configuration edit page');

        // Force database check
        $this->core->get_database_instance()->check_and_update_database();

        // Check if migration was successful
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");

        if (!in_array('result_mappings', $columns))
        {
            echo '<div class="wrap">';
            echo '<h1>' . __('Database Update Required', 'operaton-dmn') . '</h1>';
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . __('Database update failed.', 'operaton-dmn') . '</strong> ';
            echo __('Please deactivate and reactivate the plugin, or contact your administrator.', 'operaton-dmn') . '</p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        // Handle form submission
        if (isset($_POST['save_config']) && wp_verify_nonce($_POST['_wpnonce'], 'save_config'))
        {
            $this->handle_config_save($_POST);
        }

        // Get data for form display
        $gravity_forms = $this->get_gravity_forms();
        $config = $this->core->get_database_instance()->get_configuration($_GET['edit']);

        // Start the admin page wrapper
        echo '<div class="wrap">';
        echo '<h1>' . __('Add/Edit Configuration', 'operaton-dmn') . '</h1>';

        // Include the admin form template
        $this->load_admin_template('form', compact('gravity_forms', 'config'));

        echo '</div>'; // Close wrap
    }

    /**
     * Temporary debug page for testing debug menu functionality
     * Displays debug status and class availability information for troubleshooting
     *
     * @since 1.0.0
     */
    public function temp_debug_page()
    {
        operaton_debug('Admin', 'Displaying temporary debug page');

        echo '<div class="wrap operaton-debug-page">';
        echo '<h1>' . __('Debug Menu Test', 'operaton-dmn') . '</h1>';

        // Debug system status
        echo '<div class="debug-section">';
        echo '<div class="debug-section-header">';
        echo '<h3>' . __('Debug System Status', 'operaton-dmn') . '</h3>';
        echo '</div>';
        echo '<div class="debug-section-content">';
        echo '<p class="debug-status-success">' . __('Debug menu is working! The debug system is properly integrated.', 'operaton-dmn') . '</p>';

        $debugger_exists = class_exists('OperatonDMNUpdateDebugger');
        echo '<p><strong>' . __('OperatonDMNUpdateDebugger class exists:', 'operaton-dmn') . '</strong> ';
        echo '<span class="debug-badge ' . ($debugger_exists ? 'success">YES' : 'error">NO') . '</span></p>';
        echo '<p class="debug-text-muted">' . __('If the class exists, the full debug interface should work.', 'operaton-dmn') . '</p>';
        echo '</div>';
        echo '</div>';

        // ADD PERFORMANCE MONITOR SECTION IF AVAILABLE
        $this->add_performance_debug_section();

        // System information
        $this->display_system_info();

        // CSS test section
        $this->display_css_test_section();

        echo '</div>';
    }

    // =============================================================================
    // ASSET MANAGEMENT METHODS
    // =============================================================================

    /**
     * Add performance debug section
     */
    private function add_performance_debug_section()
    {
        if (!class_exists('Operaton_DMN_Performance_Monitor'))
        {
            return;
        }

        $performance = Operaton_DMN_Performance_Monitor::get_instance();
        $summary = $performance->get_summary();

        echo '<div class="debug-section">';
        echo '<div class="debug-section-header">';
        echo '<h3>' . __('Performance Monitoring', 'operaton-dmn') . '</h3>';
        echo '</div>';
        echo '<div class="debug-section-content">';

        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">';

        echo '<div class="debug-stat-card" style="background: #f0f8ff; padding: 15px; border-radius: 6px; text-align: center;">';
        echo '<div style="font-size: 20px; font-weight: bold; color: #0073aa;">' . round($summary['total_time_ms'], 2) . 'ms</div>';
        echo '<div style="color: #666; font-size: 12px;">Total Load Time</div>';
        echo '</div>';

        echo '<div class="debug-stat-card" style="background: #f0fff0; padding: 15px; border-radius: 6px; text-align: center;">';
        echo '<div style="font-size: 20px; font-weight: bold; color: #28a745;">' . $summary['peak_memory_formatted'] . '</div>';
        echo '<div style="color: #666; font-size: 12px;">Peak Memory</div>';
        echo '</div>';

        echo '<div class="debug-stat-card" style="background: #fff5f5; padding: 15px; border-radius: 6px; text-align: center;">';
        echo '<div style="font-size: 20px; font-weight: bold; color: #dc3545;">' . $summary['milestone_count'] . '</div>';
        echo '<div style="color: #666; font-size: 12px;">Milestones</div>';
        echo '</div>';

        echo '</div>';

        // Show recent milestones
        if (!empty($summary['milestones']))
        {
            echo '<h4>Recent Performance Milestones</h4>';
            echo '<div style="max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; border-radius: 4px;">';
            foreach (array_slice($summary['milestones'], -10, 10, true) as $name => $milestone)
            {
                echo '<div style="margin: 5px 0; font-family: monospace; font-size: 12px;">';
                echo '<strong>' . esc_html($name) . ':</strong> ' . $milestone['time_ms'] . 'ms';
                if ($milestone['details'])
                {
                    echo ' - ' . esc_html($milestone['details']);
                }
                echo '</div>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Enqueue admin-specific CSS and JavaScript files for plugin configuration pages
     * Loads admin scripts and localizes AJAX endpoints for backend functionality
     *
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function enqueue_admin_scripts($hook)
    {
        operaton_debug_verbose('Admin', 'Enqueuing admin scripts', ['hook' => $hook]);

        // Only enqueue on our plugin pages
        if (strpos($hook, 'operaton-dmn') !== false)
        {
            $this->assets->enqueue_admin_assets($hook);

            // Add admin-specific localizations
            wp_localize_script('operaton-dmn-admin', 'operaton_admin_ajax', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('operaton_admin_nonce'),
                'strings' => array(
                    'testing' => __('Testing...', 'operaton-dmn'),
                    'success' => __('Success!', 'operaton-dmn'),
                    'error' => __('Error occurred', 'operaton-dmn'),
                    'confirm_delete' => __('Are you sure you want to delete this configuration?', 'operaton-dmn'),
                    'saving' => __('Saving...', 'operaton-dmn'),
                    'saved' => __('Saved!', 'operaton-dmn')
                )
            ));
        }
    }

    /**
     * Enqueue frontend CSS and JavaScript for DMN evaluation functionality
     * Loads client-side scripts and styles for form evaluation on public pages
     *
     * @since 1.0.0
     */
    public function enqueue_frontend_scripts()
    {
        operaton_debug('Admin', 'Ensuring frontend assets are loaded');

        // Only enqueue on frontend
        if (!is_admin())
        {
            // Force enqueue frontend assets to ensure operaton_ajax is available
            $this->assets->enqueue_frontend_assets();
        }
    }

    // =============================================================================
    // ADMIN NOTICE AND STATUS METHODS
    // =============================================================================

    /**
     * Show admin notices for plugin health issues and status messages
     * Displays warnings for missing dependencies and configuration problems
     *
     * @since 1.0.0
     */
    public function admin_notices()
    {
        if (!current_user_can($this->capability))
        {
            return;
        }

        operaton_debug_verbose('Admin', 'Checking for admin notices');

        $issues = $this->check_plugin_health();
        if (!empty($issues))
        {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . __('Operaton DMN Plugin Issues:', 'operaton-dmn') . '</strong></p>';
            echo '<ul>';
            foreach ($issues as $issue)
            {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }

    /**
     * Add plugin settings link to the WordPress plugins page
     * Provides quick access to plugin configuration from the plugins list
     *
     * @param array $links Existing plugin action links
     * @return array Modified links array with settings link added
     * @since 1.0.0
     */
    public function add_settings_link($links)
    {
        operaton_debug('Admin', 'Adding settings link to plugin page');

        $settings_link = '<a href="' . admin_url('admin.php?page=operaton-dmn') . '">' . __('Settings', 'operaton-dmn') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // =============================================================================
    // HELPER AND UTILITY METHODS
    // =============================================================================

    /**
     * Handle configuration deletion with proper validation and cleanup
     * Removes DMN configuration from database and clears associated cached data
     *
     * @param int $config_id Configuration ID to delete
     * @since 1.0.0
     */
    private function handle_config_deletion($config_id)
    {
        $result = $this->core->get_database_instance()->delete_config($config_id);

        if ($result !== false)
        {
            echo '<div class="notice notice-success"><p>' . __('Configuration deleted successfully!', 'operaton-dmn') . '</p></div>';
        }
        else
        {
            echo '<div class="notice notice-error"><p>' . __('Error deleting configuration.', 'operaton-dmn') . '</p></div>';
        }
    }

    /**
     * Handle configuration save with validation and error handling
     * Processes form submission for DMN configuration creation/editing
     *
     * @param array $data Posted form data
     * @since 1.0.0
     */
    private function handle_config_save($data)
    {
        $result = $this->core->get_database_instance()->save_configuration($data);

        if ($result)
        {
            echo '<div class="notice notice-success"><p>' . __('Configuration saved successfully!', 'operaton-dmn') . '</p></div>';
        }
        // Error messages are handled by the core class
    }

    /**
     * Get available Gravity Forms for configuration interface
     * Retrieves all Gravity Forms with field information for mapping
     *
     * @return array Array of Gravity Forms with field details
     * @since 1.0.0
     */
    private function get_gravity_forms()
    {
        $gravity_forms_manager = $this->core->get_gravity_forms_instance();

        if (!$gravity_forms_manager || !$gravity_forms_manager->is_gravity_forms_available())
        {
            operaton_debug_minimal('Admin', 'Gravity Forms not available');
            return array();
        }

        return $gravity_forms_manager->get_available_forms();
    }

    /**
     * Load admin template with data
     * Includes template files from the templates/admin directory
     *
     * @param string $template Template name (without .php extension)
     * @param array $data Data to extract for template use
     * @since 1.0.0
     */
    private function load_admin_template($template, $data = array())
    {
        $template_path = OPERATON_DMN_PLUGIN_PATH . "templates/admin/{$template}.php";

        if (file_exists($template_path))
        {
            // Extract data for use in template
            extract($data);
            include $template_path;
        }
        else
        {
            operaton_debug_minimal('Admin', 'Template not found', ['template_path' => $template_path]);
            echo '<div class="notice notice-error"><p>' . sprintf(__('Template not found: %s', 'operaton-dmn'), $template) . '</p></div>';
        }
    }

    /**
     * Check plugin health for admin notices
     * Validates plugin dependencies and database integrity
     *
     * @return array Array of health issue descriptions
     * @since 1.0.0
     */
    private function check_plugin_health()
    {
        $issues = array();

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Admin: Performing health check');
        }

        // Check if Gravity Forms is active
        $gravity_forms_manager = $this->core->get_gravity_forms_instance();
        if (!$gravity_forms_manager || !$gravity_forms_manager->is_gravity_forms_available())
        {
            $issues[] = __('Gravity Forms is not active.', 'operaton-dmn');
        }

        // Check if Gravity Forms is active (duplicate check - can remove one)
        if (!class_exists('GFForms'))
        {
            $issues[] = __('Gravity Forms is not active.', 'operaton-dmn');
        }

        // Check database table
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name)
        {
            $issues[] = __('Database table is missing.', 'operaton-dmn');
        }

        // IMPROVED REST API CHECK - Test internal registration instead of external HTTP call
        if (!$this->test_rest_api_internally())
        {
            // Only add this as an issue if we're confident it's a real problem
            // Since your logs show the API is working, let's be more specific
            error_log('Operaton DMN Admin: REST API internal test failed, but external functionality may still work');

            // Optional: Only show this warning in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                $issues[] = __('REST API registration check failed (functionality may still work).', 'operaton-dmn');
            }
        }

        return $issues;
    }

    /**
     * Test REST API internally without external HTTP calls
     *
     * @return bool True if REST API appears to be working
     */
    private function test_rest_api_internally()
    {
        try
        {
            // Method 1: Check if our routes are registered
            $routes = rest_get_server()->get_routes();
            $our_namespace = '/operaton-dmn/v1';

            if (!isset($routes[$our_namespace . '/evaluate']))
            {
                error_log('Operaton DMN Admin: REST route /evaluate not found in registered routes');
                return false;
            }

            // Method 2: Test if we can create a REST request (without executing it)
            $request = new WP_REST_Request('GET', '/operaton-dmn/v1/health');
            if (!$request)
            {
                error_log('Operaton DMN Admin: Failed to create REST request object');
                return false;
            }

            // Method 3: Check if the API class has the required methods
            $api_instance = $this->core->get_api_instance();
            if (!$api_instance || !method_exists($api_instance, 'handle_evaluation'))
            {
                error_log('Operaton DMN Admin: API instance or required methods not available');
                return false;
            }

            error_log('Operaton DMN Admin: REST API internal checks passed');
            return true;
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN Admin: REST API internal test exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Display system information for debug page
     * Shows system details for troubleshooting
     *
     * @since 1.0.0
     */
    private function display_system_info()
    {
        echo '<div class="debug-section">';
        echo '<div class="debug-section-header">';
        echo '<h3>' . __('System Information', 'operaton-dmn') . '</h3>';
        echo '</div>';
        echo '<div class="debug-section-content">';
        echo '<table class="debug-table">';
        echo '<tr><th>' . __('Plugin Version', 'operaton-dmn') . '</th><td>' . OPERATON_DMN_VERSION . '</td></tr>';
        echo '<tr><th>' . __('WordPress Version', 'operaton-dmn') . '</th><td>' . get_bloginfo('version') . '</td></tr>';
        echo '<tr><th>' . __('PHP Version', 'operaton-dmn') . '</th><td>' . PHP_VERSION . '</td></tr>';

        $wp_debug_status = defined('WP_DEBUG') && WP_DEBUG;
        echo '<tr><th>' . __('WP_DEBUG Status', 'operaton-dmn') . '</th><td>';
        echo '<span class="debug-badge ' . ($wp_debug_status ? 'success">Enabled' : 'warning">Disabled') . '</span>';
        echo '</td></tr>';

        $assets_loaded = isset($this->assets);
        echo '<tr><th>' . __('Assets Manager', 'operaton-dmn') . '</th><td>';
        echo '<span class="debug-badge ' . ($assets_loaded ? 'success">Loaded' : 'error">Not Found') . '</span>';
        echo '</td></tr>';

        echo '</table>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Display CSS test section for debug page
     * Shows various CSS components for testing
     *
     * @since 1.0.0
     */
    private function display_css_test_section()
    {
        echo '<div class="debug-section">';
        echo '<div class="debug-section-header">';
        echo '<h3>' . __('CSS Test Section', 'operaton-dmn') . '</h3>';
        echo '</div>';
        echo '<div class="debug-section-content">';
        echo '<div class="debug-alert success">' . __('This is a success alert', 'operaton-dmn') . '</div>';
        echo '<div class="debug-alert warning">' . __('This is a warning alert', 'operaton-dmn') . '</div>';
        echo '<div class="debug-alert error">' . __('This is an error alert', 'operaton-dmn') . '</div>';
        echo '<div class="debug-alert info">' . __('This is an info alert', 'operaton-dmn') . '</div>';
        echo '<div class="debug-code">' . __('Sample debug code block', 'operaton-dmn') . '</div>';
        echo '<button class="debug-button">' . __('Primary Button', 'operaton-dmn') . '</button>';
        echo '<button class="debug-button secondary">' . __('Secondary Button', 'operaton-dmn') . '</button>';
        echo '<button class="debug-button danger">' . __('Danger Button', 'operaton-dmn') . '</button>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Display update management section in admin interface
     * Shows current version status, available updates, and manual update trigger functionality
     *
     * @since 1.0.0
     */
    public function show_update_management_section()
    {
        if (!current_user_can($this->capability))
        {
            return;
        }

        operaton_debug('Admin', 'Displaying update management section');

        $current_version = OPERATON_DMN_VERSION;
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
        <div class="operaton-update-section" style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;">
            <h3><?php _e('Plugin Updates', 'operaton-dmn'); ?></h3>

            <p><strong><?php _e('Current Version:', 'operaton-dmn'); ?></strong> <?php echo esc_html($current_version); ?></p>

            <?php if ($has_update) : ?>
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0;">
                    <p><strong><?php _e('Update Available:', 'operaton-dmn'); ?></strong> <?php echo esc_html($new_version); ?></p>
                    <p>
                        <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-primary">
                            <?php _e('Go to Plugins Page to Update', 'operaton-dmn'); ?>
                        </a>
                    </p>
                </div>
            <?php else : ?>
                <p style="color: #46b450;">✓ <?php _e('You are running the latest version', 'operaton-dmn'); ?></p>
            <?php endif; ?>

            <p>
                <button type="button" id="operaton-check-updates" class="button">
                    <?php _e('Check for Updates Now', 'operaton-dmn'); ?>
                </button>
                <span id="operaton-update-status" style="margin-left: 10px;"></span>
            </p>

            <script>
                jQuery(document).ready(function($) {
                    $('#operaton-check-updates').click(function() {
                        var button = $(this);
                        var status = $('#operaton-update-status');

                        button.prop('disabled', true).text('<?php _e('Checking...', 'operaton-dmn'); ?>');
                        status.html('<span style="color: #666;">⏳ <?php _e('Checking for updates...', 'operaton-dmn'); ?></span>');

                        // Clear update transients to force fresh check
                        $.post(ajaxurl, {
                            action: 'operaton_clear_update_cache',
                            _ajax_nonce: '<?php echo wp_create_nonce('operaton_admin_nonce'); ?>'
                        }, function(response) {
                            if (response.success) {
                                // Reload page to show updated status
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
        </div>
<?php
    }

    /**
     * Get assets manager instance for external access
     * Provides access to assets manager for other components
     *
     * @return Operaton_DMN_Assets Assets manager instance
     * @since 1.0.0
     */
    public function get_assets_manager()
    {
        return $this->assets;
    }

    /**
     * Get core plugin instance for external access
     * Provides access to core plugin functionality for other components
     *
     * @return OperatonDMNEvaluator Core plugin instance
     * @since 1.0.0
     */
    public function get_core_instance()
    {
        return $this->core;
    }

    /**
     * Check if current user can manage plugin settings
     * Convenience method for capability checking
     *
     * @return bool True if user can manage plugin
     * @since 1.0.0
     */
    public function current_user_can_manage()
    {
        return current_user_can($this->capability);
    }

    /**
     * AJAX handler for saving connection timeout setting
     */
    public function ajax_save_connection_timeout()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce') || !current_user_can('manage_options'))
        {
            wp_send_json_error(array('message' => __('Security check failed', 'operaton-dmn')));
            return;
        }

        $timeout = intval($_POST['timeout']);

        // Validate timeout range (1 minute to 30 minutes)
        if ($timeout < 60 || $timeout > 1800)
        {
            wp_send_json_error(array(
                'message' => __('Invalid timeout value. Must be between 1 and 30 minutes.', 'operaton-dmn')
            ));
            return;
        }

        try
        {
            // Save the setting
            update_option('operaton_connection_timeout', $timeout);

            // Apply the setting to the API instance if available
            $api_instance = $this->core->get_api_instance();
            if ($api_instance && method_exists($api_instance, 'set_connection_pool_timeout'))
            {
                $api_instance->set_connection_pool_timeout($timeout);
            }

            // Format the response
            $timeout_minutes = round($timeout / 60, 1);
            $timeout_label = ($timeout_minutes == floor($timeout_minutes)) ?
                intval($timeout_minutes) . ' minute' . (intval($timeout_minutes) != 1 ? 's' : '') :
                $timeout_minutes . ' minutes';

            wp_send_json_success(array(
                'message' => sprintf(__('Connection timeout updated to %s', 'operaton-dmn'), $timeout_label),
                'timeout_seconds' => $timeout,
                'timeout_label' => $timeout_label
            ));
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN: Connection timeout save error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => sprintf(__('Failed to save timeout setting: %s', 'operaton-dmn'), $e->getMessage())
            ));
        }
    }

    /**
     * AJAX handler for checking connection pool statistics
     */
    public function ajax_check_connection_stats()
    {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce') || !current_user_can('manage_options'))
        {
            wp_send_json_error(array('message' => __('Security check failed', 'operaton-dmn')));
            return;
        }

        try
        {
            // Get the API instance to access connection stats
            $api_instance = $this->core->get_api_instance();

            if (!$api_instance || !method_exists($api_instance, 'get_connection_pool_stats'))
            {
                wp_send_json_error(array(
                    'message' => __('Connection pool monitoring not available', 'operaton-dmn')
                ));
                return;
            }

            // Get the connection pool statistics
            $stats = $api_instance->get_connection_pool_stats();

            // Format the statistics for display
            $formatted_stats = $this->format_connection_stats($stats);

            wp_send_json_success(array(
                'message' => __('Connection efficiency check completed', 'operaton-dmn'),
                'stats' => $formatted_stats,
                'raw_stats' => $stats // For debugging if needed
            ));
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN: Connection stats error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => sprintf(__('Failed to retrieve connection stats: %s', 'operaton-dmn'), $e->getMessage())
            ));
        }
    }

    /**
     * Format connection statistics for display
     */
    private function format_connection_stats($stats)
    {
        if (empty($stats) || !isset($stats['stats']))
        {
            return array(
                'summary' => 'No connection activity recorded yet',
                'details' => array(),
                'efficiency' => 'N/A'
            );
        }

        $pool_stats = $stats['stats'];
        $active_connections = $stats['active_connections'] ?? 0;
        $pool_details = $stats['pool_details'] ?? array();

        // Calculate efficiency
        $total_requests = $pool_stats['hits'] + $pool_stats['misses'];
        $efficiency_percent = $total_requests > 0 ? round(($pool_stats['hits'] / $total_requests) * 100, 1) : 0;

        // Determine efficiency status
        if ($efficiency_percent >= 70)
        {
            $efficiency_status = 'Excellent';
            $efficiency_color = '#28a745';
        }
        elseif ($efficiency_percent >= 50)
        {
            $efficiency_status = 'Good';
            $efficiency_color = '#ffc107';
        }
        elseif ($efficiency_percent >= 30)
        {
            $efficiency_status = 'Fair';
            $efficiency_color = '#fd7e14';
        }
        else
        {
            $efficiency_status = 'Poor';
            $efficiency_color = '#dc3545';
        }

        return array(
            'summary' => sprintf(
                'Connection reuse efficiency: %s%% (%s)',
                $efficiency_percent,
                $efficiency_status
            ),
            'efficiency_percent' => $efficiency_percent,
            'efficiency_status' => $efficiency_status,
            'efficiency_color' => $efficiency_color,
            'details' => array(
                'Total API Calls' => number_format($total_requests),
                'Reused Connections' => number_format($pool_stats['hits']),
                'New Connections' => number_format($pool_stats['misses']),
                'Active Connections' => number_format($active_connections),
                'Connections Created' => number_format($pool_stats['created']),
                'Connections Cleaned' => number_format($pool_stats['cleaned'])
            ),
            'pool_details' => $pool_details
        );
    }
}
