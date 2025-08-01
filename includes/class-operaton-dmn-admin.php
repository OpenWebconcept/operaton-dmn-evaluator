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
if (!defined('ABSPATH')) {
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

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Interface manager initialized');
        }

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
    }

    // =============================================================================
    // ADMIN MENU AND PAGE METHODS
    // =============================================================================

    /**
     * Enhanced ajax_debug_status method for class-operaton-dmn-admin.php
     *
     * This cleans up the duplicate performance data and adds context
     */
    public function ajax_debug_status()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Get performance data if available
        $performance_data = array();
        if (class_exists('Operaton_DMN_Performance_Monitor')) {
            try {
                $performance_monitor = Operaton_DMN_Performance_Monitor::get_instance();
                $performance_summary = $performance_monitor->get_summary();

                // Clean and organize performance data
                $performance_data = array(
                    'current_request' => array(
                        'total_time_ms' => $performance_summary['total_time_ms'],
                        'peak_memory_formatted' => $performance_summary['peak_memory_formatted'],
                        'milestone_count' => $performance_summary['milestone_count'],
                        'request_type' => $performance_summary['request_data']['is_ajax'] ? 'AJAX' : 'Standard',
                        'is_admin' => $performance_summary['request_data']['is_admin']
                    ),
                    'initialization_timing' => array(
                        'plugin_construct' => $this->get_milestone_duration($performance_summary['milestones'], 'plugin_construct'),
                        'assets_manager' => $this->get_milestone_time($performance_summary['milestones'], 'assets_manager_loaded'),
                        'database_manager' => $this->get_milestone_time($performance_summary['milestones'], 'database_manager_loaded'),
                        'gravity_forms_manager' => $this->get_milestone_time($performance_summary['milestones'], 'gravity_forms_manager_loaded'),
                        'wp_loaded_at' => $this->get_milestone_time($performance_summary['milestones'], 'wp_loaded')
                    ),
                    'performance_grade' => $this->calculate_performance_grade($performance_summary),
                    'recommendations' => $this->get_performance_recommendations($performance_summary)
                );
            } catch (Exception $e) {
                $performance_data = array(
                    'error' => 'Performance monitor error: ' . $e->getMessage()
                );
            }
        } else {
            $performance_data = array(
                'status' => 'Performance monitor class not available'
            );
        }

        // Get asset status with context
        $asset_status = $this->assets->get_assets_status();

        // Add context about why scripts might not be registered
        $asset_status['context'] = array(
            'current_page' => get_current_screen()->id ?? 'unknown',
            'is_ajax_request' => wp_doing_ajax(),
            'script_loading_note' => 'Scripts are only registered when needed on specific pages - this is optimal behavior'
        );

        $status = array(
            'plugin_version' => OPERATON_DMN_VERSION,
            'managers' => $this->core->get_managers_status(),
            'health' => $this->core->health_check(),
            'assets' => $asset_status,
            'performance' => $performance_data, // Cleaned up single performance section
            'environment' => array(
                'wordpress' => get_bloginfo('version'),
                'php' => PHP_VERSION,
                'theme' => wp_get_theme()->get('Name') . ' v' . wp_get_theme()->get('Version'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'wp_debug' => defined('WP_DEBUG') && WP_DEBUG
            ),
            'operaton_constants' => array(
                'version' => OPERATON_DMN_VERSION,
                'plugin_url' => OPERATON_DMN_PLUGIN_URL,
                'plugin_path' => OPERATON_DMN_PLUGIN_PATH
            ),
            'timestamp' => current_time('mysql'),
            'user_context' => array(
                'user_id' => get_current_user_id(),
                'user_role' => implode(', ', wp_get_current_user()->roles),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            )
        );

        wp_send_json_success($status);
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

        if (isset($milestones[$start_key]) && isset($milestones[$end_key])) {
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
        if ($total_time < 100 && $memory_mb < 16) {
            return 'A+ (Excellent)';
        } elseif ($total_time < 200 && $memory_mb < 32) {
            return 'A (Very Good)';
        } elseif ($total_time < 500 && $memory_mb < 64) {
            return 'B (Good)';
        } elseif ($total_time < 1000 && $memory_mb < 128) {
            return 'C (Acceptable)';
        } else {
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

        if ($total_time < 100) {
            $recommendations[] = '🚀 Excellent loading speed!';
        }

        if ($memory_mb < 16) {
            $recommendations[] = '🧠 Very efficient memory usage!';
        }

        if ($performance_summary['milestone_count'] > 20) {
            $recommendations[] = '📊 Consider reducing performance monitoring in production';
        }

        if (empty($recommendations)) {
            $recommendations[] = '✨ Performance is optimal - no recommendations needed!';
        }

        return $recommendations;
    }

    // Add debug button to admin pages
    public function add_debug_button()
    {
        if (!current_user_can('manage_options') || !defined('WP_DEBUG') || !WP_DEBUG) {
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Adding admin menu pages');
        }

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
        if (defined('WP_DEBUG') && WP_DEBUG) {
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Adding debug menu');

            // Check if debug class exists and use it, otherwise use temp page
            if (class_exists('OperatonDMNUpdateDebugger')) {
                global $operaton_debug_instance;
                if (!$operaton_debug_instance) {
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

                error_log('Operaton DMN Admin: Debug menu added using OperatonDMNUpdateDebugger class');
            } else {
                add_submenu_page(
                    'operaton-dmn',
                    __('Update Debug', 'operaton-dmn'),
                    __('Update Debug', 'operaton-dmn'),
                    $this->capability,
                    'operaton-dmn-update-debug',
                    array($this, 'temp_debug_page')
                );

                error_log('Operaton DMN Admin: Debug menu added using temp page (class not found)');
            }
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Loading main admin page');
        }

        // Force database check when accessing admin pages
        $this->core->get_database_instance()->check_and_update_database();

        // Check for database issues and show user-friendly message
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");

        if (!in_array('result_mappings', $columns)) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . __('Database Update Failed', 'operaton-dmn') . '</strong></p>';
            echo '<p>' . __('The plugin attempted to update the database but it failed. Please contact your administrator.', 'operaton-dmn') . '</p>';
            echo '<p>' . __('Error: Missing required columns in database table.', 'operaton-dmn') . '</p>';
            echo '</div>';
            return;
        }

        // Check for database update success message
        if (isset($_GET['database_updated'])) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('Database schema updated successfully!', 'operaton-dmn') . '</p>';
            echo '</div>';
        }

        // Handle configuration deletion
        if (isset($_POST['delete_config']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_config')) {
            $this->handle_config_deletion($_POST['config_id']);
        }

        // Get configurations for display
        $configs = $this->core->get_database_instance()->get_all_configurations();

        // Start the admin page wrapper
        echo '<div class="wrap">';
        echo '<h1>' . __('Operaton DMN Configurations', 'operaton-dmn') . '</h1>';

        // ADD DEBUG BUTTON HERE
        $this->add_debug_button();

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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Loading configuration edit page');
        }

        // Force database check
        $this->core->get_database_instance()->check_and_update_database();

        // Check if migration was successful
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");

        if (!in_array('result_mappings', $columns)) {
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
        if (isset($_POST['save_config']) && wp_verify_nonce($_POST['_wpnonce'], 'save_config')) {
            $this->handle_config_save($_POST);
        }

        // Get data for form display
        $gravity_forms = $this->get_gravity_forms();
        $config = $this->core->get_database_instance()->get_configuration($_GET['edit']);

        // Start the admin page wrapper
        echo '<div class="wrap">';
        echo '<h1>' . __('Add/Edit Configuration', 'operaton-dmn') . '</h1>';

        // ADD DEBUG BUTTON HERE TOO
        $this->add_debug_button();

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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Displaying temporary debug page');
        }

        echo '<div class="wrap operaton-debug-page">';
        echo '<h1>' . __('Debug Menu Test', 'operaton-dmn') . '</h1>';

        // ADD THE MAIN DEBUG BUTTON AT THE TOP
        $this->add_debug_button();

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
        if (!class_exists('Operaton_DMN_Performance_Monitor')) {
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
        if (!empty($summary['milestones'])) {
            echo '<h4>Recent Performance Milestones</h4>';
            echo '<div style="max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; border-radius: 4px;">';
            foreach (array_slice($summary['milestones'], -10, 10, true) as $name => $milestone) {
                echo '<div style="margin: 5px 0; font-family: monospace; font-size: 12px;">';
                echo '<strong>' . esc_html($name) . ':</strong> ' . $milestone['time_ms'] . 'ms';
                if ($milestone['details']) {
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Enqueuing admin scripts for hook: ' . $hook);
        }

        // Only enqueue on our plugin pages
        if (strpos($hook, 'operaton-dmn') !== false) {
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Ensuring frontend assets are loaded');
        }

        // Only enqueue on frontend
        if (!is_admin()) {
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
        if (!current_user_can($this->capability)) {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Checking for admin notices');
        }

        $issues = $this->check_plugin_health();
        if (!empty($issues)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . __('Operaton DMN Plugin Issues:', 'operaton-dmn') . '</strong></p>';
            echo '<ul>';
            foreach ($issues as $issue) {
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Adding settings link to plugin page');
        }

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

        if ($result !== false) {
            echo '<div class="notice notice-success"><p>' . __('Configuration deleted successfully!', 'operaton-dmn') . '</p></div>';
        } else {
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

        if ($result) {
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

        if (!$gravity_forms_manager || !$gravity_forms_manager->is_gravity_forms_available()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Admin: Gravity Forms not available');
            }
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

        if (file_exists($template_path)) {
            // Extract data for use in template
            extract($data);
            include $template_path;
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Admin: Template not found: ' . $template_path);
            }
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

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Performing health check');
        }

        // Check if Gravity Forms is active
        $gravity_forms_manager = $this->core->get_gravity_forms_instance();
        if (!$gravity_forms_manager || !$gravity_forms_manager->is_gravity_forms_available()) {
            $issues[] = __('Gravity Forms is not active.', 'operaton-dmn');
        }

        // Check if Gravity Forms is active
        if (!class_exists('GFForms')) {
            $issues[] = __('Gravity Forms is not active.', 'operaton-dmn');
        }

        // Check database table
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $issues[] = __('Database table is missing.', 'operaton-dmn');
        }

        // Check if REST API is working
        $test_url = rest_url('operaton-dmn/v1/test');
        $response = wp_remote_get($test_url, array('timeout' => 5));
        if (is_wp_error($response)) {
            $issues[] = __('REST API is not accessible.', 'operaton-dmn');
        }

        return $issues;
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
        if (!current_user_can($this->capability)) {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Admin: Displaying update management section');
        }

        $current_version = OPERATON_DMN_VERSION;
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
}
