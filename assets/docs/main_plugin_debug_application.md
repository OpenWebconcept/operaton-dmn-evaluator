# Debug Manager Application in Main Plugin File - Documentation

## Overview

This document describes the implementation and application of the `Operaton_DMN_Debug_Manager` in the main plugin file (`operaton-dmn-plugin.php`). The debug manager is integrated early in the bootstrap process to ensure consistent logging throughout the plugin lifecycle, using a component-based organization strategy that distinguishes between plugin-level operations and main class coordination activities.

## Component Organization Strategy

The main plugin file uses a clear component organization strategy to distinguish between different types of operations and their corresponding log contexts.

### Component Definitions

**`Operaton DMN Main`**
- Used for **global/plugin-level operations** outside of classes
- Examples:
  - Loading the performance monitor
  - Loading the auto-updater  
  - Loading debug tools
  - Global functions like `operaton_dmn_debug_status()`
  - Asset debug functions that run globally

**`Operaton DMN Evaluator`**
- Used for operations **inside the `OperatonDMNEvaluator` class**
- Examples:
  - Constructor initialization
  - Loading individual managers (assets, database, API, etc.)
  - Class-specific operations
  - Manager status checks

### In Practice

```php
// OUTSIDE classes (global scope) → 'Main'
operaton_debug('Main', 'Performance monitor loaded successfully');
operaton_debug('Main', 'Auto-updater loaded successfully');

// INSIDE OperatonDMNEvaluator class → 'Evaluator'  
operaton_debug('Evaluator', 'Starting fresh initialization');
operaton_debug('Evaluator', 'Assets manager loaded successfully');
```

### The Logic

- **`Main`** = Plugin-level, global scope, bootstrap operations
- **`Evaluator`** = Main plugin class operations, manager coordination

This makes it easier to filter logs - you can see what's happening at the plugin level vs. what's happening inside the main orchestrating class.

## Bootstrap Implementation

### Early Loading
The debug manager is loaded and initialized at the very beginning of the plugin bootstrap process, before any other classes or managers:

```php
// operaton-dmn-plugin.php - Early in file after constants
require_once __DIR__ . '/includes/class-operaton-dmn-debug-manager.php';
Operaton_DMN_Debug_Manager::get_instance();
```

### Implementation Sequence
1. **Constants definition** (plugin version, paths)
2. **Debug manager loading** (immediate availability)
3. **Performance monitor loading** (with debug logging)
4. **Auto-updater initialization** (with debug logging)
5. **Debug tools loading** (with debug logging)
6. **Main class initialization** (with component-based logging)

## Practical Implementation Examples

### Global Operations ('Main' Component)

**Performance Monitor Loading:**
```php
$performance_file = OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-performance.php';
if (file_exists($performance_file)) {
    require_once $performance_file;
    operaton_debug('Main', 'Performance monitor loaded successfully');
} else {
    operaton_debug_minimal('Main', 'Performance monitor file not found', ['path' => $performance_file]);
}
```

**Auto-updater Initialization:**
```php
if (file_exists($updater_file)) {
    require_once $updater_file;
    new OperatonDMNAutoUpdater(__FILE__, OPERATON_DMN_VERSION);
    
    operaton_debug('Main', 'Auto-updater loaded successfully', [
        'plugin_file' => __FILE__,
        'plugin_basename' => plugin_basename(__FILE__)
    ]);
} else {
    operaton_debug_minimal('Main', 'Auto-updater file not found', ['path' => $updater_file]);
}
```

**Debug Tools Loading:**
```php
operaton_debug_verbose('Main', 'WP_DEBUG is enabled, attempting to load debug tools');
$debug_file = OPERATON_DMN_PLUGIN_PATH . 'includes/update-debug.php';
operaton_debug_verbose('Main', 'Debug file path', ['path' => $debug_file]);

if (file_exists($debug_file)) {
    operaton_debug_verbose('Main', 'Debug file exists, loading...');
    require_once $debug_file;
    operaton_debug('Main', 'Debug file loaded successfully');
} else {
    operaton_debug_minimal('Main', 'Debug file NOT found', ['path' => $debug_file]);
}
```

### Class Operations ('Evaluator' Component)

**Constructor Initialization:**
```php
public function __construct() {
    operaton_debug('Evaluator', 'Starting fresh initialization', ['version' => OPERATON_DMN_VERSION]);
    
    // Prevent multiple initializations
    if (self::$initialized) {
        operaton_debug_verbose('Evaluator', 'Preventing duplicate initialization');
        return;
    }
}
```

**Manager Loading Sequence:**
```php
private function load_quirks_fix_manager() {
    require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-quirks-fix.php';
    $this->quirks_fix = new Operaton_DMN_Quirks_Fix();
    operaton_debug('Evaluator', 'Quirks fix manager loaded successfully');
}

private function load_assets_manager() {
    require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-assets.php';
    $this->assets = new Operaton_DMN_Assets(OPERATON_DMN_PLUGIN_URL, OPERATON_DMN_VERSION);
    operaton_debug('Evaluator', 'Assets manager loaded successfully');
}

private function load_database_manager() {
    require_once OPERATON_DMN_PLUGIN_PATH . 'includes/class-operaton-dmn-database.php';
    $this->database = new Operaton_DMN_Database(OPERATON_DMN_VERSION);
    operaton_debug('Evaluator', 'Database manager loaded successfully');
}
```

**Initialization Completion:**
```php
// Mark as initialized
self::$initialized = true;
operaton_debug('Evaluator', '✅ Initialization complete');
```

## Global Function Integration

### Plugin Status Function
```php
function operaton_dmn_debug_status() {
    $debug_manager = operaton_debug_manager();
    if (!$debug_manager->get_debug_level()) {
        return;
    }
    
    $instance = OperatonDMNEvaluator::get_instance();
    $status = $instance->get_managers_status();
    $health = $instance->health_check();
    
    operaton_debug_diagnostic('Main', '=== OPERATON DMN PLUGIN STATUS ===', [
        'plugin_version' => OPERATON_DMN_VERSION,
        'managers_status' => $status,
        'health_status' => !empty($health) ? $health : 'All systems operational'
    ]);

    // Performance monitoring integration
    $performance = $instance->get_performance_instance();
    if ($performance) {
        $summary = $performance->get_summary();
        operaton_debug_diagnostic('Main', 'Performance Monitor Available', [
            'performance_summary' => $summary
        ]);
    } else {
        operaton_debug_minimal('Main', 'Performance Monitor: Not available');
    }
}
```

### Asset Loading Functions
```php
function operaton_dmn_reset_asset_loading() {
    if (is_admin() && current_user_can('manage_options')) {
        $plugin_instance = OperatonDMNEvaluator::get_instance();
        $assets_manager = $plugin_instance->get_assets_instance();

        if ($assets_manager) {
            // Asset manager operations...
        }

        operaton_debug_verbose('Evaluator', 'Asset loading state manually reset');
    }
}
```

### Diagnostic Functions
```php
function debug_operaton_assets_loading_late() {
    // Asset analysis code...
    
    operaton_debug_diagnostic('Main', '=== OPERATON ASSETS DEBUG (LATE) ===', [
        'operaton_scripts' => $operaton_scripts,
        'frontend_script_data' => $frontend_script_data
    ]);
}
```

## Error Handling and Debug Levels

### Level-Appropriate Error Handling
```php
// Critical errors use minimal level
operaton_debug_minimal('Main', 'Performance monitor file not found', ['path' => $performance_file]);
operaton_debug_minimal('Main', 'Auto-updater file not found', ['path' => $updater_file]);

// Standard operations use default level
operaton_debug('Main', 'Performance monitor loaded successfully');
operaton_debug('Evaluator', 'Assets manager loaded successfully');

// Detailed diagnostics use verbose level
operaton_debug_verbose('Main', 'Debug file exists, loading...');
operaton_debug_verbose('Evaluator', 'Preventing duplicate initialization');

// Complete diagnostics use diagnostic level
operaton_debug_diagnostic('Main', 'Asset loading debug', $comprehensive_data);
```

## Real-World Output Example

The implementation produces organized, component-based debug output as demonstrated in actual testing:

```
[26-Sep-2025 15:59:08 UTC] Operaton DMN Main: Performance monitor loaded successfully
[26-Sep-2025 15:59:08 UTC] Operaton DMN Auto-Updater V11.6 initialized (NUCLEAR OVERRIDE MODE with Error Handling)
[26-Sep-2025 15:59:08 UTC] Operaton DMN Main: Auto-updater loaded successfully
[26-Sep-2025 15:59:08 UTC] Operaton DMN Main: Data: {
    "plugin_file": "/volume2/web/test-open-regels-lab-nl/wp-content/plugins/operaton-dmn-evaluator/operaton-dmn-plugin.php",
    "plugin_basename": "operaton-dmn-evaluator/operaton-dmn-plugin.php"
}
[26-Sep-2025 15:59:08 UTC] Operaton DMN Main [VERBOSE]: WP_DEBUG is enabled, attempting to load debug tools
[26-Sep-2025 15:59:08 UTC] Operaton DMN Main [VERBOSE]: Debug file path
[26-Sep-2025 15:59:08 UTC] Operaton DMN Main [VERBOSE]: Data: {
    "path": "/volume2/web/test-open-regels-lab-nl/wp-content/plugins/operaton-dmn-evaluator/includes/update-debug.php"
}
[26-Sep-2025 15:59:08 UTC] Operaton DMN Main [VERBOSE]: Debug file exists, loading...
[26-Sep-2025 15:59:08 UTC] Operaton DMN: OperatonDMNUpdateDebugger constructor called
[26-Sep-2025 15:59:08 UTC] Operaton DMN Main: Debug file loaded successfully
[26-Sep-2025 15:59:08 UTC] ⏱️ Operaton Performance: monitoring_start = 0ms (Memory: 6 MB, Peak: 6 MB) - Performance monitoring initialized
[26-Sep-2025 15:59:08 UTC] ⏱️ Operaton Performance: plugin_construct_start = 0.06ms (Memory: 6 MB, Peak: 6 MB) - Main plugin constructor started
[26-Sep-2025 15:59:08 UTC] Operaton DMN Evaluator: Starting fresh initialization
[26-Sep-2025 15:59:08 UTC] Operaton DMN Evaluator: Data: {
    "version": "1.0.0-beta.17"
}
[26-Sep-2025 15:59:08 UTC] Operaton DMN Evaluator: Quirks fix manager loaded successfully
[26-Sep-2025 15:59:08 UTC] ⏱️ Operaton Performance: quirks_fix_loaded = 0.13ms (Memory: 6 MB, Peak: 6 MB)
[26-Sep-2025 15:59:08 UTC] Operaton DMN Assets: WordPress hooks initialized
[26-Sep-2025 15:59:08 UTC] Operaton DMN Assets: Assets manager initialized with URL: https://test.open-regels-lab.nl/wp-content/plugins/operaton-dmn-evaluator/
[26-Sep-2025 15:59:08 UTC] Operaton DMN Evaluator: Assets manager loaded successfully
[26-Sep-2025 15:59:08 UTC] ⏱️ Operaton Performance: assets_manager_loaded = 0.19ms (Memory: 6 MB, Peak: 6 MB)
[26-Sep-2025 15:59:08 UTC] Operaton DMN Admin: Interface manager initialized
[26-Sep-2025 15:59:08 UTC] Operaton DMN Evaluator: Admin manager loaded successfully
[26-Sep-2025 15:59:08 UTC] ⏱️ Operaton Performance: admin_manager_loaded = 0.27ms (Memory: 6 MB, Peak: 6 MB)
```

## Benefits of This Organization

### Clear Separation of Concerns
- **Plugin-level operations** are clearly identified with 'Main' component
- **Class-level operations** are clearly identified with 'Evaluator' component
- **Mixed debug sources** work together harmoniously

### Improved Debugging Experience
- **Easy filtering**: Can filter logs by component to focus on specific areas
- **Clear context**: Always know whether an operation is global or class-specific
- **Consistent formatting**: All logs follow the same component-based format
- **Performance coordination**: Integrates seamlessly with performance monitoring

### Development and Troubleshooting
- **Bootstrap tracking**: Easy to see plugin loading sequence
- **Manager coordination**: Clear visibility into manager initialization order
- **Error isolation**: Can quickly identify whether issues are in bootstrap or class initialization
- **Status monitoring**: Comprehensive status reporting with organized output

This organization provides clear separation between plugin-level bootstrap operations and main class coordination activities, making logs easier to filter, analyze, and debug while maintaining consistency across the entire plugin ecosystem.