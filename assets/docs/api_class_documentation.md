# Operaton DMN API Class - Complete Documentation

## Overview

The `class-operaton-dmn-api.php` file is the core API handler class managing all DMN evaluation requests, REST API endpoints, AJAX communication, and integration with external Operaton decision engines. It handles both direct decision table evaluation and complete BPMN process execution workflows.

### Core Dependencies
- `$core` - Main plugin instance
- `$database` - Database manager for configuration
- External Operaton DMN/BPMN engines
- WordPress REST API system
- WordPress AJAX system
- Frontend JavaScript evaluation calls
- Admin interface configuration

## Method Inventory

### Core Initialization & WordPress Integration

#### `__construct($core, $database)`
**Purpose**: Constructor for API handler
**Visibility**: Public
**Parameters**:
- `$core` - Core plugin instance
- `$database` - Database manager instance
**Description**: Initializes API functionality with required dependencies and establishes WordPress hook integration. Sets up REST API routes, AJAX handlers, and connection timeout configuration.

#### `init_hooks()`
**Purpose**: Initialize WordPress hooks for API functionality
**Visibility**: Private
**Description**: Registers all necessary WordPress action hooks including REST API routes, AJAX handlers for admin interface communication, and decision flow endpoints. Establishes the complete integration layer between WordPress and the plugin.

#### `init_connection_timeout()`
**Purpose**: Initialize connection timeout from WordPress settings
**Visibility**: Private
**Description**: Retrieves and applies the saved API timeout configuration from WordPress options. Provides sensible defaults and validation to ensure stable API communication performance.

### REST API Endpoints & Registration

#### `register_rest_routes()`
**Purpose**: Register all REST API routes for DMN functionality
**Visibility**: Public
**Description**: Establishes the complete REST API interface including evaluation endpoints, health monitoring, testing endpoints, and decision flow visualization. All endpoints include proper permission callbacks and parameter validation.

#### `register_decision_flow_endpoint()`
**Purpose**: Register decision flow REST endpoint separately for modular loading
**Visibility**: Public
**Description**: Creates endpoint for decision flow summary retrieval and visualization. Provides process instance tracking and decision flow analysis for complex BPMN workflows with multiple decision points.

### Main Evaluation Methods - CRITICAL FUNCTIONALITY

#### `rest_evaluate($request)`
**Purpose**: Enhanced evaluation handler that routes to either process execution or direct decision evaluation
**Visibility**: Public
**Parameters**: `$request` - WP_REST_Request object
**Returns**: array|WP_Error
**Description**: Main REST API endpoint that determines evaluation method based on configuration settings. Routes requests to either complete BPMN process execution or direct DMN decision table evaluation based on the configuration's use_process setting. This method serves as the primary entry point for all DMN evaluations from frontend JavaScript.

#### `execute_process_evaluation($config, $form_data)`
**Purpose**: Execute complete BPMN process evaluation with decision tracking
**Visibility**: Public
**Parameters**:
- `$config` - DMN configuration object from database
- `$form_data` - Processed form data from Gravity Forms
**Returns**: array|WP_Error
**Description**: Handles full business process execution that may involve multiple DMN decisions. Provides comprehensive tracking of all decision instances throughout process execution and advanced variable extraction from completed process instances using history API.

#### `execute_decision_evaluation($config, $form_data)`
**Purpose**: Execute direct DMN decision evaluation without process wrapper
**Visibility**: Public
**Parameters**:
- `$config` - DMN configuration object from database
- `$form_data` - Processed form data from Gravity Forms
**Returns**: array|WP_Error
**Description**: Handles direct decision table evaluation for simple DMN scenarios that don't require complete BPMN process orchestration. Provides fast, efficient evaluation for single decision tables with immediate result processing.

### AJAX Handlers - Admin Interface Integration

#### `ajax_test_endpoint()`
**Purpose**: AJAX handler for basic endpoint connectivity testing
**Visibility**: Public
**Description**: Tests basic connectivity to a DMN endpoint URL without full configuration. Used by admin interface for initial endpoint validation during setup. Provides immediate feedback on network connectivity and basic URL structure.

#### `ajax_test_full_config()`
**Purpose**: AJAX handler for comprehensive configuration testing
**Visibility**: Public
**Description**: Tests complete DMN configuration including endpoint connectivity, decision key validation, field mappings, and result extraction. Provides detailed feedback for admin interface configuration validation.

#### `ajax_clear_update_cache()`
**Purpose**: AJAX handler for clearing WordPress update cache
**Visibility**: Public
**Description**: Removes cached update information to trigger fresh plugin update detection. Used by admin interface for forcing update checks and resolving update issues.

#### `ajax_test_configuration_complete()`
**Purpose**: AJAX handler for enhanced configuration testing with complete validation
**Visibility**: Public
**Description**: Performs comprehensive testing of DMN configuration including field mappings validation, result mappings verification, and end-to-end evaluation testing with sample data.

#### `handle_dmn_debug_ajax()`
**Purpose**: AJAX handler for DMN debug operations
**Visibility**: Public
**Description**: Handles debug-specific AJAX requests from admin interface debug panels. Provides comprehensive debugging information and diagnostic capabilities for troubleshooting DMN integration issues.

#### `run_operaton_dmn_debug()`
**Purpose**: Run Operaton DMN debug operations
**Visibility**: Public
**Description**: Executes comprehensive debug operations including system status checks, configuration validation, and connectivity testing. Provides detailed diagnostic information for troubleshooting and system monitoring.

### Decision Flow & Monitoring Endpoints

#### `rest_get_decision_flow($request)`
**Purpose**: REST endpoint handler for decision flow visualization data
**Visibility**: Public
**Parameters**: `$request` - WP_REST_Request containing form_id parameter
**Returns**: array|WP_Error
**Description**: Retrieves and formats decision flow information for a specific form, including process instance tracking, decision execution history, and comprehensive flow analysis for complex BPMN workflows.

#### `health_check($request)`
**Purpose**: System health check endpoint for monitoring and diagnostics
**Visibility**: Public
**Parameters**: `$request` - WP_REST_Request with optional detailed parameter
**Returns**: array
**Description**: Provides comprehensive system status information including database connectivity, external API accessibility, configuration validity, and performance metrics. Used by external monitoring systems and load balancers.

#### `get_decision_flow_summary($form_id)`
**Purpose**: Get decision flow summary for visualization
**Visibility**: Public
**Parameters**: `$form_id` - Gravity Forms form ID
**Returns**: array
**Description**: Retrieves comprehensive decision flow information including process instances, decision execution history, variable tracking, and timing analysis for complex business process workflows.

#### `get_decision_flow_summary_html($form_id)`
**Purpose**: Generate decision flow summary HTML for admin display
**Visibility**: Public
**Parameters**: `$form_id` - Gravity Forms form ID
**Returns**: string
**Description**: Creates formatted HTML representation of decision flow analysis including decision execution timeline, variable transformations, and process completion statistics for administrative review.

### Testing & Configuration Validation

#### `test_full_endpoint_configuration($base_endpoint, $decision_key)`
**Purpose**: Test complete DMN endpoint configuration with comprehensive validation
**Visibility**: Public
**Parameters**:
- `$base_endpoint` - Base DMN engine endpoint URL
- `$decision_key` - Decision definition key to test
**Returns**: array
**Description**: Performs end-to-end testing of DMN configuration including URL validation, connectivity testing, decision key verification, and sample evaluation. Provides detailed feedback for configuration troubleshooting.

#### `test_endpoint_connectivity($endpoint)`
**Purpose**: Test basic endpoint connectivity without evaluation
**Visibility**: Public
**Parameters**: `$endpoint` - Full endpoint URL to test
**Returns**: array
**Description**: Performs basic HTTP connectivity test to verify that the endpoint is reachable and responds to requests. Used for initial validation before attempting more complex configuration testing.

#### `test_configuration_complete($config)`
**Purpose**: Perform comprehensive configuration testing with detailed validation
**Visibility**: Public
**Parameters**: `$config` - Complete DMN configuration object
**Returns**: array
**Description**: Executes complete validation of a DMN configuration including field mappings, result mappings, endpoint connectivity, and sample evaluation with mock data. Provides detailed diagnostic information for troubleshooting.

#### `test_process_execution_complete($config, $test_data)`
**Purpose**: Test complete BPMN process execution workflow
**Visibility**: Private
**Parameters**:
- `$config` - DMN configuration object
- `$test_data` - Test data for process execution
**Returns**: array
**Description**: Comprehensive testing of BPMN process execution including process start, variable processing, completion detection, and result extraction validation.

#### `test_decision_evaluation_complete($config, $test_data)`
**Purpose**: Test complete DMN decision evaluation workflow
**Visibility**: Private
**Parameters**:
- `$config` - DMN configuration object
- `$test_data` - Test data for decision evaluation
**Returns**: array
**Description**: Comprehensive testing of direct DMN decision evaluation including variable processing, API communication, response validation, and result field verification.

### Data Processing & Transformation Utilities

#### `process_input_variables($field_mappings, $form_data)`
**Purpose**: Process input variables with type conversion and validation
**Visibility**: Private
**Parameters**:
- `$field_mappings` - Field mapping configuration
- `$form_data` - Raw form data
**Returns**: array|WP_Error
**Description**: Converts form data to properly typed variables for DMN evaluation. Handles type conversion, validation, and formatting according to DMN variable requirements and Operaton engine expectations.

#### `convert_form_value($value, $field_config)`
**Purpose**: Convert form values to appropriate DMN variable types
**Visibility**: Private
**Parameters**:
- `$value` - Raw form value
- `$field_config` - Field configuration information
**Returns**: mixed
**Description**: Transforms WordPress form data into properly typed DMN variables with appropriate type conversion and validation for Operaton engine.

#### `determine_variable_type($value)`
**Purpose**: Determine DMN variable type from value
**Visibility**: Private
**Parameters**: `$value` - Processed variable value
**Returns**: string
**Description**: Analyzes processed values to determine appropriate DMN variable type for proper serialization and engine processing.

#### `extract_process_results($config, $process_instance_id)`
**Purpose**: Extract results from process execution response
**Visibility**: Private
**Parameters**:
- `$config` - DMN configuration object
- `$process_instance_id` - Completed process instance ID
**Returns**: array
**Description**: Retrieves and processes results from completed BPMN process instances using comprehensive variable extraction strategies including active variables and process history analysis.

#### `extract_variable_from_process_data($field_name, $variables)`
**Purpose**: Extract specific variable from complex process variable data
**Visibility**: Private
**Parameters**:
- `$field_name` - Target variable name
- `$variables` - Complete process variables data
**Returns**: mixed
**Description**: Implements multiple search strategies to locate specific variables within complex process variable structures returned by Operaton engine.

#### `extract_decision_results($result_mappings, $data)`
**Purpose**: Extract results from direct decision evaluation response
**Visibility**: Private
**Parameters**:
- `$result_mappings` - Result mapping configuration
- `$data` - API response data
**Returns**: array
**Description**: Processes DMN decision table results based on configuration mapping. Handles various response formats and extracts configured result fields.

### URL Construction & Validation Helpers

#### `build_evaluation_endpoint($base_endpoint, $decision_key)`
**Purpose**: Build complete evaluation endpoint URL from base endpoint and decision key
**Visibility**: Public
**Parameters**:
- `$base_endpoint` - Base DMN engine endpoint URL
- `$decision_key` - Decision definition key
**Returns**: string
**Description**: Constructs proper Operaton DMN evaluation URL by combining base endpoint with decision key and evaluation path. Handles URL normalization and validation to ensure proper API communication.

#### `build_process_endpoint($base_endpoint, $process_key)`
**Purpose**: Build process execution endpoint URL for BPMN workflows
**Visibility**: Public
**Parameters**:
- `$base_endpoint` - Base engine endpoint URL
- `$process_key` - Process definition key
**Returns**: string
**Description**: Constructs proper Operaton process execution URL for BPMN process instances. Handles URL validation and process key encoding.

#### `get_engine_rest_base_url($endpoint)`
**Purpose**: Extract base engine REST URL from various endpoint formats
**Visibility**: Private
**Parameters**: `$endpoint` - Any Operaton endpoint URL
**Returns**: string
**Description**: Normalizes different endpoint URL formats to extract the base engine-rest URL for constructing additional API endpoints.

#### `normalize_engine_url($url)`
**Purpose**: Normalize engine URL format for consistency
**Visibility**: Private
**Parameters**: `$url` - Raw engine URL
**Returns**: string
**Description**: Standardizes various URL formats to ensure consistent API communication. Handles trailing slashes, protocol validation, and path normalization.

#### `is_valid_operaton_url($url)`
**Purpose**: Validate if URL appears to be a valid Operaton engine URL
**Visibility**: Private
**Parameters**: `$url` - URL to validate
**Returns**: bool
**Description**: Performs basic validation to check if a URL looks like a valid Operaton engine endpoint with proper structure and format.

#### `sanitize_endpoint_url($url)`
**Purpose**: Sanitize endpoint URL for security
**Visibility**: Private
**Parameters**: `$url` - URL to sanitize
**Returns**: string
**Description**: Applies security sanitization to endpoint URLs while preserving functionality. Prevents common URL-based security issues.

### API Communication & HTTP Utilities

#### `make_api_request($endpoint, $data = array(), $method = 'POST')`
**Purpose**: Make HTTP request to Operaton API with comprehensive error handling
**Visibility**: Private
**Parameters**:
- `$endpoint` - API endpoint URL
- `$data` - Request data
- `$method` - HTTP method (GET, POST, etc.)
**Returns**: array|WP_Error
**Description**: Enhanced HTTP request method with detailed logging, timeout management, response validation, and comprehensive error handling for reliable API communication with external Operaton engines.

#### `make_optimized_api_call($endpoint, $data, $method = 'POST')`
**Purpose**: Make optimized API call with enhanced performance and error handling
**Visibility**: Private
**Parameters**:
- `$endpoint` - API endpoint URL
- `$data` - Request data
- `$method` - HTTP method
**Returns**: array|WP_Error
**Description**: Optimized version of API request method with performance monitoring, connection pooling awareness, and enhanced error recovery strategies.

#### `get_api_headers()`
**Purpose**: Get standard API headers for Operaton requests
**Visibility**: Private
**Returns**: array
**Description**: Returns consistent headers for all API calls including content type, accept headers, and user agent identification for proper API communication.

#### `validate_expected_results($result_mappings, $api_response)`
**Purpose**: Validate expected results against API response
**Visibility**: Private
**Parameters**:
- `$result_mappings` - Expected result field mappings
- `$api_response` - Actual API response data
**Returns**: array
**Description**: Comprehensive validation of API response structure against expected result mappings. Provides detailed analysis for troubleshooting configuration and mapping issues.

#### `validate_result_fields_in_response($result_mappings, $api_response)`
**Purpose**: Validate result fields in actual API response
**Visibility**: Private
**Parameters**:
- `$result_mappings` - Expected result mappings
- `$api_response` - Actual API response
**Returns**: array
**Description**: Checks if expected result fields are present in the response with detailed field analysis and validation reporting.

### Configuration & Settings Management

#### `set_api_timeout($timeout)`
**Purpose**: Set API timeout for external requests
**Visibility**: Public
**Parameters**: `$timeout` - Timeout in seconds (5-60 seconds)
**Description**: Configures timeout settings for API requests with validation to ensure reasonable timeout values for stable API communication.

#### `set_ssl_verify($verify)`
**Purpose**: Set SSL verification setting
**Visibility**: Public
**Parameters**: `$verify` - Whether to verify SSL certificates
**Description**: Configures SSL certificate verification for API calls. Should be enabled in production for security but can be disabled for testing.

#### `get_api_status()`
**Purpose**: Get API configuration status
**Visibility**: Public
**Returns**: array
**Description**: Returns current API configuration settings and status information for debugging and administrative review.

#### `get_endpoint_examples()`
**Purpose**: Get configuration examples for admin interface help
**Visibility**: Public
**Returns**: array
**Description**: Provides sample configuration examples and templates to help users set up proper DMN configurations with correct URL formats.

### Debug & Monitoring Utilities

#### `get_debug_info()`
**Purpose**: Check if debug mode is enabled
**Visibility**: Private
**Returns**: bool
**Description**: Determines if debug information should be included in responses and whether detailed logging should be performed.

#### `get_system_debug_info()`
**Purpose**: Get system debug information for troubleshooting
**Visibility**: Private
**Returns**: array
**Description**: Collects comprehensive system information for debugging including PHP version, WordPress version, plugin status, and configuration.

#### `get_configurations_debug_info()`
**Purpose**: Get configurations debug information
**Visibility**: Private
**Returns**: array
**Description**: Retrieves summary information about all DMN configurations for debugging and system status monitoring.

#### `test_all_configurations_connectivity()`
**Purpose**: Test connectivity for all configurations
**Visibility**: Private
**Returns**: array
**Description**: Performs connectivity tests for all active DMN configurations and provides summary of results for system health monitoring.

#### `get_performance_debug_info()`
**Purpose**: Get performance debug information
**Visibility**: Private
**Returns**: array
**Description**: Collects performance-related debug information including memory usage, execution timing, and resource utilization metrics.

#### `check_database_health()`
**Purpose**: Check database health for system monitoring
**Visibility**: Private
**Returns**: array
**Description**: Verifies database connectivity and table structure for system health monitoring and diagnostic purposes.

#### `format_timestamp_for_display($iso_timestamp)`
**Purpose**: Format timestamp for user-friendly display
**Visibility**: Private
**Parameters**: `$iso_timestamp` - ISO format timestamp
**Returns**: string
**Description**: Converts ISO timestamps from Operaton engine to user-friendly display format with proper timezone handling.

#### `cleanup_debug_connections()`
**Purpose**: Cleanup debug connections and resources
**Visibility**: Public
**Returns**: int
**Description**: Cleans up any debug connections, temporary resources, or cached data to prevent memory leaks and resource exhaustion during testing.

## Integration Dependencies Map

### External Dependencies
- WordPress REST API System
- WordPress AJAX System
- Admin Interface (multiple AJAX calls)
- Frontend JavaScript (REST endpoints)
- Operaton DMN Engine APIs
- Database Manager Class
- Gravity Forms Integration
- Performance Monitoring System

### Internal Dependencies
- Core Plugin Instance
- Database Configuration Storage
- Asset Loading Coordination
- Debug/Logging Systems

## Method Usage Analysis

### Methods Called by Frontend JavaScript
- `rest_evaluate()` - Main evaluation (CRITICAL)
- `rest_get_decision_flow()` - Decision visualization

### Methods Called by Admin Interface
- `ajax_test_endpoint()` - Configuration testing
- `ajax_test_full_config()` - Complete validation
- `ajax_test_configuration_complete()` - Enhanced testing
- `handle_dmn_debug_ajax()` - Debug operations

### Methods Called by External Monitoring
- `health_check()` - REST health endpoint

### Methods Used in Complex Workflows
- `execute_process_evaluation()` - Multi-step process execution
- `execute_decision_evaluation()` - Decision table evaluation
- `process_input_variables()` - Data conversion pipeline

## Security Considerations

### Input Validation
- All AJAX handlers verify user permissions and nonces
- URL sanitization for endpoint configuration
- Form data validation and type conversion
- SQL injection prevention through prepared statements

### SSL/TLS Security
- Configurable SSL verification for API calls
- HTTPS enforcement recommendations
- Secure header management for API requests

## Performance Considerations

### Caching Strategies
- Decision flow data caching with configurable expiration
- Configuration result caching to minimize database queries
- Session-based process instance storage

### Resource Management
- Configurable API timeouts to prevent hanging requests
- Memory usage monitoring and cleanup
- Connection pooling awareness
- Performance metrics collection

## Error Handling & Recovery

### Graceful Degradation
- Fallback mechanisms for API failures
- Graceful handling of malformed responses
- User-friendly error messages
- Comprehensive logging for troubleshooting

### Recovery Mechanisms
- Automatic retry for transient failures
- Emergency recovery procedures
- Cache invalidation on errors
- Health check false positive elimination

This documentation provides a comprehensive overview of the API class structure, method purposes, and integration points to support safe maintenance and future development.
