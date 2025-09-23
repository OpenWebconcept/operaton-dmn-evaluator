# Operaton DMN Assets Class - Documentation

## Overview

The `Operaton_DMN_Assets` class manages CSS and JavaScript asset loading for the Operaton DMN WordPress plugin. It provides intelligent asset detection, conditional loading based on page context, and comprehensive integration with WordPress asset management systems. The class handles frontend assets, admin interface assets, and specialized components like decision flow visualization and radio button synchronization.

## Class Properties

### Core Properties
- **`$performance`**: Optional performance monitor instance for timing and optimization tracking
- **`$plugin_url`**: Base plugin URL used for constructing asset file paths  
- **`$version`**: Plugin version string used for cache busting in asset URLs
- **`$gravity_forms_manager`**: Reference to Gravity Forms integration manager for coordinated asset loading

### Static Caching Properties
- **`$detection_cache`**: Stores detection results to avoid repeated page analysis
- **`$cache_timestamp`**: Tracks cache creation time for expiration management
- **`$detection_complete`**: Boolean flag preventing multiple detection runs
- **`$localized_configs`**: Tracks which script configurations have been localized to prevent duplicates
- **`$asset_loading_state`**: Monitors completion status of different asset groups

## Method Groupings

### Core Initialization & Setup

These methods handle the fundamental setup and WordPress integration of the assets manager.

**`__construct(string $plugin_url, string $version)`**  
Initializes the assets manager with the base plugin URL and version. Sets up performance monitoring if available, initializes cache timestamps, and establishes WordPress hooks through the `init_hooks()` method.

**`set_gravity_forms_manager(Operaton_DMN_Gravity_Forms $gravity_forms_manager)`**  
Establishes the relationship between the assets manager and the Gravity Forms integration component. This dependency injection allows coordinated asset loading when forms are detected on pages.

**`init_hooks()`**  
Registers WordPress action hooks for frontend asset loading (`wp_enqueue_scripts`), admin asset loading (`admin_enqueue_scripts`), and jQuery compatibility handling (`wp_footer`). This method establishes all WordPress integration points.

### Public API Methods

These methods provide the external interface for asset management and are called by other plugin components.

**`should_load_frontend_assets()`**  
Static method that determines whether frontend assets should be loaded on the current page. Uses caching to avoid repeated detection and employs multiple detection strategies including shortcode scanning, Gutenberg block detection, and special page identification.

**`maybe_enqueue_frontend_assets()`**  
WordPress hook callback that conditionally loads frontend assets based on the detection system. Only enqueues assets when DMN functionality is needed on the current page.

**`maybe_enqueue_admin_assets(string $hook)`**  
WordPress hook callback for admin pages that loads admin-specific assets only on plugin administration pages. Uses hook parameter filtering to minimize impact on other admin areas.

**`enqueue_frontend_assets()`**  
Loads all required CSS and JavaScript files for frontend DMN evaluation functionality. Handles the main frontend script, Gravity Forms integration scripts, decision flow visualization assets, and frontend styles. Includes duplicate loading protection and performance timing.

**`enqueue_admin_assets()`**  
Loads CSS and JavaScript files required for the WordPress admin interface, including configuration forms, API testing tools, and admin-specific styling. Provides script localization with AJAX endpoints and admin-specific strings.

**`enqueue_radio_sync_assets(int $form_id)`**  
Loads specialized assets for radio button synchronization between Gravity Forms and DMN evaluation results. Used when forms require synchronized radio button states.

**`force_enqueue(string $asset_group)`**  
Manually loads specific asset groups regardless of automatic detection. Supports 'frontend', 'admin', and 'decision_flow' groups for testing and emergency scenarios.

**`get_status()`**  
Returns comprehensive status information including detection completion state, cache age, WordPress asset registration status, and loading context. Used for debugging and monitoring purposes.

**`clear_form_cache(?int $form_id = null)`**  
Clears cached detection results for specific forms or all forms, forcing fresh detection on subsequent requests. Essential for development and after configuration changes.

### WordPress Hooks & Callbacks

These methods serve as WordPress hook callbacks and handle integration with WordPress core functionality.

**`add_jquery_compatibility()`**  
WordPress footer hook callback that adds jQuery compatibility information for debugging purposes. Provides browser compatibility data and ensures proper jQuery integration.

### Private Helper Methods

These internal methods support the public API and handle core detection and asset management logic.

**`perform_asset_detection()`**  
Core static detection logic that analyzes the current page to determine if DMN assets are needed. Employs multiple detection strategies including shortcode scanning, Gutenberg block detection, widget analysis, and special page identification.

**`has_gravity_forms_shortcode()`**  
Scans post content for Gravity Forms shortcodes (`[gravityform]` and `[gravityforms]`) that might contain DMN-enabled forms.

**`has_gravity_forms_block()`**  
Detects Gravity Forms Gutenberg blocks in post content using WordPress block detection functions.

**`has_dmn_forms_in_widgets()`**  
Placeholder method for scanning active widgets for Gravity Forms widgets containing DMN-enabled forms. Currently returns false to avoid unnecessary complexity.

**`is_special_page_requiring_assets()`**  
Identifies special pages that might need DMN assets loaded even without explicit form detection, such as pages with specific templates or query parameters.

**`should_load_decision_flow_assets()`**  
Determines whether decision flow visualization assets are needed based on configuration and context. Currently assumes decision flow is needed when other assets are loaded.

**`localize_frontend_script()`**  
Provides JavaScript configuration including AJAX endpoints, nonces, localized strings, and timeout settings. Includes duplicate prevention to avoid multiple localizations.

**`localize_configuration(string $handle, object $config, int $form_id)`**  
Provides form-specific configuration to JavaScript including DMN endpoints, process keys, and form-specific settings. Tracks localization to prevent duplicates.

**`add_inline_styles(?int $form_id = null, array $styles = array())`**  
Applies custom CSS styles for theme integration and visual customization. Supports both global theme variables and form-specific styling.

**`is_cache_expired(int $max_age = 300)`**  
Determines cache validity based on configurable expiration time. Default expiration is 300 seconds.

**`safe_json_decode(string $json_string, $default = array())`**  
Safely decodes JSON strings with error handling and default fallback values to prevent PHP errors from malformed data.

**`log_debug(string $message)`**  
Conditional debug logging that outputs messages only when `WP_DEBUG` is enabled.

### Utility and Debug Methods

These methods provide debugging capabilities and state management for development and troubleshooting.

**`generate_request_id()`**  
Creates unique identifiers for requests based on URI, method, query string, and timestamp. Used for request-specific tracking and cache management.

**`reset_all_loading_states()`**  
Resets all caches and loading states for testing and development scenarios. Clears detection cache, localization tracking, and asset loading completion flags.

**`log_performance()`**  
Outputs detailed performance and status information when debug mode is enabled. Includes detection status, cache information, WordPress asset states, and loading context.

### Backward Compatibility Methods

These methods maintain compatibility with existing code that expects specific method signatures and behaviors.

**`get_plugin_url()`**  
Returns the base plugin URL for external classes that need to construct asset paths or perform URL-based operations.

**`get_version()`**  
Returns the plugin version string for external classes that need version information for cache busting or compatibility checks.

**`get_loading_state()`**  
Legacy method that returns current loading status information. Provides backward compatibility for existing code patterns.

**`clear_form_localization_cache(int $form_id)`**  
Clears localized script configurations for specific forms, forcing fresh localization on next request.

**`clear_all_localization_cache()`**  
Clears all localized script configurations and resets loading states. Called during plugin deactivation and comprehensive cache clearing operations.

**`get_coordinator_status()`**  
Static method providing status information for legacy code that expects static coordinator patterns. Creates temporary instance if needed.

**`reset_loading_coordinator()`**  
Static method for resetting loading states, maintaining compatibility with existing code patterns that expect static reset functionality.

## Asset File Dependencies

The class manages loading of the following asset files:

- **Frontend JavaScript**: `assets/js/frontend.js` (main functionality), `assets/js/gravity-forms.js` (form integration), `assets/js/decision-flow.js` (visualization)
- **Admin JavaScript**: `assets/js/admin.js` (admin interface), `assets/js/api-test.js` (testing tools)
- **Specialized JavaScript**: `assets/js/radio-sync.js` (radio button synchronization)
- **Stylesheets**: `assets/css/frontend.css`, `assets/css/admin.css`, `assets/css/decision-flow.css`, `assets/css/radio-sync.css`

All assets are loaded with proper dependency management, version-based cache busting, and WordPress integration following plugin development best practices.