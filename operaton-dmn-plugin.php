<?php
/**
 * Plugin Name: Operaton DMN Evaluator
 * Plugin URI: https://github.com/yourorg/operaton-dmn-evaluator
 * Description: WordPress plugin to integrate Gravity Forms with Operaton DMN decision tables for dynamic form evaluations.
 * Version: 1.0.0-beta.1
 * Author: Steven Gort assisted by Claude.ai
 * License: GPL v2 or later
 * Text Domain: operaton-dmn
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OPERATON_DMN_VERSION', '1.0.0-beta.1');
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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Gravity Forms integration
        add_action('gform_enqueue_scripts', array($this, 'enqueue_gravity_scripts'), 10, 2);
        add_filter('gform_submit_button', array($this, 'add_evaluate_button'), 10, 2);
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        load_plugin_textdomain('operaton-dmn', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function activate() {
        $this->create_database_tables();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_database_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        
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
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
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
    
    private function get_gravity_forms() {
        if (!class_exists('GFAPI')) {
            return array();
        }
        
        $forms = GFAPI::get_forms();
        return $forms;
    }
    
    private function save_configuration($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        
        $config_data = array(
            'name' => sanitize_text_field($data['name']),
            'form_id' => intval($data['form_id']),
            'dmn_endpoint' => esc_url_raw($data['dmn_endpoint']),
            'decision_key' => sanitize_text_field($data['decision_key']),
            'field_mappings' => wp_json_encode($data['field_mappings']),
            'result_field' => sanitize_text_field($data['result_field']),
            'button_text' => sanitize_text_field($data['button_text'])
        );
        
        if (isset($data['config_id']) && !empty($data['config_id'])) {
            $wpdb->update($table_name, $config_data, array('id' => intval($data['config_id'])));
            $message = __('Configuration updated successfully!', 'operaton-dmn');
        } else {
            $wpdb->insert($table_name, $config_data);
            $message = __('Configuration saved successfully!', 'operaton-dmn');
        }
        
        echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
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
    
    private function delete_config($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        $wpdb->delete($table_name, array('id' => intval($id)));
        echo '<div class="notice notice-success"><p>' . __('Configuration deleted successfully!', 'operaton-dmn') . '</p></div>';
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'operaton-dmn-frontend',
            OPERATON_DMN_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            OPERATON_DMN_VERSION,
            true
        );
        
        wp_localize_script('operaton-dmn-frontend', 'operaton_ajax', array(
            'url' => rest_url('operaton-dmn/v1/evaluate'),
            'nonce' => wp_create_nonce('wp_rest')
        ));
        
        // Enqueue admin styles
        if (is_admin()) {
            wp_enqueue_style(
                'operaton-dmn-admin',
                OPERATON_DMN_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                OPERATON_DMN_VERSION
            );
        }
    }
    
    public function enqueue_gravity_scripts($form, $is_ajax) {
        $config = $this->get_config_by_form_id($form['id']);
        if ($config) {
            wp_localize_script('operaton-dmn-frontend', 'operaton_config_' . $form['id'], array(
                'config_id' => $config->id,
                'button_text' => $config->button_text,
                'field_mappings' => json_decode($config->field_mappings, true)
            ));
        }
    }
    
    public function add_evaluate_button($button, $form) {
        $config = $this->get_config_by_form_id($form['id']);
        if ($config) {
            $evaluate_button = '<input type="button" id="operaton-evaluate-' . $form['id'] . '" value="' . esc_attr($config->button_text) . '" class="gform_button button operaton-evaluate-btn" data-form-id="' . $form['id'] . '" data-config-id="' . $config->id . '" style="margin-left: 10px;">';
            $button .= $evaluate_button;
            $button .= '<div id="operaton-result-' . $form['id'] . '" class="operaton-result" style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; display: none;"><h4>' . __('Result:', 'operaton-dmn') . '</h4><div class="result-content"></div></div>';
        }
        return $button;
    }
    
    private function get_config_by_form_id($form_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE form_id = %d", $form_id));
    }
    
    public function register_rest_routes() {
        register_rest_route('operaton-dmn/v1', '/evaluate', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_evaluation'),
            'permission_callback' => '__return_true',
        ));
    }
    
    public function handle_evaluation($request) {
        $params = $request->get_json_params();
        
        if (!isset($params['config_id']) || !isset($params['form_data'])) {
            return new WP_Error('missing_params', 'Configuration ID and form data are required', array('status' => 400));
        }
        
        $config = $this->get_configuration($params['config_id']);
        if (!$config) {
            return new WP_Error('invalid_config', 'Configuration not found', array('status' => 404));
        }
        
        $field_mappings = json_decode($config->field_mappings, true);
        $variables = array();
        
        // Map form data to DMN variables
        foreach ($field_mappings as $dmn_variable => $form_field) {
            if (isset($params['form_data'][$form_field['field_id']])) {
                $value = $params['form_data'][$form_field['field_id']];
                
                // Type conversion
                switch ($form_field['type']) {
                    case 'Integer':
                        $value = intval($value);
                        break;
                    case 'Double':
                        $value = floatval($value);
                        break;
                    case 'Boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
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
        
        // Prepare data for Operaton API
        $operaton_data = array('variables' => $variables);
        
        // Make API call to Operaton
        $response = wp_remote_post($config->dmn_endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($operaton_data),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'Failed to connect to Operaton API: ' . $response->get_error_message(), array('status' => 500));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', 'Invalid response from Operaton API', array('status' => 500));
        }
        
        // Extract result based on configuration
        $result_value = '';
        if (isset($data[0][$config->result_field]['value'])) {
            $result_value = $data[0][$config->result_field]['value'];
        }
        
        return array(
            'success' => true,
            'result' => $result_value,
            'full_response' => $data
        );
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