<?php

/**
 * Plugin Name: Operaton DMN Evaluator
 * Plugin URI: https://git.open-regels.nl/showcases/operaton-dmn-evaluator
 * Description: WordPress plugin to integrate Gravity Forms with Operaton DMN decision tables for dynamic form evaluations.
 * Version: 1.0.0-beta.10.1
 * Author: Steven Gort
 * Author URI: https://git.open-regels.nl/showcases/operaton-dmn-evaluator
 * License: EU PL v1.2
 * License URI: https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * Text Domain: operaton-dmn
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Update URI: https://git.open-regels.nl/showcases/operaton-dmn-evaluator
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}

// Define plugin constants
define('OPERATON_DMN_VERSION', '1.0.0-beta.10.1');
define('OPERATON_DMN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OPERATON_DMN_PLUGIN_PATH', plugin_dir_path(__FILE__));

// CRITICAL FIX: Load Performance Monitor FIRST, before everything else
$performance_file = OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-performance.php';
if (file_exists($performance_file))
{
    require_once $performance_file;

    if (defined('WP_DEBUG') && WP_DEBUG)
    {
        error_log('Operaton DMN: Performance monitor loaded successfully');
    }
}
else
{
    if (defined('WP_DEBUG') && WP_DEBUG)
    {
        error_log('Operaton DMN: Performance monitor file not found at: ' . $performance_file);
    }
}

// Initialize the update checker - CLEAN VERSION
if (is_admin())
{
    // Only load auto-updater in admin context
    $updater_file = OPERATON_DMN_PLUGIN_PATH . 'includes/plugin-updater.php';

    if (file_exists($updater_file))
    {
        require_once $updater_file;

        // IMPORTANT: Initialize with the MAIN plugin file, not the updater file
        new OperatonDMNAutoUpdater(__FILE__, OPERATON_DMN_VERSION);

        // Log successful loading if debug is enabled
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Auto-updater loaded successfully');
            error_log('Operaton DMN: Plugin file for updater: ' . __FILE__);
            error_log('Operaton DMN: Plugin basename: ' . plugin_basename(__FILE__));
        }

        // Add debug information to admin
        add_action('admin_footer', function ()
        {
            if (current_user_can('manage_options') && isset($_GET['page']) && strpos($_GET['page'], 'operaton-dmn') !== false)
            {
                echo '<script>console.log("Operaton DMN Auto-Updater: Loaded");</script>';
            }
        });
    }
    else
    {
        // Log missing file if debug is enabled
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Auto-updater file not found at: ' . $updater_file);
        }

        // Show admin notice about missing auto-updater
        add_action('admin_notices', function ()
        {
            if (current_user_can('manage_options'))
            {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>Operaton DMN Evaluator:</strong> Auto-update system files are missing. Please reinstall the plugin to enable automatic updates.</p>';
                echo '</div>';
            }
        });
    }

    // Load debug tools if in debug mode (remove in production)
    if (defined('WP_DEBUG') && WP_DEBUG)
    {
        error_log('Operaton DMN: WP_DEBUG is enabled, attempting to load debug tools');
        $debug_file = OPERATON_DMN_PLUGIN_PATH . 'includes/update-debug.php';
        error_log('Operaton DMN: Debug file path: ' . $debug_file);

        if (file_exists($debug_file))
        {
            error_log('Operaton DMN: Debug file exists, loading...');
            require_once $debug_file;
            error_log('Operaton DMN: Debug file loaded successfully');
        }
        else
        {
            error_log('Operaton DMN: Debug file NOT found at: ' . $debug_file);
        }
    }
}

/**
 * Main plugin class
 */
class OperatonDMNEvaluator
{

    /**
     * Performance monitor instance
     */
    private $performance;

    /**
     * Single instance of the plugin
     *
     * @var OperatonDMNEvaluator|null
     * @since 1.0.0
     */
    private static $instance = null;

    /**
     * Initialization flag to prevent multiple setups
     */
    private static $initialized = false;

    /**
     * Assets manager instance
     * Handles CSS and JavaScript loading
     *
     * @var Operaton_DMN_Assets
     * @since 1.0.0
     */
    private $assets;

    /**
     * Admin interface manager instance
     * Handles WordPress admin interface
     *
     * @var Operaton_DMN_Admin
     * @since 1.0.0
     */
    private $admin;

    /**
     * Database manager instance
     * Handles all database operations
     *
     * @var Operaton_DMN_Database
     * @since 1.0.0
     */
    private $database;

    /**
     * API manager instance
     * Handles external API calls and REST endpoints
     *
     * @var Operaton_DMN_API
     * @since 1.0.0
     */
    private $api;

    /**
     * Gravity Forms integration manager instance
     * Handles all Gravity Forms integration
     *
     * @var Operaton_DMN_Gravity_Forms
     * @since 1.0.0
     */
    private $gravity_forms;

    /**
     * Quirks Mode Fix manager instance
     * Handles DOCTYPE and jQuery compatibility issues
     *
     * @var Operaton_DMN_Quirks_Fix
     * @since 1.0.0
     */
    private $quirks_fix;

    /**
     * Get singleton instance
     *
     * @return OperatonDMNEvaluator
     * @since 1.0.0
     */
    public static function get_instance()
    {
        if (null === self::$instance)
        {
            self::$instance = new self();
        }
        return self::$instance;
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
    private function __construct()
    {
        // FIXED: Initialize performance monitoring FIRST
        if (class_exists('Operaton_DMN_Performance_Monitor'))
        {
            $this->performance = Operaton_DMN_Performance_Monitor::get_instance();
            $this->performance->mark('plugin_construct_start', 'Main plugin constructor started');
        }
        else
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN: Performance monitor class not available');
            }
        }

        // Prevent multiple initializations
        if (self::$initialized)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN: Preventing duplicate initialization');
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Starting fresh initialization - v' . OPERATON_DMN_VERSION);
        }

        // NEW: Load quirks fix manager FIRST (before assets)
        $this->load_quirks_fix_manager();
        if ($this->performance) $this->performance->mark('quirks_fix_loaded');

        // 1. Load assets manager first
        $this->load_assets_manager();
        if ($this->performance) $this->performance->mark('assets_manager_loaded');

        // 2. Load admin manager second (depends on assets)
        $this->load_admin_manager();
        if ($this->performance) $this->performance->mark('admin_manager_loaded');

        // 3. Load database manager third
        $this->load_database_manager();
        if ($this->performance) $this->performance->mark('database_manager_loaded');

        // 4. Load API manager fourth (depends on core and database)
        $this->load_api_manager();
        if ($this->performance) $this->performance->mark('api_manager_loaded');

        // 5. Load Gravity Forms manager fifth (depends on all others)
        $this->load_gravity_forms_manager();
        if ($this->performance) $this->performance->mark('gravity_forms_manager_loaded');

        // Core WordPress hooks
        add_action('init', array($this, 'init'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Database and version checks (admin only)
        if (is_admin())
        {
            add_action('admin_init', array($this->database, 'check_and_update_database'), 1);
            add_action('admin_init', array($this, 'check_version'), 5);
        }

        // Cleanup scheduled task
        add_action('operaton_dmn_cleanup', array($this->database, 'cleanup_old_data'));

        // TEMPORARY: Clear decision flow cache
        add_action('admin_init', function ()
        {
            if (isset($_GET['clear_operaton_cache']))
            {
                global $wpdb;
                $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_operaton_%'");
                wp_redirect(admin_url('admin.php?page=operaton-dmn&cache_cleared=1'));
                exit;
            }
        });

        // FORCE LOAD FRONTEND ASSETS - This should fix operaton_ajax issue
        add_action('wp_enqueue_scripts', array($this, 'force_frontend_assets_on_gravity_forms'), 20);

        // Emergency fallback for operaton_ajax
        add_action('wp_head', array($this, 'emergency_operaton_ajax_fallback'), 1);

        // Plugin lifecycle hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // FIXED: Add performance hooks if available
        if ($this->performance)
        {
            add_action('wp_loaded', array($this, 'mark_wp_loaded'));
            add_action('shutdown', array($this, 'store_performance_data'));
        }

        // Mark as initialized
        self::$initialized = true;

        if ($this->performance)
        {
            $this->performance->mark('plugin_construct_complete', 'Main plugin constructor completed');
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: ✅ Initialization complete');
        }
    }

    /**
     * Mark WordPress loaded
     */
    public function mark_wp_loaded()
    {
        if ($this->performance)
        {
            $this->performance->mark('wp_loaded', 'WordPress fully loaded');
        }
    }

    /**
     * Store performance data on shutdown
     */
    public function store_performance_data()
    {
        if ($this->performance)
        {
            $this->performance->store_performance_stats();
        }
    }

    /**
     * Get performance instance
     */
    public function get_performance_instance()
    {
        return $this->performance;
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    // =============================================================================
    // MANAGER LOADING METHODS
    // =============================================================================

    /**
     * NEW METHOD: Load quirks fix manager
     * Add this new method to your class
     */
    private function load_quirks_fix_manager()
    {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-quirks-fix.php';
        $this->quirks_fix = new Operaton_DMN_Quirks_Fix();

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Quirks fix manager loaded successfully');
        }
    }

    /**
     * NEW METHOD: Get quirks fix manager instance
     * Add this accessor method
     */
    public function get_quirks_fix_instance()
    {
        return $this->quirks_fix;
    }

    /**
     * Load assets manager for CSS/JavaScript handling
     *
     * @since 1.0.0
     */
    private function load_assets_manager()
    {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-assets.php';
        $this->assets = new Operaton_DMN_Assets(OPERATON_DMN_PLUGIN_URL, OPERATON_DMN_VERSION);

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Assets manager loaded successfully');
        }
    }

    /**
     * Load admin manager for WordPress admin interface
     *
     * @since 1.0.0
     */
    private function load_admin_manager()
    {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-admin.php';
        $this->admin = new Operaton_DMN_Admin($this, $this->assets);

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Admin manager loaded successfully');
        }
    }

    /**
     * Load database manager for all database operations
     *
     * @since 1.0.0
     */
    private function load_database_manager()
    {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-database.php';
        $this->database = new Operaton_DMN_Database(OPERATON_DMN_VERSION);

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Database manager loaded successfully');
        }
    }

    /**
     * Load API manager for external service integration
     * Handles DMN evaluation, process execution, and decision flow functionality
     *
     * @since 1.0.0
     */
    private function load_api_manager()
    {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-api.php';
        $this->api = new Operaton_DMN_API($this, $this->database);

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: API manager loaded successfully');
        }
    }

    /**
     * Load Gravity Forms integration manager
     *
     * @since 1.0.0
     */
    private function load_gravity_forms_manager()
    {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-gravity-forms.php';
        $this->gravity_forms = new Operaton_DMN_Gravity_Forms($this, $this->assets, $this->database);

        // Set the Gravity Forms manager in the assets manager for form detection
        $this->assets->set_gravity_forms_manager($this->gravity_forms);

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Gravity Forms manager loaded successfully');
        }
    }

    // =============================================================================
    // MANAGER ACCESSOR METHODS
    // =============================================================================

    /**
     * Get API manager instance for external access
     * Provides access to API functionality for other components
     *
     * @return Operaton_DMN_API API manager instance
     * @since 1.0.0
     */
    public function get_api_instance()
    {
        return $this->api;
    }

    /**
     * Get database instance for external access
     * Provides access to database manager for other components
     *
     * @return Operaton_DMN_Database Database manager instance
     * @since 1.0.0
     */
    public function get_database_instance()
    {
        return $this->database;
    }

    /**
     * Get Gravity Forms instance for external access
     * Provides access to Gravity Forms integration manager for other components
     *
     * @return Operaton_DMN_Gravity_Forms Gravity Forms manager instance
     * @since 1.0.0
     */
    public function get_gravity_forms_instance()
    {
        return $this->gravity_forms;
    }

    /**
     * Get assets manager instance for external access
     *
     * @return Operaton_DMN_Assets Assets manager instance
     * @since 1.0.0
     */
    public function get_assets_instance()
    {
        return $this->assets;
    }

    /**
     * Get admin manager instance for external access
     *
     * @return Operaton_DMN_Admin Admin manager instance
     * @since 1.0.0
     */
    public function get_admin_instance()
    {
        return $this->admin;
    }

    // =============================================================================
    // CORE WORDPRESS INTEGRATION METHODS
    // =============================================================================

    /**
     * Initialize plugin textdomain for internationalization support.
     * Loads translation files from the plugin's languages directory.
     * Enhanced init method with performance tracking
     *
     * @since 1.0.0
     */
    public function init()
    {
        if ($this->performance)
        {
            $timer_id = $this->performance->start_timer('plugin_init');
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Initializing plugin textdomain');
        }

        load_plugin_textdomain('operaton-dmn', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        if ($this->performance && isset($timer_id))
        {
            $this->performance->stop_timer($timer_id, 'Textdomain loaded');
        }
    }

    /**
     * Register REST API routes for DMN evaluation and testing endpoints.
     * Now delegates to API manager for route registration.
     *
     * @since 1.0.0
     */
    public function register_rest_routes()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Delegating REST API registration to API manager');
        }

        // API manager handles all REST route registration
        if (isset($this->api))
        {
            // Routes are registered automatically via API manager hooks
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: API manager not available for REST route registration');
        }
    }

    /**
     * Version check method that triggers automatic database migration on upgrades.
     * Compares installed version with current version and runs migrations as needed.
     *
     * @since 1.0.0
     */
    public function check_version()
    {
        $installed_version = get_option('operaton_dmn_version', '1.0.0-beta.1');

        if (version_compare($installed_version, OPERATON_DMN_VERSION, '<'))
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN: Version upgrade detected from ' . $installed_version . ' to ' . OPERATON_DMN_VERSION);
            }

            // Run database migration for any version upgrade
            $this->database->check_and_update_database();

            // Update stored version
            update_option('operaton_dmn_version', OPERATON_DMN_VERSION);

            error_log('Operaton DMN: Upgraded from ' . $installed_version . ' to ' . OPERATON_DMN_VERSION);
        }
    }

    // =============================================================================
    // PLUGIN LIFECYCLE METHODS
    // =============================================================================

    /**
     * Enhanced activation hook that creates database tables and sets default options.
     * Initializes plugin data structures and schedules cleanup tasks.
     * Enhanced activate method with performance tracking
     *
     * @since 1.0.0
     */
    public function activate()
    {
        if ($this->performance)
        {
            $timer_id = $this->performance->start_timer('plugin_activation');
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Plugin activation started');
        }

        // EXISTING ACTIVATION CODE
        $this->database->create_database_tables();
        add_option('operaton_dmn_version', OPERATON_DMN_VERSION);
        add_option('operaton_dmn_activated', current_time('mysql'));

        if (!wp_next_scheduled('operaton_dmn_cleanup'))
        {
            wp_schedule_event(time(), 'daily', 'operaton_dmn_cleanup');
        }

        flush_rewrite_rules();

        if ($this->performance && isset($timer_id))
        {
            $this->performance->stop_timer($timer_id, 'Plugin activation completed');
        }
    }

    /**
     * Enhanced deactivation hook that cleans up scheduled events and cached data.
     * Removes plugin-specific cron jobs and clears configuration cache.
     *
     * @since 1.0.0
     */
    public function deactivate()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Plugin deactivation started');
        }

        // Clear scheduled events
        wp_clear_scheduled_hook('operaton_dmn_cleanup');

        // Clear any cached data
        $this->database->clear_configuration_cache();

        flush_rewrite_rules();
    }

    // =============================================================================
    // ASSET LOADING AND FRONTEND METHODS
    // =============================================================================

    /**
     * Ensure frontend assets are loaded when Gravity Forms renders
     * This is a safety net to ensure operaton_ajax is always available
     *
     * @since 1.0.0
     */
    /**
     * ENHANCED: Ensure frontend assets are loaded when Gravity Forms renders
     * This fixes the operaton_ajax not being available issue
     */
    public function force_frontend_assets_on_gravity_forms()
    {
        // Skip in admin
        if (is_admin())
        {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('🚀 OPERATON DMN: force_frontend_assets_on_gravity_forms called!');
            error_log('🚀 OPERATON DMN: GFForms available = ' . (class_exists('GFForms') ? 'TRUE' : 'FALSE'));
        }

        // Check multiple conditions for when to force load assets
        $should_load = false;

        // Condition 1: Gravity Forms is available
        if (class_exists('GFForms'))
        {
            $should_load = true;
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('🚀 OPERATON DMN: GFForms class exists - will load assets');
            }
        }

        // Condition 2: Current page has Gravity Forms content
        global $post;
        if ($post)
        {
            if (
                has_shortcode($post->post_content, 'gravityform') ||
                has_block('gravityforms/form', $post)
            )
            {
                $should_load = true;
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('🚀 OPERATON DMN: GF shortcode/block detected - will load assets');
                }
            }
        }

        // Condition 3: GF preview page
        if (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview')
        {
            $should_load = true;
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('🚀 OPERATON DMN: GF preview page - will load assets');
            }
        }

        if ($should_load && isset($this->assets))
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('🚀 OPERATON DMN: Loading frontend assets...');
            }

            // CRITICAL: Ensure assets are loaded with high priority
            add_action('wp_enqueue_scripts', function ()
            {
                $this->assets->enqueue_frontend_assets();
            }, 5); // High priority

            // ALSO: Load immediately if we're past wp_enqueue_scripts
            if (did_action('wp_enqueue_scripts'))
            {
                $this->assets->enqueue_frontend_assets();
            }

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('🚀 OPERATON DMN: Frontend assets loading initiated');
            }
        }
        else
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('🚀 OPERATON DMN: Conditions not met for asset loading');
                error_log('  - Should load: ' . ($should_load ? 'YES' : 'NO'));
                error_log('  - Assets manager: ' . (isset($this->assets) ? 'AVAILABLE' : 'NOT AVAILABLE'));
            }
        }
    }

    /**
     * Emergency fallback for operaton_ajax availability
     * Ensures operaton_ajax is always available even if normal loading fails
     *
     * @since 1.0.0
     */
    /**
     * ENHANCED: Emergency fallback with better detection
     */
    public function emergency_operaton_ajax_fallback()
    {
        // Skip in admin
        if (is_admin())
        {
            return;
        }

        // Only add fallback if we detect Gravity Forms on the page
        global $post;
        $has_gf = false;

        if ($post)
        {
            $has_gf = has_shortcode($post->post_content, 'gravityform') ||
                has_block('gravityforms/form', $post);
        }

        if (!$has_gf && !class_exists('GFForms'))
        {
            return;
        }

?>
        <script type="text/javascript">
            /* Operaton DMN Enhanced Emergency Fallback */
            (function() {
                'use strict';

                // Wait a bit to see if operaton_ajax loads normally
                setTimeout(function() {
                    if (typeof window.operaton_ajax === 'undefined') {
                        console.log('🆘 Emergency: operaton_ajax not found, creating fallback');

                        window.operaton_ajax = {
                            url: '<?php echo rest_url('operaton-dmn/v1/evaluate'); ?>',
                            nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
                            debug: <?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>,
                            strings: {
                                evaluating: <?php echo json_encode(__('Evaluating...', 'operaton-dmn')); ?>,
                                error: <?php echo json_encode(__('Evaluation failed', 'operaton-dmn')); ?>,
                                success: <?php echo json_encode(__('Evaluation completed', 'operaton-dmn')); ?>,
                                loading: <?php echo json_encode(__('Loading...', 'operaton-dmn')); ?>,
                                no_config: <?php echo json_encode(__('Configuration not found', 'operaton-dmn')); ?>,
                                validation_failed: <?php echo json_encode(__('Please fill in all required fields', 'operaton-dmn')); ?>,
                                connection_error: <?php echo json_encode(__('Connection error. Please try again.', 'operaton-dmn')); ?>
                            },
                            emergency_mode: true
                        };

                        console.log('🆘 Emergency operaton_ajax created:', window.operaton_ajax);

                        // Trigger custom event to notify scripts
                        if (typeof jQuery !== 'undefined') {
                            jQuery(document).trigger('operaton_ajax_emergency_loaded');
                        }
                    }
                }, 1000);
            })();
        </script>
<?php
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
    public function get_config_by_form_id($form_id, $use_cache = true)
    {
        if (!$this->database)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN: Database manager not available for config retrieval');
            }
            return null;
        }

        return $this->database->get_config_by_form_id($form_id, $use_cache);
    }

    /**
     * Helper method to get example configurations for documentation and testing.
     * Provides predefined endpoint examples for different Operaton deployment scenarios.
     *
     * @return array Array of example configuration templates
     * @since 1.0.0
     */
    public function get_endpoint_examples()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
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
    public function health_check()
    {
        $issues = array();

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Performing health check');
        }

        // Check if Gravity Forms is active using the new manager
        if (!$this->gravity_forms || !$this->gravity_forms->is_gravity_forms_available())
        {
            $issues[] = __('Gravity Forms is not active.', 'operaton-dmn');
        }

        // Check database table
        if (!$this->database)
        {
            $issues[] = __('Database manager not initialized.', 'operaton-dmn');
        }
        else
        {
            $health = $this->database->check_database_health();
            if (!empty($health['issues']))
            {
                $issues = array_merge($issues, $health['issues']);
            }
        }

        // Check if REST API is working
        $test_url = rest_url('operaton-dmn/v1/test');
        $response = wp_remote_get($test_url, array('timeout' => 5));
        if (is_wp_error($response))
        {
            $issues[] = __('REST API is not accessible.', 'operaton-dmn');
        }

        // NEW: Check quirks fix status
        if (!$this->quirks_fix)
        {
            $issues[] = __('Quirks fix manager not initialized.', 'operaton-dmn');
        }
        else
        {
            $quirks_status = $this->quirks_fix->get_compatibility_status();
            if (!$quirks_status['quirks_fix_active'])
            {
                $issues[] = __('Quirks mode compatibility fixes not active.', 'operaton-dmn');
            }
        }

        // Check if assets are properly loaded
        if (!$this->assets)
        {
            $issues[] = __('Assets manager not initialized.', 'operaton-dmn');
        }

        return $issues;
    }

    /**
     * Get manager status for debugging
     * Returns the status of all plugin managers
     *
     * @return array Manager status information
     * @since 1.0.0
     */
    public function get_managers_status()
    {
        return array(
            'assets' => isset($this->assets) ? 'loaded' : 'not loaded',
            'admin' => isset($this->admin) ? 'loaded' : 'not loaded',
            'database' => isset($this->database) ? 'loaded' : 'not loaded',
            'api' => isset($this->api) ? 'loaded' : 'not loaded',
            'gravity_forms' => isset($this->gravity_forms) ? 'loaded' : 'not loaded',
            'quirks_fix' => isset($this->quirks_fix) ? 'loaded' : 'not loaded', // NEW
            'performance' => isset($this->performance) ? 'loaded' : 'not loaded', // ADDED
            'gravity_forms_available' => isset($this->gravity_forms) ? $this->gravity_forms->is_gravity_forms_available() : false
        );
    }

    // =============================================================================
    // BACKWARD COMPATIBILITY METHODS
    // =============================================================================

    /**
     * Backward compatibility: Delegate to API manager
     *
     * @deprecated Use get_api_instance()->test_full_endpoint_configuration() instead
     * @param string $base_endpoint Base endpoint URL
     * @param string $decision_key Decision key to test
     * @return array Test results
     * @since 1.0.0
     */
    public function test_full_endpoint_configuration($base_endpoint, $decision_key)
    {
        if (!$this->api)
        {
            return array(
                'success' => false,
                'message' => __('API manager not available', 'operaton-dmn'),
                'endpoint' => $base_endpoint
            );
        }

        return $this->api->test_full_endpoint_configuration($base_endpoint, $decision_key);
    }

    /**
     * Backward compatibility: Delegate to API manager
     *
     * @deprecated Use get_api_instance()->get_decision_flow_summary_html() instead
     * @param int $form_id Form ID for decision flow
     * @return string Decision flow HTML
     * @since 1.0.0
     */
    public function get_decision_flow_summary_html($form_id)
    {
        if (!$this->api)
        {
            return '<div class="operaton-error"><p><em>API manager not available for decision flow.</em></p></div>';
        }

        return $this->api->get_decision_flow_summary_html($form_id);
    }
}

// =============================================================================
// GLOBAL FUNCTIONS AND INITIALIZATION
// =============================================================================

/**
 * Global debug functions for asset loading tracking
 */
function debug_operaton_assets_loading()
{
    if (defined('WP_DEBUG') && WP_DEBUG)
    {
        error_log('=== OPERATON ASSETS DEBUG (EARLY) ===');
        error_log('Is admin: ' . (is_admin() ? 'YES' : 'NO'));
        error_log('Current page: ' . $_SERVER['REQUEST_URI']);
        error_log('Has Gravity Forms: ' . (class_exists('GFForms') ? 'YES' : 'NO'));

        // Check if we have Gravity Forms on page
        global $post;
        if ($post)
        {
            $has_gf_shortcode = has_shortcode($post->post_content, 'gravityform');
            $has_gf_block = has_block('gravityforms/form', $post);
            error_log('Has GF shortcode: ' . ($has_gf_shortcode ? 'YES' : 'NO'));
            error_log('Has GF block: ' . ($has_gf_block ? 'YES' : 'NO'));
            error_log('Post content preview: ' . substr($post->post_content, 0, 200));
        }

        // Check what scripts are registered
        global $wp_scripts;
        $operaton_scripts = array();
        if (isset($wp_scripts->registered))
        {
            foreach ($wp_scripts->registered as $handle => $script)
            {
                if (strpos($handle, 'operaton') !== false)
                {
                    $operaton_scripts[] = $handle . ' (registered)';
                }
            }
        }
        error_log('Operaton scripts found: ' . implode(', ', $operaton_scripts));
        error_log('=====================================');
    }
}

function debug_operaton_assets_loading_late()
{
    if (defined('WP_DEBUG') && WP_DEBUG)
    {
        error_log('=== OPERATON ASSETS DEBUG (LATE) ===');

        global $wp_scripts;
        $operaton_scripts = array();
        if (isset($wp_scripts->registered))
        {
            foreach ($wp_scripts->registered as $handle => $script)
            {
                if (strpos($handle, 'operaton') !== false)
                {
                    $status = wp_script_is($handle, 'enqueued') ? 'ENQUEUED' : 'registered only';
                    $operaton_scripts[] = $handle . ' (' . $status . ')';
                }
            }
        }
        error_log('Final Operaton scripts: ' . implode(', ', $operaton_scripts));

        // Check if frontend script was localized
        if (isset($wp_scripts->registered['operaton-dmn-frontend']))
        {
            $frontend_script = $wp_scripts->registered['operaton-dmn-frontend'];
            error_log('Frontend script localized data: ' . print_r($frontend_script->extra, true));
        }

        error_log('====================================');
    }
}

// Hook the debug functions AFTER the class is defined
add_action('wp_enqueue_scripts', 'debug_operaton_assets_loading', 1);
add_action('wp_enqueue_scripts', 'debug_operaton_assets_loading_late', 999);

// Add AJAX handler for clearing update cache
add_action('wp_ajax_operaton_clear_update_cache', function ()
{
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce'))
    {
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

/**
 * Create necessary plugin files and directories on activation
 *
 * @since 1.0.0
 */
function operaton_dmn_create_files()
{
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/operaton-dmn/';

    if (!file_exists($plugin_dir))
    {
        wp_mkdir_p($plugin_dir);
    }
}

/**
 * Global convenience function to get plugin instance
 *
 * @return OperatonDMNEvaluator
 * @since 1.0.0
 */
function operaton_dmn()
{
    return OperatonDMNEvaluator::get_instance();
}

/**
 * Global convenience function to get quirks fix manager
 *
 * @return Operaton_DMN_Quirks_Fix
 * @since 1.0.0
 */
function operaton_dmn_get_quirks_fix()
{
    $instance = OperatonDMNEvaluator::get_instance();
    return $instance->get_quirks_fix_instance();
}

/**
 * Global convenience function to get a specific manager
 *
 * @param string $manager Manager type (api, database, gravity_forms, assets, admin, performance)
 * @return mixed Manager instance or null
 * @since 1.0.0
 */
function operaton_dmn_get_manager($manager)
{
    $instance = OperatonDMNEvaluator::get_instance();

    switch ($manager)
    {
        case 'api':
            return $instance->get_api_instance();
        case 'database':
            return $instance->get_database_instance();
        case 'gravity_forms':
            return $instance->get_gravity_forms_instance();
        case 'assets':
            return $instance->get_assets_instance();
        case 'admin':
            return $instance->get_admin_instance();
        case 'quirks_fix':
            return $instance->get_quirks_fix_instance();
        case 'performance': // ADDED
            return $instance->get_performance_instance();
        default:
            return null;
    }
}

/**
 * Global debug function for plugin status
 *
 * @since 1.0.0
 */
function operaton_dmn_debug_status()
{
    if (!defined('WP_DEBUG') || !WP_DEBUG)
    {
        return;
    }

    $instance = OperatonDMNEvaluator::get_instance();
    $status = $instance->get_managers_status();
    $health = $instance->health_check();

    error_log('=== OPERATON DMN PLUGIN STATUS ===');
    error_log('Plugin Version: ' . OPERATON_DMN_VERSION);
    error_log('Managers Status: ' . print_r($status, true));

    if (!empty($health))
    {
        error_log('Health Issues: ' . implode(', ', $health));
    }
    else
    {
        error_log('Health Status: All systems operational');
    }

    // ADDED: Performance status
    $performance = $instance->get_performance_instance();
    if ($performance)
    {
        error_log('Performance Monitor: Available');
        $summary = $performance->get_summary();
        error_log('Performance Summary: ' . print_r($summary, true));
    }
    else
    {
        error_log('Performance Monitor: Not available');
    }

    error_log('==================================');
}
