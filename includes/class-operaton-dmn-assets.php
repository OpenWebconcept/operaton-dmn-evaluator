<?php
/**
 * Assets Manager for Operaton DMN Plugin
 * 
 * Handles proper enqueueing of CSS and JavaScript files with dependency management,
 * conditional loading, and performance optimization.
 * 
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Operaton_DMN_Assets {
    
    /**
     * Plugin URL for asset paths
     * Base URL for loading CSS and JavaScript files
     * 
     * @var string
     * @since 1.0.0
     */
    private $plugin_url;
    
    /**
     * Plugin version for cache busting
     * Used to force browser cache updates when plugin is updated
     * 
     * @var string
     * @since 1.0.0
     */
    private $version;
    
    /**
     * Asset loading flags to prevent duplicate enqueuing
     * Tracks which asset groups have been loaded to avoid conflicts
     * 
     * @var array
     * @since 1.0.0
     */
    private $loaded_assets = array();

    /**
     * Gravity Forms manager instance for form detection
     * 
     * @var Operaton_DMN_Gravity_Forms|null
     * @since 1.0.0
     */
    private $gravity_forms_manager = null;

    /**
     * Constructor for assets manager with plugin information
     * Initializes asset management system with base configuration
     * 
     * @param string $plugin_url Plugin base URL for asset paths
     * @param string $version Plugin version for cache busting
     * @since 1.0.0
     */
    public function __construct($plugin_url, $version) {
        $this->plugin_url = trailingslashit($plugin_url);
        $this->version = $version;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Manager initialized with version ' . $version);
        }
        
        $this->init_hooks();
    }

    /**
     * Set Gravity Forms manager instance
     * Allows assets manager to check for DMN-enabled forms
     * 
     * @param Operaton_DMN_Gravity_Forms $gravity_forms_manager Gravity Forms manager instance
     * @since 1.0.0
     */
    public function set_gravity_forms_manager($gravity_forms_manager) {
        $this->gravity_forms_manager = $gravity_forms_manager;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Gravity Forms manager set successfully');
        }
    }

    public function debug_assets_loading() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('=== ASSETS MANAGER DEBUG ===');
        error_log('Is admin: ' . (is_admin() ? 'YES' : 'NO'));
        error_log('Gravity Forms manager available: ' . (isset($this->gravity_forms_manager) ? 'YES' : 'NO'));
        
        if ($this->gravity_forms_manager) {
            error_log('GF manager - is GF available: ' . ($this->gravity_forms_manager->is_gravity_forms_available() ? 'YES' : 'NO'));
        }
        
        // Check if we're detecting forms correctly
        $has_gf_on_page = $this->has_gravity_forms_on_page();
        error_log('Has GF on page (our detection): ' . ($has_gf_on_page ? 'YES' : 'NO'));
        
        // Force check for DMN enabled forms
        if ($this->gravity_forms_manager) {
            $has_dmn_forms = $this->has_dmn_enabled_forms_on_page();
            error_log('Has DMN enabled forms: ' . ($has_dmn_forms ? 'YES' : 'NO'));
        }
        
        error_log('============================');
    }
}

    /**
     * Initialize WordPress hooks for asset management
     * Sets up action hooks for proper asset loading at the right times
     * 
     * @since 1.0.0
     */
    private function init_hooks() {
        // Register all assets early
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'), 5);
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'), 5);
        
        // Conditional loading hooks
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_frontend_assets'), 10);
        add_action('admin_enqueue_scripts', array($this, 'maybe_enqueue_admin_assets'), 10);

        // NEW: DOCTYPE compatibility check
        add_action('wp_head', array($this, 'check_document_compatibility'), 1);
    }

    /**
     * NEW: Check and handle document compatibility mode issues
     * Detects Quirks Mode and provides warnings/fixes for jQuery compatibility
     * 
     * @since 1.0.0
     */
public function check_document_compatibility() {
    // Only run on frontend pages with potential Gravity Forms
    if (is_admin()) {
        return;
    }
    
    // Check if we should run compatibility checks
    if (!$this->should_run_compatibility_check()) {
        return;
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Operaton DMN Assets: Running document compatibility check');
    }
    
    ?>
    <script type="text/javascript">
    /* Operaton DMN Document Compatibility Check */
    (function() {
        'use strict';
        
        // Check document compatibility mode
        var isQuirksMode = document.compatMode === "BackCompat";
        var hasDoctype = document.doctype !== null;
        var doctypeName = document.doctype ? document.doctype.name : 'none';
        
        if (isQuirksMode) {
            console.warn('Operaton DMN: Page running in Quirks Mode - jQuery compatibility issues possible');
            console.warn('Operaton DMN: Doctype detected:', doctypeName);
            
            // Try to provide helpful information
            if (!hasDoctype) {
                console.warn('Operaton DMN: No DOCTYPE declaration found. Add <!DOCTYPE html> to your theme header.');
                
                // CRITICAL FIX: Try to force standards mode if possible
                if (document.documentElement) {
                    document.documentElement.setAttribute('data-operaton-quirks-detected', 'true');
                }
            }
            
            // Store compatibility info for other scripts
            window.operatonCompatibilityInfo = {
                quirksMode: true,
                doctype: doctypeName,
                jqueryWarning: true,
                fixApplied: false
            };
            
            // Add body class for CSS fixes
            if (document.body) {
                document.body.className += ' operaton-quirks-mode-detected';
            } else {
                // Wait for body to be available
                document.addEventListener('DOMContentLoaded', function() {
                    if (document.body) {
                        document.body.className += ' operaton-quirks-mode-detected';
                    }
                });
            }
        } else {
            if (<?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>) {
                console.log('Operaton DMN: Document in Standards Mode ✓');
            }
            
            window.operatonCompatibilityInfo = {
                quirksMode: false,
                doctype: doctypeName,
                jqueryWarning: false,
                fixApplied: false
            };
        }
    })();
    </script>
    <?php
    
    // Add comprehensive CSS fixes for Quirks Mode
    $this->add_enhanced_quirks_mode_css_fixes();
}

/**
 * Enhanced CSS fixes for Quirks Mode compatibility
 */
private function add_enhanced_quirks_mode_css_fixes() {
    ?>
    <style type="text/css">
    /* Operaton DMN Enhanced Quirks Mode Compatibility Fixes */
    
    /* Force box-sizing for all elements in quirks mode */
    .operaton-quirks-mode-detected *,
    .operaton-quirks-mode-detected *:before,
    .operaton-quirks-mode-detected *:after {
        -webkit-box-sizing: border-box !important;
        -moz-box-sizing: border-box !important;
        box-sizing: border-box !important;
    }
    
    /* Fix Gravity Forms in Quirks Mode */
    .operaton-quirks-mode-detected .gform_wrapper {
        width: 100% !important;
    }
    
    .operaton-quirks-mode-detected .gform_wrapper .operaton-evaluate-btn {
        display: inline-block !important;
        vertical-align: top !important;
        margin: 10px 0 !important;
        padding: 8px 16px !important;
        line-height: 1.4 !important;
    }
    
    /* Decision flow tables in Quirks Mode */
    .operaton-quirks-mode-detected .decision-table.excel-style {
        table-layout: fixed !important;
        width: 100% !important;
        border-collapse: collapse !important;
    }
    
    /* Fix jQuery UI conflicts in Quirks Mode */
    .operaton-quirks-mode-detected .ui-widget {
        font-family: inherit !important;
    }
    
    /* Ensure proper form field sizing */
    .operaton-quirks-mode-detected .gform_wrapper input[type="text"],
    .operaton-quirks-mode-detected .gform_wrapper input[type="email"],
    .operaton-quirks-mode-detected .gform_wrapper input[type="number"],
    .operaton-quirks-mode-detected .gform_wrapper select,
    .operaton-quirks-mode-detected .gform_wrapper textarea {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    /* Quirks mode notification */
    .operaton-quirks-mode-detected:before {
        content: "⚠️ Quirks Mode Detected - Some features may not work optimally";
        display: block;
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
        padding: 8px 12px;
        margin: 0 0 15px 0;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        text-align: center;
    }
    
    /* Hide the warning in production */
    <?php if (!defined('WP_DEBUG') || !WP_DEBUG): ?>
    .operaton-quirks-mode-detected:before {
        display: none !important;
    }
    <?php endif; ?>
    </style>
    <?php
}

    /**
     * NEW: Determine if compatibility check should run
     * Only runs on pages that might have Gravity Forms with DMN
     * 
     * @return bool True if compatibility check should run
     * @since 1.0.0
     */
    private function should_run_compatibility_check() {
        // Always run if we detect Gravity Forms on the page
        if ($this->has_gravity_forms_on_page()) {
            return true;
        }
        
        // Run on specific pages that might load forms dynamically
        global $post;
        if ($post) {
            // Check for shortcodes
            if (has_shortcode($post->post_content, 'gravityform')) {
                return true;
            }
            
            // Check for Gutenberg blocks
            if (has_block('gravityforms/form', $post)) {
                return true;
            }
        }
        
        // Run on Gravity Forms preview pages
        if (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview') {
            return true;
        }
        
        return false;
    }

    /**
     * NEW: Add CSS fixes for Quirks Mode compatibility
     * Provides basic styling fixes when in Quirks Mode
     * 
     * @since 1.0.0
     */
    private function add_quirks_mode_css_fixes() {
        ?>
        <style type="text/css">
        /* Operaton DMN Quirks Mode Compatibility Fixes */
        .operaton-quirks-mode-detected {
            /* Ensure consistent box model */
            -webkit-box-sizing: border-box;
            -moz-box-sizing: border-box;
            box-sizing: border-box;
        }
        
        /* Fix for Gravity Forms in Quirks Mode */
        .gform_wrapper .operaton-evaluate-btn {
            /* Ensure button displays correctly */
            display: inline-block !important;
            vertical-align: top;
        }
        
        /* Decision flow tables in Quirks Mode */
        .decision-table.excel-style {
            /* More explicit table styling for Quirks Mode */
            table-layout: fixed;
            width: 100%;
        }
        </style>
        <?php
    }

    /**
     * Register all frontend assets without enqueuing them
     * Pre-registers assets for conditional loading based on page context
     * 
     * @since 1.0.0
     */
    public function register_frontend_assets() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Registering frontend assets');
        }
        
        // Register frontend CSS
        wp_register_style(
            'operaton-dmn-frontend',
            $this->plugin_url . 'assets/css/frontend.css',
            array(),
            $this->version
        );
        
        // FIXED: Ensure jQuery is properly loaded first
        wp_register_script(
            'operaton-dmn-frontend',
            $this->plugin_url . 'assets/js/frontend.js',
            array('jquery'), // Explicit jQuery dependency
            $this->version,
            true // Load in footer
        );
        
        // FIXED: Better dependency chain for Gravity Forms integration
        wp_register_script(
            'operaton-dmn-gravity-integration',
            $this->plugin_url . 'assets/js/gravity-forms.js',
            array('jquery', 'operaton-dmn-frontend'), // Both dependencies
            $this->version,
            true
        );
        
        // Register decision flow JavaScript
        wp_register_script(
            'operaton-dmn-decision-flow',
            $this->plugin_url . 'assets/js/decision-flow.js',
            array('jquery'),
            $this->version,
            true
        );
    }

    /**
     * Register all admin assets without enqueuing them
     * Pre-registers admin assets for conditional loading on plugin pages
     * 
     * @since 1.0.0
     */
    public function register_admin_assets() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Registering admin assets');
        }
        
        // Register admin CSS
        wp_register_style(
            'operaton-dmn-admin',
            $this->plugin_url . 'assets/css/admin.css',
            array(),
            $this->version
        );
        
        // Register debug CSS (only in debug mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_register_style(
                'operaton-dmn-debug',
                $this->plugin_url . 'assets/css/debug.css',
                array('operaton-dmn-admin'),
                $this->version
            );
        }
        
        // Register admin JavaScript
        wp_register_script(
            'operaton-dmn-admin',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );
        
        // Register API testing JavaScript
        wp_register_script(
            'operaton-dmn-api-test',
            $this->plugin_url . 'assets/js/api-test.js',
            array('jquery', 'operaton-dmn-admin'),
            $this->version,
            true
        );
    }

    /**
     * Conditionally enqueue frontend assets based on page context
     * Only loads frontend assets when they're actually needed
     * 
     * @since 1.0.0
     */
public function maybe_enqueue_frontend_assets() {
    // TEMPORARY DEBUG - Add this line at the very beginning
    error_log('🔥 OPERATON DMN: maybe_enqueue_frontend_assets called! Admin: ' . (is_admin() ? 'YES' : 'NO'));
    
    // Skip if admin
    if (is_admin()) {
        error_log('🔥 OPERATON DMN: Skipping because is_admin() = true');
        return;
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Operaton DMN Assets: Checking if frontend assets should be loaded');
    }
    
    // Check if we're on a page with Gravity Forms
    $has_gf = $this->has_gravity_forms_on_page();
    error_log('🔥 OPERATON DMN: has_gravity_forms_on_page() = ' . ($has_gf ? 'TRUE' : 'FALSE'));
    
    if ($has_gf) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Gravity Forms detected, loading frontend assets');
        }
        error_log('🔥 OPERATON DMN: About to call enqueue_frontend_assets()');
        $this->enqueue_frontend_assets();
        error_log('🔥 OPERATON DMN: enqueue_frontend_assets() finished');
    } else {
        error_log('🔥 OPERATON DMN: No Gravity Forms detected, not loading assets');
    }
}
    /**
     * Conditionally enqueue admin assets based on current admin page
     * Only loads admin assets on plugin-related admin pages
     * 
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function maybe_enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'operaton-dmn') !== false) {
            $this->enqueue_admin_assets($hook);
        }
    }

    /**
     * Enqueue frontend assets for public form pages
     * Loads CSS and JavaScript needed for DMN evaluation functionality
     * 
     * @since 1.0.0
     */

/**
 * FIXED enqueue_frontend_assets method for class-operaton-dmn-assets.php
 * The issue is that wp_enqueue_script() might not immediately register the script
 * We need to ensure registration happens before localization
     * UPDATED: Enhanced frontend asset enqueuing with compatibility check
     * Now includes compatibility mode handling
     */
public function enqueue_frontend_assets() {
    // Prevent duplicate loading
    if (isset($this->loaded_assets['frontend'])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Frontend assets already loaded, skipping');
        }
        return;
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Operaton DMN Assets: ⭐ STARTING enqueue_frontend_assets');
    }
    
    // CRITICAL FIX: Ensure jQuery is loaded FIRST and wait for it
    if (!wp_script_is('jquery', 'done') && !wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
    
    // Force registration if not already registered
    if (!wp_script_is('operaton-dmn-frontend', 'registered')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: 🔧 Script not registered, registering now');
        }
        
        wp_register_script(
            'operaton-dmn-frontend',
            $this->plugin_url . 'assets/js/frontend.js',
            array('jquery'), // Explicit jQuery dependency
            $this->version,
            true // Load in footer AFTER jQuery
        );
    }
    
    // Enqueue CSS first
    wp_enqueue_style('operaton-dmn-frontend');
    
    // Then enqueue JS with jQuery dependency
    wp_enqueue_script('operaton-dmn-frontend');
    
    // CRITICAL: Add jQuery ready wrapper to the localization
    $localization_data = array(
        'url' => rest_url('operaton-dmn/v1/evaluate'),
        'nonce' => wp_create_nonce('wp_rest'),
        'debug' => defined('WP_DEBUG') && WP_DEBUG ? '1' : '0', // String for consistency
        'strings' => array(
            'evaluating' => __('Evaluating...', 'operaton-dmn'),
            'error' => __('Evaluation failed', 'operaton-dmn'),
            'success' => __('Evaluation completed', 'operaton-dmn'),
            'loading' => __('Loading...', 'operaton-dmn'),
            'no_config' => __('Configuration not found', 'operaton-dmn'),
            'validation_failed' => __('Please fill in all required fields', 'operaton-dmn'),
            'connection_error' => __('Connection error. Please try again.', 'operaton-dmn')
        ),
        'compatibility' => array(
            'quirks_mode_check' => true,
            'jquery_version_required' => '3.0'
        )
    );
    
    // Enhanced localization with better error handling
    $localize_result = wp_localize_script('operaton-dmn-frontend', 'operaton_ajax', $localization_data);
    
    if (!$localize_result && defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Operaton DMN Assets: ❌ wp_localize_script failed');
    }
    
    $this->loaded_assets['frontend'] = true;
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Operaton DMN Assets: ⭐ FINISHED enqueue_frontend_assets');
    }
}

    /**
     * NEW: Add compatibility integration to footer
     * Provides additional JavaScript compatibility handling
     * 
     * @since 1.0.0
     */
    public function add_compatibility_integration() {
        // Only add once
        static $added = false;
        if ($added) {
            return;
        }
        $added = true;
        
        ?>
        <script type="text/javascript">
        /* Operaton DMN Compatibility Integration */
        (function($) {
            'use strict';
            
            // Wait for compatibility info to be available
            function waitForCompatibilityInfo(callback) {
                var attempts = 0;
                var maxAttempts = 10;
                
                function check() {
                    if (window.operatonCompatibilityInfo) {
                        callback(window.operatonCompatibilityInfo);
                    } else if (attempts < maxAttempts) {
                        attempts++;
                        setTimeout(check, 100);
                    } else {
                        // Fallback
                        callback({
                            quirksMode: document.compatMode === "BackCompat",
                            doctype: document.doctype ? document.doctype.name : 'none',
                            jqueryWarning: false
                        });
                    }
                }
                check();
            }
            
            // Initialize compatibility handling
            $(document).ready(function() {
                waitForCompatibilityInfo(function(compatInfo) {
                    if (compatInfo.quirksMode) {
                        // Add CSS class for Quirks Mode styling
                        $('body').addClass('operaton-quirks-mode-detected');
                        
                        // Show user-friendly warning in debug mode
                        if (<?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>) {
                            console.warn('Operaton DMN: Your theme may be in Quirks Mode. Consider adding <!DOCTYPE html> to the theme header for better compatibility.');
                        }
                    }
                    
                    // Store compatibility info globally for other scripts
                    if (window.operaton_ajax) {
                        window.operaton_ajax.compatibility = compatInfo;
                    }
                });
            });
            
        })(jQuery);
        </script>
        <?php
    }

    /**
     * NEW: Get compatibility status for debugging
     * Provides compatibility information for admin interface
     * 
     * @return array Compatibility status information
     * @since 1.0.0
     */
    public function get_compatibility_status() {
        return array(
            'check_enabled' => true,
            'hooks_registered' => array(
                'wp_head' => has_action('wp_head', array($this, 'check_document_compatibility')),
                'wp_footer' => has_action('wp_footer', array($this, 'add_compatibility_integration'))
            ),
            'quirks_mode_detection' => 'JavaScript-based',
            'css_fixes_available' => true
        );
    }

    /**
     * Enqueue admin assets for plugin configuration pages
     * Loads CSS and JavaScript needed for admin interface functionality
     * 
     * @param string $hook Current admin page hook
     * @since 1.0.0
     */
    public function enqueue_admin_assets($hook) {
        // Prevent duplicate loading
        if (isset($this->loaded_assets['admin'])) {
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Enqueuing admin assets for: ' . $hook);
        }
        
        // Enqueue admin CSS
        wp_enqueue_style('operaton-dmn-admin');
        
        // Enqueue debug CSS if in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG && strpos($hook, 'debug') !== false) {
            wp_enqueue_style('operaton-dmn-debug');
        }
        
        // Enqueue admin JavaScript
        wp_enqueue_script('operaton-dmn-admin');
        
        // Enqueue API testing on config pages
        if (strpos($hook, 'operaton-dmn-add') !== false || isset($_GET['edit'])) {
            wp_enqueue_script('operaton-dmn-api-test');
        }
        
        // Localize admin script
        wp_localize_script('operaton-dmn-admin', 'operaton_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('operaton_admin_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'strings' => array(
                'testing' => __('Testing...', 'operaton-dmn'),
                'success' => __('Success!', 'operaton-dmn'),
                'error' => __('Error occurred', 'operaton-dmn'),
                'confirm_delete' => __('Are you sure you want to delete this configuration?', 'operaton-dmn'),
                'saving' => __('Saving...', 'operaton-dmn'),
                'saved' => __('Saved!', 'operaton-dmn')
            )
        ));
        
        $this->loaded_assets['admin'] = true;
    }

/**
 * Enqueue Gravity Forms-specific assets with form configuration
 * Loads form-specific JavaScript and CSS for DMN evaluation functionality
 * 
 * @param array $form Gravity Forms form array
 * @param object $config DMN configuration object
 * @since 1.0.0
 */
    public function enqueue_gravity_form_assets($form, $config) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Enqueuing Gravity Forms assets for form: ' . $form['id']);
        }
        
        // FIXED: Ensure frontend assets are loaded first
        $this->enqueue_frontend_assets();
        
        // FIXED: Wait for frontend assets to be ready
        add_action('wp_footer', function() use ($form, $config) {
            $this->enqueue_gravity_integration_scripts($form, $config);
        }, 5);
    }

    /**
     * FIXED: Separate method for Gravity Forms integration scripts
     */
    private function enqueue_gravity_integration_scripts($form, $config) {
        // Enqueue Gravity Forms integration script
        wp_enqueue_script('operaton-dmn-gravity-integration');
        
        // Localize Gravity Forms specific data
        wp_localize_script('operaton-dmn-gravity-integration', 'operaton_gravity', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('operaton_gravity_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'strings' => array(
                'validation_failed' => __('Please complete all required fields before evaluation.', 'operaton-dmn'),
                'evaluation_in_progress' => __('Evaluation in progress...', 'operaton-dmn'),
                'form_error' => __('Form validation failed. Please check your entries.', 'operaton-dmn')
            )
        ));
        
        // Process configuration for JavaScript
        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $field_mappings = array();
        }
        
        $result_mappings = json_decode($config->result_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result_mappings = array();
        }
        
        // Localize form-specific configuration
        wp_localize_script('operaton-dmn-gravity-integration', 'operaton_config_' . $form['id'], array(
            'config_id' => $config->id,
            'button_text' => $config->button_text,
            'field_mappings' => $field_mappings,
            'result_mappings' => $result_mappings,
            'form_id' => $form['id'],
            'evaluation_step' => isset($config->evaluation_step) ? $config->evaluation_step : 'auto',
            'use_process' => isset($config->use_process) ? $config->use_process : false,
            'show_decision_flow' => isset($config->show_decision_flow) ? $config->show_decision_flow : false,
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
    }

    /**
     * Enqueue decision flow CSS and JavaScript for process execution results
     * Loads Excel-style table CSS and interactive JavaScript for decision flow display
     * 
     * @since 1.0.0
     */
    public function enqueue_decision_flow_assets() {
        // Prevent duplicate loading
        if (isset($this->loaded_assets['decision_flow'])) {
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Enqueuing decision flow assets');
        }
        
        // Enqueue decision flow CSS
        wp_enqueue_style('operaton-dmn-decision-flow');
        
        // Enqueue decision flow JavaScript
        wp_enqueue_script('operaton-dmn-decision-flow');
        
        // Localize decision flow script
        wp_localize_script('operaton-dmn-decision-flow', 'operaton_decision_flow', array(
            'ajax_url' => rest_url('operaton-dmn/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'strings' => array(
                'loading' => __('Loading decision flow...', 'operaton-dmn'),
                'refreshing' => __('Refreshing...', 'operaton-dmn'),
                'error' => __('Error loading decision flow', 'operaton-dmn'),
                'no_data' => __('No decision flow data available', 'operaton-dmn')
            )
        ));
        
        $this->loaded_assets['decision_flow'] = true;
    }

    /**
     * Add inline CSS for dynamic styling based on configuration
     * Generates custom CSS properties and form-specific styles
     * 
     * @param int $form_id Gravity Forms form ID (optional)
     * @param array $styles Custom styles to apply
     * @since 1.0.0
     */
    public function add_inline_styles($form_id = null, $styles = array()) {
        $css = '';
        
        // Generate CSS custom properties from styles
        if (!empty($styles['theme'])) {
            $css .= ':root {';
            
            foreach ($styles['theme'] as $property => $value) {
                $css .= '--operaton-' . esc_attr($property) . ': ' . esc_attr($value) . ';';
            }
            
            $css .= '}';
        }
        
        // Form-specific styles
        if ($form_id && !empty($styles['form'])) {
            $css .= "#operaton-evaluate-{$form_id} {";
            foreach ($styles['form'] as $property => $value) {
                $css .= esc_attr($property) . ': ' . esc_attr($value) . ' !important;';
            }
            $css .= '}';
            
            $css .= "#decision-flow-summary-{$form_id} {";
            foreach ($styles['form'] as $property => $value) {
                if (strpos($property, 'button-') === false) {
                    $css .= esc_attr($property) . ': ' . esc_attr($value) . ';';
                }
            }
            $css .= '}';
        }
        
        if (!empty($css)) {
            // Determine which style to add inline CSS to
            $handle = 'operaton-dmn-frontend';
            if (is_admin()) {
                $handle = 'operaton-dmn-admin';
            }
            
            wp_add_inline_style($handle, $css);
        }
    }

    /**
     * Check if current page has Gravity Forms that might need our assets
     * Determines whether frontend assets should be loaded based on page content
     * 
     * @return bool True if page has relevant Gravity Forms
     * @since 1.0.0
     */
    private function has_gravity_forms_on_page() {
        // If Gravity Forms manager is available, use its detection
        if ($this->gravity_forms_manager && $this->gravity_forms_manager->is_gravity_forms_available()) {
            return $this->has_dmn_enabled_forms_on_page();
        }
        
        // Fallback to basic Gravity Forms detection
        if (!class_exists('GFForms')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: Gravity Forms not active');
            }
            return false;
        }
        
        // Check for shortcodes in post content
        global $post;
        if ($post && has_shortcode($post->post_content, 'gravityform')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: Found gravityform shortcode in post content');
            }
            return true;
        }
        
        // Check for Gravity Forms blocks (Gutenberg)
        if ($post && has_block('gravityforms/form', $post)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: Found Gravity Forms block in post');
            }
            return true;
        }
        
        // Check if we're on a Gravity Forms preview page
        if (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: On Gravity Forms preview page');
            }
            return true;
        }
        
        // Allow other plugins/themes to indicate GF presence
        $has_gf = apply_filters('operaton_dmn_has_gravity_forms', false);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Filter result for has_gravity_forms: ' . ($has_gf ? 'true' : 'false'));
        }
        
        return $has_gf;
    }

    /**
     * Get asset loading status for debugging and diagnostics
     * Returns information about which assets are currently loaded
     * 
     * @return array Asset loading status information
     * @since 1.0.0
     */
    public function get_assets_status() {
        global $wp_scripts, $wp_styles;
        
        $status = array(
            'loaded_assets' => $this->loaded_assets,
            'scripts' => array(),
            'styles' => array(),
            'registered' => array()
        );
        
        // Check our scripts
        $our_scripts = array(
            'operaton-dmn-admin',
            'operaton-dmn-frontend', 
            'operaton-dmn-gravity',
            'operaton-dmn-decision-flow',
            'operaton-dmn-api-test'
        );
        
        foreach ($our_scripts as $script) {
            $status['scripts'][$script] = array(
                'registered' => wp_script_is($script, 'registered'),
                'enqueued' => wp_script_is($script, 'enqueued'),
                'done' => wp_script_is($script, 'done')
            );
        }
        
        // Check our styles
        $our_styles = array(
            'operaton-dmn-admin',
            'operaton-dmn-frontend',
            'operaton-dmn-decision-flow',
            'operaton-dmn-debug'
        );
        
        foreach ($our_styles as $style) {
            $status['styles'][$style] = array(
                'registered' => wp_style_is($style, 'registered'),
                'enqueued' => wp_style_is($style, 'enqueued'),
                'done' => wp_style_is($style, 'done')
            );
        }
        
        return $status;
    }

    /**
     * Force enqueue specific assets for manual loading
     * Allows other components to force load specific asset groups when needed
     * 
     * @param string $asset_group Asset group to force load (admin, frontend, decision_flow)
     * @since 1.0.0
     */
    public function force_enqueue($asset_group) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Force enqueuing asset group: ' . $asset_group);
        }
        
        switch ($asset_group) {
            case 'admin':
                $this->enqueue_admin_assets(get_current_screen()->id);
                break;
                
            case 'frontend':
                $this->enqueue_frontend_assets();
                break;
                
            case 'decision_flow':
                $this->enqueue_decision_flow_assets();
                break;
                
            default:
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN Assets: Unknown asset group: ' . $asset_group);
                }
        }
    }

    /**
     * Check if current page has DMN-enabled Gravity Forms
     * Uses Gravity Forms manager to detect forms with DMN configurations
     * 
     * @return bool True if page has DMN-enabled forms
     * @since 1.0.0
     */
    private function has_dmn_enabled_forms_on_page() {
        global $post;
        
        // Check for shortcodes in post content
        if ($post && has_shortcode($post->post_content, 'gravityform')) {
            $form_ids = $this->extract_form_ids_from_shortcodes($post->post_content);
            return $this->any_forms_have_dmn_config($form_ids);
        }
        
        // Check for Gravity Forms blocks (Gutenberg)
        if ($post && has_block('gravityforms/form', $post)) {
            $form_ids = $this->extract_form_ids_from_blocks($post);
            return $this->any_forms_have_dmn_config($form_ids);
        }
        
        // Check if we're on a Gravity Forms preview page
        if (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview') {
            $form_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($form_id > 0) {
                $config = $this->gravity_forms_manager->get_form_configuration($form_id);
                return $config !== null;
            }
        }
        
        return false;
    }

    /**
     * Extract form IDs from gravityform shortcodes
     * Parses shortcode attributes to find form IDs
     * 
     * @param string $content Post content to search
     * @return array Array of form IDs found
     * @since 1.0.0
     */
    private function extract_form_ids_from_shortcodes($content) {
        $form_ids = array();
        
        // Pattern to match [gravityform id="X"] shortcodes
        $pattern = '/\[gravityform[^\]]*id=["\'](\d+)["\'][^\]]*\]/';
        
        if (preg_match_all($pattern, $content, $matches)) {
            $form_ids = array_map('intval', $matches[1]);
        }
        
        return array_unique($form_ids);
    }

    /**
     * Extract form IDs from Gravity Forms Gutenberg blocks
     * Parses block content to find form IDs
     * 
     * @param WP_Post $post Post object to search
     * @return array Array of form IDs found
     * @since 1.0.0
     */
    private function extract_form_ids_from_blocks($post) {
        $form_ids = array();
        
        if (function_exists('parse_blocks')) {
            $blocks = parse_blocks($post->post_content);
            $form_ids = $this->find_gravity_form_ids_in_blocks($blocks);
        }
        
        return array_unique($form_ids);
    }

    /**
     * Recursively find Gravity Forms block IDs
     * Searches through nested blocks for gravityforms/form blocks
     * 
     * @param array $blocks Array of parsed blocks
     * @return array Array of form IDs found
     * @since 1.0.0
     */
    private function find_gravity_form_ids_in_blocks($blocks) {
        $form_ids = array();
        
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'gravityforms/form') {
                if (isset($block['attrs']['formId'])) {
                    $form_ids[] = intval($block['attrs']['formId']);
                }
            }
            
            // Check inner blocks recursively
            if (!empty($block['innerBlocks'])) {
                $inner_ids = $this->find_gravity_form_ids_in_blocks($block['innerBlocks']);
                $form_ids = array_merge($form_ids, $inner_ids);
            }
        }
        
        return $form_ids;
    }

    /**
     * Check if any of the provided form IDs have DMN configurations
     * Uses Gravity Forms manager to check for DMN configurations
     * 
     * @param array $form_ids Array of form IDs to check
     * @return bool True if any form has DMN configuration
     * @since 1.0.0
     */
    private function any_forms_have_dmn_config($form_ids) {
        if (!$this->gravity_forms_manager) {
            return false;
        }
        
        foreach ($form_ids as $form_id) {
            $config = $this->gravity_forms_manager->get_form_configuration($form_id);
            if ($config !== null) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Clear loaded assets cache for testing or manual reset
     * Resets the internal tracking of which assets have been loaded
     * 
     * @since 1.0.0
     */
    public function reset_loaded_assets() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Resetting loaded assets cache');
        }
        
        $this->loaded_assets = array();
    }

    /**
     * Get plugin URL for external asset access
     * Provides access to plugin URL for components that need to build asset paths
     * 
     * @return string Plugin URL with trailing slash
     * @since 1.0.0
     */
    public function get_plugin_url() {
        return $this->plugin_url;
    }

    /**
     * Get plugin version for external cache busting
     * Provides access to plugin version for components that need cache busting
     * 
     * @return string Plugin version string
     * @since 1.0.0
     */
    public function get_version() {
        return $this->version;
    }
}