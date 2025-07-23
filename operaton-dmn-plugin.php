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

    private $assets;
    private $admin;
    private $database;
    private $api;
    private $gravity_forms;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

/**
 * Get API manager instance for external access
 * Provides access to API functionality for other components
 * 
 * @return Operaton_DMN_API API manager instance
 * @since 1.0.0
 */
public function get_api_instance() {
    return $this->api;
}

/**
 * Backward compatibility: Delegate to API manager
 * 
 * @deprecated Use get_api_instance()->test_full_endpoint_configuration() instead
 */
public function test_full_endpoint_configuration($base_endpoint, $decision_key) {
    return $this->api->test_full_endpoint_configuration($base_endpoint, $decision_key);
}

/**
 * Backward compatibility: Delegate to API manager
 * 
 * @deprecated Use get_api_instance()->get_decision_flow_summary_html() instead
 */
public function get_decision_flow_summary_html($form_id) {
    return $this->api->get_decision_flow_summary_html($form_id);
}

    // =============================================================================
    // CORE WORDPRESS METHODS (Init, Hooks)
    // =============================================================================

    /**
     * Plugin constructor that initializes WordPress hooks and activation handlers.
     * Sets up the plugin instance with all necessary WordPress integration points.
     * 
     * @since 1.0.0
     */
private function __construct() {
    // 1. Load assets manager first
    $this->load_assets_manager();
    
    // 2. Load admin manager second (depends on assets)
    $this->load_admin_manager();

    // 3. Load database manager third
    $this->load_database_manager();

    // 4. Load API manager fourth (depends on core and database)
    $this->load_api_manager();

    // 5. Load Gravity Forms manager fifth (depends on all others)
    $this->load_gravity_forms_manager();

    // Core WordPress hooks
    add_action('init', array($this, 'init'));
    add_action('rest_api_init', array($this, 'register_rest_routes'));
 
    // Database and version checks (admin only)
    if (is_admin()) {
        add_action('admin_init', array($this->database, 'check_and_update_database'), 1);
        add_action('admin_init', array($this, 'check_version'), 5);
    }
    
    // Cleanup scheduled task
    add_action('operaton_dmn_cleanup', array($this->database, 'cleanup_old_data'));
    
    // TEMPORARY: Clear decision flow cache
    add_action('admin_init', function() {
        if (isset($_GET['clear_operaton_cache'])) {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_operaton_%'");
            wp_redirect(admin_url('admin.php?page=operaton-dmn&cache_cleared=1'));
            exit;
        }
    });

    // Plugin lifecycle hooks
    register_activation_hook(__FILE__, array($this, 'activate'));
    register_deactivation_hook(__FILE__, array($this, 'deactivate'));
}

    // NEW: Add this method
    private function load_assets_manager() {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-assets.php';
        $this->assets = new Operaton_DMN_Assets(OPERATON_DMN_PLUGIN_URL, OPERATON_DMN_VERSION);
    }

    // NEW: Add this after loading the assets manager
    private function load_admin_manager() {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-admin.php';
        $this->admin = new Operaton_DMN_Admin($this, $this->assets);
    }

    // NEW: Add this method after load_admin_manager()
    private function load_database_manager() {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-database.php';
        $this->database = new Operaton_DMN_Database(OPERATON_DMN_VERSION);
    }

/**
 * Load API manager for external service integration
 * Handles DMN evaluation, process execution, and decision flow functionality
 * 
 * @since 1.0.0
 */
private function load_api_manager() {
    require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-api.php';
    $this->api = new Operaton_DMN_API($this, $this->database);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Operaton DMN: API manager loaded successfully');
    }
}

/**
 * Load Gravity Forms integration
 * 
 * @since 1.0.0
 */
private function load_gravity_forms_manager() {
    require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-gravity-forms.php';
    $this->gravity_forms = new Operaton_DMN_Gravity_Forms($this, $this->assets, $this->database);
    
    // Set the Gravity Forms manager in the assets manager for form detection
    $this->assets->set_gravity_forms_manager($this->gravity_forms);
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Operaton DMN: Gravity Forms manager loaded successfully');
    }
}

    /**
    * Get database instance for external access
    * Provides access to database manager for other components
    * 
    * @return Operaton_DMN_Database Database manager instance
    * @since 1.0.0
    */
    public function get_database_instance() {
        return $this->database;
    }

    /**
    * Get Gravity Forms instance for external access
    * Provides access to Gravity Forma integration manager for other components
    * 
    * @return Operaton_DMN_Gravity_Forms Database manager instance
    * @since 1.0.0
    */
    public function get_gravity_forms_instance() {
        return $this->gravity_forms;
    }

    /**
     * Initialize plugin textdomain for internationalization support.
     * Loads translation files from the plugin's languages directory.
     * 
     * @since 1.0.0
     */
    public function init() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Initializing plugin textdomain');
        }
        
        load_plugin_textdomain('operaton-dmn', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Enhanced activation hook that creates database tables and sets default options.
     * Initializes plugin data structures and schedules cleanup tasks.
     * 
     * @since 1.0.0
     */
    public function activate() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Plugin activation started');
        }
        
        // Create/update database tables - NOW USES DATABASE CLASS
        $this->database->create_database_tables();
        
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
     * Enhanced deactivation hook that cleans up scheduled events and cached data.
     * Removes plugin-specific cron jobs and clears configuration cache.
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Plugin deactivation started');
        }
        
        // Clear scheduled events
        wp_clear_scheduled_hook('operaton_dmn_cleanup');
        
        // Clear any cached data - NOW USES DATABASE CLASS
        $this->database->clear_configuration_cache();
        
        flush_rewrite_rules();
    }

/**
 * Register REST API routes for DMN evaluation and testing endpoints.
 * Now delegates to API manager for route registration.
 * 
 * @since 1.0.0
 */
public function register_rest_routes() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Operaton DMN: Delegating REST API registration to API manager');
    }
    
    // API manager handles all REST route registration
    if (isset($this->api)) {
        // Routes are registered automatically via API manager hooks
        return;
    }
    
    error_log('Operaton DMN: API manager not available for REST route registration');
}

    /**
     * Version check method that triggers automatic database migration on upgrades.
     * Compares installed version with current version and runs migrations as needed.
     * 
     * @since 1.0.0
     */
    public function check_version() {
        $installed_version = get_option('operaton_dmn_version', '1.0.0-beta.1');
        
        if (version_compare($installed_version, OPERATON_DMN_VERSION, '<')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: Version upgrade detected from ' . $installed_version . ' to ' . OPERATON_DMN_VERSION);
            }
            
            // Run database migration for any version upgrade - NOW USES DATABASE CLASS
            $this->database->check_and_update_database();
            
            // Update stored version
            update_option('operaton_dmn_version', OPERATON_DMN_VERSION);
            
            error_log('Operaton DMN: Upgraded from ' . $installed_version . ' to ' . OPERATON_DMN_VERSION);
        }
    }

/**
 * Ensure frontend assets are loaded when Gravity Forms renders
 * This is a safety net to ensure operaton_ajax is always available
 * 
 * @since 1.0.0
 */
public function force_frontend_assets_on_gravity_forms() {
    if (!is_admin() && class_exists('GFForms')) {
        // Add debug call
        $this->debug_assets_loading();
        
        // Force load frontend assets which includes operaton_ajax localization
        $this->assets->enqueue_frontend_assets();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Forced frontend assets loading due to Gravity Forms presence');
        }
    }
}

    // =============================================================================
    // UTILITY/HELPER METHODS
    // =============================================================================
   
    /**
     * Enhanced configuration retrieval with caching for performance optimization.
     * Gets DMN configuration by form ID with optional caching to reduce database queries.
     * 
     * @param int $form_id Gravity Forms form ID
     * @param bool $use_cache Whether to use cached results
     * @return object|null Configuration object or null if not found
     * @since 1.0.0
     */
    public function get_config_by_form_id($form_id, $use_cache = true) {
        return $this->database->get_config_by_form_id($form_id, $use_cache);
    }

    /**
     * Helper method to get example configurations for documentation and testing.
     * Provides predefined endpoint examples for different Operaton deployment scenarios.
     * 
     * @return array Array of example configuration templates
     * @since 1.0.0
     */
    public function get_endpoint_examples() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Getting endpoint examples');
        }
        
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
     * Plugin health check to identify common configuration and dependency issues.
     * Validates plugin dependencies and database integrity for troubleshooting support.
     * 
     * @return array Array of health issue descriptions
     * @since 1.0.0
     */
    public function health_check() {
        $issues = array();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Performing health check');
        }
        
        // Check if Gravity Forms is active using the new manager
        if (!$this->gravity_forms || !$this->gravity_forms->is_gravity_forms_available()) {
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