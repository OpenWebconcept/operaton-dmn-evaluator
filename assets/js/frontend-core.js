/**
 * Operaton DMN Frontend Core Module
 * Foundation and global state management
 *
 * @package OperatonDMN
 * @since 1.0.0-beta.18
 */

operatonDebugFrontend('Core', 'Frontend core module loading...');

// =============================================================================
// GLOBAL STATE MANAGEMENT
// =============================================================================

/**
 * Global processing lock to prevent duplicate operations
 */
window.operatonProcessingLock = window.operatonProcessingLock || {};

/**
 * Enhanced global state - better tracking without loops
 */
window.operatonInitialized = window.operatonInitialized || {
  forms: new Set(),
  globalInit: false,
  jQueryReady: false,
  initInProgress: false,
  performanceStats: {
    initializationAttempts: 0,
    successfulInits: 0,
    totalProcessingTime: 0,
    cacheHits: 0,
  },
};

/**
 * Core caching utilities for DOM queries and form configurations
 */
window.operatonCaches = window.operatonCaches || {
  domQueryCache: new Map(),
  formConfigCache: new Map(),
};

// =============================================================================
// CORE UTILITY FUNCTIONS
// =============================================================================

/**
 * Get cached DOM element with automatic cache management
 * @param {string} selector - jQuery selector
 * @param {number} maxAge - Maximum cache age in milliseconds
 * @returns {jQuery} Cached or fresh jQuery element
 */
window.getCachedElement = function(selector, maxAge = 5000) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Core', 'jQuery not available for getCachedElement');
    return $();
  }

  const cacheKey = `element_${selector}`;
  const cached = window.operatonCaches.domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < maxAge) {
    window.operatonInitialized.performanceStats.cacheHits++;
    return cached.element;
  }

  const element = $(selector);
  window.operatonCaches.domQueryCache.set(cacheKey, {
    element: element,
    timestamp: Date.now(),
  });

  return element;
};

/**
 * Get cached form configuration with automatic cache management
 * @param {number} formId - Gravity Forms form ID
 * @returns {Object|null} Form configuration object or null
 */
window.getFormConfigCached = function(formId) {
  const cacheKey = `config_${formId}`;

  if (window.operatonCaches.formConfigCache.has(cacheKey)) {
    window.operatonInitialized.performanceStats.cacheHits++;
    return window.operatonCaches.formConfigCache.get(cacheKey);
  }

  const configVar = `operaton_config_${formId}`;
  const config = window[configVar];

  if (config) {
    window.operatonCaches.formConfigCache.set(cacheKey, config);
  }

  return config;
};

/**
 * Get current page number for multi-page forms with caching
 * @param {number} formId - Gravity Forms form ID
 * @returns {number} Current page number (1-based)
 */
window.getCurrentPageCached = function(formId) {
  const urlParams = new URLSearchParams(window.location.search);
  const pageParam = urlParams.get('gf_page');
  if (pageParam) {
    return parseInt(pageParam);
  }

  const $ = window.jQuery || window.$;
  if ($) {
    const $form = window.getCachedElement(`#gform_${formId}`);
    const $pageField = $form.find(`input[name="gform_source_page_number_${formId}"]`);
    if ($pageField.length > 0) {
      return parseInt($pageField.val()) || 1;
    }
  }

  return 1;
};

/**
 * Clear DOM cache for specific form or all forms
 * @param {number|null} formId - Form ID to clear cache for, or null for all
 */
window.clearDOMCache = function(formId) {
  if (formId) {
    // Clear specific form caches
    const keys = Array.from(window.operatonCaches.domQueryCache.keys());
    keys.forEach(key => {
      if (key.includes(`${formId}`) || key.includes(`gform_${formId}`)) {
        window.operatonCaches.domQueryCache.delete(key);
      }
    });
    
    // Clear form config cache
    window.operatonCaches.formConfigCache.delete(`config_${formId}`);
    
    operatonDebugVerbose('Core', `Cleared DOM cache for form ${formId}`);
  } else {
    // Clear all caches
    window.operatonCaches.domQueryCache.clear();
    window.operatonCaches.formConfigCache.clear();
    operatonDebugVerbose('Core', 'Cleared all DOM caches');
  }
};

// =============================================================================
// CORE SYSTEM MANAGEMENT
// =============================================================================

/**
 * Reset the entire form system state
 * Used for debugging and emergency recovery
 */
window.resetFormSystem = function() {
  operatonDebugVerbose('Core', 'Resetting form system state...');

  // Clear state
  window.operatonInitialized.forms.clear();
  window.operatonInitialized.globalInit = false;
  window.operatonInitialized.initInProgress = false;

  // Clear progress flags
  Object.keys(window.operatonInitialized).forEach(key => {
    if (key.startsWith('init_')) {
      delete window.operatonInitialized[key];
    }
  });

  // Clear caches
  window.operatonCaches.domQueryCache.clear();
  window.operatonCaches.formConfigCache.clear();
  
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

  operatonDebugVerbose('Core', 'Form system reset complete');
};

// =============================================================================
// CORE DEBUGGING AND UTILITIES
// =============================================================================

/**
 * Global debugging function for core system inspection
 * @returns {Object} Current system state and statistics
 */
window.operatonDebugCore = function() {
  const stats = window.operatonInitialized.performanceStats;

  const debugInfo = {
    initializationState: window.operatonInitialized,
    performanceStats: stats,
    cacheStats: {
      domCache: window.operatonCaches.domQueryCache.size,
      configCache: window.operatonCaches.formConfigCache.size,
      buttonCache: window.operatonButtonManager?.buttonCache?.size || 0,
    },
    systemStatus: 'core-module-active',
  };

  operatonDebugVerbose('Core', 'Core Debug Info:', debugInfo);
  return debugInfo;
};

/**
 * Force cleanup utility for debugging
 */
window.operatonForceCleanup = window.resetFormSystem;

/**
 * Manual reinitialization utility for debugging
 */
window.operatonReinitialize = function() {
  operatonDebugVerbose('Core', 'MANUAL REINIT: Starting re-initialization');
  window.resetFormSystem();
  
  // Trigger re-detection after cleanup
  setTimeout(() => {
    if (typeof window.simplifiedFormDetection === 'function') {
      window.simplifiedFormDetection();
    }
  }, 500);
};

// =============================================================================
// MODULE INITIALIZATION FLAG
// =============================================================================

// Mark core module as loaded
window.operatonModulesLoaded = window.operatonModulesLoaded || {};
window.operatonModulesLoaded.core = true;

operatonDebugFrontend('Core', 'Frontend core module loaded successfully');
