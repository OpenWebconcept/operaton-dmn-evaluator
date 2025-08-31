/**
 * FIXED: Operaton DMN Frontend Script
 * Single initialization, proper result field detection, smart navigation clearing
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

console.log('🚀 Operaton DMN FIXED frontend script loading...');

// =============================================================================
// FIXED GLOBAL STATE MANAGEMENT
// =============================================================================

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
 * Caching utilities (keeping the useful parts)
 */
const domQueryCache = new Map();
const formConfigCache = new Map();

/**
 * Define global functions FIRST for inline script compatibility
 */
window.showEvaluateButton = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for showEvaluateButton');
    return;
  }

  try {
    const $button = getCachedElement(`#operaton-evaluate-${formId}`);
    const $summary = getCachedElement(`#decision-flow-summary-${formId}`);

    console.log('✅ Showing evaluate button for form', formId);
    $button.addClass('operaton-show-button').show();
    $summary.removeClass('operaton-show-summary');
  } catch (error) {
    console.error('Error in showEvaluateButton:', error);
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
    const $button = getCachedElement(`#operaton-evaluate-${formId}`);
    $button.removeClass('operaton-show-button');

    const $summary = getCachedElement(`#decision-flow-summary-${formId}`);
    $summary.addClass('operaton-show-summary');

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
      targetPage = config ? parseInt(config.evaluation_step) || 2 : 2;
    }

    if (currentPage === targetPage) {
      console.log('⏱️ Skipping hide - we are on target page', currentPage);
      return;
    }

    const $button = getCachedElement(`#operaton-evaluate-${formId}`);
    const $summary = getCachedElement(`#decision-flow-summary-${formId}`);

    console.log('❌ Hiding all elements for form', formId);
    $button.removeClass('operaton-show-button').hide();
    $summary.removeClass('operaton-show-summary');
  } catch (error) {
    console.error('Error in hideAllElements:', error);
    $(`#operaton-evaluate-${formId}`).removeClass('operaton-show-button').hide();
    $(`#decision-flow-summary-${formId}`).removeClass('operaton-show-summary');
  }
};

// =============================================================================
// CACHING UTILITIES
// =============================================================================

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

function getCurrentPageCached(formId) {
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
// BUTTON MANAGER
// =============================================================================

window.operatonButtonManager = window.operatonButtonManager || {
  originalTexts: new Map(),
  buttonCache: new Map(),

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

  storeOriginalText: function ($button, formId) {
    const buttonId = `form_${formId}`;
    if (this.originalTexts.has(buttonId)) {
      return this.originalTexts.get(buttonId);
    }

    let originalText = $button.attr('data-original-text') || $button.val() || $button.attr('value') || 'Evaluate';

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

  setEvaluatingState: function ($button, formId) {
    this.storeOriginalText($button, formId);
    $button.val('Evaluating...').prop('disabled', true).addClass('operaton-evaluating');
  },

  restoreOriginalState: function ($button, formId) {
    const originalText = this.getOriginalText(formId);
    $button
      .val(originalText)
      .prop('value', originalText)
      .attr('value', originalText)
      .prop('disabled', false)
      .removeClass('operaton-evaluating');

    this.buttonCache.delete(`button_${formId}`);

    setTimeout(() => {
      $button.val(originalText).prop('disabled', false);
    }, 100);

    setTimeout(() => {
      if ($button.val() !== originalText || $button.prop('disabled')) {
        $button.val(originalText).prop('disabled', false);
      }
    }, 1000);
  },

  clearCache: function (formId) {
    if (formId) {
      this.buttonCache.delete(`button_${formId}`);
    } else {
      this.buttonCache.clear();
    }
  },
};

// =============================================================================
// FIXED RESULT FIELD MANAGEMENT
// =============================================================================

function getResultFieldIds(formId) {
  const config = getFormConfigCached(formId);
  const resultFieldIds = [];

  if (!config) {
    console.warn(`No configuration found for form ${formId}`);
    return resultFieldIds;
  }

  // Primary: result_field_ids from config (most reliable)
  if (config.result_field_ids && Array.isArray(config.result_field_ids)) {
    config.result_field_ids.forEach(fieldId => {
      const normalizedId = parseInt(fieldId);
      if (!isNaN(normalizedId) && !resultFieldIds.includes(normalizedId)) {
        resultFieldIds.push(normalizedId);
      }
    });
  }

  // Secondary: result_mappings (more reliable than field_mappings for results)
  if (config.result_mappings && typeof config.result_mappings === 'object') {
    Object.values(config.result_mappings).forEach(mapping => {
      if (mapping && mapping.field_id) {
        const normalizedId = parseInt(mapping.field_id);
        if (!isNaN(normalizedId) && !resultFieldIds.includes(normalizedId)) {
          resultFieldIds.push(normalizedId);
        }
      }
    });
  }

  // Tertiary: STRICT filtering from field_mappings - only clear result variables
  if (config.field_mappings && typeof config.field_mappings === 'object') {
    Object.entries(config.field_mappings).forEach(([dmnVariable, mapping]) => {
      if (mapping && mapping.field_id) {
        // ONLY include variables that clearly start with result indicators
        if (dmnVariable.startsWith('aanmerking')) {
          const normalizedId = parseInt(mapping.field_id);
          if (!isNaN(normalizedId) && !resultFieldIds.includes(normalizedId)) {
            resultFieldIds.push(normalizedId);
          }
        }
      }
    });
  }

  console.log(`Result field IDs for form ${formId}:`, resultFieldIds);
  return resultFieldIds;
}

function clearAllResultFields(formId, reason) {
  console.log(`CLEARING result fields for form ${formId}: ${reason}`);

  const $ = window.jQuery || window.$;
  if (!$) return;

  const $form = getCachedElement(`#gform_${formId}`);
  const resultFieldIds = getResultFieldIds(formId);

  if (resultFieldIds.length === 0) {
    console.log(`No result fields configured for form ${formId}`);
    return;
  }

  let clearedCount = 0;

  // Enhanced clearing with multiple selector attempts
  resultFieldIds.forEach(fieldId => {
    let fieldCleared = false;

    // Try multiple selectors for each field
    const selectors = [
      `#input_${formId}_${fieldId}`,
      `input[name="input_${formId}_${fieldId}"]`,
      `select[name="input_${formId}_${fieldId}"]`,
      `textarea[name="input_${formId}_${fieldId}"]`,
    ];

    for (const selector of selectors) {
      const $resultField = $form.find(selector);

      if ($resultField.length > 0) {
        $resultField.each(function () {
          const $field = $(this);
          const currentValue = $field.val();

          if (currentValue && currentValue.trim() !== '') {
            console.log(`Clearing field ${fieldId} (${selector}): "${currentValue}"`);
            $field.val('');
            $field.trigger('change');
            $field.trigger('input');
            clearedCount++;
            fieldCleared = true;
          }
        });

        if (fieldCleared) break;
      }
    }

    if (!fieldCleared) {
      console.log(`Result field ${fieldId} not found or already empty`);
    }
  });

  if (clearedCount > 0) {
    console.log(`Successfully cleared ${clearedCount} result fields`);

    // Verification
    setTimeout(() => {
      let verifyCount = 0;
      resultFieldIds.forEach(fieldId => {
        const $field = $form.find(`#input_${formId}_${fieldId}`);
        if ($field.length > 0 && $field.val() && $field.val().trim() !== '') {
          console.warn(`Field ${fieldId} still has value after clearing: "${$field.val()}"`);
        } else {
          verifyCount++;
        }
      });
      console.log(`Verified ${verifyCount}/${resultFieldIds.length} fields are properly cleared`);
    }, 100);
  } else {
    console.log('No result fields needed clearing');
  }
}

function clearResultFieldWithMessage(formId, reason) {
  console.log('🧹 Clearing result field for form:', formId, 'Reason:', reason);
  clearAllResultFields(formId, reason);
  clearStoredResults(formId);
  clearDOMCache(formId);

  if (typeof window.OperatonDecisionFlow !== 'undefined') {
    window.OperatonDecisionFlow.clearCache();
  }
}

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

// =============================================================================
// BUTTON PLACEMENT AND VISIBILITY MANAGEMENT
// =============================================================================

function handleButtonPlacement(formId) {
  const $ = window.jQuery || window.$;
  if (!$) return;

  const config = getFormConfigCached(formId);
  if (!config) return;

  const currentPage = getCurrentPageCached(formId);
  const targetPage = parseInt(config.evaluation_step) || 2;
  const showDecisionFlow = config.show_decision_flow || false;
  const useProcess = config.use_process || false;

  console.log(`Button placement check - Form: ${formId}, Current page: ${currentPage}, Target: ${targetPage}`);

  if (currentPage === targetPage) {
    console.log(`Showing evaluate button for form ${formId}`);
    window.showEvaluateButton(formId);
  } else if (currentPage === targetPage + 1 && showDecisionFlow && useProcess) {
    console.log(`Showing decision flow for form ${formId}`);
    window.showDecisionFlowSummary(formId);
  } else {
    console.log(`Hiding elements for form ${formId}`);
    window.hideAllElements(formId);
  }
}

// =============================================================================
// PAGE CHANGE DETECTION AND HANDLING
// =============================================================================

function setupPageChangeDetection(formId) {
  const $ = window.jQuery || window.$;
  if (!$) return;

  // Initial button placement
  setTimeout(() => {
    handleButtonPlacement(formId);
  }, 100);

  // Hook into Gravity Forms page events
  if (typeof gform !== 'undefined' && gform.addAction) {
    // Remove existing handler
    if (gform.removeAction) {
      gform.removeAction('gform_page_loaded', `operaton_button_${formId}`);
    }

    gform.addAction(
      'gform_page_loaded',
      function (loadedFormId, currentPage) {
        if (loadedFormId == formId) {
          console.log(`Page loaded for form ${formId}: page ${currentPage}`);
          setTimeout(() => {
            handleButtonPlacement(formId);
          }, 200);
        }
      },
      10,
      `operaton_button_${formId}`
    );
  }

  // URL change detection for manual navigation
  let currentUrl = window.location.href;
  const urlCheckInterval = setInterval(() => {
    if (window.location.href !== currentUrl) {
      currentUrl = window.location.href;
      console.log(`URL changed for form ${formId}, updating button placement`);
      setTimeout(() => {
        handleButtonPlacement(formId);
      }, 300);
    }
  }, 500);

  // Store interval for cleanup
  window[`operaton_url_check_${formId}`] = urlCheckInterval;

  // Fallback button placement check
  setTimeout(() => {
    handleButtonPlacement(formId);
  }, 2000);
}

function setupInputChangeMonitoring(formId) {
  const $ = window.jQuery || window.$;
  const $form = $(`#gform_${formId}`);

  if ($form.length === 0) {
    console.log(`Form ${formId} not found for input monitoring`);
    return;
  }

  // Remove ALL existing handlers first
  $form.off('.operaton-clear');

  // Cache result field IDs once to avoid repeated calls
  const resultFieldIds = getResultFieldIds(formId);
  console.log(`Cached result field IDs for form ${formId}:`, resultFieldIds);

  // FIXED: More conservative debounced clearing with navigation awareness
  let debounceTimer = null;
  let lastClearTime = 0;

  function debouncedClear(reason) {
    // Prevent rapid successive clears
    const now = Date.now();
    if (now - lastClearTime < 1000) {
      console.log('Skipping clear - too soon after last clear');
      return;
    }

    if (debounceTimer) {
      window.clearTimeout(debounceTimer);
    }
    debounceTimer = window.setTimeout(() => {
      console.log(`INPUT CHANGE CLEARING: ${reason}`);
      clearAllResultFields(formId, reason);
      clearStoredResults(formId);
      lastClearTime = Date.now();
    }, 500); // Longer debounce to avoid clearing during navigation transitions
  }

  // Monitor regular form fields - but be more careful about when we clear
  $form.on(
    'change.operaton-clear',
    'input[type="text"], input[type="number"], input[type="email"], input[type="date"], select, textarea',
    function () {
      const $field = $(this);
      const fieldId = $field.attr('id');
      const fieldName = $field.attr('name') || fieldId;

      // Skip result fields using cached IDs
      const isResultField = resultFieldIds.some(
        id => fieldId && (fieldId.includes(`input_${formId}_${id}`) || fieldId === `input_${formId}_${id}`)
      );

      if (isResultField) {
        console.log(`Skipping result field change: ${fieldName}`);
        return;
      }

      const fieldValue = $field.val();
      console.log(`INPUT CHANGE DETECTED (change): ${fieldName} = "${fieldValue}"`);
      debouncedClear(`Input changed: ${fieldName}`);
    }
  );

  // For text/number inputs, also monitor 'input' event but with even longer debounce
  $form.on('input.operaton-clear', 'input[type="text"], input[type="number"], input[type="email"]', function () {
    const $field = $(this);
    const fieldId = $field.attr('id');
    const fieldName = $field.attr('name') || fieldId;

    // Skip result fields using cached IDs
    const isResultField = resultFieldIds.some(
      id => fieldId && (fieldId.includes(`input_${formId}_${id}`) || fieldId === `input_${formId}_${id}`)
    );

    if (isResultField) {
      return; // Don't log for result fields
    }

    // Much longer debounce for typing (wait for user to finish)
    if (debounceTimer) {
      window.clearTimeout(debounceTimer);
    }
    debounceTimer = window.setTimeout(() => {
      const finalValue = $field.val();
      console.log(`INPUT TYPING COMPLETE: ${fieldName} = "${finalValue}"`);
      clearAllResultFields(formId, `Input typing complete: ${fieldName}`);
      clearStoredResults(formId);
      lastClearTime = Date.now();
    }, 1500); // Even longer wait for typing
  });

  // Monitor radio buttons with improved logic
  $form.on('change.operaton-clear', 'input[type="radio"]', function () {
    const $radio = $(this);
    const radioName = $radio.attr('name');
    const radioValue = $radio.val();

    // Skip hidden sync fields
    if (radioName && radioName.startsWith('input_')) {
      console.log(`Skipping hidden radio sync field: ${radioName}`);
      return;
    }

    // Ignore changes during radio sync
    if (window.operatonRadioSyncInProgress) {
      console.log(`Skipping radio change during sync: ${radioName}`);
      return;
    }

    if (radioName) {
      console.log(`RADIO CHANGE DETECTED: ${radioName} = "${radioValue}"`);
      debouncedClear(`Radio changed: ${radioName}`);
    }
  });

  // Monitor checkboxes
  $form.on('change.operaton-clear', 'input[type="checkbox"]', function () {
    const $checkbox = $(this);
    const checkboxName = $checkbox.attr('name') || $checkbox.attr('id');
    const isChecked = $checkbox.is(':checked');

    console.log(`CHECKBOX CHANGE DETECTED: ${checkboxName} = ${isChecked}`);
    debouncedClear(`Checkbox changed: ${checkboxName}`);
  });

  console.log(`Enhanced input monitoring active for form ${formId}`);
}

// =============================================================================
// ENHANCED NAVIGATION HANDLING
// =============================================================================

function bindNavigationEventsOptimized(formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for bindNavigationEventsOptimized');
    return;
  }

  const $form = getCachedElement(`#gform_${formId}`);
  const resultFieldIds = getResultFieldIds(formId);

  // Store form state for comparison - EXCLUDE result fields entirely
  let formStateSnapshot = null;

  function captureFormState() {
    const state = {};
    // Only capture actual INPUT fields, completely exclude result fields
    $form.find('input, select, textarea').each(function () {
      const $field = $(this);
      const fieldName = $field.attr('name') || $field.attr('id');

      if (fieldName) {
        // Skip result fields completely from state tracking
        const isResultField = resultFieldIds.some(
          id => fieldName.includes(`input_${formId}_${id}`) || fieldName === `input_${formId}_${id}`
        );

        // Also skip hidden sync fields and other non-user fields
        const isHiddenSyncField = fieldName.startsWith('input_') && $field.attr('type') === 'hidden';
        const isSystemField = fieldName.includes('gform_') || fieldName.includes('honeypot');

        if (!isResultField && !isHiddenSyncField && !isSystemField) {
          if ($field.is(':radio') || $field.is(':checkbox')) {
            state[fieldName] = $field.is(':checked') ? $field.val() : '';
          } else {
            state[fieldName] = $field.val() || '';
          }
        }
      }
    });
    return state;
  }

  function hasFormChanged(oldState, newState) {
    if (!oldState || !newState) {
      console.log('No previous state to compare - treating as NO change for navigation');
      return false; // FIXED: Conservative approach - no clearing on navigation without clear changes
    }

    // Get all unique field names from both states
    const allFields = new Set([...Object.keys(oldState), ...Object.keys(newState)]);

    for (const fieldName of allFields) {
      const oldValue = oldState[fieldName] || '';
      const newValue = newState[fieldName] || '';

      if (oldValue !== newValue) {
        console.log(`ACTUAL form change detected: ${fieldName} changed from "${oldValue}" to "${newValue}"`);
        return true;
      }
    }

    console.log('No actual form changes detected in input fields');
    return false;
  }

  // FIXED: Capture initial state when document is ready, not delayed
  setTimeout(() => {
    if (!formStateSnapshot) {
      formStateSnapshot = captureFormState();
      console.log(
        `Captured initial form state for form ${formId} with ${Object.keys(formStateSnapshot).length} input fields`
      );
    }
  }, 500); // Reasonable delay for form to be ready

  // Remove existing navigation handlers
  $form.off(`.operaton-nav-${formId}`);

  // FIXED: Conservative Previous button handler - only clear on REAL input changes
  $form.on(
    `click.operaton-nav-${formId}`,
    '.gform_previous_button input, .gform_previous_button button, input[value*="Previous"], button:contains("Previous")',
    function (e) {
      console.log('Previous button clicked for form:', formId);

      // CRITICAL FIX: Don't check for changes immediately on button click
      // Instead, preserve results during navigation and only clear if there were actual input changes

      // Wait a moment to see if there are any pending input changes
      setTimeout(() => {
        const currentState = captureFormState();
        const hasChanged = hasFormChanged(formStateSnapshot, currentState);

        if (hasChanged) {
          console.log('REAL form changes detected before navigation - clearing result fields');
          clearAllResultFields(formId, 'Form changed before navigation');
          clearStoredResults(formId);
          // Update snapshot to current state
          formStateSnapshot = currentState;
        } else {
          console.log('NO real changes detected - PRESERVING result fields during navigation');
          // Don't clear anything - this is the key fix
        }

        // Always clear DOM cache for navigation (this is safe)
        clearDOMCache(formId);
      }, 50);
    }
  );

  // FIXED: Much more conservative Gravity Forms page load handler
  if (typeof gform !== 'undefined' && gform.addAction) {
    if (gform.removeAction) {
      gform.removeAction('gform_page_loaded', `operaton_clear_${formId}`);
    }

    gform.addAction(
      'gform_page_loaded',
      function (loadedFormId, currentPage) {
        if (loadedFormId == formId) {
          console.log('Form page loaded for form:', formId, 'page:', currentPage);
          clearDOMCache(formId); // Safe to clear DOM cache

          // CRITICAL FIX: Don't automatically clear on page load
          // Only update the state snapshot for future comparisons
          setTimeout(() => {
            const currentState = captureFormState();

            if (!formStateSnapshot) {
              console.log(`First page load for form ${formId} - capturing initial state`);
              formStateSnapshot = currentState;
            } else {
              // FIXED: Just update the snapshot, don't clear unless there are significant changes
              // AND we can confirm they are user input changes (not navigation artifacts)
              console.log(`Page ${currentPage} loaded - updating state snapshot without clearing`);
              formStateSnapshot = currentState;
            }
          }, 100);
        }
      },
      10,
      `operaton_clear_${formId}`
    );
  }
}

// =============================================================================
// FORM INITIALIZATION (SINGLE VERSION)
// =============================================================================

function simpleFormInitialization(formId) {
  formId = parseInt(formId);

  // Prevent concurrent initialization
  const initKey = `init_${formId}`;
  if (window.operatonInitialized[initKey]) {
    console.log(`Form ${formId} initialization already in progress`);
    return;
  }

  // Prevent duplicate initialization
  if (window.operatonInitialized.forms.has(formId)) {
    console.log(`Form ${formId} already initialized`);
    return;
  }

  const config = getFormConfigCached(formId);
  if (!config) {
    console.log(`No configuration found for form ${formId}`);
    return;
  }

  // Set progress flag
  window.operatonInitialized[initKey] = true;

  console.log(`=== INITIALIZING FORM ${formId} ===`);

  try {
    // Mark as initialized
    window.operatonInitialized.forms.add(formId);
    window.operatonInitialized.performanceStats.successfulInits++;

    const $ = window.jQuery || window.$;
    const $form = $(`#gform_${formId}`);

    if ($form.length > 0) {
      // Use enhanced input monitoring
      setupInputChangeMonitoring(formId);

      // Set up evaluation button events
      bindEvaluationEventsOptimized(formId);

      // Set up enhanced navigation events
      bindNavigationEventsOptimized(formId);

      // Set up page change detection and button placement
      setupPageChangeDetection(formId);

      // Initialize decision flow if enabled
      if (config.show_decision_flow && typeof window.initializeDecisionFlowForForm === 'function') {
        window.initializeDecisionFlowForForm(formId, config);
      }

      // Clear any existing results after initialization - BUT NOT on decision flow page
      setTimeout(() => {
        const currentPage = getCurrentPageCached(formId);
        const targetPage = parseInt(config.evaluation_step) || 2;
        const isDecisionFlowPage = currentPage === targetPage + 1 && config.show_decision_flow;

        if (!isDecisionFlowPage) {
          console.log(`Clearing results on initialization - Form ${formId}, page ${currentPage}`);
          clearResultFieldWithMessage(formId, 'Form initialized');
        } else {
          console.log(`PRESERVING results on decision flow page - Form ${formId}, page ${currentPage}`);
          // Only clear stored data, not the actual result fields
          clearStoredResults(formId);
        }
      }, 500);
    }

    console.log(`=== FORM ${formId} INITIALIZATION COMPLETE ===`);
  } catch (error) {
    console.error(`Error initializing form ${formId}:`, error);
    window.operatonInitialized.forms.delete(formId);
  } finally {
    delete window.operatonInitialized[initKey];
  }
}

function simplifiedFormDetection() {
  const $ = window.jQuery || window.$;
  if (!$) return;

  // Prevent concurrent detection
  if (window.operatonInitialized.initInProgress) {
    console.log('Form detection already in progress, skipping');
    return;
  }

  window.operatonInitialized.initInProgress = true;

  try {
    console.log('🔍 Running simplified form detection...');
    window.operatonInitialized.performanceStats.initializationAttempts++;

    $('form[id^="gform_"]').each(function () {
      const $form = $(this);
      const formId = parseInt($form.attr('id').replace('gform_', ''));

      if (formId && !isNaN(formId)) {
        const config = getFormConfigCached(formId);
        if (config) {
          console.log(`🎯 DMN-enabled form detected: ${formId}`);
          simpleFormInitialization(formId);
        }
      }
    });

    console.log('✅ Simplified detection complete');
  } finally {
    window.operatonInitialized.initInProgress = false;
  }
}

function initOperatonDMN() {
  // Prevent duplicate global initialization
  if (window.operatonInitialized.globalInit) {
    console.log('Global initialization already complete, skipping');
    return;
  }

  console.log('🚀 Starting Operaton DMN initialization...');

  // Hook into Gravity Forms events if available
  if (typeof gform !== 'undefined' && gform.addAction) {
    // Remove any existing handlers first
    if (gform.removeAction) {
      gform.removeAction('gform_post_render', 'operaton_form_render');
    }

    gform.addAction(
      'gform_post_render',
      function (formId) {
        console.log('📋 Gravity Form rendered:', formId);
        clearDOMCache(formId);

        // Small delay to ensure DOM is fully rendered
        setTimeout(() => {
          simpleFormInitialization(formId);
        }, 100);
      },
      10,
      'operaton_form_render'
    );

    console.log('✅ Hooked into gform_post_render action');
  }

  // Initial form detection
  setTimeout(() => {
    simplifiedFormDetection();
  }, 200);

  // Set global flag
  window.operatonInitialized.globalInit = true;
}

function resetFormSystem() {
  console.log('🧹 Resetting form system...');

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
  domQueryCache.clear();
  formConfigCache.clear();
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

  // Re-initialize
  setTimeout(() => {
    simplifiedFormDetection();
  }, 500);

  console.log('✅ System reset complete');
}

// =============================================================================
// EVALUATION HANDLING
// =============================================================================

function findResultFieldOnCurrentPageOptimized(formId) {
  const $ = window.jQuery || window.$;
  if (!$) return null;

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

  // Fallback detection strategies
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

function handleEvaluateClick($button) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.error('jQuery not available for handleEvaluateClick');
    showError('System error: jQuery not available. Please refresh the page.');
    return;
  }

  const formId = $button.data('form-id');
  const configId = $button.data('config-id');

  console.log('Button clicked for form:', formId, 'config:', configId);

  const config = getFormConfigCached(formId);
  if (!config) {
    console.error('Configuration not found for form:', formId);
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

    // Collect form data
    const formData = {};
    let hasRequiredData = true;
    const missingFields = [];

    Object.entries(fieldMappings).forEach(([dmnVariable, mapping]) => {
      const fieldId = mapping.field_id;
      console.log('Processing variable:', dmnVariable, 'Field ID:', fieldId);

      let value = getGravityFieldValueOptimized(formId, fieldId);
      console.log('Found raw value for field', fieldId + ':', value);

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

      console.log('Processed value for', dmnVariable + ':', value);
      formData[dmnVariable] = value;
    });

    // Apply conditional logic for partner-related fields
    const isAlleenstaand = formData['aanvragerAlleenstaand'];
    console.log('User is single (alleenstaand):', isAlleenstaand);

    if (isAlleenstaand === 'true' || isAlleenstaand === true) {
      console.log('User is single, setting geboortedatumPartner to null');
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
      console.error('operaton_ajax not available');
      showError('System error: AJAX configuration not loaded. Please refresh the page.');
      window.operatonButtonManager.restoreOriginalState($button, formId);
      return;
    }

    console.log('Making AJAX call to:', window.operaton_ajax.url);

    // Make AJAX call
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
        console.log('AJAX success:', response);

        if (response.success && response.results) {
          console.log('Results received:', response.results);

          let populatedCount = 0;
          const resultSummary = [];

          Object.entries(response.results).forEach(([dmnResultField, resultData]) => {
            const resultValue = resultData.value;
            const fieldId = resultData.field_id;

            console.log('Processing result:', dmnResultField, 'Value:', resultValue, 'Field ID:', fieldId);

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
              console.log('Populated field', fieldId, 'with result:', resultValue);
            } else {
              console.warn('No field found for result:', dmnResultField, 'Field ID:', fieldId);
            }
          });

          // Store process instance ID if provided
          if (response.process_instance_id) {
            storeProcessInstanceId(formId, response.process_instance_id);
            console.log('Stored process instance ID:', response.process_instance_id);
          }

          if (populatedCount > 0) {
            let message = `Results populated (${populatedCount}): ${resultSummary.join(', ')}`;

            if (response.process_instance_id && config.show_decision_flow) {
              message += '\n\nComplete the form to see the detailed decision flow summary on the final page.';

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
          console.error('Invalid response structure:', response);
          showError('No results received from evaluation.');
        }
      },
      error: function (xhr, status, error) {
        console.error('AJAX Error:', error);
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
// VALIDATION AND UTILITIES
// =============================================================================

function validateForm(formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for validateForm');
    return true;
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
}

function convertDateFormat(dateStr, fieldName) {
  if (!dateStr || dateStr === null) {
    return null;
  }

  console.log('Converting date for field:', fieldName, 'Input:', dateStr);

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
    console.error('Error parsing date:', dateStr, e);
  }

  return dateStr;
}

function findFieldOnCurrentPageOptimized(formId, fieldId) {
  const cacheKey = `field_${formId}_${fieldId}`;
  const cached = domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < 3000) {
    return cached.element;
  }

  const $ = window.jQuery || window.$;
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

function getGravityFieldValueOptimized(formId, fieldId) {
  const $ = window.jQuery || window.$;
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

  // Check using DMN variable name
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

function storeProcessInstanceId(formId, processInstanceId) {
  if (typeof Storage !== 'undefined') {
    sessionStorage.setItem(`operaton_process_${formId}`, processInstanceId);
  }
  window[`operaton_process_${formId}`] = processInstanceId;
  console.log('Stored process instance ID for form', formId + ':', processInstanceId);
}

// =============================================================================
// UI FEEDBACK FUNCTIONS
// =============================================================================

function showSuccessNotification(message) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for showSuccessNotification');
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

function showError(message) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for showError');
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
// EVENT BINDING
// =============================================================================

function bindEvaluationEventsOptimized(formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for bindEvaluationEventsOptimized');
    return;
  }

  const selector = `.operaton-evaluate-btn[data-form-id="${formId}"]`;

  $(document).off(`click.operaton-${formId}`, selector);
  $(document).on(`click.operaton-${formId}`, selector, function (e) {
    e.preventDefault();
    console.log('🎯 Button clicked for form:', formId);
    handleEvaluateClick($(this));
  });

  console.log('✅ Event handler bound for form:', formId);
}

// =============================================================================
// AJAX SETUP
// =============================================================================

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

// =============================================================================
// MAIN INITIALIZATION (SINGLE VERSION)
// =============================================================================

function waitForJQuery(callback, maxAttempts = 50) {
  let attempts = 0;

  function check() {
    attempts++;

    if (typeof jQuery !== 'undefined') {
      console.log(`✅ jQuery found after ${attempts} attempts`);
      callback();
    } else if (attempts < maxAttempts) {
      if (attempts % 10 === 0) {
        console.log(`⏳ Still waiting for jQuery... attempt ${attempts}`);
      }
      const delay = Math.min(100 * Math.pow(1.1, attempts), 1000);
      setTimeout(check, delay);
    } else {
      console.error(`❌ jQuery not found after ${maxAttempts} attempts`);
    }
  }
  check();
}

// SINGLE MAIN INITIALIZATION - NO DUPLICATES
(function () {
  'use strict';

  // Ensure we only run once per page load
  if (window.operatonMainInitCalled) {
    console.log('Main initialization already called, skipping');
    return;
  }
  window.operatonMainInitCalled = true;

  function performInitialization($) {
    console.log('✅ jQuery available, version:', $.fn.jquery);

    // Wait for operaton_ajax and initialize
    waitForOperatonAjax(() => {
      const initStartTime = performance.now();
      console.log('🚀 Initializing Operaton DMN...');

      window.operatonInitialized.jQueryReady = true;
      initOperatonDMN();

      // Secondary detection for late-loading forms
      setTimeout(() => {
        if (!window.operatonInitialized.initInProgress) {
          simplifiedFormDetection();
        }
      }, 1000);

      const initEndTime = performance.now();
      window.operatonInitialized.performanceStats.totalProcessingTime += initEndTime - initStartTime;

      console.log(`🎉 Operaton DMN initialization complete in ${(initEndTime - initStartTime).toFixed(2)}ms`);
    });

    // Cleanup on page unload
    $(window).on('beforeunload', () => {
      console.log('🧹 Cleaning up Operaton DMN...');
      window.operatonInitialized.forms.clear();
      window.operatonInitialized.globalInit = false;
      window.operatonInitialized.initInProgress = false;
      domQueryCache.clear();
      formConfigCache.clear();
      if (window.operatonButtonManager) {
        window.operatonButtonManager.clearCache();
      }
    });

    $(document).ready(() => {
      console.log('📋 Document ready - initialization active');
    });
  }

  // Initialize based on jQuery availability
  if (typeof jQuery !== 'undefined') {
    console.log('✅ jQuery available immediately');
    performInitialization(jQuery);
  } else {
    console.log('⏳ jQuery not immediately available - waiting...');
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
      console.log('Window load: Attempting late initialization...');
      if (typeof jQuery !== 'undefined') {
        simplifiedFormDetection();
      } else {
        console.warn('Window load: jQuery still not available');
      }
    } else {
      console.log('Window load: Initialization already complete');
    }
  }, 1000);
});

document.addEventListener('DOMContentLoaded', () => {
  console.log('DOM Content Loaded - checking initialization state');
  if (typeof jQuery !== 'undefined' && !window.operatonInitialized.globalInit) {
    console.log('Early DOM initialization trigger');
    setTimeout(simplifiedFormDetection, 100);
  }
});

// =============================================================================
// GLOBAL DEBUGGING FUNCTIONS
// =============================================================================

if (typeof window !== 'undefined') {
  window.operatonDebugFixed = function () {
    const stats = window.operatonInitialized.performanceStats;

    console.log('Debug Info:', {
      initializationState: window.operatonInitialized,
      performanceStats: stats,
      cacheStats: {
        domCache: domQueryCache.size,
        configCache: formConfigCache.size,
        buttonCache: window.operatonButtonManager.buttonCache.size,
      },
      status: 'fixed - single initialization, smart clearing',
    });

    return {
      status: 'fixed',
      initialized: window.operatonInitialized.globalInit,
      performance: stats,
    };
  };

  window.operatonForceCleanup = resetFormSystem;

  window.operatonReinitialize = function () {
    console.log('MANUAL REINIT: Starting re-initialization');
    resetFormSystem();
  };
}

console.log('Operaton DMN frontend script loaded - FIXED VERSION');
