<?php

/**
 * Gravity Forms Integration for Operaton DMN Plugin
 *
 * Handles all Gravity Forms integration including form detection, script loading,
 * button placement, field validation, and form submission integration.
 * Follows WordPress and Gravity Forms best practices.
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
     * Core plugin instance reference
     * Provides access to main plugin functionality and data
     *
     * @var OperatonDMNEvaluator
     * @since 1.0.0
     */
    private $core;

    /**
     * Assets manager instance
     * Handles CSS and JavaScript loading for Gravity Forms integration
     *
     * @var Operaton_DMN_Assets
     * @since 1.0.0
     */
    private $assets;

    /**
     * Database manager instance
     * Handles configuration retrieval for form integration
     *
     * @var Operaton_DMN_Database
     * @since 1.0.0
     */
    private $database;

    /**
     * Cached form configurations to avoid repeated database queries
     * Stores form configurations during request lifecycle
     *
     * @var array
     * @since 1.0.0
     */
    private $form_configs_cache = array();

    /**
     * Flag to track if Gravity Forms is available
     * Prevents repeated class_exists() checks
     *
     * @var bool|null
     * @since 1.0.0
     */
    private $gravity_forms_available = null;

    /**
     * Constructor for Gravity Forms integration
     * Initializes integration with required dependencies
     *
     * @param OperatonDMNEvaluator $core Core plugin instance
     * @param Operaton_DMN_Assets $assets Assets manager instance
     * @param Operaton_DMN_Database $database Database manager instance
     * @since 1.0.0
     */
    public function __construct($core, $assets, $database)
    {
        $this->core = $core;
        $this->assets = $assets;
        $this->database = $database;

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Integration manager initialized');
        }

        $this->init_hooks();
    }

    /**
     * Initialize WordPress and Gravity Forms hooks
     * Sets up all integration points with proper priority
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // Early hook to check Gravity Forms availability
        add_action('init', array($this, 'check_gravity_forms_availability'), 1);

        // Only add hooks if Gravity Forms is available
        add_action('init', array($this, 'init_gravity_forms_integration'), 5);

        // Frontend hooks for form detection and asset loading
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_gravity_forms_assets'), 15);

        // Gravity Forms specific hooks (conditional)
        add_action('plugins_loaded', array($this, 'add_gravity_forms_hooks'), 20);

        // Admin hooks for form field detection
        add_action('admin_init', array($this, 'init_admin_integration'));
    }

    // =============================================================================
    // GRAVITY FORMS AVAILABILITY AND SETUP
    // =============================================================================

    /**
     * Check if Gravity Forms is available and cache the result
     * Performs early check to determine if integration should be activated
     *
     * @since 1.0.0
     * @return bool True if Gravity Forms is available
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
     * Initialize Gravity Forms integration if available
     * Sets up integration hooks only when Gravity Forms is active
     *
     * @since 1.0.0
     */
    public function init_gravity_forms_integration()
    {
        if (!$this->check_gravity_forms_availability())
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Gravity Forms: Skipping integration - Gravity Forms not available');
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Initializing integration hooks');
        }

        // Form rendering hooks - CORRECTED PRIORITY AND FILTERS
        add_filter('gform_submit_button', array($this, 'add_evaluate_button'), 10, 2);
        add_action('gform_enqueue_scripts', array($this, 'enqueue_gravity_scripts'), 10, 2);

        // CRITICAL FIX: Ensure assets are loaded when forms are present
        add_action('gform_pre_render', array($this, 'ensure_assets_loaded'), 5, 1);
        add_action('gform_pre_validation', array($this, 'ensure_assets_loaded'), 5, 1);
        add_action('gform_pre_submission_filter', array($this, 'ensure_assets_loaded'), 5, 1);

        // Form editor integration (admin only)
        if (is_admin())
        {
            add_action('gform_editor_js', array($this, 'add_editor_script'));
            add_action('gform_field_advanced_settings', array($this, 'add_field_advanced_settings'), 10, 2);
        }

        // Form validation and submission hooks
        add_filter('gform_validation', array($this, 'validate_dmn_fields'), 10, 1);
        add_action('gform_after_submission', array($this, 'handle_post_submission'), 10, 2);

        $this->add_radio_sync_hooks();
    }

    /**
     * CRITICAL FIX: Ensure frontend assets are loaded when forms are rendered
     * This fixes the operaton_ajax not being available issue
     *
     * @param array $form Gravity Forms form array
     * @since 1.0.0
     */
    public function ensure_assets_loaded($form)
    {
        if (!is_admin())
        {
            // Check if this form has DMN configuration
            $config = $this->get_form_config($form['id']);
            if ($config)
            {
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('Operaton DMN Gravity Forms: Ensuring assets for DMN form: ' . $form['id']);
                }

                // Force load frontend assets
                $this->assets->enqueue_frontend_assets();

                // Load form-specific configuration
                $this->enqueue_gravity_scripts($form, false);
            }
        }

        return $form;
    }

    /**
     * Add Gravity Forms specific hooks after plugins are loaded
     * Ensures all Gravity Forms functionality is available
     *
     * @since 1.0.0
     */
    public function add_gravity_forms_hooks()
    {
        if (!$this->check_gravity_forms_availability())
        {
            return;
        }

        // Form pre-render hooks for dynamic field population
        add_filter('gform_pre_render', array($this, 'pre_render_form'), 10, 3);
        add_filter('gform_pre_validation', array($this, 'pre_render_form'), 10, 3);
        add_filter('gform_pre_submission_filter', array($this, 'pre_render_form'), 10, 3);
        add_filter('gform_admin_pre_render', array($this, 'pre_render_form'), 10, 3);

        // AJAX submission handling
        add_action('wp_ajax_gf_operaton_evaluate', array($this, 'ajax_evaluate_form'));
        add_action('wp_ajax_nopriv_gf_operaton_evaluate', array($this, 'ajax_evaluate_form'));
    }

    /**
     * Initialize admin-specific integration features
     * Sets up admin interface enhancements for form configuration
     *
     * @since 1.0.0
     */
    public function init_admin_integration()
    {
        if (!$this->check_gravity_forms_availability() || !is_admin())
        {
            return;
        }

        // Add custom field types or modifications if needed
        add_filter('gform_add_field_buttons', array($this, 'add_custom_field_buttons'), 10, 1);

        // Form settings integration
        add_filter('gform_form_settings', array($this, 'add_form_settings'), 10, 2);
        add_filter('gform_pre_form_settings_save', array($this, 'save_form_settings'), 10, 1);
    }

    // =============================================================================
    // FORM DETECTION AND ASSET LOADING
    // =============================================================================

    /**
     * Detect Gravity Forms on current page and enqueue assets if needed
     * Checks for forms and loads DMN evaluation assets when required
     *
     * @since 1.0.0
     */
    public function maybe_enqueue_gravity_forms_assets()
    {
        if (is_admin() || !$this->check_gravity_forms_availability())
        {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Checking if assets should be loaded');
        }

        // Check if we're on a page with DMN-enabled Gravity Forms
        if ($this->has_dmn_enabled_forms_on_page())
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Gravity Forms: DMN-enabled forms detected, loading assets');
            }

            // Force load frontend assets
            $this->assets->enqueue_frontend_assets();

            // Load Gravity Forms specific scripts
            $this->enqueue_gravity_forms_scripts();
        }
    }

    /**
     * Add these methods to your class-operaton-dmn-gravity-forms.php file
     * Radio Button Synchronization Integration
     */

    /**
     * Add this method to your Operaton_DMN_Gravity_Forms class
     *
     * Detect forms that need radio button synchronization
     */
    public function detect_radio_sync_forms()
    {
        if (!$this->check_gravity_forms_availability())
        {
            return array();
        }

        $forms_with_radio_sync = array();

        try
        {
            $forms = GFAPI::get_forms();

            foreach ($forms as $form)
            {
                if ($this->form_needs_radio_sync($form['id']))
                {
                    $forms_with_radio_sync[] = $form['id'];
                }
            }
        }
        catch (Exception $e)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Gravity Forms: Error detecting radio sync forms: ' . $e->getMessage());
            }
        }

        return $forms_with_radio_sync;
    }

    /**
     * Check if a specific form needs radio button synchronization
     */
    public function form_needs_radio_sync($form_id)
    {
        if (!$this->check_gravity_forms_availability())
        {
            return false;
        }

        try
        {
            $form = GFAPI::get_form($form_id);

            if (!$form || !isset($form['fields']))
            {
                return false;
            }

            // Check for HTML fields with radio buttons that need sync
            foreach ($form['fields'] as $field)
            {
                if ($field->type === 'html' && isset($field->content))
                {
                    $content = $field->content;

                    // Look for radio buttons with specific naming patterns
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

    /**
     * Extract radio sync mappings from form configuration
     */
    public function extract_radio_sync_mappings($form_id)
    {
        $mappings = array();

        // Get DMN configuration for this form
        $config = $this->get_form_config($form_id);

        if (!$config)
        {
            return $this->get_default_radio_mappings($form_id);
        }

        // Extract mappings from field_mappings JSON
        $field_mappings = json_decode($config->field_mappings, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($field_mappings))
        {
            foreach ($field_mappings as $dmn_variable => $mapping)
            {
                // Check if this looks like a radio sync variable
                if (strpos($dmn_variable, 'aanvrager') === 0 && isset($mapping['field_id']))
                {
                    $mappings[$dmn_variable] = 'input_' . $form_id . '_' . $mapping['field_id'];
                }
            }
        }

        return $mappings;
    }

    /**
     * Get default radio sync mappings for known forms
     */
    private function get_default_radio_mappings($form_id)
    {
        $default_mappings = array();

        // Form 8 specific mappings
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

    /**
     * Initialize radio sync for a specific form
     */
    public function initialize_radio_sync($form_id)
    {
        if (!$this->form_needs_radio_sync($form_id))
        {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Initializing radio sync for form: ' . $form_id);
        }

        // Enqueue radio sync assets
        $this->assets->enqueue_radio_sync_assets($form_id);

        // Add form-specific initialization
        add_action('wp_footer', function () use ($form_id)
        {
            $this->add_radio_sync_initialization($form_id);
        }, 15);
    }

    /**
     * Add radio sync initialization script to footer
     */
    private function add_radio_sync_initialization($form_id)
    {
        $mappings = $this->extract_radio_sync_mappings($form_id);

        if (empty($mappings))
        {
            return;
        }

?>
        <script type="text/javascript">
            /* Operaton DMN Radio Sync Initialization for Form <?php echo esc_js($form_id); ?> */
            (function($) {
                'use strict';

                if (typeof window.OperatonRadioSync !== 'undefined') {
                    // Update field mappings for this specific form
                    window.OperatonRadioSync.fieldMappings = <?php echo wp_json_encode($mappings); ?>;

                    // Force re-initialization with new mappings
                    $(document).ready(function() {
                        setTimeout(function() {
                            if (window.OperatonRadioSync.forceSyncAll) {
                                window.OperatonRadioSync.forceSyncAll();

                                console.log('✅ Radio sync initialized for form <?php echo esc_js($form_id); ?> with mappings:', window.OperatonRadioSync.fieldMappings);
                            }
                        }, 1000);
                    });
                } else {
                    console.warn('⚠️ OperatonRadioSync not available for form <?php echo esc_js($form_id); ?>');
                }

            })(jQuery);
        </script>
    <?php
    }

    /**
     * Add hook integration for radio sync
     * Add this to your existing init_gravity_forms_integration method
     */
    public function add_radio_sync_hooks()
    {
        if (!$this->check_gravity_forms_availability())
        {
            return;
        }

        // Hook into form rendering to initialize radio sync
        add_action('gform_pre_render', array($this, 'maybe_initialize_radio_sync'), 5, 1);
        add_action('gform_pre_validation', array($this, 'maybe_initialize_radio_sync'), 5, 1);
        add_action('gform_pre_submission_filter', array($this, 'maybe_initialize_radio_sync'), 5, 1);

        // Add admin hooks for form editing
        if (is_admin())
        {
            add_action('gform_editor_js', array($this, 'add_radio_sync_editor_support'));
        }
    }

    /**
     * Maybe initialize radio sync for a form
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
     * Add radio sync support to form editor
     */
    public function add_radio_sync_editor_support()
    {
    ?>
        <script type="text/javascript">
            /* Operaton DMN Radio Sync Editor Support */
            if (typeof fieldSettings !== 'undefined') {
                // Add radio sync setting to HTML fields
                fieldSettings.html += ', .operaton_radio_sync_setting';
            }

            // Add editor enhancement for radio sync detection
            jQuery(document).ready(function($) {
                // Detect HTML fields with radio buttons
                $('.gfield_html textarea').on('input', function() {
                    var content = $(this).val();
                    var $field = $(this).closest('.gfield');

                    if (content.indexOf('type="radio"') !== -1 &&
                        content.indexOf('aanvrager') !== -1) {

                        if (!$field.find('.operaton-radio-sync-notice').length) {
                            $field.append(
                                '<div class="operaton-radio-sync-notice" style="background: #e8f4f8; padding: 8px; margin-top: 5px; border-radius: 4px; font-size: 12px;">' +
                                '🔄 <strong>Operaton DMN:</strong> Radio button synchronization will be automatically enabled for this field.' +
                                '</div>'
                            );
                        }
                    } else {
                        $field.find('.operaton-radio-sync-notice').remove();
                    }
                });
            });
        </script>
    <?php
    }

    /**
     * Get radio sync status for debugging
     */
    public function get_radio_sync_status()
    {
        $status = array(
            'available_forms' => array(),
            'enabled_forms' => array(),
            'mappings' => array(),
            'assets_loaded' => array()
        );

        try
        {
            $forms = GFAPI::get_forms();

            foreach ($forms as $form)
            {
                $form_id = $form['id'];
                $status['available_forms'][] = $form_id;

                if ($this->form_needs_radio_sync($form_id))
                {
                    $status['enabled_forms'][] = $form_id;
                    $status['mappings'][$form_id] = $this->extract_radio_sync_mappings($form_id);
                }
            }
        }
        catch (Exception $e)
        {
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    /**
     * Clean HTML content for forms (remove large script blocks)
     * This can be used to automatically clean up forms with large sync scripts
     */
    public function clean_form_html_blocks($form_id)
    {
        if (!$this->check_gravity_forms_availability())
        {
            return false;
        }

        try
        {
            $form = GFAPI::get_form($form_id);

            if (!$form || !isset($form['fields']))
            {
                return false;
            }

            $modified = false;

            foreach ($form['fields'] as &$field)
            {
                if ($field->type === 'html' && isset($field->content))
                {
                    $original_content = $field->content;

                    // Check if this HTML field contains a large radio sync script
                    if (
                        strpos($original_content, 'fieldMapping') !== false &&
                        strpos($original_content, 'syncRadioToHidden') !== false &&
                        strlen($original_content) > 5000
                    )
                    {

                        // Replace with cleaned version
                        $field->content = $this->get_cleaned_html_content();
                        $modified = true;

                        if (defined('WP_DEBUG') && WP_DEBUG)
                        {
                            error_log('Operaton DMN: Cleaned large HTML block in form ' . $form_id . ', field ' . $field->id);
                        }
                    }
                }
            }

            if ($modified)
            {
                $result = GFAPI::update_form($form);

                if (is_wp_error($result))
                {
                    if (defined('WP_DEBUG') && WP_DEBUG)
                    {
                        error_log('Operaton DMN: Error updating form: ' . $result->get_error_message());
                    }
                    return false;
                }

                return true;
            }
        }
        catch (Exception $e)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN: Error cleaning form HTML: ' . $e->getMessage());
            }
        }

        return false;
    }

    /**
     * Get cleaned HTML content to replace large script blocks
     */
    private function get_cleaned_html_content()
    {
        return '<!-- Operaton DMN: Radio sync handled by plugin -->
<style>
/* Form styling preserved */
.gform_wrapper {
    background-color: white !important;
    padding: 30px !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
    margin: 20px 0 !important;
}

/* Table styling */
.gf-table-header {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 20px;
    align-items: center;
    padding: 15px;
    background-color: #f2f2f2;
    border-bottom: 2px solid #ddd;
    font-weight: bold;
    margin-bottom: 10px;
    border-radius: 4px;
}

.gf-table-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 20px;
    align-items: center;
    padding: 15px 15px 5px 15px;
    border-bottom: 1px solid #eee;
    min-height: 60px;
}

.gf-table-row:hover {
    background-color: #f8f9fa;
}

.gf-table-row input[type="radio"] {
    margin-right: 5px;
    transform: scale(1.2);
}

.gf-table-row h3.field-label {
    font-size: 16px !important;
    font-weight: 500 !important;
    color: #333 !important;
    line-height: 1.4 !important;
    margin: 0 !important;
}

.gf-table-row label {
    cursor: pointer;
    font-weight: normal !important;
    font-size: 14px !important;
}
</style>

<script type="text/javascript">
// Minimal form initialization - radio sync handled by plugin
document.addEventListener("DOMContentLoaded", function() {
    var form = document.querySelector("#gform_8");
    if (form) {
        form.setAttribute("data-operaton-radio-sync", "true");
        console.log("✅ Form marked for Operaton DMN radio synchronization");
    }
});
</script>';
    }

    /**
     * Check if current page has Gravity Forms with DMN configurations
     * Optimized detection that checks for actual DMN-enabled forms
     *
     * @since 1.0.0
     * @return bool True if page has DMN-enabled Gravity Forms
     */
    private function has_dmn_enabled_forms_on_page()
    {
        // Check for shortcodes in post content
        global $post;
        if ($post && has_shortcode($post->post_content, 'gravityform'))
        {
            $form_ids = $this->extract_form_ids_from_shortcodes($post->post_content);
            return $this->any_forms_have_dmn_config($form_ids);
        }

        // Check for Gravity Forms blocks (Gutenberg)
        if ($post && has_block('gravityforms/form', $post))
        {
            $form_ids = $this->extract_form_ids_from_blocks($post);
            return $this->any_forms_have_dmn_config($form_ids);
        }

        // Check if we're on a Gravity Forms preview page
        if (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview')
        {
            $form_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($form_id > 0)
            {
                return $this->form_has_dmn_config($form_id);
            }
        }

        // Allow other plugins/themes to indicate DMN-enabled GF presence
        return apply_filters('operaton_dmn_has_gravity_forms', false);
    }

    /**
     * Extract form IDs from gravityform shortcodes
     * Parses shortcode attributes to find form IDs
     *
     * @param string $content Post content to search
     * @since 1.0.0
     * @return array Array of form IDs found
     */
    private function extract_form_ids_from_shortcodes($content)
    {
        $form_ids = array();

        // Pattern to match [gravityform id="X"] shortcodes
        $pattern = '/\[gravityform[^\]]*id=["\'](\d+)["\'][^\]]*\]/';

        if (preg_match_all($pattern, $content, $matches))
        {
            $form_ids = array_map('intval', $matches[1]);
        }

        return array_unique($form_ids);
    }

    /**
     * Extract form IDs from Gravity Forms Gutenberg blocks
     * Parses block content to find form IDs
     *
     * @param WP_Post $post Post object to search
     * @since 1.0.0
     * @return array Array of form IDs found
     */
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

    /**
     * Recursively find Gravity Forms block IDs
     * Searches through nested blocks for gravityforms/form blocks
     *
     * @param array $blocks Array of parsed blocks
     * @since 1.0.0
     * @return array Array of form IDs found
     */
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

            // Check inner blocks recursively
            if (!empty($block['innerBlocks']))
            {
                $inner_ids = $this->find_gravity_form_ids_in_blocks($block['innerBlocks']);
                $form_ids = array_merge($form_ids, $inner_ids);
            }
        }

        return $form_ids;
    }

    /**
     * Check if any of the provided form IDs have DMN configurations
     * Optimized check that uses caching to avoid repeated database queries
     *
     * @param array $form_ids Array of form IDs to check
     * @since 1.0.0
     * @return bool True if any form has DMN configuration
     */
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

    /**
     * Check if a specific form has DMN configuration
     * Uses caching to avoid repeated database queries
     *
     * @param int $form_id Form ID to check
     * @since 1.0.0
     * @return bool True if form has DMN configuration
     */
    private function form_has_dmn_config($form_id)
    {
        if (isset($this->form_configs_cache[$form_id]))
        {
            return $this->form_configs_cache[$form_id] !== null;
        }

        $config = $this->database->get_config_by_form_id($form_id);
        $this->form_configs_cache[$form_id] = $config;

        return $config !== null;
    }

    // =============================================================================
    // SCRIPT AND ASSET ENQUEUING - FIXED SECTION
    // =============================================================================

    /**
     * Enqueue Gravity Forms specific scripts and styles
     * Loads integration-specific JavaScript and CSS
     *
     * @since 1.0.0
     */
    private function enqueue_gravity_forms_scripts()
    {
        // CRITICAL FIX: Ensure frontend assets are loaded first
        $this->assets->enqueue_frontend_assets();

        wp_enqueue_script(
            'operaton-dmn-gravity-integration',
            $this->assets->get_plugin_url() . 'assets/js/gravity-forms.js',
            array('jquery', 'operaton-dmn-frontend'),
            $this->assets->get_version(),
            true
        );

        // Localize with Gravity Forms specific data
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

    /**
     * Enqueue scripts for specific Gravity Form with DMN configuration
     * Called by Gravity Forms when rendering forms
     *
     * @param array $form Gravity Forms form array
     * @param bool $is_ajax Whether the form is being loaded via AJAX
     * @since 1.0.0
     */
    public function enqueue_gravity_scripts($form, $is_ajax)
    {
        $config = $this->get_form_config($form['id']);
        if (!$config)
        {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Enqueuing scripts for form ' . $form['id']);
        }

        // CRITICAL FIX: Ensure frontend assets are loaded first
        $this->assets->enqueue_frontend_assets();

        // Load form-specific assets
        $this->assets->enqueue_gravity_form_assets($form, $config);

        // Add form-specific inline scripts
        $this->add_form_inline_scripts($form, $config);
    }

    /**
     * Add inline scripts for form-specific functionality
     * Generates JavaScript for button placement and form interaction
     *
     * @param array $form Gravity Forms form array
     * @param object $config DMN configuration object
     * @since 1.0.0
     */
    private function add_form_inline_scripts($form, $config)
    {
        $form_id = $form['id'];
        $evaluation_step = isset($config->evaluation_step) ? $config->evaluation_step : 'auto';
        $show_decision_flow = isset($config->show_decision_flow) ? $config->show_decision_flow : false;
        $use_process = isset($config->use_process) ? $config->use_process : false;

        // Calculate target page for button placement
        if ($evaluation_step === 'auto')
        {
            $total_pages = $this->count_form_pages($form);
            $target_page = max(1, $total_pages - 1); // Second to last page
        }
        else
        {
            $target_page = intval($evaluation_step);
        }

        $script = $this->generate_form_control_script(
            $form_id,
            $target_page,
            $show_decision_flow,
            $use_process,
            $config
        );

        wp_add_inline_script('operaton-dmn-gravity-integration', $script);
    }

    // =============================================================================
    // FORM BUTTON AND UI INTEGRATION
    // =============================================================================

    /**
     * Add DMN evaluation button to Gravity Forms
     * Integrates evaluation button into form submission flow
     *
     * @param string $button Existing form submit button HTML
     * @param array $form Gravity Forms form array
     * @since 1.0.0
     * @return string Modified button HTML with evaluation functionality
     */
    public function add_evaluate_button($button, $form)
    {
        // Skip in admin or AJAX contexts
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX))
        {
            return $button;
        }

        $config = $this->get_form_config($form['id']);
        if (!$config)
        {
            return $button;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Adding evaluate button for form ' . $form['id']);
        }

        // Create the evaluate button
        $evaluate_button = sprintf(
            '<input type="button" id="operaton-evaluate-%1$d" value="%2$s" class="gform_button gform-theme-button operaton-evaluate-btn" data-form-id="%1$d" data-config-id="%3$d" style="display: none;">',
            $form['id'],
            esc_attr($config->button_text),
            $config->id
        );

        // Decision flow summary container
        $decision_flow_container = '';
        if (isset($config->show_decision_flow) && $config->show_decision_flow)
        {
            $decision_flow_container = sprintf(
                '<div id="decision-flow-summary-%d" class="decision-flow-summary" style="display: none;"></div>',
                $form['id']
            );
        }

        return $button . $evaluate_button . $decision_flow_container;
    }

    /**
     * Generate JavaScript for form control and button placement
     * Creates dynamic button placement and decision flow loading script
     *
     * @param int $form_id Gravity Forms form ID
     * @param int $target_page Target page for button placement
     * @param bool $show_decision_flow Whether to show decision flow
     * @param bool $use_process Whether using process execution
     * @param object $config Configuration object
     * @since 1.0.0
     * @return string JavaScript code for form control
     */
    private function generate_form_control_script($form_id, $target_page, $show_decision_flow, $use_process, $config)
    {
        return sprintf(
            '
(function($) {
    "use strict";

    var formId = %d;
    var targetPage = %d;
    var showDecisionFlow = %s;
    var useProcess = %s;

    console.log("Operaton DMN: Form control initialized for form " + formId);
    console.log("Target page:", targetPage, "Decision flow:", showDecisionFlow, "Process mode:", useProcess);

    function getCurrentPage() {
        // Method 1: Check URL parameter first (most reliable for multi-page forms)
        var urlParams = new URLSearchParams(window.location.search);
        var gfPage = urlParams.get("gf_page");
        if (gfPage) {
            var pageNum = parseInt(gfPage);
            console.log("Current page from URL:", pageNum);
            return pageNum;
        }

        // Method 2: Check Gravity Forms hidden field
        var pageField = $("#gform_source_page_number_" + formId);
        if (pageField.length && pageField.val()) {
            var pageNum = parseInt(pageField.val());
            console.log("Current page from hidden field:", pageNum);
            return pageNum;
        }

        // Method 3: Check which page container is visible
        var form = $("#gform_" + formId);
        var visiblePageNumber = 1;

        // Look for page break indicators
        form.find(".gf_page_break").each(function(index) {
            if ($(this).is(":visible") || $(this).siblings(":visible").length > 0) {
                visiblePageNumber = index + 2; // Page breaks are 0-indexed, pages are 1-indexed
            }
        });

        // Method 4: Check for page-specific elements
        if (form.find("#decision-flow-summary-" + formId + ":visible").length > 0) {
            console.log("Decision flow summary visible - assuming final page");
            return targetPage + 1; // This should be the decision flow page
        }

        console.log("Current page determined by visibility:", visiblePageNumber);
        return visiblePageNumber;
    }

    function hideAllButtons() {
        $("#operaton-evaluate-" + formId).hide();
        $("#decision-flow-summary-" + formId).hide();
    }

    function handleButtonAndSummary() {
        var currentPage = getCurrentPage();
        var evaluateBtn = $("#operaton-evaluate-" + formId);
        var summaryContainer = $("#decision-flow-summary-" + formId);

        console.log("📍 Current page:", currentPage, "Target page:", targetPage);

        // CRITICAL FIX: Hide everything first to prevent multiple showings
        hideAllButtons();

        if (currentPage === targetPage) {
            // Show ONLY the evaluate button on the target page
            console.log("✅ Showing evaluate button ONLY on target page", currentPage);

            var form = $("#gform_" + formId);
            var targetContainer = form.find(".gform_body").first();

            if (targetContainer.length) {
                evaluateBtn.detach().appendTo(targetContainer);
                showEvaluateButton(formId);

                // Ensure decision flow summary is hidden
                hideAllElements(formId);
            }

        } else if (currentPage === (targetPage + 1) && showDecisionFlow && useProcess) {
            // Show ONLY decision flow on the next page
            console.log("✅ Showing decision flow ONLY on page", currentPage);

            // Ensure evaluate button is hidden
            hideAllElements(formId);

            // Show and load decision flow
            showDecisionFlowSummary(formId);
            loadDecisionFlowSummary();

        } else {
            // Hide both on all other pages
            console.log("❌ Hiding both button and summary on page", currentPage);
            hideAllElements(formId);
        }
    }

    function loadDecisionFlowSummary() {
        var container = $("#decision-flow-summary-" + formId);

        if (container.hasClass("loading")) {
            return;
        }

        console.log("📊 Loading decision flow summary...");
        container.addClass("loading");
        container.html("<div style=\"padding: 20px; text-align: center;\"><p>⏳ Loading decision flow summary...</p></div>");

        $.ajax({
            url: "%s/wp-json/operaton-dmn/v1/decision-flow/" + formId + "?cache_bust=" + Date.now(),
            type: "GET",
            cache: false,
            success: function(response) {
                if (response.success && response.html) {
                    container.html(response.html);
                    console.log("✅ Decision flow loaded successfully");
                } else {
                    container.html("<div style=\"padding: 20px;\"><p><em>No decision flow data available.</em></p></div>");
                }
            },
            error: function(xhr, status, error) {
                console.error("❌ Decision flow error:", error);
                container.html("<div style=\"padding: 20px;\"><p><em>Error loading decision flow summary.</em></p></div>");
            },
            complete: function() {
                container.removeClass("loading");
            }
        });
    }

    // CRITICAL FIX: More robust initialization with multiple detection methods
    function initializeButtonPlacement() {
        console.log("🚀 Initializing button placement for form", formId);

        // Wait a bit for Gravity Forms to settle
        setTimeout(function() {
            handleButtonAndSummary();
        }, 300);

        // Handle Gravity Forms page changes
        $(document).on("gform_page_loaded", function(event, form_id, current_page) {
            if (form_id == formId) {
                console.log("📄 GF page loaded event - Form:", form_id, "Page:", current_page);
                setTimeout(function() {
                    handleButtonAndSummary();
                }, 200);
            }
        });

        // Handle URL changes (for multi-page navigation)
        var currentUrl = window.location.href;
        setInterval(function() {
            if (window.location.href !== currentUrl) {
                currentUrl = window.location.href;
                console.log("🔄 URL changed, re-evaluating button placement");
                setTimeout(handleButtonAndSummary, 300);
            }
        }, 500);

        // Additional fallback for forms that don\'t trigger proper events
        setTimeout(function() {
            var evaluateBtn = $("#operaton-evaluate-" + formId);
            var currentPage = getCurrentPage();

            if (currentPage === targetPage && !evaluateBtn.is(":visible")) {
                console.log("🔧 Fallback: Button should be visible but isn\'t");
                handleButtonAndSummary();
            } else if (currentPage !== targetPage && evaluateBtn.is(":visible")) {
                console.log("🔧 Fallback: Button is visible but shouldn\'t be");
                hideAllElements(formId);
            }
        }, 1500);
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        initializeButtonPlacement();
    });

    // Additional initialization when window loads (for edge cases)
    $(window).on("load", function() {
        setTimeout(function() {
            console.log("🔄 Window loaded - checking button placement");
            handleButtonAndSummary();
        }, 500);
    });

})(jQuery);',
            $form_id,
            $target_page,
            $show_decision_flow ? 'true' : 'false',
            $use_process ? 'true' : 'false',
            home_url()
        );
    }

    // =============================================================================
    // REMAINING METHODS (abbreviated for space, but include all from your original)
    // =============================================================================

    /**
     * Count the number of pages in a Gravity Form
     * Calculates total pages for button placement logic
     *
     * @param array $form Gravity Forms form array
     * @since 1.0.0
     * @return int Number of pages in the form
     */
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

    /**
     * Get form configuration with caching
     * Retrieves DMN configuration for a specific form with caching
     *
     * @param int $form_id Gravity Forms form ID
     * @since 1.0.0
     * @return object|null Configuration object or null if not found
     */
    private function get_form_config($form_id)
    {
        if (isset($this->form_configs_cache[$form_id]))
        {
            return $this->form_configs_cache[$form_id];
        }

        $config = $this->database->get_config_by_form_id($form_id);
        $this->form_configs_cache[$form_id] = $config;

        return $config;
    }

    /**
     * Get available Gravity Forms for admin interface
     * Retrieves all Gravity Forms with field information for configuration
     *
     * @since 1.0.0
     * @return array Array of Gravity Forms with field details
     */
    public function get_available_forms()
    {
        if (!$this->check_gravity_forms_availability())
        {
            return array();
        }

        try
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Gravity Forms: Retrieving available forms');
            }

            $forms = GFAPI::get_forms();

            // Add form fields information for better mapping
            foreach ($forms as &$form)
            {
                if (isset($form['fields']))
                {
                    $form['field_list'] = array();
                    foreach ($form['fields'] as $field)
                    {
                        $form['field_list'][] = array(
                            'id' => $field->id,
                            'label' => $field->label,
                            'type' => $field->type,
                            'adminLabel' => $field->adminLabel ?? '',
                            'isRequired' => $field->isRequired ?? false
                        );
                    }
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

    // =============================================================================
    // PUBLIC API METHODS FOR OTHER COMPONENTS
    // =============================================================================

    /**
     * Check if Gravity Forms is available (public method)
     * Public interface for checking Gravity Forms availability
     *
     * @since 1.0.0
     * @return bool True if Gravity Forms is available
     */
    public function is_gravity_forms_available()
    {
        return $this->check_gravity_forms_availability();
    }

    /**
     * Get form configuration (public method)
     * Public interface for getting form configuration
     *
     * @param int $form_id Gravity Forms form ID
     * @since 1.0.0
     * @return object|null Configuration object or null if not found
     */
    public function get_form_configuration($form_id)
    {
        return $this->get_form_config($form_id);
    }

    /**
     * Handle Gravity Forms validation for DMN-enabled forms
     * Integrates with Gravity Forms validation system
     *
     * @param array $validation_result Gravity Forms validation result
     * @since 1.0.0
     * @return array Modified validation result
     */
    public function validate_dmn_fields($validation_result)
    {
        $form = $validation_result['form'];
        $config = $this->get_form_config($form['id']);

        if (!$config)
        {
            return $validation_result;
        }

        // Add custom validation logic here if needed
        // For example, validate required fields for DMN evaluation

        return $validation_result;
    }

    /**
     * Handle post-submission processing for DMN-enabled forms
     * Processes form after successful Gravity Forms submission
     *
     * @param array $entry Gravity Forms entry data
     * @param array $form Gravity Forms form data
     * @since 1.0.0
     */
    public function handle_post_submission($entry, $form)
    {
        $config = $this->get_form_config($form['id']);

        if (!$config)
        {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Post-submission processing for form ' . $form['id']);
        }

        // Trigger any post-submission DMN processing if needed
        do_action('operaton_dmn_form_submitted', $entry, $form, $config);
    }

    /**
     * AJAX handler for form evaluation
     * Handles AJAX evaluation requests from Gravity Forms
     *
     * @since 1.0.0
     */
    public function ajax_evaluate_form()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'operaton_gravity_nonce'))
        {
            wp_send_json_error(array('message' => __('Security check failed', 'operaton-dmn')));
        }

        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $form_data = isset($_POST['form_data']) ? $_POST['form_data'] : array();

        if (!$form_id || empty($form_data))
        {
            wp_send_json_error(array('message' => __('Invalid form data', 'operaton-dmn')));
        }

        $config = $this->get_form_config($form_id);
        if (!$config)
        {
            wp_send_json_error(array('message' => __('Configuration not found', 'operaton-dmn')));
        }

        // Process evaluation through API manager
        $api = $this->core->get_api_instance();
        $result = $api->handle_evaluation(new WP_REST_Request('POST', '/operaton-dmn/v1/evaluate'));

        if (is_wp_error($result))
        {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Add JavaScript to Gravity Forms form editor
     * Enhances form editor with DMN-specific functionality
     *
     * @since 1.0.0
     */
    public function add_editor_script()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Adding editor script');
        }

    ?>
        <script type='text/javascript'>
            jQuery(document).ready(function($) {
                // Add compatibility for form editor
                if (typeof fieldSettings !== 'undefined') {
                    fieldSettings.operaton_dmn = '.label_setting, .description_setting, .admin_label_setting, .size_setting, .default_value_textarea_setting, .error_message_setting, .css_class_setting, .visibility_setting';
                }

                // Add DMN-specific field settings
                $('.operaton-dmn-field').each(function() {
                    $(this).addClass('operaton-dmn-enhanced');
                });
            });
        </script>
        <?php
    }

    /**
     * Add advanced field settings for DMN integration
     * Adds custom field settings in the Gravity Forms editor
     *
     * @param int $position Setting position in the form editor
     * @param int $form_id Gravity Forms form ID
     * @since 1.0.0
     */
    public function add_field_advanced_settings($position, $form_id)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Adding field advanced settings for form ' . $form_id);
        }

        // Add custom field settings here if needed
        // This could include field-level DMN mapping options

        if ($position == 25)
        {
        ?>
            <li class="operaton_dmn_setting field_setting">
                <label for="operaton_dmn_variable">
                    <?php _e('DMN Variable Name', 'operaton-dmn'); ?>
                    <?php gform_tooltip('operaton_dmn_variable') ?>
                </label>
                <input type="text" id="operaton_dmn_variable" class="fieldwidth-3" />
            </li>
<?php
        }
    }

    /**
     * Add custom field buttons to Gravity Forms editor
     * Adds DMN-specific field types to the form editor
     *
     * @param array $field_groups Existing field groups
     * @since 1.0.0
     * @return array Modified field groups
     */
    public function add_custom_field_buttons($field_groups)
    {
        // Add DMN field group if needed
        foreach ($field_groups as &$group)
        {
            if ($group['name'] == 'advanced_fields')
            {
                // Add custom DMN field types here if needed
                break;
            }
        }

        return $field_groups;
    }

    /**
     * Add form settings for DMN integration
     * Adds DMN configuration options to form settings
     *
     * @param array $settings Existing form settings
     * @param array $form Gravity Forms form data
     * @since 1.0.0
     * @return array Modified form settings
     */
    public function add_form_settings($settings, $form)
    {
        $config = $this->get_form_config($form['id']);

        $settings['DMN Integration'] = array(
            array(
                'title' => __('DMN Configuration', 'operaton-dmn'),
                'fields' => array(
                    array(
                        'label' => __('DMN Configuration Status', 'operaton-dmn'),
                        'type' => 'html',
                        'html' => $config ?
                            '<span class="gf_settings_description" style="color: green;">✓ ' . __('DMN configuration is active for this form', 'operaton-dmn') . '</span>' :
                            '<span class="gf_settings_description">' . __('No DMN configuration found. Configure in Operaton DMN plugin settings.', 'operaton-dmn') . '</span>'
                    ),
                    array(
                        'label' => __('Configuration Link', 'operaton-dmn'),
                        'type' => 'html',
                        'html' => '<a href="' . admin_url('admin.php?page=operaton-dmn') . '" class="button">' . __('Manage DMN Configurations', 'operaton-dmn') . '</a>'
                    )
                )
            )
        );

        return $settings;
    }

    /**
     * Save form settings for DMN integration
     * Processes DMN-related form settings
     *
     * @param array $form Gravity Forms form data
     * @since 1.0.0
     * @return array Modified form data
     */
    public function save_form_settings($form)
    {
        // Process any DMN-specific form settings here
        return $form;
    }

    /**
     * Pre-render form processing for dynamic field population
     * Handles form pre-processing for DMN integration
     *
     * @param array $form Gravity Forms form data
     * @param bool $ajax Whether this is an AJAX request
     * @param array $field_values Field values for population
     * @since 1.0.0
     * @return array Modified form data
     */
    public function pre_render_form($form, $ajax = false, $field_values = array())
    {
        $config = $this->get_form_config($form['id']);

        if (!$config)
        {
            return $form;
        }

        // Add DMN-specific form modifications here
        // For example, add CSS classes, modify field properties, etc.

        foreach ($form['fields'] as &$field)
        {
            // Add DMN-related CSS classes or attributes
            if (!empty($field->cssClass))
            {
                $field->cssClass .= ' operaton-dmn-field';
            }
            else
            {
                $field->cssClass = 'operaton-dmn-field';
            }
        }

        return $form;
    }

    /**
     * Get form field details for mapping interface
     * Retrieves detailed field information for a specific form
     *
     * @param int $form_id Gravity Forms form ID
     * @since 1.0.0
     * @return array Array of field details
     */
    public function get_form_fields($form_id)
    {
        if (!$this->check_gravity_forms_availability())
        {
            return array();
        }

        try
        {
            $form = GFAPI::get_form($form_id);

            if (!$form)
            {
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

                // Add choices for select/radio/checkbox fields
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

            return $fields;
        }
        catch (Exception $e)
        {
            error_log('Operaton DMN Gravity Forms: Error getting form fields: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Check if form exists and is accessible
     * Validates form existence for configuration
     *
     * @param int $form_id Gravity Forms form ID
     * @since 1.0.0
     * @return bool True if form exists and is accessible
     */
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

    /**
     * Get form title for display purposes
     * Retrieves form title for admin interface
     *
     * @param int $form_id Gravity Forms form ID
     * @since 1.0.0
     * @return string Form title or empty string if not found
     */
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

    /**
     * Get integration status for diagnostics
     * Provides status information about Gravity Forms integration
     *
     * @since 1.0.0
     * @return array Integration status information
     */
    public function get_integration_status()
    {
        $status = array(
            'gravity_forms_available' => $this->check_gravity_forms_availability(),
            'hooks_registered' => false,
            'forms_with_dmn_config' => 0,
            'total_forms' => 0,
            'version_info' => array()
        );

        if ($status['gravity_forms_available'])
        {
            // Check if our hooks are registered
            $status['hooks_registered'] = array(
                'gform_submit_button' => has_filter('gform_submit_button', array($this, 'add_evaluate_button')),
                'gform_enqueue_scripts' => has_action('gform_enqueue_scripts', array($this, 'enqueue_gravity_scripts')),
                'gform_validation' => has_filter('gform_validation', array($this, 'validate_dmn_fields'))
            );

            // Get form statistics
            try
            {
                $forms = GFAPI::get_forms();
                $status['total_forms'] = count($forms);

                // Count forms with DMN configurations
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

            // Get version information
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

    /**
     * Clear form configuration cache
     * Clears cached form configurations for fresh data
     *
     * @since 1.0.0
     */
    public function clear_form_cache()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Gravity Forms: Clearing form configuration cache');
        }

        $this->form_configs_cache = array();
    }

    /**
     * Force reload form configuration
     * Forces fresh load of form configuration bypassing cache
     *
     * @param int $form_id Gravity Forms form ID
     * @since 1.0.0
     * @return object|null Configuration object or null if not found
     */
    public function reload_form_configuration($form_id)
    {
        unset($this->form_configs_cache[$form_id]);
        return $this->get_form_config($form_id);
    }

    /**
     * Get core plugin instance
     * Provides access to core plugin functionality
     *
     * @since 1.0.0
     * @return OperatonDMNEvaluator Core plugin instance
     */
    public function get_core_instance()
    {
        return $this->core;
    }

    /**
     * Get assets manager instance
     * Provides access to assets manager
     *
     * @since 1.0.0
     * @return Operaton_DMN_Assets Assets manager instance
     */
    public function get_assets_manager()
    {
        return $this->assets;
    }

    /**
     * Get database manager instance
     * Provides access to database manager
     *
     * @since 1.0.0
     * @return Operaton_DMN_Database Database manager instance
     */
    public function get_database_manager()
    {
        return $this->database;
    }
}
