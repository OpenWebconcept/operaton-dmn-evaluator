<?php
/**
 * Plugin Name: Operaton DMN Evaluator
 * Plugin URI: https://git.open-regels.nl/showcases/operaton-dmn-evaluator
 *
 * Enhanced Operaton DMN Evaluator v1.0.0-beta.9 with Process Integration
 * 
 * Key Changes:
 * 1. Added process execution support alongside decision evaluation
 * 2. Added decision flow results summary display
 * 3. Enhanced configuration to support both modes
 * 4. Added third page summary functionality
 * 
 * Description: WordPress plugin to integrate Gravity Forms with Operaton DMN decision tables for dynamic form evaluations.
 * Version: 1.0.0-beta.9
 * Author: Steven Gort
 * License: EU PL v1.2
 * Text Domain: operaton-dmn
 * Update URI: https://git.open-regels.nl/showcases/operaton-dmn-evaluator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OPERATON_DMN_VERSION', '1.0.0-beta.9');
define('OPERATON_DMN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OPERATON_DMN_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Initialize the update checker - CLEAN VERSION
if (is_admin()) {
    // Only load auto-updater in admin context
    $updater_file = OPERATON_DMN_PLUGIN_PATH . 'includes/plugin-updater.php';
    
    if (file_exists($updater_file)) {
        require_once $updater_file;
        
        // IMPORTANT: Initialize with the MAIN plugin file, not the updater file
        new OperatonDMNAutoUpdater(__FILE__, OPERATON_DMN_VERSION);
        
        // Log successful loading if debug is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Auto-updater loaded successfully');
            error_log('Operaton DMN: Plugin file for updater: ' . __FILE__);
            error_log('Operaton DMN: Plugin basename: ' . plugin_basename(__FILE__));
        }
        
        // Add debug information to admin
        add_action('admin_footer', function() {
            if (current_user_can('manage_options') && isset($_GET['page']) && strpos($_GET['page'], 'operaton-dmn') !== false) {
                echo '<script>console.log("Operaton DMN Auto-Updater: Loaded");</script>';
            }
        });
        
    } else {
        // Log missing file if debug is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Auto-updater file not found at: ' . $updater_file);
        }
        
        // Show admin notice about missing auto-updater
        add_action('admin_notices', function() {
            if (current_user_can('manage_options')) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>Operaton DMN Evaluator:</strong> Auto-update system files are missing. Please reinstall the plugin to enable automatic updates.</p>';
                echo '</div>';
            }
        });
    }
    
    // Load debug tools if in debug mode (remove in production)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Operaton DMN: WP_DEBUG is enabled, attempting to load debug tools');
        $debug_file = OPERATON_DMN_PLUGIN_PATH . 'includes/update-debug.php';
        error_log('Operaton DMN: Debug file path: ' . $debug_file);
    
        if (file_exists($debug_file)) {
            error_log('Operaton DMN: Debug file exists, loading...');
            require_once $debug_file;
            error_log('Operaton DMN: Debug file loaded successfully');
        } else {
            error_log('Operaton DMN: Debug file NOT found at: ' . $debug_file);
        }
    }
}

/**
 * Main plugin class
 */
class OperatonDMNEvaluator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

public function ajax_clear_update_cache() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    // Clear WordPress update transients
    delete_site_transient('update_plugins');
    delete_transient('operaton_dmn_updater');
    delete_transient('operaton_dmn_fallback_check');
    
    // Force WordPress to check for updates
    wp_update_plugins();
    
    wp_send_json_success(array('message' => 'Update cache cleared'));
}
    
/**
 * Updated constructor - add database check on every admin page load
 */
private function __construct() {
    add_action('init', array($this, 'init'));
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    add_action('rest_api_init', array($this, 'register_rest_routes'));
    
    // CRITICAL: Check database on every admin page load
    if (is_admin()) {
        add_action('admin_init', array($this, 'check_and_update_database'), 1);
    }
    
    // Version check for upgrades
    add_action('admin_init', array($this, 'check_version'), 5);
        
    // Add AJAX handlers
    add_action('wp_ajax_operaton_test_endpoint', array($this, 'ajax_test_endpoint'));
    add_action('wp_ajax_nopriv_operaton_test_endpoint', array($this, 'ajax_test_endpoint'));
    add_action('wp_ajax_operaton_test_full_config', array($this, 'ajax_test_full_config'));
    add_action('wp_ajax_operaton_clear_update_cache', array($this, 'ajax_clear_update_cache')); // ADD THIS LINE
        // Add manual database update handler
        add_action('wp_ajax_operaton_manual_db_update', array($this, 'ajax_manual_database_update'));
    
        // Admin notices and health checks
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('operaton_dmn_cleanup', array($this, 'cleanup_old_data'));
        
        // Add settings link to plugin page
        $plugin_basename = plugin_basename(__FILE__);
        add_filter("plugin_action_links_$plugin_basename", array($this, 'add_settings_link'));
        
        // Gravity Forms integration - fixed to always load when GF is available
        add_action('init', array($this, 'init_gravity_forms_integration'));
        
        // Version check for upgrades
        add_action('admin_init', array($this, 'check_version'));

// TEMPORARY: Clear decision flow cache
add_action('admin_init', function() {
    if (isset($_GET['clear_operaton_cache'])) {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_operaton_%'");
        wp_redirect(admin_url('admin.php?page=operaton-dmn&cache_cleared=1'));
        exit;
    }
});

// Add a button to clear cache
add_action('admin_notices', function() {
    if (current_user_can('manage_options') && isset($_GET['page']) && $_GET['page'] === 'operaton-dmn') {
        echo '<div class="notice notice-info">';
        echo '<p><strong>Decision Flow Cache:</strong> ';
        echo '<a href="' . admin_url('admin.php?page=operaton-dmn&clear_operaton_cache=1') . '" class="button">Clear Decision Flow Cache</a>';
        echo '</p></div>';
        
        if (isset($_GET['cache_cleared'])) {
            echo '<div class="notice notice-success"><p>Decision flow cache cleared!</p></div>';
        }
    }
});
        // IMMEDIATE database check on admin pages
        if (is_admin()) {
            add_action('admin_init', array($this, 'check_and_update_database'), 5);
        }

        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        load_plugin_textdomain('operaton-dmn', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function init_gravity_forms_integration() {
        // Check if Gravity Forms is active before adding hooks
        if (class_exists('GFForms')) {
            add_action('gform_enqueue_scripts', array($this, 'enqueue_gravity_scripts'), 10, 2);
            add_filter('gform_submit_button', array($this, 'add_evaluate_button'), 10, 2);
            
            // Add form editor integration
            add_action('gform_editor_js', array($this, 'editor_script'));
            add_action('gform_field_advanced_settings', array($this, 'field_advanced_settings'), 10, 2);
        }
    }
    
    /**
     * Add compatibility for form editor
     */
    public function editor_script() {
        ?>
        <script type='text/javascript'>
        jQuery(document).ready(function($) {
            // Add compatibility for form editor
            if (typeof fieldSettings !== 'undefined') {
                fieldSettings.operaton_dmn = '.label_setting, .description_setting, .admin_label_setting, .size_setting, .default_value_textarea_setting, .error_message_setting, .css_class_setting, .visibility_setting';
            }
        });
        </script>
        <?php
    }
    
    /**
     * Field advanced settings (placeholder for future features)
     */
    public function field_advanced_settings($position, $form_id) {
        // Placeholder for future field-specific settings
    }
    
/**
 * Enhanced activation hook with automatic migration
 */
public function activate() {
    // Create/update database tables
    $this->create_database_tables();
    
    // Set default options
    add_option('operaton_dmn_version', OPERATON_DMN_VERSION);
    add_option('operaton_dmn_activated', current_time('mysql'));
    
    // Schedule cleanup cron job
    if (!wp_next_scheduled('operaton_dmn_cleanup')) {
        wp_schedule_event(time(), 'daily', 'operaton_dmn_cleanup');
    }
    
    flush_rewrite_rules();
}    
    /**
     * Enhanced deactivation hook
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('operaton_dmn_cleanup');
        
        // Clear any cached data
        $this->clear_config_cache();
        
        flush_rewrite_rules();
    }
    
    /**
     * Enhanced database table creation with process support
     */
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        
        // Check if table already exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            // Check if new columns exist, add them if not
            $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
            
            if (!in_array('result_mappings', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN result_mappings longtext NOT NULL");
            }
            
            if (!in_array('evaluation_step', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN evaluation_step varchar(10) DEFAULT 'auto'");
            }
            
            // NEW: Add process integration columns
            if (!in_array('use_process', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN use_process boolean DEFAULT false");
            }
            
            if (!in_array('process_key', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN process_key varchar(255) DEFAULT NULL");
            }
            
            if (!in_array('show_decision_flow', $columns)) {
                $wpdb->query("ALTER TABLE $table_name ADD COLUMN show_decision_flow boolean DEFAULT false");
            }
            
            return;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            form_id int(11) NOT NULL,
            dmn_endpoint varchar(500) NOT NULL,
            decision_key varchar(255) NOT NULL,
            field_mappings longtext NOT NULL,
            result_mappings longtext NOT NULL,
            evaluation_step varchar(10) DEFAULT 'auto',
            button_text varchar(255) DEFAULT 'Evaluate',
            use_process boolean DEFAULT false,
            process_key varchar(255) DEFAULT NULL,
            show_decision_flow boolean DEFAULT false,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_form_id (form_id),
            KEY idx_form_id (form_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        return dbDelta($sql);
    }

public function add_admin_menu() {
    add_menu_page(
        __('Operaton DMN', 'operaton-dmn'),
        __('Operaton DMN', 'operaton-dmn'),
        'manage_options',
        'operaton-dmn',
        array($this, 'admin_page'),
        'dashicons-analytics',
        30
    );
    
    add_submenu_page(
        'operaton-dmn',
        __('Configurations', 'operaton-dmn'),
        __('Configurations', 'operaton-dmn'),
        'manage_options',
        'operaton-dmn',
        array($this, 'admin_page')
    );
    
    add_submenu_page(
        'operaton-dmn',
        __('Add Configuration', 'operaton-dmn'),
        __('Add Configuration', 'operaton-dmn'),
        'manage_options',
        'operaton-dmn-add',
        array($this, 'add_config_page')
    );
    
    // Add debug menu directly (temporary for testing)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Operaton DMN: Adding debug menu directly from main plugin');
        
        // Check if debug class exists and use it, otherwise use temp page
        if (class_exists('OperatonDMNUpdateDebugger')) {
            // Create an instance to call the debug page method
            global $operaton_debug_instance;
            if (!$operaton_debug_instance) {
                $operaton_debug_instance = new OperatonDMNUpdateDebugger();
            }
            
            add_submenu_page(
                'operaton-dmn',
                __('Update Debug', 'operaton-dmn'),
                __('Update Debug', 'operaton-dmn'),
                'manage_options',
                'operaton-dmn-update-debug',
                array($operaton_debug_instance, 'debug_page')
            );
            error_log('Operaton DMN: Debug menu added using OperatonDMNUpdateDebugger class');
        } else {
            add_submenu_page(
                'operaton-dmn',
                __('Update Debug', 'operaton-dmn'),
                __('Update Debug', 'operaton-dmn'),
                'manage_options',
                'operaton-dmn-update-debug',
                array($this, 'temp_debug_page')
            );
            error_log('Operaton DMN: Debug menu added using temp page (class not found)');
        }
    }
}

// Add this temporary method to the main plugin class
public function temp_debug_page() {
    echo '<div class="wrap">';
    echo '<h1>Debug Menu Test</h1>';
    echo '<p>✅ Debug menu is working! The debug system is properly integrated.</p>';
    echo '<p>OperatonDMNUpdateDebugger class exists: ' . (class_exists('OperatonDMNUpdateDebugger') ? 'YES' : 'NO') . '</p>';
    echo '<p>If the class exists, the full debug interface should work.</p>';
    echo '</div>';
}
    
/**
 * Safe admin form template with automatic migration trigger
 */
public function admin_page() {
    // Force database check when accessing admin pages
    $this->check_and_update_database();
    
    // Check for any database issues and show user-friendly message
    global $wpdb;
    $table_name = $wpdb->prefix . 'operaton_dmn_configs';
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
    
    if (!in_array('result_mappings', $columns)) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>Database Update Failed</strong></p>';
        echo '<p>The plugin attempted to update the database but it failed. Please contact your administrator.</p>';
        echo '<p>Error: Missing result_mappings column in database table.</p>';
        echo '</div>';
        return;
    }
    
    // Check for database update success message
    if (isset($_GET['database_updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Database schema updated successfully!', 'operaton-dmn') . '</p></div>';
    }
    
    // Handle configuration deletion
    if (isset($_POST['delete_config']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_config')) {
        $this->delete_config($_POST['config_id']);
    }
    
    $configs = $this->get_all_configurations();
    
    // Show update management section
    $this->show_update_management_section();
    
    include OPERATON_DMN_PLUGIN_PATH . 'templates/admin-list.php';
}

/**
 * Safe configuration page with migration check
 */
public function add_config_page() {
    // Force database check
    $this->check_and_update_database();
    
    // Check if migration was successful
    global $wpdb;
    $table_name = $wpdb->prefix . 'operaton_dmn_configs';
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
    
    if (!in_array('result_mappings', $columns)) {
        echo '<div class="wrap">';
        echo '<h1>Database Update Required</h1>';
        echo '<div class="notice notice-error">';
        echo '<p><strong>Database update failed.</strong> Please deactivate and reactivate the plugin, or contact your administrator.</p>';
        echo '</div>';
        echo '</div>';
        return;
    }
    
    // Rest of existing add_config_page method...
    if (isset($_POST['save_config']) && wp_verify_nonce($_POST['_wpnonce'], 'save_config')) {
        $this->save_configuration($_POST);
    }
    
    $gravity_forms = $this->get_gravity_forms();
    $config = isset($_GET['edit']) ? $this->get_configuration($_GET['edit']) : null;
    include OPERATON_DMN_PLUGIN_PATH . 'templates/admin-form.php';
}    

/**
 * Helper method for timezone handling
 */
private function format_evaluation_time($iso_timestamp) {
    if (empty($iso_timestamp)) {
        return 'Unknown';
    }
    
    try {
        // Parse the ISO timestamp (handles UTC offsets)
        $datetime = new DateTime($iso_timestamp);
        
        // Convert to WordPress site timezone
        $wp_timezone = wp_timezone();
        $datetime->setTimezone($wp_timezone);
        
        // Format in a user-friendly way
        $formatted_date = $datetime->format('Y-m-d H:i:s');
        $timezone_name = $datetime->format('T'); // Timezone abbreviation (CEST, CET, etc.)
        
        return $formatted_date . ' (' . $timezone_name . ')';
        
    } catch (Exception $e) {
        // Fallback: just clean up the original timestamp
        $clean_time = str_replace(['T', '+0000'], [' ', ' UTC'], $iso_timestamp);
        return $clean_time;
    }
}

    /**
     * Show update management section in admin
     */
private function show_update_management_section() {
    if (!current_user_can('manage_options')) {
        return;
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
        
        <?php if ($has_update): ?>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0;">
                <p><strong><?php _e('Update Available:', 'operaton-dmn'); ?></strong> <?php echo esc_html($new_version); ?></p>
                <p>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-primary">
                        <?php _e('Go to Plugins Page to Update', 'operaton-dmn'); ?>
                    </a>
                </p>
            </div>
        <?php else: ?>
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
                status.html('<span style="color: #666;">⏳ Checking for updates...</span>');
                
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
                        status.html('<span style="color: #46b450;">✓ Update check completed</span>');
                    } else {
                        status.html('<span style="color: #dc3232;">✗ Update check failed</span>');
                        button.prop('disabled', false).text('<?php _e('Check for Updates Now', 'operaton-dmn'); ?>');
                    }
                }).fail(function() {
                    status.html('<span style="color: #dc3232;">✗ Update check failed</span>');
                    button.prop('disabled', false).text('<?php _e('Check for Updates Now', 'operaton-dmn'); ?>');
                });
            });
        });
        </script>
    </div>
    <?php
}

    /**
     * Improved Gravity Forms retrieval with field information
     */
    private function get_gravity_forms() {
        if (!class_exists('GFAPI')) {
            return array();
        }
        
        try {
            $forms = GFAPI::get_forms();
            // Add form fields information for better mapping
            foreach ($forms as &$form) {
                if (isset($form['fields'])) {
                    $form['field_list'] = array();
                    foreach ($form['fields'] as $field) {
                        $form['field_list'][] = array(
                            'id' => $field->id,
                            'label' => $field->label,
                            'type' => $field->type
                        );
                    }
                }
            }
            return $forms;
        } catch (Exception $e) {
            error_log('Operaton DMN: Error getting Gravity Forms: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Build the full DMN evaluation endpoint URL
     */
    private function build_evaluation_endpoint($base_endpoint, $decision_key) {
        // Ensure base endpoint ends with /
        if (!empty($base_endpoint) && substr($base_endpoint, -1) !== '/') {
            $base_endpoint .= '/';
        }
        
        return $base_endpoint . $decision_key . '/evaluate';
    }
    
    /**
     * Enhanced validation with process support
     */
    private function validate_configuration_data($data) {
        $errors = array();
        
        // Required field validation
        $required_fields = array(
            'name' => __('Configuration Name', 'operaton-dmn'),
            'form_id' => __('Gravity Form', 'operaton-dmn'),
            'dmn_endpoint' => __('DMN Base Endpoint URL', 'operaton-dmn'),
        );
        
        // Decision key OR process key is required
        $use_process = isset($data['use_process']) && $data['use_process'];
        
        if ($use_process) {
            if (empty($data['process_key'])) {
                $errors[] = __('Process Key is required when using process execution.', 'operaton-dmn');
            }
        } else {
            if (empty($data['decision_key'])) {
                $errors[] = __('Decision Key is required when using direct decision evaluation.', 'operaton-dmn');
            }
        }
        
        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = sprintf(__('%s is required.', 'operaton-dmn'), $label);
            }
        }
        
        // URL validation
        if (!empty($data['dmn_endpoint']) && !filter_var($data['dmn_endpoint'], FILTER_VALIDATE_URL)) {
            $errors[] = __('DMN Base Endpoint URL is not valid.', 'operaton-dmn');
        }
        
        // Key validation
        $key_to_validate = $use_process ? $data['process_key'] : $data['decision_key'];
        if (!empty($key_to_validate)) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', trim($key_to_validate))) {
                $key_type = $use_process ? 'Process key' : 'Decision key';
                $errors[] = sprintf(__('%s should only contain letters, numbers, hyphens, and underscores.', 'operaton-dmn'), $key_type);
            }
        }
    
    // Form ID validation
    if (!empty($data['form_id'])) {
        if (class_exists('GFAPI')) {
            $form = GFAPI::get_form($data['form_id']);
            if (!$form) {
                $errors[] = __('Selected Gravity Form does not exist.', 'operaton-dmn');
            }
        }
    }
    
    // Input field mappings validation
    $has_input_mappings = false;
    if (isset($data['field_mappings_dmn_variable']) && is_array($data['field_mappings_dmn_variable'])) {
        $dmn_variables = $data['field_mappings_dmn_variable'];
        $field_ids = isset($data['field_mappings_field_id']) ? $data['field_mappings_field_id'] : array();
        
        for ($i = 0; $i < count($dmn_variables); $i++) {
            $dmn_var = trim($dmn_variables[$i]);
            $field_id = isset($field_ids[$i]) ? trim($field_ids[$i]) : '';
            
            if (!empty($dmn_var) && !empty($field_id)) {
                $has_input_mappings = true;
                
                if (!is_numeric($field_id)) {
                    $errors[] = sprintf(__('Field ID "%s" must be numeric.', 'operaton-dmn'), $field_id);
                }
                
                // Validate field exists in form
                if (class_exists('GFAPI') && !empty($data['form_id'])) {
                    $form = GFAPI::get_form($data['form_id']);
                    if ($form) {
                        $field_exists = false;
                        foreach ($form['fields'] as $form_field) {
                            if ($form_field->id == $field_id) {
                                $field_exists = true;
                                break;
                            }
                        }
                        if (!$field_exists) {
                            $errors[] = sprintf(__('Input field ID "%s" does not exist in the selected form.', 'operaton-dmn'), $field_id);
                        }
                    }
                }
            }
        }
    }
    
    if (!$has_input_mappings) {
        $errors[] = __('At least one input field mapping is required.', 'operaton-dmn');
    }
    
    // Result mappings validation
    $has_result_mappings = false;
    if (isset($data['result_mappings_dmn_result']) && is_array($data['result_mappings_dmn_result'])) {
        $dmn_results = $data['result_mappings_dmn_result'];
        $result_field_ids = isset($data['result_mappings_field_id']) ? $data['result_mappings_field_id'] : array();
        
        for ($i = 0; $i < count($dmn_results); $i++) {
            $dmn_result = trim($dmn_results[$i]);
            $field_id = isset($result_field_ids[$i]) ? trim($result_field_ids[$i]) : '';
            
            if (!empty($dmn_result) && !empty($field_id)) {
                $has_result_mappings = true;
                
                if (!is_numeric($field_id)) {
                    $errors[] = sprintf(__('Result field ID "%s" must be numeric.', 'operaton-dmn'), $field_id);
                }
                
                // Validate field exists in form
                if (class_exists('GFAPI') && !empty($data['form_id'])) {
                    $form = GFAPI::get_form($data['form_id']);
                    if ($form) {
                        $field_exists = false;
                        foreach ($form['fields'] as $form_field) {
                            if ($form_field->id == $field_id) {
                                $field_exists = true;
                                break;
                            }
                        }
                        if (!$field_exists) {
                            $errors[] = sprintf(__('Result field ID "%s" does not exist in the selected form.', 'operaton-dmn'), $field_id);
                        }
                    }
                }
            }
        }
    }
    
    if (!$has_result_mappings) {
        $errors[] = __('At least one result field mapping is required.', 'operaton-dmn');
    }
    
    return $errors;
}

/**
 * Enhanced configuration saving with process support
 */
private function save_configuration($data) {
    // Validate data
    $validation_errors = $this->validate_configuration_data($data);
    
    if (!empty($validation_errors)) {
        echo '<div class="notice notice-error"><ul>';
        foreach ($validation_errors as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
        return false;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'operaton_dmn_configs';
    
    // Process field mappings
    $field_mappings = array();
    
    if (isset($data['field_mappings_dmn_variable']) && is_array($data['field_mappings_dmn_variable'])) {
        $dmn_variables = $data['field_mappings_dmn_variable'];
        $field_ids = isset($data['field_mappings_field_id']) ? $data['field_mappings_field_id'] : array();
        $types = isset($data['field_mappings_type']) ? $data['field_mappings_type'] : array();
        $radio_names = isset($data['field_mappings_radio_name']) ? $data['field_mappings_radio_name'] : array();
        
        for ($i = 0; $i < count($dmn_variables); $i++) {
            $dmn_var = sanitize_text_field(trim($dmn_variables[$i]));
            $field_id = isset($field_ids[$i]) ? sanitize_text_field(trim($field_ids[$i])) : '';
            $type = isset($types[$i]) ? sanitize_text_field($types[$i]) : 'String';
            $radio_name = isset($radio_names[$i]) ? sanitize_text_field(trim($radio_names[$i])) : '';
            
            if (!empty($dmn_var) && !empty($field_id)) {
                $field_mappings[$dmn_var] = array(
                    'field_id' => $field_id,
                    'type' => $type,
                    'radio_name' => $radio_name
                );
            }
        }
    }
    
    // Process result mappings
    $result_mappings = array();
    
    if (isset($data['result_mappings_dmn_result']) && is_array($data['result_mappings_dmn_result'])) {
        $dmn_results = $data['result_mappings_dmn_result'];
        $result_field_ids = isset($data['result_mappings_field_id']) ? $data['result_mappings_field_id'] : array();
        
        for ($i = 0; $i < count($dmn_results); $i++) {
            $dmn_result = sanitize_text_field(trim($dmn_results[$i]));
            $field_id = isset($result_field_ids[$i]) ? sanitize_text_field(trim($result_field_ids[$i])) : '';
            
            if (!empty($dmn_result) && !empty($field_id)) {
                $result_mappings[$dmn_result] = array(
                    'field_id' => $field_id
                );
            }
        }
    }
    
        $config_data = array(
            'name' => sanitize_text_field($data['name']),
            'form_id' => intval($data['form_id']),
            'dmn_endpoint' => esc_url_raw($data['dmn_endpoint']),
            'decision_key' => sanitize_text_field($data['decision_key'] ?? ''),
            'field_mappings' => wp_json_encode($field_mappings),
            'result_mappings' => wp_json_encode($result_mappings),
            'evaluation_step' => sanitize_text_field($data['evaluation_step'] ?? 'auto'),
            'button_text' => sanitize_text_field($data['button_text'] ?: 'Evaluate'),
            // NEW: Process-related fields
            'use_process' => isset($data['use_process']) ? (bool)$data['use_process'] : false,
            'process_key' => sanitize_text_field($data['process_key'] ?? ''),
            'show_decision_flow' => isset($data['show_decision_flow']) ? (bool)$data['show_decision_flow'] : false
        );
    
    $config_id = isset($data['config_id']) ? intval($data['config_id']) : 0;
    
    if ($config_id > 0) {
        // Update existing configuration
        $result = $wpdb->update(
            $table_name, 
            $config_data, 
            array('id' => $config_id),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $message = __('Configuration updated successfully!', 'operaton-dmn');
            $this->clear_config_cache();
        } else {
            echo '<div class="notice notice-error"><p>' . __('Error updating configuration: ', 'operaton-dmn') . $wpdb->last_error . '</p></div>';
            return false;
        }
    } else {
        // Check for duplicate form_id
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE form_id = %d", 
            $config_data['form_id']
        ));
        
        if ($existing) {
            echo '<div class="notice notice-error"><p>' . __('A configuration for this form already exists. Please edit the existing configuration or choose a different form.', 'operaton-dmn') . '</p></div>';
            return false;
        }
        
        // Insert new configuration
        $result = $wpdb->insert(
            $table_name, 
            $config_data,
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result !== false) {
            $message = __('Configuration saved successfully!', 'operaton-dmn');
            $this->clear_config_cache();
        } else {
            echo '<div class="notice notice-error"><p>' . __('Error saving configuration: ', 'operaton-dmn') . $wpdb->last_error . '</p></div>';
            return false;
        }
    }
    
    echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
    return true;
}

/**
 * Automatic database migration on plugin updates
 */
public function check_and_update_database() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'operaton_dmn_configs';
    
    // Check if table exists at all
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        // Table doesn't exist, create it with new schema
        $this->create_database_tables();
        return;
    }
    
    // Get current columns
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
    
    // Add missing columns
    if (!in_array('result_mappings', $columns)) {
        $sql = "ALTER TABLE $table_name ADD COLUMN result_mappings longtext NOT NULL DEFAULT '{}'";
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            error_log('Operaton DMN: Error adding result_mappings column: ' . $wpdb->last_error);
        } else {
            error_log('Operaton DMN: Successfully added result_mappings column');
        }
    }
    
    if (!in_array('evaluation_step', $columns)) {
        $sql = "ALTER TABLE $table_name ADD COLUMN evaluation_step varchar(10) DEFAULT 'auto'";
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            error_log('Operaton DMN: Error adding evaluation_step column: ' . $wpdb->last_error);
        } else {
            error_log('Operaton DMN: Successfully added evaluation_step column');
        }
    }
    
    // Migration successful
    error_log('Operaton DMN: Database migration completed successfully');
}

/**
 * AJAX handler for manual database update
 */
public function ajax_manual_database_update() {
    // Verify nonce
    if (!wp_verify_nonce($_GET['_wpnonce'], 'operaton_manual_db_update')) {
        wp_die('Security check failed');
    }
    
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    // Perform database update
    $this->check_and_update_database();
    
    // Redirect back with success message
    wp_redirect(add_query_arg(array(
        'page' => 'operaton-dmn',
        'database_updated' => '1'
    ), admin_url('admin.php')));
    exit;
}

    private function get_all_configurations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        return $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    }
    
    private function get_configuration($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    }
    
    /**
     * Enhanced delete with cleanup
     */
    private function delete_config($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        
        $result = $wpdb->delete(
            $table_name, 
            array('id' => intval($id)),
            array('%d')
        );
        
        if ($result !== false) {
            $this->clear_config_cache();
            echo '<div class="notice notice-success"><p>' . __('Configuration deleted successfully!', 'operaton-dmn') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Error deleting configuration: ', 'operaton-dmn') . $wpdb->last_error . '</p></div>';
        }
        
        return $result;
    }
    
    public function enqueue_frontend_scripts() {
        // Only enqueue on frontend
        if (!is_admin()) {
            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'operaton-dmn-frontend',
                OPERATON_DMN_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                OPERATON_DMN_VERSION,
                true
            );
            
            // Enqueue frontend styles
            wp_enqueue_style(
                'operaton-dmn-frontend',
                OPERATON_DMN_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                OPERATON_DMN_VERSION
            );

            // Fixed REST API URL - make sure it matches the registered route
            wp_localize_script('operaton-dmn-frontend', 'operaton_ajax', array(
                'url' => rest_url('operaton-dmn/v1/evaluate'),
                'nonce' => wp_create_nonce('wp_rest'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG
            ));
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        // Only enqueue admin styles on our plugin pages
        if (strpos($hook, 'operaton-dmn') !== false) {
            wp_enqueue_style(
                'operaton-dmn-admin',
                OPERATON_DMN_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                OPERATON_DMN_VERSION
            );
            
            wp_enqueue_script('jquery');
            wp_enqueue_script(
                'operaton-dmn-admin',
                OPERATON_DMN_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                OPERATON_DMN_VERSION,
                true
            );
            
            // Localize script for admin AJAX
            wp_localize_script('operaton-dmn-admin', 'operaton_admin_ajax', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('operaton_admin_nonce')
            ));
        }
    }
    
/**
 * Simplified script enqueuing - no backward compatibility
 * Replace the enqueue_gravity_scripts method in your main plugin file
 */
public function enqueue_gravity_scripts($form, $is_ajax) {
    $config = $this->get_config_by_form_id($form['id']);
    if (!$config) {
        return;
    }
    
    // Ensure jQuery is loaded
    wp_enqueue_script('jquery');
    
    // Enqueue our frontend script
    wp_enqueue_script(
        'operaton-dmn-frontend',
        OPERATON_DMN_PLUGIN_URL . 'assets/js/frontend.js',
        array('jquery', 'gform_gravityforms'),
        OPERATON_DMN_VERSION,
        true
    );
    
    // Enqueue styles
    wp_enqueue_style(
        'operaton-dmn-frontend',
        OPERATON_DMN_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        OPERATON_DMN_VERSION
    );
    
    // Localize script
    wp_localize_script('operaton-dmn-frontend', 'operaton_ajax', array(
        'url' => rest_url('operaton-dmn/v1/evaluate'),
        'nonce' => wp_create_nonce('wp_rest'),
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    ));
    
    // Form-specific configuration
    $field_mappings = json_decode($config->field_mappings, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $field_mappings = array();
    }
    
    $result_mappings = json_decode($config->result_mappings, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $result_mappings = array();
    }
    
    wp_localize_script('operaton-dmn-frontend', 'operaton_config_' . $form['id'], array(
        'config_id' => $config->id,
        'button_text' => $config->button_text,
        'field_mappings' => $field_mappings,
        'result_mappings' => $result_mappings,
        'form_id' => $form['id'],
        'evaluation_step' => isset($config->evaluation_step) ? $config->evaluation_step : 'auto'
    ));
}

/**
 * Button placement with JavaScript fallback
 */
public function add_evaluate_button($button, $form) {
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
        return $button;
    }
    
    $config = $this->get_config_by_form_id($form['id']);
    if (!$config) {
        return $button;
    }
    
    // Get evaluation step from config
    $evaluation_step = isset($config->evaluation_step) ? $config->evaluation_step : '2';
    if ($evaluation_step === 'auto') {
        $evaluation_step = '2';
    }
    
    // Count total pages
    $total_pages = 1;
    foreach ($form['fields'] as $field) {
        if ($field->type === 'page') {
            $total_pages++;
        }
    }
    
    // Check for decision flow summary
    $show_decision_flow = isset($config->show_decision_flow) ? $config->show_decision_flow : false;
    
    // Create the evaluate button (always add it, let JavaScript control placement)
    $evaluate_button = sprintf(
        '<input type="button" id="operaton-evaluate-%1$d" value="%2$s" class="gform_button gform-theme-button operaton-evaluate-btn" data-form-id="%1$d" data-config-id="%3$d" style="display: none;">',
        $form['id'],
        esc_attr($config->button_text),
        $config->id
    );
    
    // Decision flow summary container (always add it)
    $decision_flow_container = sprintf(
        '<div id="decision-flow-summary-%d" class="decision-flow-summary" style="display: none;"></div>',
        $form['id']
    );
    
// FIXED JavaScript for dynamic button placement and decision flow
$script = '
<script>
jQuery(document).ready(function($) {
    var formId = ' . $form['id'] . ';
    var targetPage = ' . intval($evaluation_step) . ';
    var totalPages = ' . $total_pages . ';
    var showDecisionFlow = ' . ($show_decision_flow ? 'true' : 'false') . ';
    var useProcess = ' . (isset($config->use_process) && $config->use_process ? 'true' : 'false') . ';
    var decisionFlowLoaded = false;
    
    function getCurrentPage() {
        // Check URL parameter first
        var urlParams = new URLSearchParams(window.location.search);
        var gfPage = urlParams.get("gf_page");
        if (gfPage) {
            return parseInt(gfPage);
        }
        
        // Check Gravity Forms page field
        var pageField = $("#gform_source_page_number_" + formId);
        if (pageField.length && pageField.val()) {
            return parseInt(pageField.val());
        }
        
        // Check visible elements
        var form = $("#gform_" + formId);
        
        // Page 1: Personal info
        if (form.find("#input_" + formId + "_6:visible, #input_" + formId + "_5:visible").length > 0) {
            return 1;
        }
        
        // Page 2: Radio button table
        if (form.find(".gf-table-row:visible").length > 0) {
            return 2;
        }
        
        // Page 3: Summary
        if (form.find("#field_" + formId + "_40:visible").length > 0) {
            return 3;
        }
        
        return 1;
    }
    
    // CLEAR PROCESS DATA WHEN USER NAVIGATES OR CHANGES FORM
    function clearProcessData() {
        console.log("Clearing process data due to form changes");
        
        // Clear all stored process data
        sessionStorage.removeItem("operaton_process_" + formId);
        sessionStorage.removeItem("operaton_dmn_eval_data_" + formId);
        
        // Clear global variables
        if (window["operaton_process_" + formId]) {
            delete window["operaton_process_" + formId];
        }
        
        // Reset decision flow loaded flag
        decisionFlowLoaded = false;
        
        // Clear the summary container
        $("#decision-flow-summary-" + formId).html("");
    }
    
    function handleButtonAndSummary() {
        var currentPage = getCurrentPage();
        var evaluateBtn = $("#operaton-evaluate-" + formId);
        var summaryContainer = $("#decision-flow-summary-" + formId);
        
        console.log("=== BUTTON CONTROL ===");
        console.log("Current page:", currentPage, "Target page:", targetPage);
        console.log("Use process:", useProcess, "Show decision flow:", showDecisionFlow);
        
        // Always hide first
        evaluateBtn.hide();
        summaryContainer.hide();
        
        // Show button ONLY on page 2
        if (currentPage === 2 && targetPage === 2) {
            console.log("✅ Showing evaluate button on page 2");
            
            var form = $("#gform_" + formId);
            
            // SOLUTION: Move button to a guaranteed visible container
            // Try multiple containers in order of preference
            var containers = [
                form.find(".gform_body"),                    // Form body (most reliable)
                form.find(".gform-page"),                    // Current page
                form.find(".gform_wrapper"),                 // Form wrapper
                form                                         // Form itself
            ];
            
            var targetContainer = null;
            for (var i = 0; i < containers.length; i++) {
                if (containers[i].length > 0 && containers[i].is(":visible")) {
                    targetContainer = containers[i];
                    console.log("Using container " + i + ":", targetContainer[0]);
                    break;
                }
            }
            
            if (!targetContainer) {
                console.log("No visible container found, using form body");
                targetContainer = form.find(".gform_body");
            }
            
            // Remove button from any hidden parent and add to visible container
            evaluateBtn.detach().appendTo(targetContainer);
            
            // FORCE ALL PARENT ELEMENTS TO BE VISIBLE
            evaluateBtn.parents().each(function() {
                $(this).css({
                    "display": "block",
                    "visibility": "visible",
                    "opacity": "1"
                });
            });
            
            // Apply button styles
            evaluateBtn.show().css({
                "display": "inline-block !important",
                "visibility": "visible !important",
                "opacity": "1 !important",
                "position": "relative !important",
                "margin": "15px 10px !important",
                "padding": "12px 24px !important",
                "background": "#007ba7 !important",
                "color": "white !important",
                "border": "1px solid #007ba7 !important",
                "border-radius": "4px !important",
                "font-size": "14px !important",
                "font-weight": "normal !important",
                "cursor": "pointer !important",
                "z-index": "1000 !important"
            });
            
            // Force inline styles as backup
            evaluateBtn.attr("style", 
                "display: inline-block !important; " +
                "visibility: visible !important; " +
                "opacity: 1 !important; " +
                "position: relative !important; " +
                "margin: 15px 10px !important; " +
                "padding: 12px 24px !important; " +
                "background: #007ba7 !important; " +
                "color: white !important; " +
                "border: 1px solid #007ba7 !important; " +
                "border-radius: 4px !important; " +
                "font-size: 14px !important; " +
                "cursor: pointer !important; " +
                "z-index: 1000 !important;"
            );
            
            console.log("Button styling complete");
            
        } else if (currentPage === 3 && showDecisionFlow && useProcess) {
            // FIXED: Only show decision flow if BOTH conditions are met:
            // 1. showDecisionFlow is enabled
            // 2. useProcess is true (process execution mode)
            console.log("📋 Page 3: showing decision flow (process execution mode)");
            
            evaluateBtn.remove(); // Remove completely on page 3
            summaryContainer.show();
            
            if (!decisionFlowLoaded) {
                loadDecisionFlowSummary();
                decisionFlowLoaded = true;
            }
            
        } else if (currentPage === 3 && (!useProcess || !showDecisionFlow)) {
            // NEW: Hide decision flow on page 3 if not using process execution
            console.log("⏹️ Page 3: hiding decision flow (direct decision evaluation or disabled)");
            
            evaluateBtn.remove();
            summaryContainer.hide();
            
            // Show a simple message instead
            if (!useProcess) {
                console.log("Direct decision evaluation - no decision flow available");
            }
            
        } else {
            console.log("⏹️ Other page - hiding everything");
            evaluateBtn.hide();
            summaryContainer.hide();
            
            if (currentPage !== 3) {
                decisionFlowLoaded = false;
            }
        }
    }
    
    function loadDecisionFlowSummary() {
        var container = $("#decision-flow-summary-" + formId);
        
        if (container.hasClass("loading")) {
            return;
        }
        
        container.addClass("loading");
        container.html("<p>⏳ Loading decision flow summary...</p>");
        
        // FIXED: Add cache busting to always get fresh data
        $.ajax({
            url: "' . home_url() . '/wp-json/operaton-dmn/v1/decision-flow/" + formId + "?cache_bust=" + Date.now(),
            type: "GET",
            cache: false,
            success: function(response) {
                if (response.success && response.html) {
                    container.html(response.html);
                } else {
                    container.html("<p><em>No decision flow data available.</em></p>");
                }
            },
            error: function() {
                container.html("<p><em>Error loading decision flow summary.</em></p>");
            },
            complete: function() {
                container.removeClass("loading");
            }
        });
    }
    
    // Refresh button handler
    $(document).on("click", ".refresh-decision-flow-controlled", function(e) {
        e.preventDefault();
        
        var button = $(this);
        var originalText = button.text();
        button.text("🔄 Refreshing...").prop("disabled", true);
        
        decisionFlowLoaded = false;
        
        setTimeout(function() {
            loadDecisionFlowSummary();
            button.text(originalText).prop("disabled", false);
        }, 500);
    });
    
    // Initialize after short delay
    setTimeout(handleButtonAndSummary, 500);
    
    // Handle Gravity Forms events
    $(document).on("gform_page_loaded", function(event, form_id, current_page) {
        if (form_id == formId) {
            console.log("GF page loaded:", current_page);
            
            // CLEAR PROCESS DATA when navigating between pages
            if (current_page < 3) {
                clearProcessData();
            }
            
            decisionFlowLoaded = false;
            setTimeout(handleButtonAndSummary, 200);
        }
    });
    
    // CLEAR PROCESS DATA when form inputs change
    $("form#gform_" + formId).on("change", "input, select, textarea", function() {
        console.log("Form input changed, clearing process data");
        clearProcessData();
    });
    
    // Emergency fix for hidden parents
    setInterval(function() {
        var currentPage = getCurrentPage();
        if (currentPage === 2) {
            var btn = $("#operaton-evaluate-" + formId);
            if (btn.length > 0 && !btn.is(":visible")) {
                console.log("Emergency: fixing hidden button");
                
                // Force all parents to be visible
                btn.parents().css({
                    "display": "block !important",
                    "visibility": "visible !important",
                    "opacity": "1 !important"
                });
                
                // Re-apply button styles
                btn.css({
                    "display": "inline-block !important",
                    "visibility": "visible !important",
                    "opacity": "1 !important"
                });
            }
        }
        
        // Remove button on page 3
        if (currentPage === 3) {
            var btn = $("#operaton-evaluate-" + formId);
            if (btn.length > 0) {
                btn.remove();
            }
        }
    }, 2000);
    
    console.log("Button control initialized");
});
</script>

<style>
/* AGGRESSIVE BUTTON AND PARENT VISIBILITY */
#operaton-evaluate-' . $form['id'] . ' {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
    margin: 15px 10px !important;
    padding: 12px 24px !important;
    background: #007ba7 !important;
    color: white !important;
    border: 1px solid #007ba7 !important;
    border-radius: 4px !important;
    font-size: 14px !important;
    cursor: pointer !important;
    z-index: 1000 !important;
}

#operaton-evaluate-' . $form['id'] . ':hover {
    background: #005a7a !important;
}

/* FORCE PARENT CONTAINERS TO BE VISIBLE */
.gform-page-footer,
.gform_page_footer,
.gform_footer {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.operaton-evaluate-btn {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.decision-flow-summary { 
    margin: 20px 0;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
    border-left: 4px solid #0073aa;
}

.decision-flow-summary.loading {
    opacity: 0.7;
    pointer-events: none;
}
</style>
';

    // Always return button + hidden elements + script
    return $button . $evaluate_button . $decision_flow_container . $script;
}

    /**
     * Enhanced configuration retrieval with caching
     */
    private function get_config_by_form_id($form_id, $use_cache = true) {
        static $cache = array();
        
        if ($use_cache && isset($cache[$form_id])) {
            return $cache[$form_id];
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        
        $config = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE form_id = %d", 
            $form_id
        ));
        
        if ($use_cache) {
            $cache[$form_id] = $config;
        }
        
        return $config;
    }
    
    /**
     * Clear configuration cache
     */
    private function clear_config_cache() {
        // This method can be called after saving/deleting configurations
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('operaton_dmn_configs', 'operaton_dmn');
        }
    }
    
    public function register_rest_routes() {
        // Register the REST API route - make sure this matches what we're calling from frontend
        register_rest_route('operaton-dmn/v1', '/evaluate', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_evaluation'),
            'permission_callback' => '__return_true',
            'args' => array(
                'config_id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
                'form_data' => array(
                    'required' => true,
                    'type' => 'object',
                )
            )
        ));
        
        // Add a test endpoint for debugging
        register_rest_route('operaton-dmn/v1', '/test', array(
            'methods' => 'GET',
            'callback' => function() {
                return array('status' => 'Plugin REST API is working!');
            },
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Enhanced evaluation handler with process support
     */
    public function handle_evaluation($request) {
        try {
            $params = $request->get_json_params();
            
            if (!isset($params['config_id']) || !isset($params['form_data'])) {
                return new WP_Error('missing_params', 'Configuration ID and form data are required', array('status' => 400));
            }
            
            $config = $this->get_configuration($params['config_id']);
            if (!$config) {
                return new WP_Error('invalid_config', 'Configuration not found', array('status' => 404));
            }
            
            // NEW: Check if we should use process execution
            $use_process = isset($config->use_process) ? $config->use_process : false;
            
            if ($use_process && !empty($config->process_key)) {
                return $this->handle_process_execution($config, $params['form_data']);
            } else {
                return $this->handle_decision_evaluation($config, $params['form_data']);
            }
            
        } catch (Exception $e) {
            return new WP_Error('server_error', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * NEW: Handle process execution
     * FIXED: Handle process execution with correct endpoint construction
     * UPDATED: Handle process execution using history API for completed processes
     */
private function handle_process_execution($config, $form_data) {
    $field_mappings = json_decode($config->field_mappings, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_mappings', 'Invalid field mappings configuration', array('status' => 500));
    }

    // Process input variables
    $variables = array();
    
    foreach ($field_mappings as $dmn_variable => $form_field) {
        $value = isset($form_data[$dmn_variable]) ? $form_data[$dmn_variable] : null;
        
        if ($value === null || $value === 'null' || $value === '') {
            $variables[$dmn_variable] = array(
                'value' => null,
                'type' => $form_field['type']
            );
            continue;
        }
        
        // Type conversion
        switch ($form_field['type']) {
            case 'Integer':
                $value = intval($value);
                break;
            case 'Double':
                $value = floatval($value);
                break;
            case 'Boolean':
                if (is_string($value)) {
                    $value = strtolower($value);
                    $value = ($value === 'true' || $value === '1') ? true : false;
                }
                break;
            default:
                $value = sanitize_text_field($value);
        }
        
        $variables[$dmn_variable] = array(
            'value' => $value,
            'type' => $form_field['type']
        );
    }

    // Build process start endpoint
    $base_url = rtrim($config->dmn_endpoint, '/');
    $base_url = str_replace('/decision-definition/key', '', $base_url);
    $base_url = str_replace('/decision-definition', '', $base_url);
    
    if (strpos($base_url, '/engine-rest') === false) {
        $base_url .= '/engine-rest';
    }
    
    $process_endpoint = $base_url . '/process-definition/key/' . $config->process_key . '/start';
    
    error_log('Operaton DMN: Starting process at: ' . $process_endpoint);
    
    // Start the process
    $process_data = array('variables' => $variables);
    
    $response = wp_remote_post($process_endpoint, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ),
        'body' => wp_json_encode($process_data),
        'timeout' => 30,
        'sslverify' => false,
    ));
    
    if (is_wp_error($response)) {
        return new WP_Error('api_error', 'Failed to start process: ' . $response->get_error_message(), array('status' => 500));
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($http_code !== 200 && $http_code !== 201) {
        return new WP_Error('api_error', sprintf('Process start failed with status %d: %s', $http_code, $body), array('status' => 500));
    }
    
    $process_result = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_response', 'Invalid JSON response from process start', array('status' => 500));
    }
    
    $process_instance_id = $process_result['id'];
    $process_ended = isset($process_result['ended']) ? $process_result['ended'] : false;
    
    error_log('Operaton DMN: Process started with ID: ' . $process_instance_id . ', ended: ' . ($process_ended ? 'true' : 'false'));
    
    $final_variables = array();
    
    if ($process_ended) {
        // Process completed immediately - get variables from history
        error_log('Operaton DMN: Process completed immediately, getting variables from history');
        
        $history_endpoint = $base_url . '/history/variable-instance';
        $history_url = $history_endpoint . '?processInstanceId=' . $process_instance_id;
        
        error_log('Operaton DMN: Getting historical variables from: ' . $history_url);
        
        $history_response = wp_remote_get($history_url, array(
            'headers' => array('Accept' => 'application/json'),
            'timeout' => 15,
            'sslverify' => false,
        ));
        
        if (is_wp_error($history_response)) {
            error_log('Operaton DMN: Failed to get historical variables: ' . $history_response->get_error_message());
            return new WP_Error('api_error', 'Failed to get historical variables: ' . $history_response->get_error_message(), array('status' => 500));
        }
        
        $history_body = wp_remote_retrieve_body($history_response);
        $historical_variables = json_decode($history_body, true);
        
        error_log('Operaton DMN: Historical variables response: ' . $history_body);
        
    if (json_last_error() === JSON_ERROR_NONE && is_array($historical_variables)) {
        // FIXED: Convert historical variables with proper error checking
        foreach ($historical_variables as $var) {
            // Add proper checks for array keys
            if (isset($var['name']) && array_key_exists('value', $var)) {
                $final_variables[$var['name']] = array(
                    'value' => $var['value'],
                    'type' => isset($var['type']) ? $var['type'] : 'String'
                );
            }
        }
    }
        
    } else {
        // Process is still running - wait and try to get active variables
        error_log('Operaton DMN: Process still running, waiting for completion');
        sleep(3);
        
        $variables_endpoint = $base_url . '/process-instance/' . $process_instance_id . '/variables';
        
        $variables_response = wp_remote_get($variables_endpoint, array(
            'headers' => array('Accept' => 'application/json'),
            'timeout' => 15,
            'sslverify' => false,
        ));
        
        if (!is_wp_error($variables_response)) {
            $variables_body = wp_remote_retrieve_body($variables_response);
            $final_variables = json_decode($variables_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $final_variables = array();
            }
        }
        
        // If active variables failed, try history as fallback
        if (empty($final_variables)) {
            error_log('Operaton DMN: Active variables failed, trying history as fallback');
            
            $history_endpoint = $base_url . '/history/variable-instance';
            $history_url = $history_endpoint . '?processInstanceId=' . $process_instance_id;
            
            $history_response = wp_remote_get($history_url, array(
                'headers' => array('Accept' => 'application/json'),
                'timeout' => 15,
                'sslverify' => false,
            ));
            
            if (!is_wp_error($history_response)) {
                $history_body = wp_remote_retrieve_body($history_response);
                $historical_variables = json_decode($history_body, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($historical_variables)) {
                    foreach ($historical_variables as $var) {
                        if (isset($var['name']) && isset($var['value'])) {
                            $final_variables[$var['name']] = array(
                                'value' => $var['value'],
                                'type' => isset($var['type']) ? $var['type'] : 'String'
                            );
                        }
                    }
                }
            }
        }
    }
    
    error_log('Operaton DMN: Final variables after processing: ' . print_r($final_variables, true));
    
// Process results based on configured mappings
$result_mappings = json_decode($config->result_mappings, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $result_mappings = array();
}

$results = array();

foreach ($result_mappings as $dmn_result_field => $mapping) {
    $result_value = null;
    
    // Strategy 1: Direct variable access
    if (isset($final_variables[$dmn_result_field]['value'])) {
        $result_value = $final_variables[$dmn_result_field]['value'];
        error_log('Operaton DMN: Found direct result for ' . $dmn_result_field . ': ' . print_r($result_value, true));
    } elseif (isset($final_variables[$dmn_result_field])) {
        $result_value = $final_variables[$dmn_result_field];
        error_log('Operaton DMN: Found simple result for ' . $dmn_result_field . ': ' . print_r($result_value, true));
    }
    
    // Strategy 2: Search in nested result objects (for DMN array results)
    if ($result_value === null) {
        // Look in heusdenpasResult, kindpakketResult, finalResult, etc.
        $possible_containers = array(
            'heusdenpasResult',
            'kindpakketResult', 
            'finalResult',
            'autoApprovalResult',
            'knockoffsResult'
        );
        
        foreach ($possible_containers as $container) {
            if (isset($final_variables[$container]['value']) && is_array($final_variables[$container]['value'])) {
                $container_data = $final_variables[$container]['value'];
                
                // Check if it's an array of results
                if (isset($container_data[0]) && is_array($container_data[0])) {
                    if (isset($container_data[0][$dmn_result_field])) {
                        $result_value = $container_data[0][$dmn_result_field];
                        error_log('Operaton DMN: Found nested result for ' . $dmn_result_field . ' in ' . $container . ': ' . print_r($result_value, true));
                        break;
                    }
                }
                // Also check direct access in case it's not nested
                elseif (isset($container_data[$dmn_result_field])) {
                    $result_value = $container_data[$dmn_result_field];
                    error_log('Operaton DMN: Found container result for ' . $dmn_result_field . ' in ' . $container . ': ' . print_r($result_value, true));
                    break;
                }
            }
        }
    }
    
    // Strategy 3: Search ALL variables for the field name (comprehensive search)
    if ($result_value === null) {
        foreach ($final_variables as $var_name => $var_data) {
            if (isset($var_data['value']) && is_array($var_data['value'])) {
                // Check if it's an array of objects
                if (isset($var_data['value'][0]) && is_array($var_data['value'][0])) {
                    if (isset($var_data['value'][0][$dmn_result_field])) {
                        $result_value = $var_data['value'][0][$dmn_result_field];
                        error_log('Operaton DMN: Found comprehensive result for ' . $dmn_result_field . ' in ' . $var_name . ': ' . print_r($result_value, true));
                        break;
                    }
                }
            }
        }
    }
    
    // Convert result value if found
    if ($result_value !== null) {
        // Handle boolean conversion (DMN often returns 1/0 instead of true/false)
        if (is_numeric($result_value) && ($result_value === 1 || $result_value === 0 || $result_value === '1' || $result_value === '0')) {
            $result_value = (bool) $result_value;
        }
        
        $results[$dmn_result_field] = array(
            'value' => $result_value,
            'field_id' => $mapping['field_id']
        );
        error_log('Operaton DMN: Final processed result for ' . $dmn_result_field . ': ' . print_r($result_value, true));
    } else {
        error_log('Operaton DMN: No result found for ' . $dmn_result_field . ' after comprehensive search');
        
        // Debug: Show what variables are available
        error_log('Operaton DMN: Available variables: ' . implode(', ', array_keys($final_variables)));
    }
}

// Store process instance ID for decision flow retrieval
$this->store_process_instance_id($config->form_id, $process_instance_id);

return array(
    'success' => true,
    'results' => $results,
    'process_instance_id' => $process_instance_id,
    'debug_info' => defined('WP_DEBUG') && WP_DEBUG ? array(
        'variables_sent' => $variables,
        'process_result' => $process_result,
        'final_variables' => $final_variables,
        'endpoint_used' => $process_endpoint,
        'process_ended_immediately' => $process_ended,
        'result_mappings' => $result_mappings,
        'extraction_summary' => array(
            'total_variables_found' => count($final_variables),
            'results_extracted' => count($results),
            'result_fields_searched' => array_keys($result_mappings)
        )
    ) : null
);
}

/**
 * Add this missing method to your OperatonDMNEvaluator class
 * Insert this method right after your handle_evaluation() method
 */

/**
 * Handle direct decision evaluation (your original logic)
 */
private function handle_decision_evaluation($config, $form_data) {
    $field_mappings = json_decode($config->field_mappings, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_mappings', 'Invalid field mappings configuration', array('status' => 500));
    }
    
    $result_mappings = json_decode($config->result_mappings, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_result_mappings', 'Invalid result mappings configuration', array('status' => 500));
    }
    
    if (empty($result_mappings)) {
        return new WP_Error('no_result_mappings', 'No result mappings configured', array('status' => 500));
    }
    
    // Process input variables
    $variables = array();
    
    foreach ($field_mappings as $dmn_variable => $form_field) {
        $value = null;
        
        if (isset($form_data[$dmn_variable])) {
            $value = $form_data[$dmn_variable];
        }
        
        if ($value === null || $value === 'null' || $value === '') {
            $variables[$dmn_variable] = array(
                'value' => null,
                'type' => $form_field['type']
            );
            continue;
        }
        
        // Type conversion
        switch ($form_field['type']) {
            case 'Integer':
                if (!is_numeric($value)) {
                    return new WP_Error('invalid_type', sprintf('Value for %s must be numeric', $dmn_variable), array('status' => 400));
                }
                $value = intval($value);
                break;
            case 'Double':
                if (!is_numeric($value)) {
                    return new WP_Error('invalid_type', sprintf('Value for %s must be numeric', $dmn_variable), array('status' => 400));
                }
                $value = floatval($value);
                break;
            case 'Boolean':
                if (is_string($value)) {
                    $value = strtolower($value);
                    if ($value === 'true' || $value === '1') {
                        $value = true;
                    } elseif ($value === 'false' || $value === '0') {
                        $value = false;
                    } else {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if ($value === null) {
                            return new WP_Error('invalid_type', sprintf('Value for %s must be boolean', $dmn_variable), array('status' => 400));
                        }
                    }
                }
                break;
            default:
                $value = sanitize_text_field($value);
        }
        
        $variables[$dmn_variable] = array(
            'value' => $value,
            'type' => $form_field['type']
        );
    }
    
    error_log('Operaton DMN: Variables being sent to DMN engine: ' . print_r($variables, true));
    
    if (empty($variables)) {
        return new WP_Error('no_data', 'No valid form data provided', array('status' => 400));
    }
    
    // Build the full evaluation endpoint
    $evaluation_endpoint = $this->build_evaluation_endpoint($config->dmn_endpoint, $config->decision_key);
    
    error_log('Operaton DMN: Using evaluation endpoint: ' . $evaluation_endpoint);
    
    // Make API call
    $operaton_data = array('variables' => $variables);
    
    $response = wp_remote_post($evaluation_endpoint, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ),
        'body' => wp_json_encode($operaton_data),
        'timeout' => 30,
        'sslverify' => false,
    ));
    
    if (is_wp_error($response)) {
        return new WP_Error('api_error', 'Failed to connect to Operaton API: ' . $response->get_error_message(), array('status' => 500));
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($http_code !== 200) {
        return new WP_Error('api_error', sprintf('API returned status code %d: %s', $http_code, $body), array('status' => 500));
    }
    
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_response', 'Invalid JSON response from Operaton API', array('status' => 500));
    }
    
    // Process results based on configured mappings
    $results = array();
    
    foreach ($result_mappings as $dmn_result_field => $mapping) {
        $result_value = null;
        
        if (isset($data[0][$dmn_result_field]['value'])) {
            $result_value = $data[0][$dmn_result_field]['value'];
        } elseif (isset($data[0][$dmn_result_field])) {
            $result_value = $data[0][$dmn_result_field];
        }
        
        if ($result_value !== null) {
            $results[$dmn_result_field] = array(
                'value' => $result_value,
                'field_id' => $mapping['field_id']
            );
        }
    }
    
    if (empty($results)) {
        return new WP_Error('no_results', 'No valid results found in API response', array('status' => 500));
    }
    
    return array(
        'success' => true,
        'results' => $results,
        'debug_info' => defined('WP_DEBUG') && WP_DEBUG ? array(
            'variables_sent' => $variables,
            'api_response' => $data,
            'endpoint_used' => $evaluation_endpoint,
            'result_mappings' => $result_mappings
        ) : null
    );
}

    /**
     * Store process instance ID for later retrieval
     */
    private function store_process_instance_id($form_id, $process_instance_id) {
        // Store in session or user meta for later retrieval
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['operaton_process_' . $form_id] = $process_instance_id;
        
        // Also store in user meta if user is logged in
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'operaton_process_' . $form_id, $process_instance_id);
        }
    }

    /**
     * Get stored process instance ID
     */
    private function get_process_instance_id($form_id) {
        // Try session first
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['operaton_process_' . $form_id])) {
            return $_SESSION['operaton_process_' . $form_id];
        }
        
        // Try user meta if logged in
        if (is_user_logged_in()) {
            $process_id = get_user_meta(get_current_user_id(), 'operaton_process_' . $form_id, true);
            if ($process_id) {
                return $process_id;
            }
        }
        
        return null;
    }

/**
 * UPDATED: Get decision flow summary HTML with cache busting support
 */
public function get_decision_flow_summary_html($form_id) {
    // CHECK: Only show decision flow for process execution
    $config = $this->get_config_by_form_id($form_id);
    if (!$config || !$config->show_decision_flow || !$config->use_process) {
        error_log('Operaton DMN: Decision flow not available - not using process execution or disabled');
        $result = '<div class="decision-flow-placeholder">' .
               '<h3>🔍 Decision Flow Results</h3>' .
               '<p><em>Decision flow summary is only available for process execution mode.</em></p>' .
               '</div>';
        return $result;
    }
    
    // CACHE BUSTING: Check if cache bust parameter is present
    $cache_bust = isset($_GET['cache_bust']) ? sanitize_text_field($_GET['cache_bust']) : '';
    
    // ADD RATE LIMITING TO PREVENT LOOPS (but allow cache busting)
    $cache_key = 'operaton_decision_flow_' . $form_id;
    if (empty($cache_bust)) {
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            error_log('Operaton DMN: Returning cached decision flow for form ' . $form_id);
            return $cached_result;
        }
    } else {
        error_log('Operaton DMN: Cache busting requested for form ' . $form_id);
        // Clear the existing cache when cache busting
        delete_transient($cache_key);
    }
    
    error_log('Operaton DMN: Loading fresh decision flow for form ' . $form_id);
    
    $process_instance_id = $this->get_process_instance_id($form_id);
    if (!$process_instance_id) {
        $result = '<div class="decision-flow-placeholder">' .
               '<h3>🔍 Decision Flow Results</h3>' .
               '<p><em>Complete the evaluation on the previous step to see the detailed decision flow summary here.</em></p>' .
               '</div>';
        
        // Cache for 1 minute (shorter since user might complete evaluation)
        if (empty($cache_bust)) {
            set_transient($cache_key, $result, 60);
        }
        return $result;
    }
    
    // PREVENT RAPID API CALLS (but allow cache busting)
    $api_cache_key = 'operaton_api_call_' . $process_instance_id;
    if (empty($cache_bust) && get_transient($api_cache_key)) {
        error_log('Operaton DMN: API call rate limited for process ' . $process_instance_id);
        
        $result = '<div class="decision-flow-loading">' .
               '<h3>🔍 Decision Flow Results</h3>' .
               '<p>⏳ Loading decision flow data... Please wait.</p>' .
               '</div>';
        return $result;
    }
    
    // SET API RATE LIMIT (prevent calls for 5 seconds, unless cache busting)
    if (empty($cache_bust)) {
        set_transient($api_cache_key, true, 5);
    }
    
    // Build correct history endpoint
    $base_url = rtrim($config->dmn_endpoint, '/');
    $base_url = str_replace('/decision-definition/key', '', $base_url);
    $base_url = str_replace('/decision-definition', '', $base_url);
    
    if (strpos($base_url, '/engine-rest') === false) {
        $base_url .= '/engine-rest';
    }
    
    $history_endpoint = $base_url . '/history/decision-instance';
    $history_url = $history_endpoint . '?processInstanceId=' . $process_instance_id . '&includeInputs=true&includeOutputs=true';
    
    error_log('Operaton DMN: Getting decision flow from: ' . $history_url);
    
    $response = wp_remote_get($history_url, array(
        'headers' => array('Accept' => 'application/json'),
        'timeout' => 15,
        'sslverify' => false,
    ));
    
    if (is_wp_error($response)) {
        error_log('Operaton DMN: Error retrieving decision flow: ' . $response->get_error_message());
        $result = '<div class="decision-flow-error">' .
               '<h3>🔍 Decision Flow Results</h3>' .
               '<p><em>Error retrieving decision flow: ' . $response->get_error_message() . '</em></p>' .
               '</div>';
        
        // Cache error for 2 minutes (unless cache busting)
        if (empty($cache_bust)) {
            set_transient($cache_key, $result, 120);
        }
        return $result;
    }
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($http_code !== 200) {
        error_log('Operaton DMN: Decision flow API returned status: ' . $http_code);
        $result = '<div class="decision-flow-error">' .
               '<h3>🔍 Decision Flow Results</h3>' .
               '<p><em>Error loading decision flow (HTTP ' . $http_code . '). Please try again.</em></p>' .
               '</div>';
        
        // Cache error for 2 minutes (unless cache busting)
        if (empty($cache_bust)) {
            set_transient($cache_key, $result, 120);
        }
        return $result;
    }
    
    $decision_instances = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Operaton DMN: JSON decode error: ' . json_last_error_msg());
        $result = '<div class="decision-flow-error">' .
               '<h3>🔍 Decision Flow Results</h3>' .
               '<p><em>Error parsing decision flow data.</em></p>' .
               '</div>';
        
        // Cache error for 2 minutes (unless cache busting)
        if (empty($cache_bust)) {
            set_transient($cache_key, $result, 120);
        }
        return $result;
    }
    
    error_log('Operaton DMN: Decision instances count: ' . count($decision_instances));
    
    $result = $this->format_decision_flow_summary($decision_instances, $process_instance_id);
    
    // Cache successful result for 10 minutes (unless cache busting)
    if (empty($cache_bust)) {
        set_transient($cache_key, $result, 600);
    }
    
    return $result;
}

/**
 * UPDATED: Format decision flow with Excel-style table layout
 */
private function format_decision_flow_summary($decision_instances, $process_instance_id) {
    $html = '<h3>🔍 Decision Flow Results Summary</h3>';
    $html .= '<p><strong>Process Instance:</strong> <code>' . esc_html($process_instance_id) . '</code></p>';
    
    if (empty($decision_instances) || !is_array($decision_instances)) {
        $html .= '<div class="decision-flow-empty">';
        $html .= '<p><em>No decision instances found for this process.</em></p>';
        $html .= '</div>';
        return $html;
    }
    
    error_log('Operaton DMN: Processing ' . count($decision_instances) . ' decision instances');
    
    // FILTER 1: Only get instances from Activity_FinalResultCompilation if available
    $filtered_instances = array();
    $has_final_compilation = false;
    
    foreach ($decision_instances as $instance) {
        if (isset($instance['activityId']) && $instance['activityId'] === 'Activity_FinalResultCompilation') {
            $filtered_instances[] = $instance;
            $has_final_compilation = true;
        }
    }
    
    // If no FinalResultCompilation activity, get the latest evaluation for each decision
    if (!$has_final_compilation) {
        error_log('Operaton DMN: No Activity_FinalResultCompilation found, using latest evaluations');
        
        // Group by decision definition key and get the latest evaluation time for each
        $latest_by_decision = array();
        
        foreach ($decision_instances as $instance) {
            if (isset($instance['decisionDefinitionKey']) && isset($instance['evaluationTime'])) {
                $key = $instance['decisionDefinitionKey'];
                $eval_time = $instance['evaluationTime'];
                
                if (!isset($latest_by_decision[$key]) || 
                    strtotime($eval_time) > strtotime($latest_by_decision[$key]['evaluationTime'])) {
                    $latest_by_decision[$key] = $instance;
                }
            }
        }
        
        $filtered_instances = array_values($latest_by_decision);
        error_log('Operaton DMN: Filtered to latest evaluations, count: ' . count($filtered_instances));
    } else {
        error_log('Operaton DMN: Using Activity_FinalResultCompilation instances, count: ' . count($filtered_instances));
    }
    
    if (empty($filtered_instances)) {
        $html .= '<p><em>No relevant decision instances found.</em></p>';
        return $html;
    }
    
    // Sort by evaluation time
    usort($filtered_instances, function($a, $b) {
        $timeA = isset($a['evaluationTime']) ? strtotime($a['evaluationTime']) : 0;
        $timeB = isset($b['evaluationTime']) ? strtotime($b['evaluationTime']) : 0;
        return $timeA - $timeB;
    });
    
    // Group decisions by decision definition key for cleaner display
    $decisions_by_key = array();
    foreach ($filtered_instances as $instance) {
        if (isset($instance['decisionDefinitionKey'])) {
            $key = $instance['decisionDefinitionKey'];
            if (!isset($decisions_by_key[$key])) {
                $decisions_by_key[$key] = array();
            }
            $decisions_by_key[$key][] = $instance;
        }
    }
    
    // TOP SECTION: Summary Statistics + Status + Refresh Button
    $html .= '<div class="decision-flow-header" style="background: #f0f8ff; padding: 15px; border-radius: 6px; border-left: 4px solid #0073aa; margin-bottom: 20px;">';
    
    // SUMMARY STATISTICS AT THE TOP
    $html .= '<div class="decision-flow-summary-stats" style="margin-bottom: 15px;">';
    $html .= '<h4 style="margin: 0 0 10px 0;">📊 Summary</h4>';
    $html .= '<ul style="margin: 0; padding-left: 20px;">';
    $html .= '<li><strong>Total Decision Types:</strong> ' . count($decisions_by_key) . '</li>';
    $html .= '<li><strong>Total Evaluations Shown:</strong> ' . count($filtered_instances) . '</li>';
    $html .= '<li><strong>Total Available:</strong> ' . count($decision_instances) . '</li>';
    $html .= '<li><strong>Filter Applied:</strong> ' . ($has_final_compilation ? 'Activity_FinalResultCompilation only' : 'Latest evaluation per decision') . '</li>';
    $html .= '</ul>';
    $html .= '</div>';
    
    // STATUS LINE
    $html .= '<p style="margin: 10px 0;"><strong>Showing:</strong> ' . ($has_final_compilation ? 'Final compilation results' : 'Latest evaluation for each decision') . '</p>';
    
    // REFRESH BUTTON
    $html .= '<button type="button" class="button refresh-decision-flow-controlled" data-form-id="8" style="margin-top: 10px;">';
    $html .= '🔄 Refresh Decision Flow';
    $html .= '</button>';
    $html .= '</div>';
    
    // MAIN SECTION: Excel-style Decision Tables
    $html .= '<div class="decision-flow-tables">';
    
    $step = 1;
    foreach ($decisions_by_key as $decision_key => $instances) {
        // Only show the first instance for each decision (since we filtered to latest/final)
        $instance = $instances[0];
        
        $html .= '<div class="decision-table-container">';
        $html .= '<h4 class="decision-table-title">' . $step . '. ' . esc_html($decision_key) . '</h4>';
        
        // Create Excel-style table
        $html .= '<table class="decision-table excel-style">';
        
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
        if (isset($instance['inputs']) && is_array($instance['inputs']) && count($instance['inputs']) > 0) {
            $input_count = count($instance['inputs']);
            $first_input = true;
            
            foreach ($instance['inputs'] as $input) {
                $html .= '<tr class="input-row">';
                
                // Row header (only on first input row)
                if ($first_input) {
                    $html .= '<td class="row-header inputs-header" rowspan="' . $input_count . '">📥 Inputs</td>';
                    $first_input = false;
                } else {
                    // Empty cell for subsequent rows (handled by rowspan)
                }
                
                // Variable name
                $name = 'Unknown Input';
                if (isset($input['clauseName']) && !empty($input['clauseName'])) {
                    $name = $input['clauseName'];
                } elseif (isset($input['name']) && !empty($input['name'])) {
                    $name = $input['name'];
                }
                $html .= '<td class="variable-cell">' . esc_html($name) . '</td>';
                
                // Value
                $value = 'null';
                if (array_key_exists('value', $input)) {
                    if (is_null($input['value']) || $input['value'] === '') {
                        $value = '<em class="null-value">null</em>';
                    } elseif (is_bool($input['value'])) {
                        $icon = $input['value'] ? '✅' : '❌';
                        $text = $input['value'] ? 'true' : 'false';
                        $value = $icon . ' ' . $text;
                    } elseif (is_array($input['value'])) {
                        $value = esc_html(json_encode($input['value']));
                    } else {
                        $value = esc_html((string) $input['value']);
                    }
                }
                $html .= '<td class="value-cell">' . $value . '</td>';
                
                $html .= '</tr>';
            }
        }
        
        // OUTPUTS Section
        if (isset($instance['outputs']) && is_array($instance['outputs']) && count($instance['outputs']) > 0) {
            $output_count = count($instance['outputs']);
            $first_output = true;
            
            foreach ($instance['outputs'] as $output) {
                $html .= '<tr class="output-row">';
                
                // Row header (only on first output row)
                if ($first_output) {
                    $html .= '<td class="row-header outputs-header" rowspan="' . $output_count . '">📤 Outputs</td>';
                    $first_output = false;
                } else {
                    // Empty cell for subsequent rows (handled by rowspan)
                }
                
                // Variable name
                $name = 'Unknown Output';
                if (isset($output['clauseName']) && !empty($output['clauseName'])) {
                    $name = $output['clauseName'];
                } elseif (isset($output['variableName']) && !empty($output['variableName'])) {
                    $name = $output['variableName'];
                } elseif (isset($output['name']) && !empty($output['name'])) {
                    $name = $output['name'];
                }
                $html .= '<td class="variable-cell">' . esc_html($name) . '</td>';
                
                // Value with enhanced formatting
                $value = '';
                if (array_key_exists('value', $output)) {
                    if (is_null($output['value']) || $output['value'] === '') {
                        $value = '<em class="null-value">null</em>';
                    } elseif (is_bool($output['value'])) {
                        $icon = $output['value'] ? '✅' : '❌';
                        $text = $output['value'] ? 'true' : 'false';
                        $value = '<span class="boolean-value ' . ($output['value'] ? 'true' : 'false') . '">' . $icon . ' ' . $text . '</span>';
                    } elseif (is_numeric($output['value'])) {
                        $value = '<span class="numeric-value">' . esc_html((string) $output['value']) . '</span>';
                    } elseif (is_array($output['value'])) {
                        $value = '<span class="array-value">' . esc_html(json_encode($output['value'])) . '</span>';
                    } else {
                        $value = '<span class="string-value">' . esc_html((string) $output['value']) . '</span>';
                    }
                } else {
                    $value = '<em class="no-value">no value</em>';
                }
                $html .= '<td class="value-cell">' . $value . '</td>';
                
                $html .= '</tr>';
            }
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        // Metadata footer
// Metadata footer (UPDATED with timezone conversion)
$html .= '<div class="decision-metadata">';
if (isset($instance['evaluationTime'])) {
    $formatted_time = $this->format_evaluation_time($instance['evaluationTime']);
    $html .= '<small><strong>⏱️ Evaluation Time:</strong> ' . esc_html($formatted_time) . '</small>';
}
if (isset($instance['activityId'])) {
    $html .= '<small style="margin-left: 15px;"><strong>🔧 Activity:</strong> ' . esc_html($instance['activityId']) . '</small>';
}
$html .= '</div>';        
        $html .= '</div>'; // Close decision-table-container
        
        $step++;
    }
    
    $html .= '</div>'; // Close decision-flow-tables
    
    // Enhanced Excel-style CSS
    $html .= '<style>
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
        
        /* Value type styling */
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
        
        /* Header styling */
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
        
        /* Responsive design */
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
        
        /* Print styling */
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
    
    return $html;
}

    /**
     * Updated AJAX handler for testing DMN endpoints with URL construction
     */
    public function ajax_test_endpoint() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'operaton_test_endpoint')) {
            wp_die('Security check failed');
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $endpoint = sanitize_url($_POST['endpoint']);
        
        if (empty($endpoint)) {
            wp_send_json_error(array('message' => __('Endpoint URL is required.', 'operaton-dmn')));
        }
        
        // Test the endpoint with a simple OPTIONS request first
        $response = wp_remote_request($endpoint, array(
            'method' => 'OPTIONS',
            'timeout' => 10,
            'sslverify' => false, // Only for development
        ));
        
        if (is_wp_error($response)) {
            // Try a HEAD request if OPTIONS fails
            $response = wp_remote_head($endpoint, array(
                'timeout' => 10,
                'sslverify' => false,
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Connection failed: %s', 'operaton-dmn'), $response->get_error_message())
                ));
            }
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code >= 200 && $http_code < 300) {
            wp_send_json_success(array(
                'message' => __('Connection successful! Endpoint is reachable.', 'operaton-dmn')
            ));
        } elseif ($http_code === 405) {
            // Method not allowed is actually good - means endpoint exists
            wp_send_json_success(array(
                'message' => __('Endpoint is reachable (Method Not Allowed is expected for evaluation endpoints).', 'operaton-dmn')
            ));
        } elseif ($http_code === 404) {
            wp_send_json_error(array(
                'message' => sprintf(__('Endpoint not found (404). Please check your base URL and decision key.', 'operaton-dmn'))
            ));
        } else {
            wp_send_json_error(array(
                'message' => sprintf(__('Endpoint returned status code: %d. This may indicate a configuration issue.', 'operaton-dmn'), $http_code)
            ));
        }
    }
    
    /**
     * Method to test a complete endpoint configuration
     */
    public function test_full_endpoint_configuration($base_endpoint, $decision_key) {
        $full_endpoint = $this->build_evaluation_endpoint($base_endpoint, $decision_key);
        
        // Test with minimal DMN evaluation payload
        $test_data = array(
            'variables' => array(
                'test' => array(
                    'value' => 'test',
                    'type' => 'String'
                )
            )
        );
        
        $response = wp_remote_post($full_endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode($test_data),
            'timeout' => 15,
            'sslverify' => false, // Only for development
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message(),
                'endpoint' => $full_endpoint
            );
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Analyze response
        if ($http_code === 200) {
            return array(
                'success' => true,
                'message' => 'Endpoint is working correctly and accepts DMN evaluations.',
                'endpoint' => $full_endpoint
            );
        } elseif ($http_code === 400) {
            // Bad request might mean the decision doesn't exist or input is wrong
            return array(
                'success' => false,
                'message' => 'Endpoint is reachable but decision key may be incorrect or decision table has different input requirements.',
                'endpoint' => $full_endpoint,
                'http_code' => $http_code,
                'response' => $body
            );
        } elseif ($http_code === 404) {
            return array(
                'success' => false,
                'message' => 'Decision not found. Please check your decision key.',
                'endpoint' => $full_endpoint,
                'http_code' => $http_code
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Unexpected response code: ' . $http_code,
                'endpoint' => $full_endpoint,
                'http_code' => $http_code,
                'response' => substr($body, 0, 200) // Truncate long responses
            );
        }
    }
    
    /**
     * WordPress admin AJAX action for comprehensive endpoint testing
     */
    public function ajax_test_full_config() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'operaton_test_endpoint')) {
            wp_die('Security check failed');
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $base_endpoint = sanitize_url($_POST['base_endpoint']);
        $decision_key = sanitize_text_field($_POST['decision_key']);
        
        if (empty($base_endpoint) || empty($decision_key)) {
            wp_send_json_error(array('message' => __('Both base endpoint and decision key are required.', 'operaton-dmn')));
        }
        
        $test_result = $this->test_full_endpoint_configuration($base_endpoint, $decision_key);
        
        if ($test_result['success']) {
            wp_send_json_success($test_result);
        } else {
            wp_send_json_error($test_result);
        }
    }
    
    /**
     * Helper method to get example configurations for documentation
     */
    public function get_endpoint_examples() {
        return array(
            'operaton_cloud' => array(
                'name' => 'Operaton Cloud',
                'base_endpoint' => 'https://your-tenant.operaton.cloud/engine-rest/decision-definition/key/',
                'example_decision_key' => 'loan-approval',
                'full_example' => 'https://your-tenant.operaton.cloud/engine-rest/decision-definition/key/loan-approval/evaluate'
            ),
            'operaton_self_hosted' => array(
                'name' => 'Self-hosted Operaton',
                'base_endpoint' => 'https://operatondev.open-regels.nl/engine-rest/decision-definition/key/',
                'example_decision_key' => 'dish',
                'full_example' => 'https://operatondev.open-regels.nl/engine-rest/decision-definition/key/dish/evaluate'
            ),
            'local_development' => array(
                'name' => 'Local Development',
                'base_endpoint' => 'http://localhost:8080/engine-rest/decision-definition/key/',
                'example_decision_key' => 'my-decision',
                'full_example' => 'http://localhost:8080/engine-rest/decision-definition/key/my-decision/evaluate'
            )
        );
    }
    
    /**
     * Plugin health check
     */
    public function health_check() {
        $issues = array();
        
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
        $response = wp_remote_get($test_url);
        if (is_wp_error($response)) {
            $issues[] = __('REST API is not accessible.', 'operaton-dmn');
        }
        
        return $issues;
    }
    
    /**
     * Add admin notice for health issues
     */
    public function admin_notices() {
        if (current_user_can('manage_options')) {
            $issues = $this->health_check();
            if (!empty($issues)) {
                echo '<div class="notice notice-warning"><p><strong>' . __('Operaton DMN Plugin Issues:', 'operaton-dmn') . '</strong></p><ul>';
                foreach ($issues as $issue) {
                    echo '<li>' . esc_html($issue) . '</li>';
                }
                echo '</ul></div>';
            }
        }
    }
    
    /**
     * Add settings link to plugin page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=operaton-dmn">' . __('Settings', 'operaton-dmn') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Cleanup old data (cron job)
     */
    public function cleanup_old_data() {
        // This could clean up old logs, temporary data, etc.
        // For now, just clear cache
        $this->clear_config_cache();
    }
    
/**
 * Version check with automatic migration
 */
public function check_version() {
    $installed_version = get_option('operaton_dmn_version', '1.0.0-beta.1');
    
    if (version_compare($installed_version, OPERATON_DMN_VERSION, '<')) {
        // Run database migration for any version upgrade
        $this->check_and_update_database();
        
        // Update stored version
        update_option('operaton_dmn_version', OPERATON_DMN_VERSION);
        
        error_log('Operaton DMN: Upgraded from ' . $installed_version . ' to ' . OPERATON_DMN_VERSION);
    }
}
    
    /**
     * Handle database upgrades between versions
     */
    private function upgrade_database($from_version) {
        // Add any database schema changes here for future versions
        
        if (version_compare($from_version, '1.0.0-beta.3', '<')) {
            // Any upgrade logic for beta.3
            error_log('Operaton DMN: Upgraded to version ' . OPERATON_DMN_VERSION);
        }
    }
}

/**
 * NEW: Add REST endpoint for decision flow data
 */
add_action('rest_api_init', function() {
    register_rest_route('operaton-dmn/v1', '/decision-flow/(?P<form_id>\d+)', array(
        'methods' => 'GET',
        'callback' => function($request) {
            $form_id = $request['form_id'];
            
            // Get the plugin instance and call the public method
            $plugin = OperatonDMNEvaluator::get_instance();
            $html = $plugin->get_decision_flow_summary_html($form_id);
            
            return array(
                'success' => true,
                'html' => $html
            );
        },
        'permission_callback' => '__return_true'
    ));
});

// Add AJAX handler for clearing update cache
add_action('wp_ajax_operaton_clear_update_cache', function() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    // Clear WordPress update transients
    delete_site_transient('update_plugins');
    delete_transient('operaton_dmn_updater');
    delete_transient('operaton_dmn_fallback_check');
    
    // Force WordPress to check for updates
    wp_update_plugins();
    
    wp_send_json_success(array('message' => 'Update cache cleared'));
});

// Initialize the plugin
OperatonDMNEvaluator::get_instance();

// Create necessary directories and files
register_activation_hook(__FILE__, 'operaton_dmn_create_files');

function operaton_dmn_create_files() {
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/operaton-dmn/';
    
    if (!file_exists($plugin_dir)) {
        wp_mkdir_p($plugin_dir);
    }
}