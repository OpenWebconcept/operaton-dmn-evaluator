<?php

/**
 * Gravity Forms Integration Manager for Operaton DMN Plugin
 *
 * Provides comprehensive integration between Gravity Forms and DMN evaluation system.
 * Handles form detection, asset loading, button placement, and evaluation coordination.
 *
 * Key Features:
 * - Automatic form detection and configuration loading
 * - Dynamic button placement with multi-page form support
 * - Asset loading optimization with intelligent caching
 * - Radio button synchronization for complex forms
 * - Decision flow integration and visualization
 * - Performance monitoring and debug capabilities
 *
 * @package    OperatonDMN
 * @subpackage GravityFormsIntegration
 * @since      1.0.0
 * @author     Operaton DMN Team
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Operaton_DMN_Gravity_Forms
 *
 * Main integration class between Operaton DMN evaluation system and Gravity Forms.
 * Manages all aspects of form integration including hooks, assets, and evaluation logic.
 */
class Operaton_DMN_Gravity_Forms
{
    // =============================================================================
    // CLASS CONSTANTS AND STATIC PROPERTIES
    // =============================================================================

    /**
     * Minimum supported Gravity Forms version
     *
     * @since 1.0.0
     * @var string
     */
    const MIN_GF_VERSION = '2.0';

    /**
     * Cache duration for form configurations (in seconds)
     *
     * @since 1.0.0
     * @var int
     */
    const CACHE_DURATION = 300; // 5 minutes

    /**
     * Form configuration cache
     * Stores loaded DMN configurations to reduce database queries
     *
     * @since 1.0.0
     * @var array
     */
    private static $form_config_cache = array();

    /**
     * Form fields cache
     * Stores Gravity Forms field definitions to reduce API calls
     *
     * @since 1.0.0
     * @var array
     */
    private static $form_fields_cache = array();

    /**
     * Localized form configurations tracker
     * Prevents duplicate JavaScript configuration output
     *
     * @since 1.0.0
     * @var array
     */
    private static $localized_form_configs = array();

    /**
     * Localization timestamps tracker
     * Prevents rapid-fire localization attempts
     *
     * @since 1.0.0
     * @var array
     */
    private static $localization_timestamps = array();

    // =============================================================================
    // INSTANCE PROPERTIES
    // =============================================================================

    /**
     * Core plugin instance reference
     *
     * @since 1.0.0
     * @var OperatonDMNEvaluator
     */
    private $core;

    /**
     * Assets manager instance reference
     *
     * @since 1.0.0
     * @var Operaton_DMN_Assets
     */
    private $assets;

    /**
     * Database manager instance reference
     *
     * @since 1.0.0
     * @var Operaton_DMN_Database
     */
    private $database;

    /**
     * Performance monitor instance reference
     *
     * @since 1.0.0
     * @var Operaton_DMN_Performance_Monitor|null
     */
    private $performance;

    /**
     * Gravity Forms availability flag
     * Cached result of Gravity Forms availability check
     *
     * @since 1.0.0
     * @var bool|null
     */
    private $gravity_forms_available = null;

    // =============================================================================
    // CONSTRUCTOR AND INITIALIZATION
    // =============================================================================

    /**
     * Constructor - Initialize the Gravity Forms integration manager
     *
     * Sets up manager dependencies and initializes WordPress hooks for
     * Gravity Forms integration functionality.
     *
     * @since 1.0.0
     * @param OperatonDMNEvaluator   $core     Core plugin instance
     * @param Operaton_DMN_Assets    $assets   Assets manager instance
     * @param Operaton_DMN_Database  $database Database manager instance
     */
    public function __construct($core, $assets, $database)
    {
        $this->core = $core;
        $this->assets = $assets;
        $this->database = $database;

        // Initialize performance monitor if available
        if (method_exists($core, 'get_performance_instance')) {
            $this->performance = $core->get_performance_instance();
        }

        $this->log_debug('Gravity Forms integration manager initialized');
        $this->init_hooks();
    }

    /**
     * Initialize WordPress and Gravity Forms hooks
     *
     * Sets up all necessary WordPress actions and filters for proper integration.
     * Uses priority-based loading to ensure correct initialization order.
     *
     * @since 1.0.0
     * @return void
     */
    private function init_hooks(): void
    {
        // Early availability check with caching
        add_action('init', array($this, 'check_gravity_forms_availability'), 1);

        // Conditional initialization based on GF availability
        add_action('init', array($this, 'conditional_init_gravity_forms'), 5);

        // Asset loading hooks
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'), 15);

        // Gravity Forms specific hooks (loaded after plugins_loaded)
        add_action('plugins_loaded', array($this, 'add_gravity_forms_hooks'), 20);

        // Admin integration hooks
        add_action('admin_init', array($this, 'init_admin_integration'));

        $this->log_debug('WordPress hooks initialized');
    }

    // =============================================================================
    // GRAVITY FORMS AVAILABILITY AND SETUP
    // =============================================================================

    /**
     * Check Gravity Forms availability with caching
     *
     * Determines if Gravity Forms is available and compatible with this plugin.
     * Results are cached to prevent repeated checks.
     *
     * @since 1.0.0
     * @return bool True if Gravity Forms is available and compatible
     */
    public function check_gravity_forms_availability(): bool
    {
        if ($this->gravity_forms_available === null) {
            $this->gravity_forms_available = $this->perform_gravity_forms_check();

            $status = $this->gravity_forms_available ? 'available' : 'not available';
            $this->log_debug('Gravity Forms is ' . $status);
        }

        return $this->gravity_forms_available;
    }

    /**
     * Perform the actual Gravity Forms availability check
     *
     * @since 1.0.0
     * @return bool True if available and compatible
     */
    private function perform_gravity_forms_check(): bool
    {
        // Check if classes exist
        if (!class_exists('GFForms') || !class_exists('GFAPI')) {
            return false;
        }

        // Check version compatibility
        if (class_exists('GFCommon') && isset(GFCommon::$version)) {
            return version_compare(GFCommon::$version, self::MIN_GF_VERSION, '>=');
        }

        return true;
    }

    /**
     * Conditional initialization for Gravity Forms integration
     *
     * Only initializes GF-specific hooks and functionality if Gravity Forms
     * is available. Prevents errors when GF is not installed.
     *
     * @since 1.0.0
     * @return void
     */
    public function conditional_init_gravity_forms(): void
    {
        if (!$this->check_gravity_forms_availability()) {
            $this->log_debug('Gravity Forms not available - skipping integration');
            return;
        }

        // Form rendering and interaction hooks
        add_filter('gform_submit_button', array($this, 'add_evaluate_button'), 10, 2);
        add_action('gform_enqueue_scripts', array($this, 'enqueue_gravity_scripts'), 10, 2);
        add_action('gform_pre_render', array($this, 'ensure_assets_loaded'), 5, 1);

        // Form validation and submission hooks
        add_filter('gform_validation', array($this, 'validate_dmn_fields'), 10, 1);
        add_action('gform_after_submission', array($this, 'handle_post_submission'), 10, 2);

        // Admin interface hooks
        if (is_admin()) {
            add_action('gform_editor_js', array($this, 'add_editor_script'));
            add_action('gform_field_advanced_settings', array($this, 'add_field_advanced_settings'), 10, 2);
        }

        // Radio synchronization hooks
        $this->add_radio_sync_hooks();

        $this->log_debug('Gravity Forms integration hooks initialized');
    }

    // =============================================================================
    // FORM BUTTON AND UI INTEGRATION
    // =============================================================================

    /**
     * Add DMN evaluation button to Gravity Forms
     *
     * This is the main integration point that adds the evaluation button
     * to forms that have DMN configurations. The button is initially hidden
     * and shown/hidden based on form state and page navigation.
     *
     * @since 1.0.0
     * @param string $button Existing form button HTML
     * @param array  $form   Gravity Forms form array
     * @return string Modified button HTML with DMN evaluation button
     */
    public function add_evaluate_button($button, $form): string
    {
        // Skip button addition in admin or AJAX contexts
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return $button;
        }

        $form_id = $form['id'];
        $config = $this->get_form_config($form_id);

        // No DMN configuration found for this form
        if (!$config) {
            return $button;
        }

        $this->log_debug('Adding evaluate button for form ' . $form_id);

        // Build the evaluation button HTML
        $evaluate_button = sprintf(
            '<input type="button" id="operaton-evaluate-%1$d" value="%2$s" class="gform_button gform-theme-button operaton-evaluate-btn" data-form-id="%1$d" data-config-id="%3$d" style="display: none;">',
            $form_id,
            esc_attr($config->button_text ?? 'Evaluate'),
            $config->id
        );

        // Add decision flow container if enabled
        $decision_flow_container = '';
        if (!empty($config->show_decision_flow)) {
            $decision_flow_container = sprintf(
                '<div id="decision-flow-summary-%d" class="decision-flow-summary" style="display: none;"></div>',
                $form_id
            );
        }

        return $button . $evaluate_button . $decision_flow_container;
    }

    // =============================================================================
    // ASSET LOADING AND MANAGEMENT
    // =============================================================================

    /**
     * Ensure form assets are loaded when form is rendered
     *
     * Called during Gravity Forms pre-render to ensure all necessary
     * assets are loaded for forms with DMN configurations.
     *
     * @since 1.0.0
     * @param array $form Gravity Forms form array
     * @return array Unmodified form array
     */
    public function ensure_assets_loaded($form)
    {
        if (is_admin()) {
            return $form;
        }

        $form_id = $form['id'];
        $config = $this->get_form_config($form_id);

        if ($config) {
            $this->log_debug('Form ' . $form_id . ' has DMN config - ensuring assets loaded');
            $this->enqueue_gravity_scripts($form, false);
        }

        return $form;
    }

    /**
     * Maybe enqueue frontend assets for DMN-enabled forms
     *
     * Checks if the current page contains forms with DMN configurations
     * and loads assets only when needed for optimal performance.
     *
     * @since 1.0.0
     * @return void
     */
    public function maybe_enqueue_assets(): void
    {
        if (is_admin() || !$this->check_gravity_forms_availability()) {
            return;
        }

        $this->log_debug('Checking for DMN forms on page');

        // Use centralized detection from assets manager
        if (Operaton_DMN_Assets::should_load_frontend_assets() && $this->has_dmn_enabled_forms_on_page()) {
            $this->assets->enqueue_frontend_assets();
            $this->enqueue_gravity_forms_scripts();
            $this->log_debug('Assets loaded for DMN-enabled forms');
        }
    }

    /**
     * Enqueue Gravity Forms specific scripts
     *
     * Loads the main Gravity Forms integration JavaScript and localizes
     * it with necessary configuration data.
     *
     * @since 1.0.0
     * @param array $form    Gravity Forms form array
     * @param bool  $is_ajax Whether this is an AJAX form submission
     * @return void
     */
    public function enqueue_gravity_scripts($form, $is_ajax): void
    {
        $form_id = $form['id'];
        $config = $this->get_form_config($form_id);

        if (!$config) {
            $this->log_debug('No config found for form ' . $form_id);
            return;
        }

        $this->log_debug('Enqueuing scripts for form ' . $form_id);

        // Load form-specific assets
        $this->enqueue_gravity_form_assets($form, $config);

        // Ensure form configuration is localized
        add_action('wp_footer', function () use ($form, $config) {
            $this->ensure_form_config_localized($form, $config);
        }, 5);
    }

    /**
     * Enqueue assets for a specific gravity form
     *
     * @since 1.0.0
     * @param array  $form   Gravity Forms form array
     * @param object $config DMN configuration object
     * @return void
     */
    private function enqueue_gravity_form_assets($form, $config): void
    {
        $form_id = $form['id'];

        $this->log_debug('Loading assets for form ' . $form_id);

        // Load main Gravity Forms integration script
        if (!wp_script_is('operaton-dmn-gravity-integration', 'enqueued')) {
            $this->enqueue_gravity_forms_scripts();
        }

        // Load radio sync assets if needed
        if ($this->form_needs_radio_sync($form_id)) {
            $this->assets->enqueue_radio_sync_assets($form_id);
        }
    }

    /**
     * Enqueue main Gravity Forms integration scripts
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueue_gravity_forms_scripts(): void
    {
        static $scripts_loaded = false;

        if ($scripts_loaded) {
            return;
        }

        if (!wp_script_is('operaton-dmn-gravity-integration', 'enqueued')) {
            wp_enqueue_script(
                'operaton-dmn-gravity-integration',
                $this->assets->get_plugin_url() . 'assets/js/gravity-forms.js',
                array('jquery', 'operaton-dmn-frontend'),
                $this->assets->get_version(),
                true
            );

            // Localize with Gravity Forms specific strings
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
    }

    // =============================================================================
    // CONFIGURATION LOCALIZATION
    // =============================================================================

    /**
     * Ensure form configuration is localized to JavaScript
     *
     * Outputs form configuration data to JavaScript with duplicate prevention.
     * This provides the frontend with all necessary configuration for evaluation.
     *
     * @since 1.0.0
     * @param array  $form   Gravity Forms form array
     * @param object $config DMN configuration object
     * @return void
     */
    private function ensure_form_config_localized($form, $config): void
    {
        $form_id = $form['id'];
        $config_var_name = 'operaton_config_' . $form_id;
        $localization_key = $form_id . '_' . ($config->id ?? 0);

        // Prevent duplicate localization
        if (isset(self::$localized_form_configs[$localization_key])) {
            $this->log_debug('Config already localized for form ' . $form_id);
            return;
        }

        // Prevent rapid-fire attempts
        if (isset(self::$localization_timestamps[$form_id])) {
            $last_attempt = self::$localization_timestamps[$form_id];
            if ((time() - $last_attempt) < 3) {
                $this->log_debug('Preventing rapid localization for form ' . $form_id);
                return;
            }
        }

        self::$localization_timestamps[$form_id] = time();
        $this->log_debug('Localizing config for form ' . $form_id);

        // Build configuration arrays
        $field_mappings = $this->get_field_mappings($config);
        $result_mappings = $this->get_result_mappings($config);
        $result_field_ids = $this->extract_result_field_ids($form_id, $config, $field_mappings, $result_mappings);

        // JavaScript configuration object
        $js_config = array(
            'config_id' => $config->id,
            'button_text' => $config->button_text ?? 'Evaluate',
            'field_mappings' => $field_mappings,
            'result_mappings' => $result_mappings,
            'form_id' => $form_id,
            'evaluation_step' => $config->evaluation_step ?? 'auto',
            'use_process' => !empty($config->use_process),
            'show_decision_flow' => !empty($config->show_decision_flow),
            'result_display_field' => $config->result_display_field ?? null,
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'result_field_ids' => $result_field_ids,
            'clear_results_on_change' => true
        );

        // Output configuration with duplicate protection
        echo '<script type="text/javascript">';
        echo '(function() {';
        echo 'if (typeof window.' . $config_var_name . ' !== "undefined") { return; }';
        echo 'window.' . $config_var_name . ' = ' . wp_json_encode($js_config) . ';';
        echo 'if (window.console && window.console.log) {';
        echo '  console.log("DMN config localized for form ' . $form_id . '", window.' . $config_var_name . ');';
        echo '}';
        echo '})();';
        echo '</script>';

        // Mark as successfully localized
        self::$localized_form_configs[$localization_key] = array(
            'timestamp' => time(),
            'form_id' => $form_id,
            'config_id' => $config->id ?? 0,
            'config_var_name' => $config_var_name
        );

        $this->log_debug('Config successfully localized for form ' . $form_id);
    }

    // =============================================================================
    // RADIO BUTTON SYNCHRONIZATION
    // =============================================================================

    /**
     * Add radio button synchronization hooks
     *
     * Sets up hooks for radio button synchronization feature which keeps
     * related radio buttons in sync across different form sections.
     *
     * @since 1.0.0
     * @return void
     */
    private function add_radio_sync_hooks(): void
    {
        add_action('gform_pre_render', array($this, 'maybe_add_radio_sync'), 15, 1);
    }

    /**
     * Maybe add radio synchronization for the form
     *
     * @since 1.0.0
     * @param array $form Gravity Forms form array
     * @return array Unmodified form array
     */
    public function maybe_add_radio_sync($form)
    {
        $form_id = $form['id'];

        if ($this->form_needs_radio_sync($form_id)) {
            $this->log_debug('Adding radio sync for form ' . $form_id);
            $this->assets->enqueue_radio_sync_assets($form_id);

            add_action('wp_footer', function () use ($form_id) {
                $this->add_radio_sync_initialization($form_id);
            }, 15);
        }

        return $form;
    }

    /**
     * Check if form needs radio button synchronization
     *
     * @since 1.0.0
     * @param int $form_id Form ID to check
     * @return bool True if radio sync is needed
     */
    private function form_needs_radio_sync($form_id): bool
    {
        $config = $this->get_form_config($form_id);

        if (!$config) {
            return false;
        }

        // Add logic to determine if radio sync is needed
        // This could be based on form structure or configuration settings
        return false; // Placeholder - implement based on your requirements
    }

    /**
     * Add radio sync initialization script
     *
     * @since 1.0.0
     * @param int $form_id Form ID
     * @return void
     */
    private function add_radio_sync_initialization($form_id): void
    {
        echo '<script type="text/javascript">';
        echo 'if (typeof window.initializeRadioSync === "function") {';
        echo '  window.initializeRadioSync(' . intval($form_id) . ');';
        echo '}';
        echo '</script>';
    }

    // =============================================================================
    // AJAX HANDLERS AND FORM PROCESSING
    // =============================================================================

    /**
     * Handle AJAX form evaluation request
     *
     * Main AJAX handler for DMN evaluation requests from the frontend.
     * Validates request, processes form data, and returns evaluation results.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_evaluate_form(): void
    {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'operaton_gravity_nonce')) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('Security verification failed.', 'operaton-dmn')
            )));
        }

        $form_id = intval($_POST['form_id'] ?? 0);
        $config_id = intval($_POST['config_id'] ?? 0);

        if (!$form_id || !$config_id) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('Missing form or configuration ID.', 'operaton-dmn')
            )));
        }

        // Get configuration and validate
        $config = $this->database->get_config($config_id);
        if (!$config || $config->form_id != $form_id) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('Invalid configuration.', 'operaton-dmn')
            )));
        }

        // Process evaluation through API manager
        $api_manager = $this->core->get_api_instance();
        if (!$api_manager) {
            wp_die(json_encode(array(
                'success' => false,
                'message' => __('API manager not available.', 'operaton-dmn')
            )));
        }

        $form_data = $_POST['form_data'] ?? array();
        $result = $api_manager->evaluate_dmn($config, $form_data);

        wp_die(json_encode($result));
    }

    // =============================================================================
    // FORM VALIDATION AND SUBMISSION
    // =============================================================================

    /**
     * Validate DMN-specific form fields
     *
     * Integrates with Gravity Forms validation system to add DMN-specific
     * validation rules and requirements.
     *
     * @since 1.0.0
     * @param array $validation_result Gravity Forms validation result
     * @return array Modified validation result
     */
    public function validate_dmn_fields($validation_result)
    {
        $form = $validation_result['form'];
        $form_id = $form['id'];
        $config = $this->get_form_config($form_id);

        if (!$config) {
            return $validation_result;
        }

        // Add custom validation logic here if needed
        // For example, validate required fields for DMN evaluation

        return $validation_result;
    }

    /**
     * Handle form submission completion
     *
     * Called after Gravity Forms processes a form submission.
     * Can be used for additional processing or logging.
     *
     * @since 1.0.0
     * @param array $entry Gravity Forms entry array
     * @param array $form  Gravity Forms form array
     * @return void
     */
    public function handle_post_submission($entry, $form): void
    {
        $form_id = $form['id'];
        $config = $this->get_form_config($form_id);

        if (!$config) {
            return;
        }

        $this->log_debug('Form ' . $form_id . ' submitted with DMN configuration');

        // Add post-submission processing logic here if needed
        // For example, logging or additional data processing
    }

    // =============================================================================
    // ADMIN INTEGRATION METHODS
    // =============================================================================

    /**
     * Initialize admin integration features
     *
     * Sets up admin-specific functionality when in WordPress admin area.
     *
     * @since 1.0.0
     * @return void
     */
    public function init_admin_integration(): void
    {
        if (!is_admin() || !$this->check_gravity_forms_availability()) {
            return;
        }

        // Admin-specific initialization logic here
        $this->log_debug('Admin integration initialized');
    }

    /**
     * Add editor script for Gravity Forms form editor
     *
     * Adds JavaScript functionality to the Gravity Forms form editor
     * for enhanced DMN integration features.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_editor_script(): void
    {
        // Add editor enhancement scripts here
        echo '<script type="text/javascript">';
        echo '// DMN Gravity Forms editor enhancements';
        echo 'console.log("DMN editor integration loaded");';
        echo '</script>';
    }

    /**
     * Add advanced field settings in form editor
     *
     * Adds DMN-specific field settings to the Gravity Forms field editor.
     *
     * @since 1.0.0
     * @param int $position Setting position
     * @param int $form_id  Form ID
     * @return void
     */
    public function add_field_advanced_settings($position, $form_id): void
    {
        // Add advanced settings HTML here if needed
        // This could include DMN variable mapping settings
    }

    // =============================================================================
    // PUBLIC API METHODS
    // =============================================================================

    /**
     * Public API: Check if Gravity Forms is available
     *
     * @since 1.0.0
     * @return bool True if Gravity Forms is available
     */
    public function is_gravity_forms_available(): bool
    {
        return $this->check_gravity_forms_availability();
    }

    /**
     * Public API: Get form configuration
     *
     * @since 1.0.0
     * @param int $form_id Form ID
     * @return object|null Configuration object or null if not found
     */
    public function get_form_configuration($form_id)
    {
        return $this->get_form_config($form_id);
    }

    /**
     * Public API: Get all available Gravity Forms with field information
     *
     * @since 1.0.0
     * @return array Array of forms with field details
     */
    public function get_available_forms(): array
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

    /**
     * Public API: Check if specific form exists
     *
     * @since 1.0.0
     * @param int $form_id Form ID to check
     * @return bool True if form exists
     */
    public function form_exists($form_id): bool
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

    /**
     * Public API: Get form title
     *
     * @since 1.0.0
     * @param int $form_id Form ID
     * @return string Form title or empty string if not found
     */
    public function get_form_title($form_id): string
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

    /**
     * Public API: Get form fields with caching
     *
     * @since 1.0.0
     * @param int $form_id Form ID
     * @return array Array of form field data
     */
    public function get_form_fields($form_id): array
    {
        if (!$this->check_gravity_forms_availability()) {
            return array();
        }

        $cache_key = 'fields_' . $form_id;

        if (isset(self::$form_fields_cache[$cache_key])) {
            return self::$form_fields_cache[$cache_key];
        }

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

            self::$form_fields_cache[$cache_key] = $fields;
            return $fields;

        } catch (Exception $e) {
            error_log('Operaton DMN Gravity Forms: Error getting form fields: ' . $e->getMessage());
            self::$form_fields_cache[$cache_key] = array();
            return array();
        }
    }

    /**
     * Public API: Get integration status information
     *
     * @since 1.0.0
     * @return array Status information array
     */
    public function get_integration_status(): array
    {
        $status = array(
            'gravity_forms_available' => $this->check_gravity_forms_availability(),
            'hooks_registered' => true,
            'forms_with_dmn_config' => 0,
            'total_forms' => 0,
            'version_info' => array(),
            'cache_status' => array(
                'config_cache_size' => count(self::$form_config_cache),
                'fields_cache_size' => count(self::$form_fields_cache),
                'localized_configs' => count(self::$localized_form_configs)
            )
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

            // Add version information
            if (class_exists('GFCommon')) {
                $status['version_info'] = array(
                    'gravity_forms_version' => GFCommon::$version ?? 'unknown',
                    'minimum_required' => self::MIN_GF_VERSION,
                    'compatible' => version_compare(GFCommon::$version ?? '0', self::MIN_GF_VERSION, '>=')
                );
            }
        }

        return $status;
    }

    // =============================================================================
    // CACHE MANAGEMENT METHODS
    // =============================================================================

    /**
     * Clear form configuration cache
     *
     * @since 1.0.0
     * @param int|null $form_id Optional specific form ID to clear
     * @return void
     */
    public function clear_form_cache($form_id = null): void
    {
        if ($form_id) {
            $keys_to_remove = array();
            foreach (self::$form_config_cache as $key => $value) {
                if (strpos($key, '_' . $form_id) !== false) {
                    $keys_to_remove[] = $key;
                }
            }

            foreach ($keys_to_remove as $key) {
                unset(self::$form_config_cache[$key]);
            }

            // Clear fields cache for specific form
            unset(self::$form_fields_cache['fields_' . $form_id]);

            $this->log_debug('Cleared cache for form ' . $form_id);
        } else {
            $this->clear_all_caches();
        }
    }

    /**
     * Clear all caches
     *
     * @since 1.0.0
     * @return void
     */
    public function clear_all_caches(): void
    {
        self::$form_config_cache = array();
        self::$form_fields_cache = array();
        self::$localized_form_configs = array();
        self::$localization_timestamps = array();

        $this->log_debug('All caches cleared');
    }

    /**
     * Clear Gravity Forms localization cache
     *
     * @since 1.0.0
     * @param int|null $form_id Optional specific form ID
     * @return void
     */
    public function clear_gravity_forms_localization_cache($form_id = null): void
    {
        if ($form_id) {
            $keys_to_remove = array();
            foreach (self::$localized_form_configs as $key => $data) {
                if ($data['form_id'] == $form_id) {
                    $keys_to_remove[] = $key;
                }
            }

            foreach ($keys_to_remove as $key) {
                unset(self::$localized_form_configs[$key]);
            }

            unset(self::$localization_timestamps[$form_id]);
            $this->log_debug('Cleared localization cache for form ' . $form_id);
        } else {
            self::$localized_form_configs = array();
            self::$localization_timestamps = array();
            $this->log_debug('All localization cache cleared');
        }
    }

    // =============================================================================
    // BACKWARD COMPATIBILITY METHODS
    // =============================================================================

    /**
     * Add Gravity Forms hooks (backward compatibility)
     *
     * @since 1.0.0
     * @return void
     */
    public function add_gravity_forms_hooks(): void
    {
        // This method is kept for backward compatibility
        // Most functionality is now handled in conditional_init_gravity_forms()
        $this->log_debug('Gravity Forms hooks method called (compatibility)');
    }

    /**
     * Reload form configuration (public API)
     *
     * @since 1.0.0
     * @param int $form_id Form ID
     * @return object|null Reloaded configuration
     */
    public function reload_form_configuration($form_id)
    {
        $this->clear_form_cache($form_id);
        return $this->get_form_config($form_id);
    }

    // =============================================================================
    // UTILITY AND HELPER METHODS
    // =============================================================================

    /**
     * Get form configuration with caching
     *
     * @since 1.0.0
     * @param int $form_id Form ID
     * @return object|null Configuration object or null if not found
     */
    private function get_form_config($form_id)
    {
        $cache_key = 'config_' . $form_id;

        if (isset(self::$form_config_cache[$cache_key])) {
            return self::$form_config_cache[$cache_key];
        }

        $config = $this->database->get_config_by_form_id($form_id);
        self::$form_config_cache[$cache_key] = $config;

        return $config;
    }

    /**
     * Check if form has DMN configuration
     *
     * @since 1.0.0
     * @param int $form_id Form ID
     * @return bool True if form has DMN configuration
     */
    private function form_has_dmn_config($form_id): bool
    {
        return $this->get_form_config($form_id) !== null;
    }

    /**
     * Check if page has DMN-enabled forms
     *
     * @since 1.0.0
     * @return bool True if page has DMN-enabled forms
     */
    private function has_dmn_enabled_forms_on_page(): bool
    {
        global $post;

        if (!$post) {
            return false;
        }

        // Check shortcodes
        $shortcode_form_ids = $this->extract_form_ids_from_shortcodes($post->post_content);
        if ($this->any_forms_have_dmn_config($shortcode_form_ids)) {
            return true;
        }

        // Check Gutenberg blocks
        $block_form_ids = $this->extract_form_ids_from_blocks($post);
        if ($this->any_forms_have_dmn_config($block_form_ids)) {
            return true;
        }

        // Check URL parameters for specific form
        if (isset($_GET['gf_page']) && isset($_GET['id'])) {
            $form_id = intval($_GET['id']);
            if ($form_id > 0) {
                return $this->form_has_dmn_config($form_id);
            }
        }

        return false;
    }

    /**
     * Check if any forms in array have DMN config
     *
     * @since 1.0.0
     * @param array $form_ids Array of form IDs
     * @return bool True if any form has DMN config
     */
    private function any_forms_have_dmn_config($form_ids): bool
    {
        foreach ($form_ids as $form_id) {
            if ($this->form_has_dmn_config($form_id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract form IDs from shortcodes
     *
     * @since 1.0.0
     * @param string $content Post content
     * @return array Array of form IDs
     */
    private function extract_form_ids_from_shortcodes($content): array
    {
        $form_ids = array();
        $pattern = '/\[gravityform[^\]]*id=["\'](\d+)["\'][^\]]*\]/';

        if (preg_match_all($pattern, $content, $matches)) {
            $form_ids = array_map('intval', $matches[1]);
        }

        return array_unique($form_ids);
    }

    /**
     * Extract form IDs from Gutenberg blocks
     *
     * @since 1.0.0
     * @param object $post Post object
     * @return array Array of form IDs
     */
    private function extract_form_ids_from_blocks($post): array
    {
        $form_ids = array();

        if (function_exists('parse_blocks')) {
            $blocks = parse_blocks($post->post_content);
            $form_ids = $this->find_gravity_form_ids_in_blocks($blocks);
        }

        return array_unique($form_ids);
    }

    /**
     * Find Gravity Form IDs in blocks recursively
     *
     * @since 1.0.0
     * @param array $blocks Array of parsed blocks
     * @return array Array of form IDs
     */
    private function find_gravity_form_ids_in_blocks($blocks): array
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
     * Get field mappings from configuration
     *
     * @since 1.0.0
     * @param object $config Configuration object
     * @return array Field mappings array
     */
    private function get_field_mappings($config): array
    {
        if (empty($config->field_mappings)) {
            return array();
        }

        $field_mappings = json_decode($config->field_mappings, true);
        return is_array($field_mappings) ? $field_mappings : array();
    }

    /**
     * Get result mappings from configuration
     *
     * @since 1.0.0
     * @param object $config Configuration object
     * @return array Result mappings array
     */
    private function get_result_mappings($config): array
    {
        if (empty($config->result_mappings)) {
            return array();
        }

        $result_mappings = json_decode($config->result_mappings, true);
        return is_array($result_mappings) ? $result_mappings : array();
    }

    /**
     * Extract result field IDs for JavaScript configuration
     *
     * @since 1.0.0
     * @param int    $form_id         Form ID
     * @param object $config          Configuration object
     * @param array  $field_mappings  Field mappings array
     * @param array  $result_mappings Result mappings array
     * @return array Array of result field IDs
     */
    private function extract_result_field_ids($form_id, $config, $field_mappings, $result_mappings): array
    {
        $result_field_ids = array();

        // Primary: Extract from result_mappings (most reliable)
        if (is_array($result_mappings)) {
            foreach ($result_mappings as $dmn_variable => $mapping) {
                if (isset($mapping['field_id']) && is_numeric($mapping['field_id'])) {
                    $field_id = intval($mapping['field_id']);
                    if (!in_array($field_id, $result_field_ids)) {
                        $result_field_ids[] = $field_id;
                    }
                }
            }
        }

        // Secondary: Look for clear result variables in field_mappings
        if (is_array($field_mappings)) {
            foreach ($field_mappings as $dmn_variable => $mapping) {
                if (isset($mapping['field_id']) && is_numeric($mapping['field_id'])) {
                    $field_id = intval($mapping['field_id']);
                    // Only include variables that clearly indicate results
                    if (strpos($dmn_variable, 'aanmerking') === 0 || strpos($dmn_variable, 'result') === 0) {
                        if (!in_array($field_id, $result_field_ids)) {
                            $result_field_ids[] = $field_id;
                        }
                    }
                }
            }
        }

        // Form-specific corrections (if needed)
        if ($form_id == 8) {
            $correct_result_fields = array(35, 36); // aanmerkingHeusdenPas, aanmerkingKindPakket
            $result_field_ids = array_intersect($result_field_ids, $correct_result_fields);
            foreach ($correct_result_fields as $field_id) {
                if (!in_array($field_id, $result_field_ids)) {
                    $result_field_ids[] = $field_id;
                }
            }
        }

        sort($result_field_ids);
        return $result_field_ids;
    }

    /**
     * Count form pages for multi-page forms
     *
     * @since 1.0.0
     * @param array $form Gravity Forms form array
     * @return int Number of pages
     */
    private function count_form_pages($form): int
    {
        if (empty($form['fields'])) {
            return 1;
        }

        $max_page = 1;
        foreach ($form['fields'] as $field) {
            if ($field->type === 'page') {
                $max_page++;
            }
        }

        return $max_page;
    }

    /**
     * Get manager instance references (for external access)
     */

    /**
     * Get core instance
     *
     * @since 1.0.0
     * @return OperatonDMNEvaluator Core instance
     */
    public function get_core_instance()
    {
        return $this->core;
    }

    /**
     * Get assets manager
     *
     * @since 1.0.0
     * @return Operaton_DMN_Assets Assets manager instance
     */
    public function get_assets_manager()
    {
        return $this->assets;
    }

    /**
     * Get database manager
     *
     * @since 1.0.0
     * @return Operaton_DMN_Database Database manager instance
     */
    public function get_database_manager()
    {
        return $this->database;
    }

    // =============================================================================
    // DEBUG AND LOGGING METHODS
    // =============================================================================

    /**
     * Log debug message if debug mode is enabled
     *
     * @since 1.0.0
     * @param string $message Debug message
     * @return void
     */
    private function log_debug($message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN Gravity Forms: ' . $message);
        }
    }

    /**
     * Get performance metrics if performance monitor is available
     *
     * @since 1.0.0
     * @return array Performance metrics
     */
    public function get_performance_metrics(): array
    {
        if ($this->performance) {
            return $this->performance->get_metrics('gravity_forms');
        }

        return array(
            'initialization_time' => 0,
            'cache_hits' => count(self::$form_config_cache),
            'memory_usage' => memory_get_usage(true)
        );
    }
}

// End of class
