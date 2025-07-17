<?php
/**
 * Plugin Name: Operaton DMN Evaluator
 * Plugin URI: https://git.open-regels.nl/showcases/operaton-dmn-evaluator
 * Description: WordPress plugin to integrate Gravity Forms with Operaton DMN decision tables for dynamic form evaluations.
 * Version: 1.0.0-beta.8
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
define('OPERATON_DMN_VERSION', '1.0.0-beta.8');
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
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Add AJAX handlers
        add_action('wp_ajax_operaton_test_endpoint', array($this, 'ajax_test_endpoint'));
        add_action('wp_ajax_nopriv_operaton_test_endpoint', array($this, 'ajax_test_endpoint'));
        add_action('wp_ajax_operaton_test_full_config', array($this, 'ajax_test_full_config'));
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
     * Enhanced activation hook
     */
    public function activate() {
        // Create database tables
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
 * Updated database table creation with new fields
 * Replace the create_database_tables method in your main plugin file
 */
private function create_database_tables() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'operaton_dmn_configs';
    
    // Check if table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        // Check if new columns exist, add them if not
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
        
        if (!in_array('result_display_field', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN result_display_field varchar(255) DEFAULT NULL");
        }
        
        if (!in_array('evaluation_step', $columns)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN evaluation_step varchar(10) DEFAULT 'auto'");
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
        result_field varchar(255) NOT NULL,
        result_display_field varchar(255) DEFAULT NULL,
        evaluation_step varchar(10) DEFAULT 'auto',
        button_text varchar(255) DEFAULT 'Evaluate',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_form_id (form_id),
        KEY idx_form_id (form_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);
    
    if (!empty($wpdb->last_error)) {
        error_log('Operaton DMN: Database table creation error: ' . $wpdb->last_error);
    }
    
    return $result;
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
    
public function admin_page() {
    // Check for database update success message
    if (isset($_GET['database_updated'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Database schema updated successfully!', 'operaton-dmn') . '</p></div>';
    }
    
    // Check if database needs updating
    global $wpdb;
    $table_name = $wpdb->prefix . 'operaton_dmn_configs';
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
    $needs_update = !in_array('result_display_field', $columns) || !in_array('evaluation_step', $columns);
    
    if ($needs_update) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . __('Database Update Required', 'operaton-dmn') . '</strong></p>';
        echo '<p>' . __('Your database schema needs to be updated to support the latest features.', 'operaton-dmn') . '</p>';
        echo '<p>';
        echo '<a href="' . wp_nonce_url(admin_url('admin-ajax.php?action=operaton_manual_db_update'), 'operaton_manual_db_update') . '" class="button button-primary">';
        echo __('Update Database Now', 'operaton-dmn');
        echo '</a>';
        echo '</p>';
        echo '</div>';
    }
    
    if (isset($_POST['delete_config']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_config')) {
        $this->delete_config($_POST['config_id']);
    }
    
    $configs = $this->get_all_configurations();
    
    // Show update management section
    $this->show_update_management_section();
    
    include OPERATON_DMN_PLUGIN_PATH . 'templates/admin-list.php';
}

    public function add_config_page() {
        if (isset($_POST['save_config']) && wp_verify_nonce($_POST['_wpnonce'], 'save_config')) {
            $this->save_configuration($_POST);
        }
        
        $gravity_forms = $this->get_gravity_forms();
        $config = isset($_GET['edit']) ? $this->get_configuration($_GET['edit']) : null;
        include OPERATON_DMN_PLUGIN_PATH . 'templates/admin-form.php';
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
     * Enhanced form validation for separated URL components
     */
    private function validate_configuration_data($data) {
        $errors = array();
        
        // Required field validation
        $required_fields = array(
            'name' => __('Configuration Name', 'operaton-dmn'),
            'form_id' => __('Gravity Form', 'operaton-dmn'),
            'dmn_endpoint' => __('DMN Base Endpoint URL', 'operaton-dmn'),
            'decision_key' => __('Decision Key', 'operaton-dmn'),
            'result_field' => __('Result Field Name', 'operaton-dmn')
        );
        
        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = sprintf(__('%s is required.', 'operaton-dmn'), $label);
            }
        }
        
        // URL validation
        if (!empty($data['dmn_endpoint']) && !filter_var($data['dmn_endpoint'], FILTER_VALIDATE_URL)) {
            $errors[] = __('DMN Base Endpoint URL is not valid.', 'operaton-dmn');
        }
        
        // Check that base URL doesn't include the decision key
        if (!empty($data['dmn_endpoint']) && !empty($data['decision_key'])) {
            $base_url = trim($data['dmn_endpoint']);
            $decision_key = trim($data['decision_key']);
            
            // Check if decision key is already in the base URL
            if (strpos($base_url, $decision_key) !== false) {
                $errors[] = sprintf(__('The base endpoint URL should not include the decision key "%s". Please remove it from the URL.', 'operaton-dmn'), $decision_key);
            }
            
            // Check if URL ends with /evaluate (which suggests it's a full endpoint)
            if (substr($base_url, -9) === '/evaluate') {
                $errors[] = __('The base endpoint URL should not include "/evaluate". This will be added automatically.', 'operaton-dmn');
            }
            
            // Suggest proper format if URL structure looks wrong
            if (!preg_match('/\/engine-rest\/decision-definition\/key\/?$/', $base_url)) {
                $errors[] = __('The base endpoint URL should end with "/engine-rest/decision-definition/key/" for Operaton DMN engines.', 'operaton-dmn');
            }
        }
        
        // Decision key validation
        if (!empty($data['decision_key'])) {
            $decision_key = trim($data['decision_key']);
            
            // Basic validation for decision key format
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $decision_key)) {
                $errors[] = __('Decision key should only contain letters, numbers, hyphens, and underscores.', 'operaton-dmn');
            }
            
            // Check for common mistakes
            if (strpos($decision_key, '/') !== false) {
                $errors[] = __('Decision key should not contain forward slashes.', 'operaton-dmn');
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
        
        // Field mappings validation
        $has_mappings = false;
        if (isset($data['field_mappings_dmn_variable']) && is_array($data['field_mappings_dmn_variable'])) {
            $dmn_variables = $data['field_mappings_dmn_variable'];
            $field_ids = isset($data['field_mappings_field_id']) ? $data['field_mappings_field_id'] : array();
            
            for ($i = 0; $i < count($dmn_variables); $i++) {
                $dmn_var = trim($dmn_variables[$i]);
                $field_id = isset($field_ids[$i]) ? trim($field_ids[$i]) : '';
                
                if (!empty($dmn_var) && !empty($field_id)) {
                    $has_mappings = true;
                    
                    // Validate field ID is numeric
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
                                $errors[] = sprintf(__('Field ID "%s" does not exist in the selected form.', 'operaton-dmn'), $field_id);
                            }
                        }
                    }
                }
            }
        }
        
        if (!$has_mappings) {
            $errors[] = __('At least one field mapping is required.', 'operaton-dmn');
        }
        
        return $errors;
    }
    
/**
 * Updated configuration saving with new fields
 * Update the save_configuration method
 */
private function save_configuration($data) {
    // Validate data (existing validation code...)
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
    
    // Process field mappings with radio button names
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
                    'radio_name' => $radio_name // Store the custom radio button name
                );
            }
        }
    }
    
    $config_data = array(
        'name' => sanitize_text_field($data['name']),
        'form_id' => intval($data['form_id']),
        'dmn_endpoint' => esc_url_raw($data['dmn_endpoint']),
        'decision_key' => sanitize_text_field($data['decision_key']),
        'field_mappings' => wp_json_encode($field_mappings),
        'result_field' => sanitize_text_field($data['result_field']),
        'result_display_field' => isset($data['result_display_field']) ? sanitize_text_field($data['result_display_field']) : '',
        'evaluation_step' => isset($data['evaluation_step']) ? sanitize_text_field($data['evaluation_step']) : 'auto',
        'button_text' => sanitize_text_field($data['button_text'] ?: 'Evaluate')
    );
    
    $config_id = isset($data['config_id']) ? intval($data['config_id']) : 0;
    
    if ($config_id > 0) {
        // Update existing configuration
        $result = $wpdb->update(
            $table_name, 
            $config_data, 
            array('id' => $config_id),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
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
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
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
 * Check and update database schema
 */
public function check_and_update_database() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'operaton_dmn_configs';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        // Table doesn't exist, create it
        $this->create_database_tables();
        return;
    }
    
    // Get current columns
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
    
    // Add missing columns
    if (!in_array('result_display_field', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN result_display_field varchar(255) DEFAULT NULL AFTER result_field");
        error_log('Operaton DMN: Added result_display_field column');
    }
    
    if (!in_array('evaluation_step', $columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN evaluation_step varchar(10) DEFAULT 'auto' AFTER result_display_field");
        error_log('Operaton DMN: Added evaluation_step column');
    }
    
    // Check for any errors
    if (!empty($wpdb->last_error)) {
        error_log('Operaton DMN: Database update error: ' . $wpdb->last_error);
    } else {
        error_log('Operaton DMN: Database schema updated successfully');
    }
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
 * Enhanced script enqueuing with result field configuration
 * Replace the enqueue_gravity_scripts method in your main plugin file
 */
public function enqueue_gravity_scripts($form, $is_ajax) {
    $config = $this->get_config_by_form_id($form['id']);
    if (!$config) {
        return;
    }
    
    // Ensure jQuery is loaded
    wp_enqueue_script('jquery');
    
    // Enqueue our frontend script with proper dependencies
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
    
    // Localize script with better error handling
    wp_localize_script('operaton-dmn-frontend', 'operaton_ajax', array(
        'url' => rest_url('operaton-dmn/v1/evaluate'),
        'nonce' => wp_create_nonce('wp_rest'),
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    ));
    
    // Form-specific configuration with new fields
    $field_mappings = json_decode($config->field_mappings, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $field_mappings = array();
    }
    
    wp_localize_script('operaton-dmn-frontend', 'operaton_config_' . $form['id'], array(
        'config_id' => $config->id,
        'button_text' => $config->button_text,
        'field_mappings' => $field_mappings,
        'form_id' => $form['id'],
        'result_display_field' => isset($config->result_display_field) ? $config->result_display_field : '',
        'evaluation_step' => isset($config->evaluation_step) ? $config->evaluation_step : 'auto',
        'result_field_name' => $config->result_field
    ));
}

/**
 * Fixed button rendering for multi-page forms
 * Replace the add_evaluate_button method in your main plugin file
 */
public function add_evaluate_button($button, $form) {
    // Don't add button in admin/editor context
    if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
        return $button;
    }
    
    $config = $this->get_config_by_form_id($form['id']);
    if (!$config) {
        return $button;
    }
    
    // Get current page and total pages
    $current_page = isset($_GET['gf_page']) ? intval($_GET['gf_page']) : 1;
    $total_pages = 1;
    
    // Count total pages by looking for page break fields
    if (isset($form['fields'])) {
        foreach ($form['fields'] as $field) {
            if ($field->type === 'page') {
                $total_pages++;
            }
        }
    }
    
    // Determine which page should have the evaluate button
    $evaluation_step = isset($config->evaluation_step) ? $config->evaluation_step : 'auto';
    
    if ($evaluation_step === 'auto') {
        // Auto-detect: put evaluate button on the second-to-last page
        $evaluate_page = max(1, $total_pages - 1);
    } else {
        $evaluate_page = intval($evaluation_step);
    }
    
    // Create the evaluate button
    $evaluate_button = sprintf(
        '<input type="button" id="operaton-evaluate-%1$d" value="%2$s" class="gform_button gform-theme-button operaton-evaluate-btn" data-form-id="%1$d" data-config-id="%3$d" style="margin-right: 10px;">',
        $form['id'],
        esc_attr($config->button_text),
        $config->id
    );
    
    // For single page forms
    if ($total_pages <= 1) {
        return sprintf(
            '<div class="gform_footer top_label">
                <div class="gform_button_wrapper gform_button_select_wrapper">
                    %s
                    %s
                </div>
            </div>',
            $evaluate_button,
            $button
        );
    }
    
    // For multi-page forms - add evaluate button on the appropriate page
    if ($current_page == $evaluate_page) {
        // This is the page that should have the evaluate button
        // Add it alongside the existing Next button
        return sprintf(
            '%s
            <div style="margin-top: 10px;">
                %s
            </div>
            <style>
                .operaton-evaluate-btn { 
                    background-color: #007ba7 !important;
                    border-color: #007ba7 !important;
                    margin-top: 10px !important;
                }
            </style>',
            $button,  // Original button (Next/Previous)
            $evaluate_button
        );
    } else {
        // Other pages should show normal buttons
        return $button;
    }
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
     * Enhanced API call handling with separated URL construction
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
            
            $field_mappings = json_decode($config->field_mappings, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('invalid_mappings', 'Invalid field mappings configuration', array('status' => 500));
            }
            
// Replace the variable processing section in your handle_evaluation method:

$variables = array();

// Process ALL mapped fields, ensuring each one is included
foreach ($field_mappings as $dmn_variable => $form_field) {
    $value = null; // Default to null
    
    // Check if data was provided for this variable
    if (isset($params['form_data'][$dmn_variable])) {
        $value = $params['form_data'][$dmn_variable];
    }
    
    // Handle explicit null values
    if ($value === null || $value === 'null' || $value === '') {
        $variables[$dmn_variable] = array(
            'value' => null,
            'type' => $form_field['type']
        );
        continue;
    }
    
    // Enhanced type conversion with validation
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
            // Handle string boolean values
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

// Log the variables being sent for debugging
error_log('Operaton DMN: Variables being sent to DMN engine: ' . print_r($variables, true));

if (empty($variables)) {
    return new WP_Error('no_data', 'No valid form data provided', array('status' => 400));
}            
            // Build the full evaluation endpoint
            $evaluation_endpoint = $this->build_evaluation_endpoint($config->dmn_endpoint, $config->decision_key);
            
            error_log('Operaton DMN: Using evaluation endpoint: ' . $evaluation_endpoint);
            
            // Make API call with better error handling
            $operaton_data = array('variables' => $variables);
            
            $response = wp_remote_post($evaluation_endpoint, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ),
                'body' => wp_json_encode($operaton_data),
                'timeout' => 30,
                'sslverify' => false, // Only for development
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
            
            // Extract result with better error handling
            $result_value = '';
            if (isset($data[0][$config->result_field]['value'])) {
                $result_value = $data[0][$config->result_field]['value'];
            } elseif (isset($data[0][$config->result_field])) {
                $result_value = $data[0][$config->result_field];
            } else {
                return new WP_Error('result_not_found', sprintf('Result field "%s" not found in API response', $config->result_field), array('status' => 500));
            }
            
            return array(
                'success' => true,
                'result' => $result_value,
                'debug_info' => defined('WP_DEBUG') && WP_DEBUG ? array(
                    'variables_sent' => $variables,
                    'api_response' => $data,
                    'endpoint_used' => $evaluation_endpoint
                ) : null
            );
            
        } catch (Exception $e) {
            return new WP_Error('server_error', $e->getMessage(), array('status' => 500));
        }
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
     * Add version check for database migrations
     */
    public function check_version() {
        $installed_version = get_option('operaton_dmn_version', '1.0.0-beta.1');
        
        if (version_compare($installed_version, OPERATON_DMN_VERSION, '<')) {
            // Run any necessary upgrades
            $this->upgrade_database($installed_version);
            
            // Update stored version
            update_option('operaton_dmn_version', OPERATON_DMN_VERSION);
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