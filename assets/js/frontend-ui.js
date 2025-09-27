/**
 * Operaton DMN Frontend UI Module
 * Handles notifications, button management, visual feedback, and user interface elements
 *
 * Dependencies: frontend-core.js
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

operatonDebugVerbose('Frontend', 'UI module loading...');

// =============================================================================
// GLOBAL UI FUNCTIONS FOR INLINE SCRIPT COMPATIBILITY
// =============================================================================

/**
 * Show evaluate button for a form
 * Called by inline scripts and other modules
 * @param {number} formId Form ID
 */
window.showEvaluateButton = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for showEvaluateButton');
    return;
  }

  try {
    const $button = getCachedElement(`#operaton-evaluate-${formId}`);
    const $summary = getCachedElement(`#decision-flow-summary-${formId}`);

    operatonDebugVerbose('Frontend', 'Showing evaluate button for form', {formId: formId});
    $button.addClass('operaton-show-button').show();
    $summary.removeClass('operaton-show-summary');
  } catch (error) {
    operatonDebugMinimal('Frontend', 'Error in showEvaluateButton', {error: error.message || error});
    // Fallback without caching
    $(`#operaton-evaluate-${formId}`).addClass('operaton-show-button').show();
    $(`#decision-flow-summary-${formId}`).removeClass('operaton-show-summary');
  }
};

/**
 * Show decision flow summary for a form  
 * Called by inline scripts and evaluation results
 * @param {number} formId Form ID
 */
window.showDecisionFlowSummary = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for showDecisionFlowSummary');
    return;
  }

  try {
    const $button = getCachedElement(`#operaton-evaluate-${formId}`);
    $button.removeClass('operaton-show-button');

    const $summary = getCachedElement(`#decision-flow-summary-${formId}`);
    $summary.addClass('operaton-show-summary');

    if (typeof window.loadDecisionFlowSummary === 'function') {
      operatonDebugVerbose('Frontend', 'Delegating decision flow loading to decision-flow.js', {formId: formId});
      window.loadDecisionFlowSummary(formId);
    } else {
      operatonDebugVerbose('Frontend', 'Decision flow manager not available', {formId: formId});
    }
  } catch (error) {
    operatonDebugMinimal('Frontend', 'Error in showDecisionFlowSummary', {error: error.message || error});
  }
};

/**
 * Hide all UI elements for a form
 * Called when user navigates away from evaluation pages
 * @param {number} formId Form ID
 */
window.hideAllElements = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for hideAllElements');
    return;
  }

  try {
    let currentPage = 1;
    let targetPage = 2;

    // Get current page
    if (typeof getCurrentPageCached === 'function') {
      currentPage = getCurrentPageCached(formId);
    } else {
      const urlParams = new URLSearchParams(window.location.search);
      const pageParam = urlParams.get('gf_page');
      if (pageParam) {
        currentPage = parseInt(pageParam);
      }
    }

    // Get target page from configuration
    if (typeof getFormConfigCached === 'function') {
      const config = getFormConfigCached(formId);
      targetPage = config ? parseInt(config.evaluation_step) || 2 : 2;
    }

    // Don't hide if we're on the target page
    if (currentPage === targetPage) {
      operatonDebugVerbose('Frontend', 'Skipping hide - we are on target page', {currentPage: currentPage});
      return;
    }

    const $button = getCachedElement(`#operaton-evaluate-${formId}`);
    const $summary = getCachedElement(`#decision-flow-summary-${formId}`);

    operatonDebugVerbose('Frontend', 'Hiding all elements for form', {formId: formId});
    $button.removeClass('operaton-show-button').hide();
    $summary.removeClass('operaton-show-summary');
  } catch (error) {
    operatonDebugMinimal('Frontend', 'Error in hideAllElements', {error: error.message || error});
    // Fallback without caching
    $(`#operaton-evaluate-${formId}`).removeClass('operaton-show-button').hide();
    $(`#decision-flow-summary-${formId}`).removeClass('operaton-show-summary');
  }
};

// =============================================================================
// NOTIFICATION SYSTEM
// =============================================================================

/**
 * Show success notification
 * @param {string} message Success message to display
 */
function showSuccess(message) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for showSuccess');
    alert(message);
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
 * Show error notification
 * @param {string} message Error message to display
 */
function showError(message) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for showError');
    alert('Error: ' + message);
    return;
  }

  $('.operaton-notification').remove();

  const $notification = $(`<div class="operaton-notification">Error: ${message}</div>`);
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
 * Legacy success notification function
 * Maintained for backward compatibility
 * @param {string} message Success message to display
 */
function showSuccessNotification(message) {
  showSuccess(message);
}

// =============================================================================
// FIELD HIGHLIGHTING
// =============================================================================

/**
 * Highlight a form field with visual feedback
 * @param {jQuery} $field jQuery element of the field to highlight
 */
function highlightField($field) {
  const $ = window.jQuery || window.$;
  if (!$ || !$field || $field.length === 0) {
    operatonDebugMinimal('Frontend', 'jQuery or field not available for highlightField');
    return;
  }

  const originalBackground = $field.css('background-color');
  const originalBorder = $field.css('border');

  $field.css({
    'background-color': '#e8f5e8',
    border: '2px solid #4CAF50',
    transition: 'all 0.3s ease',
  });

  // Scroll to field
  $('html, body').animate(
    {
      scrollTop: $field.offset().top - 100,
    },
    500
  );

  // Restore original styling after 3 seconds
  setTimeout(() => {
    $field.css({
      'background-color': originalBackground,
      border: originalBorder,
    });
  }, 3000);
}

// =============================================================================
// CACHED ELEMENT UTILITIES
// =============================================================================

/**
 * Get cached jQuery element with performance optimization
 * @param {string} selector CSS selector
 * @param {number} maxAge Maximum cache age in milliseconds
 * @returns {jQuery} jQuery element
 */
function getCachedElement(selector, maxAge = 5000) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for getCachedElement');
    return $();
  }

  // Use global cache from core module
  const domQueryCache = window.domQueryCache || new Map();
  const cacheKey = `element_${selector}`;
  const cached = domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < maxAge) {
    // Update performance stats if available
    if (window.operatonInitialized && window.operatonInitialized.performanceStats) {
      window.operatonInitialized.performanceStats.cacheHits++;
    }
    return cached.element;
  }

  const element = $(selector);
  domQueryCache.set(cacheKey, {
    element: element,
    timestamp: Date.now(),
  });

  return element;
}

// =============================================================================
// BUTTON MANAGER
// =============================================================================

/**
 * Advanced button state management system
 */
window.operatonButtonManager = window.operatonButtonManager || {
  originalTexts: new Map(),
  buttonCache: new Map(),

  /**
   * Get cached button element for a form
   * @param {number} formId Form ID
   * @returns {jQuery} Button element
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
   * Store original button text before modification
   * @param {jQuery} $button Button element
   * @param {number} formId Form ID
   * @returns {string} Original button text
   */
  storeOriginalText: function ($button, formId) {
    const buttonId = `form_${formId}`;
    if (this.originalTexts.has(buttonId)) {
      return this.originalTexts.get(buttonId);
    }

    let originalText = $button.attr('data-original-text') || $button.val() || $button.attr('value') || 'Evaluate';

    // Clean up text if it's already in a modified state
    if (
      originalText.includes('Evaluation') ||
      originalText.includes('Evaluating') ||
      originalText.includes('progress')
    ) {
      originalText = 'Evaluate';
    }

    this.originalTexts.set(buttonId, originalText);
    $button.attr('data-original-text', originalText);
    return originalText;
  },

  /**
   * Get stored original button text
   * @param {number} formId Form ID
   * @returns {string} Original button text
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
   * Set button to evaluating state
   * @param {jQuery} $button Button element
   * @param {number} formId Form ID
   */
  setEvaluatingState: function ($button, formId) {
    this.storeOriginalText($button, formId);
    $button.val('Evaluating...').prop('disabled', true).addClass('operaton-evaluating');
  },

  /**
   * Restore button to original state
   * @param {jQuery} $button Button element
   * @param {number} formId Form ID
   */
  restoreOriginalState: function ($button, formId) {
    const originalText = this.getOriginalText(formId);
    $button
      .val(originalText)
      .prop('value', originalText)
      .attr('value', originalText)
      .prop('disabled', false)
      .removeClass('operaton-evaluating');

    // Clear button cache to force fresh lookup
    this.buttonCache.delete(`button_${formId}`);

    // Double-check restoration
    setTimeout(() => {
      $button.val(originalText).prop('disabled', false);
    }, 100);

    // Final verification
    setTimeout(() => {
      if ($button.val() !== originalText) {
        $button.val(originalText);
        operatonDebugVerbose('Frontend', 'Button text corrected after restoration', {formId: formId});
      }
    }, 500);
  },

  /**
   * Clear all button caches
   */
  clearCache: function () {
    this.buttonCache.clear();
  }
};

// =============================================================================
// MODULE EXPORTS AND GLOBAL REGISTRATION
// =============================================================================

/**
 * Export UI functions for other modules to use
 */
window.OperatonUI = {
  // Notification system
  showSuccess: showSuccess,
  showError: showError,
  showSuccessNotification: showSuccessNotification,
  
  // Visual feedback
  highlightField: highlightField,
  
  // Element utilities
  getCachedElement: getCachedElement,
  
  // Button management
  buttonManager: window.operatonButtonManager
};

// Make key functions globally accessible for backward compatibility
window.showSuccess = showSuccess;
window.showError = showError;
window.showSuccessNotification = showSuccessNotification;
window.highlightField = highlightField;
window.getCachedElement = getCachedElement;

operatonDebugVerbose('Frontend', 'UI module loaded - Production version');