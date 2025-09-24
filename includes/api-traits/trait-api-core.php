<?php

/**
 * Operaton DMN API Core Trait
 *
 * Handles core class properties, constructor, and fundamental WordPress
 * integration including hooks setup and timeout management.
 *
 * @package OperatonDMN
 * @subpackage API\Traits
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}

/**
 * Core API functionality trait
 *
 * Provides the fundamental class properties, constructor logic, and
 * WordPress integration setup for the DMN API manager.
 *
 * @since 1.0.0
 */
trait Operaton_DMN_API_Core
{
    // =============================================================================
    // CLASS PROPERTIES & CONFIGURATION
    // =============================================================================

    /**
     * Core plugin instance reference
     *
     * Provides access to centralized plugin functionality and manager instances
     * for coordinated operations across the plugin ecosystem.
     *
     * @var OperatonDMNEvaluator
     * @since 1.0.0
     */
    private $core;

    /**
     * Database manager instance reference
     *
     * Handles configuration storage, retrieval, caching, and data persistence
     * operations for DMN configurations and evaluation results.
     *
     * @var Operaton_DMN_Database
     * @since 1.0.0
     */
    private $database;

    /**
     * HTTP connection timeout in seconds
     *
     * Configurable timeout for external API calls to Operaton DMN engines.
     * Initialized from database settings with fallback to default value.
     *
     * @var int
     * @since 1.0.0
     */
    private $connection_timeout = 30;

    // =============================================================================
    // CORE INITIALIZATION & WORDPRESS INTEGRATION
    // =============================================================================

    /**
     * Constructor for API handler
     *
     * Initializes API functionality with required dependencies including core
     * plugin instance and database manager. Sets up connection timeout from
     * saved settings and initializes WordPress hooks for REST API routes and
     * AJAX handlers.
     *
     * @param OperatonDMNEvaluator $core Core plugin instance
     * @param Operaton_DMN_Database $database Database manager instance
     * @since 1.0.0
     */
    public function __construct($core, $database)
    {
        $this->core = $core;
        $this->database = $database;

        // Initialize connection timeout from saved setting
        $this->init_connection_timeout();

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Handler initialized');
        }

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks for API functionality
     *
     * Sets up REST API routes and AJAX handlers for evaluation requests,
     * configuration testing, debug operations, decision flow endpoints,
     * and administrative functions. Registers both authenticated and
     * public AJAX handlers as needed.
     *
     * @since 1.0.0
     */
    private function init_hooks()
    {
        // REST API hooks
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // AJAX handlers
        add_action('wp_ajax_operaton_test_endpoint', array($this, 'ajax_test_endpoint'));
        add_action('wp_ajax_nopriv_operaton_test_endpoint', array($this, 'ajax_test_endpoint'));
        add_action('wp_ajax_operaton_test_full_config', array($this, 'ajax_test_full_config'));
        add_action('wp_ajax_operaton_clear_update_cache', array($this, 'ajax_clear_update_cache'));

        // Enhanced configuration testing
        add_action('wp_ajax_operaton_test_configuration_complete', array($this, 'ajax_test_configuration_complete'));

        // API Debug tests
        add_action('wp_ajax_operaton_dmn_debug', array($this, 'handle_dmn_debug_ajax'));
        add_action('wp_ajax_nopriv_operaton_dmn_debug', array($this, 'run_operaton_dmn_debug'));

        // Decision flow REST endpoint
        add_action('rest_api_init', array($this, 'register_decision_flow_endpoint'));
    }

    /**
     * Initialize connection timeout from database settings
     *
     * Loads connection timeout setting from database with fallback to default
     * value of 30 seconds. Ensures timeout is within reasonable bounds to
     * prevent excessively long waits or premature timeouts.
     *
     * @since 1.0.0
     */
    private function init_connection_timeout()
    {
        $timeout = get_option('operaton_dmn_connection_timeout', 30);
        $this->connection_timeout = max(5, min(300, intval($timeout))); // Between 5-300 seconds

        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            error_log('Operaton DMN API: Connection timeout initialized to ' . $this->connection_timeout . ' seconds');
        }
    }
}
