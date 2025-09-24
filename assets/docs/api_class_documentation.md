# Operaton DMN API Class - Documentation

## Overview

The `Operaton_DMN_API` class serves as the primary communication layer between WordPress/Gravity Forms and external Operaton DMN engines. It manages all external API communications, processes REST API endpoints, handles AJAX requests, and coordinates complex decision management workflows including both direct decision evaluation and full BPMN process execution.

## Class Purpose

This class provides comprehensive integration between WordPress and Operaton DMN engines, supporting:

- **Direct Decision Evaluation**: Simple DMN decision table evaluation
- **BPMN Process Execution**: Full business process orchestration with decision flow tracking
- **REST API Management**: WordPress REST endpoints for DMN functionality
- **AJAX Administration**: Admin interface integration and configuration testing
- **Decision Flow Analysis**: Comprehensive tracking and visualization of decision processes

## Key Dependencies

### Core Dependencies
- **`OperatonDMNEvaluator`**: Main plugin instance for core functionality access
- **`Operaton_DMN_Database`**: Database operations and configuration management
- **WordPress REST API**: For endpoint registration and request handling
- **WordPress HTTP API**: For external API communications

### WordPress Integration
- **Actions**: `rest_api_init` (REST routes), multiple AJAX handlers
- **Functions**: `wp_remote_*` for HTTP requests, `wp_send_json_*` for AJAX responses
- **Security**: WordPress nonces and capability checking for admin functions

## Method Inventory

### Public API Methods (External Interface)

#### Core Initialization
- **`__construct($core, $database)`** - Initialize API handler with dependencies
- **`set_api_timeout($timeout)`** - Configure API timeout (5-60 seconds)
- **`set_ssl_verify($verify)`** - Control SSL certificate validation

#### REST API Endpoints
- **`handle_evaluation(WP_REST_Request $request)`** - Main evaluation endpoint handler
- **`rest_get_decision_flow(WP_REST_Request $request)`** - Decision flow data retrieval
- **`health_check(WP_REST_Request $request)`** - System health monitoring endpoint

#### Testing & Configuration
- **`test_full_endpoint_configuration($base_endpoint, $decision_key)`** - Complete endpoint testing
- **`build_evaluation_endpoint($base_endpoint, $decision_key)`** - URL construction helper
- **`build_process_endpoint($base_endpoint, $process_key)`** - Process URL construction
- **`get_endpoint_examples()`** - Configuration examples for admin interface

#### Utility & Access Methods
- **`get_core_instance()`** - Access to core plugin instance
- **`get_database_instance()`** - Access to database manager
- **`get_api_status()`** - Current API configuration status
- **`get_decision_flow_summary_html($form_id)`** - HTML decision flow representation
- **`cleanup_temporary_data()`** - Maintenance and cleanup operations
- **`clear_idle_connections()`** - Connection pool management

### Private Helper Methods (Internal Operations)

#### WordPress Integration
- **`init_hooks()`** - Register WordPress hooks and actions
- **`init_connection_timeout()`** - Load timeout configuration from database
- **`register_rest_routes()`** - Register all REST API endpoints
- **`register_decision_flow_endpoint()`** - Register decision flow endpoint

#### Evaluation Processing
- **`handle_direct_decision_evaluation($config, $form_data)`** - Direct DMN evaluation
- **`handle_process_evaluation($config, $form_data)`** - BPMN process execution
- **`process_input_variables($field_mappings, $form_data)`** - Form data processing
- **`extract_decision_results($result_mappings, $data)`** - Decision result extraction
- **`extract_process_results($result_mappings, $variables)`** - Process result extraction

#### Data Processing & Transformation
- **`convert_value_type($value, $field_config)`** - Type conversion for DMN variables
- **`get_dmn_type($field_config)`** - DMN type identifier mapping
- **`find_variable_value($variables, $field_name)`** - Complex data structure searching
- **`get_process_variables_active($base_url, $process_instance_id)`** - Active process variables
- **`get_process_variables_history($base_url, $process_instance_id)`** - Historical process variables

#### API Communication
- **`make_optimized_api_call($endpoint, $data, $method)`** - Enhanced HTTP requests
- **`get_api_headers()`** - Standard API headers
- **`validate_api_response_structure($api_response, $result_mappings)`** - Response validation
- **`is_acceptable_response_code($test_result)`** - Response code analysis

#### URL & Endpoint Management
- **`get_engine_rest_base_url($endpoint_url)`** - Base URL extraction and normalization
- **`sanitize_api_endpoint($url)`** - URL sanitization and validation

#### Testing & Validation
- **`test_configuration_complete($config_id)`** - Comprehensive configuration testing
- **`test_process_endpoint_connectivity($base_endpoint, $process_key)`** - Process endpoint testing
- **`analyze_test_response($http_code, $body, $endpoint)`** - Response analysis
- **`test_server_config()`** - Server environment validation
- **`test_plugin_initialization()`** - Plugin component validation
- **`test_rest_api_availability()`** - REST API infrastructure testing
- **`test_rest_api_call()`** - Actual REST API functionality testing

#### Decision Flow & Monitoring
- **`get_decision_flow_for_process($base_url, $process_instance_id)`** - Process decision tracking
- **`get_active_config_count()`** - Configuration statistics
- **`get_recent_evaluation_stats()`** - Usage and performance metrics

#### Debug & Utilities
- **`get_debug_info()`** - Debug mode detection
- **`format_timestamp($iso_timestamp)`** - Timestamp formatting
- **`validate_json_string($json_string)`** - JSON validation with error handling
- **`generate_request_id()`** - Unique request identification
- **`log_api_activity($activity, $context, $level)`** - Structured activity logging

### AJAX Handler Methods (Admin Interface)

#### Configuration Testing
- **`ajax_test_endpoint()`** - Basic endpoint connectivity testing
- **`ajax_test_full_config()`** - Complete configuration validation
- **`ajax_test_configuration_complete()`** - Enhanced configuration testing

#### System Management
- **`ajax_clear_update_cache()`** - WordPress update cache management
- **`handle_dmn_debug_ajax()`** - Debug test coordination
- **`run_operaton_dmn_debug()`** - Comprehensive system debugging

## Integration Points

### WordPress Core Integration
- **REST API Framework**: Registers custom endpoints under `operaton-dmn/v1` namespace
- **AJAX System**: Provides admin interface functionality with proper nonce validation
- **HTTP API**: Uses WordPress HTTP functions for external API communication
- **Options API**: Manages configuration settings and caching
- **Security**: Implements capability checks and nonce validation

### Plugin Component Integration
- **Database Manager**: Retrieves and validates configuration data
- **Core Plugin**: Accesses shared functionality and performance monitoring
- **Gravity Forms**: Processes form data and field mappings
- **Assets Manager**: Coordinates with asset loading for decision flow visualization

### External System Integration
- **Operaton DMN Engines**: Direct communication with decision evaluation endpoints
- **BPMN Process Engines**: Process execution and variable retrieval
- **Decision Flow APIs**: Historical decision tracking and analysis
- **Health Monitoring**: System status and performance metrics

## Configuration Options

### API Communication Settings
- **`api_timeout`**: Request timeout (5-60 seconds, default: 30)
- **`ssl_verify`**: SSL certificate validation (boolean, default: true)

### Endpoint Configuration
- **Base Endpoint URL**: Root DMN engine URL (e.g., `https://your-server.com/engine-rest/decision-definition/key/`)
- **Decision Key**: Specific decision identifier for direct evaluation
- **Process Key**: BPMN process identifier for process execution mode
- **Field Mappings**: JSON mapping between form fields and DMN variables
- **Result Mappings**: JSON mapping for extracting results back to form fields

### Evaluation Modes
- **Direct Decision Mode**: Single DMN decision table evaluation
- **Process Execution Mode**: Full BPMN process with multiple decisions
- **Mixed Mode**: Configuration-dependent automatic selection

### Debug and Monitoring
- **Debug Logging**: Controlled by `WP_DEBUG` WordPress constant
- **Health Monitoring**: Automatic system health tracking and reporting
- **Performance Metrics**: Request timing and success rate tracking
- **Error Logging**: Comprehensive error logging with context information

## Usage Examples

### Basic Configuration Testing
```php
// Test endpoint connectivity
$api_manager = $core_plugin->get_api_instance();
$result = $api_manager->test_full_endpoint_configuration(
    'https://your-server.com/engine-rest/decision-definition/key/',
    'your-decision-key'
);

if ($result['success']) {
    echo "Endpoint is working: " . $result['message'];
} else {
    echo "Configuration error: " . $result['message'];
}
```

### REST API Evaluation
```php
// Direct evaluation via REST API
$request_data = array(
    'config_id' => 1,
    'form_data' => array(
        'customer_age' => 25,
        'income_level' => 'high',
        'credit_score' => 750
    )
);

$response = wp_remote_post(
    rest_url('operaton-dmn/v1/evaluate'),
    array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => wp_json_encode($request_data)
    )
);
```

### Health Check Monitoring
```php
// Check system health
$health_response = wp_remote_get(
    rest_url('operaton-dmn/v1/health?detailed=true')
);

$health_data = json_decode(wp_remote_retrieve_body($health_response), true);
echo "System Status: " . $health_data['status'];
echo "Active Configurations: " . $health_data['detailed_info']['active_configurations'];
```

### Decision Flow Analysis
```php
// Get decision flow for form
$decision_flow_html = $api_manager->get_decision_flow_summary_html($form_id);
echo $decision_flow_html; // Displays configuration summary
```

## Error Handling

### HTTP Error Management
- **Connection Failures**: Comprehensive `WP_Error` handling with detailed messages
- **Timeout Handling**: Configurable timeouts with fallback strategies
- **SSL Issues**: Configurable SSL verification with debugging support
- **Authentication**: Proper error reporting for authentication failures

### API Response Validation
- **JSON Parsing**: Safe JSON decoding with error detection
- **Structure Validation**: Expected response format verification
- **Data Type Checking**: Type validation for DMN variables
- **Missing Field Detection**: Comprehensive field mapping validation

### WordPress Integration Errors
- **Nonce Validation**: Security check failures with appropriate responses
- **Permission Checking**: Capability verification for admin functions
- **Database Errors**: Configuration retrieval error handling
- **REST API Issues**: Endpoint registration and routing error management

## Performance Considerations

### Optimization Features
- **Connection Reuse**: Optimized HTTP connection handling
- **Response Caching**: Strategic caching of configuration data
- **Request Batching**: Efficient API call strategies for process execution
- **Timeout Management**: Configurable timeouts for different operation types

### Monitoring and Metrics
- **Response Time Tracking**: Built-in performance measurement
- **Success Rate Monitoring**: Evaluation success statistics
- **Error Rate Analysis**: Failure pattern identification
- **Resource Usage**: Memory and connection usage tracking

### Scalability Support
- **Connection Pooling**: Idle connection cleanup
- **Cache Management**: Temporary data cleanup routines
- **Load Balancing Ready**: Multiple endpoint support architecture
- **High Availability**: Robust error handling and recovery mechanisms

## Security Features

### WordPress Security Integration
- **Nonce Validation**: All AJAX requests use WordPress nonces
- **Capability Checks**: Admin functions require `manage_options` capability
- **Input Sanitization**: All user inputs sanitized using WordPress functions
- **URL Validation**: Endpoint URLs validated and sanitized

### API Security
- **SSL/TLS Support**: Configurable SSL certificate verification
- **Request Headers**: Standardized headers with version identification
- **Data Validation**: Comprehensive input and output validation
- **Error Information**: Controlled error message disclosure

### Data Protection
- **Sensitive Data Handling**: Secure handling of form data and results
- **Logging Controls**: Debug information only in development mode
- **Connection Security**: Secure HTTP communication protocols
- **Configuration Protection**: Database-stored configuration security

## Troubleshooting Guide

### Common Configuration Issues
1. **Endpoint Not Reachable**
   - Check base URL format and accessibility
   - Verify SSL certificate if using HTTPS
   - Test network connectivity from WordPress server

2. **Invalid Decision Key**
   - Verify decision key exists in DMN engine
   - Check case sensitivity and special characters
   - Use testing interface to validate configuration

3. **Field Mapping Errors**
   - Validate JSON format in field mappings
   - Check form field IDs match Gravity Forms configuration
   - Verify DMN variable names match decision table inputs

4. **Process Execution Issues**
   - Confirm process key exists and is deployable
   - Check process variable requirements
   - Verify process completion and variable extraction

### Debug Tools
- **Health Check Endpoint**: `GET /wp-json/operaton-dmn/v1/health?detailed=true`
- **Test Endpoint**: `GET /wp-json/operaton-dmn/v1/test`
- **Admin Debug Interface**: Available in WordPress admin under Operaton DMN settings
- **WordPress Debug Logging**: Enable `WP_DEBUG` for detailed logging

### Performance Optimization
1. **Timeout Tuning**: Adjust API timeouts based on network conditions
2. **Cache Management**: Regular cleanup of temporary data
3. **Connection Optimization**: Monitor and optimize HTTP connections
4. **Error Rate Monitoring**: Track and address recurring failures

## Version Compatibility

### WordPress Requirements
- **Minimum WordPress Version**: 5.0+
- **PHP Requirements**: 7.4+ (recommended 8.0+)
- **Required Extensions**: cURL, OpenSSL, JSON
- **Optional Extensions**: APCu for enhanced caching

### External System Compatibility
- **Operaton DMN**: All versions with REST API support
- **Camunda Platform**: 7.x and 8.x series
- **Generic DMN Engines**: Any engine with REST API following DMN standards

### Gravity Forms Integration
- **Minimum Gravity Forms**: 2.4+
- **Form Field Support**: All standard field types
- **Advanced Fields**: File uploads, multi-select, conditional logic
- **Custom Fields**: Extensible field mapping system

## Future Enhancements

### Planned Features
- **Bulk Evaluation**: Multiple form submissions in single API call
- **Async Processing**: Background evaluation for complex processes
- **Advanced Caching**: Intelligent result caching strategies
- **Multi-Engine Support**: Load balancing across multiple DMN engines

### Integration Roadmap
- **Additional Form Plugins**: Contact Form 7, WPForms support
- **Webhook Integration**: Real-time decision notifications
- **Analytics Dashboard**: Advanced reporting and visualization
- **API Rate Limiting**: Built-in rate limiting and throttling

---

## Summary

The `Operaton_DMN_API` class provides a comprehensive, secure, and performant interface between WordPress and external DMN engines. Its modular architecture, extensive error handling, and rich debugging capabilities make it suitable for both development and production environments. The class supports both simple decision evaluation and complex business process orchestration while maintaining WordPress security and performance standards.