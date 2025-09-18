<?php

/**
 * Simplified Assets Manager for Operaton DMN Plugin
 *
 * SIMPLIFIED APPROACH:
 * 1. Trust WordPress's built-in asset management
 * 2. Single detection run per request with proper caching
 * 3. Minimal state management
 * 4. Clear separation of concerns
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
    // ADD these new static properties for Issue 4 fix:
    private static $global_asset_state = array();
    private static $context_locks = array();
    private static $asset_load_attempts = 0;

    // ADD these new static properties for Issue 3 fix:
    private static $detection_lock = false;
    private static $detection_result = null;
    private static $detection_request_id = null;

    /**
     * Performance monitor instance
     */
    private $performance;

    private static $localized_configs = array();
    private static $localization_attempts = array();

    /**
     * Simple state tracking - one source of truth
     */
    private static $detection_cache = array();
    private static $cache_timestamp = null;
    private static $detection_complete = false;

    /**
     * Basic properties
     */
    private $plugin_url;
    private $version;
    private $gravity_forms_manager = null;

    /**
     * Constructor
     */
    public function __construct($plugin_url, $version)
    {
        $this->plugin_url = trailingslashit($plugin_url);
        $this->version = $version;

        // Initialize cache timestamp properly
        if (self::$cache_timestamp === null) {
            self::$cache_timestamp = time();
        }

        // Get performance monitor if available
        if (class_exists('Operaton_DMN_Performance_Monitor')) {
            $this->performance = Operaton_DMN_Performance_Monitor::get_instance();
        }

        $this->init_hooks();
    }

    public function set_gravity_forms_manager($gravity_forms_manager)
    {
        $this->gravity_forms_manager = $gravity_forms_manager;
    }

    /**
     * Initialize hooks with clean separation
     */
    private function init_hooks()
    {
        // Frontend assets
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_frontend_assets'), 10);

        // Admin assets
        add_action('admin_enqueue_scripts', array($this, 'maybe_enqueue_admin_assets'), 10);

        // Compatibility check
        add_action('wp_head', array($this, 'output_compatibility_check'), 1);

        // Performance logging
        if ($this->performance) {
            add_action('shutdown', array($this, 'log_performance'), 999);
        }
    }

    // =============================================================================
    // SIMPLIFIED DETECTION LOGIC
    // =============================================================================



    /**
     * Check if current admin page is Gravity Forms related
     */
    private function is_gravity_forms_admin_page()
    {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'toplevel_page_gf_') === 0;
    }

    /**
     * Check if current page content has Gravity Forms
     */
    private function has_gravity_forms_in_content()
    {
        global $post;

        if (!$post) {
            return false;
        }

        // Cache by post ID
        if (isset(self::$detection_cache['content_' . $post->ID])) {
            return self::$detection_cache['content_' . $post->ID];
        }

        $has_forms = has_shortcode($post->post_content, 'gravityform') ||
            has_block('gravityforms/form', $post);

        self::$detection_cache['content_' . $post->ID] = $has_forms;

        return $has_forms;
    }

    /**
     * Check URL for Gravity Forms indicators
     */
    private function has_gravity_forms_url_indicators()
    {
        return (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview') ||
            isset($_GET['gf_token']) ||
            (strpos($_SERVER['REQUEST_URI'] ?? '', '/gravityforms') !== false);
    }

    // =============================================================================
    // ASSET LOADING - TRUST WORDPRESS
    // =============================================================================

    /**
     * ISSUE 4 FIX: Enhanced maybe_enqueue_frontend_assets with global state tracking
     */
    public function maybe_enqueue_frontend_assets()
    {
        // ISSUE 4 FIX: Initialize global state
        self::init_global_asset_state();

        $context = is_admin() ? 'admin' : 'frontend';
        $context_key = $context . '_attempted';

        // ISSUE 4 FIX: Check global state across all contexts
        if (self::$global_asset_state[$context_key])
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 4 FIX: Assets already attempted in ' . $context . ' context for this request');
            }
            return;
        }

        // ISSUE 4 FIX: Prevent concurrent loading from different contexts
        if (isset(self::$context_locks[$context]))
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 4 FIX: Context lock active for ' . $context . ', waiting...');
            }
            return;
        }

        // ISSUE 4 FIX: Set context lock
        self::$context_locks[$context] = true;
        self::$asset_load_attempts++;

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('ISSUE 4 FIX: Processing assets for ' . $context . ' context - Attempt #' . self::$asset_load_attempts);
        }

        try
        {
            // Skip admin context if not relevant
            if (is_admin() && strpos($_SERVER['REQUEST_URI'] ?? '', 'operaton-dmn') === false)
            {
                self::$global_asset_state[$context_key] = true;
                return;
            }

            // Skip if detection says not to load
            if (!self::should_load_frontend_assets())
            {
                self::$global_asset_state[$context_key] = true;
                return;
            }

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 4 FIX: Enqueuing assets for ' . $context . ' context - Request: ' . self::$global_asset_state['request_signature']);
            }

            $this->enqueue_frontend_assets();

            // ISSUE 4 FIX: Mark as completed
            self::$global_asset_state[$context_key] = true;
            self::$global_asset_state[str_replace('_attempted', '_completed', $context_key)] = true;
        }
        finally
        {
            // ISSUE 4 FIX: Always release context lock
            unset(self::$context_locks[$context]);
        }
    }

    /**
     * ISSUE 4 FIX: Enhanced enqueue_frontend_assets with global completion tracking
     */
    public function enqueue_frontend_assets()
    {
        // ISSUE 4 FIX: Initialize global state
        self::init_global_asset_state();

        // ISSUE 4 FIX: Check if already completed globally
        if (self::$global_asset_state['frontend_completed'])
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 4 FIX: Frontend assets already completed globally');
            }
            return;
        }

        // Trust WordPress - if already enqueued, it won't duplicate
        if (wp_script_is('operaton-dmn-frontend', 'done'))
        {
            self::$global_asset_state['frontend_completed'] = true;
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 4 FIX: Frontend assets already done in WordPress');
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('ISSUE 4 FIX: Actually enqueuing frontend assets - Request: ' . self::$global_asset_state['request_signature']);
        }

        $timer_id = $this->performance ?
            $this->performance->start_timer('frontend_assets') : null;

        // Register and enqueue main script
        wp_enqueue_script(
            'operaton-dmn-frontend',
            $this->plugin_url . 'assets/js/frontend.js',
            array('jquery'),
            $this->version,
            true
        );

        // Register and enqueue Gravity Forms integration
        wp_enqueue_script(
            'operaton-dmn-gravity-integration',
            $this->plugin_url . 'assets/js/gravity-forms.js',
            array('jquery', 'operaton-dmn-frontend'),
            $this->version,
            true
        );

        // Register and enqueue decision flow if needed
        if ($this->should_load_decision_flow_assets())
        {
            wp_enqueue_script(
                'operaton-dmn-decision-flow',
                $this->plugin_url . 'assets/js/decision-flow.js',
                array('jquery', 'operaton-dmn-frontend'),
                $this->version,
                true
            );
        }

        // Enqueue styles
        wp_enqueue_style(
            'operaton-dmn-frontend',
            $this->plugin_url . 'assets/css/frontend.css',
            array(),
            $this->version
        );

        // Localize script once
        $this->localize_frontend_script();

        if ($timer_id)
        {
            $this->performance->stop_timer($timer_id, 'Frontend assets loaded');
        }

        // ISSUE 4 FIX: Mark as globally completed
        self::$global_asset_state['frontend_completed'] = true;
        $this->log_debug('ISSUE 4 FIX: Frontend assets enqueued and marked complete');
    }

    /**
     * Localize frontend script with essential data only
     */
    private function localize_frontend_script()
    {
        // Check if already localized
        if (wp_scripts()->get_data('operaton-dmn-frontend', 'data')) {
            return;
        }

        wp_localize_script('operaton-dmn-frontend', 'operaton_ajax', array(
            'url' => rest_url('operaton-dmn/v1/evaluate'),
            'nonce' => wp_create_nonce('wp_rest'),
            'test_nonce' => wp_create_nonce('operaton_test_endpoint'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'strings' => array(
                'evaluating' => __('Evaluating...', 'operaton-dmn'),
                'error' => __('Evaluation failed', 'operaton-dmn'),
                'success' => __('Evaluation completed', 'operaton-dmn'),
                'loading' => __('Loading...', 'operaton-dmn'),
                'validation_failed' => __('Please fill in all required fields', 'operaton-dmn'),
                'connection_error' => __('Connection error. Please try again.', 'operaton-dmn')
            )
        ));
    }

    // =============================================================================
    // GRAVITY FORMS INTEGRATION - SIMPLIFIED
    // =============================================================================

    /**
     * ISSUE 4 FIX: Enhanced enqueue_gravity_form_assets with global form tracking
     */
    public function enqueue_gravity_form_assets($form, $config)
    {
        $form_id = $form['id'];

        // ISSUE 4 FIX: Initialize global state
        self::init_global_asset_state();

        // ISSUE 4 FIX: Check global form processing state
        if (in_array($form_id, self::$global_asset_state['gravity_forms_processed']))
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 4 FIX: Form ' . $form_id . ' already processed globally across all contexts');
            }
            return;
        }

        // ISSUE 4 FIX: Prevent concurrent processing of same form
        if (isset(self::$context_locks['form_' . $form_id]))
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 4 FIX: Form ' . $form_id . ' processing locked, waiting...');
            }
            return;
        }

        // ISSUE 4 FIX: Set form processing lock
        self::$context_locks['form_' . $form_id] = true;

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('ISSUE 4 FIX: Processing form assets for form ' . $form_id . ' - Global first time - Request: ' . self::$global_asset_state['request_signature']);
        }

        try
        {
            // Ensure frontend assets are loaded first
            $this->enqueue_frontend_assets();

            $handle = 'operaton_config_form_' . $form_id;

            // Check if this form's config is already localized
            if (wp_scripts()->get_data('operaton-dmn-gravity-integration', $handle))
            {
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('ISSUE 4 FIX: Form ' . $form_id . ' config already localized in WordPress');
                }
                self::$global_asset_state['gravity_forms_processed'][] = $form_id;
                return;
            }

            // Get fresh config from database
            $fresh_config = $this->get_fresh_form_config($form_id, $config);

            // Localize form-specific configuration
            $this->localize_form_config($form_id, $fresh_config, $handle);

            // Enqueue radio sync if needed
            if ($this->form_needs_radio_sync($form_id))
            {
                $this->enqueue_radio_sync_assets($form_id);
            }

            // ISSUE 4 FIX: Mark form as globally processed
            self::$global_asset_state['gravity_forms_processed'][] = $form_id;

            $this->log_debug('ISSUE 4 FIX: Gravity form assets enqueued and marked complete for form: ' . $form_id);
        }
        finally
        {
            // ISSUE 4 FIX: Always release form lock
            unset(self::$context_locks['form_' . $form_id]);
        }
    }

    /**
     * ISSUE 3 FIX: Method to reset detection state (for testing/debugging)
     */
    public static function reset_detection_state()
    {
        self::$detection_lock = false;
        self::$detection_result = null;
        self::$detection_request_id = null;

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('ISSUE 3 FIX: Detection state reset');
        }
    }

    /**
     * ISSUE 3 FIX: Get detection status for debugging
     */
    public static function get_detection_status()
    {
        return array(
            'detection_lock' => self::$detection_lock,
            'detection_result' => self::$detection_result,
            'detection_request_id' => self::$detection_request_id,
            'current_request_id' => self::generate_request_id()
        );
    }

    /**
     * ISSUE 3 FIX: Generate a unique request identifier
     */
    private static function generate_request_id()
    {
        static $request_id = null;

        if ($request_id === null)
        {
            // Create unique ID based on request characteristics
            $request_id = md5(
                ($_SERVER['REQUEST_URI'] ?? '') .
                    ($_SERVER['HTTP_USER_AGENT'] ?? '') .
                    ($_SERVER['REMOTE_ADDR'] ?? '') .
                    (microtime(true)) .
                    (is_admin() ? 'admin' : 'frontend')
            );
        }

        return $request_id;
    }

    /**
     * Get fresh configuration from database
     */
    private function get_fresh_form_config($form_id, $fallback_config)
    {
        if (!$this->gravity_forms_manager) {
            return $fallback_config;
        }

        $database = $this->gravity_forms_manager->get_database_instance();
        if (!$database) {
            return $fallback_config;
        }

        $fresh_config = $database->get_config_by_form_id($form_id, false);
        return $fresh_config ?: $fallback_config;
    }

    /**
     * Localize form configuration
     */
    private function localize_form_config($form_id, $config, $handle)
    {
        // STEP 1 FIX: Enhanced duplicate prevention with multiple checks
        $config_key = 'form_' . $form_id . '_' . ($config->id ?? 0);

        // Check 1: Our internal tracking (most reliable)
        if (isset(self::$localized_configs[$config_key]))
        {
            $this->log_debug('STEP 1 FIX: Configuration already localized (internal check) - Form: ' . $form_id);
            return;
        }

        // Check 2: Enhanced WordPress script data check
        $all_script_data = wp_scripts()->get_data('operaton-dmn-gravity-integration', 'data');
        if ($all_script_data && strpos($all_script_data, '"form_id":' . $form_id) !== false)
        {
            $this->log_debug('STEP 1 FIX: Configuration already localized (WP script check) - Form: ' . $form_id);
            self::$localized_configs[$config_key] = true;
            return;
        }

        // Check 3: Prevent rapid-fire attempts
        if (isset(self::$localization_attempts[$form_id]))
        {
            $last_attempt = self::$localization_attempts[$form_id];
            if ((time() - $last_attempt) < 2)
            {
                $this->log_debug('STEP 1 FIX: Preventing rapid localization attempt - Form: ' . $form_id);
                return;
            }
        }

        // Mark attempt
        self::$localization_attempts[$form_id] = time();

        // Validate configuration before localizing
        if (empty($config->dmn_endpoint))
        {
            $this->log_debug('STEP 1 FIX: Skipping localization - No DMN endpoint for form: ' . $form_id);
            return;
        }

        // Your existing field mapping logic (keep unchanged)
        $field_mappings = $this->safe_json_decode($config->field_mappings ?? '{}');
        $result_mappings = $this->safe_json_decode($config->result_mappings ?? '{}');

        // Your existing wp_localize_script call (keep unchanged)
        wp_localize_script('operaton-dmn-gravity-integration', $handle, array(
            'config_id' => $config->id ?? 0,
            'form_id' => $form_id,
            'button_text' => $config->button_text ?? 'Evaluate',
            'field_mappings' => $field_mappings,
            'result_mappings' => $result_mappings,
            'evaluation_step' => $config->evaluation_step ?? 'auto',
            'use_process' => $config->use_process ?? false,
            'show_decision_flow' => $config->show_decision_flow ?? false,
            'endpoint' => $config->dmn_endpoint ?? '',
            'decision_key' => $config->decision_key ?? '',
            'process_key' => $config->process_key ?? '',
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));

        // Mark as successfully localized in our tracking
        self::$localized_configs[$config_key] = array(
            'timestamp' => time(),
            'handle' => $handle,
            'form_id' => $form_id,
            'config_id' => $config->id ?? 0
        );

        // Enhanced debug log
        $this->log_debug('STEP 1 FIX: Configuration successfully localized - Form: ' . $form_id .
            ' | Handle: ' . $handle . ' | Endpoint: ' . ($config->dmn_endpoint ?? 'NONE'));
    }

    /**
     * Safe JSON decode
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
    // ADMIN ASSETS
    // =============================================================================

    /**
     * Maybe enqueue admin assets
     */
    public function maybe_enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'operaton-dmn') === false) {
            return;
        }

        $this->enqueue_admin_assets();
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets()
    {
        wp_enqueue_style(
            'operaton-dmn-admin',
            $this->plugin_url . 'assets/css/admin.css',
            array(),
            $this->version
        );

        wp_enqueue_script(
            'operaton-dmn-admin',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );

        // NEW: Enqueue API testing module
        wp_enqueue_script(
            'operaton-dmn-api-test',
            $this->plugin_url . 'assets/js/api-test.js',
            array('jquery', 'operaton-dmn-admin'),
            $this->version,
            true
        );

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

        $this->log_debug('Admin assets enqueued');
    }

    // =============================================================================
    // DECISION FLOW ASSETS
    // =============================================================================

    /**
     * Check if decision flow assets should be loaded
     */
    public function should_load_decision_flow_assets()
    {
        global $post;

        if (!$post || !$this->gravity_forms_manager) {
            return false;
        }

        // Only load if we're loading frontend assets
        if (!self::should_load_frontend_assets()) {
            return false;
        }

        // Extract form IDs from page content
        $form_ids = $this->get_page_form_ids($post);

        // Check if any forms have decision flow enabled
        foreach ($form_ids as $form_id) {
            $config = $this->gravity_forms_manager->get_form_configuration($form_id);
            if (
                $config &&
                isset($config->show_decision_flow) && $config->show_decision_flow &&
                isset($config->use_process) && $config->use_process
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get form IDs from page content
     */
    private function get_page_form_ids($post)
    {
        $form_ids = array();

        // Extract from shortcodes
        if (has_shortcode($post->post_content, 'gravityform')) {
            preg_match_all('/\[gravityform[^\]]*id=["\'](\d+)["\']/', $post->post_content, $matches);
            if (!empty($matches[1])) {
                $form_ids = array_merge($form_ids, array_map('intval', $matches[1]));
            }
        }

        // Extract from blocks
        if (has_block('gravityforms/form', $post)) {
            $blocks = parse_blocks($post->post_content);
            $form_ids = array_merge($form_ids, $this->extract_form_ids_from_blocks($blocks));
        }

        return array_unique($form_ids);
    }

    /**
     * Extract form IDs from Gutenberg blocks
     */
    private function extract_form_ids_from_blocks($blocks)
    {
        $form_ids = array();

        foreach ($blocks as $block) {
            if ($block['blockName'] === 'gravityforms/form' && isset($block['attrs']['formId'])) {
                $form_ids[] = intval($block['attrs']['formId']);
            }

            if (!empty($block['innerBlocks'])) {
                $inner_ids = $this->extract_form_ids_from_blocks($block['innerBlocks']);
                $form_ids = array_merge($form_ids, $inner_ids);
            }
        }

        return $form_ids;
    }

    // =============================================================================
    // RADIO SYNC ASSETS
    // =============================================================================

    /**
     * Enqueue radio sync assets if needed
     */
    public function enqueue_radio_sync_assets($form_id)
    {
        if (!$this->form_needs_radio_sync($form_id)) {
            return;
        }

        wp_enqueue_script(
            'operaton-dmn-radio-sync',
            $this->plugin_url . 'assets/js/radio-sync.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_enqueue_style(
            'operaton-dmn-radio-sync',
            $this->plugin_url . 'assets/css/radio-sync.css',
            array(),
            $this->version
        );

        wp_localize_script('operaton-dmn-radio-sync', 'operaton_radio_sync', array(
            'form_id' => $form_id,
            'field_mappings' => $this->get_radio_sync_mappings($form_id),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));

        $this->log_debug('Radio sync assets enqueued for form: ' . $form_id);
    }

    /**
     * Check if form needs radio synchronization
     */
    private function form_needs_radio_sync($form_id)
    {
        // Form 8 specifically needs radio sync
        if ($form_id == 8) {
            return true;
        }

        // Check if form has HTML fields with radio buttons
        if ($this->gravity_forms_manager && class_exists('GFAPI')) {
            try {
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
            } catch (Exception $e) {
                $this->log_debug('Error checking form for radio sync: ' . $e->getMessage());
            }
        }

        return false;
    }

    /**
     * Get radio sync field mappings
     */
    private function get_radio_sync_mappings($form_id)
    {
        if ($form_id == 8) {
            return array(
                'aanvragerDitKalenderjaarAlAangevraagd' => 'input_8_25',
                'aanvragerAanmerkingStudieFinanciering' => 'input_8_26',
                'aanvragerUitkeringBaanbrekers' => 'input_8_27',
                'aanvragerVoedselbankpasDenBosch' => 'input_8_28',
                'aanvragerKwijtscheldingGemeentelijkeBelastingen' => 'input_8_29',
                'aanvragerSchuldhulptrajectKredietbankNederland' => 'input_8_30',
                'aanvragerHeeftKind4Tm17' => 'input_8_31'
            );
        }

        return array();
    }

    // =============================================================================
    // COMPATIBILITY AND UTILITIES
    // =============================================================================

    /**
     * ISSUE 4 FIX: Global asset state management across all contexts
     */
    private static function init_global_asset_state()
    {
        if (empty(self::$global_asset_state))
        {
            self::$global_asset_state = array(
                'frontend_attempted' => false,
                'frontend_completed' => false,
                'admin_attempted' => false,
                'admin_completed' => false,
                'gravity_forms_processed' => array(),
                'request_signature' => self::get_request_signature(),
                'init_time' => microtime(true)
            );
        }
    }

    /**
     * ISSUE 4 FIX: Generate consistent request signature across contexts
     */
    private static function get_request_signature()
    {
        static $signature = null;

        if ($signature === null)
        {
            $signature = md5(
                ($_SERVER['REQUEST_URI'] ?? '') . '|' .
                    ($_SERVER['HTTP_HOST'] ?? '') . '|' .
                    (defined('DOING_AJAX') && DOING_AJAX ? 'ajax' : 'normal') . '|' .
                    (is_admin() ? 'admin' : 'frontend') . '|' .
                    (wp_doing_cron() ? 'cron' : 'web')
            );
        }

        return $signature;
    }

    /**
     * Output compatibility check script
     */
    public function output_compatibility_check()
    {
        if (is_admin() || !self::should_load_frontend_assets()) {
            return;
        }

        ?>
        <script type="text/javascript">
            (function() {
                window.operatonCompatibilityInfo = {
                    jqueryAvailable: typeof jQuery !== 'undefined',
                    jqueryVersion: typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'none',
                    quirksMode: document.compatMode === "BackCompat",
                    doctype: document.doctype ? document.doctype.name : 'missing',
                    timestamp: Date.now()
                };

                <?php if (defined('WP_DEBUG') && WP_DEBUG) : ?>
                    console.log('Operaton DMN Compatibility:', window.operatonCompatibilityInfo);
                <?php endif; ?>
            })();
        </script>
        <?php
    }

    /**
     * Add inline styles for theming
     */
    public function add_inline_styles($form_id = null, $styles = array())
    {
        $css = '';

        if (!empty($styles['theme'])) {
            $css .= ':root {';
            foreach ($styles['theme'] as $property => $value) {
                $css .= '--operaton-' . esc_attr($property) . ': ' . esc_attr($value) . ';';
            }
            $css .= '}';
        }

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

    // =============================================================================
    // CACHE AND STATE MANAGEMENT
    // =============================================================================

    /**
     * ISSUE 4 FIX: Enhanced clear_form_cache with global state reset
     */
    public function clear_form_cache($form_id = null)
    {
        // Your existing cache clearing logic...
        if ($form_id)
        {
            unset(self::$detection_cache['content_' . $form_id]);
        }
        else
        {
            self::$detection_cache = array();
            self::$detection_complete = false;
            self::$cache_timestamp = time();
        }

        // ISSUE 3 FIX: Reset detection state when clearing cache
        if (!$form_id)
        {
            self::reset_detection_state();
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 3 FIX: Detection state reset during cache clear');
            }
        }

        // ISSUE 4 FIX: Reset global asset state when clearing cache
        if (!$form_id)
        {
            // Full cache clear - reset everything
            self::$global_asset_state = array();
            self::$context_locks = array();
            self::$asset_load_attempts = 0;
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 4 FIX: Global asset state reset during full cache clear');
            }
        }
        else
        {
            // Form-specific clear - remove from processed forms
            if (isset(self::$global_asset_state['gravity_forms_processed']))
            {
                self::$global_asset_state['gravity_forms_processed'] = array_filter(
                    self::$global_asset_state['gravity_forms_processed'],
                    function ($processed_form_id) use ($form_id)
                    {
                        return $processed_form_id != $form_id;
                    }
                );
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('ISSUE 4 FIX: Removed form ' . $form_id . ' from global processed forms');
                }
            }
        }

        // Your existing Step 1 cache clearing...
        if ($form_id)
        {
            foreach (self::$localized_configs as $key => $data)
            {
                if (strpos($key, 'form_' . $form_id . '_') === 0)
                {
                    unset(self::$localized_configs[$key]);
                }
            }
            unset(self::$localization_attempts[$form_id]);
        }
        else
        {
            self::$localized_configs = array();
            self::$localization_attempts = array();
        }

        $this->log_debug('ISSUE 4 FIX: Form cache cleared' . ($form_id ? ' for form: ' . $form_id : ' (all)'));
    }

    /**
     * Reset all states (for testing)
     */
    public static function reset_all_states()
    {
        self::$detection_cache = array();
        self::$cache_timestamp = time();
        self::$detection_complete = false;
    }

    // =============================================================================
    // STATUS AND DEBUGGING
    // =============================================================================

    /**
     * ISSUE 4 FIX: Get comprehensive asset loading status for debugging
     */
    public static function get_asset_loading_status()
    {
        self::init_global_asset_state();

        return array(
            'global_asset_state' => self::$global_asset_state,
            'context_locks' => self::$context_locks,
            'asset_load_attempts' => self::$asset_load_attempts,
            'current_context' => is_admin() ? 'admin' : 'frontend',
            'request_signature' => self::get_request_signature(),
            'wordpress_script_status' => array(
                'frontend_registered' => wp_script_is('operaton-dmn-frontend', 'registered'),
                'frontend_enqueued' => wp_script_is('operaton-dmn-frontend', 'enqueued'),
                'frontend_done' => wp_script_is('operaton-dmn-frontend', 'done'),
                'gravity_registered' => wp_script_is('operaton-dmn-gravity-integration', 'registered'),
                'gravity_enqueued' => wp_script_is('operaton-dmn-gravity-integration', 'enqueued'),
            )
        );
    }

    /**
     * ISSUE 4 FIX: Force reset all asset loading states (for emergency/testing)
     */
    public static function emergency_reset_all_asset_states()
    {
        self::$global_asset_state = array();
        self::$context_locks = array();
        self::$asset_load_attempts = 0;

        // Also reset Issue 3 states
        self::reset_detection_state();

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('ISSUE 4 FIX: EMERGENCY - All asset states reset');
        }
    }

    /**
     * Get current status for debugging
     */
    public function get_status()
    {
        return array(
            'detection_complete' => self::$detection_complete,
            'cache_age' => self::$cache_timestamp ? time() - self::$cache_timestamp : 0,
            'cache_entries' => count(self::$detection_cache),
            'should_load' => self::$detection_cache['should_load'] ?? null,
            'wordpress_states' => array(
                'frontend_registered' => wp_script_is('operaton-dmn-frontend', 'registered'),
                'frontend_enqueued' => wp_script_is('operaton-dmn-frontend', 'enqueued'),
                'frontend_done' => wp_script_is('operaton-dmn-frontend', 'done'),
                'jquery_available' => wp_script_is('jquery', 'done') || wp_script_is('jquery', 'enqueued')
            )
        );
    }

    /**
     * Force enqueue specific assets (for manual loading)
     */
    public function force_enqueue($asset_group)
    {
        switch ($asset_group) {
            case 'frontend':
                $this->enqueue_frontend_assets();
                break;
            case 'admin':
                $this->enqueue_admin_assets();
                break;
            case 'decision_flow':
                wp_enqueue_script(
                    'operaton-dmn-decision-flow',
                    $this->plugin_url . 'assets/js/decision-flow.js',
                    array('jquery', 'operaton-dmn-frontend'),
                    $this->version,
                    true
                );
                break;
        }

        $this->log_debug('Force enqueued: ' . $asset_group);
    }

    /**
     * Log performance statistics
     */
    public function log_performance()
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $status = $this->get_status();
        error_log('=== OPERATON DMN SIMPLIFIED ASSETS REPORT ===');
        error_log('Detection Complete: ' . ($status['detection_complete'] ? 'YES' : 'NO'));
        error_log('Cache Age: ' . $status['cache_age'] . 's');
        error_log('Cache Entries: ' . $status['cache_entries']);
        error_log('Should Load: ' . ($status['should_load'] ? 'YES' : 'NO'));
        error_log('WordPress Frontend State: ' . json_encode($status['wordpress_states']));
        error_log('============================================');
    }

    /**
     * Conditional debug logging
     */
    private function log_debug($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Assets: ' . $message);
        }
    }

    // =============================================================================
    // BACKWARD COMPATIBILITY METHODS
    // =============================================================================

    /**
     * Static detection method for compatibility with existing code
     */
    /**
     * ISSUE 3 FIX: Replace your current should_load_frontend_assets() method with this:
     */
    public static function should_load_frontend_assets()
    {
        // ISSUE 3 FIX: Generate unique request ID for this detection run
        $current_request_id = self::generate_request_id();

        // ISSUE 3 FIX: If we already have a result for this exact request, return it immediately
        if (self::$detection_result !== null && self::$detection_request_id === $current_request_id)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 3 FIX: Using cached detection result: ' . (self::$detection_result ? 'LOAD' : 'SKIP'));
            }
            return self::$detection_result;
        }

        // ISSUE 3 FIX: Prevent concurrent detection runs with lock
        if (self::$detection_lock)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 3 FIX: Detection in progress, waiting for result...');
            }
            // Wait briefly for the locked detection to complete
            $wait_attempts = 0;
            while (self::$detection_lock && $wait_attempts < 10)
            {
                usleep(1000); // Wait 1ms
                $wait_attempts++;
            }
            // Return the result from the completed detection
            return self::$detection_result ?? false;
        }

        // ISSUE 3 FIX: Set lock to prevent multiple simultaneous detections
        self::$detection_lock = true;
        self::$detection_request_id = $current_request_id;

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('ISSUE 3 FIX: Starting detection run - Request ID: ' . $current_request_id);
        }

        try
        {
            // Skip detection for asset requests (CSS, JS, images)
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)(\?|$)/i', $request_uri))
            {
                self::$detection_result = false;
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('ISSUE 3 FIX: Skipping detection - Asset request');
                }
                return false;
            }

            $should_load = false;

            // Method 1: Gravity Forms class exists (most reliable)
            if (class_exists('GFForms'))
            {
                $should_load = true;
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('ISSUE 3 FIX: Detection method 1 - GFForms class available');
                }
            }
            // Method 2: Admin GF pages
            elseif (is_admin() && self::is_gravity_forms_admin_page_static())
            {
                $should_load = true;
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('ISSUE 3 FIX: Detection method 2 - GF admin page');
                }
            }
            // Method 3: Content analysis (frontend only)
            elseif (!is_admin() && self::has_gravity_forms_in_content_static())
            {
                $should_load = true;
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('ISSUE 3 FIX: Detection method 3 - GF content found');
                }
            }
            // Method 4: URL indicators
            elseif (self::has_gravity_forms_url_indicators_static())
            {
                $should_load = true;
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('ISSUE 3 FIX: Detection method 4 - GF URL indicators');
                }
            }

            // ISSUE 3 FIX: Store result for this request
            self::$detection_result = $should_load;

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('ISSUE 3 FIX: Detection complete - Result: ' . ($should_load ? 'LOAD' : 'SKIP') . ' - Request ID: ' . $current_request_id);
            }

            return $should_load;
        }
        finally
        {
            // ISSUE 3 FIX: Always release the lock
            self::$detection_lock = false;
        }
    }

    /**
     * Static helper methods for compatibility
     */
    private static function is_gravity_forms_admin_page_static()
    {
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'toplevel_page_gf_') === 0;
    }

    private static function has_gravity_forms_in_content_static()
    {
        global $post;

        if (!$post) {
            return false;
        }

        // Cache by post ID
        if (isset(self::$detection_cache['content_' . $post->ID])) {
            return self::$detection_cache['content_' . $post->ID];
        }

        $has_forms = has_shortcode($post->post_content, 'gravityform') ||
            has_block('gravityforms/form', $post);

        self::$detection_cache['content_' . $post->ID] = $has_forms;

        return $has_forms;
    }

    private static function has_gravity_forms_url_indicators_static()
    {
        return (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview') ||
            isset($_GET['gf_token']) ||
            (strpos($_SERVER['REQUEST_URI'] ?? '', '/gravityforms') !== false);
    }

    public static function get_coordinator_status()
    {
        static $temp_instance = null;
        if ($temp_instance === null) {
            $temp_instance = new self('', '1.0.0');
        }
        return $temp_instance->get_status();
    }

    public static function reset_loading_coordinator()
    {
        self::reset_all_states();
    }

    public function get_loading_state()
    {
        return $this->get_status();
    }

    public function maybe_enqueue_frontend_assets_legacy()
    {
        $this->maybe_enqueue_frontend_assets();
    }

    public function clear_form_localization_cache($form_id)
    {
        $this->clear_form_cache($form_id);
    }

    public function clear_all_localization_cache()
    {
        $this->clear_form_cache();
    }

    // Getter methods for external access
    public function get_plugin_url()
    {
        return $this->plugin_url;
    }

    public function get_version()
    {
        return $this->version;
    }
}
