# Operaton DMN Gravity Forms Class - Documentation

## Overview

The `Operaton_DMN_Gravity_Forms` class provides comprehensive integration between Gravity Forms and the DMN evaluation system. It handles form detection, button placement, asset loading coordination, configuration localization, and evaluation orchestration. The class serves as the primary bridge between WordPress Gravity Forms functionality and Operaton DMN decision evaluation capabilities, managing both frontend user interactions and admin configuration interfaces.

## Class Properties

### Core Properties
- **`$core`**: Core plugin instance reference providing access to centralized plugin functionality
- **`$assets`**: Assets manager instance reference for coordinated asset loading and script management
- **`$database`**: Database manager instance reference for configuration storage and retrieval
- **`$performance`**: Optional performance monitor instance for timing and optimization tracking

### State Management Properties
- **`$gravity_forms_available`**: Cached result of Gravity Forms availability check to prevent repeated class existence verification

### Static Caching Properties
- **`$form_config_cache`**: Stores loaded DMN configurations to reduce database queries and improve performance
- **`$form_fields_cache`**: Caches Gravity Forms field definitions to minimize API calls and enhance responsiveness
- **`$localized_form_configs`**: Tracks JavaScript configuration output to prevent duplicate script localization
- **`$localization_timestamps`**: Prevents rapid-fire localization attempts with timing-based duplicate prevention

### Class Constants
- **`MIN_GF_VERSION`**: Minimum supported Gravity Forms version (2.0) for compatibility validation
- **`CACHE_DURATION`**: Cache lifetime in seconds (300 = 5 minutes) for configuration and field caching

## Method Groupings

### Core Initialization & Setup

These methods handle the fundamental setup and WordPress integration of the Gravity Forms manager.

**`__construct($core, $assets, $database)`**  
Initializes the Gravity Forms integration manager with required dependencies. Accepts core plugin instance, assets manager, and database manager references. Sets up performance monitoring if available and establishes WordPress hooks through the `init_hooks()` method. Logs initialization status when debug mode is enabled.

**`init_hooks()`**  
Registers WordPress action hooks for Gravity Forms integration including availability checking (`init`), conditional initialization (`init`), asset loading (`wp_enqueue_scripts`), and admin integration (`admin_init`). Establishes proper hook priorities to ensure correct initialization order and prevent conflicts with other plugins.

**`check_gravity_forms_availability()`**  
Public method that determines if Gravity Forms is available and compatible. Uses caching to prevent repeated checks and validates both class existence (`GFForms`, `GFAPI`) and version compatibility. Results are cached in the `$gravity_forms_available` property for subsequent calls.

**`conditional_init_gravity_forms()`**  
Initializes Gravity Forms-specific hooks only when GF is available. Sets up form rendering hooks (`gform_submit_button`, `gform_enqueue_scripts`), validation hooks (`gform_validation`), submission hooks (`gform_after_submission`), and admin interface hooks (`gform_editor_js`). Includes radio synchronization setup and comprehensive debug logging.

### Public API Methods

These methods provide the external interface for Gravity Forms integration and are called by other plugin components.

**`is_gravity_forms_available()`**  
Public wrapper for availability checking that delegates to the `check_gravity_forms_availability()` method. Provides clean API for external components to verify Gravity Forms status.

**`get_available_forms()`**  
Returns array of all Gravity Forms with enhanced field information. Each form includes field list populated through `get_form_fields()` method. Handles exceptions gracefully and returns empty array when Gravity Forms is unavailable.

**`get_form_configuration($form_id)`**  
Public API wrapper for retrieving DMN configuration for a specific form. Delegates to internal `get_form_config()` method which implements caching for performance optimization.

**`get_form_fields($form_id)`**  
Returns comprehensive field definitions for a Gravity Forms form including field types, labels, choices, and metadata. Implements intelligent caching to minimize Gravity Forms API calls. Handles special field types like select, radio, and checkbox with choice enumeration.

**`form_exists($form_id)`**  
Validates existence of a specific Gravity Forms form using GFAPI. Includes exception handling for robust error management and returns false for any failures.

**`get_form_title($form_id)`**  
Retrieves the display title for a specific Gravity Forms form with error handling. Returns empty string when form is not found or Gravity Forms is unavailable.

**`get_integration_status()`**  
Provides comprehensive status information including Gravity Forms availability, form counts, DMN configuration statistics, version compatibility information, and cache status metrics. Used for admin dashboard reporting and troubleshooting.

### Form Button and UI Integration

These methods handle the core user interface integration including button placement and form enhancement.

**`add_evaluate_button($button, $form)`**  
WordPress filter callback that adds DMN evaluation button to forms with configurations. Constructs button HTML with proper form ID, configuration ID, and button text. Includes decision flow container when enabled. Skips button addition in admin or AJAX contexts to prevent interference.

**`ensure_assets_loaded($form)`**  
Gravity Forms hook callback (`gform_pre_render`) that ensures necessary assets are loaded when forms are rendered. Checks for DMN configuration and triggers asset loading through `enqueue_gravity_scripts()` method. Provides early asset loading to prevent timing issues.

**`enqueue_gravity_scripts($form, $is_ajax)`**  
Main asset loading orchestrator for individual forms. Validates DMN configuration, calls `enqueue_gravity_form_assets()` for script loading, and schedules configuration localization through WordPress footer action. Handles both standard page loads and AJAX form submissions.

**`enqueue_gravity_form_assets($form, $config)`**  
Loads form-specific assets including main Gravity Forms integration script and radio synchronization assets when needed. Coordinates with assets manager to prevent duplicate script loading and ensures proper dependency management.

### Asset Loading and Management

These methods coordinate with the assets manager to provide optimal asset loading performance.

**`maybe_enqueue_assets()`**  
WordPress hook callback (`wp_enqueue_scripts`) that conditionally loads frontend assets. Uses centralized detection from assets manager combined with page-specific form detection to minimize unnecessary asset loading. Triggers both main frontend assets and Gravity Forms-specific scripts when needed.

**`has_dmn_enabled_forms_on_page()`**  
Analyzes current page content to determine if DMN-enabled forms are present. Employs multiple detection strategies including shortcode scanning (`extract_form_ids_from_shortcodes()`), Gutenberg block detection (`extract_form_ids_from_blocks()`), and URL parameter checking. Provides comprehensive page analysis for accurate asset loading decisions.

**`enqueue_gravity_forms_scripts()`**  
Loads main Gravity Forms integration JavaScript with proper dependencies and localization. Includes static loading flag to prevent duplicate enqueuing and provides comprehensive localization with AJAX URLs, nonces, debug status, and user-facing strings. Handles both development and production environments.

### Configuration Localization

These methods handle outputting configuration data to JavaScript for frontend functionality.

**`ensure_form_config_localized($form, $config)`**  
Comprehensive configuration localization with sophisticated duplicate prevention. Creates JavaScript configuration objects including form mappings, evaluation settings, and debug information. Implements multiple layers of duplicate prevention including internal tracking, browser-side checking, and timing protection.

**`get_field_mappings($config)`** and **`get_result_mappings($config)`**  
Parse JSON configuration strings from database into PHP arrays with error handling. Provide safe JSON decoding with fallback to empty arrays when configuration is invalid or missing.

**`extract_result_field_ids($form_id, $config, $field_mappings, $result_mappings)`**  
Builds comprehensive list of result field IDs for JavaScript configuration. Implements multi-stage extraction including result mappings analysis, field mappings filtering for result variables, and form-specific corrections. Ensures JavaScript receives accurate field targeting information for result display.

### Radio Button Synchronization

These methods provide specialized radio button synchronization functionality for complex form interactions.

**`add_radio_sync_hooks()`**  
Establishes WordPress hooks for radio button synchronization feature including `gform_pre_render` callback for sync detection and initialization.

**`maybe_add_radio_sync($form)`**  
Evaluates forms for radio synchronization requirements through `form_needs_radio_sync()` method. Loads radio sync assets and schedules initialization script when synchronization is needed.

**`form_needs_radio_sync($form_id)`**  
Determines whether a form requires radio button synchronization based on configuration settings and form structure. Currently returns false as placeholder for implementation based on specific requirements.

**`add_radio_sync_initialization($form_id)`**  
Outputs JavaScript initialization code for radio button synchronization functionality. Creates script block that calls global radio sync initialization function when available.

### AJAX Handlers and Form Processing

These methods handle server-side processing of evaluation requests and form interactions.

**`ajax_evaluate_form()`**  
Main AJAX handler for DMN evaluation requests from frontend. Validates security nonces, processes form and configuration IDs, retrieves configuration from database, and delegates evaluation to API manager. Returns JSON response with evaluation results or error messages.

**`validate_dmn_fields($validation_result)`**  
Gravity Forms validation hook callback that integrates with GF validation system. Provides extension point for DMN-specific validation rules and requirements. Currently maintains compatibility with existing validation workflow.

**`handle_post_submission($entry, $form)`**  
Gravity Forms submission hook callback for post-submission processing. Provides extension point for additional data processing, logging, or integration with external systems after form submission completion.

### Admin Integration Methods

These methods handle WordPress admin interface integration and form editor enhancements.

**`init_admin_integration()`**  
Initializes admin-specific functionality when in WordPress admin area. Provides setup point for admin interface enhancements and editor integrations.

**`add_editor_script()`**  
Adds JavaScript functionality to Gravity Forms form editor for enhanced DMN integration features. Currently provides basic editor enhancement placeholder for future development.

**`add_field_advanced_settings($position, $form_id)`**  
Adds DMN-specific field settings to Gravity Forms field editor interface. Provides extension point for advanced field configuration options like DMN variable mapping settings.

### Cache Management Methods

These methods provide comprehensive cache management for optimal performance and data freshness.

**`clear_form_cache($form_id = null)`**  
Clears configuration and field caches for specific forms or all cached data. Removes cache entries matching form ID patterns and clears fields cache. Provides granular cache control for development and configuration updates.

**`clear_all_caches()`**  
Comprehensive cache clearing that resets all static cache arrays including configuration cache, fields cache, localization tracking, and timestamp records. Used during plugin updates and major configuration changes.

**`clear_gravity_forms_localization_cache($form_id = null)`**  
Specialized cache clearing for JavaScript localization data. Removes localized configuration tracking and timestamp records to force fresh configuration output. Supports both specific form clearing and complete localization cache reset.

**`reload_form_configuration($form_id)`**  
Public API method that clears cache and reloads configuration for specific form. Provides clean interface for external components to force configuration refresh after updates.

### Utility and Helper Methods

These internal methods support the public API and handle core detection and processing logic.

**`get_form_config($form_id)`**  
Internal configuration retrieval with caching implementation. Checks cache first to avoid database queries, retrieves configuration from database manager when needed, and stores results in cache for subsequent requests.

**`form_has_dmn_config($form_id)`**  
Utility method that determines whether a form has DMN configuration by checking if `get_form_config()` returns non-null result. Used throughout the class for conditional functionality.

**`any_forms_have_dmn_config($form_ids)`**  
Batch checking method that determines if any forms in provided array have DMN configurations. Used by detection methods to efficiently process multiple form IDs.

**`extract_form_ids_from_shortcodes($content)`** and **`extract_form_ids_from_blocks($post)`**  
Content parsing methods that extract Gravity Forms form IDs from post content using regular expressions for shortcodes and WordPress block parsing for Gutenberg blocks. Support comprehensive form detection across different content types.

**`find_gravity_form_ids_in_blocks($blocks)`**  
Recursive block parsing that handles nested Gutenberg block structures to find all Gravity Forms blocks including those within columns, groups, and other container blocks.

**`count_form_pages($form)`**  
Utility method that calculates number of pages in multi-page Gravity Forms by counting page break fields. Used for button placement logic in complex forms.

### Performance and Debug Methods

These methods provide debugging capabilities and performance monitoring integration.

**`log_debug($message)`**  
Conditional debug logging that outputs messages only when `WP_DEBUG` is enabled. Provides consistent logging format with class identifier prefix for easy log filtering.

**`get_performance_metrics()`**  
Returns performance data when performance monitor is available including initialization timing, cache hit statistics, and memory usage information. Provides fallback metrics when performance monitor is unavailable.

### Backward Compatibility Methods

These methods maintain compatibility with existing code while providing clean migration paths.

**`add_gravity_forms_hooks()`**  
Backward compatibility method that maintains existing API expectations while functionality has been moved to `conditional_init_gravity_forms()`. Provides logging for compatibility tracking.

**`get_core_instance()`**, **`get_assets_manager()`**, **`get_database_manager()`**  
Public accessor methods that provide external access to manager instances for integration and testing purposes. Enable loose coupling between components while maintaining clear dependency relationships.

## Integration Dependencies

The class manages integration with the following WordPress and plugin systems:

### WordPress Core Integration
- **Actions**: `init` (availability checking and conditional initialization), `wp_enqueue_scripts` (asset loading), `admin_init` (admin integration), `wp_footer` (configuration localization)
- **Filters**: `gform_submit_button` (button addition), `gform_validation` (form validation integration)
- **Functions**: WordPress asset management (`wp_enqueue_script`, `wp_localize_script`), nonce security (`wp_create_nonce`, `wp_verify_nonce`), database operations through `$wpdb`

### Gravity Forms Integration
- **Classes**: `GFForms` (availability checking), `GFAPI` (form data access), `GFCommon` (version checking)
- **Hooks**: `gform_enqueue_scripts` (asset loading), `gform_pre_render` (early asset loading), `gform_after_submission` (post-submission processing), `gform_editor_js` (editor enhancements), `gform_field_advanced_settings` (field configuration)
- **Data Structures**: Form arrays, field objects, entry arrays for comprehensive form handling

### Plugin Manager Integration
- **Assets Manager**: Coordinated asset loading, script dependency management, performance optimization
- **Database Manager**: Configuration storage and retrieval, cache management, data persistence
- **Core Plugin**: API management delegation, performance monitoring integration, centralized plugin orchestration
- **Performance Monitor**: Timing collection, metrics reporting, optimization recommendations

## Asset File Dependencies

The class coordinates loading of the following specialized asset files:

- **Gravity Forms JavaScript**: `assets/js/gravity-forms.js` (main integration functionality)
- **Radio Synchronization**: `assets/js/radio-sync.js` (specialized radio button coordination)
- **Integration Stylesheets**: Form-specific CSS through assets manager coordination

All assets are loaded with proper WordPress dependency management, version-based cache busting, and intelligent conditional loading based on page content analysis and configuration requirements.