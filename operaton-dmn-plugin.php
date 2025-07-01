<?php
/**
 * Plugin Name: Operaton DMN Evaluator
 * Plugin URI: https://git.open-regels.nl/showcases/operaton-dmn-evaluator
 * Description: WordPress plugin to integrate Gravity Forms with Operaton DMN decision tables for dynamic form evaluations.
 * Version: 1.0.0-beta.3
 * Author: Steven Gort
 * License: EU PL v1.2
 * Text Domain: operaton-dmn
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OPERATON_DMN_VERSION', '1.0.0-beta.3');
define('OPERATON_DMN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OPERATON_DMN_PLUGIN_PATH', plugin_dir_path(__FILE__));

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
        
        // Admin notices and health checks
        add_action('admin_notices', array($this, 'admin_notices'));
        add_action('operaton_dmn_cleanup', array($this, 'cleanup_old_data'));
        
        // Add settings link to plugin page
        $plugin_basename = plugin_basename(__FILE__);
        add_filter("plugin_action_links_$plugin_basename", array($this, 'add_settings_link'));
        
        // Gravity Forms integration - fixed to always load when GF is available
        add_action('init', array($this, 'init_gravity_forms_integration'));
        
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
     * Enhanced database table creation with better error handling
     */
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        
        // Check if table already exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
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
            button_text varchar(255) DEFAULT 'Evaluate',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_form_id (form_id),
            KEY idx_form_id (form_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Log any errors
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
    }
    
    public function admin_page() {
        if (isset($_POST['delete_config']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_config')) {
            $this->delete_config($_POST['config_id']);
        }
        
        $configs = $this->get_all_configurations();
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
     * Enhanced configuration saving with validation
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
            
            for ($i = 0; $i < count($dmn_variables); $i++) {
                $dmn_var = sanitize_text_field(trim($dmn_variables[$i]));
                $field_id = isset($field_ids[$i]) ? sanitize_text_field(trim($field_ids[$i])) : '';
                $type = isset($types[$i]) ? sanitize_text_field($types[$i]) : 'String';
                
                if (!empty($dmn_var) && !empty($field_id)) {
                    $field_mappings[$dmn_var] = array(
                        'field_id' => $field_id,
                        'type' => $type
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
            'button_text' => sanitize_text_field($data['button_text'] ?: 'Evaluate')
        );
        
        $config_id = isset($data['config_id']) ? intval($data['config_id']) : 0;
        
        if ($config_id > 0) {
            // Update existing configuration
            $result = $wpdb->update(
                $table_name, 
                $config_data, 
                array('id' => $config_id),
                array('%s', '%d', '%s', '%s', '%s', '%s', '%s'),
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
                array('%s', '%d', '%s', '%s', '%s', '%s', '%s')
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
     * Enhanced script enqueuing with proper dependencies
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
        
        // Form-specific configuration
        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $field_mappings = array();
        }
        
        wp_localize_script('operaton-dmn-frontend', 'operaton_config_' . $form['id'], array(
            'config_id' => $config->id,
            'button_text' => $config->button_text,
            'field_mappings' => $field_mappings,
            'form_id' => $form['id']
        ));
    }
    
    /**
     * Improved button rendering with better form state detection
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
        
        // Create a more robust button implementation
        $evaluate_button = sprintf(
            '<input type="button" id="operaton-evaluate-%1$d" value="%2$s" class="gform_button button operaton-evaluate-btn" data-form-id="%1$d" data-config-id="%3$d" style="margin-left: 10px;">',
            $form['id'],
            esc_attr($config->button_text),
            $config->id
        );
        
        $result_container = sprintf(
            '<div class="gfield gfield_operaton_result">
                <div id="operaton-result-%1$d" class="ginput_container operaton-result" style="display: none;">
                    <h4>%2$s</h4>
                    <div class="result-content"></div>
                </div>
            </div>',
            $form['id'],
            __('Result:', 'operaton-dmn')
        );
        
        return $button . $evaluate_button . $result_container;
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
            
            $variables = array();
            
            foreach ($field_mappings as $dmn_variable => $form_field) {
                if (isset($params['form_data'][$dmn_variable])) {
                    $value = $params['form_data'][$dmn_variable];
                    
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
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                            if ($value === null) {
                                return new WP_Error('invalid_type', sprintf('Value for %s must be boolean', $dmn_variable), array('status' => 400));
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
            }
            
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
}

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