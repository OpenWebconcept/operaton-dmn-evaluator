# Operaton DMN API Debug Enhanced Trait - Documentation

## Overview

The `Operaton_DMN_API_Debug_Enhanced` trait provides a comprehensive, secure debug logging system for the Operaton DMN WordPress plugin. This trait implements level-controlled debug logging with automatic sensitive information sanitization, intelligent environment detection, and performance optimization. The system ensures that credential exposure is prevented while maintaining maximum debugging utility for development and troubleshooting.

The debug system features five distinct logging levels (None, Minimal, Standard, Verbose, Diagnostic), automatic sensitive data sanitization with configurable keys, intelligent development environment detection, performance optimization through debug level caching, and comprehensive logging convenience methods for different operation types.

## Trait Architecture

The debug trait organizes functionality into logical groups:

- **Security Hardening**: Sensitive data sanitization and protection mechanisms
- **Debug Level Management**: Level determination, caching, and environment detection  
- **Enhanced Logging System**: Core logging functionality with level control
- **Convenience Methods**: Operation-specific logging helpers
- **Debug Utilities**: Configuration reporting and cache management
- **Emergency Logging**: Critical error logging that bypasses restrictions

## Debug Level Configuration

### WordPress Configuration Constants

Add these constants to your `wp-config.php` file to configure debug logging levels:

```php
// Enable WordPress debugging (required for all Operaton debug logging)
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false); // Don't display errors on frontend

// Set Operaton DMN debug level (0-4)
define('OPERATON_DEBUG_LEVEL', 4); // Diagnostic level for full debugging

// Alternative: Set via environment variable
// $_ENV['OPERATON_DEBUG_LEVEL'] = 4;
```

### Available Debug Levels

| Level | Constant | Name | Description | Log Prefix |
|-------|----------|------|-------------|------------|
| 0 | `DEBUG_LEVEL_NONE` | None (Disabled) | No debug logging | N/A |
| 1 | `DEBUG_LEVEL_MINIMAL` | Minimal (Errors Only) | Critical errors and warnings only | `[MIN]` |
| 2 | `DEBUG_LEVEL_STANDARD` | Standard (Normal Operations) | Standard operations and important events | No prefix |
| 3 | `DEBUG_LEVEL_VERBOSE` | Verbose (Detailed Info) | Detailed operational information | `[VERBOSE]` |
| 4 | `DEBUG_LEVEL_DIAGNOSTIC` | Diagnostic (Full Debug) | Complete debugging information | `[DIAG]` |

### Environment Detection

The debug system automatically detects development environments based on:

- **Local development indicators**: `localhost`, `.local`, `.dev`, `.test` domains
- **Staging environments**: `test.open-regels-lab.nl`, `open-regels-lab` hosts
- **Custom environment detection**: Configurable through host patterns

## Class Properties

### Debug Level Management
- **`$debug_level_cache`**: Static cache for current debug level to prevent repeated calculations and improve performance

### Debug Level Constants
- **`DEBUG_LEVEL_NONE (0)`**: Disables all debug logging
- **`DEBUG_LEVEL_MINIMAL (1)`**: Logs only critical errors and warnings
- **`DEBUG_LEVEL_STANDARD (2)`**: Standard operational logging
- **`DEBUG_LEVEL_VERBOSE (3)`**: Detailed operational information
- **`DEBUG_LEVEL_DIAGNOSTIC (4)`**: Complete debugging with full data dumps

## Method Groupings by Functionality

### Security Hardening - Sensitive Data Sanitization

These methods ensure that sensitive information is never exposed in debug logs while maintaining debugging utility.

**`sanitize_debug_output($data, $additional_sensitive_keys = array())`**  
Core sanitization method that removes or masks sensitive information from debug output. Handles nested arrays and objects recursively, applies default sensitive key patterns (password, token, key, secret), supports additional custom sensitive keys, and maintains data structure while protecting sensitive values.

**`is_sensitive_key($key)`**  
Determines if a data key contains sensitive information using pattern matching. Checks against common sensitive patterns including authentication tokens, API keys, passwords and credentials, database connection strings, and custom application secrets.

**`sanitize_sensitive_value($value)`**  
Safely sanitizes sensitive values while preserving data type information. Handles different value types appropriately (strings, numbers, booleans), provides masked output for debugging purposes, maintains value structure for complex types, and ensures no credential exposure in logs.

### Debug Level Management

These methods handle debug level determination, caching, and environment detection for optimal performance.

**`get_debug_level()`**  
Primary debug level determination method with intelligent caching. Checks WordPress constant `OPERATON_DEBUG_LEVEL` first, falls back to environment variable detection, implements caching to prevent repeated calculations, and provides default level based on environment detection.

**`is_development_environment()`**  
Intelligent development environment detection using multiple indicators. Detects local development environments (localhost, .local, .dev, .test), identifies staging environments (test.open-regels-lab.nl patterns), supports custom environment detection patterns, and provides reliable environment classification.

**`should_log($required_level = null)`**  
Determines if logging should occur at specified level with efficient level comparison. Compares current debug level against required level, defaults to standard level when not specified, provides early exit for disabled logging, and optimizes performance through cached level checking.

**`clear_debug_level_cache()`**  
Clears cached debug level for testing and configuration changes. Resets static cache variable, forces fresh level calculation on next access, useful for admin interface integration, and supports dynamic configuration changes.

### Enhanced Logging System

These methods provide the core debug logging functionality with level control and data sanitization.

**`debug_log($message, $data = null, $level = null, $additional_sensitive_keys = array())`**  
Primary logging method with comprehensive features. Validates logging level requirements, applies appropriate debug prefixes, sanitizes sensitive data automatically, formats complex data as JSON, and logs to WordPress error log with proper formatting.

**`get_debug_prefix($level)`**  
Generates appropriate debug prefixes based on logging level. Returns level-specific prefixes for log filtering, enables easy log parsing and analysis, maintains consistent formatting across all log entries, and supports multiple logging tools integration.

**`log_emergency($message, $data = null)`**  
Critical error logging that bypasses all level restrictions. Always logs regardless of debug level setting, used only for critical system errors, maintains special emergency prefix, and ensures critical issues are never missed.

### Convenience Methods for Different Log Levels

These methods provide easy-to-use logging interfaces for different debugging scenarios.

**`log_minimal($message, $data = null)`**  
Logs minimal level messages for critical errors and warnings. Automatically applies minimal debug level, used for critical system issues, provides consistent error logging, and ensures important issues are captured.

**`log_standard($message, $data = null)`**  
Logs standard operational messages for normal plugin operations. Uses default standard debug level, captures routine operational information, provides baseline logging for troubleshooting, and maintains operational audit trail.

**`log_verbose($message, $data = null)`**  
Logs detailed operational information for comprehensive debugging. Provides detailed operational context, captures intermediate processing states, enables detailed troubleshooting workflows, and supports complex debugging scenarios.

**`log_diagnostic($message, $data = null)`**  
Logs complete debugging information with full data dumps. Captures maximum debugging detail, includes complete variable states, enables comprehensive system analysis, and supports advanced troubleshooting needs.

### Operation-Specific Logging Methods

These methods provide specialized logging for different types of operations with automatic sensitive data handling.

**`log_database($message, $data = null)`**  
Database operation logging with automatic query sanitization. Sanitizes database-specific sensitive information (queries, connections, credentials), maintains standard logging level, captures database operation context, and protects sensitive database information.

**`log_api($message, $data = null)`**  
API operation logging with automatic credential sanitization. Sanitizes API-specific sensitive data (headers, authorization, endpoints), captures API request/response information, maintains API operation audit trail, and protects authentication information.

**`log_config($message, $data = null)`**  
Configuration operation logging with field mapping sanitization. Sanitizes configuration-specific sensitive data (field mappings, credentials, secrets), captures configuration changes and validations, maintains configuration audit trail, and protects sensitive configuration data.

**`log_performance($message, $metrics = null)`**  
Performance monitoring logging with metric sanitization. Sanitizes performance-specific sensitive data, captures timing and resource usage information, maintains verbose logging level for detailed analysis, and supports performance optimization efforts.

### Debug Utility Methods

These methods provide configuration reporting and system information for administrative interfaces.

**`get_debug_config()`**  
Comprehensive debug configuration reporting for admin display. Returns current WordPress debug settings, reports current Operaton debug level and name, provides environment detection results, includes available debug levels information, and shows log file path and system details.

**`get_debug_level_name($level)`**  
Human-readable debug level name conversion for user interfaces. Converts numeric levels to descriptive names, supports admin interface display, provides user-friendly level descriptions, and maintains consistent naming conventions.

## WordPress Integration Points

### Debug Configuration Constants
- **`WP_DEBUG`**: Must be true for any Operaton debug logging
- **`WP_DEBUG_LOG`**: Enables WordPress error logging to file
- **`WP_DEBUG_DISPLAY`**: Controls frontend error display (recommend false)
- **`OPERATON_DEBUG_LEVEL`**: Sets Operaton-specific debug level (0-4)

### Environment Variables
- **`OPERATON_DEBUG_LEVEL`**: Alternative to WordPress constant
- **`$_SERVER['HTTP_HOST']`**: Used for environment detection
- **`$_SERVER['SERVER_NAME']`**: Backup for environment detection

### WordPress Error Logging
- **`error_log()`**: Core WordPress logging function integration
- **`ini_get('error_log')`**: Log file path detection for configuration
- WordPress transient API for configuration caching

## Security Features

### Sensitive Data Protection
- **Automatic sanitization**: All logged data automatically sanitized
- **Pattern-based detection**: Intelligent sensitive key identification
- **Configurable keys**: Support for additional sensitive data patterns
- **Value masking**: Sensitive values masked while preserving structure

### Environment-Based Security
- **Development detection**: Automatic development environment identification
- **Production safety**: Reduced logging in production environments
- **Configurable patterns**: Custom environment detection rules
- **Host-based detection**: Multiple host pattern matching strategies

## Performance Optimization Features

### Caching and Efficiency
- **Debug level caching**: Static cache prevents repeated calculations
- **Early exit optimization**: Quick level checking prevents unnecessary processing
- **Conditional sanitization**: Sanitization only when logging will occur
- **Efficient pattern matching**: Optimized sensitive key detection

### Resource Management
- **Memory efficient**: Minimal memory overhead for disabled logging
- **CPU optimization**: Level checking optimized for performance
- **I/O efficiency**: Grouped log writes when possible
- **Cache management**: Intelligent cache invalidation strategies

## Error Handling and Logging

### Comprehensive Error Management
- **Exception safety**: All methods handle exceptions gracefully
- **Fallback mechanisms**: Multiple fallback strategies for logging
- **Safe defaults**: Conservative defaults for production safety
- **Recovery options**: Graceful handling of logging failures

### Debug and Monitoring
- **Self-monitoring**: Debug system monitors its own operation
- **Configuration validation**: Validates debug configuration settings
- **Health reporting**: Reports debug system health status
- **Performance metrics**: Tracks debug system performance impact

## Usage Examples

### Basic Debug Logging
```php
// Standard operational logging
$this->log_standard('Configuration loaded', $config_data);

// Verbose debugging with detailed information
$this->log_verbose('API request prepared', $request_details);

// Diagnostic logging with complete data dump
$this->log_diagnostic('Variable processing complete', $all_variables);
```

### Operation-Specific Logging
```php
// Database operations with automatic query sanitization
$this->log_database('Configuration retrieved', $database_result);

// API operations with credential protection
$this->log_api('External API call initiated', $api_request);

// Configuration changes with sensitive data protection
$this->log_config('Field mappings updated', $mapping_changes);
```

### Performance Monitoring
```php
// Performance metrics with timing information
$performance_data = array(
    'execution_time' => 125.5,
    'memory_usage' => '2.5MB',
    'api_calls' => 3
);
$this->log_performance('Evaluation completed', $performance_data);
```

### Emergency Logging
```php
// Critical errors that must always be logged
$this->log_emergency('Critical system failure', $error_context);
```

## Configuration Examples

### Development Environment Setup
```php
// wp-config.php for development
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('OPERATON_DEBUG_LEVEL', 4); // Full diagnostic logging
```

### Production Environment Setup
```php
// wp-config.php for production
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('OPERATON_DEBUG_LEVEL', 1); // Minimal error logging only
```

### Staging Environment Setup
```php
// wp-config.php for staging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('OPERATON_DEBUG_LEVEL', 3); // Verbose logging for testing
```

## Log Output Examples

### Standard Level Output
```
[25-Sep-2025 18:00:04 UTC] Operaton DMN API: Configuration loaded successfully
[25-Sep-2025 18:00:04 UTC] Operaton DMN API: Starting DMN evaluation process
```

### Verbose Level Output
```
[25-Sep-2025 18:00:04 UTC] Operaton DMN API [VERBOSE]: API request prepared
[25-Sep-2025 18:00:04 UTC] Operaton DMN API [VERBOSE]: Data: {
    "endpoint": "https://operaton.example.com/engine-rest/decision-definition/key/MyDecision/evaluate",
    "method": "POST"
}
```

### Diagnostic Level Output
```
[25-Sep-2025 18:00:04 UTC] Operaton DMN API [DIAG]: Variable processing initiated
[25-Sep-2025 18:00:04 UTC] Operaton DMN API [DIAG]: Data: {
    "input_variables": {
        "customerAge": 25,
        "customerType": "premium"
    },
    "processing_time_ms": 12.5
}
```

### Sanitized Sensitive Data Output
```
[25-Sep-2025 18:00:04 UTC] Operaton DMN API: API configuration loaded
[25-Sep-2025 18:00:04 UTC] Operaton DMN API: Data: {
    "endpoint": "https://operaton.example.com/engine-rest",
    "api_key": "***SANITIZED***",
    "timeout": 30
}
```

## Asset Dependencies

The debug trait coordinates with the following systems and dependencies:

### WordPress Core
- **Error logging system**: WordPress `error_log()` function integration
- **Constants API**: WordPress constant definitions for configuration
- **Environment detection**: WordPress server variable access
- **Transient API**: WordPress caching system for configuration

### PHP Core Features
- **JSON processing**: PHP JSON encoding with comprehensive error handling
- **Error handling**: PHP exception handling and error reporting
- **Regular expressions**: Pattern matching for sensitive data detection
- **Static variables**: PHP static caching for performance optimization

### Plugin Integration
- **Core plugin instance**: Integration with main plugin functionality
- **Manager instances**: Coordination with other plugin managers
- **Configuration system**: Integration with plugin configuration management
- **Performance monitoring**: Coordination with performance tracking systems

All methods maintain strict security standards while providing comprehensive debugging capability, ensuring that sensitive information is never exposed while maintaining maximum utility for development and troubleshooting scenarios.