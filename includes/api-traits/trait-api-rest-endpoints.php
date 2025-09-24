<?php

/**
 * Operaton DMN API REST Endpoints Trait
 *
 * Handles registration of all REST API endpoints including evaluation,
 * health monitoring, testing, and decision flow endpoints.
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
 * REST API endpoints registration trait
 *
 * Provides REST API route registration for DMN evaluation endpoints,
 * health monitoring, testing utilities, and decision flow visualization.
 *
 * @since 1.0.0
 */
trait Operaton_DMN_API_Rest_Endpoints
{
    // =============================================================================
    // REST API ENDPOINTS & REGISTRATION
    // =============================================================================

    /**
     * Register all REST API routes for DMN evaluation and testing
     *
     * Creates REST endpoints for evaluation requests, health monitoring,
     * and testing functionality. Routes include proper parameter validation,
     * permission callbacks, and comprehensive argument sanitization.
     *
     * @since 1.0.0
     */
    public function register_rest_routes()
    {
        // Main evaluation endpoint
        register_rest_route('operaton-dmn/v1', '/evaluate', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_evaluation'),
            'permission_callback' => '__return_true',
            'args' => array(
                'config_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                ),
                'form_data' => array(
                    'required' => true,
                    'type' => 'object'
                )
            )
        ));

        // Test endpoint for connectivity verification
        register_rest_route('operaton-dmn/v1', '/test', array(
            'methods' => 'GET',
            'callback' => function ($request)
            {
                return array(
                    'status' => 'OK',
                    'message' => __('Operaton DMN API is operational', 'operaton-dmn'),
                    'version' => OPERATON_DMN_VERSION,
                    'timestamp' => current_time('mysql')
                );
            },
            'permission_callback' => '__return_true'
        ));
        error_log('Operaton DMN API: Test route registered');

        // Health endpoint for monitoring and load testing
        $health_registered = register_rest_route('operaton-dmn/v1', '/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true',
            'args' => array(
                'detailed' => array(
                    'description' => 'Include detailed health information',
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ),
            ),
        ));

        if ($health_registered)
        {
            error_log('Operaton DMN API: Health route registered successfully');
        }
        else
        {
            error_log('Operaton DMN API: Health route registration FAILED');
        }

        // Check if the health_check method exists
        if (method_exists($this, 'health_check'))
        {
            error_log('Operaton DMN API: health_check method exists');
        }
        else
        {
            error_log('Operaton DMN API: health_check method DOES NOT EXIST');
        }
    }

    /**
     * Register decision flow REST endpoint separately for modular loading
     *
     * Creates endpoint for decision flow summary retrieval with proper
     * parameter validation and form ID sanitization. Supports decision
     * flow visualization and monitoring functionality.
     *
     * @since 1.0.0
     */
    public function register_decision_flow_endpoint()
    {
        register_rest_route('operaton-dmn/v1', '/decision-flow/(?P<form_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_decision_flow'),
            'permission_callback' => '__return_true',
            'args' => array(
                'form_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint'
                )
            )
        ));
    }
}
