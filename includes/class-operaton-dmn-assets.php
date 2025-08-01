<?php

/**
 * ENHANCED: Assets Manager for Operaton DMN Plugin
 *
 * PERFORMANCE OPTIMIZATIONS:
 * 1. Enhanced Global State Management with atomic loading flags
 * 2. Single-run detection with comprehensive caching
 * 3. Eliminated redundant asset loading calls
 * 4. Optimized Gravity Forms processing
 * 5. Smart dependency management
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Operaton_DMN_Assets
{
    /**
     * Performance monitor instance
     */
    private $performance;

    /**
     * PHASE 1 FIX: Enhanced global state with atomic loading prevention
     */
    private static $global_loading_state = array(
        'frontend_loaded' => false,
        'admin_loaded' => false,
        'gravity_loaded' => false,
        'detection_complete' => false,
        'should_load' => false,
        'last_detection_hash' => null
    );

    /**
     * PHASE 1 FIX: Atomic loading flags to prevent concurrent operations
     */
    private static $atomic_loading_flags = array(
        'frontend_loading' => false,
        'gravity_loading' => false,
        'detection_running' => false,
        'admin_loading' => false
    );

    /**
     * PHASE 1 FIX: Smart caching for expensive operations
     */
    private static $operation_cache = array(
        'form_detection' => array(),
        'dmn_form_check' => array(),
        'gravity_forms_available' => null,
        'cache_timestamp' => 0
    );

    /**
     * PHASE 1 FIX: Performance tracking
     */
    private static $performance_stats = array(
        'asset_load_count' => 0,
        'detection_runs' => 0,
        'cache_hits' => 0,
        'duplicate_preventions' => 0
    );

    private $plugin_url;
    private $version;
    private $loaded_assets = array();
    private $gravity_forms_manager = null;

    /**
     * Enhanced constructor with performance monitoring
     */
    public function __construct($plugin_url, $version)
    {
        $this->plugin_url = trailingslashit($plugin_url);
        $this->version = $version;

        // Get performance monitor if available
        if (class_exists('Operaton_DMN_Performance_Monitor')) {
            $this->performance = Operaton_DMN_Performance_Monitor::get_instance();
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Enhanced manager initialized with atomic loading v' . $version);
        }

        $this->init_hooks();
    }

    public function set_gravity_forms_manager($gravity_forms_manager)
    {
        $this->gravity_forms_manager = $gravity_forms_manager;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Gravity Forms manager set successfully');
        }
    }

    /**
     * PHASE 1 FIX: Enhanced initialization with smart hook timing
     */
    private function init_hooks()
    {
        // CRITICAL: Register assets very early but only once
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'), 5);
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'), 5);

        // PHASE 1 FIX: Single conditional loading point
        add_action('wp_enqueue_scripts', array($this, 'smart_conditional_loading'), 10);
        add_action('admin_enqueue_scripts', array($this, 'maybe_enqueue_admin_assets'), 10);

        // Enhanced compatibility checking
        add_action('wp_head', array($this, 'check_document_compatibility'), 1);

        // Early Gravity Forms detection (only on frontend)
        if (!is_admin()) {
            add_action('template_redirect', array($this, 'early_gravity_detection'), 1);
        }

        // Performance tracking hooks
        if ($this->performance) {
            add_action('shutdown', array($this, 'log_performance_stats'), 999);
        }
    }

    // =============================================================================
    // PHASE 1 FIX: CENTRALIZED DETECTION WITH COMPREHENSIVE CACHING
    // =============================================================================

    /**
     * PHASE 1 FIX: Single-run detection method with comprehensive caching
     * This eliminates redundant detection logic across multiple methods
     */
    public static function should_load_frontend_assets()
    {
        // CRITICAL: Prevent concurrent detection
        if (self::$atomic_loading_flags['detection_running']) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ⏸️ Detection already running, using cached result');
            }
            return self::$global_loading_state['should_load'];
        }

        // Use cached result if detection already complete and cache is fresh
        if (
            self::$global_loading_state['detection_complete'] &&
            self::is_cache_fresh()
        ) {
            self::$performance_stats['cache_hits']++;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ✅ Using cached detection result: ' .
                    (self::$global_loading_state['should_load'] ? 'LOAD' : 'SKIP'));
            }
            return self::$global_loading_state['should_load'];
        }

        // Set atomic flag to prevent concurrent detection
        self::$atomic_loading_flags['detection_running'] = true;
        self::$performance_stats['detection_runs']++;

        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('🔍 Operaton DMN Assets: Running enhanced centralized detection');
            }

            $should_load = false;
            $detection_context = self::build_detection_context();

            // ENHANCED DETECTION METHODS (in order of reliability and performance)

            // Method 1: Class existence (most reliable, fastest)
            if (class_exists('GFForms')) {
                $should_load = true;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('✅ Detection: GFForms class available');
                }
            }

            // Method 2: Admin context with GF pages (admin only)
            if (!$should_load && is_admin()) {
                $screen = get_current_screen();
                if ($screen && strpos($screen->id, 'toplevel_page_gf_') === 0) {
                    $should_load = true;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('✅ Detection: GF admin page detected');
                    }
                }
            }

            // Method 3: Content analysis (with caching)
            if (!$should_load && !is_admin()) {
                $should_load = self::detect_gravity_forms_in_content($detection_context);
            }

            // Method 4: URL-based detection
            if (!$should_load && self::detect_gravity_forms_in_url()) {
                $should_load = true;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('✅ Detection: GF URL parameters detected');
                }
            }

            // Method 5: Template-based detection
            if (!$should_load && !is_admin()) {
                $template = get_page_template_slug();
                if (strpos($template, 'gravity') !== false || strpos($template, 'form') !== false) {
                    $should_load = true;
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('✅ Detection: Form template detected');
                    }
                }
            }

            // Cache the results with detection hash
            self::$global_loading_state['detection_complete'] = true;
            self::$global_loading_state['should_load'] = $should_load;
            self::$global_loading_state['last_detection_hash'] = self::generate_detection_hash($detection_context);
            self::$operation_cache['cache_timestamp'] = time();

            if (defined('WP_DEBUG') && WP_DEBUG) {
                $result_text = $should_load ? '✅ LOAD ASSETS' : '❌ SKIP ASSETS';
                error_log("🔍 Operaton DMN Assets: Detection complete - {$result_text}");
            }

            return $should_load;
        } finally {
            // Always clear the atomic flag
            self::$atomic_loading_flags['detection_running'] = false;
        }
    }

    /**
     * PHASE 1 FIX: Build detection context for caching and comparison
     */
    private static function build_detection_context()
    {
        global $post;

        return array(
            'is_admin' => is_admin(),
            'post_id' => $post ? $post->ID : 0,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'query_vars' => $_GET,
            'screen_id' => is_admin() ? get_current_screen()->id ?? '' : '',
            'template' => is_admin() ? '' : get_page_template_slug(),
            'gravity_forms_available' => class_exists('GFForms')
        );
    }

    /**
     * PHASE 1 FIX: Generate hash for detection context to enable smart caching
     */
    private static function generate_detection_hash($context)
    {
        return md5(serialize($context));
    }

    /**
     * PHASE 1 FIX: Check if cache is fresh (prevents stale detection)
     */
    private static function is_cache_fresh($max_age = 300) // 5 minutes
    {
        return (time() - self::$operation_cache['cache_timestamp']) < $max_age;
    }

    /**
     * PHASE 1 FIX: Enhanced content detection with caching
     */
    private static function detect_gravity_forms_in_content($context)
    {
        $post_id = $context['post_id'];

        // Use cached result if available
        if (isset(self::$operation_cache['form_detection'][$post_id])) {
            self::$performance_stats['cache_hits']++;
            return self::$operation_cache['form_detection'][$post_id];
        }

        global $post;
        $has_gravity_forms = false;

        if ($post) {
            // Check for shortcodes
            if (has_shortcode($post->post_content, 'gravityform')) {
                $has_gravity_forms = true;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('✅ Detection: gravityform shortcode found');
                }
            }
            // Check for Gutenberg blocks
            elseif (has_block('gravityforms/form', $post)) {
                $has_gravity_forms = true;
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('✅ Detection: gravityforms block found');
                }
            }
        }

        // Cache the result
        self::$operation_cache['form_detection'][$post_id] = $has_gravity_forms;

        return $has_gravity_forms;
    }

    /**
     * PHASE 1 FIX: URL-based detection
     */
    private static function detect_gravity_forms_in_url()
    {
        return (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview') ||
            (isset($_GET['gf_token'])) ||
            (strpos($_SERVER['REQUEST_URI'] ?? '', '/gravityforms') !== false);
    }

    // =============================================================================
    // PHASE 1 FIX: ATOMIC ASSET LOADING WITH DUPLICATE PREVENTION
    // =============================================================================

    /**
     * PHASE 1 FIX: Smart conditional loading that prevents duplicates
     */
    public function smart_conditional_loading()
    {
        if (is_admin()) {
            return;
        }

        // Use atomic detection
        if (self::should_load_frontend_assets()) {
            $this->enqueue_frontend_assets();

            // ADD THIS: Load decision flow assets if needed
            if ($this->should_load_decision_flow_assets()) {
                $this->enqueue_decision_flow_assets();
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: Smart loading determined assets not needed');
            }
        }
    }

    /**
     * PHASE 1 FIX: Atomic frontend asset loading with comprehensive duplicate prevention
     */
    public function enqueue_frontend_assets()
    {
        $timer_id = null;
        if ($this->performance) {
            $timer_id = $this->performance->start_timer('atomic_frontend_assets_enqueue');
        }

        // CRITICAL: Prevent concurrent loading
        if (self::$atomic_loading_flags['frontend_loading']) {
            self::$performance_stats['duplicate_preventions']++;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ⏸️ PREVENTED - Frontend loading already in progress');
            }
            return;
        }

        // CRITICAL: Check if already completed globally
        if (self::$global_loading_state['frontend_loaded']) {
            self::$performance_stats['duplicate_preventions']++;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ⏭️ PREVENTED - Already loaded globally');
            }
            return;
        }

        // Set atomic flag immediately
        self::$atomic_loading_flags['frontend_loading'] = true;

        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: 🚀 ATOMIC LOADING - Frontend assets');
            }

            self::$performance_stats['asset_load_count']++;

            // ENHANCED: Ensure jQuery is available with smart dependency
            $this->ensure_jquery_dependency();

            // Register and enqueue with optimized dependency chain
            $this->register_and_enqueue_frontend_scripts();
            $this->register_and_enqueue_frontend_styles();

            // CRITICAL: Localize only once with duplicate prevention
            $this->smart_localization();

            // Mark as completed atomically
            $this->loaded_assets['frontend'] = true;
            self::$global_loading_state['frontend_loaded'] = true;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ✅ ATOMIC LOADING COMPLETE - Frontend assets');
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ❌ ATOMIC LOADING ERROR: ' . $e->getMessage());
            }
        } finally {
            // CRITICAL: Always clear the atomic flag
            self::$atomic_loading_flags['frontend_loading'] = false;

            if ($this->performance && $timer_id) {
                $this->performance->stop_timer($timer_id, 'Atomic frontend assets loading completed');
            }
        }
    }

    /**
     * PHASE 1 FIX: Smart jQuery dependency management
     */
    private function ensure_jquery_dependency()
    {
        if (!wp_script_is('jquery', 'enqueued') && !wp_script_is('jquery', 'done')) {
            wp_enqueue_script('jquery');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ⚡ jQuery enqueued for dependency');
            }
        }
    }

    /**
     * PHASE 1 FIX: Optimized script registration and enqueuing
     */
    private function register_and_enqueue_frontend_scripts()
    {
        // Register main frontend script with explicit dependency
        if (!wp_script_is('operaton-dmn-frontend', 'registered')) {
            wp_register_script(
                'operaton-dmn-frontend',
                $this->plugin_url . 'assets/js/frontend.js',
                array('jquery'),
                $this->version,
                true
            );
        }

        // Register Gravity Forms integration with proper dependency chain
        if (!wp_script_is('operaton-dmn-gravity-integration', 'registered')) {
            wp_register_script(
                'operaton-dmn-gravity-integration',
                $this->plugin_url . 'assets/js/gravity-forms.js',
                array('jquery', 'operaton-dmn-frontend'),
                $this->version,
                true
            );
        }

        // Register decision flow script
        if (!wp_script_is('operaton-dmn-decision-flow', 'registered')) {
            wp_register_script(
                'operaton-dmn-decision-flow',
                $this->plugin_url . 'assets/js/decision-flow.js',
                array('jquery', 'operaton-dmn-frontend'), // Depends on frontend.js but not gravity-forms.js
                $this->version,
                true
            );
        }

        // Enqueue scripts
        wp_enqueue_script('operaton-dmn-frontend');
        wp_enqueue_script('operaton-dmn-gravity-integration');
        wp_enqueue_script('operaton-dmn-decision-flow');
    }

    /**
     * PHASE 1 FIX: Optimized style registration and enqueuing
     */
    private function register_and_enqueue_frontend_styles()
    {
        if (!wp_style_is('operaton-dmn-frontend', 'registered')) {
            wp_register_style(
                'operaton-dmn-frontend',
                $this->plugin_url . 'assets/css/frontend.css',
                array(),
                $this->version
            );
        }

        wp_enqueue_style('operaton-dmn-frontend');
    }

    /**
     * PHASE 1 FIX: Smart localization with duplicate prevention
     */
    private function smart_localization()
    {
        // Check if already localized to prevent duplicates
        if (
            wp_script_is('operaton-dmn-frontend', 'localized') ||
            wp_scripts()->get_data('operaton-dmn-frontend', 'data')
        ) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ⏭️ Localization skipped - already done');
            }
            return;
        }

        $localization_data = array(
            'url' => rest_url('operaton-dmn/v1/evaluate'),
            'nonce' => wp_create_nonce('wp_rest'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG ? '1' : '0',
            'strings' => array(
                'evaluating' => __('Evaluating...', 'operaton-dmn'),
                'error' => __('Evaluation failed', 'operaton-dmn'),
                'success' => __('Evaluation completed', 'operaton-dmn'),
                'loading' => __('Loading...', 'operaton-dmn'),
                'no_config' => __('Configuration not found', 'operaton-dmn'),
                'validation_failed' => __('Please fill in all required fields', 'operaton-dmn'),
                'connection_error' => __('Connection error. Please try again.', 'operaton-dmn'),
                'button_text_default' => __('Evaluate', 'operaton-dmn'),
                'button_text_evaluating' => __('Evaluating...', 'operaton-dmn'),
                'button_text_error' => __('Try again', 'operaton-dmn')
            ),
            'compatibility' => array(
                'quirks_mode_check' => true,
                'jquery_version_required' => '3.0'
            ),
            'performance' => array(
                'load_time' => $this->performance ? round(($this->performance->get_summary()['total_time_ms']), 2) : 0,
                'timestamp' => time(),
                'atomic_loading' => true
            ),
            'loading_source' => 'enhanced_atomic_management'
        );

        wp_localize_script('operaton-dmn-frontend', 'operaton_ajax', $localization_data);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: ✅ Smart localization completed');
        }
    }

    // =============================================================================
    // PHASE 1 FIX: OPTIMIZED GRAVITY FORMS INTEGRATION
    // =============================================================================

    /**
     * PHASE 1 FIX: Atomic Gravity Forms asset enqueuing with performance optimization
     */
    public function enqueue_gravity_form_assets($form, $config)
    {
        $timer_id = null;
        if ($this->performance) {
            $timer_id = $this->performance->start_timer('atomic_gravity_assets');
        }

        // CRITICAL: Prevent concurrent Gravity Forms loading
        if (self::$atomic_loading_flags['gravity_loading']) {
            self::$performance_stats['duplicate_preventions']++;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ⏸️ PREVENTED - Gravity loading already in progress for form ' . $form['id']);
            }
            return;
        }

        // Check if already loaded for this specific form
        $form_cache_key = 'gravity_form_' . $form['id'];
        if (
            self::$global_loading_state['gravity_loaded'] ||
            isset($this->loaded_assets[$form_cache_key])
        ) {
            self::$performance_stats['duplicate_preventions']++;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ⏭️ PREVENTED - Gravity assets already loaded for form ' . $form['id']);
            }
            return;
        }

        // Set atomic flag
        self::$atomic_loading_flags['gravity_loading'] = true;

        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: 🚀 ATOMIC LOADING - Gravity assets for form ' . $form['id']);
            }

            // Ensure frontend assets are loaded first (atomic)
            $this->enqueue_frontend_assets();

            // Process form configuration efficiently
            $this->process_form_configuration($form, $config);

            // Mark as completed
            $this->loaded_assets[$form_cache_key] = true;
            self::$global_loading_state['gravity_loaded'] = true;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ✅ ATOMIC GRAVITY LOADING COMPLETE - Form ' . $form['id']);
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Assets: ❌ ATOMIC GRAVITY ERROR: ' . $e->getMessage());
            }
        } finally {
            // Always clear the atomic flag
            self::$atomic_loading_flags['gravity_loading'] = false;

            if ($this->performance && $timer_id) {
                $this->performance->stop_timer($timer_id, 'Atomic Gravity assets completed for form: ' . $form['id']);
            }
        }
    }

    /**
     * PHASE 1 FIX: Efficient form configuration processing
     */
    private function process_form_configuration($form, $config)
    {
        // Process configuration for JavaScript efficiently
        $field_mappings = $this->safe_json_decode($config->field_mappings, array());
        $result_mappings = $this->safe_json_decode($config->result_mappings, array());

        // Localize form-specific configuration (only once per form)
        $config_handle = 'operaton_config_' . $form['id'];

        // Check if already localized for this form
        if (!isset($this->loaded_assets['config_' . $form['id']])) {
            wp_localize_script('operaton-dmn-gravity-integration', $config_handle, array(
                'config_id' => $config->id,
                'button_text' => $config->button_text,
                'field_mappings' => $field_mappings,
                'result_mappings' => $result_mappings,
                'form_id' => $form['id'],
                'evaluation_step' => isset($config->evaluation_step) ? $config->evaluation_step : 'auto',
                'use_process' => isset($config->use_process) ? $config->use_process : false,
                'show_decision_flow' => isset($config->show_decision_flow) ? $config->show_decision_flow : false,
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'atomic_loading' => true
            ));

            $this->loaded_assets['config_' . $form['id']] = true;
        }
    }

    /**
     * PHASE 1 FIX: Safe JSON decoding with caching
     */
    private function safe_json_decode($json_string, $default = array())
    {
        if (empty($json_string)) {
            return $default;
        }

        $decoded = json_decode($json_string, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $default;
    }

    // =============================================================================
    // PHASE 1 FIX: EARLY DETECTION AND CACHING
    // =============================================================================

    /**
     * PHASE 1 FIX: Early Gravity Forms detection with caching
     */
    public function early_gravity_detection()
    {
        if (is_admin()) {
            return;
        }

        // Check cache first
        $request_hash = md5($_SERVER['REQUEST_URI'] ?? '');
        if (isset(self::$operation_cache['early_detection'][$request_hash])) {
            if (self::$operation_cache['early_detection'][$request_hash]) {
                $this->queue_assets_for_early_loading();
            }
            return;
        }

        $should_load_early = false;

        // Check for GF preview pages
        if (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview') {
            $should_load_early = true;
        }
        // Check post content early for shortcodes
        elseif (self::has_gravity_forms_content()) {
            $should_load_early = true;
        }

        // Cache the result
        self::$operation_cache['early_detection'][$request_hash] = $should_load_early;

        if ($should_load_early) {
            $this->queue_assets_for_early_loading();
        }
    }

    /**
     * PHASE 1 FIX: Check for Gravity Forms content efficiently
     */
    private static function has_gravity_forms_content()
    {
        global $post;

        if (!$post) {
            return false;
        }

        // Use cached result if available
        $post_cache_key = 'gf_content_' . $post->ID;
        if (isset(self::$operation_cache[$post_cache_key])) {
            return self::$operation_cache[$post_cache_key];
        }

        $has_gf = has_shortcode($post->post_content, 'gravityform') ||
            has_block('gravityforms/form', $post);

        // Cache the result
        self::$operation_cache[$post_cache_key] = $has_gf;

        return $has_gf;
    }

    /**
     * PHASE 1 FIX: Queue assets for early loading
     */
    private function queue_assets_for_early_loading()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'), 8);
    }

    // =============================================================================
    // ADMIN ASSETS (Optimized)
    // =============================================================================

    /**
     * Register admin assets
     */
    public function register_admin_assets()
    {
        wp_register_style(
            'operaton-dmn-admin',
            $this->plugin_url . 'assets/css/admin.css',
            array(),
            $this->version
        );

        wp_register_script(
            'operaton-dmn-admin',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );
    }

    /**
     * PHASE 1 FIX: Atomic admin asset loading
     */
    public function maybe_enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'operaton-dmn') === false) {
            return;
        }

        // Prevent concurrent admin loading
        if (self::$atomic_loading_flags['admin_loading']) {
            self::$performance_stats['duplicate_preventions']++;
            return;
        }

        if (self::$global_loading_state['admin_loaded']) {
            self::$performance_stats['duplicate_preventions']++;
            return;
        }

        self::$atomic_loading_flags['admin_loading'] = true;

        try {
            $this->enqueue_admin_assets($hook);
            self::$global_loading_state['admin_loaded'] = true;
        } finally {
            self::$atomic_loading_flags['admin_loading'] = false;
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        wp_enqueue_style('operaton-dmn-admin');
        wp_enqueue_script('operaton-dmn-admin');

        wp_localize_script('operaton-dmn-admin', 'operaton_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('operaton_admin_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'strings' => array(
                'testing' => __('Testing...', 'operaton-dmn'),
                'success' => __('Success!', 'operaton-dmn'),
                'error' => __('Error occurred', 'operaton-dmn')
            )
        ));

        $this->loaded_assets['admin'] = true;
    }

    // =============================================================================
    // COMPATIBILITY AND UTILITY METHODS
    // =============================================================================

    /**
     * Enhanced document compatibility check
     */
    public function check_document_compatibility()
    {
        if (is_admin() || !self::should_load_frontend_assets()) {
            return;
        }

        ?>
        <script type="text/javascript">
            /* Operaton DMN: Enhanced Compatibility Check */
            (function() {
                'use strict';

                var compatibilityInfo = {
                    jqueryAvailable: typeof jQuery !== 'undefined',
                    jqueryVersion: typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'none',
                    quirksMode: document.compatMode === "BackCompat",
                    doctype: document.doctype ? document.doctype.name : 'missing',
                    atomicLoading: true,
                    timestamp: Date.now()
                };

                // Store globally for debugging
                window.operatonCompatibilityInfo = compatibilityInfo;

                if (<?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>) {
                    console.log('✅ Operaton DMN Enhanced Compatibility Check:', compatibilityInfo);
                }
            })();
        </script>
        <?php
    }

    // =============================================================================
    // PHASE 1 FIX: PERFORMANCE MONITORING AND OPTIMIZATION
    // =============================================================================

    /**
     * PHASE 1 FIX: Reset all loading states (for testing and cache clearing)
     */
    public static function reset_all_loading_states()
    {
        self::$global_loading_state = array(
            'frontend_loaded' => false,
            'admin_loaded' => false,
            'gravity_loaded' => false,
            'detection_complete' => false,
            'should_load' => false,
            'last_detection_hash' => null
        );

        self::$atomic_loading_flags = array(
            'frontend_loading' => false,
            'gravity_loading' => false,
            'detection_running' => false,
            'admin_loading' => false
        );

        self::$operation_cache = array(
            'form_detection' => array(),
            'dmn_form_check' => array(),
            'gravity_forms_available' => null,
            'cache_timestamp' => 0
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: 🔄 All loading states reset');
        }
    }

    /**
     * PHASE 1 FIX: Get comprehensive status for debugging
     */
    public static function get_enhanced_status()
    {
        return array(
            'global_state' => self::$global_loading_state,
            'atomic_flags' => self::$atomic_loading_flags,
            'performance_stats' => self::$performance_stats,
            'cache_info' => array(
                'cache_entries' => count(self::$operation_cache),
                'cache_age' => time() - self::$operation_cache['cache_timestamp'],
                'cache_fresh' => self::is_cache_fresh()
            ),
            'wordpress_states' => array(
                'frontend_registered' => wp_script_is('operaton-dmn-frontend', 'registered'),
                'frontend_enqueued' => wp_script_is('operaton-dmn-frontend', 'enqueued'),
                'frontend_done' => wp_script_is('operaton-dmn-frontend', 'done'),
                'jquery_enqueued' => wp_script_is('jquery', 'enqueued'),
                'gravity_integration_registered' => wp_script_is('operaton-dmn-gravity-integration', 'registered')
            )
        );
    }

    /**
     * PHASE 1 FIX: Log performance statistics
     */
    public function log_performance_stats()
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log('=== OPERATON DMN ASSETS PERFORMANCE REPORT ===');
        error_log('Asset Load Count: ' . self::$performance_stats['asset_load_count']);
        error_log('Detection Runs: ' . self::$performance_stats['detection_runs']);
        error_log('Cache Hits: ' . self::$performance_stats['cache_hits']);
        error_log('Duplicate Preventions: ' . self::$performance_stats['duplicate_preventions']);

        $efficiency = self::$performance_stats['detection_runs'] > 0 ?
            round((self::$performance_stats['cache_hits'] / self::$performance_stats['detection_runs']) * 100, 2) : 0;
        error_log('Cache Efficiency: ' . $efficiency . '%');

        error_log('============================================');
    }

    // =============================================================================
    // RADIO SYNC AND ADDITIONAL FEATURES (Optimized)
    // =============================================================================

    /**
     * PHASE 1 FIX: Optimized radio sync asset enqueuing
     */
    public function enqueue_radio_sync_assets($form_id = null)
    {
        // Use smart caching for radio sync detection
        $cache_key = 'radio_sync_' . $form_id;
        if (isset(self::$operation_cache[$cache_key])) {
            if (!self::$operation_cache[$cache_key]) {
                return; // Cached result: doesn't need radio sync
            }
        } else {
            $needs_sync = $this->form_needs_radio_sync($form_id);
            self::$operation_cache[$cache_key] = $needs_sync;

            if (!$needs_sync) {
                return;
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Enqueuing radio sync assets for form: ' . $form_id);
        }

        // Register and enqueue radio sync assets
        if (!wp_script_is('operaton-dmn-radio-sync', 'registered')) {
            wp_register_script(
                'operaton-dmn-radio-sync',
                $this->plugin_url . 'assets/js/radio-sync.js',
                array('jquery'),
                $this->version,
                true
            );

            wp_register_style(
                'operaton-dmn-radio-sync',
                $this->plugin_url . 'assets/css/radio-sync.css',
                array(),
                $this->version
            );
        }

        wp_enqueue_script('operaton-dmn-radio-sync');
        wp_enqueue_style('operaton-dmn-radio-sync');

        // Localize with form-specific data
        wp_localize_script('operaton-dmn-radio-sync', 'operaton_radio_sync', array(
            'form_id' => $form_id,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'field_mappings' => $this->get_radio_sync_mappings($form_id),
            'strings' => array(
                'sync_complete' => __('Radio buttons synchronized', 'operaton-dmn'),
                'sync_error' => __('Radio synchronization error', 'operaton-dmn'),
                'initializing' => __('Initializing radio sync...', 'operaton-dmn')
            )
        ));
    }

    /**
     * Check if a form needs radio synchronization (with caching)
     */
    private function form_needs_radio_sync($form_id)
    {
        // Form 8 specifically needs radio sync
        if ($form_id == 8) {
            return true;
        }

        // Check if form has HTML fields with radio buttons
        if ($this->gravity_forms_manager && $this->gravity_forms_manager->is_gravity_forms_available()) {
            try {
                if (class_exists('GFAPI')) {
                    $form = GFAPI::get_form($form_id);
                    if ($form && isset($form['fields'])) {
                        foreach ($form['fields'] as $field) {
                            if (
                                $field->type === 'html' &&
                                strpos($field->content, 'type="radio"') !== false &&
                                strpos($field->content, 'aanvrager') !== false
                            ) {
                                return true;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN Assets: Error checking form for radio sync: ' . $e->getMessage());
                }
            }
        }

        return false;
    }

    /**
     * Get radio sync field mappings for a specific form
     */
    private function get_radio_sync_mappings($form_id)
    {
        // Default mappings for form 8
        $default_mappings = array(
            'aanvragerDitKalenderjaarAlAangevraagd' => 'input_8_25',
            'aanvragerAanmerkingStudieFinanciering' => 'input_8_26',
            'aanvragerUitkeringBaanbrekers' => 'input_8_27',
            'aanvragerVoedselbankpasDenBosch' => 'input_8_28',
            'aanvragerKwijtscheldingGemeentelijkeBelastingen' => 'input_8_29',
            'aanvragerSchuldhulptrajectKredietbankNederland' => 'input_8_30',
            'aanvragerHeeftKind4Tm17' => 'input_8_31'
        );

        if ($form_id == 8) {
            return $default_mappings;
        }

        return array();
    }

    // =============================================================================
    // DECISION FLOW ASSETS (Optimized)
    // =============================================================================

    /**
     * PHASE 1 FIX: Optimized decision flow asset enqueuing
     */
    public function enqueue_decision_flow_assets()
    {
        // Prevent duplicate loading
        if (isset($this->loaded_assets['decision_flow'])) {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Enqueuing decision flow assets');
        }

        // Register if not already registered
        if (!wp_script_is('operaton-dmn-decision-flow', 'registered')) {
            wp_register_script(
                'operaton-dmn-decision-flow',
                $this->plugin_url . 'assets/js/decision-flow.js',
                array('jquery', 'operaton-dmn-frontend'),
                $this->version,
                true
            );
        }

        if (!wp_style_is('operaton-dmn-decision-flow', 'registered')) {
            wp_register_style(
                'operaton-dmn-decision-flow',
                $this->plugin_url . 'assets/css/decision-flow.css',
                array(),
                $this->version
            );
        }

        // Enqueue assets
        wp_enqueue_script('operaton-dmn-decision-flow');
        wp_enqueue_style('operaton-dmn-decision-flow');

        $this->loaded_assets['decision_flow'] = true;
    }

    public function should_load_decision_flow_assets()
    {
        // Check if any forms on the page have decision flow enabled
        if (!$this->gravity_forms_manager) {
            return false;
        }

        // Use centralized detection
        if (!self::should_load_frontend_assets()) {
            return false;
        }

        // Check if current page has forms with decision flow enabled
        global $post;
        if (!$post) {
            return false;
        }

        // Extract form IDs from page content
        $form_ids = array();

        // Check shortcodes
        if (has_shortcode($post->post_content, 'gravityform')) {
            preg_match_all('/\[gravityform[^\]]*id=["\'](\d+)["\'][^\]]*\]/', $post->post_content, $matches);
            if (!empty($matches[1])) {
                $form_ids = array_merge($form_ids, array_map('intval', $matches[1]));
            }
        }

        // Check blocks
        if (has_block('gravityforms/form', $post)) {
            $blocks = parse_blocks($post->post_content);
            foreach ($blocks as $block) {
                if ($block['blockName'] === 'gravityforms/form' && isset($block['attrs']['formId'])) {
                    $form_ids[] = intval($block['attrs']['formId']);
                }
            }
        }

        // Check if any forms have decision flow enabled
        foreach ($form_ids as $form_id) {
            $config = $this->gravity_forms_manager->get_form_configuration($form_id);
            if (
                $config && isset($config->show_decision_flow) && $config->show_decision_flow &&
                isset($config->use_process) && $config->use_process
            ) {
                return true;
            }
        }

        return false;
    }

    // =============================================================================
    // UTILITY AND ACCESS METHODS
    // =============================================================================

    /**
     * Force enqueue specific assets for manual loading
     */
    public function force_enqueue($asset_group)
    {
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

            case 'radio_sync':
                wp_enqueue_script('operaton-dmn-radio-sync');
                wp_enqueue_style('operaton-dmn-radio-sync');
                break;

            default:
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN Assets: Unknown asset group: ' . $asset_group);
                }
        }
    }

    /**
     * Get current loading state for debugging
     */
    public function get_loading_state()
    {
        return array(
            'local' => $this->loaded_assets,
            'enhanced_status' => self::get_enhanced_status(),
            'performance_summary' => self::$performance_stats
        );
    }

    /**
     * Get plugin URL for external access
     */
    public function get_plugin_url()
    {
        return $this->plugin_url;
    }

    /**
     * Get plugin version for external access
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Clear loaded assets cache for testing
     */
    public function reset_loaded_assets()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: Resetting loaded assets cache');
        }

        $this->loaded_assets = array();
        self::reset_all_loading_states();
    }

    // =============================================================================
    // BACKWARD COMPATIBILITY METHODS
    // =============================================================================

    /**
     * Register frontend assets (for compatibility)
     */
    public function register_frontend_assets()
    {
        // All registration is now handled in the atomic loading methods
        // This method is kept for hook compatibility
    }

    /**
     * Maybe enqueue frontend assets (legacy method)
     */
    public function maybe_enqueue_frontend_assets()
    {
        // Redirect to smart conditional loading
        $this->smart_conditional_loading();
    }

    /**
     * Force enqueue frontend assets (legacy method)
     */
    public function force_enqueue_frontend_assets()
    {
        $this->enqueue_frontend_assets();
    }

    /**
     * Add inline styles (optimized version)
     */
    public function add_inline_styles($form_id = null, $styles = array())
    {
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
        }

        if (!empty($css)) {
            $handle = is_admin() ? 'operaton-dmn-admin' : 'operaton-dmn-frontend';
            wp_add_inline_style($handle, $css);
        }
    }

    /**
     * Get detailed asset status with performance data
     */
    public function get_assets_status()
    {
        $status = array(
            'loaded_assets' => $this->loaded_assets,
            'enhanced_status' => self::get_enhanced_status(),
            'scripts' => array(),
            'styles' => array(),
            'performance' => array()
        );

        // Add performance data if available
        if ($this->performance) {
            $performance_summary = $this->performance->get_summary();
            $status['performance'] = array(
                'total_time_ms' => $performance_summary['total_time_ms'],
                'peak_memory' => $performance_summary['peak_memory_formatted'],
                'milestones_count' => $performance_summary['milestone_count']
            );
        }

        // Check script states
        $our_scripts = array(
            'operaton-dmn-admin',
            'operaton-dmn-frontend',
            'operaton-dmn-gravity-integration',
            'operaton-dmn-decision-flow',
            'operaton-dmn-radio-sync'
        );

        foreach ($our_scripts as $script) {
            $status['scripts'][$script] = array(
                'registered' => wp_script_is($script, 'registered'),
                'enqueued' => wp_script_is($script, 'enqueued'),
                'done' => wp_script_is($script, 'done')
            );
        }

        // Check style states
        $our_styles = array(
            'operaton-dmn-admin',
            'operaton-dmn-frontend',
            'operaton-dmn-decision-flow',
            'operaton-dmn-radio-sync'
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
     * Get compatibility status for debugging
     */
    public function get_compatibility_status()
    {
        return array(
            'check_enabled' => true,
            'atomic_loading' => true,
            'performance_optimized' => true,
            'cache_enabled' => true,
            'duplicate_prevention' => true,
            'hooks_registered' => array(
                'wp_head' => has_action('wp_head', array($this, 'check_document_compatibility')),
                'template_redirect' => has_action('template_redirect', array($this, 'early_gravity_detection'))
            )
        );
    }

    // =============================================================================
    // BACKWARD COMPATIBILITY METHODS (CRITICAL FOR EXISTING CODE)
    // =============================================================================

    /**
     * CRITICAL FIX: Add missing get_coordinator_status method for backward compatibility
     * This method is called by the main plugin file's debug functions
     */
    public static function get_coordinator_status()
    {
        return self::get_enhanced_status();
    }

    /**
     * CRITICAL FIX: Add missing reset_loading_coordinator method for backward compatibility
     */
    public static function reset_loading_coordinator()
    {
        self::reset_all_loading_states();
    }

    /**
     * COMPATIBILITY: Legacy should_load_frontend_assets check
     * Some parts of the old code may still call this differently
     */
    public function should_load_assets()
    {
        return self::should_load_frontend_assets();
    }

    /**
     * COMPATIBILITY: Legacy maybe_enqueue_frontend_assets method
     */
    public function maybe_enqueue_frontend_assets_legacy()
    {
        $this->smart_conditional_loading();
    }

    /**
     * COMPATIBILITY: Get current loading state in old format
     */
    public function get_loading_state_legacy()
    {
        return array(
            'local' => $this->loaded_assets,
            'global' => self::$global_loading_state,
            'wordpress_states' => array(
                'frontend_registered' => wp_script_is('operaton-dmn-frontend', 'registered'),
                'frontend_enqueued' => wp_script_is('operaton-dmn-frontend', 'enqueued'),
                'frontend_done' => wp_script_is('operaton-dmn-frontend', 'done'),
                'jquery_enqueued' => wp_script_is('jquery', 'enqueued')
            )
        );
    }

    /**
     * COMPATIBILITY: Reset global state (for testing)
     */
    public static function reset_global_state()
    {
        self::reset_all_loading_states();
    }

    /**
     * COMPATIBILITY: Check if should run compatibility check
     */
    private function should_run_compatibility_check()
    {
        return self::should_load_frontend_assets();
    }

    /**
     * COMPATIBILITY: Has DMN enabled forms on page
     */
    private function has_dmn_enabled_forms_on_page()
    {
        return self::has_gravity_forms_content();
    }

    /**
     * COMPATIBILITY: Extract form IDs from shortcodes
     */
    private function extract_form_ids_from_shortcodes($content)
    {
        $form_ids = array();
        $pattern = '/\[gravityform[^\]]*id=["\'](\d+)["\'][^\]]*\]/';

        if (preg_match_all($pattern, $content, $matches)) {
            $form_ids = array_map('intval', $matches[1]);
        }

        return array_unique($form_ids);
    }

    /**
     * COMPATIBILITY: Extract form IDs from blocks
     */
    private function extract_form_ids_from_blocks($post)
    {
        $form_ids = array();

        if (function_exists('parse_blocks')) {
            $blocks = parse_blocks($post->post_content);
            $form_ids = $this->find_gravity_form_ids_in_blocks($blocks);
        }

        return array_unique($form_ids);
    }

    /**
     * COMPATIBILITY: Find gravity form IDs in blocks
     */
    private function find_gravity_form_ids_in_blocks($blocks)
    {
        $form_ids = array();

        foreach ($blocks as $block) {
            if ($block['blockName'] === 'gravityforms/form') {
                if (isset($block['attrs']['formId'])) {
                    $form_ids[] = intval($block['attrs']['formId']);
                }
            }

            if (!empty($block['innerBlocks'])) {
                $inner_ids = $this->find_gravity_form_ids_in_blocks($block['innerBlocks']);
                $form_ids = array_merge($form_ids, $inner_ids);
            }
        }

        return $form_ids;
    }

    /**
     * COMPATIBILITY: Check if any forms have DMN config
     */
    private function any_forms_have_dmn_config($form_ids)
    {
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
}
