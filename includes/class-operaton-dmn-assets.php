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
        
        // Register decision flow CSS
        wp_register_style(
            'operaton-dmn-decision-flow',
            $this->plugin_url . 'assets/css/decision-flow.css',
            array(),
            $this->version
        );
        
        // Register frontend JavaScript
        wp_register_script(
            'operaton-dmn-frontend',
            $this->plugin_url . 'assets/js/frontend.js',
            array('jquery'),
            $this->version,
            true
        );
        
        // Register Gravity Forms integration
        wp_register_script(
            'operaton-dmn-gravity',
            $this->plugin_url . 'assets/js/gravity-forms.js',
            array('jquery', 'operaton-dmn-frontend'),
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
        // Skip if admin
        if (is_admin()) {
            return;
        }
        
        // Check if we're on a page with Gravity Forms
        if ($this->has_gravity_forms_on_page()) {
            $this->enqueue_frontend_assets();
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
    public function enqueue_frontend_assets() {
        // Prevent duplicate loading
        if (isset($this->loaded_assets['frontend'])) {
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Enqueuing frontend assets');
        }
        
        // Enqueue frontend CSS
        wp_enqueue_style('operaton-dmn-frontend');
        
        // Enqueue frontend JavaScript
        wp_enqueue_script('operaton-dmn-frontend');
        
        // Localize frontend script
        wp_localize_script('operaton-dmn-frontend', 'operaton_ajax', array(
            'url' => rest_url('operaton-dmn/v1/evaluate'),
            'nonce' => wp_create_nonce('wp_rest'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'strings' => array(
                'evaluating' => __('Evaluating...', 'operaton-dmn'),
                'error' => __('Evaluation failed', 'operaton-dmn'),
                'success' => __('Evaluation completed', 'operaton-dmn'),
                'loading' => __('Loading...', 'operaton-dmn')
            )
        ));
        
        $this->loaded_assets['frontend'] = true;
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
        
        // Ensure frontend assets are loaded first
        $this->enqueue_frontend_assets();
        
        // Enqueue Gravity Forms integration script
        wp_enqueue_script('operaton-dmn-gravity');
        
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
        wp_localize_script('operaton-dmn-gravity', 'operaton_config_' . $form['id'], array(
            'config_id' => $config->id,
            'button_text' => $config->button_text,
            'field_mappings' => $field_mappings,
            'result_mappings' => $result_mappings,
            'form_id' => $form['id'],
            'evaluation_step' => isset($config->evaluation_step) ? $config->evaluation_step : 'auto',
            'use_process' => isset($config->use_process) ? $config->use_process : false,
            'show_decision_flow' => isset($config->show_decision_flow) ? $config->show_decision_flow : false
        ));
        
        // Enqueue decision flow assets if needed
        if (isset($config->show_decision_flow) && $config->show_decision_flow) {
            $this->enqueue_decision_flow_assets();
        }
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
        // Check if Gravity Forms is active
        if (!class_exists('GFForms')) {
            return false;
        }
        
        // Check for shortcodes in post content
        global $post;
        if ($post && has_shortcode($post->post_content, 'gravityform')) {
            return true;
        }
        
        // Check for Gravity Forms blocks (Gutenberg)
        if ($post && has_block('gravityforms/form', $post)) {
            return true;
        }
        
        // Allow other plugins/themes to indicate GF presence
        return apply_filters('operaton_dmn_has_gravity_forms', false);
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