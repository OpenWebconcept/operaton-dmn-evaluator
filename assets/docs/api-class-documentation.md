# Operaton DMN API Class - Restructured Documentation

## Overview

The `Operaton_DMN_API` class serves as the comprehensive API handler for the Operaton DMN WordPress plugin, managing all external API interactions including REST endpoints, DMN evaluation, process execution, endpoint testing, and enhanced debug logging capabilities. Through a trait-based architecture, the class orchestrates communication with Operaton decision engines, provides AJAX handlers for admin functionality, manages connection pooling for performance optimization, and handles decision flow visualization.

The class is composed of eight specialized traits that organize functionality into logical groups: Core initialization, REST endpoints, evaluation processing, AJAX handlers, decision flow management, testing and validation, utilities, and enhanced debug logging. This modular approach enables maintainable code organization while preserving all original functionality through exact method copying.

## Trait-Based Architecture

The API class utilizes the following traits loaded from separate files for modular organization:

```php
$trait_files = array(
    __DIR__ . '/api-traits/trait-api-core.php',
    __DIR__ . '/api-traits/trait-api-rest-endpoints.php',
    __DIR__ . '/api-traits/trait-api-evaluation.php',
    __DIR__ . '/api-traits/trait-api-ajax-handlers.php',
    __DIR__ . '/api-traits/trait-api-decision-flow.php',
    __DIR__ . '/api-traits/trait-api-testing.php',
    __DIR__ . '/api-traits/trait-api-utilities.php',
    __DIR__ . '/api-traits/trait-api-debug-enhanced.php',
);
```

- **`Operaton_DMN_API_Core`**: Constructor, properties, and initialization hooks
- **`Operaton_DMN_API_REST_Endpoints`**: REST API routes and health monitoring
- **`Operaton_DMN_API_Evaluation`**: DMN evaluation and process execution
- **`Operaton_DMN_API_AJAX_Handlers`**: AJAX endpoint handlers for admin functionality
- **`Operaton_DMN_API_Decision_Flow`**: Decision flow visualization and formatting
- **`Operaton_DMN_API_Testing`**: Testing, validation, and debug functionality
- **`Operaton_DMN_API_Utilities`**: Connection pooling, helpers, and utilities
- **`Operaton_DMN_API_Debug_Enhanced`**: Secure, level-controlled debug logging system

### Trait Loading System

The class implements dynamic trait loading with comprehensive error handling:

- **File Existence Validation**: Checks each trait file exists before loading
- **Require Once Loading**: Prevents duplicate trait loading with `require_once`
- **Error Logging**: WordPress error logging for missing trait files
- **Graceful Degradation**: Continues operation even if some traits are missing

## Class Properties

### Core Dependencies (from Core Trait)
- **`$core`**: Core plugin instance reference providing access to main plugin functionality and configuration
- **`$database`**: Database manager instance handling configuration retrieval and process tracking
- **`$api_timeout`**: API request timeout in seconds (default: 30) for external API calls
- **`$ssl_verify`**: SSL verification setting for API calls (should be true in production, false for development)

### Connection Pooling Properties (from Utilities Trait)
- **`$connection_pool`**: Static HTTP connection pool for reusing connections to the same host
- **`$pool_stats`**: Connection pool statistics for monitoring (hits, misses, created, cleaned)
- **`$connection_max_age`**: Maximum age for pooled connections in seconds (default: 300)
- **`$max_connections_per_host`**: Maximum number of connections per host (default: 3)

### Enhanced Debug Properties (from Debug Enhanced Trait)
- **`$debug_level_cache`**: Static cache for current debug level preventing repeated calculations
- **Debug level constants**: `DEBUG_LEVEL_NONE` (0) through `DEBUG_LEVEL_DIAGNOSTIC` (4)

## Method Groupings by Trait

### Core Initialization & Setup (Operaton_DMN_API_Core)

These methods handle fundamental setup and WordPress integration of the API functionality.

**`__construct($core, $database)`**  
Initializes API functionality with required dependencies including core plugin instance and database manager. Sets up connection timeout from saved settings, enables debug logging when WP_DEBUG is active, and establishes WordPress hooks through `init_hooks()` method.

**`init_hooks()`**  
Registers comprehensive WordPress action hooks including REST API routes (`rest_api_init`), AJAX handlers for endpoint testing and configuration management, enhanced configuration testing endpoints, API debug test handlers, and decision flow REST endpoint registration. Establishes proper integration points with WordPress core systems.

**`init_connection_timeout()`**  
Retrieves and applies saved timeout configuration from WordPress options, delegating to `set_connection_pool_timeout()` for implementation. Provides centralized timeout management across all API connections.

### REST API Management (Operaton_DMN_API_REST_Endpoints)

These methods provide REST API endpoints for DMN evaluation and system monitoring.

**`register_rest_routes()`**  
Creates public REST API endpoints including main evaluation endpoint (`/evaluate`) with parameter validation, test endpoint for debugging functionality, and health endpoint for monitoring and load testing. Includes comprehensive error logging and method existence verification for robust API registration.

**`register_decision_flow_endpoint()`**  
Establishes dedicated REST endpoint for decision flow summary retrieval with form ID parameter validation and proper sanitization callbacks.

**`health_check($request)`**  
Main health monitoring endpoint providing system status information including basic health metrics (status, timestamp, version, environment), optional detailed health information, response time measurement, and critical issue detection with appropriate HTTP status codes.

**`get_detailed_health_info()`, `check_critical_health()`**  
Support methods for health monitoring that analyze WordPress status, database connectivity, plugin dependencies, DMN configuration statistics, and recent evaluation activity. Provide comprehensive system diagnostics for monitoring and troubleshooting.

**`count_dmn_configurations()`, `count_active_configurations()`, `get_recent_evaluation_stats()`**  
Database query methods that return configuration counts and evaluation statistics for health monitoring dashboard integration.

### DMN Evaluation Processing (Operaton_DMN_API_Evaluation)

These methods handle the core DMN evaluation logic including both process execution and direct decision evaluation.

**`handle_evaluation($request)`**  
Main REST API endpoint handler that routes evaluation requests to appropriate processing methods. Validates required parameters (config_id, form_data), retrieves configuration from database, determines evaluation method based on configuration settings, and delegates to either process execution or decision evaluation with comprehensive error handling.

**`handle_process_execution($config, $form_data)`**  
Original process execution method that starts Operaton process instances, waits for completion, and extracts results from process variables. Handles field mapping validation, variable processing, process endpoint construction, API communication, and result extraction with debug logging and process instance ID storage.

**`handle_process_execution_optimized($config, $form_data)`**  
Enhanced process execution with intelligent batching and connection reuse. Prepares multiple API calls upfront for optimal performance, implements intelligent variable retrieval with primary/fallback strategies, utilizes optimized API call methods, and provides comprehensive timing and batching strategy reporting.

**`handle_decision_evaluation($config, $form_data)`**  
Direct decision evaluation using Operaton's decision engine endpoint. Processes form data for DMN evaluation, constructs evaluation endpoints, makes optimized API calls, and extracts decision results with comprehensive validation and error handling.

**`execute_variable_retrieval_batch($api_batch, $primary_endpoint, $process_instance_id)`**  
Advanced variable retrieval strategy with fallback mechanisms. Attempts primary strategy first (active or history variables), implements intelligent fallback when primary fails, includes process completion waiting logic, and provides detailed timing and success reporting.

**`process_variable_response($response, $endpoint_type)`**  
Processes API responses from variable retrieval endpoints with response validation, JSON parsing and error handling, format conversion between endpoint types (history vs active), and consistent variable structure normalization.

### AJAX Administration Handlers (Operaton_DMN_API_AJAX_Handlers)

These methods handle server-side processing of admin interface interactions and testing functionality.

**`ajax_test_endpoint()`**  
AJAX handler for basic DMN endpoint connectivity testing. Validates security nonces and user permissions, sanitizes endpoint URLs, delegates to connectivity testing methods, and returns structured JSON responses for admin interface integration.

**`ajax_test_full_config()`**  
Comprehensive endpoint configuration testing with DMN payload validation. Tests complete endpoint setup including decision key validation, response parsing verification, and detailed error reporting with actionable feedback.

**`ajax_clear_update_cache()`**  
WordPress update cache management for forcing fresh plugin update detection. Clears update transients, forces WordPress update checks, and provides admin interface feedback for update management.

**`run_operaton_dmn_debug()`, `handle_dmn_debug_ajax()`**  
Comprehensive debug testing functionality including server configuration analysis, plugin initialization verification, REST API availability testing, and structured debug result reporting with error logging integration.

**`ajax_test_configuration_complete()`**  
Enhanced configuration testing with realistic data simulation, comprehensive validation workflow, and detailed test result reporting including performance metrics and validation summaries.

### Decision Flow Visualization (Operaton_DMN_API_Decision_Flow)

These methods manage decision flow summary retrieval, formatting, and visualization for process execution results.

**`rest_get_decision_flow($request)`**  
REST endpoint wrapper for decision flow summary HTML generation with form ID parameter handling and structured JSON response formatting.

**`get_decision_flow_summary_html($form_id)`**  
Main decision flow generation method with caching and cache busting support. Checks configuration requirements, handles cache management with expiration policies, implements rate limiting for API calls, fetches decision flow data from Operaton history API, and formats comprehensive HTML output with error handling.

**`fetch_decision_flow_data($config, $process_instance_id)`**  
Retrieves decision instance history from Operaton API with proper endpoint construction, HTTP request handling, response validation, and JSON parsing with comprehensive error reporting.

**`format_decision_flow_summary($decision_instances, $process_instance_id)`**  
Creates formatted HTML display with Excel-style table layouts. Filters and processes decision instances, generates summary headers with statistics, creates main content tables, and applies comprehensive CSS styling for professional presentation.

**`filter_decision_instances($decision_instances)`, `generate_decision_flow_header($filtered_instances, $all_instances)`**  
Decision instance processing methods that apply filtering logic for relevant instances, create summary statistics, generate status information, and provide refresh controls for dynamic content updates.

**`generate_decision_flow_tables($filtered_instances)`, `generate_decision_table($instance)`**  
Table generation methods that create Excel-style decision tables with proper sectioning for inputs and outputs, variable name and value formatting, and comprehensive styling integration.

**`generate_table_section($items, $type, $header)`, `format_decision_value($item)`**  
Detailed formatting methods for decision data presentation including input/output section generation, value formatting with type-specific styling and icons, and comprehensive data type handling.

**`generate_decision_metadata($instance)`, `generate_decision_flow_styles()`**  
Support methods providing metadata display with timestamps and activity information, and comprehensive CSS styling for Excel-style tables with responsive design and print optimization.

**`get_decision_flow_placeholder()`, `get_decision_flow_loading_message()`, `format_decision_flow_error($error_message)`**  
State management methods for various decision flow display scenarios including informational placeholders, loading state messages, and error display formatting.

### Testing and Validation (Operaton_DMN_API_Testing)

These methods provide comprehensive testing functionality for endpoint validation and configuration verification.

**`test_full_endpoint_configuration($base_endpoint, $decision_key)`**  
Complete endpoint testing with minimal DMN payload validation. Constructs full evaluation endpoints, sends test data with optimized API calls, analyzes response codes and content, and provides detailed test results with actionable feedback.

**`test_endpoint_connectivity($endpoint)`**, **`analyze_test_response($http_code, $body, $endpoint)`**  
Basic connectivity testing using OPTIONS/HEAD requests and response code interpretation with helpful error messages and suggestions for common issues.

**`test_configuration_complete($config_id)`**  
Comprehensive configuration testing workflow including configuration validation, realistic test data generation, evaluation method determination, and detailed test result reporting with performance metrics and validation summaries.

**`validate_configuration_for_testing($config)`, `generate_test_data_from_mappings($config)`**  
Configuration validation methods that check required fields and JSON mapping validity, and generate realistic test data based on field mappings with context-aware value generation.

**`generate_test_value_for_type($type, $variable_name)`**  
Intelligent test value generation with contextual value creation based on variable names and types, realistic data ranges for better DMN rule matching, and comprehensive type support.

**`test_process_execution_complete($config, $test_data)`, `test_decision_evaluation_complete($config, $test_data)`**  
Mode-specific testing methods providing comprehensive validation workflows, step-by-step test execution, performance timing measurement, and detailed result analysis with suggestions for common issues.

**`validate_expected_result_fields($result_mappings, $mode)`, `validate_result_fields_in_response($result_mappings, $api_response)`**  
Result validation methods that check expected field configuration and validate actual API response content with field presence verification and comprehensive result structure analysis.

**`is_acceptable_response_code($test_result)`**  
Response code analysis helper determining acceptable HTTP codes for testing scenarios including method not allowed (405) interpretation.

**`test_server_config()`, `test_plugin_initialization()`, `test_rest_api_availability()`, `test_rest_api_call()`**  
Debug testing methods providing server configuration analysis, plugin initialization status checking, REST API availability verification, and actual endpoint testing with comprehensive logging.

### Utilities and Helpers (Operaton_DMN_API_Utilities)

These methods provide connection pooling, optimization, data processing, and configuration management functionality.

**`get_optimized_http_options($endpoint_url)`**, **`create_optimized_connection_options($host)`**  
HTTP connection optimization methods managing connection reuse with host-based connection keys, cached connection validation with age expiration, new connection creation with keep-alive optimization, and comprehensive cURL option configuration for performance.

**`get_connection_key($host)`, `has_valid_connection($connection_key)`, `get_cached_connection_options($connection_key)`**  
Connection pool management methods providing connection key generation, cache validity checking with age-based expiration, and cached connection retrieval with usage tracking.

**`cache_connection($connection_key, $options)`, `cleanup_old_connections()`**  
Connection caching methods that store connection options with metadata tracking, clean expired connections automatically, and maintain optimal pool size with comprehensive statistics tracking.

**`make_optimized_api_call($endpoint, $data, $method)`**  
Enhanced API call method with connection reuse utilizing optimized connection options, request-specific data handling, HTTP method flexibility, and comprehensive error handling integration.

**`set_connection_pool_timeout($timeout)`, `clear_connection_pool_for_batching()`, `get_connection_pool_stats()`**  
Connection pool management methods providing timeout configuration with bounds validation, manual cache management for testing, and comprehensive statistics reporting with WordPress persistence.

**`process_input_variables($field_mappings, $form_data)`, `convert_variable_type($value, $type, $variable_name)`**  
Data processing methods handling form data conversion to DMN variables with type conversion and validation, null value handling, and comprehensive error reporting.

**`build_evaluation_endpoint($base_endpoint, $decision_key)`, `build_process_endpoint($base_endpoint, $process_key)`**  
URL construction methods building complete evaluation URLs following Operaton REST API conventions with base URL normalization, endpoint path correction, and comprehensive debug logging.

**`get_process_variables($config, $process_instance_id, $process_ended)`**, **`get_historical_variables($base_url, $process_instance_id)`, `get_active_process_variables($base_url, $process_instance_id)`**  
Process variable retrieval methods handling completed and running process instances, historical variable retrieval from history API, active variable retrieval with fallback logic, and variable format normalization.

**`extract_process_results($config, $final_variables)`, `find_result_value($field_name, $variables)`, `extract_decision_results($result_mappings, $data)`**  
Result extraction methods processing complex variable structures with multiple search strategies, nested result object handling, boolean conversion logic, and comprehensive result mapping support.

**`parse_result_mappings($config)`, `get_engine_rest_base_url($endpoint_url)`, `format_evaluation_time($iso_timestamp)`**  
Configuration processing methods providing JSON configuration parsing, engine REST base URL extraction, and timestamp formatting with timezone handling.

**`get_api_headers()`, `get_debug_info()`, `set_api_timeout($timeout)`, `set_ssl_verify($verify)`**  
API configuration methods returning consistent headers for all requests, debug mode detection, timeout configuration with bounds validation, and SSL verification setting management.

**`get_api_status()`, `validate_configuration()`, `get_core_instance()`, `get_database_instance()`**  
Status and access methods providing current API configuration reporting, configuration validation with issue detection, and external access to manager instances for integration purposes.

### Enhanced Debug Logging System (Operaton_DMN_API_Debug_Enhanced)

These methods provide secure, level-controlled debug logging with automatic sensitive data sanitization.

**`sanitize_debug_output($data, $additional_sensitive_keys = array())`**  
Core sanitization method that removes or masks sensitive information from debug output. Handles nested arrays and objects recursively, applies default sensitive key patterns, supports additional custom sensitive keys, and maintains data structure while protecting sensitive values.

**`get_debug_level()`, `is_development_environment()`**  
Debug level determination and environment detection methods with intelligent caching. Checks WordPress constants and environment variables, implements performance optimization through caching, and provides reliable environment classification for development vs production.

**`should_log($required_level = null)`, `debug_log($message, $data = null, $level = null, $additional_sensitive_keys = array())`**  
Core logging functionality with level control and data sanitization. Validates logging level requirements, applies appropriate debug prefixes, sanitizes sensitive data automatically, formats complex data as JSON, and logs to WordPress error log.

**`log_minimal($message, $data = null)`, `log_standard($message, $data = null)`, `log_verbose($message, $data = null)`, `log_diagnostic($message, $data = null)`**  
Convenience logging methods for different debug levels. Provides easy-to-use interfaces for various debugging scenarios with automatic level assignment and consistent formatting.

**`log_database($message, $data = null)`, `log_api($message, $data = null)`, `log_config($message, $data = null)`, `log_performance($message, $metrics = null)`**  
Operation-specific logging methods with automatic sensitive data handling. Sanitizes operation-specific sensitive information, captures relevant operational context, maintains appropriate logging levels, and protects credentials and sensitive configuration.

**`log_emergency($message, $data = null)`**  
Critical error logging that bypasses all level restrictions. Always logs regardless of debug level setting, used only for critical system errors, maintains special emergency prefix, and ensures critical issues are captured.

**`get_debug_config()`, `get_debug_level_name($level)`, `clear_debug_level_cache()`**  
Debug utility methods for configuration reporting and cache management. Provides comprehensive debug configuration information, converts numeric levels to human-readable names, and supports dynamic configuration changes.

## WordPress Integration Points

### REST API Endpoints
- **`/wp-json/operaton-dmn/v1/evaluate`**: Main DMN evaluation endpoint with parameter validation
- **`/wp-json/operaton-dmn/v1/test`**: Debug testing endpoint for API verification
- **`/wp-json/operaton-dmn/v1/health`**: System health monitoring with detailed diagnostics
- **`/wp-json/operaton-dmn/v1/decision-flow/{form_id}`**: Decision flow summary retrieval

### AJAX Handlers
- **`wp_ajax_operaton_test_endpoint`**: Basic endpoint connectivity testing
- **`wp_ajax_operaton_test_full_config`**: Comprehensive configuration testing
- **`wp_ajax_operaton_clear_update_cache`**: Update cache management
- **`wp_ajax_operaton_test_configuration_complete`**: Enhanced configuration testing
- **`wp_ajax_operaton_dmn_debug`**: Comprehensive debug testing

### WordPress Hooks
- **`rest_api_init`**: REST API route registration and endpoint setup
- **`init`**: Basic initialization and availability checking
- **`admin_init`**: Admin interface integration and asset loading

### Enhanced Debug Configuration
- **`WP_DEBUG`**: Must be true for any debug logging functionality
- **`WP_DEBUG_LOG`**: Enables WordPress error logging to file
- **`OPERATON_DEBUG_LEVEL`**: Sets debug level (0-4) for granular control

## Performance Optimization Features

### Connection Pooling
- **HTTP/1.1 keep-alive connections**: Reuse connections to reduce overhead
- **Intelligent connection caching**: Host-based connection keys with age-based expiration
- **Connection pool statistics**: Comprehensive monitoring with WordPress persistence
- **Automatic cleanup**: Age and idle time-based connection management

### Caching and Optimization
- **Configuration caching**: Database query reduction through intelligent caching
- **API response caching**: Transient-based caching for decision flow data
- **Rate limiting**: API call throttling to prevent abuse
- **Optimized HTTP options**: cURL optimization for performance
- **Debug level caching**: Static cache prevents repeated level calculations

### Batching and Intelligence
- **Intelligent batching**: Multiple API calls prepared upfront for optimal performance
- **Primary/fallback strategies**: Intelligent variable retrieval with automatic fallback
- **Process completion detection**: Smart handling of immediate vs delayed process completion
- **Performance timing**: Comprehensive timing collection and reporting

## Security Features

### Request Validation
- **Nonce verification**: WordPress nonce security for all AJAX requests
- **Permission checking**: User capability verification for admin functions
- **Input sanitization**: Comprehensive input validation and sanitization
- **Parameter validation**: REST API parameter validation with type checking

### Enhanced Debug Security
- **Automatic data sanitization**: Prevents credential exposure in debug logs
- **Environment-based logging**: Reduced logging in production environments
- **Configurable sensitive keys**: Custom sensitive data pattern support
- **Safe error messages**: No sensitive information in error responses

### SSL and Communication
- **SSL verification**: Configurable SSL certificate verification for API calls
- **Secure headers**: Consistent security headers for all API requests
- **Error handling**: Secure error messages without sensitive information exposure
- **Debug information**: Conditional debug output only when WP_DEBUG enabled

## Error Handling and Logging

### Comprehensive Error Management
- **Trait loading resilience**: Continues operation with missing trait files
- **WP_Error integration**: WordPress standard error handling throughout
- **HTTP status codes**: Appropriate status codes for different error conditions
- **Fallback mechanisms**: Multiple fallback strategies for robust operation
- **Graceful degradation**: Functional operation even when some features unavailable

### Enhanced Debug and Monitoring
- **Multi-level debug logging**: Five levels from None to Diagnostic
- **Automatic sensitive data protection**: Prevents credential exposure
- **Performance metrics**: Timing and resource usage monitoring
- **Health monitoring**: Comprehensive system health reporting
- **Configuration validation**: Detailed configuration checking and reporting

## Asset Dependencies

The API class coordinates with the following external systems and dependencies:

### WordPress Core
- **REST API framework**: WordPress REST API for endpoint registration
- **AJAX system**: WordPress AJAX handling for admin interface
- **Transient API**: WordPress caching system for performance optimization
- **Options API**: WordPress settings storage for configuration persistence
- **Error logging system**: WordPress error logging integration for debug output

### External API Communication
- **Operaton REST API**: Communication with Operaton decision engine
- **HTTP/1.1 with keep-alive**: Optimized connection management
- **cURL optimization**: Advanced connection settings for performance
- **JSON processing**: Comprehensive JSON encoding/decoding with error handling

### Plugin Integration
- **Core plugin**: Integration with main plugin instance
- **Database manager**: Configuration and data persistence
- **Assets manager**: Coordinated asset loading and script management
- **Gravity Forms**: Form integration and evaluation coordination

### Trait File Dependencies
- **File system access**: Trait files must exist and be readable
- **PHP include system**: Proper trait loading and inclusion
- **Error logging**: Missing trait file error reporting
- **Graceful degradation**: Operation continues with partial trait loading

All methods maintain exact functionality from the original implementation while providing improved organization through trait-based architecture, enhanced debug logging capabilities, and clear separation of concerns for maintainable code structure.