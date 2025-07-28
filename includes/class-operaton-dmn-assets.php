<?php

/**
 * FIXED: Assets Manager for Operaton DMN Plugin
 *
 * Key fixes:
 * 1. Enhanced jQuery dependency management
 * 2. Better Gravity Forms detection
 * 3. Improved compatibility checks
 * 4. DOCTYPE validation and fixes
 * 5. FIXED: Button text localization conflicts
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}

class Operaton_DMN_Assets
{

    /**
     * Performance monitor instance
     */
    private $performance;

    /**
     * Static loading flag to prevent cross-instance duplicates
     */
    private static $global_loading_state = array(
        'frontend_loaded' => false,
        'admin_loaded' => false,
        'gravity_loaded' => false
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
        if (class_exists('Operaton_DMN_Performance_Monitor'))
        {
            $this->performance = Operaton_DMN_Performance_Monitor::get_instance();
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Manager initialized with version ' . $version);
        }

        $this->init_hooks();
    }

    public function set_gravity_forms_manager($gravity_forms_manager)
    {
        $this->gravity_forms_manager = $gravity_forms_manager;

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Gravity Forms manager set successfully');
        }
    }

    /**
     * PHASE 1 FIX: Centralized loading coordinator
     * This replaces the fragmented loading logic with a single source of truth
     */
    private static $loading_coordinator = array(
        'detection_complete' => false,
        'should_load_assets' => false,
        'loading_in_progress' => false,
        'detection_reasons' => array(),
        'detection_timestamp' => 0,
        'form_detection_cache' => array()
    );

    /**
     * PHASE 1 FIX: Single detection method that all other methods must use
     * This eliminates redundant detection logic across multiple methods
     */
    public static function should_load_frontend_assets()
    {
        // Return cached result if detection already complete
        if (self::$loading_coordinator['detection_complete'])
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Assets: Using cached detection result: ' .
                    (self::$loading_coordinator['should_load_assets'] ? 'LOAD' : 'SKIP') .
                    ' (reasons: ' . implode(', ', self::$loading_coordinator['detection_reasons']) . ')');
            }
            return self::$loading_coordinator['should_load_assets'];
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('🔍 Operaton DMN Assets: Running centralized asset detection');
        }

        $should_load = false;
        $reasons = array();

        // DETECTION METHOD 1: Class existence (most reliable)
        if (class_exists('GFForms'))
        {
            $should_load = true;
            $reasons[] = 'GFForms class available';
        }

        // DETECTION METHOD 2: Admin context with GF pages
        if (!$should_load && is_admin())
        {
            $screen = get_current_screen();
            if ($screen && strpos($screen->id, 'toplevel_page_gf_') === 0)
            {
                $should_load = true;
                $reasons[] = 'GF admin page detected';
            }
        }

        // DETECTION METHOD 3: Post content analysis (only if not already determined)
        if (!$should_load && !is_admin())
        {
            global $post;
            if ($post)
            {
                if (has_shortcode($post->post_content, 'gravityform'))
                {
                    $should_load = true;
                    $reasons[] = 'gravityform shortcode found';
                }
                elseif (has_block('gravityforms/form', $post))
                {
                    $should_load = true;
                    $reasons[] = 'gravityforms block found';
                }
            }
        }

        // DETECTION METHOD 4: URL-based detection (preview pages, etc.)
        if (!$should_load && isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview')
        {
            $should_load = true;
            $reasons[] = 'GF preview page';
        }

        // DETECTION METHOD 5: Template-based detection
        if (!$should_load && !is_admin())
        {
            $template = get_page_template_slug();
            if (strpos($template, 'gravity') !== false || strpos($template, 'form') !== false)
            {
                $should_load = true;
                $reasons[] = 'form template detected';
            }
        }

        // Cache the results
        self::$loading_coordinator['detection_complete'] = true;
        self::$loading_coordinator['should_load_assets'] = $should_load;
        self::$loading_coordinator['detection_reasons'] = $reasons;
        self::$loading_coordinator['detection_timestamp'] = time();

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            $result_text = $should_load ? '✅ LOAD ASSETS' : '❌ SKIP ASSETS';
            $reasons_text = empty($reasons) ? 'no triggers found' : implode(', ', $reasons);
            error_log("🔍 Operaton DMN Assets: Detection complete - {$result_text} ({$reasons_text})");
        }

        return $should_load;
    }

    /**
     * FIXED: Enhanced initialization hooks with better timing
     */
    private function init_hooks()
    {
        // Register all assets very early
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'), 5);
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'), 5);

        // FIXED: Better conditional loading with multiple detection methods
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_frontend_assets'), 10);
        add_action('admin_enqueue_scripts', array($this, 'maybe_enqueue_admin_assets'), 10);

        // FIXED: Enhanced DOCTYPE and compatibility checking
        add_action('wp_head', array($this, 'check_document_compatibility'), 1);
        add_action('wp_head', array($this, 'add_jquery_compatibility_fix'), 2);

        // FIXED: Force load assets for known GF pages
        add_action('template_redirect', array($this, 'detect_gravity_forms_early'), 1);
    }

    /**
     * NEW: Early detection of Gravity Forms pages
     */
    public function detect_gravity_forms_early()
    {
        if (is_admin())
        {
            return;
        }

        // Check for GF preview pages
        if (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview')
        {
            add_action('wp_enqueue_scripts', array($this, 'force_enqueue_frontend_assets'), 8);
            return;
        }

        // Check post content early
        global $post;
        if ($post)
        {
            $has_gf = has_shortcode($post->post_content, 'gravityform') ||
                has_block('gravityforms/form', $post);

            if ($has_gf)
            {
                add_action('wp_enqueue_scripts', array($this, 'force_enqueue_frontend_assets'), 8);
            }
        }
    }

    /**
     * FIXED: Enhanced jQuery compatibility checking
     */
    public function add_jquery_compatibility_fix()
    {
        if (is_admin())
        {
            return;
        }

        // Only add compatibility check if we're actually loading assets
        if (!self::should_load_frontend_assets())
        {
            return;
        }

        // Add to footer to ensure it runs after jQuery is loaded
        add_action('wp_footer', array($this, 'add_footer_compatibility_check'), 1);
    }

    /**
     * NEW: Footer-based compatibility check that runs after jQuery
     */
    public function add_footer_compatibility_check()
    {
?>
        <script type="text/javascript">
            /* Operaton DMN: Post-Load Compatibility Check */
            (function() {
                'use strict';

                // This runs after jQuery should be loaded
                var compatibilityInfo = {
                    jqueryAvailable: typeof jQuery !== 'undefined',
                    jqueryVersion: typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'none',
                    quirksMode: document.compatMode === "BackCompat",
                    doctype: document.doctype ? document.doctype.name : 'missing',
                    issues: []
                };

                // Only log issues, don't try to fix them
                if (!compatibilityInfo.jqueryAvailable) {
                    compatibilityInfo.issues.push('jQuery not loaded');
                    console.error('❌ Operaton DMN: jQuery still not available after page load');
                } else {
                    if (<?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>) {
                        console.log('✅ Operaton DMN: jQuery loaded successfully, version:', compatibilityInfo.jqueryVersion);
                    }
                }

                if (compatibilityInfo.quirksMode) {
                    compatibilityInfo.issues.push('Quirks Mode detected');
                    document.body.className += ' operaton-quirks-mode-detected';
                }

                // Store globally for debugging
                window.operatonCompatibilityInfo = compatibilityInfo;

                if (<?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>) {
                    console.log('Operaton DMN Compatibility Check:', compatibilityInfo);
                }
            })();
        </script>
    <?php
    }

    /**
     * FIXED: Determine if compatibility check should run
     */
    private function should_run_compatibility_check()
    {
        // Always run if we detect Gravity Forms
        if (class_exists('GFForms'))
        {
            return true;
        }

        global $post;
        if ($post)
        {
            // Check for shortcodes
            if (has_shortcode($post->post_content, 'gravityform'))
            {
                return true;
            }

            // Check for Gutenberg blocks
            if (has_block('gravityforms/form', $post))
            {
                return true;
            }
        }

        // Run on Gravity Forms preview pages
        if (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview')
        {
            return true;
        }

        return false;
    }

    /**
     * FIXED: Register frontend assets with proper dependencies
     */
    public function register_frontend_assets()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Registering frontend assets');
        }

        // Register frontend CSS
        wp_register_style(
            'operaton-dmn-frontend',
            $this->plugin_url . 'assets/css/frontend.css',
            array(),
            $this->version
        );

        // FIXED: Remove the manual jQuery registration - WordPress handles this
        // jQuery is automatically registered by WordPress core
        // Just ensure it's enqueued when needed

        wp_register_script(
            'operaton-dmn-frontend',
            $this->plugin_url . 'assets/js/frontend.js',
            array('jquery'), // Explicit jQuery dependency
            $this->version,
            true // Load in footer AFTER jQuery
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

        // Register radio sync assets
        $this->register_radio_sync_assets();
    }

    /**
     * Register radio sync assets
     */
    public function register_radio_sync_assets()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Registering radio sync assets');
        }

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

    /**
     * Updated detection method that uses centralized logic
     */
    public function maybe_enqueue_frontend_assets()
    {
        if (is_admin())
        {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('🔍 Operaton DMN Assets: maybe_enqueue_frontend_assets called - checking centralized controller');
        }

        // Use centralized detection
        if (self::should_load_frontend_assets())
        {
            $this->enqueue_frontend_assets();
        }
        else
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('🔍 Operaton DMN Assets: Centralized controller determined assets not needed');
            }
        }
    }

    /**
     * Forced loading now uses centralized control
     */

    /**
     * Atomic asset loading with comprehensive duplicate prevention
     * This replaces the existing method with centralized control
     */
    public function force_enqueue_frontend_assets()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('🔍 Operaton DMN Assets: force_enqueue_frontend_assets called');
        }

        // Even forced loading should check for duplicates
        $this->enqueue_frontend_assets();
    }

    /**
     * PHASE 1 FIX: Reset method for testing and clearing state
     */
    public static function reset_loading_coordinator()
    {
        self::$loading_coordinator = array(
            'detection_complete' => false,
            'should_load_assets' => false,
            'loading_in_progress' => false,
            'detection_reasons' => array(),
            'detection_timestamp' => 0,
            'form_detection_cache' => array()
        );

        self::$global_loading_state = array(
            'frontend_loaded' => false,
            'admin_loaded' => false,
            'gravity_loaded' => false
        );

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: 🔄 Loading coordinator reset');
        }
    }

    /**
     * PHASE 1 FIX: Get coordinator status for debugging
     */
    public static function get_coordinator_status()
    {
        return array(
            'coordinator_state' => self::$loading_coordinator,
            'global_state' => self::$global_loading_state,
            'wordpress_states' => array(
                'frontend_registered' => wp_script_is('operaton-dmn-frontend', 'registered'),
                'frontend_enqueued' => wp_script_is('operaton-dmn-frontend', 'enqueued'),
                'frontend_done' => wp_script_is('operaton-dmn-frontend', 'done'),
                'jquery_enqueued' => wp_script_is('jquery', 'enqueued')
            )
        );
    }

    /**
     * FIXED: Enhanced frontend asset loading with guaranteed jQuery dependency
     */
    public function enqueue_frontend_assets()
    {
        $timer_id = null;
        if ($this->performance)
        {
            $timer_id = $this->performance->start_timer('frontend_assets_enqueue');
        }

        // Prevent duplicate loading
        if (self::$global_loading_state['frontend_loaded'])
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Assets: ⏭️ SKIPPED - Already loaded globally');
            }
            if ($this->performance && $timer_id)
            {
                $this->performance->stop_timer($timer_id, 'Skipped - already loaded globally');
            }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: 🚀 LOADING frontend assets with jQuery dependency check');
        }

        try
        {
            // CRITICAL FIX: Ensure jQuery is available before proceeding
            if (!wp_script_is('jquery', 'enqueued') && !wp_script_is('jquery', 'done'))
            {
                wp_enqueue_script('jquery');

                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('Operaton DMN Assets: Explicitly enqueued jQuery');
                }
            }

            // Register our script with explicit jQuery dependency
            if (!wp_script_is('operaton-dmn-frontend', 'registered'))
            {
                wp_register_script(
                    'operaton-dmn-frontend',
                    $this->plugin_url . 'assets/js/frontend.js',
                    array('jquery'), // Explicit dependency
                    $this->version,
                    true // Load in footer AFTER jQuery
                );

                // CRITICAL: Add script dependency data for WordPress
                wp_script_add_data('operaton-dmn-frontend', 'group', 1);
            }

            // Enqueue assets
            wp_enqueue_style('operaton-dmn-frontend');
            wp_enqueue_script('operaton-dmn-frontend');

            // CRITICAL FIX: Only localize once and after jQuery is guaranteed
            if (!wp_script_is('operaton-dmn-frontend', 'localized'))
            {
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
                        'timestamp' => time()
                    ),
                    'loading_source' => 'enhanced_dependency_management'
                );

                wp_localize_script('operaton-dmn-frontend', 'operaton_ajax', $localization_data);

                // Mark as localized to prevent duplicate localization
                wp_script_add_data('operaton-dmn-frontend', 'localized', true);
            }

            // Update state flags
            $this->loaded_assets['frontend'] = true;
            self::$global_loading_state['frontend_loaded'] = true;

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Assets: ✅ Frontend assets loaded with enhanced dependency management');
            }
        }
        catch (Exception $e)
        {
            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Assets: ❌ Error during asset loading: ' . $e->getMessage());
            }
        }
        finally
        {
            if ($this->performance && $timer_id)
            {
                $this->performance->stop_timer($timer_id, 'Frontend assets loading completed');
            }
        }
    }

    /**
     * Reset global state (for testing or specific scenarios)
     */
    public static function reset_global_state()
    {
        self::$global_loading_state = array(
            'frontend_loaded' => false,
            'admin_loaded' => false,
            'gravity_loaded' => false
        );

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Global loading state reset');
        }
    }

    /**
     * Get current loading state for debugging
     */
    public function get_loading_state()
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
     * Register admin assets
     */
    public function register_admin_assets()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Registering admin assets');
        }

        wp_register_style(
            'operaton-dmn-admin',
            $this->plugin_url . 'assets/css/admin.css',
            array(),
            $this->version
        );

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            wp_register_style(
                'operaton-dmn-debug',
                $this->plugin_url . 'assets/css/debug.css',
                array('operaton-dmn-admin'),
                $this->version
            );
        }

        wp_register_script(
            'operaton-dmn-admin',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_register_script(
            'operaton-dmn-api-test',
            $this->plugin_url . 'assets/js/api-test.js',
            array('jquery', 'operaton-dmn-admin'),
            $this->version,
            true
        );
    }

    /**
     * Maybe enqueue admin assets
     */
    public function maybe_enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'operaton-dmn') !== false)
        {
            $this->enqueue_admin_assets($hook);
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        if (isset($this->loaded_assets['admin']))
        {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Enqueuing admin assets for: ' . $hook);
        }

        wp_enqueue_style('operaton-dmn-admin');

        if (defined('WP_DEBUG') && WP_DEBUG && strpos($hook, 'debug') !== false)
        {
            wp_enqueue_style('operaton-dmn-debug');
        }

        wp_enqueue_script('operaton-dmn-admin');

        if (strpos($hook, 'operaton-dmn-add') !== false || isset($_GET['edit']))
        {
            wp_enqueue_script('operaton-dmn-api-test');
        }

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
     * Enqueue radio sync assets for specific form
     */
    public function enqueue_radio_sync_assets($form_id = null)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Enqueuing radio sync assets for form: ' . $form_id);
        }

        // Check if this form needs radio synchronization
        if ($form_id && $this->form_needs_radio_sync($form_id))
        {
            wp_enqueue_script('operaton-dmn-radio-sync');
            wp_enqueue_style('operaton-dmn-radio-sync');

            // Localize script with form-specific data
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

            if (defined('WP_DEBUG') && WP_DEBUG)
            {
                error_log('Operaton DMN Assets: Radio sync assets enqueued for form ' . $form_id);
            }
        }
    }

    /**
     * Check if a form needs radio synchronization
     */
    private function form_needs_radio_sync($form_id)
    {
        // Form 8 specifically needs radio sync
        if ($form_id == 8)
        {
            return true;
        }

        // Check if form has HTML fields with radio buttons
        if ($this->gravity_forms_manager && $this->gravity_forms_manager->is_gravity_forms_available())
        {
            try
            {
                if (class_exists('GFAPI'))
                {
                    $form = GFAPI::get_form($form_id);
                    if ($form && isset($form['fields']))
                    {
                        foreach ($form['fields'] as $field)
                        {
                            if (
                                $field->type === 'html' &&
                                strpos($field->content, 'type="radio"') !== false &&
                                strpos($field->content, 'aanvrager') !== false
                            )
                            {
                                return true;
                            }
                        }
                    }
                }
            }
            catch (Exception $e)
            {
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
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

        if ($form_id == 8)
        {
            return $default_mappings;
        }

        // For other forms, could extract mappings dynamically
        // or store them in the database configuration
        return array();
    }

    /**
     * Check if current page has Gravity Forms
     */
    private function has_gravity_forms_on_page()
    {
        if ($this->gravity_forms_manager && $this->gravity_forms_manager->is_gravity_forms_available())
        {
            return $this->has_dmn_enabled_forms_on_page();
        }

        if (!class_exists('GFForms'))
        {
            return false;
        }

        global $post;
        if ($post && has_shortcode($post->post_content, 'gravityform'))
        {
            return true;
        }

        if ($post && has_block('gravityforms/form', $post))
        {
            return true;
        }

        if (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview')
        {
            return true;
        }

        return apply_filters('operaton_dmn_has_gravity_forms', false);
    }

    /**
     * Check if current page has DMN-enabled Gravity Forms
     */
    private function has_dmn_enabled_forms_on_page()
    {
        global $post;

        if ($post && has_shortcode($post->post_content, 'gravityform'))
        {
            $form_ids = $this->extract_form_ids_from_shortcodes($post->post_content);
            return $this->any_forms_have_dmn_config($form_ids);
        }

        if ($post && has_block('gravityforms/form', $post))
        {
            $form_ids = $this->extract_form_ids_from_blocks($post);
            return $this->any_forms_have_dmn_config($form_ids);
        }

        if (isset($_GET['gf_page']) && $_GET['gf_page'] === 'preview')
        {
            $form_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($form_id > 0)
            {
                $config = $this->gravity_forms_manager->get_form_configuration($form_id);
                return $config !== null;
            }
        }

        return false;
    }

    /**
     * Extract form IDs from gravityform shortcodes
     */
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

    /**
     * Extract form IDs from Gravity Forms Gutenberg blocks
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

            if (!empty($block['innerBlocks']))
            {
                $inner_ids = $this->find_gravity_form_ids_in_blocks($block['innerBlocks']);
                $form_ids = array_merge($form_ids, $inner_ids);
            }
        }

        return $form_ids;
    }

    /**
     * Check if any forms have DMN configurations
     */
    private function any_forms_have_dmn_config($form_ids)
    {
        if (!$this->gravity_forms_manager)
        {
            return false;
        }

        foreach ($form_ids as $form_id)
        {
            $config = $this->gravity_forms_manager->get_form_configuration($form_id);
            if ($config !== null)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Enhanced Gravity Forms asset enqueuing with performance tracking
     */
    public function enqueue_gravity_form_assets($form, $config)
    {
        $timer_id = null;
        if ($this->performance)
        {
            $timer_id = $this->performance->start_timer('gravity_form_assets');
            $this->performance->mark('gravity_assets_start', 'Starting Gravity Forms assets for form: ' . $form['id']);
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Enqueuing Gravity Forms assets for form: ' . $form['id']);
        }

        // Ensure frontend assets are loaded first
        $this->enqueue_frontend_assets();

        // Enqueue radio sync if needed
        $this->enqueue_radio_sync_assets($form['id']);

        // Add form-specific scripts with proper timing
        add_action('wp_footer', function () use ($form, $config, $timer_id)
        {
            $this->enqueue_gravity_integration_scripts($form, $config);

            if ($this->performance && $timer_id)
            {
                $this->performance->stop_timer($timer_id, 'Gravity Forms assets completed for form: ' . $form['id']);
            }
        }, 5);

        if ($this->performance)
        {
            $this->performance->mark('gravity_assets_queued', 'Gravity Forms assets queued for form: ' . $form['id']);
        }
    }

    /**
     * FIXED: Separate method for Gravity Forms integration scripts
     * This method now uses consistent button text strings from the main localization
     */
    private function enqueue_gravity_integration_scripts($form, $config)
    {
        // Enqueue Gravity Forms integration script
        wp_enqueue_script('operaton-dmn-gravity-integration');

        // Process configuration for JavaScript
        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE)
        {
            $field_mappings = array();
        }

        $result_mappings = json_decode($config->result_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE)
        {
            $result_mappings = array();
        }

        // FIXED: Only localize form-specific configuration, NOT button text strings
        // This prevents conflicts with the main operaton_ajax localization
        wp_localize_script('operaton-dmn-gravity-integration', 'operaton_config_' . $form['id'], array(
            'config_id' => $config->id,
            'button_text' => $config->button_text, // Keep this for reference, but don't use for state management
            'field_mappings' => $field_mappings,
            'result_mappings' => $result_mappings,
            'form_id' => $form['id'],
            'evaluation_step' => isset($config->evaluation_step) ? $config->evaluation_step : 'auto',
            'use_process' => isset($config->use_process) ? $config->use_process : false,
            'show_decision_flow' => isset($config->show_decision_flow) ? $config->show_decision_flow : false,
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Form-specific configuration localized for form ' . $form['id']);
            error_log('Operaton DMN Assets: Button text from config: ' . $config->button_text);
            error_log('Operaton DMN Assets: IMPORTANT - Button state management handled by frontend.js button manager');
        }
    }

    /**
     * Enqueue decision flow CSS and JavaScript
     */
    public function enqueue_decision_flow_assets()
    {
        // Prevent duplicate loading
        if (isset($this->loaded_assets['decision_flow']))
        {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
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
     * Add inline CSS for dynamic styling
     */
    public function add_inline_styles($form_id = null, $styles = array())
    {
        $css = '';

        // Generate CSS custom properties from styles
        if (!empty($styles['theme']))
        {
            $css .= ':root {';

            foreach ($styles['theme'] as $property => $value)
            {
                $css .= '--operaton-' . esc_attr($property) . ': ' . esc_attr($value) . ';';
            }

            $css .= '}';
        }

        // Form-specific styles
        if ($form_id && !empty($styles['form']))
        {
            $css .= "#operaton-evaluate-{$form_id} {";
            foreach ($styles['form'] as $property => $value)
            {
                $css .= esc_attr($property) . ': ' . esc_attr($value) . ' !important;';
            }
            $css .= '}';

            $css .= "#decision-flow-summary-{$form_id} {";
            foreach ($styles['form'] as $property => $value)
            {
                if (strpos($property, 'button-') === false)
                {
                    $css .= esc_attr($property) . ': ' . esc_attr($value) . ';';
                }
            }
            $css .= '}';
        }

        if (!empty($css))
        {
            // Determine which style to add inline CSS to
            $handle = 'operaton-dmn-frontend';
            if (is_admin())
            {
                $handle = 'operaton-dmn-admin';
            }

            wp_add_inline_style($handle, $css);
        }
    }

    /**
     * Get detailed asset status with performance data
     */
    public function get_assets_status()
    {
        global $wp_scripts, $wp_styles;

        $status = array(
            'loaded_assets' => $this->loaded_assets,
            'global_state' => self::$global_loading_state,
            'scripts' => array(),
            'styles' => array(),
            'performance' => array()
        );

        // Add performance data if available
        if ($this->performance)
        {
            $performance_summary = $this->performance->get_summary();
            $status['performance'] = array(
                'total_time_ms' => $performance_summary['total_time_ms'],
                'peak_memory' => $performance_summary['peak_memory_formatted'],
                'milestones_count' => $performance_summary['milestone_count'],
                'asset_loading_milestones' => $this->get_asset_loading_milestones($performance_summary['milestones'])
            );
        }

        // EXISTING script and style checking code...
        $our_scripts = array(
            'operaton-dmn-admin',
            'operaton-dmn-frontend',
            'operaton-dmn-gravity-integration',
            'operaton-dmn-decision-flow',
            'operaton-dmn-api-test',
            'operaton-dmn-radio-sync'
        );

        foreach ($our_scripts as $script)
        {
            $status['scripts'][$script] = array(
                'registered' => wp_script_is($script, 'registered'),
                'enqueued' => wp_script_is($script, 'enqueued'),
                'done' => wp_script_is($script, 'done')
            );
        }

        $our_styles = array(
            'operaton-dmn-admin',
            'operaton-dmn-frontend',
            'operaton-dmn-decision-flow',
            'operaton-dmn-debug',
            'operaton-dmn-radio-sync'
        );

        foreach ($our_styles as $style)
        {
            $status['styles'][$style] = array(
                'registered' => wp_style_is($style, 'registered'),
                'enqueued' => wp_style_is($style, 'enqueued'),
                'done' => wp_style_is($style, 'done')
            );
        }

        return $status;
    }

    /**
     * Extract asset loading milestones from performance data
     *
     * @param array $milestones All performance milestones
     * @return array Asset-related milestones
     */
    private function get_asset_loading_milestones($milestones)
    {
        $asset_milestones = array();

        if (!is_array($milestones))
        {
            return $asset_milestones;
        }

        // Look for asset-related milestones
        $asset_keywords = array(
            'assets',
            'frontend_assets',
            'admin_assets',
            'gravity_form_assets',
            'script',
            'style',
            'enqueue',
            'localize'
        );

        foreach ($milestones as $name => $milestone)
        {
            $name_lower = strtolower($name);

            foreach ($asset_keywords as $keyword)
            {
                if (strpos($name_lower, $keyword) !== false)
                {
                    $asset_milestones[$name] = array(
                        'time_ms' => $milestone['time_ms'],
                        'details' => $milestone['details'] ?? '',
                        'memory' => $milestone['memory_current_formatted'] ?? ''
                    );
                    break;
                }
            }
        }

        // Sort by time
        uasort($asset_milestones, function ($a, $b)
        {
            return $a['time_ms'] <=> $b['time_ms'];
        });

        return $asset_milestones;
    }

    /**
     * Force enqueue specific assets for manual loading
     */
    public function force_enqueue($asset_group)
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Force enqueuing asset group: ' . $asset_group);
        }

        switch ($asset_group)
        {
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
                $this->register_radio_sync_assets();
                wp_enqueue_script('operaton-dmn-radio-sync');
                wp_enqueue_style('operaton-dmn-radio-sync');
                break;

            default:
                if (defined('WP_DEBUG') && WP_DEBUG)
                {
                    error_log('Operaton DMN Assets: Unknown asset group: ' . $asset_group);
                }
        }
    }

    /**
     * FIXED: Simplified document compatibility check without jQuery dependencies
     */
    public function check_document_compatibility()
    {
        if (is_admin() || !$this->should_run_compatibility_check())
        {
            return;
        }

    ?>
        <script type="text/javascript">
            /* Operaton DMN: Basic Document Check - No jQuery Required */
            (function() {
                'use strict';

                var isQuirksMode = document.compatMode === "BackCompat";
                var hasDoctype = document.doctype !== null;

                if (isQuirksMode || !hasDoctype) {
                    console.warn('⚠️ Operaton DMN: Document compatibility issues detected');

                    // Add compatibility class when DOM is ready
                    function addCompatibilityClass() {
                        if (document.body) {
                            document.body.className += ' operaton-quirks-mode-detected';
                            document.body.setAttribute('data-operaton-quirks', 'true');
                        }
                    }

                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', addCompatibilityClass);
                    } else {
                        addCompatibilityClass();
                    }
                } else {
                    if (<?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>) {
                        console.log('✅ Operaton DMN: Document in Standards Mode');
                    }
                }
            })();
        </script>
    <?php

        // Add CSS fixes
        $this->add_enhanced_quirks_mode_css_fixes();
    }

    /**
     * Enhanced CSS fixes for Quirks Mode compatibility
     */
    private function add_enhanced_quirks_mode_css_fixes()
    {
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

            /* Hide quirks mode warning in production */
            <?php if (!defined('WP_DEBUG') || !WP_DEBUG): ?>.operaton-quirks-mode-detected::before {
                display: none !important;
            }

            <?php else: ?>

            /* Quirks mode notification for debug */
            .operaton-quirks-mode-detected::before {
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

            <?php endif; ?>
        </style>
<?php
    }

    /**
     * Get compatibility status for debugging
     */
    public function get_compatibility_status()
    {
        return array(
            'check_enabled' => true,
            'hooks_registered' => array(
                'wp_head' => has_action('wp_head', array($this, 'check_document_compatibility')),
                'template_redirect' => has_action('template_redirect', array($this, 'detect_gravity_forms_early'))
            ),
            'quirks_mode_detection' => 'JavaScript-based',
            'css_fixes_available' => true,
            'jquery_compatibility' => true
        );
    }

    /**
     * Clear loaded assets cache for testing
     */
    public function reset_loaded_assets()
    {
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN Assets: Resetting loaded assets cache');
        }

        $this->loaded_assets = array();
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
}
