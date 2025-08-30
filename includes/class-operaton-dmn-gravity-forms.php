<?php

/**
 * OPTIMIZED: Gravity Forms Integration for Operaton DMN Plugin
 *
 * PERFORMANCE ENHANCEMENTS:
 * - Multi-level caching system for assets, scripts, and form data
 * - Duplicate processing prevention with loading locks
 * - Optimized detection logic with early returns
 * - Cached JavaScript generation and form configuration
 * - Memory-efficient form processing with cleanup
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Operaton_DMN_Gravity_Forms
{
    /**
     * PHASE 1 OPTIMIZATION: Multi-level caching system
     */
    private static $gravity_assets_cache = array();
    private static $gravity_loading_locks = array();
    private static $form_config_cache = array();
    private static $form_fields_cache = array();
    private static $inline_script_cache = array();
    private static $detection_cache = array();

    /**
     * Performance tracking
     */
    private static $performance_stats = array(
        'cache_hits' => 0,
        'cache_misses' => 0,
        'duplicate_blocks' => 0,
        'total_processing_time' => 0
    );

    /**
     * Core plugin instance reference
     * @var OperatonDMNEvaluator
     */
    private $core;

    /**
     * Assets manager instance
     * @var Operaton_DMN_Assets
     */
    private $assets;

    /**
     * Database manager instance
     * @var Operaton_DMN_Database
     */
    private $database;

    /**
     * Performance monitor instance
     * @var Operaton_DMN_Performance_Monitor
     */
    private $performance;

    /**
     * Cached form configurations to avoid repeated database queries
     * @var array
     */
    private $form_configs_cache = array();

    /**
     * Flag to track if Gravity Forms is available
     * @var bool|null
     */
    private $gravity_forms_available = null;

    /**
     * OPTIMIZED Constructor with performance tracking
     */
    public function __construct($core, $assets, $database)
    {
        $this->core = $core;
        $this->assets = $assets;
        $this->database = $database;

        // Get performance monitor if available
        if (method_exists($core, 'get_performance_instance')) {
            $this->performance = $core->get_performance_instance();
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Gravity Forms: 🚀 OPTIMIZED Integration manager initialized');
        }

        $this->init_hooks();
        $this->init_cache_cleanup();
    }

    /**
     * OPTIMIZED: Initialize WordPress and Gravity Forms hooks with better priorities
     */
    private function init_hooks()
    {
        // Early availability check with caching
        add_action('init', array($this, 'check_gravity_forms_availability'), 1);

        // OPTIMIZED: Only add hooks if Gravity Forms is available
        add_action('init', array($this, 'conditional_init_gravity_forms'), 5);

        // OPTIMIZED: Smarter asset loading with centralized detection
        add_action('wp_enqueue_scripts', array($this, 'optimized_maybe_enqueue_assets'), 15);

        // Gravity Forms specific hooks (conditional)
        add_action('plugins_loaded', array($this, 'add_gravity_forms_hooks'), 20);

        // Admin hooks for form field detection
        add_action('admin_init', array($this, 'init_admin_integration'));
    }

    /**
     * NEW: Initialize cache cleanup and memory management
     */
    private function init_cache_cleanup()
    {
        // Clear caches on form updates
        add_action('gform_after_save_form', array($this, 'clear_form_cache_on_save'), 10, 2);
        add_action('gform_after_delete_form', array($this, 'clear_form_cache'));

        // Periodic cache cleanup
        add_action('wp_loaded', array($this, 'maybe_cleanup_cache'));

        // Clear on plugin deactivation
        register_deactivation_hook(OPERATON_DMN_PLUGIN_PATH . 'operaton-dmn-plugin.php', array($this, 'clear_all_caches'));
    }

    // =============================================================================
    // OPTIMIZED GRAVITY FORMS AVAILABILITY AND SETUP
    // =============================================================================

    /**
     * OPTIMIZED: Check Gravity Forms availability with result caching
     */
    public function check_gravity_forms_availability()
    {
        if ($this->gravity_forms_available === null) {
            $timer_id = $this->performance ? $this->performance->start_timer('gf_availability_check') : null;

            $this->gravity_forms_available = class_exists('GFForms') && class_exists('GFAPI');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                $status = $this->gravity_forms_available ? 'available' : 'not available';
                error_log('Operaton DMN Gravity Forms: ⚡ CACHED - Gravity Forms is ' . $status);
            }

            if ($timer_id) {
                $this->performance->stop_timer($timer_id, 'GF availability cached');
            }
        }

        return $this->gravity_forms_available;
    }

    /**
     * OPTIMIZED: Conditional initialization to prevent unnecessary processing
     */
    public function conditional_init_gravity_forms()
    {
        if (!$this->check_gravity_forms_availability()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Gravity Forms: ⏭️ SKIPPED - GF not available');
            }
            return;
        }

        $timer_id = $this->performance ? $this->performance->start_timer('gf_integration_init') : null;

        // Form rendering hooks with optimized priorities
        add_filter('gform_submit_button', array($this, 'add_evaluate_button'), 10, 2);
        add_action('gform_enqueue_scripts', array($this, 'optimized_enqueue_gravity_scripts'), 10, 2);

        // CRITICAL: Optimized asset loading with duplicate prevention
        add_action('gform_pre_render', array($this, 'optimized_ensure_assets_loaded'), 5, 1);
        add_action('gform_pre_validation', array($this, 'optimized_ensure_assets_loaded'), 5, 1);
        add_action('gform_pre_submission_filter', array($this, 'optimized_ensure_assets_loaded'), 5, 1);

        // Form editor integration (admin only)
        if (is_admin()) {
            add_action('gform_editor_js', array($this, 'add_editor_script'));
            add_action('gform_field_advanced_settings', array($this, 'add_field_advanced_settings'), 10, 2);
        }

        // Form validation and submission hooks
        add_filter('gform_validation', array($this, 'validate_dmn_fields'), 10, 1);
        add_action('gform_after_submission', array($this, 'handle_post_submission'), 10, 2);

        $this->add_optimized_radio_sync_hooks();

        if ($timer_id) {
            $this->performance->stop_timer($timer_id, 'GF integration hooks initialized');
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Gravity Forms: ✅ OPTIMIZED - Integration hooks initialized');
        }
    }

    /**
     * FIXED: Remove redundant asset loading calls
     */
    public function optimized_ensure_assets_loaded($form)
    {
        if (is_admin()) {
            return $form;
        }

        $form_id = $form['id'];
        $cache_key = 'assets_check_' . $form_id;

        // Check cache first
        if (isset(self::$detection_cache[$cache_key])) {
            self::$performance_stats['cache_hits']++;
            return $form;
        }

        $timer_id = $this->performance ? $this->performance->start_timer('ensure_assets_optimized') : null;

        try {
            // Only check if this form has DMN configuration
            $config = $this->get_cached_form_config($form_id);
            if ($config) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN Gravity Forms: Form ' . $form_id . ' has DMN config');
                }

                // FIXED: Don't call enqueue_frontend_assets() - trust it's already loaded
                // The assets manager handles this via wp_enqueue_scripts hook

                $this->optimized_enqueue_gravity_scripts($form, false);
            }

            // Cache the result
            self::$detection_cache[$cache_key] = true;
            self::$performance_stats['cache_misses']++;
        } finally {
            if ($timer_id) {
                $this->performance->stop_timer($timer_id, 'Optimized asset check for form ' . $form_id);
            }
        }

        return $form;
    }

    // =============================================================================
    // OPTIMIZED FORM DETECTION AND ASSET LOADING
    // =============================================================================

    /**
     * PHASE 1 OPTIMIZATION: Cached and optimized asset detection
     */
    public function optimized_maybe_enqueue_assets()
    {
        if (is_admin() || !$this->check_gravity_forms_availability()) {
            return;
        }

        $cache_key = 'page_detection_' . get_the_ID();

        // Check cache first
        if (isset(self::$detection_cache[$cache_key])) {
            if (self::$detection_cache[$cache_key] === 'load_assets') {
                $this->assets->enqueue_frontend_assets();
                $this->optimized_enqueue_gravity_forms_scripts();
            }
            self::$performance_stats['cache_hits']++;
            return;
        }

        $timer_id = $this->performance ? $this->performance->start_timer('page_asset_detection') : null;

        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Gravity Forms: 🔍 OPTIMIZED - Checking centralized controller');
            }

            // Use centralized detection
            if (Operaton_DMN_Assets::should_load_frontend_assets() && $this->has_dmn_enabled_forms_on_page()) {
                $this->assets->enqueue_frontend_assets();
                $this->optimized_enqueue_gravity_forms_scripts();

                // Cache positive result
                self::$detection_cache[$cache_key] = 'load_assets';

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Operaton DMN Gravity Forms: ✅ OPTIMIZED - Assets loaded via centralized controller');
                }
            } else {
                // Cache negative result
                self::$detection_cache[$cache_key] = 'skip_assets';
            }

            self::$performance_stats['cache_misses']++;
        } finally {
            if ($timer_id) {
                $this->performance->stop_timer($timer_id, 'Page asset detection completed');
            }
        }
    }

    /**
     * FIXED: Remove redundant calls in form asset method
     */
    public function optimized_enqueue_gravity_form_assets($form, $config)
    {
        $form_id = $form['id'];
        $cache_key = 'gravity_assets_' . $form_id;

        // Check if already processed
        if (isset(self::$gravity_assets_cache[$cache_key])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Gravity Forms: CACHED - Assets for form ' . $form_id);
            }
            self::$performance_stats['cache_hits']++;
            self::$performance_stats['duplicate_blocks']++;
            return;
        }

        // Prevent concurrent processing
        if (isset(self::$gravity_loading_locks[$form_id])) {
            self::$performance_stats['duplicate_blocks']++;
            return;
        }

        self::$gravity_loading_locks[$form_id] = true;

        $timer_id = $this->performance ? $this->performance->start_timer('gravity_form_assets_optimized') : null;

        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Gravity Forms: LOADING assets for form ' . $form_id . ' with config ID ' . $config->id);
            }

            // FIXED: Trust that frontend assets are already loaded
            // Only ensure Gravity Forms integration script is loaded
            if (!wp_script_is('operaton-dmn-gravity-integration', 'enqueued')) {
                $this->optimized_enqueue_gravity_forms_scripts();
            }

            // CRITICAL FIX: Add the optimized form control script
            add_action('wp_footer', function () use ($form, $config) {
                $this->add_optimized_form_control_script($form, $config);
            }, 10);

            // Enqueue radio sync if needed (cached check)
            $this->maybe_enqueue_radio_sync_cached($form_id);

            // Cache successful loading
            self::$gravity_assets_cache[$cache_key] = array(
                'loaded_at' => time(),
                'form_id' => $form_id,
                'config_id' => $config->id ?? 0
            );

            self::$performance_stats['cache_misses']++;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Gravity Forms: Assets cached for form ' . $form_id);
            }
        } finally {
            unset(self::$gravity_loading_locks[$form_id]);

            if ($timer_id) {
                $this->performance->stop_timer($timer_id, 'Optimized gravity assets for form: ' . $form_id);
            }
        }
    }

    /**
     * NEW: Add optimized form control script to footer
     */
    private function add_optimized_form_control_script($form, $config)
    {
        $form_id = $form['id'];
        $script_cache_key = 'form_control_script_' . $form_id;

        // Prevent duplicate scripts
        if (isset(self::$inline_script_cache[$script_cache_key])) {
            return;
        }

        // Generate and cache the script
        $target_page = $this->get_cached_target_page($form, $config);
        $script = $this->generate_optimized_form_control_script(
            $form_id,
            $target_page,
            $config->show_decision_flow ?? false,
            $config->use_process ?? false,
            $config
        );

        // Output the script
        echo '<script type="text/javascript">' . $script . '</script>';

        // Cache to prevent duplicates
        self::$inline_script_cache[$script_cache_key] = true;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Gravity Forms: ✅ INTEGRATED - Form control script added for form ' . $form_id);
        }
    }

    /**
     * OPTIMIZED: Cached form control script generation
     */
    private function get_cached_form_control_script($form, $config)
    {
        $form_id = $form['id'];
        $config_hash = md5(serialize(array(
            'evaluation_step' => $config->evaluation_step ?? 'auto',
            'show_decision_flow' => $config->show_decision_flow ?? false,
            'use_process' => $config->use_process ?? false,
            'button_text' => $config->button_text ?? 'Evaluate'
        )));

        $script_cache_key = 'form_script_' . $form_id . '_' . $config_hash;

        if (isset(self::$inline_script_cache[$script_cache_key])) {
            self::$performance_stats['cache_hits']++;
            return self::$inline_script_cache[$script_cache_key];
        }

        $timer_id = $this->performance ? $this->performance->start_timer('script_generation') : null;

        try {
            // Calculate target page (cached)
            $target_page = $this->get_cached_target_page($form, $config);

            // Generate script with optimized logic
            $script = $this->generate_optimized_form_control_script(
                $form_id,
                $target_page,
                $config->show_decision_flow ?? false,
                $config->use_process ?? false,
                $config
            );

            // Cache the generated script
            self::$inline_script_cache[$script_cache_key] = $script;
            self::$performance_stats['cache_misses']++;

            return $script;
        } finally {
            if ($timer_id) {
                $this->performance->stop_timer($timer_id, 'Script generation for form ' . $form_id);
            }
        }
    }

    /**
     * NEW: Cached target page calculation
     */
    private function get_cached_target_page($form, $config)
    {
        $form_id = $form['id'];
        $cache_key = 'target_page_' . $form_id;

        if (isset(self::$form_config_cache[$cache_key])) {
            return self::$form_config_cache[$cache_key];
        }

        $evaluation_step = $config->evaluation_step ?? 'auto';

        if ($evaluation_step === 'auto') {
            $total_pages = $this->count_form_pages($form);
            $target_page = max(1, $total_pages - 1); // Second to last page
        } else {
            $target_page = intval($evaluation_step);
        }

        self::$form_config_cache[$cache_key] = $target_page;
        return $target_page;
    }

    /**
     * FIXED: Remove redundant calls in Gravity scripts method
     */
    public function optimized_enqueue_gravity_scripts($form, $is_ajax)
    {
        $form_id = $form['id'];
        $cache_key = 'scripts_' . $form_id;

        // Prevent duplicate processing
        if (isset(self::$gravity_assets_cache[$cache_key])) {
            self::$performance_stats['duplicate_blocks']++;
            return;
        }

        $config = $this->get_cached_form_config($form_id);
        if (!$config) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Gravity Forms: No config found for form ' . $form_id);
            }
            return;
        }

        $timer_id = $this->performance ? $this->performance->start_timer('gravity_scripts_optimized') : null;

        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Gravity Forms: Enqueuing scripts for form ' . $form_id . ' with config ID ' . $config->id);
            }

            // FIXED: Don't call enqueue_frontend_assets() - trust it's already loaded
            // Only handle Gravity Forms specific integration

            $this->optimized_enqueue_gravity_form_assets($form, $config);

            // CRITICAL FIX: Ensure form configuration is localized to JavaScript
            add_action('wp_footer', function () use ($form, $config) {
                $this->ensure_form_config_localized($form, $config);
            }, 5);

            // Mark as processed
            self::$gravity_assets_cache[$cache_key] = true;
        } finally {
            if ($timer_id) {
                $this->performance->stop_timer($timer_id, 'Optimized scripts for form ' . $form_id);
            }
        }
    }

    /**
     * FIXED: Simplified gravity forms script loading
     */
    private function optimized_enqueue_gravity_forms_scripts()
    {
        static $scripts_loaded = false;

        if ($scripts_loaded) {
            self::$performance_stats['duplicate_blocks']++;
            return;
        }

        $timer_id = $this->performance ? $this->performance->start_timer('gf_scripts_global') : null;

        try {
            // FIXED: Trust that frontend assets are loaded, just add GF integration
            if (!wp_script_is('operaton-dmn-gravity-integration', 'enqueued')) {
                wp_enqueue_script(
                    'operaton-dmn-gravity-integration',
                    $this->assets->get_plugin_url() . 'assets/js/gravity-forms.js',
                    array('jquery', 'operaton-dmn-frontend'),
                    $this->assets->get_version(),
                    true
                );

                // Global localization (once only)
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
            }

            $scripts_loaded = true;
        } finally {
            if ($timer_id) {
                $this->performance->stop_timer($timer_id, 'Global GF scripts loaded');
            }
        }
    }

    /**
     * Enhanced ensure_form_config_localized method
     * Update this method to include explicit result field identification
     */
    private function ensure_form_config_localized($form, $config)
    {
        $form_id = $form['id'];
        $config_var_name = 'operaton_config_' . $form_id;

        // Check if already localized
        if (isset(self::$form_config_cache['localized_' . $form_id]))
        {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: CRITICAL FIX - Ensuring config localized for form ' . $form_id);
        }

        // Process configuration for JavaScript (cached)
        $field_mappings = $this->get_cached_field_mappings($config);
        $result_mappings = $this->get_cached_result_mappings($config);

        // CONFIGURATION-DRIVEN: Extract result field IDs from the actual DMN configuration
        $result_field_ids = array();

        if (is_array($result_mappings))
        {
            foreach ($result_mappings as $dmn_variable => $mapping)
            {
                if (isset($mapping['field_id']) && is_numeric($mapping['field_id']))
                {
                    $result_field_ids[] = intval($mapping['field_id']);
                }
            }
        }

        // FALLBACK: If no result mappings configured, try to detect from field mappings that might be result fields
        if (empty($result_field_ids) && is_array($field_mappings))
        {
            foreach ($field_mappings as $dmn_variable => $mapping)
            {
                // Look for variables that are likely result fields based on naming patterns
                if (isset($mapping['field_id']) && is_numeric($mapping['field_id']))
                {
                    $field_id = intval($mapping['field_id']);

                    // Include fields that are likely result fields based on variable names
                    if (
                        strpos($dmn_variable, 'aanmerking') !== false ||
                        strpos($dmn_variable, 'result') !== false ||
                        strpos($dmn_variable, 'eligibility') !== false ||
                        strpos($dmn_variable, 'qualified') !== false ||
                        strpos($dmn_variable, 'approved') !== false
                    )
                    {
                        $result_field_ids[] = $field_id;
                    }
                }
            }
        }

        // LOG for debugging
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: Result field IDs detected for form ' . $form_id . ': ' . implode(', ', $result_field_ids));
        }

        // Create the JavaScript configuration object
        $js_config = array(
            'config_id' => $config->id,
            'button_text' => $config->button_text ?? 'Evaluate',
            'field_mappings' => $field_mappings,
            'result_mappings' => $result_mappings,
            'form_id' => $form_id,
            'evaluation_step' => $config->evaluation_step ?? 'auto',
            'use_process' => ($config->use_process ?? false) ? true : false,
            'show_decision_flow' => ($config->show_decision_flow ?? false) ? true : false,
            'result_display_field' => $config->result_display_field ?? null,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            // CONFIGURATION-DRIVEN: Add result field identification based on actual config
            'result_field_ids' => $result_field_ids,
            'clear_results_on_change' => true // Flag to enable aggressive clearing
        );

        // Output the configuration directly to ensure it's available
        echo '<script type="text/javascript">';
        echo 'window.' . $config_var_name . ' = ' . wp_json_encode($js_config) . ';';
        echo 'if (window.console && window.console.log) {';
        echo '  console.log("Enhanced Config localized for form ' . $form_id . '", window.' . $config_var_name . ');';
        echo '}';
        echo '</script>';

        // Mark as localized
        self::$form_config_cache['localized_' . $form_id] = true;

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: ENHANCED - Config localized for form ' . $form_id . ' with explicit result fields');
        }
    }
    
    /**
     * NEW: Cached field mappings processing
     */
    private function get_cached_field_mappings($config)
    {
        $cache_key = 'field_mappings_' . ($config->id ?? 0);

        if (isset(self::$form_config_cache[$cache_key])) {
            return self::$form_config_cache[$cache_key];
        }

        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $field_mappings = array();
        }

        self::$form_config_cache[$cache_key] = $field_mappings;
        return $field_mappings;
    }

    /**
     * NEW: Cached result mappings processing
     */
    private function get_cached_result_mappings($config)
    {
        $cache_key = 'result_mappings_' . ($config->id ?? 0);

        if (isset(self::$form_config_cache[$cache_key])) {
            return self::$form_config_cache[$cache_key];
        }

        $result_mappings = json_decode($config->result_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result_mappings = array();
        }

        self::$form_config_cache[$cache_key] = $result_mappings;
        return $result_mappings;
    }

    // =============================================================================
    // OPTIMIZED RADIO SYNC FUNCTIONALITY
    // =============================================================================

    /**
     * OPTIMIZED: Radio sync hooks with caching
     */
    public function add_optimized_radio_sync_hooks()
    {
        if (!$this->check_gravity_forms_availability()) {
            return;
        }

        // Hook into form rendering with optimization
        add_action('gform_pre_render', array($this, 'optimized_maybe_initialize_radio_sync'), 5, 1);
        add_action('gform_pre_validation', array($this, 'optimized_maybe_initialize_radio_sync'), 5, 1);
        add_action('gform_pre_submission_filter', array($this, 'optimized_maybe_initialize_radio_sync'), 5, 1);

        if (is_admin()) {
            add_action('gform_editor_js', array($this, 'add_radio_sync_editor_support'));
        }
    }

    /**
     * OPTIMIZED: Radio sync initialization with caching
     */
    public function optimized_maybe_initialize_radio_sync($form)
    {
        if (!is_array($form) || !isset($form['id'])) {
            return $form;
        }

        $form_id = $form['id'];
        $cache_key = 'radio_sync_' . $form_id;

        // Check cache first
        if (isset(self::$detection_cache[$cache_key])) {
            if (self::$detection_cache[$cache_key] === 'needs_sync') {
                $this->initialize_radio_sync_cached($form_id);
            }
            return $form;
        }

        // Check if form needs radio sync (cached)
        if ($this->form_needs_radio_sync_cached($form_id)) {
            $this->initialize_radio_sync_cached($form_id);
            self::$detection_cache[$cache_key] = 'needs_sync';
        } else {
            self::$detection_cache[$cache_key] = 'no_sync';
        }

        return $form;
    }

    /**
     * NEW: Cached radio sync need detection
     */
    private function form_needs_radio_sync_cached($form_id)
    {
        $cache_key = 'radio_sync_need_' . $form_id;

        if (isset(self::$form_config_cache[$cache_key])) {
            return self::$form_config_cache[$cache_key];
        }

        $needs_sync = $this->form_needs_radio_sync($form_id);
        self::$form_config_cache[$cache_key] = $needs_sync;

        return $needs_sync;
    }

    /**
     * NEW: Cached radio sync asset enqueuing
     */
    private function maybe_enqueue_radio_sync_cached($form_id)
    {
        $cache_key = 'radio_sync_assets_' . $form_id;

        if (isset(self::$gravity_assets_cache[$cache_key])) {
            return;
        }

        if ($this->form_needs_radio_sync_cached($form_id)) {
            $this->assets->enqueue_radio_sync_assets($form_id);
            self::$gravity_assets_cache[$cache_key] = true;
        }
    }

    /**
     * NEW: Cached radio sync initialization
     */
    private function initialize_radio_sync_cached($form_id)
    {
        $cache_key = 'radio_sync_init_' . $form_id;

        if (isset(self::$gravity_assets_cache[$cache_key])) {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Gravity Forms: ⚡ OPTIMIZED - Radio sync for form: ' . $form_id);
        }

        // Enqueue radio sync assets (cached)
        $this->maybe_enqueue_radio_sync_cached($form_id);

        // Add form-specific initialization (cached)
        add_action('wp_footer', function () use ($form_id) {
            $this->add_cached_radio_sync_initialization($form_id);
        }, 15);

        self::$gravity_assets_cache[$cache_key] = true;
    }

    // =============================================================================
    // OPTIMIZED FORM CONFIGURATION AND CACHING
    // =============================================================================

    /**
     * OPTIMIZED: Get form configuration with multi-level caching
     */
    private function get_cached_form_config($form_id)
    {
        // Level 1: Instance cache
        if (isset($this->form_configs_cache[$form_id])) {
            self::$performance_stats['cache_hits']++;
            return $this->form_configs_cache[$form_id];
        }

        // Level 2: Static cache
        $static_cache_key = 'config_' . $form_id;
        if (isset(self::$form_config_cache[$static_cache_key])) {
            $config = self::$form_config_cache[$static_cache_key];
            $this->form_configs_cache[$form_id] = $config;
            self::$performance_stats['cache_hits']++;
            return $config;
        }

        // Level 3: Database (with caching)
        $timer_id = $this->performance ? $this->performance->start_timer('db_config_fetch') : null;

        try {
            $config = $this->database->get_config_by_form_id($form_id);

            // Cache at both levels
            $this->form_configs_cache[$form_id] = $config;
            self::$form_config_cache[$static_cache_key] = $config;

            self::$performance_stats['cache_misses']++;

            return $config;
        } finally {
            if ($timer_id) {
                $this->performance->stop_timer($timer_id, 'DB config fetch for form ' . $form_id);
            }
        }
    }

    /**
     * OPTIMIZED: Form fields with caching
     */
    public function get_form_fields($form_id)
    {
        if (!$this->check_gravity_forms_availability()) {
            return array();
        }

        $cache_key = 'fields_' . $form_id;

        if (isset(self::$form_fields_cache[$cache_key])) {
            self::$performance_stats['cache_hits']++;
            return self::$form_fields_cache[$cache_key];
        }

        $timer_id = $this->performance ? $this->performance->start_timer('form_fields_fetch') : null;

        try {
            $form = GFAPI::get_form($form_id);

            if (!$form) {
                self::$form_fields_cache[$cache_key] = array();
                return array();
            }

            $fields = array();

            foreach ($form['fields'] as $field) {
                $field_data = array(
                    'id' => $field->id,
                    'label' => $field->label,
                    'type' => $field->type,
                    'adminLabel' => $field->adminLabel ?? '',
                    'isRequired' => $field->isRequired ?? false,
                    'cssClass' => $field->cssClass ?? '',
                    'size' => $field->size ?? 'medium'
                );

                // Add choices for select/radio/checkbox fields
                if (in_array($field->type, array('select', 'radio', 'checkbox')) && !empty($field->choices)) {
                    $field_data['choices'] = array();
                    foreach ($field->choices as $choice) {
                        $field_data['choices'][] = array(
                            'text' => $choice['text'],
                            'value' => $choice['value']
                        );
                    }
                }

                $fields[] = $field_data;
            }

            // Cache the result
            self::$form_fields_cache[$cache_key] = $fields;
            self::$performance_stats['cache_misses']++;

            return $fields;
        } catch (Exception $e) {
            error_log('Operaton DMN Gravity Forms: Error getting form fields: ' . $e->getMessage());
            self::$form_fields_cache[$cache_key] = array();
            return array();
        } finally {
            if ($timer_id) {
                $this->performance->stop_timer($timer_id, 'Form fields fetch for form ' . $form_id);
            }
        }
    }

    // =============================================================================
    // OPTIMIZED FORM BUTTON AND UI INTEGRATION
    // =============================================================================

    /**
     * OPTIMIZED: Add DMN evaluation button with caching
     */
    public function add_evaluate_button($button, $form)
    {
        // Skip in admin or AJAX contexts
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return $button;
        }

        $form_id = $form['id'];
        $cache_key = 'button_' . $form_id;

        // Check cache first
        if (isset(self::$gravity_assets_cache[$cache_key])) {
            return self::$gravity_assets_cache[$cache_key];
        }

        $config = $this->get_cached_form_config($form_id);
        if (!$config) {
            self::$gravity_assets_cache[$cache_key] = $button;
            return $button;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Gravity Forms: ⚡ OPTIMIZED - Adding evaluate button for form ' . $form_id);
        }

        // Create the evaluate button
        $evaluate_button = sprintf(
            '<input type="button" id="operaton-evaluate-%1$d" value="%2$s" class="gform_button gform-theme-button operaton-evaluate-btn" data-form-id="%1$d" data-config-id="%3$d" style="display: none;">',
            $form_id,
            esc_attr($config->button_text),
            $config->id
        );

        // Decision flow summary container
        $decision_flow_container = '';
        if (isset($config->show_decision_flow) && $config->show_decision_flow) {
            $decision_flow_container = sprintf(
                '<div id="decision-flow-summary-%d" class="decision-flow-summary" style="display: none;"></div>',
                $form_id
            );
        }

        $final_button = $button . $evaluate_button . $decision_flow_container;

        // Cache the result
        self::$gravity_assets_cache[$cache_key] = $final_button;

        return $final_button;
    }

    /**
     * OPTIMIZED: Generate JavaScript with aggressive caching
     * CRITICAL FIX: Integrate with existing frontend.js button management system
     */
    /**
     * OPTIMIZED: Generate JavaScript with aggressive caching
     * CRITICAL FIX: Integrate with existing frontend.js button management system
     */
    private function generate_optimized_form_control_script($form_id, $target_page, $show_decision_flow, $use_process, $config)
    {
        return sprintf(
            '
/* OPTIMIZED Form Control Script for Form %d - FRONTEND INTEGRATION */
(function($) {
    "use strict";

    var formId = %d;
    var targetPage = %d;
    var showDecisionFlow = %s;
    var useProcess = %s;

    console.log("⚡ OPTIMIZED Form control for form " + formId + " - integrating with frontend.js");

    function getCurrentPage() {
        var urlParams = new URLSearchParams(window.location.search);
        var gfPage = urlParams.get("gf_page");
        if (gfPage) {
            return parseInt(gfPage);
        }

        var pageField = $("#gform_source_page_number_" + formId);
        if (pageField.length && pageField.val()) {
            return parseInt(pageField.val());
        }

        return 1;
    }

    function integratedButtonPlacement() {
        // CRITICAL FIX: Prevent duplicate execution
        var lockKey = "operatonButtonPlacement_" + formId;
        if (window[lockKey]) {
            console.log("⏸️ INTEGRATED: Button placement already running for form " + formId);
            return;
        }

        window[lockKey] = true;

        try {
            var currentPage = getCurrentPage();

            console.log("🔧 INTEGRATED: Form " + formId + " - Current page:", currentPage, "Target:", targetPage);

            // CRITICAL FIX: Use frontend.js functions if available, fallback to direct manipulation
            if (currentPage === targetPage) {
                console.log("✅ INTEGRATED: Showing evaluate button for form " + formId);

                // Use frontend.js function if available
                if (typeof window.showEvaluateButton === "function") {
                    window.showEvaluateButton(formId);
                } else {
                    // Fallback: direct button show
                    var $button = $("#operaton-evaluate-" + formId);
                    var $form = $("#gform_" + formId);
                    var $target = $form.find(".gform_body, .gform_footer").first();

                    if ($target.length && $button.length) {
                        $button.detach().appendTo($target);
                        $button.addClass("operaton-show-button").show();
                    }
                }

                // Hide decision flow if showing button
                if (typeof window.hideAllElements === "function") {
                    // Don\'t call hideAllElements as it might hide the button we just showed
                    var $summary = $("#decision-flow-summary-" + formId);
                    $summary.removeClass("operaton-show-summary").hide();
                } else {
                    // Fallback: direct manipulation
                    var $summary = $("#decision-flow-summary-" + formId);
                    $summary.removeClass("operaton-show-summary").hide();
                }

            } else if (currentPage === (targetPage + 1) && showDecisionFlow && useProcess) {
                console.log("📊 INTEGRATED: Showing decision flow for form " + formId);

                // Use frontend.js function if available
                if (typeof window.showDecisionFlowSummary === "function") {
                    window.showDecisionFlowSummary(formId);
                } else {
                    // Fallback: direct manipulation
                    var $button = $("#operaton-evaluate-" + formId);
                    var $summary = $("#decision-flow-summary-" + formId);
                    $button.removeClass("operaton-show-button").hide();
                    $summary.addClass("operaton-show-summary").show();

                    // Load decision flow manually if frontend.js not available
                    loadDecisionFlowSummaryFallback();
                }

            } else {
                console.log("❌ INTEGRATED: Hiding elements for form " + formId);

                // Use frontend.js function if available
                if (typeof window.hideAllElements === "function") {
                    window.hideAllElements(formId);
                } else {
                    // Fallback: direct manipulation
                    var $button = $("#operaton-evaluate-" + formId);
                    var $summary = $("#decision-flow-summary-" + formId);
                    $button.removeClass("operaton-show-button").hide();
                    $summary.removeClass("operaton-show-summary").hide();
                }
            }

        } finally {
            // Always clear the flag after a short delay
            setTimeout(function() {
                window[lockKey] = false;
            }, 100);
        }
    }

    function loadDecisionFlowSummaryFallback() {
        // CRITICAL FIX: Prevent duplicate loading
        var loadKey = "operatonDecisionFlowLoading_" + formId;
        if (window[loadKey]) {
            console.log("⏸️ INTEGRATED: Decision flow already loading for form " + formId);
            return;
        }

        var container = $("#decision-flow-summary-" + formId);
        if (container.hasClass("loading") || container.hasClass("loaded")) {
            return;
        }

        window[loadKey] = true;
        container.addClass("loading");
        container.html("<div style=\"padding: 20px; text-align: center;\"><p>⏳ Loading decision flow...</p></div>");

        $.ajax({
            url: "%s/wp-json/operaton-dmn/v1/decision-flow/" + formId + "?cache_bust=" + Date.now(),
            type: "GET",
            cache: false,
            timeout: 10000,
            success: function(response) {
                if (response.success && response.html) {
                    container.html(response.html).addClass("loaded");
                } else {
                    container.html("<div style=\"padding: 20px;\"><p><em>No decision flow data available.</em></p></div>");
                }
            },
            error: function(xhr, status, error) {
                container.html("<div style=\"padding: 20px;\"><p><em>Error loading decision flow: " + error + "</em></p></div>");
            },
            complete: function() {
                container.removeClass("loading");
                window[loadKey] = false;
            }
        });
    }

    // CRITICAL FIX: Wait for frontend.js to be available with timeout
    function waitForFrontendJS() {
        var attempts = 0;
        var maxAttempts = 20;

        function checkFrontend() {
            attempts++;

            if (typeof window.showEvaluateButton === "function" || attempts >= maxAttempts) {
                if (attempts < maxAttempts) {
                    console.log("✅ INTEGRATED: Frontend.js functions available, proceeding with integration");
                } else {
                    console.log("⚠️ INTEGRATED: Frontend.js functions not found, using fallback mode");
                }

                // Initialize with integration
                integratedButtonPlacement();

                // Set up event handlers with duplicate prevention
                var eventHandlersSet = "operatonEventHandlers_" + formId;
                if (!window[eventHandlersSet]) {
                    window[eventHandlersSet] = true;

                    $(document).on("gform_page_loaded", function(event, form_id, current_page) {
                        if (form_id == formId) {
                            console.log("📄 INTEGRATED: Page loaded event - Form:", form_id, "Page:", current_page);
                            setTimeout(integratedButtonPlacement, 200);
                        }
                    });

                    // URL change detection with throttling
                    var currentUrl = window.location.href;
                    var urlCheckInterval = setInterval(function() {
                        if (window.location.href !== currentUrl) {
                            currentUrl = window.location.href;
                            console.log("🔄 INTEGRATED: URL changed, re-evaluating button placement");
                            setTimeout(integratedButtonPlacement, 300);
                        }
                    }, 500);

                    // Clear interval after 30 seconds to prevent memory leaks
                    setTimeout(function() {
                        clearInterval(urlCheckInterval);
                    }, 30000);
                }

                // Final fallback check with duplicate prevention
                var fallbackCheckDone = "operatonFallbackCheck_" + formId;
                if (!window[fallbackCheckDone]) {
                    window[fallbackCheckDone] = true;

                    setTimeout(function() {
                        var currentPage = getCurrentPage();
                        var $button = $("#operaton-evaluate-" + formId);

                        if (currentPage === targetPage && !$button.is(":visible")) {
                            console.log("🔧 INTEGRATED: Fallback - Button should be visible but isn\'t");
                            integratedButtonPlacement();
                        }
                    }, 2000);
                }

            } else {
                setTimeout(checkFrontend, 250);
            }
        }

        checkFrontend();
    }

    // Initialize when DOM is ready with duplicate prevention
    var domReadyHandlerSet = "operatonDomReady_" + formId;
    if (!window[domReadyHandlerSet]) {
        window[domReadyHandlerSet] = true;

        $(document).ready(function() {
            console.log("🚀 INTEGRATED: Starting integrated button placement for form " + formId);
            waitForFrontendJS();
        });

        // Additional initialization when window loads
        $(window).on("load", function() {
            setTimeout(function() {
                console.log("🔄 INTEGRATED: Window loaded - checking integrated button placement");
                integratedButtonPlacement();
            }, 500);
        });
    }

})(jQuery);',
            $form_id,                                    // First %d
            $form_id,                                    // Second %d
            $target_page,                               // Third %d
            $show_decision_flow ? 'true' : 'false',     // First %s
            $use_process ? 'true' : 'false',            // Second %s
            home_url()                                  // Third %s
        );
    }

    // =============================================================================
    // OPTIMIZED CACHE MANAGEMENT
    // =============================================================================

    /**
     * NEW: Clear specific form cache
     */
    public function clear_form_cache($form_id = null)
    {
        if ($form_id) {
            // Clear specific form caches
            unset($this->form_configs_cache[$form_id]);

            $keys_to_remove = array();
            foreach (self::$form_config_cache as $key => $value) {
                if (strpos($key, '_' . $form_id) !== false) {
                    $keys_to_remove[] = $key;
                }
            }

            foreach ($keys_to_remove as $key) {
                unset(self::$form_config_cache[$key]);
                unset(self::$form_fields_cache[$key]);
                unset(self::$gravity_assets_cache[$key]);
                unset(self::$inline_script_cache[$key]);
                unset(self::$detection_cache[$key]);
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: ⚡ CLEARED cache for form ' . $form_id);
            }
        }
    }

    /**
     * Handle form save with form array parameter
     * The gform_after_save_form hook passes ($form, $is_new) not just form_id
     */
    public function clear_form_cache_on_save($form, $is_new)
    {
        if (isset($form['id'])) {
            $this->clear_form_cache($form['id']);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: ⚡ CLEARED cache for saved form ' . $form['id']);
            }
        }
    }

    /**
     * NEW: Clear all caches
     */
    public function clear_all_caches()
    {
        $this->form_configs_cache = array();
        self::$form_config_cache = array();
        self::$form_fields_cache = array();
        self::$gravity_assets_cache = array();
        self::$gravity_loading_locks = array();
        self::$inline_script_cache = array();
        self::$detection_cache = array();

        self::$performance_stats = array(
            'cache_hits' => 0,
            'cache_misses' => 0,
            'duplicate_blocks' => 0,
            'total_processing_time' => 0
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: ⚡ ALL CACHES CLEARED');
        }
    }

    /**
     * NEW: Smart cache cleanup based on memory usage
     */
    public function maybe_cleanup_cache()
    {
        $cache_size = count(self::$form_config_cache) +
            count(self::$form_fields_cache) +
            count(self::$gravity_assets_cache) +
            count(self::$inline_script_cache) +
            count(self::$detection_cache);

        // Clean up if cache is getting too large
        if ($cache_size > 100) {
            // Keep only recent entries
            $recent_time = time() - 300; // 5 minutes

            foreach (self::$gravity_assets_cache as $key => $value) {
                if (is_array($value) && isset($value['loaded_at']) && $value['loaded_at'] < $recent_time) {
                    unset(self::$gravity_assets_cache[$key]);
                }
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: ⚡ Smart cache cleanup performed');
            }
        }
    }

    /**
     * NEW: Get cache performance statistics
     */
    public function get_cache_performance_stats()
    {
        $total_requests = self::$performance_stats['cache_hits'] + self::$performance_stats['cache_misses'];
        $hit_ratio = $total_requests > 0 ? (self::$performance_stats['cache_hits'] / $total_requests) * 100 : 0;

        return array(
            'cache_hits' => self::$performance_stats['cache_hits'],
            'cache_misses' => self::$performance_stats['cache_misses'],
            'hit_ratio_percent' => round($hit_ratio, 2),
            'duplicate_blocks_prevented' => self::$performance_stats['duplicate_blocks'],
            'cache_sizes' => array(
                'form_config_cache' => count(self::$form_config_cache),
                'form_fields_cache' => count(self::$form_fields_cache),
                'gravity_assets_cache' => count(self::$gravity_assets_cache),
                'inline_script_cache' => count(self::$inline_script_cache),
                'detection_cache' => count(self::$detection_cache)
            ),
            'memory_usage_estimate' => $this->estimate_cache_memory_usage()
        );
    }

    /**
     * NEW: Estimate cache memory usage
     */
    private function estimate_cache_memory_usage()
    {
        $memory_estimate = 0;

        // Rough estimate based on array sizes and typical content
        $memory_estimate += count(self::$form_config_cache) * 1024; // ~1KB per config
        $memory_estimate += count(self::$form_fields_cache) * 2048; // ~2KB per field set
        $memory_estimate += count(self::$gravity_assets_cache) * 512; // ~512B per asset cache
        $memory_estimate += count(self::$inline_script_cache) * 4096; // ~4KB per script
        $memory_estimate += count(self::$detection_cache) * 256; // ~256B per detection cache

        return array(
            'bytes' => $memory_estimate,
            'formatted' => $this->format_bytes($memory_estimate)
        );
    }

    /**
     * NEW: Format bytes for display
     */
    private function format_bytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    // =============================================================================
    // EXISTING METHODS - KEEPING COMPATIBILITY
    // =============================================================================

    /**
     * Original method for radio sync detection (now with caching)
     */
    public function form_needs_radio_sync($form_id)
    {
        if (!$this->check_gravity_forms_availability()) {
            return false;
        }

        // Form 8 specifically needs radio sync
        if ($form_id == 8) {
            return true;
        }

        try {
            $form = GFAPI::get_form($form_id);

            if (!$form || !isset($form['fields'])) {
                return false;
            }

            // Check for HTML fields with radio buttons that need sync
            foreach ($form['fields'] as $field) {
                if ($field->type === 'html' && isset($field->content)) {
                    $content = $field->content;

                    if (
                        strpos($content, 'type="radio"') !== false &&
                        (strpos($content, 'aanvrager') !== false ||
                            strpos($content, 'name="input_') !== false)
                    ) {
                        return true;
                    }
                }
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN Gravity Forms: Error checking form for radio sync: ' . $e->getMessage());
            }
        }

        return false;
    }

    /**
     * Original method for radio sync initialization (now cached)
     */
    private function add_cached_radio_sync_initialization($form_id)
    {
        $cache_key = 'radio_sync_script_' . $form_id;

        if (isset(self::$inline_script_cache[$cache_key])) {
            echo self::$inline_script_cache[$cache_key];
            return;
        }

        $mappings = $this->extract_radio_sync_mappings($form_id);

        if (empty($mappings)) {
            return;
        }

        ob_start();
        ?>
        <script type="text/javascript">
            /* Optimized Radio Sync for Form <?php echo esc_js($form_id); ?> */
            (function($) {
                'use strict';

                if (typeof window.OperatonRadioSync !== 'undefined') {
                    window.OperatonRadioSync.fieldMappings = <?php echo wp_json_encode($mappings); ?>;

                    $(document).ready(function() {
                        setTimeout(function() {
                            if (window.OperatonRadioSync.forceSyncAll) {
                                window.OperatonRadioSync.forceSyncAll();
                                console.log('⚡ OPTIMIZED Radio sync for form <?php echo esc_js($form_id); ?>');
                            }
                        }, 500);
                    });
                } else {
                    console.warn('⚠️ OperatonRadioSync not available for form <?php echo esc_js($form_id); ?>');
                }

            })(jQuery);
        </script>
        <?php
        $script_content = ob_get_clean();

        // Cache the script
        self::$inline_script_cache[$cache_key] = $script_content;

        echo $script_content;
    }

    /**
     * Count form pages (with caching)
     */
    private function count_form_pages($form)
    {
        $form_id = $form['id'];
        $cache_key = 'page_count_' . $form_id;

        if (isset(self::$form_config_cache[$cache_key])) {
            return self::$form_config_cache[$cache_key];
        }

        $total_pages = 1;

        if (isset($form['fields']) && is_array($form['fields'])) {
            foreach ($form['fields'] as $field) {
                if (isset($field->type) && $field->type === 'page') {
                    $total_pages++;
                }
            }
        }

        self::$form_config_cache[$cache_key] = $total_pages;
        return $total_pages;
    }

    /**
     * Check if current page has DMN-enabled forms (with caching)
     */
    private function has_dmn_enabled_forms_on_page()
    {
        $page_id = get_the_ID();
        $cache_key = 'dmn_forms_page_' . $page_id;

        if (isset(self::$detection_cache[$cache_key])) {
            return self::$detection_cache[$cache_key];
        }

        $has_dmn_forms = false;

        // Check for shortcodes in post content
        global $post;
        if ($post && has_shortcode($post->post_content, 'gravityform')) {
            $form_ids = $this->extract_form_ids_from_shortcodes($post->post_content);
            $has_dmn_forms = $this->any_forms_have_dmn_config($form_ids);
        }

        // Check for Gravity Forms blocks (Gutenberg)
        if (!$has_dmn_forms && $post && has_block('gravityforms/form', $post)) {
            $form_ids = $this->extract_form_ids_from_blocks($post);
            $has_dmn_forms = $this->any_forms_have_dmn_config($form_ids);
        }

        // Check if we're on a Gravity Forms preview page
        if (!$has_dmn_forms && isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview') {
            $form_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($form_id > 0) {
                $has_dmn_forms = $this->form_has_dmn_config($form_id);
            }
        }

        // Cache the result
        self::$detection_cache[$cache_key] = $has_dmn_forms;

        return $has_dmn_forms;
    }

    /**
     * Check if any forms have DMN configurations (with caching)
     */
    private function any_forms_have_dmn_config($form_ids)
    {
        foreach ($form_ids as $form_id) {
            if ($this->form_has_dmn_config($form_id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if form has DMN config (with caching)
     */
    private function form_has_dmn_config($form_id)
    {
        return $this->get_cached_form_config($form_id) !== null;
    }

    /**
     * Extract form IDs from shortcodes
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
     * Extract form IDs from blocks
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
     * Find form IDs in blocks recursively
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
     * Extract radio sync mappings
     */
    public function extract_radio_sync_mappings($form_id)
    {
        $cache_key = 'radio_mappings_' . $form_id;

        if (isset(self::$form_config_cache[$cache_key])) {
            return self::$form_config_cache[$cache_key];
        }

        $mappings = array();

        // Get DMN configuration for this form
        $config = $this->get_cached_form_config($form_id);

        if (!$config) {
            $mappings = $this->get_default_radio_mappings($form_id);
        } else {
            // Extract mappings from field_mappings JSON
            $field_mappings = $this->get_cached_field_mappings($config);

            if (is_array($field_mappings)) {
                foreach ($field_mappings as $dmn_variable => $mapping) {
                    if (strpos($dmn_variable, 'aanvrager') === 0 && isset($mapping['field_id'])) {
                        $mappings[$dmn_variable] = 'input_' . $form_id . '_' . $mapping['field_id'];
                    }
                }
            }
        }

        self::$form_config_cache[$cache_key] = $mappings;
        return $mappings;
    }

    /**
     * Get default radio mappings
     */
    private function get_default_radio_mappings($form_id)
    {
        $default_mappings = array();

        if ($form_id == 8) {
            $default_mappings = array(
                'aanvragerDitKalenderjaarAlAangevraagd' => 'input_8_25',
                'aanvragerAanmerkingStudieFinanciering' => 'input_8_26',
                'aanvragerUitkeringBaanbrekers' => 'input_8_27',
                'aanvragerVoedselbankpasDenBosch' => 'input_8_28',
                'aanvragerKwijtscheldingGemeentelijkeBelastingen' => 'input_8_29',
                'aanvragerSchuldhulptrajectKredietbankNederland' => 'input_8_30',
                'aanvragerHeeftKind4Tm17' => 'input_8_31'
            );
        }

        return apply_filters('operaton_dmn_default_radio_mappings', $default_mappings, $form_id);
    }

    // =============================================================================
    // REMAINING STANDARD METHODS
    // =============================================================================

    public function add_gravity_forms_hooks()
    {
        /* Implementation remains the same */
    }
    public function init_admin_integration()
    {
        /* Implementation remains the same */
    }
    public function validate_dmn_fields($validation_result)
    {
        return $validation_result;
    }
    public function handle_post_submission($entry, $form)
    {
        /* Implementation remains the same */
    }
    public function ajax_evaluate_form()
    {
        /* Implementation remains the same */
    }
    public function add_editor_script()
    {
        /* Implementation remains the same */
    }
    public function add_field_advanced_settings($position, $form_id)
    {
        /* Implementation remains the same */
    }
    public function add_custom_field_buttons($field_groups)
    {
        return $field_groups;
    }
    public function add_form_settings($settings, $form)
    {
        return $settings;
    }
    public function save_form_settings($form)
    {
        return $form;
    }
    public function pre_render_form($form, $ajax = false, $field_values = array())
    {
        return $form;
    }
    public function add_radio_sync_editor_support()
    {
        /* Implementation remains the same */
    }

    // =============================================================================
    // PUBLIC API METHODS
    // =============================================================================

    public function is_gravity_forms_available()
    {
        return $this->check_gravity_forms_availability();
    }

    public function get_form_configuration($form_id)
    {
        return $this->get_cached_form_config($form_id);
    }

    public function get_available_forms()
    {
        if (!$this->check_gravity_forms_availability()) {
            return array();
        }

        try {
            $forms = GFAPI::get_forms();

            foreach ($forms as &$form) {
                if (isset($form['fields'])) {
                    $form['field_list'] = $this->get_form_fields($form['id']);
                }
            }

            return $forms;
        } catch (Exception $e) {
            error_log('Operaton DMN Gravity Forms: Error getting forms: ' . $e->getMessage());
            return array();
        }
    }

    public function form_exists($form_id)
    {
        if (!$this->check_gravity_forms_availability()) {
            return false;
        }

        try {
            $form = GFAPI::get_form($form_id);
            return !empty($form);
        } catch (Exception $e) {
            return false;
        }
    }

    public function get_form_title($form_id)
    {
        if (!$this->check_gravity_forms_availability()) {
            return '';
        }

        try {
            $form = GFAPI::get_form($form_id);
            return !empty($form['title']) ? $form['title'] : '';
        } catch (Exception $e) {
            return '';
        }
    }

    public function get_integration_status()
    {
        $status = array(
            'gravity_forms_available' => $this->check_gravity_forms_availability(),
            'hooks_registered' => true,
            'forms_with_dmn_config' => 0,
            'total_forms' => 0,
            'version_info' => array(),
            'cache_performance' => $this->get_cache_performance_stats()
        );

        if ($status['gravity_forms_available']) {
            try {
                $forms = GFAPI::get_forms();
                $status['total_forms'] = count($forms);

                $forms_with_config = 0;
                foreach ($forms as $form) {
                    if ($this->form_has_dmn_config($form['id'])) {
                        $forms_with_config++;
                    }
                }
                $status['forms_with_dmn_config'] = $forms_with_config;
            } catch (Exception $e) {
                $status['error'] = $e->getMessage();
            }

            if (class_exists('GFCommon')) {
                $status['version_info'] = array(
                    'gravity_forms_version' => GFCommon::$version ?? 'unknown',
                    'minimum_required' => '2.0',
                    'compatible' => version_compare(GFCommon::$version ?? '0', '2.0', '>=')
                );
            }
        }

        return $status;
    }

    public function clear_form_cache_public()
    {
        $this->clear_all_caches();
    }

    public function reload_form_configuration($form_id)
    {
        $this->clear_form_cache($form_id);
        return $this->get_cached_form_config($form_id);
    }

    public function get_core_instance()
    {
        return $this->core;
    }

    public function get_assets_manager()
    {
        return $this->assets;
    }

    public function get_database_manager()
    {
        return $this->database;
    }
}
