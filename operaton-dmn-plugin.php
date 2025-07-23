<?php
/**
 * Plugin Name: Operaton DMN Evaluator
 * Plugin URI: https://git.open-regels.nl/showcases/operaton-dmn-evaluator
 *
 * Enhanced Operaton DMN Evaluator v1.0.0-beta.9 with Process Integration
 * 
 * Key Changes:
 * 1. Added process execution support alongside decision evaluation
 * 2. Added decision flow results summary display
 * 3. Enhanced configuration to support both modes
 * 4. Added third page summary functionality
 * 
 * Description: WordPress plugin to integrate Gravity Forms with Operaton DMN decision tables for dynamic form evaluations.
 * Version: 1.0.0-beta.9
 * Author: Steven Gort
 * License: EU PL v1.2
 * Text Domain: operaton-dmn
 * Update URI: https://git.open-regels.nl/showcases/operaton-dmn-evaluator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OPERATON_DMN_VERSION', '1.0.0-beta.9');
define('OPERATON_DMN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OPERATON_DMN_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Initialize the update checker - CLEAN VERSION
if (is_admin()) {
    // Only load auto-updater in admin context
    $updater_file = OPERATON_DMN_PLUGIN_PATH . 'includes/plugin-updater.php';
    
    if (file_exists($updater_file)) {
        require_once $updater_file;
        
        // IMPORTANT: Initialize with the MAIN plugin file, not the updater file
        new OperatonDMNAutoUpdater(__FILE__, OPERATON_DMN_VERSION);
        
        // Log successful loading if debug is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Auto-updater loaded successfully');
            error_log('Operaton DMN: Plugin file for updater: ' . __FILE__);
            error_log('Operaton DMN: Plugin basename: ' . plugin_basename(__FILE__));
        }
        
        // Add debug information to admin
        add_action('admin_footer', function() {
            if (current_user_can('manage_options') && isset($_GET['page']) && strpos($_GET['page'], 'operaton-dmn') !== false) {
                echo '<script>console.log("Operaton DMN Auto-Updater: Loaded");</script>';
            }
        });
        
    } else {
        // Log missing file if debug is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Auto-updater file not found at: ' . $updater_file);
        }
        
        // Show admin notice about missing auto-updater
        add_action('admin_notices', function() {
            if (current_user_can('manage_options')) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>Operaton DMN Evaluator:</strong> Auto-update system files are missing. Please reinstall the plugin to enable automatic updates.</p>';
                echo '</div>';
            }
        });
    }
    
    // Load debug tools if in debug mode (remove in production)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Operaton DMN: WP_DEBUG is enabled, attempting to load debug tools');
        $debug_file = OPERATON_DMN_PLUGIN_PATH . 'includes/update-debug.php';
        error_log('Operaton DMN: Debug file path: ' . $debug_file);
    
        if (file_exists($debug_file)) {
            error_log('Operaton DMN: Debug file exists, loading...');
            require_once $debug_file;
            error_log('Operaton DMN: Debug file loaded successfully');
        } else {
            error_log('Operaton DMN: Debug file NOT found at: ' . $debug_file);
        }
    }
}

/**
 * Main plugin class
 */
class OperatonDMNEvaluator {
    
    private static $instance = null;

    private $assets;
    private $admin;
    private $database;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // =============================================================================
    // CORE WORDPRESS METHODS (Init, Hooks)
    // =============================================================================

    /**
     * Plugin constructor that initializes WordPress hooks and activation handlers.
     * Sets up the plugin instance with all necessary WordPress integration points.
     * 
     * @since 1.0.0
     */
private function __construct() {
    // 1. Load assets manager first
    $this->load_assets_manager();
    
    // 2. Load admin manager second (depends on assets)
    $this->load_admin_manager();

    // 3. Load database manager third
    $this->load_database_manager();

    // Core WordPress hooks
    add_action('init', array($this, 'init'));
    add_action('rest_api_init', array($this, 'register_rest_routes'));
 
    // Database and version checks (admin only)
    if (is_admin()) {
        add_action('admin_init', array($this->database, 'check_and_update_database'), 1);
        add_action('admin_init', array($this, 'check_version'), 5);
    }
    
    // Cleanup scheduled task
    add_action('operaton_dmn_cleanup', array($this->database, 'cleanup_old_data'));
    
    // TEMPORARY: Clear decision flow cache
    add_action('admin_init', function() {
        if (isset($_GET['clear_operaton_cache'])) {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_operaton_%'");
            wp_redirect(admin_url('admin.php?page=operaton-dmn&cache_cleared=1'));
            exit;
        }
    });

    // Plugin lifecycle hooks
    register_activation_hook(__FILE__, array($this, 'activate'));
    register_deactivation_hook(__FILE__, array($this, 'deactivate'));
}

    // NEW: Add this method
    private function load_assets_manager() {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-assets.php';
        $this->assets = new Operaton_DMN_Assets(OPERATON_DMN_PLUGIN_URL, OPERATON_DMN_VERSION);
    }

    // NEW: Add this after loading the assets manager
    private function load_admin_manager() {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-admin.php';
        $this->admin = new Operaton_DMN_Admin($this, $this->assets);
    }

    // NEW: Add this method after load_admin_manager()
    private function load_database_manager() {
        require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-database.php';
        $this->database = new Operaton_DMN_Database(OPERATON_DMN_VERSION);
    }

    /**
    * Get database instance for external access
    * Provides access to database manager for other components
    * 
    * @return Operaton_DMN_Database Database manager instance
    * @since 1.0.0
    */
    public function get_database_instance() {
        return $this->database;
    }

    /**
     * Initialize plugin textdomain for internationalization support.
     * Loads translation files from the plugin's languages directory.
     * 
     * @since 1.0.0
     */
    public function init() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Initializing plugin textdomain');
        }
        
        load_plugin_textdomain('operaton-dmn', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Enhanced activation hook that creates database tables and sets default options.
     * Initializes plugin data structures and schedules cleanup tasks.
     * 
     * @since 1.0.0
     */
    public function activate() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Plugin activation started');
        }
        
        // Create/update database tables - NOW USES DATABASE CLASS
        $this->database->create_database_tables();
        
        // Set default options
        add_option('operaton_dmn_version', OPERATON_DMN_VERSION);
        add_option('operaton_dmn_activated', current_time('mysql'));
        
        // Schedule cleanup cron job
        if (!wp_next_scheduled('operaton_dmn_cleanup')) {
            wp_schedule_event(time(), 'daily', 'operaton_dmn_cleanup');
        }
        
        flush_rewrite_rules();
    }

    /**
     * Enhanced deactivation hook that cleans up scheduled events and cached data.
     * Removes plugin-specific cron jobs and clears configuration cache.
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Plugin deactivation started');
        }
        
        // Clear scheduled events
        wp_clear_scheduled_hook('operaton_dmn_cleanup');
        
        // Clear any cached data - NOW USES DATABASE CLASS
        $this->database->clear_configuration_cache();
        
        flush_rewrite_rules();
    }

    /**
     * Register REST API routes for DMN evaluation and testing endpoints.
     * Creates public endpoints for form evaluation and debug functionality.
     * 
     * @since 1.0.0
     */
    public function register_rest_routes() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Registering REST API routes');
        }
        
        // Register the REST API route - make sure this matches what we're calling from frontend
        register_rest_route('operaton-dmn/v1', '/evaluate', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_evaluation'),
            'permission_callback' => '__return_true',
            'args' => array(
                'config_id' => array(
                    'required' => true,
                    'type' => 'integer',
                ),
                'form_data' => array(
                    'required' => true,
                    'type' => 'object',
                )
            )
        ));
        
        // Add a test endpoint for debugging
        register_rest_route('operaton-dmn/v1', '/test', array(
            'methods' => 'GET',
            'callback' => function() {
                return array('status' => 'Plugin REST API is working!');
            },
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * Version check method that triggers automatic database migration on upgrades.
     * Compares installed version with current version and runs migrations as needed.
     * 
     * @since 1.0.0
     */
    public function check_version() {
        $installed_version = get_option('operaton_dmn_version', '1.0.0-beta.1');
        
        if (version_compare($installed_version, OPERATON_DMN_VERSION, '<')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: Version upgrade detected from ' . $installed_version . ' to ' . OPERATON_DMN_VERSION);
            }
            
            // Run database migration for any version upgrade - NOW USES DATABASE CLASS
            $this->database->check_and_update_database();
            
            // Update stored version
            update_option('operaton_dmn_version', OPERATON_DMN_VERSION);
            
            error_log('Operaton DMN: Upgraded from ' . $installed_version . ' to ' . OPERATON_DMN_VERSION);
        }
    }

/**
 * Ensure frontend assets are loaded when Gravity Forms renders
 * This is a safety net to ensure operaton_ajax is always available
 * 
 * @since 1.0.0
 */
public function force_frontend_assets_on_gravity_forms() {
    if (!is_admin() && class_exists('GFForms')) {
        // Add debug call
        $this->debug_assets_loading();
        
        // Force load frontend assets which includes operaton_ajax localization
        $this->assets->enqueue_frontend_assets();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Forced frontend assets loading due to Gravity Forms presence');
        }
    }
}

    // =============================================================================
    // API/EXTERNAL SERVICE METHODS
    // =============================================================================

    /**
     * Enhanced evaluation handler that routes to either process execution or direct decision evaluation.
     * Main REST API endpoint that determines evaluation method based on configuration settings.
     * 
     * @param WP_REST_Request $request REST API request object containing config ID and form data
     * @return WP_REST_Response|WP_Error Evaluation results or error response
     * @since 1.0.0
     */
    public function handle_evaluation($request) {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: Handling evaluation request');
            }
            
            $params = $request->get_json_params();
            
            if (!isset($params['config_id']) || !isset($params['form_data'])) {
                return new WP_Error('missing_params', 'Configuration ID and form data are required', array('status' => 400));
            }
            
            $config = $this->database->get_configuration($params['config_id']);
            if (!$config) {
                return new WP_Error('invalid_config', 'Configuration not found', array('status' => 404));
            }
            
            // NEW: Check if we should use process execution
            $use_process = isset($config->use_process) ? $config->use_process : false;
            
            if ($use_process && !empty($config->process_key)) {
                return $this->handle_process_execution($config, $params['form_data']);
            } else {
                return $this->handle_decision_evaluation($config, $params['form_data']);
            }
            
        } catch (Exception $e) {
            return new WP_Error('server_error', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Handle process execution using Operaton's process engine with variable extraction and storage.
     * Starts a process instance, waits for completion, and extracts results from process variables.
     * 
     * @param object $config Configuration object containing process settings
     * @param array $form_data Form data to be passed as process variables
     * @return array Process execution results with extracted variables
     * @since 1.0.0
     */
    private function handle_process_execution($config, $form_data) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Starting process execution for key: ' . $config->process_key);
        }
        
        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_mappings', 'Invalid field mappings configuration', array('status' => 500));
        }

        // Process input variables
        $variables = array();
        
        foreach ($field_mappings as $dmn_variable => $form_field) {
            $value = isset($form_data[$dmn_variable]) ? $form_data[$dmn_variable] : null;
            
            if ($value === null || $value === 'null' || $value === '') {
                $variables[$dmn_variable] = array(
                    'value' => null,
                    'type' => $form_field['type']
                );
                continue;
            }
            
            // Type conversion
            switch ($form_field['type']) {
                case 'Integer':
                    $value = intval($value);
                    break;
                case 'Double':
                    $value = floatval($value);
                    break;
                case 'Boolean':
                    if (is_string($value)) {
                        $value = strtolower($value);
                        $value = ($value === 'true' || $value === '1') ? true : false;
                    }
                    break;
                default:
                    $value = sanitize_text_field($value);
            }
            
            $variables[$dmn_variable] = array(
                'value' => $value,
                'type' => $form_field['type']
            );
        }

        // Build process start endpoint
        $base_url = rtrim($config->dmn_endpoint, '/');
        $base_url = str_replace('/decision-definition/key', '', $base_url);
        $base_url = str_replace('/decision-definition', '', $base_url);
        
        if (strpos($base_url, '/engine-rest') === false) {
            $base_url .= '/engine-rest';
        }
        
        $process_endpoint = $base_url . '/process-definition/key/' . $config->process_key . '/start';
        
        error_log('Operaton DMN: Starting process at: ' . $process_endpoint);
        
        // Start the process
        $process_data = array('variables' => $variables);
        
        $response = wp_remote_post($process_endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode($process_data),
            'timeout' => 30,
            'sslverify' => false,
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'Failed to start process: ' . $response->get_error_message(), array('status' => 500));
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200 && $http_code !== 201) {
            return new WP_Error('api_error', sprintf('Process start failed with status %d: %s', $http_code, $body), array('status' => 500));
        }
        
        $process_result = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', 'Invalid JSON response from process start', array('status' => 500));
        }
        
        $process_instance_id = $process_result['id'];
        $process_ended = isset($process_result['ended']) ? $process_result['ended'] : false;
        
        error_log('Operaton DMN: Process started with ID: ' . $process_instance_id . ', ended: ' . ($process_ended ? 'true' : 'false'));
        
        $final_variables = array();
        
        if ($process_ended) {
            // Process completed immediately - get variables from history
            error_log('Operaton DMN: Process completed immediately, getting variables from history');
            
            $history_endpoint = $base_url . '/history/variable-instance';
            $history_url = $history_endpoint . '?processInstanceId=' . $process_instance_id;
            
            error_log('Operaton DMN: Getting historical variables from: ' . $history_url);
            
            $history_response = wp_remote_get($history_url, array(
                'headers' => array('Accept' => 'application/json'),
                'timeout' => 15,
                'sslverify' => false,
            ));
            
            if (is_wp_error($history_response)) {
                error_log('Operaton DMN: Failed to get historical variables: ' . $history_response->get_error_message());
                return new WP_Error('api_error', 'Failed to get historical variables: ' . $history_response->get_error_message(), array('status' => 500));
            }
            
            $history_body = wp_remote_retrieve_body($history_response);
            $historical_variables = json_decode($history_body, true);
            
            error_log('Operaton DMN: Historical variables response: ' . $history_body);
            
        if (json_last_error() === JSON_ERROR_NONE && is_array($historical_variables)) {
            // FIXED: Convert historical variables with proper error checking
            foreach ($historical_variables as $var) {
                // Add proper checks for array keys
                if (isset($var['name']) && array_key_exists('value', $var)) {
                    $final_variables[$var['name']] = array(
                        'value' => $var['value'],
                        'type' => isset($var['type']) ? $var['type'] : 'String'
                    );
                }
            }
        }
            
        } else {
            // Process is still running - wait and try to get active variables
            error_log('Operaton DMN: Process still running, waiting for completion');
            sleep(3);
            
            $variables_endpoint = $base_url . '/process-instance/' . $process_instance_id . '/variables';
            
            $variables_response = wp_remote_get($variables_endpoint, array(
                'headers' => array('Accept' => 'application/json'),
                'timeout' => 15,
                'sslverify' => false,
            ));
            
            if (!is_wp_error($variables_response)) {
                $variables_body = wp_remote_retrieve_body($variables_response);
                $final_variables = json_decode($variables_body, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $final_variables = array();
                }
            }
            
            // If active variables failed, try history as fallback
            if (empty($final_variables)) {
                error_log('Operaton DMN: Active variables failed, trying history as fallback');
                
                $history_endpoint = $base_url . '/history/variable-instance';
                $history_url = $history_endpoint . '?processInstanceId=' . $process_instance_id;
                
                $history_response = wp_remote_get($history_url, array(
                    'headers' => array('Accept' => 'application/json'),
                    'timeout' => 15,
                    'sslverify' => false,
                ));
                
                if (!is_wp_error($history_response)) {
                    $history_body = wp_remote_retrieve_body($history_response);
                    $historical_variables = json_decode($history_body, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && is_array($historical_variables)) {
                        foreach ($historical_variables as $var) {
                            if (isset($var['name']) && isset($var['value'])) {
                                $final_variables[$var['name']] = array(
                                    'value' => $var['value'],
                                    'type' => isset($var['type']) ? $var['type'] : 'String'
                                );
                            }
                        }
                    }
                }
            }
        }
        
        error_log('Operaton DMN: Final variables after processing: ' . print_r($final_variables, true));
        
    // Process results based on configured mappings
    $result_mappings = json_decode($config->result_mappings, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $result_mappings = array();
    }

    $results = array();

    foreach ($result_mappings as $dmn_result_field => $mapping) {
        $result_value = null;
        
        // Strategy 1: Direct variable access
        if (isset($final_variables[$dmn_result_field]['value'])) {
            $result_value = $final_variables[$dmn_result_field]['value'];
            error_log('Operaton DMN: Found direct result for ' . $dmn_result_field . ': ' . print_r($result_value, true));
        } elseif (isset($final_variables[$dmn_result_field])) {
            $result_value = $final_variables[$dmn_result_field];
            error_log('Operaton DMN: Found simple result for ' . $dmn_result_field . ': ' . print_r($result_value, true));
        }
        
        // Strategy 2: Search in nested result objects (for DMN array results)
        if ($result_value === null) {
            // Look in heusdenpasResult, kindpakketResult, finalResult, etc.
            $possible_containers = array(
                'heusdenpasResult',
                'kindpakketResult', 
                'finalResult',
                'autoApprovalResult',
                'knockoffsResult'
            );
            
            foreach ($possible_containers as $container) {
                if (isset($final_variables[$container]['value']) && is_array($final_variables[$container]['value'])) {
                    $container_data = $final_variables[$container]['value'];
                    
                    // Check if it's an array of results
                    if (isset($container_data[0]) && is_array($container_data[0])) {
                        if (isset($container_data[0][$dmn_result_field])) {
                            $result_value = $container_data[0][$dmn_result_field];
                            error_log('Operaton DMN: Found nested result for ' . $dmn_result_field . ' in ' . $container . ': ' . print_r($result_value, true));
                            break;
                        }
                    }
                    // Also check direct access in case it's not nested
                    elseif (isset($container_data[$dmn_result_field])) {
                        $result_value = $container_data[$dmn_result_field];
                        error_log('Operaton DMN: Found container result for ' . $dmn_result_field . ' in ' . $container . ': ' . print_r($result_value, true));
                        break;
                    }
                }
            }
        }
        
        // Strategy 3: Search ALL variables for the field name (comprehensive search)
        if ($result_value === null) {
            foreach ($final_variables as $var_name => $var_data) {
                if (isset($var_data['value']) && is_array($var_data['value'])) {
                    // Check if it's an array of objects
                    if (isset($var_data['value'][0]) && is_array($var_data['value'][0])) {
                        if (isset($var_data['value'][0][$dmn_result_field])) {
                            $result_value = $var_data['value'][0][$dmn_result_field];
                            error_log('Operaton DMN: Found comprehensive result for ' . $dmn_result_field . ' in ' . $var_name . ': ' . print_r($result_value, true));
                            break;
                        }
                    }
                }
            }
        }
        
        // Convert result value if found
        if ($result_value !== null) {
            // Handle boolean conversion (DMN often returns 1/0 instead of true/false)
            if (is_numeric($result_value) && ($result_value === 1 || $result_value === 0 || $result_value === '1' || $result_value === '0')) {
                $result_value = (bool) $result_value;
            }
            
            $results[$dmn_result_field] = array(
                'value' => $result_value,
                'field_id' => $mapping['field_id']
            );
            error_log('Operaton DMN: Final processed result for ' . $dmn_result_field . ': ' . print_r($result_value, true));
        } else {
            error_log('Operaton DMN: No result found for ' . $dmn_result_field . ' after comprehensive search');
            
            // Debug: Show what variables are available
            error_log('Operaton DMN: Available variables: ' . implode(', ', array_keys($final_variables)));
        }
    }

    // Store process instance ID for decision flow retrieval
    $this->database->store_process_instance_id($config->form_id, $process_instance_id);

    return array(
        'success' => true,
        'results' => $results,
        'process_instance_id' => $process_instance_id,
        'debug_info' => defined('WP_DEBUG') && WP_DEBUG ? array(
            'variables_sent' => $variables,
            'process_result' => $process_result,
            'final_variables' => $final_variables,
            'endpoint_used' => $process_endpoint,
            'process_ended_immediately' => $process_ended,
            'result_mappings' => $result_mappings,
            'extraction_summary' => array(
                'total_variables_found' => count($final_variables),
                'results_extracted' => count($results),
                'result_fields_searched' => array_keys($result_mappings)
            )
        ) : null
    );
    }

    /**
     * Handle direct decision evaluation using Operaton's decision engine endpoint.
     * Sends form data to DMN evaluation endpoint and processes decision table results.
     * 
     * @param object $config Configuration object containing decision settings
     * @param array $form_data Form data to be evaluated by the decision table
     * @return array Decision evaluation results with mapped field values
     * @since 1.0.0
     */
    private function handle_decision_evaluation($config, $form_data) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Starting direct decision evaluation for key: ' . $config->decision_key);
        }
        
        $field_mappings = json_decode($config->field_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_mappings', 'Invalid field mappings configuration', array('status' => 500));
        }
        
        $result_mappings = json_decode($config->result_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_result_mappings', 'Invalid result mappings configuration', array('status' => 500));
        }
        
        if (empty($result_mappings)) {
            return new WP_Error('no_result_mappings', 'No result mappings configured', array('status' => 500));
        }
        
        // Process input variables
        $variables = array();
        
        foreach ($field_mappings as $dmn_variable => $form_field) {
            $value = null;
            
            if (isset($form_data[$dmn_variable])) {
                $value = $form_data[$dmn_variable];
            }
            
            if ($value === null || $value === 'null' || $value === '') {
                $variables[$dmn_variable] = array(
                    'value' => null,
                    'type' => $form_field['type']
                );
                continue;
            }
            
            // Type conversion
            switch ($form_field['type']) {
                case 'Integer':
                    if (!is_numeric($value)) {
                        return new WP_Error('invalid_type', sprintf('Value for %s must be numeric', $dmn_variable), array('status' => 400));
                    }
                    $value = intval($value);
                    break;
                case 'Double':
                    if (!is_numeric($value)) {
                        return new WP_Error('invalid_type', sprintf('Value for %s must be numeric', $dmn_variable), array('status' => 400));
                    }
                    $value = floatval($value);
                    break;
                case 'Boolean':
                    if (is_string($value)) {
                        $value = strtolower($value);
                        if ($value === 'true' || $value === '1') {
                            $value = true;
                        } elseif ($value === 'false' || $value === '0') {
                            $value = false;
                        } else {
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                            if ($value === null) {
                                return new WP_Error('invalid_type', sprintf('Value for %s must be boolean', $dmn_variable), array('status' => 400));
                            }
                        }
                    }
                    break;
                default:
                    $value = sanitize_text_field($value);
            }
            
            $variables[$dmn_variable] = array(
                'value' => $value,
                'type' => $form_field['type']
            );
        }
        
        error_log('Operaton DMN: Variables being sent to DMN engine: ' . print_r($variables, true));
        
        if (empty($variables)) {
            return new WP_Error('no_data', 'No valid form data provided', array('status' => 400));
        }
        
        // Build the full evaluation endpoint
        $evaluation_endpoint = $this->build_evaluation_endpoint($config->dmn_endpoint, $config->decision_key);
        
        error_log('Operaton DMN: Using evaluation endpoint: ' . $evaluation_endpoint);
        
        // Make API call
        $operaton_data = array('variables' => $variables);
        
        $response = wp_remote_post($evaluation_endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode($operaton_data),
            'timeout' => 30,
            'sslverify' => false,
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'Failed to connect to Operaton API: ' . $response->get_error_message(), array('status' => 500));
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            return new WP_Error('api_error', sprintf('API returned status code %d: %s', $http_code, $body), array('status' => 500));
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_response', 'Invalid JSON response from Operaton API', array('status' => 500));
        }
        
        // Process results based on configured mappings
        $results = array();
        
        foreach ($result_mappings as $dmn_result_field => $mapping) {
            $result_value = null;
            
            if (isset($data[0][$dmn_result_field]['value'])) {
                $result_value = $data[0][$dmn_result_field]['value'];
            } elseif (isset($data[0][$dmn_result_field])) {
                $result_value = $data[0][$dmn_result_field];
            }
            
            if ($result_value !== null) {
                $results[$dmn_result_field] = array(
                    'value' => $result_value,
                    'field_id' => $mapping['field_id']
                );
            }
        }
        
        if (empty($results)) {
            return new WP_Error('no_results', 'No valid results found in API response', array('status' => 500));
        }
        
        return array(
            'success' => true,
            'results' => $results,
            'debug_info' => defined('WP_DEBUG') && WP_DEBUG ? array(
                'variables_sent' => $variables,
                'api_response' => $data,
                'endpoint_used' => $evaluation_endpoint,
                'result_mappings' => $result_mappings
            ) : null
        );
    }

    /**
     * Test complete endpoint configuration with minimal DMN payload for validation.
     * Sends test data to verify decision key exists and endpoint responds correctly.
     * 
     * @param string $base_endpoint Base DMN endpoint URL
     * @param string $decision_key Decision definition key to test
     * @return array Test results with success status and detailed messages
     * @since 1.0.0
     */
    public function test_full_endpoint_configuration($base_endpoint, $decision_key) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Testing full endpoint configuration for decision: ' . $decision_key);
        }
        
        $full_endpoint = $this->build_evaluation_endpoint($base_endpoint, $decision_key);
        
        // Test with minimal DMN evaluation payload
        $test_data = array(
            'variables' => array(
                'test' => array(
                    'value' => 'test',
                    'type' => 'String'
                )
            )
        );
        
        $response = wp_remote_post($full_endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => wp_json_encode($test_data),
            'timeout' => 15,
            'sslverify' => false, // Only for development
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message(),
                'endpoint' => $full_endpoint
            );
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Analyze response
        if ($http_code === 200) {
            return array(
                'success' => true,
                'message' => 'Endpoint is working correctly and accepts DMN evaluations.',
                'endpoint' => $full_endpoint
            );
        } elseif ($http_code === 400) {
            // Bad request might mean the decision doesn't exist or input is wrong
            return array(
                'success' => false,
                'message' => 'Endpoint is reachable but decision key may be incorrect or decision table has different input requirements.',
                'endpoint' => $full_endpoint,
                'http_code' => $http_code,
                'response' => $body
            );
        } elseif ($http_code === 404) {
            return array(
                'success' => false,
                'message' => 'Decision not found. Please check your decision key.',
                'endpoint' => $full_endpoint,
                'http_code' => $http_code
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Unexpected response code: ' . $http_code,
                'endpoint' => $full_endpoint,
                'http_code' => $http_code,
                'response' => substr($body, 0, 200) // Truncate long responses
            );
        }
    }

    /**
     * Get decision flow summary HTML with caching and cache busting support.
     * Retrieves process execution decision history and formats it for display in the frontend.
     * 
     * @param int $form_id Gravity Forms form ID
     * @return string Formatted HTML for decision flow summary display
     * @since 1.0.0
     */
    public function get_decision_flow_summary_html($form_id) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Getting decision flow summary for form ' . $form_id);
        }
        
        // CHECK: Only show decision flow for process execution
        $config = $this->database->get_config_by_form_id($form_id);
        if (!$config || !$config->show_decision_flow || !$config->use_process) {
            error_log('Operaton DMN: Decision flow not available - not using process execution or disabled');
            $result = '<div class="decision-flow-placeholder">' .
                   '<h3>🔍 Decision Flow Results</h3>' .
                   '<p><em>Decision flow summary is only available for process execution mode.</em></p>' .
                   '</div>';
            return $result;
        }
        
        // CACHE BUSTING: Check if cache bust parameter is present
        $cache_bust = isset($_GET['cache_bust']) ? sanitize_text_field($_GET['cache_bust']) : '';
        
        // ADD RATE LIMITING TO PREVENT LOOPS (but allow cache busting)
        $cache_key = 'operaton_decision_flow_' . $form_id;
        if (empty($cache_bust)) {
            $cached_result = get_transient($cache_key);
            
            if ($cached_result !== false) {
                error_log('Operaton DMN: Returning cached decision flow for form ' . $form_id);
                return $cached_result;
            }
        } else {
            error_log('Operaton DMN: Cache busting requested for form ' . $form_id);
            // Clear the existing cache when cache busting
            delete_transient($cache_key);
        }
        
        error_log('Operaton DMN: Loading fresh decision flow for form ' . $form_id);
        
        $process_instance_id = $this->database->get_process_instance_id($form_id);
        if (!$process_instance_id) {
            $result = '<div class="decision-flow-placeholder">' .
                   '<h3>🔍 Decision Flow Results</h3>' .
                   '<p><em>Complete the evaluation on the previous step to see the detailed decision flow summary here.</em></p>' .
                   '</div>';
            
            // Cache for 1 minute (shorter since user might complete evaluation)
            if (empty($cache_bust)) {
                set_transient($cache_key, $result, 60);
            }
            return $result;
        }
        
        // PREVENT RAPID API CALLS (but allow cache busting)
        $api_cache_key = 'operaton_api_call_' . $process_instance_id;
        if (empty($cache_bust) && get_transient($api_cache_key)) {
            error_log('Operaton DMN: API call rate limited for process ' . $process_instance_id);
            
            $result = '<div class="decision-flow-loading">' .
                   '<h3>🔍 Decision Flow Results</h3>' .
                   '<p>⏳ Loading decision flow data... Please wait.</p>' .
                   '</div>';
            return $result;
        }
        
        // SET API RATE LIMIT (prevent calls for 5 seconds, unless cache busting)
        if (empty($cache_bust)) {
            set_transient($api_cache_key, true, 5);
        }
        
        // Build correct history endpoint
        $base_url = rtrim($config->dmn_endpoint, '/');
        $base_url = str_replace('/decision-definition/key', '', $base_url);
        $base_url = str_replace('/decision-definition', '', $base_url);
        
        if (strpos($base_url, '/engine-rest') === false) {
            $base_url .= '/engine-rest';
        }
        
        $history_endpoint = $base_url . '/history/decision-instance';
        $history_url = $history_endpoint . '?processInstanceId=' . $process_instance_id . '&includeInputs=true&includeOutputs=true';
        
        error_log('Operaton DMN: Getting decision flow from: ' . $history_url);
        
        $response = wp_remote_get($history_url, array(
            'headers' => array('Accept' => 'application/json'),
            'timeout' => 15,
            'sslverify' => false,
        ));
        
        if (is_wp_error($response)) {
            error_log('Operaton DMN: Error retrieving decision flow: ' . $response->get_error_message());
            $result = '<div class="decision-flow-error">' .
                   '<h3>🔍 Decision Flow Results</h3>' .
                   '<p><em>Error retrieving decision flow: ' . $response->get_error_message() . '</em></p>' .
                   '</div>';
            
            // Cache error for 2 minutes (unless cache busting)
            if (empty($cache_bust)) {
                set_transient($cache_key, $result, 120);
            }
            return $result;
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($http_code !== 200) {
            error_log('Operaton DMN: Decision flow API returned status: ' . $http_code);
            $result = '<div class="decision-flow-error">' .
                   '<h3>🔍 Decision Flow Results</h3>' .
                   '<p><em>Error loading decision flow (HTTP ' . $http_code . '). Please try again.</em></p>' .
                   '</div>';
            
            // Cache error for 2 minutes (unless cache busting)
            if (empty($cache_bust)) {
                set_transient($cache_key, $result, 120);
            }
            return $result;
        }
        
        $decision_instances = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Operaton DMN: JSON decode error: ' . json_last_error_msg());
            $result = '<div class="decision-flow-error">' .
                   '<h3>🔍 Decision Flow Results</h3>' .
                   '<p><em>Error parsing decision flow data.</em></p>' .
                   '</div>';
            
            // Cache error for 2 minutes (unless cache busting)
            if (empty($cache_bust)) {
                set_transient($cache_key, $result, 120);
            }
            return $result;
        }
        
        error_log('Operaton DMN: Decision instances count: ' . count($decision_instances));
        
        $result = $this->format_decision_flow_summary($decision_instances, $process_instance_id);
        
        // Cache successful result for 10 minutes (unless cache busting)
        if (empty($cache_bust)) {
            set_transient($cache_key, $result, 600);
        }
        
        return $result;
    }

    // =============================================================================
    // UTILITY/HELPER METHODS
    // =============================================================================

    /**
     * Enhanced configuration retrieval with caching for performance optimization.
     * Gets DMN configuration by form ID with optional caching to reduce database queries.
     * 
     * @param int $form_id Gravity Forms form ID
     * @param bool $use_cache Whether to use cached results
     * @return object|null Configuration object or null if not found
     * @since 1.0.0
     */
    public function get_config_by_form_id($form_id, $use_cache = true) {
        return $this->database->get_config_by_form_id($form_id, $use_cache);
    }

    /**
     * Build the full DMN evaluation endpoint URL from base endpoint and decision key.
     * Constructs complete evaluation URL following Operaton REST API conventions.
     * 
     * @param string $base_endpoint Base DMN endpoint URL
     * @param string $decision_key Decision definition key
     * @return string Complete evaluation endpoint URL
     * @since 1.0.0
     */
    private function build_evaluation_endpoint($base_endpoint, $decision_key) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Building evaluation endpoint for decision: ' . $decision_key);
        }
        
        // Ensure base endpoint ends with /
        if (!empty($base_endpoint) && substr($base_endpoint, -1) !== '/') {
            $base_endpoint .= '/';
        }
        
        return $base_endpoint . $decision_key . '/evaluate';
    }

    /**
     * Helper method for timezone handling in decision flow timestamps.
     * Converts ISO timestamps to WordPress timezone for user-friendly display.
     * 
     * @param string $iso_timestamp ISO format timestamp from Operaton API
     * @return string Formatted timestamp in site timezone
     * @since 1.0.0
     */
    private function format_evaluation_time($iso_timestamp) {
        if (empty($iso_timestamp)) {
            return 'Unknown';
        }
        
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Operaton DMN: Formatting timestamp: ' . $iso_timestamp);
            }
            
            // Parse the ISO timestamp (handles UTC offsets)
            $datetime = new DateTime($iso_timestamp);
            
            // Convert to WordPress site timezone
            $wp_timezone = wp_timezone();
            $datetime->setTimezone($wp_timezone);
            
            // Format in a user-friendly way
            $formatted_date = $datetime->format('Y-m-d H:i:s');
            $timezone_name = $datetime->format('T'); // Timezone abbreviation (CEST, CET, etc.)
            
            return $formatted_date . ' (' . $timezone_name . ')';
            
        } catch (Exception $e) {
            // Fallback: just clean up the original timestamp
            $clean_time = str_replace(['T', '+0000'], [' ', ' UTC'], $iso_timestamp);
            return $clean_time;
        }
    }

    /**
     * Format decision flow with Excel-style table layout for enhanced readability.
     * Creates formatted HTML display of decision instances with inputs, outputs, and metadata.
     * 
     * @param array $decision_instances Array of decision instance data from Operaton API
     * @param string $process_instance_id Process instance identifier for context
     * @return string Formatted HTML for decision flow display
     * @since 1.0.0
     */
    private function format_decision_flow_summary($decision_instances, $process_instance_id) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Formatting decision flow summary with ' . count($decision_instances) . ' instances');
        }
        
        $html = '<h3>🔍 Decision Flow Results Summary</h3>';
        $html .= '<p><strong>Process Instance:</strong> <code>' . esc_html($process_instance_id) . '</code></p>';
        
        if (empty($decision_instances) || !is_array($decision_instances)) {
            $html .= '<div class="decision-flow-empty">';
            $html .= '<p><em>No decision instances found for this process.</em></p>';
            $html .= '</div>';
            return $html;
        }
        
        error_log('Operaton DMN: Processing ' . count($decision_instances) . ' decision instances');
        
        // FILTER 1: Only get instances from Activity_FinalResultCompilation if available
        $filtered_instances = array();
        $has_final_compilation = false;
        
        foreach ($decision_instances as $instance) {
            if (isset($instance['activityId']) && $instance['activityId'] === 'Activity_FinalResultCompilation') {
                $filtered_instances[] = $instance;
                $has_final_compilation = true;
            }
        }
        
        // If no FinalResultCompilation activity, get the latest evaluation for each decision
        if (!$has_final_compilation) {
            error_log('Operaton DMN: No Activity_FinalResultCompilation found, using latest evaluations');
            
            // Group by decision definition key and get the latest evaluation time for each
            $latest_by_decision = array();
            
            foreach ($decision_instances as $instance) {
                if (isset($instance['decisionDefinitionKey']) && isset($instance['evaluationTime'])) {
                    $key = $instance['decisionDefinitionKey'];
                    $eval_time = $instance['evaluationTime'];
                    
                    if (!isset($latest_by_decision[$key]) || 
                        strtotime($eval_time) > strtotime($latest_by_decision[$key]['evaluationTime'])) {
                        $latest_by_decision[$key] = $instance;
                    }
                }
            }
            
            $filtered_instances = array_values($latest_by_decision);
            error_log('Operaton DMN: Filtered to latest evaluations, count: ' . count($filtered_instances));
        } else {
            error_log('Operaton DMN: Using Activity_FinalResultCompilation instances, count: ' . count($filtered_instances));
        }
        
        if (empty($filtered_instances)) {
            $html .= '<p><em>No relevant decision instances found.</em></p>';
            return $html;
        }
        
        // Sort by evaluation time
        usort($filtered_instances, function($a, $b) {
            $timeA = isset($a['evaluationTime']) ? strtotime($a['evaluationTime']) : 0;
            $timeB = isset($b['evaluationTime']) ? strtotime($b['evaluationTime']) : 0;
            return $timeA - $timeB;
        });
        
        // Group decisions by decision definition key for cleaner display
        $decisions_by_key = array();
        foreach ($filtered_instances as $instance) {
            if (isset($instance['decisionDefinitionKey'])) {
                $key = $instance['decisionDefinitionKey'];
                if (!isset($decisions_by_key[$key])) {
                    $decisions_by_key[$key] = array();
                }
                $decisions_by_key[$key][] = $instance;
            }
        }
        
        // TOP SECTION: Summary Statistics + Status + Refresh Button
        $html .= '<div class="decision-flow-header" style="background: #f0f8ff; padding: 15px; border-radius: 6px; border-left: 4px solid #0073aa; margin-bottom: 20px;">';
        
        // SUMMARY STATISTICS AT THE TOP
        $html .= '<div class="decision-flow-summary-stats" style="margin-bottom: 15px;">';
        $html .= '<h4 style="margin: 0 0 10px 0;">📊 Summary</h4>';
        $html .= '<ul style="margin: 0; padding-left: 20px;">';
        $html .= '<li><strong>Total Decision Types:</strong> ' . count($decisions_by_key) . '</li>';
        $html .= '<li><strong>Total Evaluations Shown:</strong> ' . count($filtered_instances) . '</li>';
        $html .= '<li><strong>Total Available:</strong> ' . count($decision_instances) . '</li>';
        $html .= '<li><strong>Filter Applied:</strong> ' . ($has_final_compilation ? 'Activity_FinalResultCompilation only' : 'Latest evaluation per decision') . '</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        // STATUS LINE
        $html .= '<p style="margin: 10px 0;"><strong>Showing:</strong> ' . ($has_final_compilation ? 'Final compilation results' : 'Latest evaluation for each decision') . '</p>';
        
        // REFRESH BUTTON
        $html .= '<button type="button" class="button refresh-decision-flow-controlled" data-form-id="8" style="margin-top: 10px;">';
        $html .= '🔄 Refresh Decision Flow';
        $html .= '</button>';
        $html .= '</div>';
        
        // MAIN SECTION: Excel-style Decision Tables
        $html .= '<div class="decision-flow-tables">';
        
        $step = 1;
        foreach ($decisions_by_key as $decision_key => $instances) {
            // Only show the first instance for each decision (since we filtered to latest/final)
            $instance = $instances[0];
            
            $html .= '<div class="decision-table-container">';
            $html .= '<h4 class="decision-table-title">' . $step . '. ' . esc_html($decision_key) . '</h4>';
            
            // Create Excel-style table
            $html .= '<table class="decision-table excel-style">';
            
            // Header row
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th class="table-header"></th>'; // Empty top-left cell
            $html .= '<th class="table-header">Variable</th>';
            $html .= '<th class="table-header">Value</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            
            $html .= '<tbody>';
            
            // INPUTS Section
            if (isset($instance['inputs']) && is_array($instance['inputs']) && count($instance['inputs']) > 0) {
                $input_count = count($instance['inputs']);
                $first_input = true;
                
                foreach ($instance['inputs'] as $input) {
                    $html .= '<tr class="input-row">';
                    
                    // Row header (only on first input row)
                    if ($first_input) {
                        $html .= '<td class="row-header inputs-header" rowspan="' . $input_count . '">📥 Inputs</td>';
                        $first_input = false;
                    } else {
                        // Empty cell for subsequent rows (handled by rowspan)
                    }
                    
                    // Variable name
                    $name = 'Unknown Input';
                    if (isset($input['clauseName']) && !empty($input['clauseName'])) {
                        $name = $input['clauseName'];
                    } elseif (isset($input['name']) && !empty($input['name'])) {
                        $name = $input['name'];
                    }
                    $html .= '<td class="variable-cell">' . esc_html($name) . '</td>';
                    
                    // Value
                    $value = 'null';
                    if (array_key_exists('value', $input)) {
                        if (is_null($input['value']) || $input['value'] === '') {
                            $value = '<em class="null-value">null</em>';
                        } elseif (is_bool($input['value'])) {
                            $icon = $input['value'] ? '✅' : '❌';
                            $text = $input['value'] ? 'true' : 'false';
                            $value = $icon . ' ' . $text;
                        } elseif (is_array($input['value'])) {
                            $value = esc_html(json_encode($input['value']));
                        } else {
                            $value = esc_html((string) $input['value']);
                        }
                    }
                    $html .= '<td class="value-cell">' . $value . '</td>';
                    
                    $html .= '</tr>';
                }
            }
            
            // OUTPUTS Section
            if (isset($instance['outputs']) && is_array($instance['outputs']) && count($instance['outputs']) > 0) {
                $output_count = count($instance['outputs']);
                $first_output = true;
                
                foreach ($instance['outputs'] as $output) {
                    $html .= '<tr class="output-row">';
                    
                    // Row header (only on first output row)
                    if ($first_output) {
                        $html .= '<td class="row-header outputs-header" rowspan="' . $output_count . '">📤 Outputs</td>';
                        $first_output = false;
                    } else {
                        // Empty cell for subsequent rows (handled by rowspan)
                    }
                    
                    // Variable name
                    $name = 'Unknown Output';
                    if (isset($output['clauseName']) && !empty($output['clauseName'])) {
                        $name = $output['clauseName'];
                    } elseif (isset($output['variableName']) && !empty($output['variableName'])) {
                        $name = $output['variableName'];
                    } elseif (isset($output['name']) && !empty($output['name'])) {
                        $name = $output['name'];
                    }
                    $html .= '<td class="variable-cell">' . esc_html($name) . '</td>';
                    
                    // Value with enhanced formatting
                    $value = '';
                    if (array_key_exists('value', $output)) {
                        if (is_null($output['value']) || $output['value'] === '') {
                            $value = '<em class="null-value">null</em>';
                        } elseif (is_bool($output['value'])) {
                            $icon = $output['value'] ? '✅' : '❌';
                            $text = $output['value'] ? 'true' : 'false';
                            $value = '<span class="boolean-value ' . ($output['value'] ? 'true' : 'false') . '">' . $icon . ' ' . $text . '</span>';
                        } elseif (is_numeric($output['value'])) {
                            $value = '<span class="numeric-value">' . esc_html((string) $output['value']) . '</span>';
                        } elseif (is_array($output['value'])) {
                            $value = '<span class="array-value">' . esc_html(json_encode($output['value'])) . '</span>';
                        } else {
                            $value = '<span class="string-value">' . esc_html((string) $output['value']) . '</span>';
                        }
                    } else {
                        $value = '<em class="no-value">no value</em>';
                    }
                    $html .= '<td class="value-cell">' . $value . '</td>';
                    
                    $html .= '</tr>';
                }
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
            
            // Metadata footer
    // Metadata footer (UPDATED with timezone conversion)
    $html .= '<div class="decision-metadata">';
    if (isset($instance['evaluationTime'])) {
        $formatted_time = $this->format_evaluation_time($instance['evaluationTime']);
        $html .= '<small><strong>⏱️ Evaluation Time:</strong> ' . esc_html($formatted_time) . '</small>';
    }
    if (isset($instance['activityId'])) {
        $html .= '<small style="margin-left: 15px;"><strong>🔧 Activity:</strong> ' . esc_html($instance['activityId']) . '</small>';
    }
    $html .= '</div>';        
            $html .= '</div>'; // Close decision-table-container
            
            $step++;
        }
        
        $html .= '</div>'; // Close decision-flow-tables
        
        // Enhanced Excel-style CSS
        $html .= '<style>
            .decision-flow-tables { 
                margin: 20px 0; 
            }
            
            .decision-table-container { 
                margin: 25px 0; 
                padding: 0;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            .decision-table-title { 
                margin: 0; 
                padding: 15px 20px;
                background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
                color: white;
                font-size: 16px;
                font-weight: 600;
                border-bottom: none;
            }
            
            .decision-table.excel-style {
                width: 100%;
                border-collapse: collapse;
                margin: 0;
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                font-size: 13px;
                background: white;
            }
            
            .decision-table.excel-style th {
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                padding: 12px 15px;
                text-align: left;
                font-weight: 600;
                color: #495057;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .decision-table.excel-style td {
                border: 1px solid #dee2e6;
                padding: 10px 15px;
                vertical-align: top;
                line-height: 1.4;
            }
            
            .row-header {
                background: #e8f4f8 !important;
                font-weight: 600;
                text-align: center;
                vertical-align: middle !important;
                width: 100px;
                min-width: 100px;
                border-right: 2px solid #0073aa !important;
            }
            
            .inputs-header {
                color: #0073aa;
            }
            
            .outputs-header {
                color: #28a745;
            }
            
            .variable-cell {
                font-weight: 500;
                color: #343a40;
                background: #f8f9fa;
                font-family: "Courier New", monospace;
                width: 250px;
            }
            
            .value-cell {
                font-family: "Courier New", monospace;
                color: #495057;
                background: white;
            }
            
            .input-row:hover {
                background: rgba(0, 115, 170, 0.05);
            }
            
            .output-row:hover {
                background: rgba(40, 167, 69, 0.05);
            }
            
            /* Value type styling */
            .boolean-value.true {
                color: #28a745;
                font-weight: 600;
            }
            
            .boolean-value.false {
                color: #dc3545;
                font-weight: 600;
            }
            
            .numeric-value {
                color: #6f42c1;
                font-weight: 600;
            }
            
            .string-value {
                color: #495057;
            }
            
            .array-value {
                color: #fd7e14;
                font-style: italic;
            }
            
            .null-value, .no-value {
                color: #6c757d;
                font-style: italic;
            }
            
            .decision-metadata {
                padding: 12px 20px;
                background: #f8f9fa;
                border-top: 1px solid #dee2e6;
                font-size: 11px;
                color: #6c757d;
            }
            
            .decision-metadata small {
                display: inline-block;
            }
            
            /* Header styling */
            .decision-flow-header {
                border-left: 4px solid #0073aa !important;
            }
            
            .decision-flow-summary-stats {
                background: rgba(255, 255, 255, 0.8);
                padding: 12px;
                border-radius: 4px;
                border: 1px solid #e0e0e0;
            }
            
            .decision-flow-summary-stats h4 {
                color: #0073aa;
                font-size: 14px;
                margin: 0 0 8px 0;
            }
            
            .decision-flow-summary-stats ul {
                margin: 0;
                padding-left: 18px;
                font-size: 13px;
            }
            
            .decision-flow-summary-stats li {
                margin: 3px 0;
            }
            
            .refresh-decision-flow-controlled {
                background-color: #0073aa !important;
                border-color: #0073aa !important;
                color: white !important;
                font-size: 12px;
                padding: 8px 16px;
            }
            
            .refresh-decision-flow-controlled:hover {
                background-color: #005a87 !important;
            }
            
            /* Responsive design */
            @media (max-width: 768px) {
                .decision-table.excel-style {
                    font-size: 11px;
                }
                
                .decision-table.excel-style th,
                .decision-table.excel-style td {
                    padding: 8px 10px;
                }
                
                .row-header {
                    width: 80px;
                    min-width: 80px;
                    font-size: 10px;
                }
                
                .variable-cell {
                    width: 200px;
                }
                
                .decision-table-title {
                    font-size: 14px;
                    padding: 12px 15px;
                }
            }
            
            /* Print styling */
            @media print {
                .decision-table-container {
                    break-inside: avoid;
                    box-shadow: none;
                    border: 1px solid #000;
                }
                
                .refresh-decision-flow-controlled {
                    display: none;
                }
            }
        </style>';
        
        return $html;
    }

    /**
     * Helper method to get example configurations for documentation and testing.
     * Provides predefined endpoint examples for different Operaton deployment scenarios.
     * 
     * @return array Array of example configuration templates
     * @since 1.0.0
     */
    public function get_endpoint_examples() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Getting endpoint examples');
        }
        
        return array(
            'operaton_cloud' => array(
                'name' => 'Operaton Cloud',
                'base_endpoint' => 'https://your-tenant.operaton.cloud/engine-rest/decision-definition/key/',
                'example_decision_key' => 'loan-approval',
                'full_example' => 'https://your-tenant.operaton.cloud/engine-rest/decision-definition/key/loan-approval/evaluate'
            ),
            'operaton_self_hosted' => array(
                'name' => 'Self-hosted Operaton',
                'base_endpoint' => 'https://operatondev.open-regels.nl/engine-rest/decision-definition/key/',
                'example_decision_key' => 'dish',
                'full_example' => 'https://operatondev.open-regels.nl/engine-rest/decision-definition/key/dish/evaluate'
            ),
            'local_development' => array(
                'name' => 'Local Development',
                'base_endpoint' => 'http://localhost:8080/engine-rest/decision-definition/key/',
                'example_decision_key' => 'my-decision',
                'full_example' => 'http://localhost:8080/engine-rest/decision-definition/key/my-decision/evaluate'
            )
        );
    }

    /**
     * Plugin health check to identify common configuration and dependency issues.
     * Validates plugin dependencies and database integrity for troubleshooting support.
     * 
     * @return array Array of health issue descriptions
     * @since 1.0.0
     */
    public function health_check() {
        $issues = array();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Operaton DMN: Performing health check');
        }
        
        // Check if Gravity Forms is active
        if (!class_exists('GFForms')) {
            $issues[] = __('Gravity Forms is not active.', 'operaton-dmn');
        }
        
        // Check database table
        global $wpdb;
        $table_name = $wpdb->prefix . 'operaton_dmn_configs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $issues[] = __('Database table is missing.', 'operaton-dmn');
        }
        
        // Check if REST API is working
        $test_url = rest_url('operaton-dmn/v1/test');
        $response = wp_remote_get($test_url);
        if (is_wp_error($response)) {
            $issues[] = __('REST API is not accessible.', 'operaton-dmn');
        }
        
        return $issues;
    }
}

/**
 * NEW: Add REST endpoint for decision flow data
 */
add_action('rest_api_init', function() {
    register_rest_route('operaton-dmn/v1', '/decision-flow/(?P<form_id>\d+)', array(
        'methods' => 'GET',
        'callback' => function($request) {
            $form_id = $request['form_id'];
            
            // Get the plugin instance and call the public method
            $plugin = OperatonDMNEvaluator::get_instance();
            $html = $plugin->get_decision_flow_summary_html($form_id);
            
            return array(
                'success' => true,
                'html' => $html
            );
        },
        'permission_callback' => '__return_true'
    ));
});

// Add AJAX handler for clearing update cache
add_action('wp_ajax_operaton_clear_update_cache', function() {
    if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['_ajax_nonce'], 'operaton_admin_nonce')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    // Clear WordPress update transients
    delete_site_transient('update_plugins');
    delete_transient('operaton_dmn_updater');
    delete_transient('operaton_dmn_fallback_check');
    
    // Force WordPress to check for updates
    wp_update_plugins();
    
    wp_send_json_success(array('message' => 'Update cache cleared'));
});

// Initialize the plugin
OperatonDMNEvaluator::get_instance();

// Create necessary directories and files
register_activation_hook(__FILE__, 'operaton_dmn_create_files');

function operaton_dmn_create_files() {
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/operaton-dmn/';
    
    if (!file_exists($plugin_dir)) {
        wp_mkdir_p($plugin_dir);
    }
}