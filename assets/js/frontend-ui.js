/**
 * Operaton DMN Frontend UI Module
 * Button management and UI element visibility control
 *
 * @package OperatonDMN
 * @since 1.0.0-beta.18
 */

operatonDebugFrontend('UI', 'Frontend UI module loading...');

// =============================================================================
// MODULE DEPENDENCY CHECK
// =============================================================================

// Ensure core module is loaded first
if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.core) {
  operatonDebugMinimal('UI', 'ERROR: Core module not loaded! UI module requires frontend-core.js');
  throw new Error('Operaton DMN: Core module must be loaded before UI module');
}

// =============================================================================
// BUTTON AND UI ELEMENT MANAGEMENT
// =============================================================================

/**
 * Show evaluation button for specific form
 * @param {number} formId - Gravity Forms form ID
 */
window.showEvaluateButton = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('UI', 'jQuery not available for showEvaluateButton');
    return;
  }

  try {
    const $button = window.getCachedElement(`#operaton-evaluate-${formId}`);
    const $summary = window.getCachedElement(`#decision-flow-summary-${formId}`);

    $button.addClass('operaton-show-button').show();
    $summary.removeClass('operaton-show-summary');
    
    operatonDebugVerbose('UI', `✅ Evaluate button shown for form ${formId}`);
  } catch (error) {
    operatonDebugMinimal('UI', 'Error in showEvaluateButton:', error);
    // Fallback without caching
    $(`#operaton-evaluate-${formId}`).addClass('operaton-show-button').show();
    $(`#decision-flow-summary-${formId}`).removeClass('operaton-show-summary');
  }
};

/**
 * Show decision flow summary for specific form
 * @param {number} formId - Gravity Forms form ID
 */
window.showDecisionFlowSummary = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('UI', 'jQuery not available for showDecisionFlowSummary');
    return;
  }

  try {
    const $button = window.getCachedElement(`#operaton-evaluate-${formId}`);
    $button.removeClass('operaton-show-button');

    const $summary = window.getCachedElement(`#decision-flow-summary-${formId}`);
    $summary.addClass('operaton-show-summary');

    // Delegate to decision flow module if available
    if (typeof window.loadDecisionFlowSummary === 'function') {
      operatonDebugVerbose('UI', '📊 Delegating decision flow loading to decision-flow.js for form', formId);
      window.loadDecisionFlowSummary(formId);
    } else {
      operatonDebugVerbose('UI', '📊 Decision flow manager not available for form', formId);
    }
    
    operatonDebugVerbose('UI', `📊 Decision flow summary shown for form ${formId}`);
  } catch (error) {
    operatonDebugMinimal('UI', 'Error in showDecisionFlowSummary:', error);
    // Fallback without caching
    $(`#operaton-evaluate-${formId}`).removeClass('operaton-show-button');
    $(`#decision-flow-summary-${formId}`).addClass('operaton-show-summary');
  }
};

/**
 * Hide all UI elements for specific form
 * @param {number} formId - Gravity Forms form ID
 */
window.hideAllElements = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('UI', 'jQuery not available for hideAllElements');
    return;
  }

  try {
    const currentPage = window.getCurrentPageCached(formId);
    const config = window.getFormConfigCached(formId);
    const targetPage = config ? parseInt(config.evaluation_step) || 2 : 2;

    if (currentPage === targetPage) {
      return;
    }

    const $button = window.getCachedElement(`#operaton-evaluate-${formId}`);
    const $summary = window.getCachedElement(`#decision-flow-summary-${formId}`);

    operatonDebugVerbose('UI', '❌ Hiding all elements for form', formId);
    $button.removeClass('operaton-show-button').hide();
    $summary.removeClass('operaton-show-summary');
  } catch (error) {
    operatonDebugMinimal('UI', 'Error in hideAllElements:', error);
    // Fallback without caching
    $(`#operaton-evaluate-${formId}`).removeClass('operaton-show-button').hide();
    $(`#decision-flow-summary-${formId}`).removeClass('operaton-show-summary');
  }
};

// =============================================================================
// BUTTON PLACEMENT LOGIC
// =============================================================================

/**
 * Handle button placement based on form configuration and current page
 * @param {number} formId - Gravity Forms form ID
 */
window.handleButtonPlacement = function(formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugVerbose('UI', 'jQuery not available for handleButtonPlacement');
    return;
  }

  const config = window.getFormConfigCached(formId);
  if (!config) {
    operatonDebugVerbose('UI', `No configuration found for form ${formId}`);
    return;
  }

  const currentPage = window.getCurrentPageCached(formId);
  const targetPage = parseInt(config.evaluation_step) || 2;
  const showDecisionFlow = config.show_decision_flow || false;
  const useProcess = config.use_process || false;

  operatonDebugVerbose('UI', `Button placement check - Form: ${formId}, Current page: ${currentPage}, Target: ${targetPage}`);

  if (currentPage === targetPage) {
    window.showEvaluateButton(formId);
  } else if (currentPage === targetPage + 1 && showDecisionFlow && useProcess) {
    window.showDecisionFlowSummary(formId);
  } else {
    operatonDebugVerbose('UI', `Hiding elements for form ${formId}`);
    window.hideAllElements(formId);
  }
};

// =============================================================================
// UI STATE MANAGEMENT
// =============================================================================

/**
 * UI state manager for tracking button states and visibility
 */
window.operatonUIState = window.operatonUIState || {
  buttonStates: new Map(),
  visibilityStates: new Map(),
  
  /**
   * Set button state for form
   */
  setButtonState: function(formId, state) {
    this.buttonStates.set(formId, {
      state: state,
      timestamp: Date.now()
    });
    operatonDebugVerbose('UI', `Button state set for form ${formId}: ${state}`);
  },
  
  /**
   * Get button state for form
   */
  getButtonState: function(formId) {
    return this.buttonStates.get(formId);
  },
  
  /**
   * Clear states for form
   */
  clearFormStates: function(formId) {
    this.buttonStates.delete(formId);
    this.visibilityStates.delete(formId);
    operatonDebugVerbose('UI', `Cleared UI states for form ${formId}`);
  },
  
  /**
   * Clear all states
   */
  clearAllStates: function() {
    this.buttonStates.clear();
    this.visibilityStates.clear();
    operatonDebugVerbose('UI', 'Cleared all UI states');
  }
};

// =============================================================================
// UI UTILITIES
// =============================================================================

/**
 * Check if element is visible on current page
 * @param {string} selector - jQuery selector
 * @returns {boolean} True if element is visible
 */
window.isElementVisible = function(selector) {
  const $ = window.jQuery || window.$;
  if (!$) return false;
  
  try {
    const $element = $(selector);
    return $element.length > 0 && $element.is(':visible');
  } catch (error) {
    operatonDebugMinimal('UI', 'Error checking element visibility:', error);
    return false;
  }
};

/**
 * Toggle element visibility with animation support
 * @param {string} selector - jQuery selector
 * @param {boolean} show - True to show, false to hide
 * @param {number} duration - Animation duration in ms
 */
window.toggleElementVisibility = function(selector, show, duration = 0) {
  const $ = window.jQuery || window.$;
  if (!$) return;
  
  try {
    const $element = $(selector);
    if ($element.length === 0) return;
    
    if (show) {
      if (duration > 0) {
        $element.fadeIn(duration);
      } else {
        $element.show();
      }
    } else {
      if (duration > 0) {
        $element.fadeOut(duration);
      } else {
        $element.hide();
      }
    }
    
    operatonDebugVerbose('UI', `Element ${selector} ${show ? 'shown' : 'hidden'} ${duration > 0 ? 'with animation' : 'instantly'}`);
  } catch (error) {
    operatonDebugMinimal('UI', 'Error toggling element visibility:', error);
  }
};

// =============================================================================
// UI DEBUG FUNCTIONS
// =============================================================================

/**
 * Debug function to inspect UI state
 * @returns {Object} Current UI state information
 */
window.operatonDebugUI = function() {
  const uiInfo = {
    buttonStates: Array.from(window.operatonUIState.buttonStates.entries()),
    visibilityStates: Array.from(window.operatonUIState.visibilityStates.entries()),
    availableFunctions: {
      showEvaluateButton: typeof window.showEvaluateButton,
      showDecisionFlowSummary: typeof window.showDecisionFlowSummary,
      hideAllElements: typeof window.hideAllElements,
      handleButtonPlacement: typeof window.handleButtonPlacement
    },
    moduleStatus: 'ui-module-active'
  };

  operatonDebugVerbose('UI', 'UI Debug Info:', uiInfo);
  return uiInfo;
};

// =============================================================================
// MODULE COMPLETION FLAG
// =============================================================================

// Mark UI module as loaded
window.operatonModulesLoaded = window.operatonModulesLoaded || {};
window.operatonModulesLoaded.ui = true;

operatonDebugFrontend('UI', 'Frontend UI module loaded successfully');
