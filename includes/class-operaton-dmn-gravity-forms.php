<?php

/**
 * SIMPLIFIED: Gravity Forms Integration for Operaton DMN Plugin
 * Removes complex caching that was causing initialization loops
 * Keeps only essential functionality
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}

class Operaton_DMN_Gravity_Forms
{
    /**
     * SIMPLIFIED: Basic caching only for database queries
     */
    private static $form_config_cache = array();
    private static $form_fields_cache = array();

    private static $localized_form_configs = array();
    private static $localization_timestamps = array();

    /**
     * Core plugin instance reference
     */
    private $core;

    /**
     * Assets manager instance
     */
    private $assets;

    /**
     * Database manager instance
     */
    private $database;

    /**
     * Performance monitor instance
     */
    private $performance;

    /**
     * Flag to track if Gravity Forms is available
     */
    private $gravity_forms_available = null;

    /**
     * SIMPLIFIED Constructor
     */
    public function __construct($core, $assets, $database)
    {
        $this->core = $core;
        $this->assets = $assets;
        $this->database = $database;

        if (method_exists($core, 'get_performance_instance'))
        {
            $this->performance = $core->get_performance_instance();
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: SIMPLIFIED Integration manager initialized');
        }

        $this->init_hooks();
    }

    /**
     * SIMPLIFIED: Initialize WordPress and Gravity Forms hooks
     */
    private function init_hooks()
    {
        // Early availability check with caching
        add_action('init', array($this, 'check_gravity_forms_availability'), 1);

        // Conditional initialization
        add_action('init', array($this, 'conditional_init_gravity_forms'), 5);

        // SIMPLIFIED: Basic asset loading
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'), 15);

        // Gravity Forms specific hooks
        add_action('plugins_loaded', array($this, 'add_gravity_forms_hooks'), 20);

        // Admin hooks
        add_action('admin_init', array($this, 'init_admin_integration'));
    }

    // =============================================================================
    // SIMPLIFIED GRAVITY FORMS AVAILABILITY AND SETUP
    // =============================================================================

    /**
     * SIMPLIFIED: Check Gravity Forms availability with basic caching
     */
    public function check_gravity_forms_availability()
    {
        if ($this->gravity_forms_available === null)
        {
            $this->gravity_forms_available = class_exists('GFForms') && class_exists('GFAPI');

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                $status = $this->gravity_forms_available ? 'available' : 'not available';
                error_log('Operaton DMN Gravity Forms: Gravity Forms is ' . $status);
            }
        }

        return $this->gravity_forms_available;
    }

    /**
     * SIMPLIFIED: Conditional initialization without complex processing
     */
    public function conditional_init_gravity_forms()
    {
        if (!$this->check_gravity_forms_availability())
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Gravity Forms: SKIPPED - GF not available');
            }
            return;
        }

        // Form rendering hooks
        add_filter('gform_submit_button', array($this, 'add_evaluate_button'), 10, 2);
        add_action('gform_enqueue_scripts', array($this, 'enqueue_gravity_scripts'), 10, 2);

        // SIMPLIFIED: Basic asset loading for forms with DMN config
        add_action('gform_pre_render', array($this, 'ensure_assets_loaded'), 5, 1);

        // Admin integration
        if (is_admin())
        {
            add_action('gform_editor_js', array($this, 'add_editor_script'));
            add_action('gform_field_advanced_settings', array($this, 'add_field_advanced_settings'), 10, 2);
        }

        // Form validation and submission
        add_filter('gform_validation', array($this, 'validate_dmn_fields'), 10, 1);
        add_action('gform_after_submission', array($this, 'handle_post_submission'), 10, 2);

        $this->add_radio_sync_hooks();

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: SIMPLIFIED - Integration hooks initialized');
        }
    }

    /**
     * SIMPLIFIED: Basic asset loading without complex duplicate prevention
     */
    public function ensure_assets_loaded($form)
    {
        if (is_admin())
        {
            return $form;
        }

        $form_id = $form['id'];
        $config = $this->get_form_config($form_id);

        if ($config)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Gravity Forms: Form ' . $form_id . ' has DMN config');
            }
            $this->enqueue_gravity_scripts($form, false);
        }

        return $form;
    }

    // =============================================================================
    // SIMPLIFIED FORM DETECTION AND ASSET LOADING
    // =============================================================================

    /**
     * SIMPLIFIED: Basic asset detection without complex caching
     */
    public function maybe_enqueue_assets()
    {
        if (is_admin() || !$this->check_gravity_forms_availability())
        {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Checking for DMN forms on page');
        }

        // Use centralized detection from assets manager
        if (Operaton_DMN_Assets::should_load_frontend_assets() && $this->has_dmn_enabled_forms_on_page())
        {
            $this->assets->enqueue_frontend_assets();
            $this->enqueue_gravity_forms_scripts();

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Gravity Forms: Assets loaded for page');
            }
        }
    }

    /**
     * SIMPLIFIED: Form asset loading without complex state management
     */
    public function enqueue_gravity_form_assets($form, $config)
    {
        $form_id = $form['id'];

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Loading assets for form ' . $form_id);
        }

        // Basic GF integration script loading
        if (!wp_script_is('operaton-dmn-gravity-integration', 'enqueued'))
        {
            $this->enqueue_gravity_forms_scripts();
        }

        // Add form control script
        add_action('wp_footer', function () use ($form, $config)
        {
            $this->add_form_control_script($form, $config);
        }, 10);

        // Radio sync if needed
        if ($this->form_needs_radio_sync($form_id))
        {
            $this->assets->enqueue_radio_sync_assets($form_id);
        }
    }

    // DISABLE the add_form_control_script method by modifying this function:

    private function add_form_control_script($form, $config)
    {
        // DISABLED: Let frontend.js handle everything now
        // The old PHP-generated form control scripts were causing conflicts

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: SKIPPING PHP form control script - handled by frontend.js');
        }

        // Just return without adding any script
        return;

        /* OLD CODE - COMMENTED OUT TO PREVENT CONFLICTS
    $form_id = $form['id'];
    $target_page = $this->get_target_page($form, $config);

    $script = $this->generate_form_control_script(
        $form_id,
        $target_page,
        $config->show_decision_flow ?? false,
        $config->use_process ?? false,
        $config
    );

    echo '<script type="text/javascript">' . $script . '</script>';

    if (defined('WP_DEBUG') && WP_DEBUG)
    {
        error_log('Operaton DMN Gravity Forms: Form control script added for form ' . $form_id);
    }
    */
    }

    /**
     * SIMPLIFIED: Target page calculation
     */
    private function get_target_page($form, $config)
    {
        $evaluation_step = $config->evaluation_step ?? 'auto';

        if ($evaluation_step === 'auto')
        {
            $total_pages = $this->count_form_pages($form);
            $target_page = max(1, $total_pages - 1);
        }
        else
        {
            $target_page = intval($evaluation_step);
        }

        return $target_page;
    }

    /**
     * SIMPLIFIED: Script loading without complex duplicate prevention
     */
    public function enqueue_gravity_scripts($form, $is_ajax)
    {
        $form_id = $form['id'];
        $config = $this->get_form_config($form_id);

        if (!$config)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Gravity Forms: No config found for form ' . $form_id);
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Enqueuing scripts for form ' . $form_id);
        }

        $this->enqueue_gravity_form_assets($form, $config);

        // Ensure form configuration is localized
        add_action('wp_footer', function () use ($form, $config)
        {
            $this->ensure_form_config_localized($form, $config);
        }, 5);
    }

    /**
     * SIMPLIFIED: Basic gravity forms script loading
     */
    private function enqueue_gravity_forms_scripts()
    {
        static $scripts_loaded = false;

        if ($scripts_loaded)
        {
            return;
        }

        if (!wp_script_is('operaton-dmn-gravity-integration', 'enqueued'))
        {
            wp_enqueue_script(
                'operaton-dmn-gravity-integration',
                $this->assets->get_plugin_url() . 'assets/js/gravity-forms.js',
                array('jquery', 'operaton-dmn-frontend'),
                $this->assets->get_version(),
                true
            );

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

    private function ensure_form_config_localized($form, $config)
    {
        $form_id = $form['id'];
        $config_var_name = 'operaton_config_' . $form_id;

        // STEP 1 PART 2: Enhanced duplicate prevention for Gravity Forms localization
        $localization_key = $form_id . '_' . ($config->id ?? 0);

        // Check 1: Our internal tracking (most reliable)
        if (isset(self::$localized_form_configs[$localization_key]))
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('STEP 1 PART 2: Gravity Forms config already localized (internal check) - Form: ' . $form_id);
            }
            return;
        }

        // Check 2: Check if the window object already exists (browser-side check)
        $check_script = "
        <script type=\"text/javascript\">
        (function() {
            if (typeof window.{$config_var_name} !== 'undefined') {
                if (window.console && window.console.log) {
                    console.log('STEP 1 PART 2: Config already exists for form {$form_id}, skipping duplicate');
                }
                return;
            }
        ";

        // Check 3: Prevent rapid-fire attempts (timing protection)
        if (isset(self::$localization_timestamps[$form_id]))
        {
            $last_attempt = self::$localization_timestamps[$form_id];
            if ((time() - $last_attempt) < 3)
            { // Prevent within 3 seconds
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('STEP 1 PART 2: Preventing rapid Gravity Forms localization - Form: ' . $form_id);
                }
                return;
            }
        }

        // Mark attempt
        self::$localization_timestamps[$form_id] = time();

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('STEP 1 PART 2: Gravity Forms localizing config for form ' . $form_id);
        }

        // Your existing field mappings logic (keep unchanged)
        $field_mappings = $this->get_field_mappings($config);
        $result_mappings = $this->get_result_mappings($config);

        // Your existing result field extraction logic (keep unchanged)
        $result_field_ids = array();

        // Primary: Extract from result_mappings (most reliable)
        if (is_array($result_mappings))
        {
            foreach ($result_mappings as $dmn_variable => $mapping)
            {
                if (isset($mapping['field_id']) && is_numeric($mapping['field_id']))
                {
                    $field_id = intval($mapping['field_id']);
                    if (!in_array($field_id, $result_field_ids))
                    {
                        $result_field_ids[] = $field_id;
                    }
                }
            }
        }

        // Secondary: ONLY look for clear result variables in field_mappings
        if (is_array($field_mappings))
        {
            foreach ($field_mappings as $dmn_variable => $mapping)
            {
                if (isset($mapping['field_id']) && is_numeric($mapping['field_id']))
                {
                    $field_id = intval($mapping['field_id']);
                    if (strpos($dmn_variable, 'aanmerking') === 0)
                    {
                        if (!in_array($field_id, $result_field_ids))
                        {
                            $result_field_ids[] = $field_id;
                        }
                    }
                }
            }
        }

        // For your specific form 8, ensure correct result fields
        if ($form_id == 8)
        {
            $correct_result_fields = array(35, 36); // aanmerkingHeusdenPas, aanmerkingKindPakket
            $result_field_ids = array_intersect($result_field_ids, $correct_result_fields);
            foreach ($correct_result_fields as $field_id)
            {
                if (!in_array($field_id, $result_field_ids))
                {
                    $result_field_ids[] = $field_id;
                }
            }
        }

        sort($result_field_ids);

        // Your existing config building (keep unchanged)
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
            'result_field_ids' => $result_field_ids,
            'clear_results_on_change' => true
        );

        // STEP 1 PART 2: Output the config with duplicate protection
        echo $check_script; // Start of the check script from above

        // The actual localization (only runs if not already exists)
        echo 'window.' . $config_var_name . ' = ' . wp_json_encode($js_config) . ';';
        echo 'if (window.console && window.console.log) {';
        echo '  console.log("STEP 1 PART 2: Gravity Forms config localized for form ' . $form_id . '", window.' . $config_var_name . ');';
        echo '}';

        // Close the check script
        echo '
            })();
        </script>';

        // Mark as successfully localized in our tracking
        self::$localized_form_configs[$localization_key] = array(
            'timestamp' => time(),
            'form_id' => $form_id,
            'config_id' => $config->id ?? 0,
            'config_var_name' => $config_var_name
        );

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('STEP 1 PART 2: Gravity Forms config successfully localized for form ' . $form_id);
        }
    }

    /**
     * SIMPLIFIED: Field mappings processing
     */
    private function get_field_mappings($config)
    {
        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE)
        {
            $field_mappings = array();
        }
        return $field_mappings;
    }

    /**
     * SIMPLIFIED: Result mappings processing
     */
    private function get_result_mappings($config)
    {
        $result_mappings = json_decode($config->result_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE)
        {
            $result_mappings = array();
        }
        return $result_mappings;
    }

    // =============================================================================
    // SIMPLIFIED RADIO SYNC FUNCTIONALITY
    // =============================================================================

    /**
     * SIMPLIFIED: Radio sync hooks
     */
    public function add_radio_sync_hooks()
    {
        if (!$this->check_gravity_forms_availability())
        {
            return;
        }

        add_action('gform_pre_render', array($this, 'maybe_initialize_radio_sync'), 5, 1);
        add_action('gform_pre_validation', array($this, 'maybe_initialize_radio_sync'), 5, 1);
        add_action('gform_pre_submission_filter', array($this, 'maybe_initialize_radio_sync'), 5, 1);

        if (is_admin())
        {
            add_action('gform_editor_js', array($this, 'add_radio_sync_editor_support'));
        }
    }

    /**
     * SIMPLIFIED: Radio sync initialization
     */
    public function maybe_initialize_radio_sync($form)
    {
        if (!is_array($form) || !isset($form['id']))
        {
            return $form;
        }

        $form_id = $form['id'];

        if ($this->form_needs_radio_sync($form_id))
        {
            $this->initialize_radio_sync($form_id);
        }

        return $form;
    }

    /**
     * SIMPLIFIED: Radio sync initialization
     */
    private function initialize_radio_sync($form_id)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Radio sync for form: ' . $form_id);
        }

        $this->assets->enqueue_radio_sync_assets($form_id);

        add_action('wp_footer', function () use ($form_id)
        {
            $this->add_radio_sync_initialization($form_id);
        }, 15);
    }

    // =============================================================================
    // SIMPLIFIED FORM CONFIGURATION AND CACHING
    // =============================================================================

    /**
     * SIMPLIFIED: Get form configuration with basic caching
     */
    private function get_form_config($form_id)
    {
        $cache_key = 'config_' . $form_id;

        if (isset(self::$form_config_cache[$cache_key]))
        {
            return self::$form_config_cache[$cache_key];
        }

        $config = $this->database->get_config_by_form_id($form_id);
        self::$form_config_cache[$cache_key] = $config;

        return $config;
    }

    /**
     * SIMPLIFIED: Get form fields with basic caching
     */
    public function get_form_fields($form_id)
    {
        if (!$this->check_gravity_forms_availability())
        {
            return array();
        }

        $cache_key = 'fields_' . $form_id;

        if (isset(self::$form_fields_cache[$cache_key]))
        {
            return self::$form_fields_cache[$cache_key];
        }

        try
        {
            $form = GFAPI::get_form($form_id);

            if (!$form)
            {
                self::$form_fields_cache[$cache_key] = array();
                return array();
            }

            $fields = array();

            foreach ($form['fields'] as $field)
            {
                $field_data = array(
                    'id' => $field->id,
                    'label' => $field->label,
                    'type' => $field->type,
                    'adminLabel' => $field->adminLabel ?? '',
                    'isRequired' => $field->isRequired ?? false,
                    'cssClass' => $field->cssClass ?? '',
                    'size' => $field->size ?? 'medium'
                );

                if (in_array($field->type, array('select', 'radio', 'checkbox')) && !empty($field->choices))
                {
                    $field_data['choices'] = array();
                    foreach ($field->choices as $choice)
                    {
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
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN Gravity Forms: Error getting form fields: ' . $e->getMessage());
            self::$form_fields_cache[$cache_key] = array();
            return array();
        }
    }

    // =============================================================================
    // SIMPLIFIED FORM BUTTON AND UI INTEGRATION
    // =============================================================================

    /**
     * SIMPLIFIED: Add DMN evaluation button
     */
    public function add_evaluate_button($button, $form)
    {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX))
        {
            return $button;
        }

        $form_id = $form['id'];
        $config = $this->get_form_config($form_id);

        if (!$config)
        {
            return $button;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Adding evaluate button for form ' . $form_id);
        }

        $evaluate_button = sprintf(
            '<input type="button" id="operaton-evaluate-%1$d" value="%2$s" class="gform_button gform-theme-button operaton-evaluate-btn" data-form-id="%1$d" data-config-id="%3$d" style="display: none;">',
            $form_id,
            esc_attr($config->button_text),
            $config->id
        );

        $decision_flow_container = '';
        if (isset($config->show_decision_flow) && $config->show_decision_flow)
        {
            $decision_flow_container = sprintf(
                '<div id="decision-flow-summary-%d" class="decision-flow-summary" style="display: none;"></div>',
                $form_id
            );
        }

        return $button . $evaluate_button . $decision_flow_container;
    }

    /**
     * SIMPLIFIED: Generate form control JavaScript
     */
    private function generate_form_control_script($form_id, $target_page, $show_decision_flow, $use_process, $config)
    {
        return sprintf(
            '
/* SIMPLIFIED Form Control Script for Form %d */
(function($) {
    "use strict";

    var formId = %d;
    var targetPage = %d;
    var showDecisionFlow = %s;
    var useProcess = %s;

    console.log("SIMPLIFIED Form control for form " + formId);

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

    function handleButtonPlacement() {
        var currentPage = getCurrentPage();
        console.log("Form " + formId + " - Current page:", currentPage, "Target:", targetPage);

        if (currentPage === targetPage) {
            console.log("Showing evaluate button for form " + formId);
            if (typeof window.showEvaluateButton === "function") {
                window.showEvaluateButton(formId);
            } else {
                var $button = $("#operaton-evaluate-" + formId);
                $button.show();
            }
        } else if (currentPage === (targetPage + 1) && showDecisionFlow && useProcess) {
            console.log("Showing decision flow for form " + formId);
            if (typeof window.showDecisionFlowSummary === "function") {
                window.showDecisionFlowSummary(formId);
            }
        } else {
            console.log("Hiding elements for form " + formId);
            if (typeof window.hideAllElements === "function") {
                window.hideAllElements(formId);
            } else {
                var $button = $("#operaton-evaluate-" + formId);
                var $summary = $("#decision-flow-summary-" + formId);
                $button.hide();
                $summary.hide();
            }
        }
    }

    // Initialize immediately
    $(document).ready(function() {
        handleButtonPlacement();
    });

    // Hook into Gravity Forms events
    if (typeof gform !== "undefined" && gform.addAction) {
        gform.addAction("gform_page_loaded", function(form_id, current_page) {
            if (form_id == formId) {
                setTimeout(handleButtonPlacement, 200);
            }
        });
    }

    // URL change detection
    var currentUrl = window.location.href;
    setInterval(function() {
        if (window.location.href !== currentUrl) {
            currentUrl = window.location.href;
            setTimeout(handleButtonPlacement, 300);
        }
    }, 500);

    // Final fallback
    setTimeout(function() {
        var currentPage = getCurrentPage();
        var $button = $("#operaton-evaluate-" + formId);

        if (currentPage === targetPage && !$button.is(":visible")) {
            console.log("Fallback - showing button for form " + formId);
            handleButtonPlacement();
        }
    }, 2000);

})(jQuery);',
            $form_id,
            $form_id,
            $target_page,
            $show_decision_flow ? 'true' : 'false',
            $use_process ? 'true' : 'false'
        );
    }

    // =============================================================================
    // KEEP: ESSENTIAL UTILITY METHODS
    // =============================================================================

    public function form_needs_radio_sync($form_id)
    {
        if (!$this->check_gravity_forms_availability())
        {
            return false;
        }

        // Form 8 specifically needs radio sync
        if ($form_id == 8)
        {
            return true;
        }

        try
        {
            $form = GFAPI::get_form($form_id);

            if (!$form || !isset($form['fields']))
            {
                return false;
            }

            foreach ($form['fields'] as $field)
            {
                if ($field->type === 'html' && isset($field->content))
                {
                    $content = $field->content;

                    if (
                        strpos($content, 'type="radio"') !== false &&
                        (strpos($content, 'aanvrager') !== false ||
                            strpos($content, 'name="input_') !== false)
                    )
                    {
                        return true;
                    }
                }
            }
        }
        catch (Exception $e)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Gravity Forms: Error checking form for radio sync: ' . $e->getMessage());
            }
        }

        return false;
    }

    private function add_radio_sync_initialization($form_id)
    {
        $mappings = $this->extract_radio_sync_mappings($form_id);

        if (empty($mappings))
        {
            return;
        }

?>
        <script type="text/javascript">
            /* Radio Sync for Form <?php echo esc_js($form_id); ?> */
            (function($) {
                'use strict';

                if (typeof window.OperatonRadioSync !== 'undefined') {
                    window.OperatonRadioSync.fieldMappings = <?php echo wp_json_encode($mappings); ?>;

                    $(document).ready(function() {
                        setTimeout(function() {
                            if (window.OperatonRadioSync.forceSyncAll) {
                                window.OperatonRadioSync.forceSyncAll();
                                console.log('Radio sync for form <?php echo esc_js($form_id); ?>');
                            }
                        }, 500);
                    });
                } else {
                    console.warn('OperatonRadioSync not available for form <?php echo esc_js($form_id); ?>');
                }

            })(jQuery);
        </script>
<?php
    }

    public function extract_radio_sync_mappings($form_id)
    {
        $mappings = array();
        $config = $this->get_form_config($form_id);

        if (!$config)
        {
            $mappings = $this->get_default_radio_mappings($form_id);
        }
        else
        {
            $field_mappings = $this->get_field_mappings($config);

            if (is_array($field_mappings))
            {
                foreach ($field_mappings as $dmn_variable => $mapping)
                {
                    if (strpos($dmn_variable, 'aanvrager') === 0 && isset($mapping['field_id']))
                    {
                        $mappings[$dmn_variable] = 'input_' . $form_id . '_' . $mapping['field_id'];
                    }
                }
            }
        }

        return $mappings;
    }

    private function get_default_radio_mappings($form_id)
    {
        $default_mappings = array();

        if ($form_id == 8)
        {
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

    private function count_form_pages($form)
    {
        $total_pages = 1;

        if (isset($form['fields']) && is_array($form['fields']))
        {
            foreach ($form['fields'] as $field)
            {
                if (isset($field->type) && $field->type === 'page')
                {
                    $total_pages++;
                }
            }
        }

        return $total_pages;
    }

    private function has_dmn_enabled_forms_on_page()
    {
        // Check for shortcodes in post content
        global $post;
        if ($post && has_shortcode($post->post_content, 'gravityform'))
        {
            $form_ids = $this->extract_form_ids_from_shortcodes($post->post_content);
            return $this->any_forms_have_dmn_config($form_ids);
        }

        // Check for Gravity Forms blocks
        if ($post && has_block('gravityforms/form', $post))
        {
            $form_ids = $this->extract_form_ids_from_blocks($post);
            return $this->any_forms_have_dmn_config($form_ids);
        }

        // Check for preview page
        if (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview')
        {
            $form_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($form_id > 0)
            {
                return $this->form_has_dmn_config($form_id);
            }
        }

        return false;
    }

    private function any_forms_have_dmn_config($form_ids)
    {
        foreach ($form_ids as $form_id)
        {
            if ($this->form_has_dmn_config($form_id))
            {
                return true;
            }
        }
        return false;
    }

    private function form_has_dmn_config($form_id)
    {
        return $this->get_form_config($form_id) !== null;
    }

    private function extract_form_ids_from_shortcodes($content)
    {
        $form_ids = array();
        $pattern = '/\[gravityform[^\]]*id=["\'](\d+)["\'][^\]]*\]/';

        if (preg_match_all($pattern, $content, $matches))
        {
            $form_ids = array_map('intval', $matches[1]);
        }

        return array_unique($form_ids);
    }

    private function extract_form_ids_from_blocks($post)
    {
        $form_ids = array();

        if (function_exists('parse_blocks'))
        {
            $blocks = parse_blocks($post->post_content);
            $form_ids = $this->find_gravity_form_ids_in_blocks($blocks);
        }

        return array_unique($form_ids);
    }

    private function find_gravity_form_ids_in_blocks($blocks)
    {
        $form_ids = array();

        foreach ($blocks as $block)
        {
            if ($block['blockName'] === 'gravityforms/form')
            {
                if (isset($block['attrs']['formId']))
                {
                    $form_ids[] = intval($block['attrs']['formId']);
                }
            }

            if (!empty($block['innerBlocks']))
            {
                $inner_ids = $this->find_gravity_form_ids_in_blocks($block['innerBlocks']);
                $form_ids = array_merge($form_ids, $inner_ids);
            }
        }

        return $form_ids;
    }

    // =============================================================================
    // SIMPLIFIED CACHE MANAGEMENT
    // =============================================================================

    /**
     * Clear specific form cache
     */
    public function clear_form_cache($form_id = null)
    {
        if ($form_id)
        {
            $keys_to_remove = array();
            foreach (self::$form_config_cache as $key => $value)
            {
                if (strpos($key, '_' . $form_id) !== false)
                {
                    $keys_to_remove[] = $key;
                }
            }

            foreach ($keys_to_remove as $key)
            {
                unset(self::$form_config_cache[$key]);
                unset(self::$form_fields_cache[$key]);
            }

            // STEP 1 PART 2: Clear Gravity Forms localization tracking
            $this->clear_gravity_forms_localization_cache($form_id);

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN: Cleared cache for form ' . $form_id);
            }
        }
    }

    public function clear_gravity_forms_localization_cache($form_id = null)
    {
        if ($form_id)
        {
            // Clear specific form
            foreach (self::$localized_form_configs as $key => $data)
            {
                if (strpos($key, $form_id . '_') === 0)
                {
                    unset(self::$localized_form_configs[$key]);
                }
            }
            unset(self::$localization_timestamps[$form_id]);

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('STEP 1 PART 2: Cleared Gravity Forms localization cache for form: ' . $form_id);
            }
        }
        else
        {
            // Clear all
            self::$localized_form_configs = array();
            self::$localization_timestamps = array();

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('STEP 1 PART 2: Cleared all Gravity Forms localization cache');
            }
        }
    }

    /**
     * Handle form save
     */
    public function clear_form_cache_on_save($form, $is_new)
    {
        if (isset($form['id']))
        {
            $this->clear_form_cache($form['id']);
        }
    }

    /**
     * Clear all caches
     */
    public function clear_all_caches()
    {
        self::$form_config_cache = array();
        self::$form_fields_cache = array();

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN: All caches cleared');
        }
    }

    // =============================================================================
    // REMAINING STANDARD METHODS (keeping for compatibility)
    // =============================================================================

    public function add_gravity_forms_hooks()
    {
        // Implementation can remain the same or be simplified
    }

    public function init_admin_integration()
    {
        // Keep existing implementation
    }

    public function validate_dmn_fields($validation_result)
    {
        return $validation_result;
    }

    public function handle_post_submission($entry, $form)
    {
        // Keep existing implementation
    }

    public function ajax_evaluate_form()
    {
        // Keep existing implementation
    }

    public function add_editor_script()
    {
        // Keep existing implementation
    }

    public function add_field_advanced_settings($position, $form_id)
    {
        // Keep existing implementation
    }

    public function add_radio_sync_editor_support()
    {
        // Keep existing implementation
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
        return $this->get_form_config($form_id);
    }

    public function get_available_forms()
    {
        if (!$this->check_gravity_forms_availability())
        {
            return array();
        }

        try
        {
            $forms = GFAPI::get_forms();

            foreach ($forms as &$form)
            {
                if (isset($form['fields']))
                {
                    $form['field_list'] = $this->get_form_fields($form['id']);
                }
            }

            return $forms;
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN Gravity Forms: Error getting forms: ' . $e->getMessage());
            return array();
        }
    }

    public function form_exists($form_id)
    {
        if (!$this->check_gravity_forms_availability())
        {
            return false;
        }

        try
        {
            $form = GFAPI::get_form($form_id);
            return !empty($form);
        }
        catch (Exception $e)
        {
            return false;
        }
    }

    public function get_form_title($form_id)
    {
        if (!$this->check_gravity_forms_availability())
        {
            return '';
        }

        try
        {
            $form = GFAPI::get_form($form_id);
            return !empty($form['title']) ? $form['title'] : '';
        }
        catch (Exception $e)
        {
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
            'simplified' => true
        );

        if ($status['gravity_forms_available'])
        {
            try
            {
                $forms = GFAPI::get_forms();
                $status['total_forms'] = count($forms);

                $forms_with_config = 0;
                foreach ($forms as $form)
                {
                    if ($this->form_has_dmn_config($form['id']))
                    {
                        $forms_with_config++;
                    }
                }
                $status['forms_with_dmn_config'] = $forms_with_config;
            }
            catch (Exception $e)
            {
                $status['error'] = $e->getMessage();
            }

            if (class_exists('GFCommon'))
            {
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
        return $this->get_form_config($form_id);
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
