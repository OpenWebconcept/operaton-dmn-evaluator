<?php

/**
 * Core API functionality trait for Operaton DMN Plugin
 *
 * Contains constructor, properties initialization, and core setup methods.
 * Handles the fundamental API initialization and dependency injection.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Operaton_DMN_API_Core
{
    /**
     * Core plugin instance reference
     * Provides access to main plugin functionality and configuration
     *
     * @var OperatonDMNEvaluator
     * @since 1.0.0
     */
    private $core;

    /**
     * Database manager instance
     * Handles configuration retrieval and process tracking
     *
     * @var Operaton_DMN_Database
     * @since 1.0.0
     */
    private $database;

    /**
     * API request timeout in seconds
     * Default timeout for external API calls
     *
     * @var int
     * @since 1.0.0
     */
    private $api_timeout = 30;

    /**
     * SSL verification setting for API calls
     * Should be true in production, false for development
     *
     * @var bool
     * @since 1.0.0
     */
    private $ssl_verify = false;

    /**
     * HTTP connection pool for reusing connections to the same host
     * @var array
     */
    private static $connection_pool = array();

    /**
     * Connection pool statistics for monitoring
     * @var array
     */
    private static $pool_stats = array(
        'hits' => 0,
        'misses' => 0,
        'created' => 0,
        'cleaned' => 0
    );

    /**
     * Maximum age for pooled connections (in seconds)
     * @var int
     */
    private $connection_max_age = 300; // 5 minutes

    /**
     * Maximum number of connections per host
     * @var int
     */
    private $max_connections_per_host = 3;

    /**
     * Constructor for API handler
     * Initializes API functionality with required dependencies
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

        operaton_debug('API', 'Handler initialized');

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks for API functionality
     * Sets up REST API routes and AJAX handlers
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

        // NEW: Enhanced configuration testing
        add_action('wp_ajax_operaton_test_configuration_complete', array($this, 'ajax_test_configuration_complete'));

        // API Debug tests
        add_action('wp_ajax_operaton_dmn_debug', array($this, 'handle_dmn_debug_ajax'));
        add_action('wp_ajax_nopriv_operaton_dmn_debug', array($this, 'run_operaton_dmn_debug'));

        // Decision flow REST endpoint
        add_action('rest_api_init', array($this, 'register_decision_flow_endpoint'));
    }

    /**
     * Initialize connection timeout from saved setting
     * Retrieves and applies saved timeout configuration
     *
     * @since 1.0.0
     */
    private function init_connection_timeout()
    {
        $saved_timeout = get_option('operaton_connection_timeout', 300);
        $this->set_connection_pool_timeout($saved_timeout);
    }
}
