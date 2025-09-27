/**
 * Operaton DMN Frontend Script - Production Version
 * Handles form evaluation, result display, and Gravity Forms integration
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

// =============================================================================
// GLOBAL STATE MANAGEMENT
// =============================================================================

window.operatonProcessingLock = window.operatonProcessingLock || {};

window.operatonInitialized = window.operatonInitialized || {
  forms: new Set(),
  globalInit: false,
  jQueryReady: false,
  initInProgress: false,
};

// Simple caching utilities
const domQueryCache = new Map();
const formConfigCache = new Map();

// =============================================================================
// GLOBAL FUNCTIONS FOR INLINE SCRIPT COMPATIBILITY
// =============================================================================

window.showEvaluateButton = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for showEvaluateButton');
    return;
  }

  try {
    const $button = getCachedElement(`#operaton-evaluate-${formId}`);
    const $summary = getCachedElement(`#decision-flow-summary-${formId}`);

    operatonDebugVerbose('Frontend', 'Showing evaluate button for form', { formId: formId });
    $button.addClass('operaton-show-button').show();
    $summary.removeClass('operaton-show-summary');
  } catch (error) {
    operatonDebugMinimal('Frontend', 'Error in showEvaluateButton', { error: error.message || error });
    $(`#operaton-evaluate-${formId}`).addClass('operaton-show-button').show();
    $(`#decision-flow-summary-${formId}`).removeClass('operaton-show-summary');
  }
};

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
      operatonDebugVerbose('Frontend', 'Delegating decision flow loading to decision-flow.js', { formId: formId });
      window.loadDecisionFlowSummary(formId);
    } else {
      operatonDebugVerbose('Frontend', 'Decision flow manager not available', { formId: formId });
    }
  } catch (error) {
    operatonDebugMinimal('Frontend', 'Error in showDecisionFlowSummary', { error: error.message || error });
  }
};

window.hideAllElements = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for hideAllElements');
    return;
  }

  try {
    let currentPage = 1;
    let targetPage = 2;

    if (typeof getCurrentPageCached === 'function') {
      currentPage = getCurrentPageCached(formId);
    } else {
      const urlParams = new URLSearchParams(window.location.search);
      const pageParam = urlParams.get('gf_page');
      if (pageParam) {
        currentPage = parseInt(pageParam);
      }
    }

    if (typeof getFormConfigCached === 'function') {
      const config = getFormConfigCached(formId);
      targetPage = config ? config.target_page || 2 : 2;
    }

    const $elements = $(`.operaton-evaluate-${formId}, #decision-flow-summary-${formId}`);
    $elements.removeClass('operaton-show-button operaton-show-summary').hide();

    if (targetPage !== currentPage) {
      const nextPageUrl = new URL(window.location);
      nextPageUrl.searchParams.set('gf_page', targetPage);
      window.location.href = nextPageUrl.toString();
    }
  } catch (error) {
    operatonDebugMinimal('Frontend', 'Error in hideAllElements', { error: error.message || error });
  }
};

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

function getCachedElement(selector) {
  const cached = domQueryCache.get(selector);
  if (cached && Date.now() - cached.timestamp < 3000) {
    return cached.element;
  }

  const $ = window.jQuery || window.$;
  const element = $(selector);
  domQueryCache.set(selector, {
    element: element,
    timestamp: Date.now(),
  });

  return element;
}

function getFormConfigCached(formId) {
  if (formConfigCache.has(formId)) {
    return formConfigCache.get(formId);
  }

  const configVarName = `operaton_dmn_form_${formId}`;
  const config = window[configVarName] || null;

  if (config) {
    formConfigCache.set(formId, config);
  }

  return config;
}

function clearDOMCache(formId) {
  if (formId) {
    for (const [key] of domQueryCache) {
      if (key.includes(formId.toString())) {
        domQueryCache.delete(key);
      }
    }
  } else {
    domQueryCache.clear();
  }
}

// =============================================================================
// DATE CONVERSION UTILITIES
// =============================================================================

function convertToISODate(dateStr) {
  if (!dateStr || typeof dateStr !== 'string') {
    return dateStr;
  }

  // Handle DD/MM/YYYY format
  if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(dateStr)) {
    const parts = dateStr.split('/');
    const day = parts[0].padStart(2, '0');
    const month = parts[1].padStart(2, '0');
    const year = parts[2];
    const convertedDate = `${year}-${month}-${day}`;
    operatonDebugVerbose('Frontend', 'DD/MM/YYYY conversion', { input: dateStr, output: convertedDate });
    return convertedDate;
  }

  // Handle MM/DD/YYYY format (US format)
  if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(dateStr)) {
    operatonDebugVerbose('Frontend', 'Ambiguous date format detected, assuming DD/MM/YYYY', { dateStr: dateStr });
  }

  // Handle YYYY/MM/DD format
  if (/^\d{4}\/\d{1,2}\/\d{1,2}$/.test(dateStr)) {
    const parts = dateStr.split('/');
    const year = parts[0];
    const month = parts[1].padStart(2, '0');
    const day = parts[2].padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  // Try JavaScript Date parsing as fallback
  try {
    const date = new Date(dateStr);
    if (!isNaN(date.getTime())) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const result = `${year}-${month}-${day}`;
      operatonDebugVerbose('Frontend', 'Date object conversion', { input: dateStr, output: result });
      return result;
    }
  } catch (e) {
    operatonDebugMinimal('Frontend', 'Error parsing date', { dateStr: dateStr, error: e.message || e });
  }

  operatonDebugVerbose('Frontend', 'Could not convert date format', { dateStr: dateStr });
  return dateStr;
}

function findFieldOnCurrentPageOptimized(formId, fieldId) {
  const cacheKey = `field_${formId}_${fieldId}`;
  const cached = domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < 3000) {
    return cached.element;
  }

  const $ = window.jQuery || window.$;
  if (!$) {
    return null;
  }

  let $field = $(`#input_${formId}_${fieldId}`);
  if ($field.length === 0) {
    $field = $(`[name="input_${formId}_${fieldId}"]`);
  }
  if ($field.length === 0) {
    $field = $(`[id*="${fieldId}"][id*="${formId}"]`);
  }

  domQueryCache.set(cacheKey, {
    element: $field,
    timestamp: Date.now(),
  });

  return $field;
}

// =============================================================================
// NOTIFICATION FUNCTIONS
// =============================================================================

function showSuccess(message) {
  const $ = window.jQuery || window.$;
  if (!$) {
    return;
  }

  $('.operaton-notification').remove();

  const $notification = $(`<div class="operaton-notification">Success: ${message}</div>`);
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
  });

  $('body').append($notification);

  setTimeout(() => {
    $notification.fadeOut(300, function () {
      $(this).remove();
    });
  }, 6000);
}

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
// EVENT BINDING
// =============================================================================

function bindEvaluationEventsOptimized(formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for bindEvaluationEventsOptimized');
    return;
  }

  const selector = `.operaton-evaluate-btn[data-form-id="${formId}"]`;

  $(document).off(`click.operaton-${formId}`, selector);
  $(document).on(`click.operaton-${formId}`, selector, function (e) {
    e.preventDefault();
    operatonDebugVerbose('Frontend', 'Button clicked for form', { formId: formId });
    handleEvaluateClick($(this));
  });

  operatonDebugVerbose('Frontend', 'Event handler bound for form', { formId: formId });
}

// =============================================================================
// AJAX SETUP
// =============================================================================

function waitForOperatonAjax(callback, maxAttempts = 50) {
  let attempts = 0;

  function check() {
    attempts++;

    if (typeof window.operaton_ajax !== 'undefined') {
      operatonDebugVerbose('Frontend', 'operaton_ajax found', { attempts: attempts });
      callback();
    } else if (attempts < maxAttempts) {
      if (attempts % 10 === 0) {
        operatonDebugVerbose('Frontend', 'Still waiting for operaton_ajax', { attempt: attempts });
      }
      setTimeout(check, 100);
    } else {
      operatonDebugMinimal('Frontend', 'operaton_ajax not found', { maxAttempts: maxAttempts });
      createEmergencyOperatonAjax();
      callback();
    }
  }
  check();
}

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
      },
      emergency_mode: true,
    };
  }
}

// =============================================================================
// MAIN EVALUATION HANDLER
// =============================================================================

function handleEvaluateClick($button) {
  const formId = $button.data('form-id');
  const configId = $button.data('config-id');

  if (!formId || !configId) {
    showError('Missing form or configuration ID');
    return;
  }

  // Prevent double-clicking
  if (window.operatonProcessingLock[formId]) {
    operatonDebugVerbose('Frontend', 'Evaluation already in progress', { formId: formId });
    return;
  }

  window.operatonProcessingLock[formId] = true;

  try {
    performEvaluation(formId, configId, $button);
  } catch (error) {
    operatonDebugMinimal('Frontend', 'Error in handleEvaluateClick', { error: error.message || error });
    window.operatonProcessingLock[formId] = false;
    showError('Evaluation failed: ' + (error.message || 'Unknown error'));
  }
}

function performEvaluation(formId, configId, $button) {
  const $ = window.jQuery || window.$;
  const $form = $(`#gform_${formId}`);

  if ($form.length === 0) {
    throw new Error('Form not found');
  }

  // Update button state
  const originalText = $button.text();
  $button.prop('disabled', true).text(window.operaton_ajax.strings.evaluating || 'Evaluating...');

  // Collect form data
  const formData = {};
  $form.find('input, select, textarea').each(function () {
    const $field = $(this);
    const name = $field.attr('name');
    const value = $field.val();

    if (name && value !== null && value !== undefined) {
      // Handle date fields
      if ($field.hasClass('datepicker') || $field.attr('type') === 'date') {
        formData[name] = convertToISODate(value);
      } else {
        formData[name] = value;
      }
    }
  });

  // Send AJAX request
  $.ajax({
    url: window.operaton_ajax.url,
    method: 'POST',
    data: {
      config_id: configId,
      form_data: formData,
      action: 'operaton_evaluate',
    },
    headers: {
      'X-WP-Nonce': window.operaton_ajax.nonce,
    },
  })
    .done(function (response) {
      handleEvaluationSuccess(response, formId, $button, originalText);
    })
    .fail(function (xhr, status, error) {
      handleEvaluationError(xhr, status, error, formId, $button, originalText);
    });
}

function handleEvaluationSuccess(response, formId, $button, originalText) {
  window.operatonProcessingLock[formId] = false;
  $button.prop('disabled', false).text(originalText);

  if (response.success && response.data) {
    populateResults(formId, response.data);
    showSuccess(window.operaton_ajax.strings.success || 'Evaluation completed');

    // Show decision flow if available
    if (typeof window.showDecisionFlowSummary === 'function') {
      window.showDecisionFlowSummary(formId);
    }
  } else {
    const message = response.data?.message || 'Evaluation failed';
    showError(message);
  }
}

function handleEvaluationError(xhr, status, error, formId, $button, originalText) {
  window.operatonProcessingLock[formId] = false;
  $button.prop('disabled', false).text(originalText);

  operatonDebugMinimal('Frontend', 'AJAX evaluation error', {
    status: status,
    error: error,
    formId: formId,
  });

  let message = window.operaton_ajax.strings.connection_error || 'Connection error. Please try again.';

  if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
    message = xhr.responseJSON.data.message;
  }

  showError(message);
}

function populateResults(formId, results) {
  const $ = window.jQuery || window.$;
  const config = getFormConfigCached(formId);

  if (!config || !config.result_mappings) {
    return;
  }

  window.operatonPopulatingResults = true;

  try {
    Object.entries(config.result_mappings).forEach(([resultKey, fieldId]) => {
      if (results.hasOwnProperty(resultKey)) {
        const $field = findFieldOnCurrentPageOptimized(formId, fieldId);

        if ($field && $field.length > 0) {
          let value = results[resultKey];

          // Handle boolean values
          if (typeof value === 'boolean') {
            value = value ? 'true' : 'false';
          }

          $field.val(value).trigger('change');
          highlightField($field);
        }
      }
    });
  } finally {
    window.operatonPopulatingResults = false;
  }
}

// =============================================================================
// FORM DETECTION AND INITIALIZATION
// =============================================================================

function simplifiedFormDetection() {
  const $ = window.jQuery || window.$;
  if (!$) {
    return;
  }

  operatonDebugVerbose('Frontend', 'Running simplified form detection...');

  $('form[id^="gform_"]').each(function () {
    const $form = $(this);
    const formId = parseInt($form.attr('id').replace('gform_', ''));

    if (formId && !window.operatonInitialized.forms.has(formId)) {
      const config = getFormConfigCached(formId);
      if (config) {
        simpleFormInitialization(formId);
      }
    }
  });

  operatonDebugVerbose('Frontend', 'Simplified detection complete');
}

function simpleFormInitialization(formId) {
  formId = parseInt(formId);

  // Prevent duplicate initialization
  if (window.operatonInitialized.forms.has(formId)) {
    return;
  }

  const config = getFormConfigCached(formId);
  if (!config) {
    return;
  }

  operatonDebugVerbose('Frontend', 'Initializing form', { formId: formId });

  try {
    // Bind evaluation events
    bindEvaluationEventsOptimized(formId);

    // Set up result field clearing on form changes
    setupResultFieldClearing(formId, config);

    // Mark as initialized
    window.operatonInitialized.forms.add(formId);

    operatonDebugVerbose('Frontend', 'Form initialization complete', { formId: formId });
  } catch (error) {
    operatonDebugMinimal('Frontend', 'Form initialization error', {
      formId: formId,
      error: error.message || error,
    });
  }
}

function setupResultFieldClearing(formId, config) {
  const $ = window.jQuery || window.$;

  if (!config.result_mappings || !config.clear_results_on_change) {
    return;
  }

  const resultFieldIds = Object.values(config.result_mappings);
  let changeTimeout;

  $(`#gform_${formId}`).on('input change', 'input, select, textarea', function () {
    const $field = $(this);
    const fieldName = $field.attr('name') || $field.attr('id');

    // Skip if it's a result field
    const isResultField = resultFieldIds.some(
      id =>
        fieldName &&
        (fieldName.includes(`input_${formId}_${id}`) ||
          fieldName === `input_${formId}_${id}` ||
          fieldName.includes(`_${id}`))
    );

    if (isResultField || window.operatonPopulatingResults) {
      return;
    }

    // Clear any existing timeout
    if (changeTimeout) {
      clearTimeout(changeTimeout);
    }

    // Set a debounced check for clearing results
    changeTimeout = setTimeout(() => {
      if (!window.operatonPopulatingResults) {
        clearAllResultFields(formId);
      }
    }, 300);
  });
}

function clearAllResultFields(formId) {
  const $ = window.jQuery || window.$;
  const config = getFormConfigCached(formId);

  if (!config || !config.result_mappings) {
    return;
  }

  Object.values(config.result_mappings).forEach(fieldId => {
    const $field = findFieldOnCurrentPageOptimized(formId, fieldId);
    if ($field && $field.length > 0) {
      $field.val('').trigger('change');
    }
  });
}

// =============================================================================
// MAIN INITIALIZATION
// =============================================================================

function initOperatonDMN() {
  // Prevent duplicate global initialization
  if (window.operatonInitialized.globalInit) {
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
        clearDOMCache(formId);

        // Small delay to ensure DOM is fully rendered
        setTimeout(() => {
          simpleFormInitialization(formId);
        }, 100);
      },
      10,
      'operaton_form_render'
    );
  }

  // Initial form detection
  setTimeout(() => {
    simplifiedFormDetection();
  }, 200);

  // Set global flag
  window.operatonInitialized.globalInit = true;

  operatonDebugFrontend('Operaton DMN initialization complete');
}

/**
 * Make handleEvaluateClick globally accessible for delegation
 */
window.handleEvaluateClick = handleEvaluateClick;

// =============================================================================
// MAIN INITIALIZATION SEQUENCE
// =============================================================================

function waitForJQuery(callback, maxAttempts = 50) {
  let attempts = 0;

  function check() {
    attempts++;

    if (typeof jQuery !== 'undefined') {
      operatonDebugVerbose('Frontend', 'jQuery found', { attempts: attempts });
      callback();
    } else if (attempts < maxAttempts) {
      if (attempts % 10 === 0) {
        operatonDebugVerbose('Frontend', 'Still waiting for jQuery', { attempt: attempts });
      }
      const delay = Math.min(100 * Math.pow(1.1, attempts), 1000);
      setTimeout(check, delay);
    } else {
      operatonDebugMinimal('Frontend', 'jQuery not found', { maxAttempts: maxAttempts });
    }
  }
  check();
}

// SINGLE MAIN INITIALIZATION - NO DUPLICATES
(function () {
  'use strict';

  // Ensure we only run once per page load
  if (window.operatonMainInitCalled) {
    return;
  }
  window.operatonMainInitCalled = true;

  function performInitialization($) {
    operatonDebugVerbose('Frontend', 'jQuery available', { version: $.fn.jquery });

    // Wait for operaton_ajax and initialize
    waitForOperatonAjax(() => {
      const initStartTime = performance.now();
      operatonDebugFrontend('Initializing Operaton DMN...');

      window.operatonInitialized.jQueryReady = true;
      initOperatonDMN();

      const timeMs = (performance.now() - initStartTime).toFixed(2);
      operatonDebugFrontend('Operaton DMN initialization complete', { timeMs: timeMs });

      $(document).ready(() => {
        operatonDebugVerbose('Frontend', 'Document ready - initialization active');
      });
    });
  }

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
        simplifiedFormDetection();
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
});

operatonDebugFrontend('Frontend script loaded - Production version');
