<?php

/**
 * Assets Manager for Operaton DMN Plugin
 *
 * Handles CSS and JavaScript asset loading with intelligent detection and optimization.
 * Manages conditional loading based on page context and form presence, with comprehensive
 * performance monitoring and caching capabilities.
 *
 * Key Features:
 * - Intelligent asset detection for optimal performance
 * - Gravity Forms integration with conditional loading
 * - Admin interface asset management
 * - Decision flow visualization assets
 * - Radio button synchronization support
 * - Performance monitoring integration
 * - Comprehensive debug capabilities
 *
 * @package OperatonDMN
 * @since 1.0.0
 * @author Operaton DMN Team
 */

// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}

/**
 * Assets Manager Class
 *
 * Manages all plugin assets including CSS, JavaScript, and their dependencies.
 * Provides intelligent loading based on page context and form requirements.
 *
 * @since 1.0.0
 */
class Operaton_DMN_Assets
{
    // =============================================================================
    // CLASS PROPERTIES
    // =============================================================================

    /**
     * Performance monitor instance for timing and optimization
     *
     * @var Operaton_DMN_Performance_Monitor|null
     * @since 1.0.0
     */
    private $performance;

    /**
     * Base plugin URL for asset path construction
     *
     * @var string
     * @since 1.0.0
     */
    private $plugin_url;

    /**
     * Plugin version for cache busting
     *
     * @var string
     * @since 1.0.0
     */
    private $version;

    /**
     * Gravity Forms manager instance reference
     *
     * @var Operaton_DMN_Gravity_Forms|null
     * @since 1.0.0
     */
    private $gravity_forms_manager = null;

    // =============================================================================
    // STATIC CACHING AND STATE MANAGEMENT
    // =============================================================================

    /**
     * Detection results cache for performance optimization
     *
     * @var array
     * @since 1.0.0
     */
    private static $detection_cache = array();

    /**
     * Cache timestamp for expiration management
     *
     * @var int|null
     * @since 1.0.0
     */
    private static $cache_timestamp = null;

    /**
     * Flag to prevent multiple detection runs
     *
     * @var bool
     * @since 1.0.0
     */
    private static $detection_complete = false;

    /**
     * Localized script configurations tracking
     *
     * @var array
     * @since 1.0.0
     */
    private static $localized_configs = array();

    /**
     * Asset loading completion state
     *
     * @var array
     * @since 1.0.0
     */
    private static $asset_loading_state = array(
        'frontend_loaded' => false,
        'admin_loaded' => false,
        'decision_flow_loaded' => false
    );

    // =============================================================================
    // CORE INITIALIZATION & SETUP
    // =============================================================================

    /**
     * Constructor - Initialize assets manager with core dependencies
     *
     * Sets up the assets manager with required plugin URL and version information,
     * initializes performance monitoring, and establishes WordPress hooks.
     *
     * @since 1.0.0
     * @param string $plugin_url Base URL for plugin assets
     * @param string $version Plugin version for cache busting
     */
    public function __construct(string $plugin_url, string $version)
    {
        $this->plugin_url = trailingslashit($plugin_url);
        $this->version = $version;

        // Initialize cache timestamp if not set
        if (self::$cache_timestamp === null)
        {
            self::$cache_timestamp = time();
        }

        // Initialize performance monitoring if available
        if (class_exists('Operaton_DMN_Performance_Monitor'))
        {
            $this->performance = Operaton_DMN_Performance_Monitor::get_instance();
        }

        $this->init_hooks();

        // UPDATED: Use global debug manager
        operaton_debug('Assets', 'Assets manager initialized with URL: ' . $this->plugin_url);
    }

    /**
     * Set Gravity Forms manager reference for integration
     *
     * Establishes the relationship between assets manager and Gravity Forms
     * integration for coordinated asset loading.
     *
     * @since 1.0.0
     * @param Operaton_DMN_Gravity_Forms $gravity_forms_manager The GF manager instance
     * @return void
     */
    public function set_gravity_forms_manager(Operaton_DMN_Gravity_Forms $gravity_forms_manager): void
    {
        $this->gravity_forms_manager = $gravity_forms_manager;
        operaton_debug('Assets', 'Gravity Forms manager reference established');
    }

    /**
     * Initialize WordPress hooks for asset management
     *
     * Sets up all WordPress hooks and filters required for proper asset loading
     * across both frontend and admin contexts.
     *
     * @since 1.0.0
     * @return void
     */
    private function init_hooks(): void
    {
        // Frontend asset hooks
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_frontend_assets'), 10);

        // Admin asset hooks
        add_action('admin_enqueue_scripts', array($this, 'maybe_enqueue_admin_assets'), 10);

        // Emergency fallback for jQuery compatibility
        add_action('wp_footer', array($this, 'add_jquery_compatibility'), 1);

        // UPDATED: Use global debug manager
        operaton_debug('Assets', 'WordPress hooks initialized');
    }

    // =============================================================================
    // PUBLIC API METHODS
    // =============================================================================

    /**
     * Determine if frontend assets should be loaded
     *
     * Intelligent detection system that checks for Gravity Forms with DMN
     * configurations on the current page. Uses caching for performance.
     *
     * @since 1.0.0
     * @return bool True if assets should be loaded
     */
    public static function should_load_frontend_assets(): bool
    {
        // Skip for admin pages
        if (is_admin())
        {
            return false;
        }

        // Skip for asset requests (CSS, JS, images)
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf)(\?.*)?$/i', $request_uri))
        {
            return false;
        }

        // Check cache first
        $cache_key = 'should_load_assets';
        if (isset(self::$detection_cache[$cache_key]) && !self::is_cache_expired())
        {
            return self::$detection_cache[$cache_key];
        }

        // Perform detection
        $should_load = self::perform_asset_detection();

        // Cache result
        self::$detection_cache[$cache_key] = $should_load;
        self::$detection_complete = true;

        return $should_load;
    }

    /**
     * Maybe enqueue frontend assets based on page context
     *
     * WordPress hook callback that conditionally loads frontend assets
     * when DMN-enabled forms are detected on the current page.
     *
     * @since 1.0.0
     * @return void
     */
    public function maybe_enqueue_frontend_assets(): void
    {
        if (!self::should_load_frontend_assets())
        {
            return;
        }

        $this->enqueue_frontend_assets();
    }

    /**
     * Maybe enqueue admin assets for Operaton DMN pages
     *
     * WordPress hook callback that loads admin assets only on plugin-specific
     * admin pages to minimize impact on other admin areas.
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function maybe_enqueue_admin_assets(string $hook): void
    {
        // Only load on Operaton DMN admin pages
        if (strpos($hook, 'operaton-dmn') === false)
        {
            return;
        }

        $this->enqueue_admin_assets();
    }

    /**
     * Enqueue frontend assets for DMN evaluation functionality
     *
     * Loads all required CSS and JavaScript files for frontend DMN evaluation,
     * including Gravity Forms integration and decision flow visualization.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_frontend_assets(): void
    {
        // Prevent duplicate loading
        if (self::$asset_loading_state['frontend_loaded'])
        {
            operaton_debug('Assets', 'Frontend assets already loaded, skipping');
            return;
        }

        $timer_id = $this->performance ?
            $this->performance->start_timer('frontend_assets') : null;

        // Debug bridge - Load FIRST as dependency for other scripts
        wp_enqueue_script(
            'operaton-dmn-debug',
            $this->plugin_url . 'assets/js/debug.js',
            array('jquery'),
            $this->version,
            true
        );

        // Main frontend script
        wp_enqueue_script(
            'operaton-dmn-frontend',
            $this->plugin_url . 'assets/js/frontend.js',
            array('jquery', 'operaton-dmn-debug'),
            $this->version,
            true
        );

        // Gravity Forms integration
        wp_enqueue_script(
            'operaton-dmn-gravity-integration',
            $this->plugin_url . 'assets/js/gravity-forms.js',
            array('jquery', 'operaton-dmn-debug', 'operaton-dmn-frontend'),
            $this->version,
            true
        );

        // Decision flow visualization (conditional)
        if ($this->should_load_decision_flow_assets())
        {
            wp_enqueue_script(
                'operaton-dmn-decision-flow',
                $this->plugin_url . 'assets/js/decision-flow.js',
                array('jquery', 'operaton-dmn-debug', 'operaton-dmn-frontend'),
                $this->version,
                true
            );

            wp_enqueue_style(
                'operaton-dmn-decision-flow',
                $this->plugin_url . 'assets/css/decision-flow.css',
                array(),
                $this->version
            );
        }

        // Frontend styles
        wp_enqueue_style(
            'operaton-dmn-frontend',
            $this->plugin_url . 'assets/css/frontend.css',
            array(),
            $this->version
        );

        // Localize main script with configuration
        $this->localize_frontend_script();

        if ($timer_id)
        {
            $this->performance->stop_timer($timer_id, 'Frontend assets loaded');
        }

        self::$asset_loading_state['frontend_loaded'] = true;
        operaton_debug('Assets', 'Frontend assets enqueued successfully');
    }

    /**
     * Enqueue admin assets for configuration interface
     *
     * Loads CSS and JavaScript files required for the WordPress admin
     * interface, including configuration forms and testing tools.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_admin_assets(): void
    {
        // Prevent duplicate loading
        if (self::$asset_loading_state['admin_loaded'])
        {
            operaton_debug('Assets', 'Admin assets already loaded, skipping');
            return;
        }

        // Admin styles
        wp_enqueue_style(
            'operaton-dmn-admin',
            $this->plugin_url . 'assets/css/admin.css',
            array(),
            $this->version
        );

        // Debug bridge - Load FIRST for admin too
        wp_enqueue_script(
            'operaton-dmn-debug',
            $this->plugin_url . 'assets/js/debug.js',
            array('jquery'),
            $this->version,
            true
        );

        // Main admin script
        wp_enqueue_script(
            'operaton-dmn-admin',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery', 'operaton-dmn-debug'),
            $this->version,
            true
        );

        // API testing module
        wp_enqueue_script(
            'operaton-dmn-api-test',
            $this->plugin_url . 'assets/js/api-test.js',
            array('jquery', 'operaton-dmn-debug', 'operaton-dmn-admin'),
            $this->version,
            true
        );

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

        self::$asset_loading_state['admin_loaded'] = true;
        operaton_debug('Assets', 'Admin assets enqueued successfully');
    }

    /**
     * Enqueue radio button synchronization assets
     *
     * Loads specialized assets for handling radio button synchronization
     * between Gravity Forms and DMN evaluation results.
     *
     * @since 1.0.0
     * @param int $form_id Gravity Forms form ID
     * @return void
     */
    public function enqueue_radio_sync_assets(int $form_id): void
    {
        wp_enqueue_script(
            'operaton-dmn-radio-sync',
            $this->plugin_url . 'assets/js/radio-sync.js',
            array('jquery', 'operaton-dmn-debug', 'operaton-dmn-frontend'),
            $this->version,
            true
        );

        wp_enqueue_style(
            'operaton-dmn-radio-sync',
            $this->plugin_url . 'assets/css/radio-sync.css',
            array(),
            $this->version
        );

        operaton_debug('Assets', 'Radio sync assets enqueued for form: ' . $form_id);
    }

    /**
     * Force enqueue specific asset groups
     *
     * Manually loads specific asset groups regardless of automatic detection.
     * Useful for testing and emergency fallback scenarios.
     *
     * @since 1.0.0
     * @param string $asset_group Asset group to load ('frontend', 'admin', 'decision_flow')
     * @return void
     */
    public function force_enqueue(string $asset_group): void
    {
        switch ($asset_group)
        {
            case 'frontend':
                $this->enqueue_frontend_assets();
                break;

            case 'admin':
                $this->enqueue_admin_assets();
                break;

            case 'decision_flow':
                // Ensure debug is loaded first
                wp_enqueue_script(
                    'operaton-dmn-debug',
                    $this->plugin_url . 'assets/js/debug.js',
                    array('jquery'),
                    $this->version,
                    true
                );

                wp_enqueue_script(
                    'operaton-dmn-decision-flow',
                    $this->plugin_url . 'assets/js/decision-flow.js',
                    array('jquery', 'operaton-dmn-debug', 'operaton-dmn-frontend'),
                    $this->version,
                    true
                );

                wp_enqueue_style(
                    'operaton-dmn-decision-flow',
                    $this->plugin_url . 'assets/css/decision-flow.css',
                    array(),
                    $this->version
                );
                break;
        }

        operaton_debug('Assets', 'Force enqueued asset group: ' . $asset_group);
    }

    /**
     * Get current assets loading status
     *
     * Returns comprehensive status information for debugging and monitoring
     * purposes, including cache state and WordPress asset registration status.
     *
     * @since 1.0.0
     * @return array Status information array
     */
    public function get_status(): array
    {
        global $wp_scripts, $wp_styles;

        return array(
            'detection_complete' => self::$detection_complete,
            'cache_age' => self::$cache_timestamp ? time() - self::$cache_timestamp : 0,
            'cache_entries' => count(self::$detection_cache),
            'should_load' => self::$detection_cache['should_load_assets'] ?? null,
            'loading_state' => self::$asset_loading_state,
            'wordpress_states' => array(
                'frontend_registered' => wp_script_is('operaton-dmn-frontend', 'registered'),
                'frontend_enqueued' => wp_script_is('operaton-dmn-frontend', 'enqueued'),
                'frontend_done' => wp_script_is('operaton-dmn-frontend', 'done'),
                'jquery_available' => wp_script_is('jquery', 'done') || wp_script_is('jquery', 'enqueued')
            ),
            'localized_scripts' => count(self::$localized_configs),
            'context' => array(
                'is_admin' => is_admin(),
                'is_ajax' => wp_doing_ajax(),
                'current_screen' => is_admin() && function_exists('get_current_screen') ?
                    get_current_screen()->id ?? 'unknown' : 'frontend'
            )
        );
    }

    /**
     * Clear form-specific caches
     *
     * Clears cached detection results for specific forms or all forms,
     * forcing fresh detection on next request.
     *
     * @since 1.0.0
     * @param int|null $form_id Form ID to clear cache for, or null for all
     * @return void
     */
    public function clear_form_cache(?int $form_id = null): void
    {
        if ($form_id)
        {
            // Clear specific form cache entries
            $keys_to_remove = array();
            foreach (self::$detection_cache as $key => $value)
            {
                if (strpos($key, 'form_' . $form_id) !== false)
                {
                    $keys_to_remove[] = $key;
                }
            }

            foreach ($keys_to_remove as $key)
            {
                unset(self::$detection_cache[$key]);
            }

            operaton_debug('Assets', 'Cleared cache for form: ' . $form_id);
        }
        else
        {
            // Clear all cache
            self::$detection_cache = array();
            self::$detection_complete = false;
            self::$cache_timestamp = time();

            operaton_debug('Assets', 'Cleared all asset detection cache');
        }
    }

    // =============================================================================
    // WORDPRESS HOOKS & CALLBACKS
    // =============================================================================

    /**
     * Add jQuery compatibility information to footer
     *
     * Provides compatibility information for debugging jQuery-related issues
     * and ensures proper fallback handling.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_jquery_compatibility(): void
    {
        if (!self::should_load_frontend_assets())
        {
            return;
        }

?>
        <script type="text/javascript">
            (function() {
                window.operatonCompatibilityInfo = {
                    jQueryVersion: typeof jQuery !== 'undefined' ? jQuery.fn.jquery : 'none',
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

    // =============================================================================
    // PRIVATE HELPER METHODS
    // =============================================================================

    /**
     * Perform intelligent asset detection
     *
     * Core detection logic that determines if DMN assets should be loaded
     * based on page content, Gravity Forms presence, and configuration.
     *
     * @since 1.0.0
     * @return bool True if assets should be loaded
     */
    private static function perform_asset_detection(): bool
    {
        // Check for Gravity Forms shortcodes in content
        if (self::has_gravity_forms_shortcode())
        {
            return true;
        }

        // Check for Gravity Forms blocks
        if (self::has_gravity_forms_block())
        {
            return true;
        }

        // Check for DMN-enabled forms in widgets
        if (self::has_dmn_forms_in_widgets())
        {
            return true;
        }

        // Special pages that might need assets
        if (self::is_special_page_requiring_assets())
        {
            return true;
        }

        return false;
    }

    /**
     * Check if current page has Gravity Forms shortcode
     *
     * Scans post content for Gravity Forms shortcodes that might contain
     * DMN-enabled forms.
     *
     * @since 1.0.0
     * @return bool True if shortcode found
     */
    private static function has_gravity_forms_shortcode(): bool
    {
        global $post;

        if (!$post || !$post->post_content)
        {
            return false;
        }

        // Check for gravity form shortcodes
        if (
            has_shortcode($post->post_content, 'gravityform') ||
            has_shortcode($post->post_content, 'gravityforms')
        )
        {
            return true;
        }

        return false;
    }

    /**
     * Check if current page has Gravity Forms blocks
     *
     * Scans for Gravity Forms Gutenberg blocks that might contain
     * DMN-enabled forms.
     *
     * @since 1.0.0
     * @return bool True if block found
     */
    private static function has_gravity_forms_block(): bool
    {
        global $post;

        if (!$post || !$post->post_content)
        {
            return false;
        }

        // Check for Gravity Forms blocks
        return has_block('gravityforms/form', $post);
    }

    /**
     * Check if DMN forms are present in widgets
     *
     * Scans active widgets for Gravity Forms widgets containing
     * DMN-enabled forms.
     *
     * @since 1.0.0
     * @return bool True if DMN forms found in widgets
     */
    private static function has_dmn_forms_in_widgets(): bool
    {
        // This could be expanded to check widgets if needed
        // For now, return false to avoid unnecessary complexity
        return false;
    }

    /**
     * Check if current page is special page requiring assets
     *
     * Identifies special pages that might need DMN assets loaded
     * even without explicit form detection.
     *
     * @since 1.0.0
     * @return bool True if special page
     */
    private static function is_special_page_requiring_assets(): bool
    {
        // Check for specific page templates or conditions
        if (is_page_template('page-dmn.php'))
        {
            return true;
        }

        // Check for specific query parameters
        if (isset($_GET['dmn_preview']) || isset($_GET['force_dmn_assets']))
        {
            return true;
        }

        return false;
    }

    /**
     * Determine if decision flow assets should be loaded
     *
     * Checks configuration and context to determine if decision flow
     * visualization assets are needed.
     *
     * @since 1.0.0
     * @return bool True if decision flow assets needed
     */
    private function should_load_decision_flow_assets(): bool
    {
        // Check if any configurations have decision flow enabled
        if ($this->gravity_forms_manager)
        {
            // This would need to be implemented in the gravity forms manager
            // For now, assume we need it if we're loading other assets
            return true;
        }

        return false;
    }

    /**
     * Localize frontend script with essential configuration
     *
     * Provides JavaScript configuration including AJAX endpoints,
     * nonces, and localized strings for frontend functionality.
     *
     * @since 1.0.0
     * @return void
     */
    private function localize_frontend_script(): void
    {
        // Check if already localized to prevent duplicates
        if (wp_scripts()->get_data('operaton-dmn-frontend', 'data'))
        {
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
                'connection_error' => __('Connection error. Please try again.', 'operaton-dmn'),
                'timeout_error' => __('Request timeout. Please try again.', 'operaton-dmn')
            ),
            'timeouts' => array(
                'evaluation' => 30000, // 30 seconds
                'test' => 10000       // 10 seconds
            )
        ));

        operaton_debug('Assets', 'Frontend script localized with configuration');
    }

    /**
     * Localize configuration for specific forms
     *
     * Provides form-specific configuration to JavaScript for DMN evaluation
     * including endpoints, process keys, and form-specific settings.
     *
     * @since 1.0.0
     * @param string $handle Script handle to localize
     * @param object $config Configuration object
     * @param int $form_id Gravity Forms form ID
     * @return void
     */
    public function localize_configuration(string $handle, object $config, int $form_id): void
    {
        $config_key = $handle . '_' . $form_id;

        // Prevent duplicate localization
        if (isset(self::$localized_configs[$config_key]))
        {
            operaton_debug('Assets', 'Configuration already localized for: ' . $config_key);
            return;
        }

        $localization_data = array(
            'endpoint' => $config->dmn_endpoint ?? '',
            'process_endpoint' => $config->process_endpoint ?? '',
            'decision_key' => $config->decision_key ?? '',
            'process_key' => $config->process_key ?? '',
            'use_process' => !empty($config->use_process),
            'show_decision_flow' => !empty($config->show_decision_flow),
            'form_id' => $form_id,
            'config_id' => $config->id ?? 0,
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        );

        wp_localize_script($handle, 'operaton_config_' . $form_id, $localization_data);

        // Track successful localization
        self::$localized_configs[$config_key] = array(
            'timestamp' => time(),
            'handle' => $handle,
            'form_id' => $form_id,
            'config_id' => $config->id ?? 0
        );

        operaton_debug('Assets', 'Configuration localized - Form: ' . $form_id .
            ' | Handle: ' . $handle . ' | Endpoint: ' . ($config->dmn_endpoint ?? 'NONE'));
    }

    /**
     * Add inline styles for theme customization
     *
     * Applies custom CSS styles for theme integration and visual customization
     * of DMN evaluation interface elements.
     *
     * @since 1.0.0
     * @param int|null $form_id Form ID for form-specific styles
     * @param array $styles Array of style definitions
     * @return void
     */
    public function add_inline_styles(?int $form_id = null, array $styles = array()): void
    {
        $css = '';

        // Global theme variables
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
        }

        if (!empty($css))
        {
            $handle = is_admin() ? 'operaton-dmn-admin' : 'operaton-dmn-frontend';
            wp_add_inline_style($handle, $css);

            operaton_debug('Assets', 'Inline styles added for form: ' . ($form_id ?? 'global'));
        }
    }

    /**
     * Check if cache has expired
     *
     * Determines if the current cache is still valid based on
     * configurable expiration time.
     *
     * @since 1.0.0
     * @param int $max_age Maximum cache age in seconds (default: 300)
     * @return bool True if cache is expired
     */
    private static function is_cache_expired(int $max_age = 300): bool
    {
        if (!self::$cache_timestamp)
        {
            return true;
        }

        return (time() - self::$cache_timestamp) > $max_age;
    }

    /**
     * Safe JSON decode with fallback
     *
     * Safely decodes JSON strings with error handling and default fallback
     * to prevent PHP errors from malformed JSON data.
     *
     * @since 1.0.0
     * @param string $json_string JSON string to decode
     * @param mixed $default Default value if decode fails
     * @return mixed Decoded data or default value
     */
    private function safe_json_decode(string $json_string, $default = array())
    {
        if (empty($json_string))
        {
            return $default;
        }

        $decoded = json_decode($json_string, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $default;
    }

    // =============================================================================
    // UTILITY AND DEBUG METHODS
    // =============================================================================

    /**
     * Generate unique request identifier
     *
     * Creates a unique identifier for the current request to prevent
     * cache conflicts and enable request-specific tracking.
     *
     * @since 1.0.0
     * @return string Unique request identifier
     */
    private static function generate_request_id(): string
    {
        return md5(
            $_SERVER['REQUEST_URI'] ?? '' .
                $_SERVER['REQUEST_METHOD'] ?? '' .
                $_SERVER['QUERY_STRING'] ?? '' .
                microtime()
        );
    }

    /**
     * Reset all loading states for testing
     *
     * Resets all caches and loading states, useful for testing
     * and development scenarios where fresh detection is needed.
     *
     * @since 1.0.0
     * @return void
     */
    public function reset_all_loading_states(): void
    {
        self::$detection_cache = array();
        self::$cache_timestamp = time();
        self::$detection_complete = false;
        self::$localized_configs = array();
        self::$asset_loading_state = array(
            'frontend_loaded' => false,
            'admin_loaded' => false,
            'decision_flow_loaded' => false
        );

        operaton_debug('Assets', 'All loading states reset');
    }

    /**
     * Log comprehensive performance statistics
     *
     * Outputs detailed performance and status information for debugging
     * and optimization purposes when debug mode is enabled.
     *
     * @since 1.0.0
     * @return void
     */
    public function log_performance(): void
    {
        if (!operaton_debug_manager()->get_debug_level())
        {
            return;
        }

        $status = $this->get_status();

        operaton_debug_verbose('Assets', '=== OPERATON DMN ASSETS PERFORMANCE REPORT ===');
        operaton_debug_verbose('Assets', 'Detection Complete: ' . ($status['detection_complete'] ? 'YES' : 'NO'));
        operaton_debug_verbose('Assets', 'Cache Age: ' . $status['cache_age'] . 's');
        operaton_debug_verbose('Assets', 'Cache Entries: ' . $status['cache_entries']);
        operaton_debug_verbose('Assets', 'Should Load: ' . ($status['should_load'] ? 'YES' : 'NO'));
        operaton_debug_verbose('Assets', 'Loading State', $status['loading_state']);
        operaton_debug_verbose('Assets', 'WordPress States', $status['wordpress_states']);
        operaton_debug_verbose('Assets', 'Context', $status['context']);
        operaton_debug_verbose('Assets', '===============================================');
    }

    // =============================================================================
    // BACKWARD COMPATIBILITY METHODS (REQUIRED BY OTHER CLASSES)
    // =============================================================================

    /**
     * Get plugin URL for external access
     *
     * Provides access to the base plugin URL for other classes that need
     * to construct asset paths or perform URL-based operations.
     *
     * @since 1.0.0
     * @return string Base plugin URL with trailing slash
     */
    public function get_plugin_url(): string
    {
        return $this->plugin_url;
    }

    /**
     * Get plugin version for external access
     *
     * Provides access to the plugin version string for other classes
     * that need version information for cache busting or compatibility checks.
     *
     * @since 1.0.0
     * @return string Plugin version string
     */
    public function get_version(): string
    {
        return $this->version;
    }

    /**
     * Legacy method compatibility for existing integrations
     *
     * Provides backward compatibility for methods that may be called
     * by existing code that hasn't been updated yet.
     *
     * @since 1.0.0
     * @return array Current loading status
     */
    public function get_loading_state(): array
    {
        return $this->get_status();
    }

    /**
     * Clear form-specific localization cache
     *
     * Clears localized script configurations for a specific form,
     * forcing fresh localization on next request.
     *
     * @since 1.0.0
     * @param int $form_id Form ID to clear localization cache for
     * @return void
     */
    public function clear_form_localization_cache(int $form_id): void
    {
        // Clear form-specific cache entries
        $keys_to_remove = array();
        foreach (self::$localized_configs as $key => $data)
        {
            if (
                strpos($key, 'form_' . $form_id . '_') === 0 ||
                strpos($key, '_' . $form_id) !== false
            )
            {
                $keys_to_remove[] = $key;
            }
        }

        foreach ($keys_to_remove as $key)
        {
            unset(self::$localized_configs[$key]);
        }

        operaton_debug('Assets', 'Cleared localization cache for form: ' . $form_id);
    }

    /**
     * Clear all localization cache
     *
     * Clears all localized script configurations, forcing fresh
     * localization for all forms on next request. Called during
     * plugin deactivation and cache clearing operations.
     *
     * @since 1.0.0
     * @return void
     */
    public function clear_all_localization_cache(): void
    {
        self::$localized_configs = array();

        // Also reset other cache-related properties
        self::$detection_cache = array();
        self::$detection_complete = false;
        self::$cache_timestamp = time();
        self::$asset_loading_state = array(
            'frontend_loaded' => false,
            'admin_loaded' => false,
            'decision_flow_loaded' => false
        );

        operaton_debug('Assets', 'Cleared all localization cache and reset loading states');
    }

    /**
     * Static coordinator status method for backward compatibility
     *
     * Provides static access to status information for legacy code
     * that expects the old static coordinator pattern.
     *
     * @since 1.0.0
     * @return array Status information
     */
    public static function get_coordinator_status(): array
    {
        // Create temporary instance for status if needed
        static $temp_instance = null;
        if ($temp_instance === null)
        {
            $temp_instance = new self('', '1.0.0');
        }
        return $temp_instance->get_status();
    }

    /**
     * Reset loading coordinator for backward compatibility
     *
     * Provides static method for resetting loading states, maintaining
     * compatibility with existing code patterns.
     *
     * @since 1.0.0
     * @return void
     */
    public static function reset_loading_coordinator(): void
    {
        self::$detection_cache = array();
        self::$cache_timestamp = time();
        self::$detection_complete = false;
        self::$localized_configs = array();
        self::$asset_loading_state = array(
            'frontend_loaded' => false,
            'admin_loaded' => false,
            'decision_flow_loaded' => false
        );
    }
}
