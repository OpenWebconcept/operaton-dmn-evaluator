# Operaton DMN Database Class - Documentation

## Overview

The `Operaton_DMN_Database` class provides comprehensive database management for the Operaton DMN WordPress plugin. It handles schema creation, configuration persistence, automatic migrations, and data integrity management. The class serves as the central data layer, managing all database interactions with proper validation, caching, and WordPress integration standards while ensuring reliable storage and retrieval of DMN configurations and process execution data.

## Class Properties

### Core Properties
- **`$wpdb`**: WordPress database instance reference for all database operations and prepared statements
- **`$table_name`**: WordPress prefixed table name for plugin configurations, constructed dynamically from global prefix
- **`$plugin_version`**: Current plugin version for database schema versioning and migration tracking
- **`$db_version_option`**: WordPress option key ('operaton_dmn_db_version') for tracking database schema version
- **`$current_db_version`**: Current database schema version (4) incremented when schema changes require migration

### Database Schema Information
The database table (`wp_operaton_dmn_configs`) contains the following columns:
- **Primary Key**: `id` (auto-increment integer)
- **Configuration**: `name`, `form_id`, `dmn_endpoint`, `decision_key`, `button_text`, `evaluation_step`
- **Mappings**: `field_mappings`, `result_mappings` (JSON-encoded longtext fields)
- **Process Execution**: `use_process`, `process_key`, `show_decision_flow` (boolean and text fields)
- **Management**: `active`, `created_at`, `updated_at` (status and timestamp fields)
- **Indexes**: Optimized indexes on `form_id`, `decision_key`, `process_key`, and `active` for performance

## Method Groupings

### Core Initialization & Setup

These methods handle the fundamental setup and WordPress integration of the database manager.

**`__construct($plugin_version = OPERATON_DMN_VERSION)`**  
Initializes the database manager with WordPress database connection and table references. Sets up plugin version tracking, constructs WordPress-prefixed table name, and establishes WordPress hooks through `init_hooks()`. Logs initialization status when debug mode is enabled for troubleshooting and monitoring.

**`init_hooks()`**  
Registers WordPress action hooks for database operations including automatic migration checking (`admin_init`), manual update triggers (`wp_ajax_operaton_manual_db_update`), and scheduled maintenance tasks (`operaton_dmn_cleanup`). Ensures proper hook priorities for reliable initialization sequence.

### Schema Management Methods

These methods handle database table creation, migrations, and schema updates with WordPress best practices.

**`create_database_tables()`**  
Creates the main configuration table with complete schema including all necessary columns for DMN and process execution. Uses WordPress dbDelta function for proper table creation, handles character set configuration, and manages both new installations and existing table updates through column addition logic.

**`add_missing_columns()`**  
Handles incremental schema updates during plugin upgrades by detecting and adding missing columns. Compares existing table structure with required schema, executes ALTER TABLE statements for missing columns, and manages database version updates. Includes performance index creation for query optimization.

**`add_missing_indexes()`**  
Creates performance optimization indexes on frequently queried columns including `decision_key`, `process_key`, and `active` status. Checks for existing indexes before creation to avoid duplicates and uses information schema queries for reliable index detection across different MySQL versions.

**`check_and_update_database()`**  
Automatic database migration system that compares installed database version with current requirements. Detects when migrations are needed, handles table creation for new installations, and orchestrates version-specific migration processes. Provides comprehensive logging for troubleshooting migration issues.

**`run_database_migration($from_version)`**  
Executes database migration between versions with comprehensive error handling. Manages version-specific migration steps including schema changes, data transformations, and index creation. Updates database version option upon successful completion and provides detailed logging for migration tracking.

**Migration Version Methods:**
- **`migrate_to_version_2()`**: Adds `result_mappings` and `evaluation_step` columns for enhanced configuration support
- **`migrate_to_version_3()`**: Adds process execution columns (`use_process`, `process_key`, `show_decision_flow`) for BPMN process integration
- **`migrate_to_version_4()`**: Adds management columns (`active`, `created_at`, `updated_at`) for health monitoring and audit trails

### Configuration CRUD Operations

These methods provide comprehensive Create, Read, Update, Delete operations for DMN configurations with validation and error handling.

**`get_all_configurations($args = array())`**  
Retrieves all DMN configurations from database with support for filtering, sorting, and pagination. Accepts parameters for orderby, order direction, limit, offset, and active-only filtering. Builds dynamic SQL queries with proper sanitization and returns configuration objects for admin interface display.

**`get_configuration($id, $use_cache = true)`**  
Gets specific DMN configuration for editing or processing with WordPress object cache support. Implements cache-first strategy with fallback to database queries, manages cache expiration (1 hour default), and provides debugging information for performance monitoring and troubleshooting.

**`get_config_by_form_id($form_id, $use_cache = true)`**  
Retrieves DMN configuration associated with specific Gravity Form using static caching for performance optimization during single request processing. Supports cache clearing commands, implements intelligent cache bypass options, and provides comprehensive logging for cache operations and database queries.

**`save_configuration($data)`**  
Creates or updates DMN configuration with comprehensive data validation, sanitization, and cache management. Orchestrates validation through `validate_configuration_data()`, sanitization through `sanitize_configuration_data()`, and delegates to creation or update methods based on configuration ID presence. Manages cache clearing and action hooks for external integrations.

**`create_configuration($config_data)`** and **`update_configuration($config_id, $config_data)`**  
Private methods handling actual database insertion and updates with duplicate checking and error handling. Implement prepared statements for SQL injection prevention, validate configuration existence for updates, and provide comprehensive error reporting through WordPress WP_Error objects.

**`delete_config($id)`**  
Removes DMN configuration with proper cleanup and validation including ID validation, existence checking, database deletion, cache clearing, and action hook triggers. Provides detailed error reporting and maintains data integrity through comprehensive validation steps.

### Data Validation and Sanitization

These methods ensure data integrity and security before database operations.

**`validate_configuration_data($data)`**  
Performs comprehensive validation of configuration data including required field checking, data type validation, URL validation, and JSON field validation. Implements business logic validation for decision vs. process execution modes and provides detailed error messages through WordPress WP_Error system for user feedback.

**`sanitize_configuration_data($data)`**  
Applies appropriate sanitization to all configuration fields before database storage including field mapping processing, result mapping processing, text field sanitization, URL sanitization, and boolean conversion. Ensures data security and consistency across all configuration fields and handles complex data structures like JSON mappings.

### Process Instance Management

These methods handle tracking of Operaton process executions for decision flow analysis.

**`store_process_instance_id($process_id, $expiration = 3600)`**  
Stores process instance ID in WordPress transients for tracking decision flow across multiple requests. Uses user-specific or session-specific keys for process tracking, implements configurable expiration times (default 1 hour), and provides fallback storage mechanisms for reliable process tracking.

**`get_process_instance_id()`**  
Retrieves stored process instance ID from WordPress transients for continued decision flow processing. Manages user-specific and session-specific key generation, provides proper sanitization of retrieved data, and includes comprehensive error handling for missing or expired process data.

### Cache Management Methods

These methods provide intelligent cache management for optimal performance and data freshness.

**`clear_configuration_cache($form_id = null)`**  
Clears configuration cache with coordinated cleanup across multiple cache layers including static cache clearing through special form ID commands, WordPress object cache clearing for specific forms or all data, and Gravity Forms localization cache coordination. Provides granular cache control for development and configuration updates.

**STEP 1 PART 2 Implementation:**  
Coordinates with Gravity Forms manager to clear JavaScript localization caches, ensuring configuration changes are immediately reflected in frontend JavaScript without requiring page refreshes. Manages cross-manager cache dependencies and provides comprehensive cache invalidation.

### Health and Maintenance Methods

These methods provide database health monitoring and automated maintenance capabilities.

**`check_database_health()`**  
Performs comprehensive database health check including table existence verification, column integrity checking, permission validation, and data consistency checks. Returns structured health information for admin dashboard reporting and automated monitoring systems.

**`cleanup_old_data()`**  
Scheduled maintenance cleanup including expired transient removal, orphaned data cleanup, and performance optimization tasks. Implements intelligent cleanup strategies to maintain database performance while preserving important historical data for decision flow analysis.

### AJAX Handlers

These methods handle WordPress AJAX requests for admin interface functionality.

**`ajax_manual_database_update()`**  
AJAX handler for manual database migration trigger providing administrators with on-demand database update capability. Implements proper security validation through nonce verification and capability checking, executes database updates, and provides user feedback through admin redirects with status messages.

### Public Accessor Methods

These methods provide external access to database manager information and functionality.

**`get_table_name()`**  
Returns the WordPress-prefixed table name for external components that need direct database access or integration with other plugins and systems.

**`get_current_db_version()` and `get_installed_db_version()`**  
Provide access to schema version information for migration compatibility checking, admin interface display, and debugging purposes. Enable external components to verify database schema compatibility and migration status.

## Integration Dependencies

The class manages integration with the following WordPress and plugin systems:

### WordPress Core Integration
- **Database API**: Uses `$wpdb` for all database operations with prepared statements for security
- **Actions**: `admin_init` (automatic migration), `wp_ajax_*` (AJAX handlers), custom cleanup actions
- **Options API**: Schema version tracking, configuration storage, and maintenance scheduling
- **Cache API**: WordPress object cache integration for performance optimization
- **Security**: Nonce verification, capability checking, and input sanitization throughout

### Plugin Manager Integration
- **Gravity Forms Manager**: Coordinated cache clearing, configuration retrieval, and JavaScript localization
- **Core Plugin**: Action hooks for configuration events, centralized instance management
- **Performance Monitor**: Database operation timing, query performance tracking, migration monitoring

### External Dependencies
- **MySQL/MariaDB**: Database schema management, index optimization, migration support
- **PHP**: JSON encoding/decoding, session management, transient storage
- **WordPress Transients**: Process instance storage, temporary data management, cache coordination

## Database Schema Evolution

### Version History
- **Version 1**: Initial schema with basic configuration fields
- **Version 2**: Added `result_mappings` and `evaluation_step` for enhanced form integration
- **Version 3**: Added process execution support (`use_process`, `process_key`, `show_decision_flow`)
- **Version 4**: Added management fields (`active`, `created_at`, `updated_at`) for health monitoring

### Migration Strategy
The class implements automatic, incremental migrations that detect current schema version and apply only necessary updates. Each migration is atomic and includes rollback capabilities for production safety. Migration logging provides detailed tracking for troubleshooting and audit purposes.

## Performance Characteristics

### Caching Strategy
- **Static Caching**: Configuration retrieval during single request processing
- **WordPress Object Cache**: Cross-request caching with configurable expiration
- **Cache Coordination**: Multi-manager cache invalidation for consistency

### Query Optimization
- **Prepared Statements**: All queries use prepared statements for security and performance
- **Strategic Indexes**: Performance indexes on frequently queried columns
- **Efficient Queries**: Optimized query patterns for configuration retrieval and management

### Memory Management
- **Selective Loading**: Configurations loaded only when needed
- **Cache Size Control**: Intelligent cache eviction and size management
- **Resource Cleanup**: Proper cleanup of database resources and temporary data