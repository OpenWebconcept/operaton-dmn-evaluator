/**
 * OPTIMIZED: Enhanced Operaton DMN Frontend Script with Debounced Initialization
 *
 * PERFORMANCE OPTIMIZATIONS:
 * - Debounced form initialization with duplicate prevention
 * - Cached DOM queries and form detection
 * - Optimized event binding with delegation
 * - Smart state management with cleanup
 * - Reduced redundant processing cycles
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

console.log('🚀 Operaton DMN OPTIMIZED frontend script loading with debounced initialization...');

// =============================================================================
// OPTIMIZED GLOBAL STATE MANAGEMENT
// =============================================================================

/**
 * Enhanced global state with performance tracking
 */
window.operatonInitialized = window.operatonInitialized || {
  forms: new Set(),
  scripts: new Set(),
  globalInit: false,
  timers: {},
  jQueryReady: false,
  performanceStats: {
    initializationAttempts: 0,
    successfulInits: 0,
    duplicatePrevented: 0,
    totalProcessingTime: 0,
    cacheHits: 0,
  },
};

/**
 * OPTIMIZED: Debounced form initialization with stronger duplicate prevention
 */
const formInitializationTimeouts = new Map();
const initializationPromises = new Map();
const formConfigCache = new Map();
const domQueryCache = new Map();
let globalPerformanceTimer = null;

/**
 * CRITICAL: Define global functions FIRST for inline script compatibility
 * These functions include fallbacks in case caching isn't available yet
 */
window.showEvaluateButton = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for showEvaluateButton');
    return;
  }

  try {
    // Try cached version first
    if (typeof getCachedElement === 'function') {
      const $button = getCachedElement(`#operaton-evaluate-${formId}`);
      const $summary = getCachedElement(`#decision-flow-summary-${formId}`);

      console.log('✅ Showing evaluate button for form', formId);
      $button.addClass('operaton-show-button').show();
      $summary.removeClass('operaton-show-summary');
    } else {
      // Fallback to direct jQuery
      const $button = $(`#operaton-evaluate-${formId}`);
      const $summary = $(`#decision-flow-summary-${formId}`);

      console.log('✅ Showing evaluate button for form (fallback)', formId);
      $button.addClass('operaton-show-button').show();
      $summary.removeClass('operaton-show-summary');
    }
  } catch (error) {
    console.error('Error in showEvaluateButton:', error);
    // Final fallback
    $(`#operaton-evaluate-${formId}`).addClass('operaton-show-button').show();
    $(`#decision-flow-summary-${formId}`).removeClass('operaton-show-summary');
  }
};

window.showDecisionFlowSummary = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for showDecisionFlowSummary');
    return;
  }

  try {
    // Hide evaluate button
    if (typeof getCachedElement === 'function') {
      const $button = getCachedElement(`#operaton-evaluate-${formId}`);
      $button.removeClass('operaton-show-button');
    } else {
      $(`#operaton-evaluate-${formId}`).removeClass('operaton-show-button');
    }

    // Show decision flow summary container
    if (typeof getCachedElement === 'function') {
      const $summary = getCachedElement(`#decision-flow-summary-${formId}`);
      $summary.addClass('operaton-show-summary');
    } else {
      $(`#decision-flow-summary-${formId}`).addClass('operaton-show-summary');
    }

    // Delegate actual loading to decision-flow.js
    if (typeof window.loadDecisionFlowSummary === 'function') {
      console.log('📊 Delegating decision flow loading to decision-flow.js for form', formId);
      window.loadDecisionFlowSummary(formId);
    } else {
      console.log('📊 Decision flow manager not available for form', formId);
    }
  } catch (error) {
    console.error('Error in showDecisionFlowSummary:', error);
  }
};

window.hideAllElements = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for hideAllElements');
    return;
  }

  try {
    // Try to get current page and config (with fallback)
    let currentPage = 1;
    let targetPage = 2;

    if (typeof getCurrentPageCached === 'function') {
      currentPage = getCurrentPageCached(formId);
    } else {
      // Simple fallback page detection
      const urlParams = new URLSearchParams(window.location.search);
      const pageParam = urlParams.get('gf_page');
      if (pageParam) {
        currentPage = parseInt(pageParam);
      }
    }

    if (typeof getFormConfigCached === 'function') {
      const config = getFormConfigCached(formId);
      targetPage = config ? parseInt(config.evaluation_step) || 2 : 2;
    }

    // CHECK: Don't hide if we're on the target page
    if (currentPage === targetPage) {
      console.log('❌ Skipping hide - we are on target page', currentPage);
      return;
    }

    // Try cached version first
    if (typeof getCachedElement === 'function') {
      const $button = getCachedElement(`#operaton-evaluate-${formId}`);
      const $summary = getCachedElement(`#decision-flow-summary-${formId}`);

      console.log('❌ Hiding all elements for form', formId);
      $button.removeClass('operaton-show-button').hide();
      $summary.removeClass('operaton-show-summary');
    } else {
      // Fallback to direct jQuery
      const $button = $(`#operaton-evaluate-${formId}`);
      const $summary = $(`#decision-flow-summary-${formId}`);

      console.log('❌ Hiding all elements for form (fallback)', formId);
      $button.removeClass('operaton-show-button').hide();
      $summary.removeClass('operaton-show-summary');
    }
  } catch (error) {
    console.error('Error in hideAllElements:', error);
    // Final fallback
    $(`#operaton-evaluate-${formId}`).removeClass('operaton-show-button').hide();
    $(`#decision-flow-summary-${formId}`).removeClass('operaton-show-summary');
  }
};

// =============================================================================
// OPTIMIZED CACHING UTILITIES
// =============================================================================

/**
 * OPTIMIZED: Cached DOM element retrieval with automatic cleanup
 */
function getCachedElement(selector, maxAge = 5000) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for getCachedElement');
    return $();
  }

  const cacheKey = `element_${selector}`;
  const cached = domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < maxAge) {
    window.operatonInitialized.performanceStats.cacheHits++;
    return cached.element;
  }

  const element = $(selector);

  domQueryCache.set(cacheKey, {
    element: element,
    timestamp: Date.now(),
  });

  return element;
}

/**
 * OPTIMIZED: Cached form configuration retrieval
 */
function getFormConfigCached(formId) {
  const cacheKey = `config_${formId}`;

  if (formConfigCache.has(cacheKey)) {
    window.operatonInitialized.performanceStats.cacheHits++;
    return formConfigCache.get(cacheKey);
  }

  const configVar = `operaton_config_${formId}`;
  const config = window[configVar];

  if (config) {
    formConfigCache.set(cacheKey, config);
  }

  return config;
}

/**
 * OPTIMIZED: Cached current page detection
 */
function getCurrentPageCached(formId) {
  const cacheKey = `current_page_${formId}`;
  const cached = domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < 1000) {
    // 1 second cache
    return cached.element;
  }

  const currentPage = getCurrentPageSimple(formId);

  domQueryCache.set(cacheKey, {
    element: currentPage,
    timestamp: Date.now(),
  });

  return currentPage;
}

/**
 * Helper function for page detection
 */
function getCurrentPageSimple(formId) {
  const urlParams = new URLSearchParams(window.location.search);
  const pageParam = urlParams.get('gf_page');
  if (pageParam) {
    return parseInt(pageParam);
  }

  const $ = window.jQuery || window.$;
  if ($) {
    const $form = getCachedElement(`#gform_${formId}`);
    const $pageField = $form.find(`input[name="gform_source_page_number_${formId}"]`);
    if ($pageField.length > 0) {
      return parseInt($pageField.val()) || 1;
    }
  }

  return 1;
}

/**
 * OPTIMIZED: Clear DOM cache for specific form or all
 */
function clearDOMCache(formId = null) {
  if (formId) {
    const keysToDelete = Array.from(domQueryCache.keys()).filter(
      key =>
        key.includes(`_${formId}`) || key.includes(`#gform_${formId}`) || key.includes(`#operaton-evaluate-${formId}`)
    );
    keysToDelete.forEach(key => domQueryCache.delete(key));
  } else {
    domQueryCache.clear();
  }
}

// =============================================================================
// ENHANCED BUTTON MANAGER WITH CACHING
// =============================================================================

window.operatonButtonManager = window.operatonButtonManager || {
  originalTexts: new Map(),
  buttonCache: new Map(),

  /**
   * OPTIMIZED: Get cached button element
   */
  getCachedButton: function (formId) {
    const cacheKey = `button_${formId}`;
    const cached = this.buttonCache.get(cacheKey);

    if (cached && Date.now() - cached.timestamp < 3000) {
      return cached.button;
    }

    const $button = getCachedElement(`#operaton-evaluate-${formId}`);

    this.buttonCache.set(cacheKey, {
      button: $button,
      timestamp: Date.now(),
    });

    return $button;
  },

  /**
   * OPTIMIZED: Store original button text with validation
   */
  storeOriginalText: function ($button, formId) {
    const buttonId = `form_${formId}`;

    if (this.originalTexts.has(buttonId)) {
      return this.originalTexts.get(buttonId);
    }

    let originalText = $button.attr('data-original-text') || $button.val() || $button.attr('value') || 'Evaluate';

    // Clean up the text (remove evaluation states)
    if (
      originalText.includes('Evaluation') ||
      originalText.includes('Evaluating') ||
      originalText.includes('progress')
    ) {
      originalText = 'Evaluate';
    }

    this.originalTexts.set(buttonId, originalText);
    $button.attr('data-original-text', originalText);

    console.log('📝 ButtonManager: Stored original text for form', formId + ':', originalText);
    return originalText;
  },

  /**
   * OPTIMIZED: Get original text with fallback chain
   */
  getOriginalText: function (formId) {
    const buttonId = `form_${formId}`;
    let storedText = this.originalTexts.get(buttonId);

    if (!storedText) {
      const $button = this.getCachedButton(formId);
      if ($button.length) {
        storedText = $button.attr('data-original-text') || 'Evaluate';
        this.originalTexts.set(buttonId, storedText);
      } else {
        storedText = 'Evaluate';
      }
    }

    return storedText;
  },

  /**
   * OPTIMIZED: Set evaluating state with atomic operations
   */
  setEvaluatingState: function ($button, formId) {
    this.storeOriginalText($button, formId);
    $button.val('Evaluating...').prop('disabled', true).addClass('operaton-evaluating');
    console.log('🔄 ButtonManager: Set evaluating state for form', formId);
  },

  /**
   * OPTIMIZED: Restore original state with multiple fallback attempts
   */
  restoreOriginalState: function ($button, formId) {
    const originalText = this.getOriginalText(formId);

    console.log('🔄 ButtonManager: Restoring button for form', formId, 'to:', originalText);

    // Primary restoration
    $button
      .val(originalText)
      .prop('value', originalText)
      .attr('value', originalText)
      .prop('disabled', false)
      .removeClass('operaton-evaluating');

    // Clear button cache to force refresh
    this.buttonCache.delete(`button_${formId}`);

    // Failsafe restoration
    setTimeout(() => {
      $button.val(originalText).prop('disabled', false);
    }, 100);

    // Final emergency fallback
    setTimeout(() => {
      if ($button.val() !== originalText || $button.prop('disabled')) {
        $button.val(originalText).prop('disabled', false);
        console.log('🆘 ButtonManager: Emergency restoration triggered for form', formId);
      }
    }, 1000);
  },

  /**
   * Clear button cache for form
   */
  clearCache: function (formId) {
    if (formId) {
      this.buttonCache.delete(`button_${formId}`);
    } else {
      this.buttonCache.clear();
    }
  },
};

// =============================================================================
// OPTIMIZED DEBOUNCED INITIALIZATION SYSTEM
// =============================================================================

/**
 * OPTIMIZED: Debounced form initialization with promise-based duplicate prevention
 */
function debouncedFormInitialization(formId) {
  formId = parseInt(formId);

  // Start performance tracking
  if (!globalPerformanceTimer) {
    globalPerformanceTimer = performance.now();
  }

  window.operatonInitialized.performanceStats.initializationAttempts++;

  // Check if already initialized
  if (window.operatonInitialized.forms.has(formId)) {
    window.operatonInitialized.performanceStats.duplicatePrevented++;
    console.log('🔄 Form', formId, 'already initialized, skipping duplicate');
    return Promise.resolve();
  }

  // Clear existing timeout
  if (formInitializationTimeouts.has(formId)) {
    clearTimeout(formInitializationTimeouts.get(formId));
    formInitializationTimeouts.delete(formId);
  }

  // Return existing promise if initialization is in progress
  if (initializationPromises.has(formId)) {
    console.log('⏳ Form', formId, 'initialization in progress, returning existing promise');
    return initializationPromises.get(formId);
  }

  // Create new initialization promise
  const initPromise = new Promise((resolve, reject) => {
    const timeoutId = setTimeout(() => {
      try {
        // Clean up tracking
        formInitializationTimeouts.delete(formId);
        initializationPromises.delete(formId);

        // Double-check initialization state
        if (!window.operatonInitialized.forms.has(formId)) {
          console.log('🚀 DEBOUNCED: Initializing form', formId);

          const startTime = performance.now();
          initializeFormEvaluation(formId);
          const endTime = performance.now();

          window.operatonInitialized.performanceStats.totalProcessingTime += endTime - startTime;
          window.operatonInitialized.performanceStats.successfulInits++;

          resolve(formId);
        } else {
          window.operatonInitialized.performanceStats.duplicatePrevented++;
          resolve(formId);
        }
      } catch (error) {
        console.error('❌ Form initialization error for form', formId, ':', error);
        reject(error);
      }
    }, 300); // Debounce delay

    formInitializationTimeouts.set(formId, timeoutId);
  });

  initializationPromises.set(formId, initPromise);
  return initPromise;
}

/**
 * OPTIMIZED: Batch form detection with intelligent caching
 */
function batchFormDetection() {
  const $ = window.jQuery || window.$;
  if (!$) return;

  console.log('🔍 Running OPTIMIZED batch form detection...');

  const detectedForms = new Set();
  const startTime = performance.now();

  // Use cached form elements if available
  let $forms = domQueryCache.get('all_gravity_forms');
  if (!$forms || Date.now() - $forms.timestamp > 5000) {
    $forms = $('form[id^="gform_"]');
    domQueryCache.set('all_gravity_forms', {
      element: $forms,
      timestamp: Date.now(),
    });
  } else {
    $forms = $forms.element;
    window.operatonInitialized.performanceStats.cacheHits++;
  }

  const initPromises = [];

  $forms.each(function () {
    const $form = $(this);
    const formId = parseInt($form.attr('id').replace('gform_', ''));

    if (formId && !isNaN(formId)) {
      detectedForms.add(formId);

      // Check if form has DMN configuration (cached)
      const config = getFormConfigCached(formId);
      if (config) {
        console.log('🎯 DMN-enabled form detected:', formId);
        initPromises.push(debouncedFormInitialization(formId));
      }
    }
  });

  // Wait for all initializations to complete
  if (initPromises.length > 0) {
    Promise.allSettled(initPromises).then(results => {
      const successful = results.filter(r => r.status === 'fulfilled').length;
      const failed = results.filter(r => r.status === 'rejected').length;

      const endTime = performance.now();
      console.log(
        `✅ Batch detection complete: ${successful} successful, ${failed} failed in ${(endTime - startTime).toFixed(
          2
        )}ms`
      );

      // Log performance stats
      logPerformanceStats();
    });
  }

  return detectedForms;
}

/**
 * OPTIMIZED: Smart form detection with multiple strategies
 */
function smartFormDetection() {
  const $ = window.jQuery || window.$;
  if (!$) return;

  // Strategy 1: Direct detection
  batchFormDetection();

  // Strategy 2: Mutation observer for dynamic forms
  if (window.MutationObserver) {
    const observer = new MutationObserver(mutations => {
      let shouldDetect = false;

      mutations.forEach(mutation => {
        mutation.addedNodes.forEach(node => {
          if (node.nodeType === 1) {
            // Element node
            if (node.tagName === 'FORM' && node.id && node.id.startsWith('gform_')) {
              shouldDetect = true;
            } else if ($(node).find('form[id^="gform_"]').length > 0) {
              shouldDetect = true;
            }
          }
        });
      });

      if (shouldDetect) {
        console.log('🔄 New form detected via mutation observer');
        setTimeout(batchFormDetection, 100);
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }
}

// =============================================================================
// OPTIMIZED MAIN INITIALIZATION FUNCTIONS
// =============================================================================

/**
 * OPTIMIZED: Enhanced form initialization with strict duplicate prevention
 */
function initializeFormEvaluation(formId) {
  formId = parseInt(formId);

  // Check if already initialized (double-check)
  if (window.operatonInitialized.forms.has(formId)) {
    console.log('🔄 Form', formId, 'already initialized at evaluation level');
    return;
  }

  const config = getFormConfigCached(formId);
  if (!config) {
    console.log('❌ No configuration found for form:', formId);
    return;
  }

  console.log('=== OPTIMIZED INITIALIZING FORM', formId, '===');

  // Mark as initializing immediately
  window.operatonInitialized.forms.add(formId);

  try {
    // Bind events with optimized selectors
    bindEvaluationEventsOptimized(formId);
    bindNavigationEventsOptimized(formId);
    bindInputChangeListenersOptimized(formId);

    // Initialize decision flow summary if enabled (delegated to decision-flow.js)
    if (config.show_decision_flow && typeof window.initializeDecisionFlowForForm === 'function') {
      window.initializeDecisionFlowForForm(formId, config);
    }

    // Clear any existing results when form initializes
    setTimeout(() => {
      clearResultFieldWithMessage(formId, 'Form initialized');
    }, 200);

    console.log('=== FORM', formId, 'INITIALIZATION COMPLETE ===');
  } catch (error) {
    console.error('❌ Error initializing form', formId, ':', error);
    // Remove from initialized set if initialization failed
    window.operatonInitialized.forms.delete(formId);
  }
}

/**
 * OPTIMIZED: Enhanced waiting mechanism with timeout and retry logic
 */
function waitForOperatonAjax(callback, maxAttempts = 50) {
  let attempts = 0;

  function check() {
    attempts++;

    if (typeof window.operaton_ajax !== 'undefined') {
      console.log('✅ operaton_ajax found after', attempts, 'attempts');
      callback();
    } else if (attempts < maxAttempts) {
      if (attempts % 10 === 0) {
        console.log('⏳ Still waiting for operaton_ajax... attempt', attempts);
      }
      setTimeout(check, 100);
    } else {
      console.error('❌ operaton_ajax not found after', maxAttempts, 'attempts');
      createEmergencyOperatonAjax();
      callback();
    }
  }
  check();
}

/**
 * Emergency operaton_ajax fallback
 */
function createEmergencyOperatonAjax() {
  if (typeof window.operaton_ajax === 'undefined') {
    console.log('🆘 Creating emergency operaton_ajax fallback');
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
      },
      emergency_mode: true,
    };
  }
}

/**
 * OPTIMIZED: Initialize evaluation system with smart hook registration
 */
function initOperatonDMN() {
  console.log('🚀 Starting OPTIMIZED Operaton DMN initialization...');

  // Hook into Gravity Forms events if available (optimized)
  if (typeof gform !== 'undefined' && gform.addAction) {
    // Remove any existing actions first
    if (gform.removeAction) {
      gform.removeAction('gform_post_render', 'operaton_form_render');
    }

    gform.addAction(
      'gform_post_render',
      function (formId) {
        console.log('📋 Gravity Form rendered via gform action, form:', formId);
        // Clear cache for this form
        clearDOMCache(formId);
        debouncedFormInitialization(formId);
      },
      10,
      'operaton_form_render'
    );

    console.log('✅ Hooked into gform_post_render action');
  }

  // Smart form detection with batching
  setTimeout(() => {
    smartFormDetection();
  }, 100);

  // Periodic cleanup and re-detection (less frequent)
  setInterval(() => {
    cleanupCaches();
    if (document.querySelectorAll('form[id^="gform_"]').length > window.operatonInitialized.forms.size) {
      console.log('🔄 Periodic re-detection triggered');
      smartFormDetection();
    }
  }, 5000);
}

/**
 * OPTIMIZED: Cleanup caches to prevent memory leaks
 */
function cleanupCaches() {
  const now = Date.now();
  const maxAge = 10000; // 10 seconds

  // Clean DOM cache
  for (const [key, value] of domQueryCache.entries()) {
    if (now - value.timestamp > maxAge) {
      domQueryCache.delete(key);
    }
  }

  // Clean button cache
  for (const [key, value] of window.operatonButtonManager.buttonCache.entries()) {
    if (now - value.timestamp > maxAge) {
      window.operatonButtonManager.buttonCache.delete(key);
    }
  }

  // Clean form config cache (less frequent)
  if (formConfigCache.size > 50) {
    formConfigCache.clear();
  }
}

/**
 * Performance statistics logging
 */
function logPerformanceStats() {
  if (window.operaton_ajax && window.operaton_ajax.debug) {
    const stats = window.operatonInitialized.performanceStats;
    console.log('📊 PERFORMANCE STATS:', {
      initializationAttempts: stats.initializationAttempts,
      successfulInits: stats.successfulInits,
      duplicatePrevented: stats.duplicatePrevented,
      cacheHits: stats.cacheHits,
      totalProcessingTime: `${stats.totalProcessingTime.toFixed(2)}ms`,
      efficiency: `${((stats.duplicatePrevented / stats.initializationAttempts) * 100).toFixed(1)}%`,
    });
  }
}

// =============================================================================
// OPTIMIZED EVENT BINDING FUNCTIONS
// =============================================================================

/**
 * OPTIMIZED: Bind evaluation button events with efficient delegation
 */
function bindEvaluationEventsOptimized(formId) {
  const selector = `.operaton-evaluate-btn[data-form-id="${formId}"]`;

  // Use single delegated event handler
  $(document).off(`click.operaton-${formId}`, selector);
  $(document).on(`click.operaton-${formId}`, selector, function (e) {
    e.preventDefault();
    console.log('🎯 Button clicked for form:', formId);
    handleEvaluateClick($(this));
  });

  console.log('✅ Optimized event handler bound for form:', formId);
}

/**
 * OPTIMIZED: Bind navigation events with smart caching
 */
function bindNavigationEventsOptimized(formId) {
  const $form = getCachedElement(`#gform_${formId}`);

  $form.off(`click.operaton-nav-${formId}`);
  $form.on(
    `click.operaton-nav-${formId}`,
    '.gform_previous_button, input[value="Previous"], button:contains("Previous")',
    function () {
      console.log('⬅️ Previous button clicked for form:', formId);
      clearResultFieldWithMessage(formId, 'Previous button clicked');
      clearDOMCache(formId); // Clear cache on navigation
    }
  );

  // Optimized Gravity Forms page loaded event
  if (typeof gform !== 'undefined' && gform.addAction) {
    if (gform.removeAction) {
      gform.removeAction('gform_page_loaded', `operaton_clear_${formId}`);
    }

    gform.addAction(
      'gform_page_loaded',
      function (loadedFormId, currentPage) {
        if (loadedFormId == formId) {
          console.log('📄 Form page loaded for form:', formId, 'page:', currentPage);
          clearDOMCache(formId); // Clear cache on page change
          setTimeout(() => {
            clearResultFieldWithMessage(formId, `Page loaded: ${currentPage}`);
          }, 300);
        }
      },
      10,
      `operaton_clear_${formId}`
    );
  }
}

/**
 * OPTIMIZED: Bind input change listeners with smart field detection
 */
function bindInputChangeListenersOptimized(formId) {
  const $form = getCachedElement(`#gform_${formId}`);
  const config = getFormConfigCached(formId);

  if (!config || !config.field_mappings) return;

  console.log('🔗 Binding optimized input change listeners for form:', formId);

  // Use single delegated handler for all mapped fields
  const fieldSelectors = [];

  Object.entries(config.field_mappings).forEach(([dmnVariable, mapping]) => {
    const fieldId = mapping.field_id;
    fieldSelectors.push(
      `#input_${formId}_${fieldId}`,
      `input[name="input_${formId}_${fieldId}"]`,
      `select[name="input_${formId}_${fieldId}"]`,
      `input[name="input_${fieldId}"]`
    );
  });

  const combinedSelector = fieldSelectors.join(', ');

  $form.off(`change.operaton-${formId}`);
  $form.on(`change.operaton-${formId}`, combinedSelector, function () {
    console.log('🔄 Input field changed:', $(this).attr('name'), 'New value:', $(this).val());

    // Clear caches related to this form
    clearDOMCache(formId);

    setTimeout(() => {
      clearResultFieldWithMessage(formId, 'Input changed - result cleared');
    }, 100);
  });
}

// =============================================================================
// OPTIMIZED FORM EVALUATION FUNCTIONS
// =============================================================================

/**
 * OPTIMIZED: Handle evaluate button click with enhanced error handling
 */
function handleEvaluateClick($button) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.error('❌ jQuery not available for handleEvaluateClick');
    showError('System error: jQuery not available. Please refresh the page.');
    return;
  }

  const formId = $button.data('form-id');
  const configId = $button.data('config-id');

  console.log('🎯 OPTIMIZED evaluate button clicked for form:', formId, 'config:', configId);

  const config = getFormConfigCached(formId);
  if (!config) {
    console.error('❌ Configuration not found for form:', formId);
    showError('Configuration error. Please contact the administrator.');
    return;
  }

  const fieldMappings = config.field_mappings;

  // Use centralized button manager
  window.operatonButtonManager.storeOriginalText($button, formId);

  // Force radio button synchronization before validation
  forceSyncRadioButtons(formId);

  setTimeout(() => {
    continueEvaluation();
  }, 100);

  function continueEvaluation() {
    if (!validateForm(formId)) {
      showError('Please fill in all required fields before evaluation.');
      return;
    }

    // Collect form data with optimized field access
    const formData = {};
    let hasRequiredData = true;
    const missingFields = [];

    Object.entries(fieldMappings).forEach(([dmnVariable, mapping]) => {
      const fieldId = mapping.field_id;
      console.log('🔍 Processing variable:', dmnVariable, 'Field ID:', fieldId);

      let value = getGravityFieldValueOptimized(formId, fieldId);
      console.log('📥 Found raw value for field', fieldId + ':', value);

      // Handle date field conversions
      if (
        dmnVariable.toLowerCase().includes('datum') ||
        dmnVariable.toLowerCase().includes('date') ||
        ['dagVanAanvraag', 'geboortedatumAanvrager', 'geboortedatumPartner'].includes(dmnVariable)
      ) {
        if (value !== null && value !== '' && value !== undefined) {
          value = convertDateFormat(value, dmnVariable);
        }
      }

      console.log('✅ Processed value for', dmnVariable + ':', value);
      formData[dmnVariable] = value;
    });

    // Apply conditional logic for partner-related fields
    const isAlleenstaand = formData['aanvragerAlleenstaand'];
    console.log('👤 User is single (alleenstaand):', isAlleenstaand);

    if (isAlleenstaand === 'true' || isAlleenstaand === true) {
      console.log('👤 User is single, setting geboortedatumPartner to null');
      formData['geboortedatumPartner'] = null;
    }

    // Validate required fields
    Object.entries(fieldMappings).forEach(([dmnVariable, mapping]) => {
      const value = formData[dmnVariable];

      // Skip validation for partner fields when user is single
      if (isAlleenstaand === 'true' || isAlleenstaand === true) {
        if (dmnVariable === 'geboortedatumPartner') {
          return;
        }
      }

      if (value === null || value === '' || value === undefined) {
        hasRequiredData = false;
        missingFields.push(`${dmnVariable} (field ID: ${mapping.field_id})`);
      } else {
        if (!validateFieldType(value, mapping.type)) {
          showError(`Invalid data type for field ${dmnVariable}. Expected: ${mapping.type}`);
          return false;
        }
      }
    });

    if (!hasRequiredData) {
      showError(`Please fill in all required fields: ${missingFields.join(', ')}`);
      return;
    }

    // Use centralized button manager for evaluating state
    window.operatonButtonManager.setEvaluatingState($button, formId);

    // Check if operaton_ajax is available
    if (typeof window.operaton_ajax === 'undefined') {
      console.error('❌ operaton_ajax not available');
      showError('System error: AJAX configuration not loaded. Please refresh the page.');
      window.operatonButtonManager.restoreOriginalState($button, formId);
      return;
    }

    console.log('🚀 Making AJAX call to:', window.operaton_ajax.url);

    // Make AJAX call with optimized error handling
    const $ = window.jQuery || window.$;
    if (!$) {
      console.error('❌ jQuery not available for AJAX call');
      showError('System error: jQuery not available. Please refresh the page.');
      window.operatonButtonManager.restoreOriginalState($button, formId);
      return;
    }

    $.ajax({
      url: window.operaton_ajax.url,
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({
        config_id: configId,
        form_data: formData,
      }),
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.operaton_ajax.nonce);
      },
      success: function (response) {
        console.log('✅ AJAX success:', response);

        if (response.success && response.results) {
          console.log('📊 Results received:', response.results);

          let populatedCount = 0;
          const resultSummary = [];

          Object.entries(response.results).forEach(([dmnResultField, resultData]) => {
            const resultValue = resultData.value;
            const fieldId = resultData.field_id;

            console.log('🔄 Processing result:', dmnResultField, 'Value:', resultValue, 'Field ID:', fieldId);

            let $resultField = null;

            if (fieldId) {
              $resultField = findFieldOnCurrentPageOptimized(formId, fieldId);
            } else {
              $resultField = findResultFieldOnCurrentPageOptimized(formId);
            }

            if ($resultField && $resultField.length > 0) {
              $resultField.val(resultValue);
              $resultField.trigger('change');
              $resultField.trigger('input');

              populatedCount++;
              resultSummary.push(`${dmnResultField}: ${resultValue}`);

              highlightField($resultField);
              console.log('✅ Populated field', fieldId, 'with result:', resultValue);
            } else {
              console.warn('⚠️ No field found for result:', dmnResultField, 'Field ID:', fieldId);
            }
          });

          // Store process instance ID if provided
          if (response.process_instance_id) {
            storeProcessInstanceId(formId, response.process_instance_id);
            console.log('💾 Stored process instance ID:', response.process_instance_id);
          }

          if (populatedCount > 0) {
            let message = `✅ Results populated (${populatedCount}): ${resultSummary.join(', ')}`;

            if (response.process_instance_id && config.show_decision_flow) {
              message += '\n\n📋 Complete the form to see the detailed decision flow summary on the final page.';

              // Notify decision flow manager about new process instance
              if (typeof window.OperatonDecisionFlow !== 'undefined') {
                window.OperatonDecisionFlow.clearCache();
              }
            }

            showSuccessNotification(message);
          } else {
            showError('No result fields found on this page to populate.');
          }

          // Store evaluation metadata
          const currentPage = getCurrentPageCached(formId);
          const evalData = {
            results: response.results,
            page: currentPage,
            timestamp: Date.now(),
            formData: formData,
            processInstanceId: response.process_instance_id || null,
          };

          if (typeof Storage !== 'undefined') {
            sessionStorage.setItem(`operaton_dmn_eval_data_${formId}`, JSON.stringify(evalData));
          }
        } else {
          console.error('❌ Invalid response structure:', response);
          showError('No results received from evaluation.');
        }
      },
      error: function (xhr, status, error) {
        console.error('❌ AJAX Error:', error);
        console.error('XHR Status:', xhr.status);
        console.error('XHR Response:', xhr.responseText);

        let errorMessage = 'Error during evaluation. Please try again.';

        if (xhr.status === 0) {
          errorMessage = 'Connection error. Please check your internet connection and try again.';
        } else if (xhr.status === 400) {
          try {
            const errorResponse = JSON.parse(xhr.responseText);
            if (errorResponse.message) {
              errorMessage = errorResponse.message;
            }
          } catch (e) {
            errorMessage = 'Bad request. Please check your form data.';
          }
        } else if (xhr.status === 404) {
          errorMessage = 'Evaluation service not found. Please contact support.';
        } else if (xhr.status === 500) {
          errorMessage = 'Server error occurred during evaluation. Please try again.';
        }

        showError(errorMessage);
      },
      complete: function () {
        // Always use centralized button manager for restoration
        window.operatonButtonManager.restoreOriginalState($button, formId);
      },
    });
  }
}

// =============================================================================
// OPTIMIZED UTILITY FUNCTIONS
// =============================================================================

/**
 * OPTIMIZED: Store process instance ID with caching
 */
function storeProcessInstanceId(formId, processInstanceId) {
  if (typeof Storage !== 'undefined') {
    sessionStorage.setItem(`operaton_process_${formId}`, processInstanceId);
  }
  window[`operaton_process_${formId}`] = processInstanceId;
  console.log('💾 Stored process instance ID for form', formId + ':', processInstanceId);
}

/**
 * OPTIMIZED: Get stored process instance ID with fallback chain
 */
function getStoredProcessInstanceId(formId) {
  if (window[`operaton_process_${formId}`]) {
    return window[`operaton_process_${formId}`];
  }

  if (typeof Storage !== 'undefined') {
    const processId = sessionStorage.getItem(`operaton_process_${formId}`);
    if (processId) {
      return processId;
    }

    const evalData = sessionStorage.getItem(`operaton_dmn_eval_data_${formId}`);
    if (evalData) {
      try {
        const parsed = JSON.parse(evalData);
        if (parsed.processInstanceId) {
          return parsed.processInstanceId;
        }
      } catch (e) {
        console.error('❌ Error parsing evaluation data:', e);
      }
    }
  }

  return null;
}

/**
 * OPTIMIZED: Clear result field with message and cache cleanup
 */
function clearResultFieldWithMessage(formId, reason) {
  console.log('🧹 Clearing result field for form:', formId, 'Reason:', reason);

  const $resultField = findResultFieldOnCurrentPageOptimized(formId);
  if ($resultField && $resultField.length > 0) {
    const currentValue = $resultField.val();
    if (currentValue && currentValue.trim() !== '') {
      console.log('🧹 Clearing result field value:', currentValue);
      $resultField.val('');
      $resultField.trigger('change');
      $resultField.trigger('input');
    }
  }

  clearStoredResults(formId);
  clearDOMCache(formId);
  // Clear decision flow cache when results are cleared
  if (typeof window.OperatonDecisionFlow !== 'undefined') {
    window.OperatonDecisionFlow.clearCache();
  }
}

/**
 * OPTIMIZED: Clear stored results with comprehensive cleanup
 */
function clearStoredResults(formId) {
  if (typeof Storage !== 'undefined') {
    const keysToRemove = [
      `operaton_dmn_result_${formId}`,
      `operaton_dmn_eval_page_${formId}`,
      `operaton_dmn_data_${formId}`,
      `operaton_dmn_eval_data_${formId}`,
      `operaton_process_${formId}`,
    ];

    keysToRemove.forEach(key => sessionStorage.removeItem(key));
  }

  delete window[`operaton_process_${formId}`];
  console.log('🧹 Cleared all stored results and process data for form:', formId);
}

/**
 * OPTIMIZED: Get current page with enhanced caching
 */
function getCurrentPage(formId) {
  return getCurrentPageCached(formId);
}

/**
 * OPTIMIZED: Get total pages with caching
 */
function getTotalPages(formId) {
  const cacheKey = `total_pages_${formId}`;
  const cached = domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < 10000) {
    return cached.element;
  }

  const $form = getCachedElement(`#gform_${formId}`);
  let totalPages = 1;

  $form.find('.gfield').each(function () {
    if ($(this).hasClass('gfield_page')) {
      totalPages++;
    }
  });

  domQueryCache.set(cacheKey, {
    element: totalPages,
    timestamp: Date.now(),
  });

  return totalPages;
}

/**
 * OPTIMIZED: Convert date format with validation
 */
function convertDateFormat(dateStr, fieldName) {
  if (!dateStr || dateStr === null) {
    return null;
  }

  console.log('📅 Converting date for field:', fieldName, 'Input:', dateStr);

  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    return dateStr;
  }

  if (/^\d{2}-\d{2}-\d{4}$/.test(dateStr)) {
    const parts = dateStr.split('-');
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
  }

  if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
    const parts = dateStr.split('/');
    return `${parts[2]}-${parts[0]}-${parts[1]}`;
  }

  try {
    const date = new Date(dateStr);
    if (!isNaN(date.getTime())) {
      return (
        date.getFullYear() +
        '-' +
        String(date.getMonth() + 1).padStart(2, '0') +
        '-' +
        String(date.getDate()).padStart(2, '0')
      );
    }
  } catch (e) {
    console.error('❌ Error parsing date:', dateStr, e);
  }

  return dateStr;
}

/**
 * OPTIMIZED: Find field on current page with smart caching
 */
function findFieldOnCurrentPageOptimized(formId, fieldId) {
  const cacheKey = `field_${formId}_${fieldId}`;
  const cached = domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < 3000) {
    return cached.element;
  }

  const $form = getCachedElement(`#gform_${formId}`);

  const selectors = [
    `#input_${formId}_${fieldId}`,
    `input[name="input_${formId}_${fieldId}"]`,
    `select[name="input_${formId}_${fieldId}"]`,
    `textarea[name="input_${formId}_${fieldId}"]`,
  ];

  for (const selector of selectors) {
    const $field = $form.find(`${selector}:visible`);
    if ($field.length > 0) {
      domQueryCache.set(cacheKey, {
        element: $field.first(),
        timestamp: Date.now(),
      });
      return $field.first();
    }
  }

  return null;
}

/**
 * OPTIMIZED: Find result field on current page with intelligent detection
 */
function findResultFieldOnCurrentPageOptimized(formId) {
  const cacheKey = `result_field_${formId}`;
  const cached = domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < 3000) {
    return cached.element;
  }

  const $form = getCachedElement(`#gform_${formId}`);
  const config = getFormConfigCached(formId);

  if (config && config.result_display_field) {
    const selectors = [
      `#input_${formId}_${config.result_display_field}`,
      `input[name="input_${formId}_${config.result_display_field}"]`,
      `select[name="input_${formId}_${config.result_display_field}"]`,
      `textarea[name="input_${formId}_${config.result_display_field}"]`,
    ];

    for (const selector of selectors) {
      const $field = $form.find(`${selector}:visible`);
      if ($field.length > 0) {
        domQueryCache.set(cacheKey, {
          element: $field.first(),
          timestamp: Date.now(),
        });
        return $field.first();
      }
    }
  }

  // Fallback detection strategies (cached)
  const detectionStrategies = [
    () =>
      $form
        .find('label:visible')
        .filter(function () {
          const text = $(this).text().toLowerCase().trim();
          return text === 'desired dish' || text === 'result' || text === 'desireddish';
        })
        .closest('.gfield')
        .find('input:visible, select:visible, textarea:visible')
        .first(),

    () =>
      $form
        .find('label:visible')
        .filter(function () {
          const text = $(this).text().toLowerCase();
          return (text.includes('desired') && text.includes('dish')) || text.includes('result');
        })
        .closest('.gfield')
        .find('input:visible, select:visible, textarea:visible')
        .first(),

    () =>
      $form
        .find(
          'input:visible[name*="dish"], input:visible[id*="dish"], select:visible[name*="dish"], select:visible[id*="dish"], textarea:visible[name*="dish"], textarea:visible[id*="dish"]'
        )
        .first(),

    () =>
      $form
        .find(
          'input:visible[name*="result"], input:visible[id*="result"], select:visible[name*="result"], select:visible[id*="result"], textarea:visible[name*="result"], textarea:visible[id*="result"]'
        )
        .first(),
  ];

  for (const strategy of detectionStrategies) {
    const $field = strategy();
    if ($field && $field.length > 0) {
      domQueryCache.set(cacheKey, {
        element: $field,
        timestamp: Date.now(),
      });
      return $field;
    }
  }

  return null;
}

/**
 * OPTIMIZED: Get Gravity field value with comprehensive field type handling
 */
function getGravityFieldValueOptimized(formId, fieldId) {
  const $form = getCachedElement(`#gform_${formId}`);
  let value = null;

  const standardSelectors = [
    `#input_${formId}_${fieldId}`,
    `input[name="input_${formId}_${fieldId}"]`,
    `select[name="input_${formId}_${fieldId}"]`,
    `textarea[name="input_${formId}_${fieldId}"]`,
  ];

  for (const selector of standardSelectors) {
    const $field = $form.find(selector);
    if ($field.length > 0) {
      value = getFieldValue($field);
      if (value !== null && value !== '') {
        return value;
      }
    }
  }

  // Check for custom radio values
  value = findCustomRadioValueOptimized(formId, fieldId);
  if (value !== null) {
    return value;
  }

  // Standard radio button check
  const $radioChecked = $form.find(`input[name="input_${fieldId}"]:checked`);
  if ($radioChecked.length > 0) {
    return $radioChecked.val();
  }

  // Checkbox check
  const $checkboxChecked = $form.find(`input[name^="input_${fieldId}"]:checked`);
  if ($checkboxChecked.length > 0) {
    const checkboxValues = [];
    $checkboxChecked.each(function () {
      checkboxValues.push($(this).val());
    });
    return checkboxValues.length === 1 ? checkboxValues[0] : checkboxValues.join(',');
  }

  return null;
}

/**
 * OPTIMIZED: Find custom radio value with enhanced detection
 */
function findCustomRadioValueOptimized(formId, fieldId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for findCustomRadioValueOptimized');
    return null;
  }

  const $form = getCachedElement(`#gform_${formId}`);

  const $hiddenField = $form.find(`#input_${formId}_${fieldId}`);
  if ($hiddenField.length > 0) {
    const $fieldContainer = $hiddenField.closest('.gfield');
    if ($fieldContainer.length > 0) {
      const fieldLabel = $fieldContainer.find('label').first().text().toLowerCase();
      const possibleRadioNames = generatePossibleRadioNames(fieldLabel, fieldId);

      for (const radioName of possibleRadioNames) {
        const $radioChecked = $(`input[name="${radioName}"]:checked`);
        if ($radioChecked.length > 0) {
          const value = $radioChecked.val();

          if ($hiddenField.val() !== value) {
            $hiddenField.val(value);
            $hiddenField.trigger('change');
          }

          return value;
        }
      }
    }
  }

  // Check using DMN variable name (optimized)
  const config = getFormConfigCached(formId);
  if (config && config.field_mappings) {
    let targetDmnVariable = null;
    Object.entries(config.field_mappings).forEach(([dmnVariable, mapping]) => {
      if (mapping.field_id == fieldId) {
        targetDmnVariable = dmnVariable;
      }
    });

    if (targetDmnVariable) {
      const $radioChecked = $(`input[type="radio"][name="${targetDmnVariable}"]:checked`);
      if ($radioChecked.length > 0) {
        const value = $radioChecked.val();

        const $hiddenField = $form.find(`#input_${formId}_${fieldId}`);
        if ($hiddenField.length > 0 && $hiddenField.val() !== value) {
          $hiddenField.val(value);
          $hiddenField.trigger('change');
        }

        return value;
      }
    }
  }

  return null;
}

/**
 * Generate possible radio names
 */
function generatePossibleRadioNames(fieldLabel, fieldId) {
  const possibilities = [];

  if (fieldLabel) {
    const cleanLabel = fieldLabel
      .toLowerCase()
      .replace(/[^a-z0-9\s]/g, '')
      .replace(/\s+/g, '')
      .trim();

    if (cleanLabel) {
      possibilities.push(cleanLabel);
      possibilities.push(`aanvrager${cleanLabel.charAt(0).toUpperCase() + cleanLabel.slice(1)}`);
    }
  }

  possibilities.push(`field_${fieldId}`);
  possibilities.push(`input_${fieldId}`);

  return possibilities;
}

/**
 * OPTIMIZED: Force sync radio buttons with cached field detection
 */
function forceSyncRadioButtons(formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for forceSyncRadioButtons');
    return;
  }

  const $form = getCachedElement(`#gform_${formId}`);
  const config = getFormConfigCached(formId);

  if (!config || !config.field_mappings) {
    return;
  }

  Object.entries(config.field_mappings).forEach(([dmnVariable, mapping]) => {
    const fieldId = mapping.field_id;
    const $hiddenField = $form.find(`#input_${formId}_${fieldId}`);

    if ($hiddenField.length > 0) {
      const $radioChecked = $(`input[name="${dmnVariable}"]:checked`);
      if ($radioChecked.length > 0) {
        const radioValue = $radioChecked.val();
        const hiddenValue = $hiddenField.val();

        if (radioValue !== hiddenValue) {
          $hiddenField.val(radioValue);
          $hiddenField.trigger('change');
        }
      }
    }
  });

  // Sync any other custom radio buttons
  $form.find('input[type="radio"]:checked').each(function () {
    const $radio = $(this);
    const radioName = $radio.attr('name');
    const radioValue = $radio.val();

    if (radioName && !radioName.startsWith('input_')) {
      const correspondingFieldId = findFieldIdForRadioName(formId, radioName);
      if (correspondingFieldId) {
        const $hiddenField = $form.find(`#input_${formId}_${correspondingFieldId}`);
        if ($hiddenField.length > 0 && $hiddenField.val() !== radioValue) {
          $hiddenField.val(radioValue);
          $hiddenField.trigger('change');
        }
      }
    }
  });
}

/**
 * Find field ID for radio name (cached)
 */
function findFieldIdForRadioName(formId, radioName) {
  const config = getFormConfigCached(formId);
  if (config && config.field_mappings && config.field_mappings[radioName]) {
    return config.field_mappings[radioName].field_id;
  }
  return null;
}

/**
 * Get field value (optimized)
 */
function getFieldValue($field) {
  if ($field.length === 0) return null;

  const tagName = $field.prop('tagName').toLowerCase();
  const fieldType = $field.attr('type');

  if (tagName === 'select') {
    return $field.val();
  } else if (fieldType === 'checkbox' || fieldType === 'radio') {
    return $field.is(':checked') ? $field.val() : null;
  } else if (
    tagName === 'textarea' ||
    fieldType === 'text' ||
    fieldType === 'email' ||
    fieldType === 'number' ||
    fieldType === 'hidden'
  ) {
    const val = $field.val();
    return val && val.trim() !== '' ? val : null;
  }

  return $field.val();
}

/**
 * Validate field type
 */
function validateFieldType(value, expectedType) {
  switch (expectedType) {
    case 'Integer':
      return /^-?\d+$/.test(value);
    case 'Double':
      return /^-?\d*\.?\d+$/.test(value);
    case 'Boolean':
      return ['true', 'false', '1', '0', 'yes', 'no'].includes(value.toString().toLowerCase());
    case 'String':
    default:
      return true;
  }
}

/**
 * OPTIMIZED: Validate form with cached element access
 */
function validateForm(formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for validateForm');
    return true; // Default to valid if jQuery not available
  }

  if (typeof gform !== 'undefined' && gform.validators && gform.validators[formId]) {
    return gform.validators[formId]();
  }

  const $form = getCachedElement(`#gform_${formId}`);
  let allValid = true;

  $form
    .find('.gfield_contains_required input, .gfield_contains_required select, .gfield_contains_required textarea')
    .each(function () {
      const $field = $(this);
      const value = getFieldValue($field);

      if (!value || value.trim() === '') {
        allValid = false;
        return false;
      }
    });

  return allValid;
}

/**
 * OPTIMIZED: Show success notification with cleanup
 */
function showSuccessNotification(message) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for showSuccessNotification');
    alert(message); // Fallback to alert
    return;
  }

  $('.operaton-notification').remove();

  const $notification = $(`<div class="operaton-notification">${message}</div>`);
  $notification.css({
    position: 'fixed',
    top: '20px',
    right: '20px',
    background: '#4CAF50',
    color: 'white',
    padding: '15px 20px',
    'border-radius': '6px',
    'box-shadow': '0 3px 15px rgba(0,0,0,0.2)',
    'z-index': 99999,
    'font-family': 'Arial, sans-serif',
    'font-size': '14px',
    'font-weight': 'bold',
    'max-width': '400px',
    'white-space': 'pre-line',
  });

  $('body').append($notification);

  setTimeout(() => {
    $notification.fadeOut(300, function () {
      $(this).remove();
    });
  }, 6000);
}

/**
 * OPTIMIZED: Show error with cleanup
 */
function showError(message) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for showError');
    alert('❌ ' + message); // Fallback to alert
    return;
  }

  $('.operaton-notification').remove();

  const $notification = $(`<div class="operaton-notification">❌ ${message}</div>`);
  $notification.css({
    position: 'fixed',
    top: '20px',
    right: '20px',
    background: '#f44336',
    color: 'white',
    padding: '15px 20px',
    'border-radius': '6px',
    'box-shadow': '0 3px 15px rgba(0,0,0,0.2)',
    'z-index': 99999,
    'font-family': 'Arial, sans-serif',
    'font-size': '14px',
    'font-weight': 'bold',
    'max-width': '400px',
  });

  $('body').append($notification);

  setTimeout(() => {
    $notification.fadeOut(300, function () {
      $(this).remove();
    });
  }, 8000);
}

/**
 * OPTIMIZED: Highlight field with performance considerations
 */
function highlightField($field) {
  const $ = window.jQuery || window.$;
  if (!$ || !$field || $field.length === 0) {
    console.warn('jQuery or field not available for highlightField');
    return;
  }

  const originalBackground = $field.css('background-color');
  const originalBorder = $field.css('border');

  $field.css({
    'background-color': '#e8f5e8',
    border: '2px solid #4CAF50',
    transition: 'all 0.3s ease',
  });

  $('html, body').animate(
    {
      scrollTop: $field.offset().top - 100,
    },
    500
  );

  setTimeout(() => {
    $field.css({
      'background-color': originalBackground,
      border: originalBorder,
    });
  }, 3000);
}

// =============================================================================
// OPTIMIZED MAIN INITIALIZATION SEQUENCE
// =============================================================================

/**
 * OPTIMIZED: Main jQuery-based initialization with performance tracking
 */
(function ($) {
  'use strict';

  // Enhanced jQuery availability check
  if (typeof $ === 'undefined') {
    console.error('❌ Operaton DMN CRITICAL: jQuery not available despite dependency declaration');

    // Try window.jQuery as fallback
    if (typeof window.jQuery !== 'undefined') {
      $ = window.jQuery;
      console.log('✅ Operaton DMN: Using window.jQuery as fallback');
    } else {
      console.error('❌ Operaton DMN FATAL: No jQuery available, plugin will not work');
      return;
    }
  } else {
    console.log('✅ Operaton DMN: jQuery available immediately, version:', $.fn.jquery);
  }

  // Wait for operaton_ajax and initialize with performance tracking
  waitForOperatonAjax(() => {
    const initStartTime = performance.now();
    console.log('🚀 Initializing OPTIMIZED Operaton DMN with debounced initialization...');

    // Set global initialization flag
    window.operatonInitialized.globalInit = true;
    window.operatonInitialized.jQueryReady = true;

    // Initialize the main system
    initOperatonDMN();

    // Initial batch form detection
    setTimeout(() => {
      batchFormDetection();
    }, 200);

    const initEndTime = performance.now();
    window.operatonInitialized.performanceStats.totalProcessingTime += initEndTime - initStartTime;

    console.log(`🎉 OPTIMIZED Operaton DMN initialization complete in ${(initEndTime - initStartTime).toFixed(2)}ms`);

    // Log initial performance stats
    setTimeout(logPerformanceStats, 1000);
  });

  // Enhanced cleanup on page unload with cache clearing
  $(window).on('beforeunload', () => {
    console.log('🧹 Cleaning up OPTIMIZED Operaton DMN initialization state...');

    // Clear form initialization state
    window.operatonInitialized.forms.clear();
    window.operatonInitialized.globalInit = false;

    // Clear all pending timers
    Object.keys(window.operatonInitialized.timers).forEach(formId => {
      clearTimeout(window.operatonInitialized.timers[formId]);
    });
    window.operatonInitialized.timers = {};

    // Clear all promises and timeouts
    formInitializationTimeouts.forEach(timeoutId => clearTimeout(timeoutId));
    formInitializationTimeouts.clear();
    initializationPromises.clear();

    // Clear all caches
    domQueryCache.clear();
    formConfigCache.clear();
    window.operatonButtonManager.clearCache();

    // Final performance log
    logPerformanceStats();
  });
})(jQuery);

// =============================================================================
// DOCUMENT READY AND INITIALIZATION STRATEGIES
// =============================================================================

/**
 * OPTIMIZED: Multiple initialization strategies with performance considerations
 */

// Strategy 1: Immediate jQuery check with optimized timing
if (typeof jQuery !== 'undefined') {
  console.log('✅ Operaton DMN: jQuery available immediately - OPTIMIZED path');
  jQuery(document).ready(() => {
    // Main initialization handled in jQuery wrapper above
    console.log('📋 Document ready - OPTIMIZED initialization active');
  });
} else {
  console.log('⏳ Operaton DMN: jQuery not immediately available - using OPTIMIZED polling...');

  // Strategy 2: Optimized polling for jQuery with exponential backoff
  let jqueryCheckAttempts = 0;
  const maxAttempts = 50;

  function checkForJQuery() {
    jqueryCheckAttempts++;

    if (typeof jQuery !== 'undefined') {
      console.log(`✅ Operaton DMN: jQuery found after ${jqueryCheckAttempts} attempts`);

      // Initialize immediately
      jQuery(document).ready(() => {
        console.log('📋 Late jQuery initialization - OPTIMIZED path active');
      });
    } else if (jqueryCheckAttempts < maxAttempts) {
      // Exponential backoff for less aggressive polling
      const delay = Math.min(100 * Math.pow(1.1, jqueryCheckAttempts), 1000);
      setTimeout(checkForJQuery, delay);
    } else {
      console.error(`❌ Operaton DMN: jQuery not found after ${maxAttempts} attempts`);
    }
  }

  checkForJQuery();
}

// Strategy 3: Window load fallback with optimization check
window.addEventListener('load', () => {
  setTimeout(() => {
    if (!window.operatonInitialized.globalInit) {
      console.log('🔄 Window load: Attempting OPTIMIZED late initialization...');

      if (typeof jQuery !== 'undefined') {
        console.log('jQuery available on window load - initializing');
        // Trigger batch detection if not already done
        batchFormDetection();
      } else {
        console.warn('⚠️ Window load: jQuery still not available');
      }
    } else {
      // If already initialized, just do a health check
      console.log('✅ Window load: Initialization already complete, running health check');
      logPerformanceStats();
    }
  }, 1000);
});

// Strategy 4: DOM Content Loaded for early initialization
document.addEventListener('DOMContentLoaded', () => {
  console.log('📄 DOM Content Loaded - checking OPTIMIZED initialization state');

  // Early form detection if jQuery is available
  if (typeof jQuery !== 'undefined' && !window.operatonInitialized.globalInit) {
    console.log('🚀 Early DOM initialization trigger');
    setTimeout(smartFormDetection, 100);
  }
});

// =============================================================================
// GLOBAL DEBUGGING AND UTILITY FUNCTIONS
// =============================================================================

/**
 * OPTIMIZED: Global debugging function with performance insights
 */
if (typeof window !== 'undefined') {
  window.operatonDebugOptimized = function () {
    const stats = window.operatonInitialized.performanceStats;
    const cacheStats = {
      domCache: domQueryCache.size,
      configCache: formConfigCache.size,
      buttonCache: window.operatonButtonManager.buttonCache.size,
      initPromises: initializationPromises.size,
      timeouts: formInitializationTimeouts.size,
    };

    console.log('🔍 OPTIMIZED Operaton DMN Debug Info:', {
      initializationState: window.operatonInitialized,
      performanceStats: stats,
      cacheStats: cacheStats,
      efficiency: `${((stats.duplicatePrevented / Math.max(stats.initializationAttempts, 1)) * 100).toFixed(1)}%`,
      memoryOptimization: 'Active',
      cachingStrategy: 'Multi-level with TTL',
      initializationStrategy: 'Debounced with promise-based deduplication',
    });

    return {
      status: 'optimized',
      initialized: window.operatonInitialized.globalInit,
      performance: stats,
      caches: cacheStats,
    };
  };

  /**
   * OPTIMIZED: Force cleanup function for debugging
   */
  window.operatonForceCleanup = function () {
    console.log('🧹 FORCE CLEANUP: Clearing all caches and state');

    // Clear all caches
    domQueryCache.clear();
    formConfigCache.clear();
    window.operatonButtonManager.clearCache();

    // Clear initialization state
    window.operatonInitialized.forms.clear();
    formInitializationTimeouts.forEach(id => clearTimeout(id));
    formInitializationTimeouts.clear();
    initializationPromises.clear();

    // Reset performance stats
    window.operatonInitialized.performanceStats = {
      initializationAttempts: 0,
      successfulInits: 0,
      duplicatePrevented: 0,
      totalProcessingTime: 0,
      cacheHits: 0,
    };

    console.log('✅ FORCE CLEANUP: Complete');
  };

  /**
   * OPTIMIZED: Manual re-initialization function
   */
  window.operatonReinitialize = function () {
    console.log('🔄 MANUAL REINIT: Starting optimized re-initialization');

    window.operatonForceCleanup();

    setTimeout(() => {
      if (typeof jQuery !== 'undefined') {
        smartFormDetection();
        console.log('✅ MANUAL REINIT: Complete');
      } else {
        console.error('❌ MANUAL REINIT: jQuery not available');
      }
    }, 100);
  };
}

// =============================================================================
// PERFORMANCE MONITORING AND OPTIMIZATION
// =============================================================================

/**
 * OPTIMIZED: Periodic performance monitoring (development only)
 */
if (window.operaton_ajax && window.operaton_ajax.debug) {
  setInterval(() => {
    const stats = window.operatonInitialized.performanceStats;

    // Log performance issues if detected
    if (stats.initializationAttempts > 20) {
      console.warn('⚠️ HIGH INITIALIZATION ATTEMPTS:', stats.initializationAttempts);
    }

    if (domQueryCache.size > 100) {
      console.warn('⚠️ LARGE DOM CACHE:', domQueryCache.size, 'entries');
      cleanupCaches();
    }

    if (stats.totalProcessingTime > 5000) {
      console.warn('⚠️ HIGH PROCESSING TIME:', stats.totalProcessingTime.toFixed(2), 'ms');
    }
  }, 30000); // Check every 30 seconds
}

console.log(
  '🎉 OPTIMIZED Operaton DMN frontend script loaded with debounced initialization and performance monitoring'
);
