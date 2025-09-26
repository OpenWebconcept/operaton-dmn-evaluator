<?php

/**
 * Enhanced Debug System Trait for Operaton DMN Plugin
 *
 * Provides secure, level-controlled debug logging that automatically
 * sanitizes sensitive information while maintaining debugging utility.
 * Includes intelligent environment detection and performance optimization.
 *
 * @package OperatonDMN
 * @subpackage API
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

trait Operaton_DMN_API_Debug_Enhanced
{
    /**
     * Current debug level cache
     * @var int|null
     */
    private static $debug_level_cache = null;

    // =============================================================================
    // SECURITY HARDENING - SENSITIVE DATA SANITIZATION
    // =============================================================================

    /**
     * Sanitize debug output to protect sensitive information
     *
     * Removes or masks sensitive data from debug output to prevent
     * credential exposure in logs while maintaining debugging capability.
     *
     * @since 1.0.0
     * @param mixed $data Data to sanitize
     * @param array $additional_keys Additional sensitive keys to sanitize
     * @return mixed Sanitized data
     */
    private function sanitize_debug_output($data, $additional_keys = array())
    {
        // Default sensitive keys based on your actual debug log issues
        $sensitive_keys = array(
            'password', 'dbpassword', 'api_key', 'secret', 'token',
            'auth_token', 'access_token', 'refresh_token', 'private_key',
            'client_secret', 'webhook_secret', 'session_id', 'nonce',
            'dbuser', 'database_user', 'db_user', 'username', 'user_login',
            'authorization', 'cookie', 'session', 'csrf_token',
            // WordPress specific
            'dbpassword:protected', 'dbuser:protected'
        );

        // Merge with additional keys
        $sensitive_keys = array_merge($sensitive_keys, $additional_keys);

        return $this->recursive_sanitize($data, $sensitive_keys);
    }

    /**
     * Recursively sanitize data structures
     *
     * @param mixed $data Data to sanitize
     * @param array $sensitive_keys Keys to sanitize
     * @return mixed Sanitized data
     */
    private function recursive_sanitize($data, $sensitive_keys)
    {
        if (is_array($data)) {
            $sanitized = array();
            foreach ($data as $key => $value) {
                if ($this->is_sensitive_key($key, $sensitive_keys)) {
                    $sanitized[$key] = $this->mask_sensitive_value($value);
                } else {
                    $sanitized[$key] = $this->recursive_sanitize($value, $sensitive_keys);
                }
            }
            return $sanitized;
        } elseif (is_object($data)) {
            return $this->sanitize_object($data, $sensitive_keys);
        }

        return $data;
    }

    /**
     * Check if a key is considered sensitive
     *
     * @param string $key Key to check
     * @param array $sensitive_keys Sensitive keys list
     * @return bool True if key is sensitive
     */
    private function is_sensitive_key($key, $sensitive_keys)
    {
        $key_lower = strtolower((string)$key);

        foreach ($sensitive_keys as $sensitive_key) {
            if (strpos($key_lower, strtolower($sensitive_key)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask sensitive values
     *
     * @param mixed $value Value to mask
     * @return string Masked value
     */
    private function mask_sensitive_value($value)
    {
        if (empty($value)) {
            return '[EMPTY]';
        }

        $value_str = (string)$value;
        $length = strlen($value_str);

        if ($length <= 4) {
            return '[REDACTED]';
        } elseif ($length <= 8) {
            return substr($value_str, 0, 2) . str_repeat('*', $length - 4) . substr($value_str, -2);
        } else {
            return substr($value_str, 0, 3) . str_repeat('*', min($length - 6, 20)) . substr($value_str, -3);
        }
    }

    /**
     * Sanitize object properties - Special handling for WordPress objects
     *
     * @param object $object Object to sanitize
     * @param array $sensitive_keys Sensitive keys list
     * @return array Sanitized object representation
     */
    private function sanitize_object($object, $sensitive_keys)
    {
        $sanitized = array(
            '_object_class' => get_class($object),
            '_object_type' => 'object'
        );

        // Handle specific object types from your debug logs
        if ($object instanceof wpdb) {
            return $this->sanitize_wpdb_object($object);
        }

        // Handle stdClass objects (common in your API responses)
        if ($object instanceof stdClass) {
            foreach ($object as $property => $value) {
                if ($this->is_sensitive_key($property, $sensitive_keys)) {
                    $sanitized[$property] = $this->mask_sensitive_value($value);
                } else {
                    $sanitized[$property] = $this->recursive_sanitize($value, $sensitive_keys);
                }
            }
            return $sanitized;
        }

        // Generic object sanitization using reflection
        try {
            $reflection = new ReflectionClass($object);
            $properties = $reflection->getProperties();

            foreach ($properties as $property) {
                $property->setAccessible(true);
                $name = $property->getName();

                try {
                    $value = $property->getValue($object);

                    if ($this->is_sensitive_key($name, $sensitive_keys)) {
                        $sanitized[$name] = $this->mask_sensitive_value($value);
                    } else {
                        $sanitized[$name] = $this->recursive_sanitize($value, $sensitive_keys);
                    }
                } catch (Exception $e) {
                    $sanitized[$name] = '[INACCESSIBLE: ' . $e->getMessage() . ']';
                }
            }
        } catch (Exception $e) {
            $sanitized['_reflection_error'] = $e->getMessage();
        }

        return $sanitized;
    }

    /**
     * Special handling for WordPress database objects (addresses your debug log issue)
     *
     * @param wpdb $wpdb_object WordPress database object
     * @return array Sanitized database object info
     */
    private function sanitize_wpdb_object($wpdb_object)
    {
        return array(
            '_object_class' => 'wpdb',
            '_object_type' => 'database_connection',
            'dbname' => $this->mask_sensitive_value($wpdb_object->dbname ?? ''),
            'dbhost' => $wpdb_object->dbhost ?? '[NOT_SET]',
            'charset' => $wpdb_object->charset ?? '',
            'collate' => $wpdb_object->collate ?? '',
            'is_mysql' => $wpdb_object->is_mysql ?? false,
            'ready' => $wpdb_object->ready ?? false,
            'last_error' => $wpdb_object->last_error ?? '',
            'num_queries' => $wpdb_object->num_queries ?? 0,
            'table_prefix' => $wpdb_object->prefix ?? '',
            'tables' => array_keys($wpdb_object->tables ?? []),
            'connection_info' => [
                'client_info' => $wpdb_object->dbh->client_info ?? 'N/A',
                'server_info' => $wpdb_object->dbh->server_info ?? 'N/A',
                'host_info' => $wpdb_object->dbh->host_info ?? 'N/A',
                'affected_rows' => $wpdb_object->dbh->affected_rows ?? 0,
                'errno' => $wpdb_object->dbh->errno ?? 0,
                'error' => $wpdb_object->dbh->error ?? '',
            ],
            '_sanitized' => 'Database credentials and sensitive connection details have been sanitized'
        );
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
    protected function get_debug_level()
    {
        // Use cached value if available
        if (self::$debug_level_cache !== null) {
            return self::$debug_level_cache;
        }

        // If WP_DEBUG is completely off, no debugging
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            self::$debug_level_cache = static::DEBUG_LEVEL_NONE;
            return self::$debug_level_cache;
        }

        // Check for explicit Operaton debug level setting
        if (defined('OPERATON_DEBUG_LEVEL')) {
            self::$debug_level_cache = max(0, min(4, (int)OPERATON_DEBUG_LEVEL));
            return self::$debug_level_cache;
        }

        // Check for WordPress debug log setting
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            self::$debug_level_cache = static::DEBUG_LEVEL_STANDARD;
        } else {
            self::$debug_level_cache = static::DEBUG_LEVEL_MINIMAL;
        }

        // Check for development environment indicators
        if ($this->is_development_environment()) {
            self::$debug_level_cache = max(self::$debug_level_cache, static::DEBUG_LEVEL_VERBOSE);
        }

        return self::$debug_level_cache;
    }

    /**
     * Check if this is a development environment
     * Enhanced detection based on your test environment
     *
     * @return bool True if development environment detected
     */
    private function is_development_environment()
    {
        // Check for common development indicators
        $dev_indicators = array(
            defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
            defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
            strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false,
            strpos($_SERVER['HTTP_HOST'] ?? '', '.local') !== false,
            strpos($_SERVER['HTTP_HOST'] ?? '', '.dev') !== false,
            strpos($_SERVER['HTTP_HOST'] ?? '', '.test') !== false,
            // Based on your debug logs - detect your specific test environment
            strpos($_SERVER['HTTP_HOST'] ?? '', 'test.open-regels-lab.nl') !== false,
            strpos($_SERVER['HTTP_HOST'] ?? '', 'open-regels-lab') !== false,
        );

        return in_array(true, $dev_indicators, true);
    }

    /**
     * Check if logging should occur at the specified level
     *
     * @param int $required_level Minimum level required for logging
     * @return bool True if logging should occur
     */
    protected function should_log($required_level = null)
    {
        if ($required_level === null) {
            $required_level = static::DEBUG_LEVEL_STANDARD;
        }
        return $this->get_debug_level() >= $required_level;
    }

    /**
     * Enhanced debug logging with level control and sanitization
     *
     * @param string $message Debug message
     * @param mixed $data Optional data to log
     * @param int $level Debug level required
     * @param array $additional_sensitive_keys Additional keys to sanitize
     */
    protected function debug_log($message, $data = null, $level = null, $additional_sensitive_keys = array())
    {
        if ($level === null) {
            $level = static::DEBUG_LEVEL_STANDARD;
        }
    {
        if (!$this->should_log($level)) {
            return;
        }

        $prefix = $this->get_debug_prefix($level);
        $log_message = $prefix . $message;

        // Log the main message
        error_log($log_message);

        // Log data if provided
        if ($data !== null) {
            $sanitized_data = $this->sanitize_debug_output($data, $additional_sensitive_keys);

            if (is_array($sanitized_data) || is_object($sanitized_data)) {
                error_log($prefix . 'Data: ' . json_encode($sanitized_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                error_log($prefix . 'Data: ' . $sanitized_data);
            }
        }
    }
}

    /**
     * Get debug prefix based on level
     *
     * @param int $level Debug level
     * @return string Debug prefix
     */
    protected function get_debug_prefix($level)
    {
        // Automatically detect the class context
        $class_name = get_class($this);

        // Map class names to friendly prefixes
        $class_prefixes = array(
            'Operaton_DMN_API' => 'Operaton DMN API',
            'Operaton_DMN_Admin' => 'Operaton DMN Admin',
            'Operaton_DMN_Database' => 'Operaton DMN Database',
            'Operaton_DMN_Assets' => 'Operaton DMN Assets',
            'Operaton_DMN_Gravity_Forms' => 'Operaton DMN Gravity Forms',
            'OperatonDMNEvaluator' => 'Operaton DMN Main'
        );

        // Get the appropriate prefix for this class
        $base_prefix = $class_prefixes[$class_name] ?? 'Operaton DMN';

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
    // CONVENIENCE METHODS FOR DIFFERENT LOG LEVELS
    // =============================================================================

    /**
     * Log minimal level messages (errors, critical warnings)
     */
    protected function log_minimal($message, $data = null)
    {
        $this->debug_log($message, $data, static::DEBUG_LEVEL_MINIMAL);
    }

    /**
     * Log standard level messages (normal operations)
     */
    protected function log_standard($message, $data = null)
    {
        $this->debug_log($message, $data, static::DEBUG_LEVEL_STANDARD);
    }

    /**
     * Log verbose level messages (detailed operations)
     */
    protected function log_verbose($message, $data = null)
    {
        $this->debug_log($message, $data, static::DEBUG_LEVEL_VERBOSE);
    }

    /**
     * Log diagnostic level messages (full debugging info)
     */
    protected function log_diagnostic($message, $data = null)
    {
        $this->debug_log($message, $data, static::DEBUG_LEVEL_DIAGNOSTIC);
    }

    /**
     * Log database operations with automatic sanitization
     */
    protected function log_database($message, $data = null)
    {
        $db_sensitive_keys = array('query', 'sql', 'prepare', 'wpdb', 'connection');
        $this->debug_log($message, $data, static::DEBUG_LEVEL_STANDARD, $db_sensitive_keys);
    }

    /**
     * Log API operations with automatic sanitization
     */
    protected function log_api($message, $data = null)
    {
        $api_sensitive_keys = array('headers', 'authorization', 'body', 'response', 'curl', 'endpoint');
        $this->debug_log($message, $data, static::DEBUG_LEVEL_STANDARD, $api_sensitive_keys);
    }

    /**
     * Log configuration operations with automatic sanitization
     */
    protected function log_config($message, $data = null)
    {
        $config_sensitive_keys = array('field_mappings', 'result_mappings', 'credentials', 'secrets');
        $this->debug_log($message, $data, static::DEBUG_LEVEL_STANDARD, $config_sensitive_keys);
    }

    // =============================================================================
    // DEBUG UTILITY METHODS
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
                static::DEBUG_LEVEL_NONE => 'None (Disabled)',
                static::DEBUG_LEVEL_MINIMAL => 'Minimal (Errors Only)',
                static::DEBUG_LEVEL_STANDARD => 'Standard (Normal Operations)',
                static::DEBUG_LEVEL_VERBOSE => 'Verbose (Detailed Info)',
                static::DEBUG_LEVEL_DIAGNOSTIC => 'Diagnostic (Full Debug)'
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
            static::DEBUG_LEVEL_NONE => 'None',
            static::DEBUG_LEVEL_MINIMAL => 'Minimal',
            static::DEBUG_LEVEL_STANDARD => 'Standard',
            static::DEBUG_LEVEL_VERBOSE => 'Verbose',
            static::DEBUG_LEVEL_DIAGNOSTIC => 'Diagnostic'
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
     * Log performance metrics with automatic sanitization
     */
    protected function log_performance($message, $metrics = null)
    {
        if ($metrics) {
            $performance_sensitive_keys = array('memory_usage', 'query_details', 'timing');
            $this->debug_log($message, $metrics, static::DEBUG_LEVEL_VERBOSE, $performance_sensitive_keys);
        } else {
            $this->debug_log($message, null, static::DEBUG_LEVEL_VERBOSE);
        }
    }

    /**
     * Emergency logging that bypasses level restrictions
     * Only use for critical errors that must always be logged
     *
     * @param string $message Critical error message
     * @param mixed $data Critical error data
     */
    protected function log_emergency($message, $data = null)
    {
        $emergency_prefix = 'Operaton DMN API [EMERGENCY]: ';
        error_log($emergency_prefix . $message);

        if ($data !== null) {
            $sanitized_data = $this->sanitize_debug_output($data);
            if (is_array($sanitized_data) || is_object($sanitized_data)) {
                error_log($emergency_prefix . 'Data: ' . json_encode($sanitized_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                error_log($emergency_prefix . 'Data: ' . $sanitized_data);
            }
        }
    }
}
