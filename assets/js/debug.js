/**
 * Operaton DMN JavaScript Debug Bridge
 *
 * Provides JavaScript debug logging that integrates with the PHP debug manager.
 * All debug calls from JavaScript are transmitted via AJAX to appear in the
 * WordPress error log with proper component organization and level control.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

(function () {
  'use strict';

  // Prevent multiple initialization
  if (window.operatonDebugJS) {
    console.log('Operaton Debug: Already initialized, skipping duplicate');
    return;
  }

  // Debug level constants (matching PHP)
  var DEBUG_LEVEL_NONE = 0;
  var DEBUG_LEVEL_MINIMAL = 1;
  var DEBUG_LEVEL_STANDARD = 2;
  var DEBUG_LEVEL_VERBOSE = 3;
  var DEBUG_LEVEL_DIAGNOSTIC = 4;

  /**
   * Main JavaScript debug function
   * Sends debug messages to PHP debug manager via AJAX
   *
   * @param {string} component Component identifier (Frontend, GravityForms, etc.)
   * @param {string} message Debug message
   * @param {*} data Optional data to log
   * @param {number} level Debug level (1-4, default: 2)
   */
  window.operatonDebugJS = function (component, message, data, level) {
    // Default to standard level
    if (typeof level === 'undefined') {
      level = DEBUG_LEVEL_STANDARD;
    }

    // Validate inputs
    if (!component || !message) {
      console.warn('Operaton Debug: Component and message are required');
      return;
    }

    // Check if debug config is available
    if (typeof window.OperatonDebugConfig === 'undefined') {
      // Fallback to console if config not available
      consoleFallback(component, message, data, level);
      return;
    }

    // Check if current debug level allows this message
    if (level > window.OperatonDebugConfig.debug_level) {
      return; // Skip if level too high
    }

    // Send via AJAX using the actual action name from debug manager
    if (typeof jQuery !== 'undefined') {
      sendAjaxLog(component, message, data, level);
    } else {
      // Fallback to console if jQuery not available
      consoleFallback(component, message, data, level);
    }
  };

  /**
   * Send debug log via AJAX to PHP debug manager
   */
  function sendAjaxLog(component, message, data, level) {
    jQuery
      .post(window.OperatonDebugConfig.ajax_url, {
        action: 'operaton_debug_log', // This matches the AJAX handler
        nonce: window.OperatonDebugConfig.nonce,
        component: component,
        message: message,
        data: data ? JSON.stringify(data) : null,
        level: level,
      })
      .fail(function (xhr, status, error) {
        // Fallback to console if AJAX fails
        console.warn('Operaton Debug: AJAX failed, using console fallback');
        consoleFallback(component, message, data, level);
      });
  }

  /**
   * Console fallback for when AJAX is not available or fails
   */
  function consoleFallback(component, message, data, level) {
    var levelNames = {
      1: '[MIN]',
      2: '',
      3: '[VERBOSE]',
      4: '[DIAG]',
    };

    var levelName = levelNames[level] || '';
    var logMessage = '[Operaton ' + component + '] ' + levelName + (levelName ? ' ' : '') + message;

    // Use appropriate console method based on level
    if (level === DEBUG_LEVEL_MINIMAL) {
      console.error(logMessage, data || '');
    } else if (level === DEBUG_LEVEL_VERBOSE || level === DEBUG_LEVEL_DIAGNOSTIC) {
      console.log(logMessage, data || '');
    } else {
      console.log(logMessage, data || '');
    }
  }

  // =============================================================================
  // CONVENIENCE FUNCTIONS FOR DIFFERENT DEBUG LEVELS
  // =============================================================================

  /**
   * Log minimal level messages (errors, critical warnings)
   */
  window.operatonDebugMinimal = function (component, message, data) {
    operatonDebugJS(component, message, data, DEBUG_LEVEL_MINIMAL);
  };

  /**
   * Log standard level messages (normal operations)
   */
  window.operatonDebugStandard = function (component, message, data) {
    operatonDebugJS(component, message, data, DEBUG_LEVEL_STANDARD);
  };

  /**
   * Log verbose level messages (detailed information)
   */
  window.operatonDebugVerbose = function (component, message, data) {
    operatonDebugJS(component, message, data, DEBUG_LEVEL_VERBOSE);
  };

  /**
   * Log diagnostic level messages (full debugging info)
   */
  window.operatonDebugDiagnostic = function (component, message, data) {
    operatonDebugJS(component, message, data, DEBUG_LEVEL_DIAGNOSTIC);
  };

  // =============================================================================
  // SPECIALIZED LOGGING FUNCTIONS
  // =============================================================================

  /**
   * Log API-related messages with automatic component prefix
   */
  window.operatonDebugAPI = function (message, data, level) {
    operatonDebugJS('API', message, data, level || DEBUG_LEVEL_STANDARD);
  };

  /**
   * Log frontend-related messages with automatic component prefix
   */
  window.operatonDebugFrontend = function (message, data, level) {
    operatonDebugJS('Frontend', message, data, level || DEBUG_LEVEL_STANDARD);
  };

  /**
   * Log Gravity Forms-related messages with automatic component prefix
   */
  window.operatonDebugGravityForms = function (message, data, level) {
    operatonDebugJS('GravityForms', message, data, level || DEBUG_LEVEL_STANDARD);
  };

  /**
   * Log decision flow-related messages with automatic component prefix
   */
  window.operatonDebugDecisionFlow = function (message, data, level) {
    operatonDebugJS('DecisionFlow', message, data, level || DEBUG_LEVEL_STANDARD);
  };

  /**
   * Log radio sync-related messages with automatic component prefix
   */
  window.operatonDebugRadioSync = function (message, data, level) {
    operatonDebugJS('RadioSync', message, data, level || DEBUG_LEVEL_STANDARD);
  };

  /**
   * Log API test-related messages with automatic component prefix
   */
  window.operatonDebugAPITest = function (message, data, level) {
    operatonDebugJS('APITest', message, data, level || DEBUG_LEVEL_STANDARD);
  };

  // =============================================================================
  // UTILITY FUNCTIONS
  // =============================================================================

  /**
   * Get current debug configuration for inspection
   */
  window.operatonGetDebugConfig = function () {
    return window.OperatonDebugConfig || 'Debug config not available';
  };

  /**
   * Test the debug system
   */
  window.operatonTestDebug = function () {
    console.log('Testing Operaton Debug System...');

    // Test different levels
    operatonDebugJS('Test', 'Testing minimal level', { test: 'minimal' }, DEBUG_LEVEL_MINIMAL);
    operatonDebugJS('Test', 'Testing standard level', { test: 'standard' }, DEBUG_LEVEL_STANDARD);
    operatonDebugJS('Test', 'Testing verbose level', { test: 'verbose' }, DEBUG_LEVEL_VERBOSE);
    operatonDebugJS('Test', 'Testing diagnostic level', { test: 'diagnostic' }, DEBUG_LEVEL_DIAGNOSTIC);

    // Test convenience functions
    operatonDebugFrontend('Frontend test message');
    operatonDebugGravityForms('Gravity Forms test message');

    console.log('Debug test complete. Check WordPress error log for results.');
  };

  /**
   * Check if debug system is ready
   */
  window.operatonDebugReady = function () {
    var ready = {
      config_available: typeof window.OperatonDebugConfig !== 'undefined',
      jquery_available: typeof jQuery !== 'undefined',
      debug_level: window.OperatonDebugConfig ? window.OperatonDebugConfig.debug_level : 'unknown',
      components: window.OperatonDebugConfig ? window.OperatonDebugConfig.components : 'unknown',
    };

    console.log('Operaton Debug System Status:', ready);
    return ready;
  };

  // =============================================================================
  // INITIALIZATION AND SELF-DIAGNOSTICS
  // =============================================================================

  /**
   * Initialize debug system when ready
   */
  function initializeDebugSystem() {
    // Wait for configuration to be available
    var maxWaits = 50;
    var currentWait = 0;

    function checkForConfig() {
      currentWait++;

      if (typeof window.OperatonDebugConfig !== 'undefined') {
        // Configuration is available
        operatonDebugJS('Debug', 'JavaScript debug bridge initialized', {
          debug_level: window.OperatonDebugConfig.debug_level,
          components: window.OperatonDebugConfig.components,
          ajax_url: window.OperatonDebugConfig.ajax_url,
        });

        // Make ready status available
        window.operatonDebugSystemReady = true;

        return;
      }

      if (currentWait < maxWaits) {
        setTimeout(checkForConfig, 100);
      } else {
        console.warn('Operaton Debug: Configuration not found after', maxWaits, 'attempts');
        console.log('Operaton Debug: Will use console fallback mode');
        window.operatonDebugSystemReady = false;
      }
    }

    checkForConfig();
  }

  // =============================================================================
  // AUTO-INITIALIZATION
  // =============================================================================

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDebugSystem);
  } else {
    // DOM already loaded
    setTimeout(initializeDebugSystem, 100);
  }

  // Also try on window load as fallback
  if (typeof window.addEventListener !== 'undefined') {
    window.addEventListener('load', function () {
      if (!window.operatonDebugSystemReady) {
        setTimeout(initializeDebugSystem, 200);
      }
    });
  }

  console.log('Operaton Debug: Bridge loaded, waiting for configuration...');
})();
