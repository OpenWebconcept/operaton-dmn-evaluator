/**
 * Operaton DMN JavaScript Debug Bridge
 *
 * Provides JavaScript debug logging that integrates with the PHP debug manager.
 * All debug calls from JavaScript are transmitted via AJAX to appear in the
 * WordPress error log with proper component organization and level control.
 *
 * ENHANCED: Now automatically includes console.log during development
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
   * Check if we're in development mode
   * @returns {boolean} True if in development mode
   */
  function isDevelopmentMode() {
    return (
      // Check if operaton_ajax debug flag is set
      (window.operaton_ajax && window.operaton_ajax.debug) ||
      // Check if debug config indicates debug mode
      (window.OperatonDebugConfig && window.OperatonDebugConfig.debug_level > DEBUG_LEVEL_STANDARD) ||
      // Check for localhost/dev domains
      window.location.hostname.includes('localhost') ||
      window.location.hostname.includes('.local') ||
      window.location.hostname.includes('dev') ||
      // Check for WP_DEBUG indicator
      (window.OperatonDebugConfig && window.OperatonDebugConfig.wp_debug)
    );
  }

  /**
   * Development mode console output with beautiful formatting
   */
  function devConsoleLog(component, message, data, level) {
    var styles = {
      1: 'color: #ef4444; font-weight: bold;', // Red for minimal
      2: 'color: #10b981;', // Green for standard
      3: 'color: #3b82f6;', // Blue for verbose
      4: 'color: #a855f7;', // Purple for diagnostic
    };

    var emojis = {
      1: '🚨',
      2: '🔧',
      3: '📝',
      4: '🔍',
    };

    var emoji = emojis[level] || '📋';
    var style = styles[level] || '';
    var logMessage = emoji + ' [' + component + '] ' + message;

    if (data) {
      console.log('%c' + logMessage, style, data);
    } else {
      console.log('%c' + logMessage, style);
    }
  }

  /**
   * Console fallback with better formatting
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

    // ENHANCEMENT: Always console.log during development mode
    // This provides immediate visual feedback without waiting for AJAX
    if (isDevelopmentMode()) {
      devConsoleLog(component, message, data, level);
    }

    // Check if debug config is available
    if (typeof window.OperatonDebugConfig === 'undefined') {
      // Only fallback to console if NOT in development mode (to avoid duplicates)
      if (!isDevelopmentMode()) {
        consoleFallback(component, message, data, level);
      }
      // Don't return - we still want to attempt other methods
      // FIXED: Don't return early here - continue to try AJAX if possible
      // return; // ❌ REMOVED - This was causing the bug!
    }

    // Check if current debug level allows this message
    // Only skip if config is available and level is too high
    if (window.OperatonDebugConfig && level > window.OperatonDebugConfig.debug_level) {
      return; // Skip if level too high
    }

    // Send via AJAX using the actual action name from debug manager
    if (typeof jQuery !== 'undefined' && window.OperatonDebugConfig) {
      sendAjaxLog(component, message, data, level);
    } else if (!isDevelopmentMode()) {
      // Only fallback to console if NOT in development mode (to avoid duplicates)
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
        // Fallback to console if AJAX fails (only if not in dev mode to avoid duplicates)
        if (!isDevelopmentMode()) {
          console.warn('Operaton Debug: AJAX failed, using console fallback');
          consoleFallback(component, message, data, level);
        }
      });
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
   * Check if in development mode
   */
  window.operatonIsDevMode = function () {
    return isDevelopmentMode();
  };

  /**
   * Test the debug system
   */
  window.operatonTestDebug = function () {
    console.log('Testing Operaton Debug System...');
    console.log('Development mode:', isDevelopmentMode());

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

  // Mark as initialized
  window.operatonDebugJS = window.operatonDebugJS || true;
})();
