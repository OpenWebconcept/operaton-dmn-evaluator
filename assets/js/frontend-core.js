/**
 * Operaton DMN Frontend Core Module
 * Foundation module providing global state, initialization, and core utilities
 *
 * This module must be loaded FIRST as it provides the foundation for all other modules.
 * Dependencies: jQuery, operaton-dmn-debug.js
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

operatonDebugFrontend('Frontend Core module loading...');

// =============================================================================
// GLOBAL STATE MANAGEMENT
// =============================================================================

/**
 * Global processing locks to prevent duplicate operations
 */
window.operatonProcessingLock = window.operatonProcessingLock || {};

/**
 * Enhanced global initialization state
 * Tracks initialization progress and performance metrics
 */
window.operatonInitialized = window.operatonInitialized || {
  forms: new Set(),
  globalInit: false,
  jQueryReady: false,
  initInProgress: false,
  eventsBound: new Set(),
  performanceStats: {
    initializationAttempts: 0,
    successfulInits: 0,
    totalProcessingTime: 0,
    cacheHits: 0,
  },
};

/**
 * Global caching utilities
 * Shared across all modules for DOM queries and configuration
 */
window.domQueryCache = new Map();
window.formConfigCache = new Map();

// =============================================================================
// DEPENDENCY DETECTION FUNCTIONS
// =============================================================================

/**
 * Wait for jQuery to become available
 * @param {function} callback Function to call when jQuery is ready
 * @param {number} maxAttempts Maximum number of detection attempts
 */
function waitForJQuery(callback, maxAttempts = 50) {
  let attempts = 0;

  function check() {
    attempts++;

    if (typeof jQuery !== 'undefined') {
      operatonDebugVerbose('Frontend', 'jQuery found', {attempts: attempts});
      callback();
    } else if (attempts < maxAttempts) {
      if (attempts % 10 === 0) {
        operatonDebugVerbose('Frontend', 'Still waiting for jQuery', {attempt: attempts});
      }
      const delay = Math.min(100 * Math.pow(1.1, attempts), 1000);
      setTimeout(check, delay);
    } else {
      operatonDebugMinimal('Frontend', 'jQuery not found after attempts', {maxAttempts: maxAttempts});
    }
  }
  check();
}

/**
 * Wait for operaton_ajax configuration to become available
 * @param {function} callback Function to call when operaton_ajax is ready
 * @param {number} maxAttempts Maximum number of detection attempts
 */
function waitForOperatonAjax(callback, maxAttempts = 50) {
  let attempts = 0;

  function check() {
    attempts++;

    if (typeof window.operaton_ajax !== 'undefined') {
      operatonDebugVerbose('Frontend', 'operaton_ajax found', {attempts: attempts});
      callback();
    } else if (attempts < maxAttempts) {
      if (attempts % 10 === 0) {
        operatonDebugVerbose('Frontend', 'Still waiting for operaton_ajax', {attempt: attempts});
      }
      setTimeout(check, 100);
    } else {
      operatonDebugMinimal('Frontend', 'operaton_ajax not found after attempts', {maxAttempts: maxAttempts});
      createEmergencyOperatonAjax();
      callback();
    }
  }
  check();
}

/**
 * Create emergency fallback for operaton_ajax if not loaded properly
 */
function createEmergencyOperatonAjax() {
  if (typeof window.operaton_ajax === 'undefined') {
    operatonDebugMinimal('Frontend', 'Creating emergency operaton_ajax fallback');
    window.operaton_ajax = {
      url: '/wp-json/operaton-dmn/v1/evaluate',
      nonce: 'fallback',
      debug: true,
      strings: {
        evaluating: 'Evaluating...',
        error: 'Evaluation failed',
        success: 'Evaluation completed',
        loading: 'Loading...',
        no_config: 'Configuration not found',
        validation_failed: 'Please fill in all required fields',
        connection_error: 'Connection error. Please try again.',
        timeout_error: 'Request timeout. Please try again.',
      },
      emergency_mode: true,
    };
  }
}

// =============================================================================
// CORE INITIALIZATION FUNCTIONS
// =============================================================================

/**
 * Main DMN system initialization
 * Sets up Gravity Forms hooks and triggers form detection
 */
function initOperatonDMN() {
  // Prevent duplicate global initialization
  if (window.operatonInitialized.globalInit) {
    operatonDebugVerbose('Frontend', 'Global initialization already complete, skipping');
    return;
  }

  operatonDebugFrontend('Starting Operaton DMN initialization...');

  // Hook into Gravity Forms events if available
  if (typeof gform !== 'undefined' && gform.addAction) {
    // Remove any existing handlers first
    if (gform.removeAction) {
      gform.removeAction('gform_post_render', 'operaton_form_render');
    }

    gform.addAction(
      'gform_post_render',
      function (formId) {
        operatonDebugVerbose('Frontend', 'Gravity Form rendered', {formId: formId});
        
        // Clear DOM cache for this form
        clearDOMCache(formId);

        // Small delay to ensure DOM is fully rendered
        setTimeout(() => {
          // Call form initialization (will be provided by frontend-forms.js)
          if (typeof window.initializeForm === 'function') {
            window.initializeForm(formId);
          }
        }, 100);
      },
      10,
      'operaton_form_render'
    );

    operatonDebugVerbose('Frontend', 'Hooked into gform_post_render action');
  }

  // Initial form detection
  setTimeout(() => {
    // Call form detection (will be provided by frontend-forms.js)
    if (typeof window.detectForms === 'function') {
      window.detectForms();
    }
  }, 200);

  // Set global flag
  window.operatonInitialized.globalInit = true;
  operatonDebugFrontend('Operaton DMN core initialization complete');
}

/**
 * Reset the entire form system
 * Used for debugging and emergency recovery
 */
function resetFormSystem() {
  operatonDebugVerbose('Frontend', 'Resetting form system...');

  // Clear state
  window.operatonInitialized.forms.clear();
  window.operatonInitialized.globalInit = false;
  window.operatonInitialized.initInProgress = false;
  window.operatonInitialized.eventsBound.clear();

  // Clear progress flags
  Object.keys(window.operatonInitialized).forEach(key => {
    if (key.startsWith('init_')) {
      delete window.operatonInitialized[key];
    }
  });

  // Clear caches
  window.domQueryCache.clear();
  window.formConfigCache.clear();
  
  // Clear button manager cache if available
  if (window.operatonButtonManager) {
    window.operatonButtonManager.clearCache();
  }

  // Reset stats
  window.operatonInitialized.performanceStats = {
    initializationAttempts: 0,
    successfulInits: 0,
    totalProcessingTime: 0,
    cacheHits: 0,
  };

  // Re-initialize after reset
  setTimeout(() => {
    if (typeof window.detectForms === 'function') {
      window.detectForms();
    }
  }, 500);

  operatonDebugVerbose('Frontend', 'System reset complete');
}

// =============================================================================
// CACHE MANAGEMENT UTILITIES
// =============================================================================

/**
 * Clear DOM cache for a specific form
 * @param {number} formId Form ID to clear cache for
 */
function clearDOMCache(formId) {
  if (!window.domQueryCache) return;
  
  const keysToDelete = [];
  window.domQueryCache.forEach((value, key) => {
    if (key.includes(`_${formId}_`) || key.includes(`form_${formId}`)) {
      keysToDelete.push(key);
    }
  });
  
  keysToDelete.forEach(key => window.domQueryCache.delete(key));
}

/**
 * Clear all caches
 * Used during system reset or emergency cleanup
 */
function clearAllCaches() {
  if (window.domQueryCache) {
    window.domQueryCache.clear();
  }
  if (window.formConfigCache) {
    window.formConfigCache.clear();
  }
}

// =============================================================================
// MAIN INITIALIZATION SEQUENCE
// =============================================================================

/**
 * Primary initialization function
 * Coordinates jQuery detection, operaton_ajax loading, and system startup
 */
function performInitialization($) {
  operatonDebugVerbose('Frontend', 'jQuery available', {version: $.fn.jquery});

  // Wait for operaton_ajax and initialize
  waitForOperatonAjax(() => {
    const initStartTime = performance.now();
    operatonDebugFrontend('Initializing Operaton DMN...');

    window.operatonInitialized.jQueryReady = true;
    initOperatonDMN();

    // Secondary detection for late-loading forms
    setTimeout(() => {
      if (!window.operatonInitialized.initInProgress && typeof window.detectForms === 'function') {
        window.detectForms();
      }
    }, 1000);

    const initEndTime = performance.now();
    window.operatonInitialized.performanceStats.totalProcessingTime += initEndTime - initStartTime;
    
    operatonDebugFrontend('Operaton DMN initialization complete', {
      timeMs: (initEndTime - initStartTime).toFixed(2)
    });
  });

  // Set up cleanup handlers
  $(window).on('beforeunload', function(e) {
    // Check if this might be form navigation rather than actual page unload
    const hasActiveForm = document.querySelector('form[id^="gform_"]');
    const isGravityFormsPage = window.location.href.includes('gf_page=') || document.querySelector('.gform_wrapper');

    if (hasActiveForm && isGravityFormsPage) {
      // This looks like form navigation - do minimal cleanup only
      operatonDebugVerbose('Frontend', 'Form navigation detected - minimal cleanup only');

      // Only clear performance-related caches that are safe to clear
      if (window.domQueryCache && window.domQueryCache.size > 100) {
        window.domQueryCache.clear();
      }
      return;
    }

    // This appears to be actual page navigation - safe to do full cleanup
    operatonDebugVerbose('Frontend', 'Page navigation detected - performing cleanup');
    clearAllCaches();
  });

  $(document).ready(() => {
    operatonDebugVerbose('Frontend', 'Document ready - initialization active');
  });
}

// =============================================================================
// MAIN STARTUP SEQUENCE (IIFE)
// =============================================================================

(function () {
  'use strict';

  // Ensure we only run once per page load
  if (window.operatonMainInitCalled) {
    operatonDebugVerbose('Frontend', 'Main initialization already called, skipping');
    return;
  }
  window.operatonMainInitCalled = true;

  // Initialize based on jQuery availability
  if (typeof jQuery !== 'undefined') {
    operatonDebugVerbose('Frontend', 'jQuery available immediately');
    performInitialization(jQuery);
  } else {
    operatonDebugVerbose('Frontend', 'jQuery not immediately available - waiting...');
    waitForJQuery(() => {
      performInitialization(jQuery);
    });
  }
})();

// =============================================================================
// ADDITIONAL EVENT LISTENERS
// =============================================================================

window.addEventListener('load', () => {
  setTimeout(() => {
    if (!window.operatonInitialized.globalInit) {
      operatonDebugVerbose('Frontend', 'Window load: Attempting late initialization...');
      if (typeof jQuery !== 'undefined') {
        if (typeof window.detectForms === 'function') {
          window.detectForms();
        }
      } else {
        operatonDebugMinimal('Frontend', 'Window load: jQuery still not available');
      }
    } else {
      operatonDebugVerbose('Frontend', 'Window load: Initialization already complete');
    }
  }, 1000);
});

document.addEventListener('DOMContentLoaded', () => {
  operatonDebugVerbose('Frontend', 'DOM Content Loaded - checking initialization state');
  
  if (typeof jQuery !== 'undefined' && !window.operatonInitialized.globalInit) {
    operatonDebugVerbose('Frontend', 'Early DOM initialization trigger');
    setTimeout(() => {
      if (typeof window.detectForms === 'function') {
        window.detectForms();
      }
    }, 100);
  }
});

// =============================================================================
// GLOBAL EXPORTS FOR OTHER MODULES
// =============================================================================

// Export core functions for other modules to use
window.OperatonCore = {
  // State management
  getInitializationState: () => window.operatonInitialized,
  isInitialized: () => window.operatonInitialized.globalInit,
  
  // Cache management
  clearDOMCache: clearDOMCache,
  clearAllCaches: clearAllCaches,
  
  // System control
  resetFormSystem: resetFormSystem,
  
  // Dependency detection
  waitForJQuery: waitForJQuery,
  waitForOperatonAjax: waitForOperatonAjax
};

// Make reset function globally accessible for debugging
window.operatonReinitialize = resetFormSystem;

operatonDebugFrontend('Frontend Core module loaded - Production version');