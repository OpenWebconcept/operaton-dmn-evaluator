# Operaton DMN Global Debug Manager - Documentation

## Overview

The `Operaton_DMN_Debug_Manager` class provides a centralized, component-based debug logging system that replaces the previous trait-based architecture. This singleton-pattern manager implements secure, level-controlled debug logging with automatic sensitive information sanitization, JavaScript integration, and component-based organization. The system ensures credential exposure is prevented while maintaining maximum debugging utility for development and troubleshooting across all plugin components.

The debug manager features five distinct logging levels (None, Minimal, Standard, Verbose, Diagnostic), component-based log organization for better filtering and analysis, automatic sensitive data sanitization with configurable patterns, JavaScript bridge for frontend debugging via AJAX, singleton pattern ensuring consistent behavior across all components, and comprehensive global convenience functions for easy integration.

## Core Features

### Centralized Architecture
- **Singleton pattern**: Single global instance accessible from anywhere
- **Component organization**: Logs organized by component (API, Database, Assets, etc.)
- **No duplication**: Eliminates trait copying and maintains single source of truth
- **Cross-language support**: Seamless integration between PHP and JavaScript logging

### Security and Data Protection
- **Automatic sanitization**: Comprehensive sensitive data protection
- **Configurable patterns**: Extensible sensitive data detection
- **WordPress object handling**: Special sanitization for WP_Error, wpdb objects
- **Emergency logging**: Critical error logging that bypasses level restrictions

### Performance Optimization
- **Level caching**: Debug level determination cached for performance
- **Conditional execution**: Early exit when logging level not met
- **Buffered JS logging**: JavaScript logs buffered for efficient transmission
- **Environment detection**: Intelligent development environment detection

## Architecture and Design

### Singleton Implementation
The debug manager implements the singleton pattern to ensure consistent behavior across all plugin components:

```php
// Single instance accessible globally
$debug_manager = Operaton_DMN_Debug_Manager::get_instance();

// Or use convenience functions
operaton_debug('Component', 'Message', $data);
```

### Component-Based Organization
Each log entry specifies a component identifier for organized debugging:

- **Main**: Plugin-level operations, bootstrap, global functions
- **Evaluator**: Main plugin class operations and manager coordination
- **API**: External API calls, REST endpoints, DMN evaluation
- **Database**: Database operations, queries, schema management
- **Assets**: CSS/JavaScript loading, asset management
- **Admin**: WordPress admin interface, configuration forms
- **GravityForms**: Gravity Forms integration and form processing
- **Frontend**: Frontend JavaScript, form interactions
- **Performance**: Performance monitoring and metrics
- **Config**: Configuration management and validation
- **Security**: Security-related operations and validation
- **Cache**: Caching operations and cache management
- **Templates**: Template processing and rendering

### Debug Level System
Five comprehensive logging levels provide granular control:

1. **NONE (0)**: No debug output - production mode
2. **MINIMAL (1)**: Critical errors and warnings only
3. **STANDARD (2)**: Normal operations and results
4. **VERBOSE (3)**: Detailed debug information and processing steps
5. **DIAGNOSTIC (4)**: Complete diagnostic data including sanitized sensitive information

## Integration and Usage

### Main Plugin File Integration

The debug manager is integrated early in the main plugin file bootstrap process to ensure availability throughout the entire plugin lifecycle:

#### Bootstrap Integration
```php
// Load debug manager first (before any other classes)
require_once __DIR__ . '/includes/class-operaton-dmn-debug-manager.php';

// Initialize singleton early
Operaton_DMN_Debug_Manager::get_instance();
```

#### Component Organization in Practice

**Global Operations (Component: 'Main')**
```php
// Plugin bootstrap operations
operaton_debug('Main', 'Performance monitor loaded successfully');
operaton_debug('Main', 'Auto-updater loaded successfully', $updater_data);
operaton_debug_verbose('Main', 'Debug tools loading attempted');

// Global status functions
operaton_debug_diagnostic('Main', 'Plugin status check', $status_data);
```

**Main Class Operations (Component: 'Evaluator')**
```php
// Inside OperatonDMNEvaluator class
operaton_debug('Evaluator', 'Starting fresh initialization', ['version' => $version]);
operaton_debug('Evaluator', 'Assets manager loaded successfully');
operaton_debug('Evaluator', 'Database manager initialized');
operaton_debug_verbose('Evaluator', 'Manager relationships established');
```

### Global Convenience Functions

The debug manager provides comprehensive convenience functions for easy integration:

#### Basic Logging Functions
```php
// Standard logging with automatic level selection
operaton_debug('Component', 'Message', $optional_data);

// Level-specific logging
operaton_debug_minimal('Component', 'Critical error', $error_data);
operaton_debug_standard('Component', 'Normal operation', $operation_data);
operaton_debug_verbose('Component', 'Detailed process', $process_data);
operaton_debug_diagnostic('Component', 'Complete diagnostic', $diagnostic_data);
```

#### Specialized Logging Functions
```php
// Database operations with automatic sanitization
operaton_debug_database('Database', 'Query executed', $query_data);

// API operations with credential protection
operaton_debug_api('API', 'External call made', $api_request_data);

// Configuration changes with sensitive data protection
operaton_debug_config('Config', 'Settings updated', $config_changes);

// Performance metrics logging
operaton_debug_performance('Performance', 'Operation completed', $metrics);

// Emergency logging (bypasses all level restrictions)
operaton_debug_emergency('Security', 'Critical failure', $emergency_data);
```

#### JavaScript Integration
```php
// JavaScript logging via AJAX bridge
operaton_debug_js('Frontend', 'Form validation completed', $form_data);
```

### Advanced Usage

#### Direct Manager Access
```php
// Access debug manager directly for advanced operations
$debug_manager = operaton_debug_manager();

// Add custom component prefix
$debug_manager->add_component_prefix('CustomComponent', 'Operaton DMN Custom');

// Clear debug level cache for testing
$debug_manager->clear_debug_level_cache();

// Get debug configuration for admin display
$debug_config = $debug_manager->get_debug_config();
```

## Data Sanitization and Security

### Automatic Sensitive Data Protection
The debug manager automatically sanitizes sensitive information using comprehensive pattern matching:

#### Built-in Sensitive Patterns
- **Credentials**: password, secret, token, api_key, private_key, auth, authorization
- **Database**: db_password, connection_string, dsn, wpdb
- **User Data**: email, user_pass, personal_data, phone, address, ssn
- **System Info**: server_info, php_info, environment_variables
- **Plugin Specific**: field_mappings, result_mappings, dmn_credentials, operaton_token

#### Custom Sanitization
```php
// Add additional sensitive keys for specific logging
operaton_debug('API', 'Custom operation', $data, Operaton_DMN_Debug_Manager::DEBUG_LEVEL_STANDARD);

// Specialized methods with automatic sensitive key detection
operaton_debug_api('API', 'Request sent', $request_data); // Automatically sanitizes headers, auth, etc.
operaton_debug_database('Database', 'Query result', $db_data); // Automatically sanitizes queries, connections
operaton_debug_config('Config', 'Settings saved', $config_data); // Automatically sanitizes credentials, mappings
```

### WordPress Object Handling
Special sanitization for WordPress-specific objects:

```php
// WP_Error objects are safely sanitized
operaton_debug('Component', 'Error occurred', $wp_error_object);

// wpdb objects have credentials removed but maintain useful debug info
operaton_debug_database('Database', 'Connection status', $wpdb_object);
```

## JavaScript Integration

### Frontend Debug Bridge
The debug manager provides seamless JavaScript debugging through an AJAX bridge:

#### Automatic Configuration
```javascript
// Debug configuration automatically available in frontend
if (window.OperatonDebugConfig) {
    // Configuration includes:
    // - ajax_url: WordPress AJAX endpoint
    // - nonce: Security nonce for requests
    // - debug_level: Current debug level
    // - components: Available component identifiers
}
```

#### JavaScript Logging
```javascript
// Log from JavaScript to PHP debug system
jQuery.post(OperatonDebugConfig.ajax_url, {
    action: 'operaton_debug_log',
    nonce: OperatonDebugConfig.nonce,
    component: 'Frontend',
    message: 'Form validation started',
    data: JSON.stringify(formData),
    level: 2 // Standard level
});

// Buffered logging for performance
operaton_debug_js('Frontend', 'User interaction', interactionData);
```

## Configuration and Environment Detection

### WordPress Configuration Integration
```php
// wp-config.php settings automatically detected
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('OPERATON_DEBUG_LEVEL', 3); // Plugin-specific level override
```

### Environment-Aware Configuration
The debug manager automatically detects development environments and adjusts logging levels:

#### Development Environment Detection
- **localhost** domains
- **.local**, **.dev**, **.test** domains  
- **test.open-regels-lab.nl** (project-specific)
- **open-regels-lab** patterns

#### Automatic Level Adjustment
```php
// Development environments automatically get at least STANDARD level logging
// Production environments respect explicit configuration
// Staging environments can use custom OPERATON_DEBUG_LEVEL definition
```



## Performance Considerations

### Optimization Features
- **Early Exit**: Level checking prevents unnecessary processing
- **Cached Configuration**: Debug level determination cached for performance
- **Conditional Data Processing**: Data sanitization only when logging occurs
- **Buffered JavaScript Logs**: Frontend logs batched for efficiency

### Memory and Resource Management
- **Minimal Memory Footprint**: Singleton pattern reduces memory usage
- **Efficient Sanitization**: Smart pattern matching for sensitive data detection
- **Selective Processing**: Only processes data when logging level permits
- **Cache Management**: Intelligent cache invalidation strategies

## Log Output Examples

### Component-Based Organization
```
[26-Sep-2025 15:59:08 UTC] Operaton DMN Main: Performance monitor loaded successfully
[26-Sep-2025 15:59:08 UTC] Operaton DMN Main: Auto-updater loaded successfully
[26-Sep-2025 15:59:08 UTC] Operaton DMN Evaluator: Starting fresh initialization
[26-Sep-2025 15:59:08 UTC] Operaton DMN Evaluator: Assets manager loaded successfully
[26-Sep-2025 15:59:08 UTC] Operaton DMN Assets: WordPress hooks initialized
[26-Sep-2025 15:59:08 UTC] Operaton DMN Admin: Interface manager initialized
```

### Level-Specific Output
```
[26-Sep-2025 15:59:08 UTC] Operaton DMN Main [VERBOSE]: Debug file exists, loading...
[26-Sep-2025 15:59:08 UTC] Operaton DMN API [DIAG]: Complete diagnostic data
[26-Sep-2025 15:59:08 UTC] Operaton DMN Database [MIN]: Critical connection error
```

### Data Structure Logging
```
[26-Sep-2025 15:59:08 UTC] Operaton DMN Evaluator: Starting fresh initialization
[26-Sep-2025 15:59:08 UTC] Operaton DMN Evaluator: Data: {
    "version": "1.0.0-beta.17",
    "php_version": "8.1.0",
    "wp_version": "6.3.0"
}
```

### Sanitized Sensitive Data
```
[26-Sep-2025 15:59:08 UTC] Operaton DMN API: API configuration loaded
[26-Sep-2025 15:59:08 UTC] Operaton DMN API: Data: {
    "endpoint": "https://operaton.example.com/engine-rest",
    "api_key": "[SANITIZED]",
    "timeout": 30,
    "credentials": "[SANITIZED]"
}
```

## Asset Dependencies

The debug manager coordinates with the following systems and dependencies:

### WordPress Core
- **Error logging system**: WordPress `error_log()` function integration
- **Constants API**: WordPress constant definitions for configuration detection
- **AJAX system**: WordPress AJAX handling for JavaScript bridge
- **Security system**: WordPress nonce verification for secure logging
- **Admin system**: WordPress admin hooks for configuration output

### PHP Core Features
- **JSON processing**: PHP JSON encoding with comprehensive error handling
- **Error handling**: PHP exception handling and error reporting
- **Regular expressions**: Pattern matching for sensitive data detection
- **Static variables**: PHP static caching for performance optimization
- **Singleton pattern**: PHP design pattern implementation for global access

### Plugin Integration
- **Manager coordination**: Integration with all plugin manager instances
- **Configuration system**: Integration with plugin configuration management
- **Performance monitoring**: Coordination with performance tracking systems
- **Asset management**: Integration with JavaScript asset loading
- **Security validation**: Coordination with plugin security systems

All methods maintain strict security standards while providing comprehensive debugging capability, ensuring that sensitive information is never exposed while maintaining maximum utility for development and troubleshooting scenarios across all plugin components and both PHP and JavaScript contexts.