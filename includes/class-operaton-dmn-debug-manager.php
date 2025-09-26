<?php

/**
 * Global Debug Manager for Operaton DMN Plugin
 *
 * Centralized debug logging system that replaces the trait-based approach.
 * Provides secure, level-controlled debug logging with automatic sanitization
 * and component-based organization. Supports both PHP and JavaScript logging.
 *
 * @package OperatonDMN
 * @subpackage Debug
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH'))
{
    exit;
}

class Operaton_DMN_Debug_Manager
{
    // =============================================================================
    // DEBUG LEVEL CONSTANTS
    // =============================================================================

    const DEBUG_LEVEL_NONE = 0;                 // No debug output
    const DEBUG_LEVEL_MINIMAL = 1;              // Only critical errors and warnings
    const DEBUG_LEVEL_STANDARD = 2;             // Standard operations and results
    const DEBUG_LEVEL_VERBOSE = 3;              // Detailed debug information
    const DEBUG_LEVEL_DIAGNOSTIC = 4;           // Full diagnostic including sensitive data (sanitized)

    // =============================================================================
    // SINGLETON PATTERN
    // =============================================================================

    /**
     * Single instance of the debug manager
     * @var Operaton_DMN_Debug_Manager|null
     */
    private static $instance = null;

    /**
     * Current debug level cache
     * @var int|null
     */
    private static $debug_level_cache = null;

    /**
     * JavaScript logs buffer for AJAX transmission
     * @var array
     */
    private $js_logs_buffer = array();

    /**
     * Component prefixes for organized logging
     * @var array
     */
    private $component_prefixes = array(
        'API' => 'Operaton DMN API',
        'Admin' => 'Operaton DMN Admin',
        'Database' => 'Operaton DMN Database',
        'Assets' => 'Operaton DMN Assets',
        'GravityForms' => 'Operaton DMN Gravity Forms',
        'Evaluator' => 'Operaton DMN Evaluator',
        'Frontend' => 'Operaton DMN Frontend',
        'Performance' => 'Operaton DMN Performance',
        'Config' => 'Operaton DMN Config',
        'Security' => 'Operaton DMN Security',
        'Cache' => 'Operaton DMN Cache',
        'Templates' => 'Operaton DMN Templates'
    );

    /**
     * Sensitive data patterns for automatic sanitization
     * @var array
     */
    private $sensitive_patterns = array(
        // Credentials and authentication
        'password',
        'passwd',
        'pwd',
        'secret',
        'token',
        'key',
        'api_key',
        'private_key',
        'auth',
        'authorization',
        'bearer',
        'credentials',

        // Database related
        'db_password',
        'database_password',
        'mysql_password',
        'dbpass',
        'connection_string',
        'dsn',
        'wpdb',

        // User data
        'email',
        'user_email',
        'user_pass',
        'user_password',
        'personal_data',
        'phone',
        'address',
        'ssn',
        'social_security',

        // System information
        'server_info',
        'php_info',
        'environment_variables',
        'env_vars',

        // Plugin specific
        'field_mappings',
        'result_mappings',
        'dmn_credentials',
        'camunda_auth',
        'operaton_token',
        'decision_key',
        'process_key'
    );

    // =============================================================================
    // SINGLETON METHODS
    // =============================================================================

    /**
     * Get singleton instance of debug manager
     *
     * @return Operaton_DMN_Debug_Manager
     */
    public static function get_instance()
    {
        if (self::$instance === null)
        {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Prevent cloning of singleton instance
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserialization of singleton instance
     */
    public function __wakeup()
    {
    }

    // =============================================================================
    // INITIALIZATION
    // =============================================================================

    /**
     * Initialize WordPress hooks for debug functionality
     */
    private function init_hooks()
    {
        // AJAX handler for JavaScript logging
        add_action('wp_ajax_operaton_debug_log', array($this, 'handle_ajax_log'));
        add_action('wp_ajax_nopriv_operaton_debug_log', array($this, 'handle_ajax_log'));

        // Add debug configuration to admin scripts
        add_action('admin_footer', array($this, 'output_debug_config'));
        add_action('wp_footer', array($this, 'output_debug_config'));
    }

    // =============================================================================
    // CORE LOGGING METHODS
    // =============================================================================

    /**
     * Main logging method with component-based organization
     *
     * @param string $component Component identifier (API, Admin, Database, etc.)
     * @param string $message Debug message
     * @param mixed $data Optional data to log
     * @param int $level Debug level required (default: STANDARD)
     * @param array $additional_sensitive_keys Additional keys to sanitize
     */
    public function log($component, $message, $data = null, $level = self::DEBUG_LEVEL_STANDARD, $additional_sensitive_keys = array())
    {
        // Check if logging should occur at this level
        if (!$this->should_log($level))
        {
            return;
        }

        $prefix = $this->get_debug_prefix($component, $level);
        $log_message = $prefix . $message;

        // Log the main message
        error_log($log_message);

        // Log data if provided
        if ($data !== null)
        {
            $sanitized_data = $this->sanitize_debug_output($data, $additional_sensitive_keys);

            if (is_array($sanitized_data) || is_object($sanitized_data))
            {
                error_log($prefix . 'Data: ' . json_encode($sanitized_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            else
            {
                error_log($prefix . 'Data: ' . $sanitized_data);
            }
        }
    }

    /**
     * JavaScript logging via AJAX
     *
     * @param string $component Component identifier
     * @param string $message Debug message
     * @param mixed $data Optional data to log
     * @param int $level Debug level required
     */
    public function log_js($component, $message, $data = null, $level = self::DEBUG_LEVEL_STANDARD)
    {
        // Buffer the log for AJAX transmission
        $this->js_logs_buffer[] = array(
            'component' => $component,
            'message' => $message,
            'data' => $data,
            'level' => $level,
            'timestamp' => current_time('mysql')
        );

        // If we have too many buffered logs, send them immediately
        if (count($this->js_logs_buffer) >= 10)
        {
            $this->flush_js_logs();
        }
    }

    // =============================================================================
    // CONVENIENCE METHODS FOR DIFFERENT LOG LEVELS
    // =============================================================================

    /**
     * Log minimal level messages (errors, critical warnings)
     */
    public function log_minimal($component, $message, $data = null)
    {
        $this->log($component, $message, $data, self::DEBUG_LEVEL_MINIMAL);
    }

    /**
     * Log standard level messages (normal operations)
     */
    public function log_standard($component, $message, $data = null)
    {
        $this->log($component, $message, $data, self::DEBUG_LEVEL_STANDARD);
    }

    /**
     * Log verbose level messages (detailed operations)
     */
    public function log_verbose($component, $message, $data = null)
    {
        $this->log($component, $message, $data, self::DEBUG_LEVEL_VERBOSE);
    }

    /**
     * Log diagnostic level messages (full debugging info)
     */
    public function log_diagnostic($component, $message, $data = null)
    {
        $this->log($component, $message, $data, self::DEBUG_LEVEL_DIAGNOSTIC);
    }

    // =============================================================================
    // SPECIALIZED LOGGING METHODS
    // =============================================================================

    /**
     * Log database operations with automatic sanitization
     */
    public function log_database($component, $message, $data = null)
    {
        $db_sensitive_keys = array('query', 'sql', 'prepare', 'wpdb', 'connection');
        $this->log($component, $message, $data, self::DEBUG_LEVEL_STANDARD, $db_sensitive_keys);
    }

    /**
     * Log API operations with automatic sanitization
     */
    public function log_api($component, $message, $data = null)
    {
        $api_sensitive_keys = array('headers', 'authorization', 'body', 'response', 'curl', 'endpoint');
        $this->log($component, $message, $data, self::DEBUG_LEVEL_STANDARD, $api_sensitive_keys);
    }

    /**
     * Log configuration operations with automatic sanitization
     */
    public function log_config($component, $message, $data = null)
    {
        $config_sensitive_keys = array('field_mappings', 'result_mappings', 'credentials', 'secrets');
        $this->log($component, $message, $data, self::DEBUG_LEVEL_STANDARD, $config_sensitive_keys);
    }

    /**
     * Log performance metrics
     */
    public function log_performance($component, $message, $metrics = null)
    {
        if ($metrics)
        {
            $performance_sensitive_keys = array('memory_usage', 'query_details', 'timing');
            $this->log($component, $message, $metrics, self::DEBUG_LEVEL_VERBOSE, $performance_sensitive_keys);
        }
        else
        {
            $this->log($component, $message, null, self::DEBUG_LEVEL_VERBOSE);
        }
    }

    /**
     * Emergency logging that bypasses level restrictions
     * Only use for critical errors that must always be logged
     *
     * @param string $component Component identifier
     * @param string $message Critical error message
     * @param mixed $data Critical error data
     */
    public function log_emergency($component, $message, $data = null)
    {
        $emergency_prefix = "Operaton DMN {$component} [EMERGENCY]: ";
        error_log($emergency_prefix . $message);

        if ($data !== null)
        {
            $sanitized_data = $this->sanitize_debug_output($data);
            if (is_array($sanitized_data) || is_object($sanitized_data))
            {
                error_log($emergency_prefix . 'Data: ' . json_encode($sanitized_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
            else
            {
                error_log($emergency_prefix . 'Data: ' . $sanitized_data);
            }
        }
    }

    // =============================================================================
    // DEBUG LEVEL CONTROL SYSTEM
    // =============================================================================

    /**
     * Get current debug level
     *
     * Determines the current debug level based on WordPress constants
     * and plugin-specific settings with intelligent defaults.
     *
     * @return int Current debug level
     */
    public function get_debug_level()
    {
        // Use cached value if available
        if (self::$debug_level_cache !== null)
        {
            return self::$debug_level_cache;
        }

        $level = self::DEBUG_LEVEL_NONE;

        // Check WordPress debug constants
        if (defined('WP_DEBUG') && WP_DEBUG)
        {
            $level = self::DEBUG_LEVEL_STANDARD;

            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG)
            {
                $level = self::DEBUG_LEVEL_VERBOSE;
            }
        }

        // Check plugin-specific debug level
        if (defined('OPERATON_DEBUG_LEVEL'))
        {
            $plugin_level = (int) OPERATON_DEBUG_LEVEL;
            if ($plugin_level >= 0 && $plugin_level <= 4)
            {
                $level = $plugin_level;
            }
        }

        // Increase level for development environments
        if ($this->is_development_environment() && $level < self::DEBUG_LEVEL_STANDARD)
        {
            $level = self::DEBUG_LEVEL_STANDARD;
        }

        // Cache the result
        self::$debug_level_cache = $level;

        return $level;
    }

    /**
     * Check if logging should occur at the specified level
     *
     * @param int $required_level Minimum level required for logging
     * @return bool True if logging should occur
     */
    private function should_log($required_level)
    {
        return $this->get_debug_level() >= $required_level;
    }

    /**
     * Detect if we're in a development environment
     *
     * @return bool True if development environment detected
     */
    private function is_development_environment()
    {
        // Check common development indicators
        $dev_indicators = array(
            strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false,
            strpos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false,
            strpos($_SERVER['HTTP_HOST'] ?? '', '.dev') !== false,
            strpos($_SERVER['HTTP_HOST'] ?? '', '.test') !== false,
            // Your specific test environment
            strpos($_SERVER['HTTP_HOST'] ?? '', 'test.open-regels-lab.nl') !== false,
            strpos($_SERVER['HTTP_HOST'] ?? '', 'open-regels-lab') !== false,
        );

        return in_array(true, $dev_indicators, true);
    }

    // =============================================================================
    // DATA SANITIZATION
    // =============================================================================

    /**
     * Sanitize debug output to protect sensitive information
     *
     * @param mixed $data Data to sanitize
     * @param array $additional_sensitive_keys Additional keys to sanitize
     * @return mixed Sanitized data
     */
    private function sanitize_debug_output($data, $additional_sensitive_keys = array())
    {
        // Merge all sensitive patterns
        $all_sensitive_keys = array_merge($this->sensitive_patterns, $additional_sensitive_keys);

        // Handle different data types
        if (is_array($data))
        {
            return $this->sanitize_array($data, $all_sensitive_keys);
        }

        if (is_object($data))
        {
            // Handle special WordPress objects
            if ($data instanceof WP_Error)
            {
                return $this->sanitize_wp_error($data);
            }

            if (isset($data->dbh) || get_class($data) === 'wpdb')
            {
                return $this->sanitize_wpdb_object($data);
            }

            return $this->sanitize_object($data, $all_sensitive_keys);
        }

        if (is_string($data))
        {
            return $this->sanitize_string($data, $all_sensitive_keys);
        }

        return $data;
    }

    /**
     * Sanitize array data
     */
    private function sanitize_array($array, $sensitive_keys)
    {
        $sanitized = array();

        foreach ($array as $key => $value)
        {
            if ($this->is_sensitive_key($key, $sensitive_keys))
            {
                $sanitized[$key] = '[SANITIZED]';
            }
            elseif (is_array($value) || is_object($value))
            {
                $sanitized[$key] = $this->sanitize_debug_output($value, $sensitive_keys);
            }
            else
            {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize object data
     */
    private function sanitize_object($object, $sensitive_keys)
    {
        $sanitized = array('_class' => get_class($object));

        foreach (get_object_vars($object) as $property => $value)
        {
            if ($this->is_sensitive_key($property, $sensitive_keys))
            {
                $sanitized[$property] = '[SANITIZED]';
            }
            elseif (is_array($value) || is_object($value))
            {
                $sanitized[$property] = $this->sanitize_debug_output($value, $sensitive_keys);
            }
            else
            {
                $sanitized[$property] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize string data
     */
    private function sanitize_string($string, $sensitive_keys)
    {
        // Check for JSON strings
        $json_data = json_decode($string, true);
        if (json_last_error() === JSON_ERROR_NONE)
        {
            return json_encode($this->sanitize_debug_output($json_data, $sensitive_keys));
        }

        // Check for SQL queries
        if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\b/i', $string))
        {
            return '[SQL_QUERY_SANITIZED]';
        }

        return $string;
    }

    /**
     * Check if a key is sensitive
     */
    private function is_sensitive_key($key, $sensitive_keys)
    {
        $key_lower = strtolower($key);

        foreach ($sensitive_keys as $sensitive_pattern)
        {
            if (strpos($key_lower, strtolower($sensitive_pattern)) !== false)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize WordPress error objects
     */
    private function sanitize_wp_error($wp_error)
    {
        return array(
            '_type' => 'WP_Error',
            'error_codes' => $wp_error->get_error_codes(),
            'error_messages' => $wp_error->get_error_messages(),
            'error_data' => '[SANITIZED]'
        );
    }

    /**
     * Sanitize WordPress database objects
     */
    private function sanitize_wpdb_object($wpdb_object)
    {
        return array(
            '_type' => 'wpdb',
            'dbname' => $wpdb_object->dbname ?? '[SANITIZED]',
            'dbuser' => '[SANITIZED]',
            'dbpassword' => '[SANITIZED]',
            'dbhost' => $wpdb_object->dbhost ?? '[NOT_SET]',
            'charset' => $wpdb_object->charset ?? '',
            'collate' => $wpdb_object->collate ?? '',
            'is_mysql' => $wpdb_object->is_mysql ?? false,
            'ready' => $wpdb_object->ready ?? false,
            'last_error' => $wpdb_object->last_error ?? '',
            'num_queries' => $wpdb_object->num_queries ?? 0,
            'table_prefix' => $wpdb_object->prefix ?? '',
            'tables' => array_keys($wpdb_object->tables ?? []),
            '_sanitized' => 'Database credentials and sensitive connection details have been sanitized'
        );
    }

    // =============================================================================
    // PREFIX AND FORMATTING
    // =============================================================================

    /**
     * Get debug prefix based on component and level
     *
     * @param string $component Component identifier
     * @param int $level Debug level
     * @return string Debug prefix
     */
    private function get_debug_prefix($component, $level)
    {
        // Get the appropriate prefix for this component
        $base_prefix = $this->component_prefixes[$component] ?? "Operaton DMN {$component}";

        // Add level indicators
        $level_suffixes = array(
            1 => ' [MIN]: ',    // MINIMAL
            2 => ': ',          // STANDARD
            3 => ' [VERBOSE]: ', // VERBOSE
            4 => ' [DIAG]: '     // DIAGNOSTIC
        );

        return $base_prefix . ($level_suffixes[$level] ?? ': ');
    }

    // =============================================================================
    // JAVASCRIPT INTEGRATION
    // =============================================================================

    /**
     * Handle AJAX requests from JavaScript logging
     */
    public function handle_ajax_log()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'operaton_debug_nonce'))
        {
            wp_die('Security check failed');
        }

        $component = sanitize_text_field($_POST['component'] ?? 'Frontend');
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $level = (int) ($_POST['level'] ?? self::DEBUG_LEVEL_STANDARD);
        $data = $_POST['data'] ?? null;

        // Decode JSON data if provided
        if ($data && is_string($data))
        {
            $data = json_decode(stripslashes($data), true);
        }

        $this->log($component, '[JS] ' . $message, $data, $level);

        wp_send_json_success('Log recorded');
    }

    /**
     * Flush buffered JavaScript logs
     */
    private function flush_js_logs()
    {
        foreach ($this->js_logs_buffer as $log_entry)
        {
            $this->log(
                $log_entry['component'],
                '[JS] ' . $log_entry['message'],
                $log_entry['data'],
                $log_entry['level']
            );
        }

        $this->js_logs_buffer = array();
    }

    /**
     * Output debug configuration for JavaScript
     */
    public function output_debug_config()
    {
        if (!$this->should_log(self::DEBUG_LEVEL_STANDARD))
        {
            return;
        }

        $debug_config = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('operaton_debug_nonce'),
            'debug_level' => $this->get_debug_level(),
            'components' => array_keys($this->component_prefixes)
        );

        echo '<script type="text/javascript">';
        echo 'window.OperatonDebugConfig = ' . json_encode($debug_config) . ';';
        echo '</script>';
    }

    // =============================================================================
    // UTILITY METHODS
    // =============================================================================

    /**
     * Get current debug configuration for admin display
     *
     * @return array Debug configuration info
     */
    public function get_debug_config()
    {
        return array(
            'wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'wp_debug_log' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
            'wp_debug_display' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : false,
            'operaton_debug_level' => defined('OPERATON_DEBUG_LEVEL') ? OPERATON_DEBUG_LEVEL : 'not_set',
            'current_level' => $this->get_debug_level(),
            'level_name' => $this->get_debug_level_name($this->get_debug_level()),
            'is_development' => $this->is_development_environment(),
            'log_file_path' => ini_get('error_log'),
            'available_levels' => array(
                self::DEBUG_LEVEL_NONE => 'None (Disabled)',
                self::DEBUG_LEVEL_MINIMAL => 'Minimal (Errors Only)',
                self::DEBUG_LEVEL_STANDARD => 'Standard (Normal Operations)',
                self::DEBUG_LEVEL_VERBOSE => 'Verbose (Detailed Info)',
                self::DEBUG_LEVEL_DIAGNOSTIC => 'Diagnostic (Full Debug)'
            ),
            'environment_details' => array(
                'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
                'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            )
        );
    }

    /**
     * Get human-readable name for debug level
     *
     * @param int $level Debug level
     * @return string Level name
     */
    private function get_debug_level_name($level)
    {
        $names = array(
            self::DEBUG_LEVEL_NONE => 'None',
            self::DEBUG_LEVEL_MINIMAL => 'Minimal',
            self::DEBUG_LEVEL_STANDARD => 'Standard',
            self::DEBUG_LEVEL_VERBOSE => 'Verbose',
            self::DEBUG_LEVEL_DIAGNOSTIC => 'Diagnostic'
        );

        return $names[$level] ?? 'Unknown';
    }

    /**
     * Clear debug level cache (useful for testing and admin interface)
     */
    public function clear_debug_level_cache()
    {
        self::$debug_level_cache = null;
    }

    /**
     * Add a new component prefix
     *
     * @param string $component Component identifier
     * @param string $prefix Full prefix for logging
     */
    public function add_component_prefix($component, $prefix)
    {
        $this->component_prefixes[$component] = $prefix;
    }

    /**
     * Get all registered components
     *
     * @return array List of component identifiers
     */
    public function get_components()
    {
        return array_keys($this->component_prefixes);
    }
}

// =============================================================================
// GLOBAL CONVENIENCE FUNCTIONS
// =============================================================================

/**
 * Main global debug function - replaces all trait usage
 *
 * @param string $component Component identifier (API, Admin, Database, etc.)
 * @param string $message Debug message
 * @param mixed $data Optional data to log
 * @param int $level Debug level (default: STANDARD)
 */
function operaton_debug($component, $message, $data = null, $level = Operaton_DMN_Debug_Manager::DEBUG_LEVEL_STANDARD)
{
    Operaton_DMN_Debug_Manager::get_instance()->log($component, $message, $data, $level);
}

/**
 * JavaScript debug function
 *
 * @param string $component Component identifier
 * @param string $message Debug message
 * @param mixed $data Optional data to log
 * @param int $level Debug level
 */
function operaton_debug_js($component, $message, $data = null, $level = Operaton_DMN_Debug_Manager::DEBUG_LEVEL_STANDARD)
{
    Operaton_DMN_Debug_Manager::get_instance()->log_js($component, $message, $data, $level);
}

/**
 * Convenience functions for different log levels
 */
function operaton_debug_minimal($component, $message, $data = null)
{
    Operaton_DMN_Debug_Manager::get_instance()->log_minimal($component, $message, $data);
}

function operaton_debug_standard($component, $message, $data = null)
{
    Operaton_DMN_Debug_Manager::get_instance()->log_standard($component, $message, $data);
}

function operaton_debug_verbose($component, $message, $data = null)
{
    Operaton_DMN_Debug_Manager::get_instance()->log_verbose($component, $message, $data);
}

function operaton_debug_diagnostic($component, $message, $data = null)
{
    Operaton_DMN_Debug_Manager::get_instance()->log_diagnostic($component, $message, $data);
}

/**
 * Specialized logging functions
 */
function operaton_debug_database($component, $message, $data = null)
{
    Operaton_DMN_Debug_Manager::get_instance()->log_database($component, $message, $data);
}

function operaton_debug_api($component, $message, $data = null)
{
    Operaton_DMN_Debug_Manager::get_instance()->log_api($component, $message, $data);
}

function operaton_debug_config($component, $message, $data = null)
{
    Operaton_DMN_Debug_Manager::get_instance()->log_config($component, $message, $data);
}

function operaton_debug_performance($component, $message, $metrics = null)
{
    Operaton_DMN_Debug_Manager::get_instance()->log_performance($component, $message, $metrics);
}

function operaton_debug_emergency($component, $message, $data = null)
{
    Operaton_DMN_Debug_Manager::get_instance()->log_emergency($component, $message, $data);
}

/**
 * Get debug manager instance for advanced usage
 *
 * @return Operaton_DMN_Debug_Manager
 */
function operaton_debug_manager()
{
    return Operaton_DMN_Debug_Manager::get_instance();
}
