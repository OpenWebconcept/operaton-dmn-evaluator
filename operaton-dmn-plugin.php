<?php
/**
 * Plugin Name: Operaton DMN Evaluator
 * Description: Gravity Forms integration with Operaton DMN Engine
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: operaton-dmn
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OPERATON_DMN_VERSION', '1.0.0');
define('OPERATON_DMN_PLUGIN_FILE', __FILE__);
define('OPERATON_DMN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OPERATON_DMN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OPERATON_DMN_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Operaton_DMN_Plugin {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('operaton-dmn', false, dirname(OPERATON_DMN_PLUGIN_BASENAME) . '/languages');
        
        // Check if Gravity Forms is active
        if (!class_exists('GFForms')) {
            add_action('admin_notices', array($this, 'gravity_forms_required_notice'));
            return;
        }
        
        // Include required files
        $this->includes();
        
        // Initialize components
        if (is_admin()) {
            new Operaton_DMN_Admin();
        }
        
        new Operaton_DMN_Frontend();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once OPERATON_DMN_PLUGIN_DIR . 'includes/class-database.php';
        require_once OPERATON_DMN_PLUGIN_DIR . 'includes/class-api.php';
        
        if (is_admin()) {
            require_once OPERATON_DMN_PLUGIN_DIR . 'admin/class-admin.php';
        }
        
        require_once OPERATON_DMN_PLUGIN_DIR . 'public/class-frontend.php';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $database = new Operaton_DMN_Database();
        $database->create_tables();
        
        // Set default options
        add_option('operaton_dmn_version', OPERATON_DMN_VERSION);
        
        // Flush rewrite rules if needed
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        flush_rewrite_rules();
    }
    
    /**
     * Show notice if Gravity Forms is not active
     */
    public function gravity_forms_required_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php 
                printf(
                    __('Operaton DMN Evaluator requires %s to be installed and activated.', 'operaton-dmn'),
                    '<strong>' . __('Gravity Forms', 'operaton-dmn') . '</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}

// Initialize the plugin
Operaton_DMN_Plugin::get_instance();