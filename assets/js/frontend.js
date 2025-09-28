/**
 * FIXED: Operaton DMN Frontend Script
 * Single initialization, proper result field detection, smart navigation clearing
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

operatonDebugFrontend('Frontend script loading...');

// =============================================================================
// MODULE DEPENDENCY CHECK
// =============================================================================

// Ensure core module is loaded first
if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.core) {
  operatonDebugMinimal('Frontend', 'ERROR: Core module not loaded! This script requires frontend-core.js');
  throw new Error('Operaton DMN: Core module must be loaded before main frontend script');
}

// Ensure UI module is loaded
if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.ui) {
  operatonDebugMinimal('Frontend', 'ERROR: UI module not loaded! This script requires frontend-ui.js');
  throw new Error('Operaton DMN: UI module must be loaded before main frontend script');
}

// Ensure Forms module is loaded
if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.forms) {
  operatonDebugMinimal('Frontend', 'ERROR: Forms module not loaded! This script requires frontend-forms.js');
  throw new Error('Operaton DMN: Forms module must be loaded before main frontend script');
}

// Ensure Utils module is loaded
if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.utils) {
  operatonDebugMinimal('Frontend', 'ERROR: Utils module not loaded! This script requires frontend-utils.js');
  throw new Error('Operaton DMN: Utils module must be loaded before main frontend script');
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

    const $button = window.getCachedElement(`#operaton-evaluate-${formId}`);
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

function clearAllResultFields(formId, reason) {
   operatonDebugVerbose('Frontend', `CLEARING result fields for form ${formId}: ${reason}`);

  const $ = window.jQuery || window.$;
  if (!$) return;

  const $form = window.getCachedElement(`#gform_${formId}`);
  const resultFieldIds = window.getResultFieldIds(formId);

  if (resultFieldIds.length === 0) {
     operatonDebugVerbose('Frontend', `No result fields configured for form ${formId}`);
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
             operatonDebugVerbose('Frontend', `Clearing field ${fieldId} (${selector}): "${currentValue}"`);
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
       operatonDebugMinimal('Frontend', `Result field ${fieldId} not found or already empty`);
    }
  });

  if (clearedCount > 0) {
     operatonDebugVerbose('Frontend', `Successfully cleared ${clearedCount} result fields`);

    // Verification
    setTimeout(() => {
      let verifyCount = 0;
      resultFieldIds.forEach(fieldId => {
        const $field = $form.find(`#input_${formId}_${fieldId}`);
        if ($field.length > 0 && $field.val() && $field.val().trim() !== '') {
          operatonDebugMinimal('Frontend', `Field ${fieldId} still has value after clearing: "${$field.val()}"`);
        } else {
          verifyCount++;
        }
      });
       operatonDebugVerbose('Frontend', `Verified ${verifyCount}/${resultFieldIds.length} fields are properly cleared`);
    }, 100);
  } else {
     operatonDebugVerbose('Frontend', 'No result fields needed clearing');
  }
}

function clearResultFieldWithMessage(formId, reason) {
  clearAllResultFields(formId, reason);
  clearStoredResults(formId);
  window.clearDOMCache(formId);

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
}

/**
 * Non-blocking input monitoring that preserves all input functionality
 */

function setupInputChangeMonitoring(formId) {
  const $ = window.jQuery || window.$;
  const $form = $(`#gform_${formId}`);

  if ($form.length === 0) {
     operatonDebugMinimal('Frontend', `Form ${formId} not found for input monitoring`);
    return;
  }

  // Remove ALL existing handlers first
  $form.off('.operaton-clear');

  // Cache result field IDs once
  const resultFieldIds = window.getResultFieldIds(formId);
   operatonDebugVerbose('Frontend', `Result field IDs for monitoring form ${formId}:`, resultFieldIds);

  // State tracking for intelligent clearing
  let debounceTimer = null;
  let lastClearTime = 0;
  let inputActivity = new Map(); // Track input activity per field

  function shouldClearResults(fieldName, fieldValue, eventType) {
    // Never clear during safeguard periods
    if (window.operatonPopulatingResults || window.operatonFieldLogicUpdating) {
      return false;
    }

    // Don't clear too frequently
    if (Date.now() - lastClearTime < 1000) {
      return false;
    }

    return true;
  }

  function scheduleResultsClear(reason, delay = 500) {
    if (!shouldClearResults()) {
      return;
    }

    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
      // Final check before clearing
      if (window.operatonPopulatingResults || window.operatonFieldLogicUpdating) {
         operatonDebugVerbose('Frontend', 'SAFEGUARD: Canceling clear due to active operations');
        return;
      }

       operatonDebugVerbose('Frontend', `CLEARING RESULTS: ${reason}`);
      clearAllResultFields(formId, reason);
      clearStoredResults(formId);
      lastClearTime = Date.now();
    }, delay);
  }

  function isMonitorableField($field) {
    const fieldId = $field.attr('id');
    const fieldName = $field.attr('name') || fieldId;

    if (!fieldName) return false;

    // Skip result fields
    const isResult = resultFieldIds.some(
      id => fieldId && (fieldId.includes(`input_${formId}_${id}`) || fieldId === `input_${formId}_${id}`)
    );

    if (isResult) {
      return false;
    }

    // Skip system fields
    if (fieldName.includes('gform_') || fieldName.includes('honeypot')) {
      return false;
    }

    // Skip hidden sync fields (but not all hidden fields)
    if (fieldName.startsWith('input_') && $field.attr('type') === 'hidden') {
      return false;
    }

    return true;
  }

  // CRITICAL FIX: Use completely passive event monitoring
  // These handlers do NOT call preventDefault() or stopPropagation()

  // Method 1: Monitor via 'change' events (when user finishes with field)
  $form.on('change.operaton-clear', 'input, select, textarea', function(event) {
    const $field = $(this);

    if (!isMonitorableField($field)) {
      return;
    }

    const fieldName = $field.attr('name') || $field.attr('id');
    const fieldValue = $field.val();

     operatonDebugVerbose('Frontend', `CHANGE DETECTED: ${fieldName} = "${fieldValue}"`);
    scheduleResultsClear(`Field changed: ${fieldName}`, 300);
  });

  // Method 2: Monitor typing completion with very long delay
  $form.on('input.operaton-clear', 'input[type="text"], input[type="number"], input[type="email"]', function(event) {
    const $field = $(this);

    if (!isMonitorableField($field)) {
      return;
    }

    const fieldName = $field.attr('name') || $field.attr('id');

    // Track input activity
    inputActivity.set(fieldName, Date.now());

    // Very long delay - only clear after user completely stops typing
    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
      // Check if user is still typing in ANY field
      const now = Date.now();
      let stillTyping = false;

      for (let [field, lastActivity] of inputActivity) {
        if (now - lastActivity < 2000) { // If any field was active in last 2 seconds
          stillTyping = true;
          break;
        }
      }

      if (!stillTyping && shouldClearResults()) {
        const finalValue = $field.val();
         operatonDebugVerbose('Frontend', `TYPING SESSION COMPLETED: ${fieldName} = "${finalValue}"`);
        clearAllResultFields(formId, `Typing completed: ${fieldName}`);
        clearStoredResults(formId);
        lastClearTime = Date.now();
      }
    }, 4000); // 4 second delay - very conservative
  });

  // Method 3: Monitor radio buttons (these can have immediate clearing)
  $form.on('change.operaton-clear', 'input[type="radio"]', function(event) {
    const $radio = $(this);
    const radioName = $radio.attr('name');
    const radioValue = $radio.val();

    // Skip system radio fields
    if (radioName && radioName.startsWith('input_')) {
      return;
    }

    // Skip during sync operations
    if (window.operatonRadioSyncInProgress || window.operatonPopulatingResults) {
      return;
    }

     operatonDebugVerbose('Frontend', `RADIO SELECTED: ${radioName} = "${radioValue}"`);
    scheduleResultsClear(`Radio selected: ${radioName}`, 200);
  });

  // Method 4: Monitor checkboxes
  $form.on('change.operaton-clear', 'input[type="checkbox"]', function(event) {
    const $checkbox = $(this);
    const checkboxName = $checkbox.attr('name') || $checkbox.attr('id');
    const isChecked = $checkbox.is(':checked');

    if (!isMonitorableField($checkbox)) {
      return;
    }

     operatonDebugVerbose('Frontend', `CHECKBOX TOGGLED: ${checkboxName} = ${isChecked}`);
    scheduleResultsClear(`Checkbox changed: ${checkboxName}`, 200);
  });

   operatonDebugVerbose('Frontend', `NON-BLOCKING input monitoring active for form ${formId} - all fields monitorable, input unrestricted`);
}

/**
 * Make field logic completely non-blocking
 */
if (window.OperatonFieldLogic) {
  // Override the setupEventListeners to be completely passive
  window.OperatonFieldLogic.setupEventListeners = function(formId, mapping, $form) {
    const self = this;

    // Partner field - use only blur and change, never input
    const $partnerField = $form.find(`#input_${formId}_${mapping.partnerField}`);
    if ($partnerField.length > 0) {
      $partnerField.off('.fieldlogic');

      // Only respond to blur (when user leaves field) and change (when value actually changes)
      $partnerField.on('blur.fieldlogic change.fieldlogic', function() {
        const $ = window.jQuery || window.$; // ← ADD THIS LINE
        if (!$) return; // ← ADD THIS SAFETY CHECK

        // Don't interfere during active typing or focus
        if ($(this).is(':focus')) {
          return;
        }

        setTimeout(() => {
          if (!window.operatonPopulatingResults && !window.operatonFieldLogicUpdating) {
            self.updateAlleenstaandLogic(formId, mapping, $form);
          }
        }, 100);
      });
    }

    // Child field - same approach
    const $childField = $form.find(`#input_${formId}_${mapping.childField}`);
    if ($childField.length > 0) {
      $childField.off('.fieldlogic');

      $childField.on('blur.fieldlogic change.fieldlogic', function() {
        const $ = window.jQuery || window.$; // ← ADD THIS LINE
        if (!$) return; // ← ADD THIS SAFETY CHECK

        if ($(this).is(':focus')) {
          return;
        }

        setTimeout(() => {
          if (!window.operatonPopulatingResults && !window.operatonFieldLogicUpdating) {
            self.updateChildrenLogic(formId, mapping, $form);
          }
        }, 100);
      });
    }

     operatonDebugVerbose('Frontend', `NON-BLOCKING field logic events set up for form ${formId}`);
  };

   operatonDebugVerbose('Frontend', "Field logic updated to be completely non-blocking");
}

/**
 * TEST FUNCTION: Verify input functionality
 */
window.testInputFunctionality = function(formId = 8) {
  const $ = jQuery;
  const fields = [
    {id: 14, name: 'partner_geslachtsnaam'},
    {id: 16, name: 'kind_geboorteplaats'}
  ];

   operatonDebugVerbose('Frontend', "=== TESTING INPUT FUNCTIONALITY ===");

  fields.forEach(field => {
    const $field = $(`#input_${formId}_${field.id}`);
     operatonDebugVerbose('Frontend', `Field ${field.name} (${field.id}):`);
     operatonDebugVerbose('Frontend', `  Found: ${$field.length > 0}`);
     operatonDebugVerbose('Frontend', `  Disabled: ${$field.prop('disabled')}`);
     operatonDebugVerbose('Frontend', `  Readonly: ${$field.prop('readonly')}`);
     operatonDebugVerbose('Frontend', `  Current value: "${$field.val()}"`);
     operatonDebugVerbose('Frontend', `  Event handlers: ${Object.keys($._data($field[0], 'events') || {}).join(', ') || 'none'}`);
  });

  return "Test complete - check console output";
};

// =============================================================================
// FIXED ENHANCED NAVIGATION HANDLING - Preserves results during navigation
// The issue was that result fields are being included in state comparison
// =============================================================================

function bindNavigationEventsOptimized(formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for bindNavigationEventsOptimized');
    return;
  }

  const $form = window.getCachedElement(`#gform_${formId}`);
  const resultFieldIds = window.getResultFieldIds(formId);

  // Store form state for comparison - EXCLUDE result fields entirely
  let formStateSnapshot = null;
  let navigationInProgress = false;

  function captureFormState() {
    const state = {};
    // Only capture actual INPUT fields, completely exclude result fields
    $form.find('input, select, textarea').each(function () {
      const $field = $(this);
      const fieldName = $field.attr('name') || $field.attr('id');

      if (fieldName) {
        // CRITICAL FIX: More thorough result field exclusion
        const isResultField = resultFieldIds.some(id => {
          // Check multiple patterns for result field identification
          return (
            fieldName.includes(`input_${formId}_${id}`) ||
            fieldName === `input_${formId}_${id}` ||
            fieldName === `input_${id}` ||
            fieldName.endsWith(`_${id}`)
          );
        });

        // Also skip hidden sync fields and other non-user fields
        const isHiddenSyncField = fieldName.startsWith('input_') && $field.attr('type') === 'hidden';
        const isSystemField = fieldName.includes('gform_') || fieldName.includes('honeypot');

        // ADDITIONAL: Skip fields that contain result variable names
        const isResultVariableField = fieldName.includes('aanmerking') || fieldName.includes('result');

        if (!isResultField && !isHiddenSyncField && !isSystemField && !isResultVariableField) {
          if ($field.is(':radio') || $field.is(':checkbox')) {
            state[fieldName] = $field.is(':checked') ? $field.val() : '';
          } else {
            state[fieldName] = $field.val() || '';
          }
        }
      }
    });

     operatonDebugVerbose('Frontend', 'Captured form state (excluding result fields):', Object.keys(state));
    return state;
  }

  function hasActualFormChanges(oldState, newState) {
    if (!oldState || !newState) {
       operatonDebugVerbose('Frontend', 'No previous state to compare - treating as NO change for navigation');
      return false;
    }

    // Get all unique field names from both states
    const allFields = new Set([...Object.keys(oldState), ...Object.keys(newState)]);
    const changedFields = [];

    for (const fieldName of allFields) {
      const oldValue = oldState[fieldName] || '';
      const newValue = newState[fieldName] || '';

      if (oldValue !== newValue) {
        // ADDITIONAL FILTER: Ignore changes that are likely navigation artifacts
        const isLikelyNavigationArtifact =
          ((oldValue === '' && newValue !== '') || (oldValue !== '' && newValue === '')) && navigationInProgress;

        // ADDITIONAL FILTER: Double-check this isn't a result field
        const isResultFieldChange = resultFieldIds.some(
          id => fieldName.includes(`_${id}`) || fieldName.includes(`input_${formId}_${id}`)
        );

        if (!isLikelyNavigationArtifact && !isResultFieldChange) {
          changedFields.push({
            field: fieldName,
            from: oldValue,
            to: newValue,
          });
        }
      }
    }

    if (changedFields.length > 0) {
       operatonDebugVerbose('Frontend', 'ACTUAL form changes detected (non-result fields):', changedFields);
      return true;
    }

     operatonDebugVerbose('Frontend', 'No actual USER INPUT changes detected');
    return false;
  }

  // Capture initial state when document is ready
  setTimeout(() => {
    if (!formStateSnapshot) {
      formStateSnapshot = captureFormState();
       operatonDebugVerbose('Frontend',
        `Captured initial form state for form ${formId} with ${
          Object.keys(formStateSnapshot).length
        } input fields (result fields excluded)`
      );
    }
  }, 500);

  // Remove existing navigation handlers
  $form.off(`.operaton-nav-${formId}`);

  // FIXED: Conservative Previous button handler
  $form.on(
    `click.operaton-nav-${formId}`,
    '.gform_previous_button input, .gform_previous_button button, input[value*="Previous"], button:contains("Previous")',
    function (e) {
       operatonDebugFrontend('Frontend', 'Previous button clicked for form:', formId);
      navigationInProgress = true;

      const currentState = captureFormState();
      const hasChanged = hasActualFormChanges(formStateSnapshot, currentState);

      if (hasChanged) {
        clearAllResultFields(formId, 'User input changed before navigation');
        clearStoredResults(formId);
        formStateSnapshot = currentState;
      }

      // Always safe to clear DOM cache
      window.clearDOMCache(formId);

      // Reset navigation flag after delay
      setTimeout(() => {
        navigationInProgress = false;
      }, 1000);
    }
  );

  // FIXED: Conservative Gravity Forms page load handler
  if (typeof gform !== 'undefined' && gform.addAction) {
    if (gform.removeAction) {
      gform.removeAction('gform_page_loaded', `operaton_clear_${formId}`);
    }

    gform.addAction(
      'gform_page_loaded',
      function (loadedFormId, currentPage) {
        if (loadedFormId == formId) {
           operatonDebugVerbose('Frontend', 'Form page loaded for form:', formId, 'page:', currentPage);
          navigationInProgress = true;

          window.clearDOMCache(formId); // Safe to clear DOM cache

          // Update state snapshot without clearing result fields
          setTimeout(() => {
            const currentState = captureFormState();

            if (!formStateSnapshot) {
               operatonDebugVerbose('Frontend', `First page load for form ${formId} - capturing initial state`);
              formStateSnapshot = currentState;
            } else {
               operatonDebugVerbose('Frontend', `Page ${currentPage} loaded - updating state snapshot WITHOUT clearing results`);

              // Just update snapshot after navigation completes
              setTimeout(() => {
                formStateSnapshot = captureFormState();
                navigationInProgress = false;
              }, 200);
            }
          }, 100);
        }
      },
      10,
      `operaton_clear_${formId}`
    );
  }

  // ENHANCED: Input change monitoring that excludes result fields
  let changeTimeout;

  $form.on('change input', 'input, select, textarea', function () {
    const $field = $(this);
    const fieldName = $field.attr('name') || $field.attr('id');

    // Skip if it's a result field or during navigation
    const isResultField = resultFieldIds.some(
      id =>
        fieldName &&
        (fieldName.includes(`input_${formId}_${id}`) ||
          fieldName === `input_${formId}_${id}` ||
          fieldName.includes(`_${id}`))
    );

    if (isResultField || navigationInProgress || window.operatonPopulatingResults) {
       operatonDebugVerbose('Frontend',
        `Skipping change handler: ${fieldName} (result field: ${isResultField}, navigation: ${navigationInProgress}, populating: ${window.operatonPopulatingResults})`
      );
      return;
    }

    // Clear any existing timeout
    if (changeTimeout) {
      clearTimeout(changeTimeout);
    }

    // Set a debounced check for actual changes
    changeTimeout = setTimeout(() => {
      if (!navigationInProgress && !window.operatonPopulatingResults) {
        const currentState = captureFormState();
        const hasChanged = hasActualFormChanges(formStateSnapshot, currentState);

        if (hasChanged) {
           operatonDebugVerbose('Frontend', `USER INPUT change detected: ${fieldName} - clearing results`);
          clearAllResultFields(formId, `User input changed: ${fieldName}`);
          clearStoredResults(formId);
          formStateSnapshot = currentState;
        }
      }
    }, 300);
  });
}

// =============================================================================
// EVALUATION HANDLING
// =============================================================================

function findResultFieldOnCurrentPageOptimized(formId) {
  const $ = window.jQuery || window.$;
  if (!$) return null;

  const cacheKey = `result_field_${formId}`;
  const cached = window.operatonCaches.domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < 3000) {
    return cached.element;
  }

  const $form = window.getCachedElement(`#gform_${formId}`);
  const config = window.getFormConfigCached(formId);

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
        window.operatonCaches.domQueryCache.set(cacheKey, {
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
      window.operatonCaches.domQueryCache.set(cacheKey, {
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
    operatonDebugMinimal('Frontend', 'jQuery not available for handleEvaluateClick');
    window.showError('System error: jQuery not available. Please refresh the page.');
    return;
  }

  const formId = $button.data('form-id');
  const configId = $button.data('config-id');

  // CRITICAL: Prevent duplicate processing
  const lockKey = `eval_${formId}_${configId}`;
  if (window.operatonProcessingLock[lockKey]) {
     operatonDebugVerbose('Frontend', '🔒 Duplicate evaluation blocked for form:', formId);
    return;
  }

  // Set processing lock
  window.operatonProcessingLock[lockKey] = true;

   operatonDebugFrontend('Frontend', 'Button clicked for form:', formId, 'config:', configId);

  const config = window.getFormConfigCached(formId);
  if (!config) {
    operatonDebugMinimal('Frontend', 'Configuration not found for form:', formId);
    window.showError('Configuration error. Please contact the administrator.');
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
      window.showError('Please fill in all required fields before evaluation.');
      return;
    }

    // Collect form data
    const formData = {};
    let hasRequiredData = true;
    const missingFields = [];

    Object.entries(fieldMappings).forEach(([dmnVariable, mapping]) => {
      const fieldId = mapping.field_id;

      let value = getGravityFieldValueOptimized(formId, fieldId);

      // Handle date field conversions
      if (
        dmnVariable.toLowerCase().includes('datum') ||
        dmnVariable.toLowerCase().includes('date') ||
        ['dagVanAanvraag', 'geboortedatumAanvrager', 'geboortedatumPartner'].includes(dmnVariable)
      ) {
        if (value !== null && value !== '' && value !== undefined) {
          value = window.convertDateFormat(value, dmnVariable);
        }
      }

      formData[dmnVariable] = value;
    });

    // Apply conditional logic for partner-related fields
    const isAlleenstaand = formData['aanvragerAlleenstaand'];
     operatonDebugVerbose('Frontend', 'User is single (alleenstaand):', isAlleenstaand);

    if (isAlleenstaand === 'true' || isAlleenstaand === true) {
       operatonDebugVerbose('Frontend', 'User is single, setting geboortedatumPartner to null');
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
          window.showError(`Invalid data type for field ${dmnVariable}. Expected: ${mapping.type}`);
          return false;
        }
      }
    });

    if (!hasRequiredData) {
      window.showError(`Please fill in all required fields: ${missingFields.join(', ')}`);
      return;
    }

    // Use centralized button manager for evaluating state
    window.operatonButtonManager.setEvaluatingState($button, formId);

    // Check if operaton_ajax is available
    if (typeof window.operaton_ajax === 'undefined') {
      operatonDebugMinimal('Frontend', 'operaton_ajax not available');
      window.showError('System error: AJAX configuration not loaded. Please refresh the page.');
      window.operatonButtonManager.restoreOriginalState($button, formId);
      return;
    }

     operatonDebugFrontend('Frontend', 'Making AJAX call to:', window.operaton_ajax.url);

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
         operatonDebugFrontend('Frontend', 'AJAX success:', response);

        if (response.success && response.results) {
           operatonDebugVerbose('Frontend', 'Results received:', response.results);

          // 🚩 Set safeguard flag
          window.operatonPopulatingResults = true;
           operatonDebugVerbose('Frontend', '🛡️ SAFEGUARD: Result population started - blocking change handlers');

          let populatedCount = 0;
          const resultSummary = [];

          Object.entries(response.results).forEach(([dmnResultField, resultData]) => {
            const resultValue = resultData.value;
            const fieldId = resultData.field_id;

            let $resultField = null;

            if (fieldId) {
              $resultField = window.findFieldOnCurrentPageOptimized(formId, fieldId);
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
            } else {
              operatonDebugMinimal('Frontend', 'No field found for result:', dmnResultField, 'Field ID:', fieldId);
            }
          });

          // 🚩 Reset safeguard flag shortly after population
          setTimeout(() => {
            window.operatonPopulatingResults = false;
             operatonDebugVerbose('Frontend', '🛡️ SAFEGUARD: Result population completed - change handlers re-enabled');
          }, 200);

          // Store process instance ID if provided
          if (response.process_instance_id) {
            storeProcessInstanceId(formId, response.process_instance_id);
             operatonDebugVerbose('Frontend', 'Stored process instance ID:', response.process_instance_id);
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
            window.showError('No result fields found on this page to populate.');
          }

          // Store evaluation metadata
          const currentPage = window.getCurrentPageCached(formId);
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
          operatonDebugMinimal('Frontend', 'Invalid response structure:', response);
          window.showError('No results received from evaluation.');
        }
      },
      error: function (xhr, status, error) {
        operatonDebugMinimal('Frontend', 'AJAX Error:', error);
        operatonDebugMinimal('Frontend', 'XHR Status:', xhr.status);
        operatonDebugMinimal('Frontend', 'XHR Response:', xhr.responseText);

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

        window.showError(errorMessage);
      },
      complete: function () {
        // Always use centralized button manager for restoration
        window.operatonButtonManager.restoreOriginalState($button, formId);

        // CRITICAL: Release the processing lock
        setTimeout(() => {
          delete window.operatonProcessingLock[lockKey];
        }, 1000); // 1 second cooldown to prevent rapid-fire clicks
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
    operatonDebugMinimal('Frontend', 'jQuery not available for validateForm');
    return true;
  }

  if (typeof gform !== 'undefined' && gform.validators && gform.validators[formId]) {
    return gform.validators[formId]();
  }

  const $form = window.getCachedElement(`#gform_${formId}`);
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
    operatonDebugMinimal('Frontend', 'jQuery not available for forceSyncRadioButtons');
    return;
  }

  const $form = window.getCachedElement(`#gform_${formId}`);
  const config = window.getFormConfigCached(formId);

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

function getGravityFieldValueOptimized(formId, fieldId) {
  const $ = window.jQuery || window.$;
  const $form = window.getCachedElement(`#gform_${formId}`);
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
    operatonDebugMinimal('Frontend', 'jQuery not available for findCustomRadioValueOptimized');
    return null;
  }

  const $form = window.getCachedElement(`#gform_${formId}`);
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
  const config = window.getFormConfigCached(formId);
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
   operatonDebugVerbose('Frontend', 'Stored process instance ID for form', formId + ':', processInstanceId);
}

// =============================================================================
// UI FEEDBACK FUNCTIONS
// =============================================================================

function showSuccessNotification(message) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for showSuccessNotification');
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
     operatonDebugFrontend('Frontend', '🎯 Button clicked for form:', formId);
    handleEvaluateClick($(this));
  });

   operatonDebugVerbose('Frontend', '✅ Event handler bound for form:', formId);
}

// =============================================================================
// AJAX SETUP
// =============================================================================

function createEmergencyOperatonAjax() {
  if (typeof window.operaton_ajax === 'undefined') {
     operatonDebugVerbose('Frontend', '🆘 Creating emergency operaton_ajax fallback');
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
 * Make handleEvaluateClick globally accessible for delegation
 */
window.handleEvaluateClick = handleEvaluateClick;

// =============================================================================
// MAIN INITIALIZATION (SINGLE VERSION)
// =============================================================================

// SINGLE MAIN INITIALIZATION - NO DUPLICATES
(function () {
  'use strict';

  // Ensure we only run once per page load
  if (window.operatonMainInitCalled) {
    return;
  }
  window.operatonMainInitCalled = true;

  function performInitialization($) {

    // Wait for operaton_ajax and initialize
    window.waitForOperatonAjax(() => {
      const initStartTime = performance.now();
       operatonDebugFrontend('Frontend', '🚀 Initializing Operaton DMN...');

      window.operatonInitialized.jQueryReady = true;
      window.initOperatonDMN();

      // Secondary detection for late-loading forms
      setTimeout(() => {
        if (!window.operatonInitialized.initInProgress) {
          window.simplifiedFormDetection();
        }
      }, 1000);

      const initEndTime = performance.now();
      window.operatonInitialized.performanceStats.totalProcessingTime += initEndTime - initStartTime;

       operatonDebugFrontend('Frontend', `🎉 Operaton DMN initialization complete in ${(initEndTime - initStartTime).toFixed(2)}ms`);
    });

    $(window).on('beforeunload', e => {
      // Only perform minimal cleanup that doesn't interfere with form functionality

      // Check if this might be form navigation rather than actual page unload
      const hasActiveForm = document.querySelector('form[id^="gform_"]');
      const isGravityFormsPage = window.location.href.includes('gf_page=') || document.querySelector('.gform_wrapper');

      if (hasActiveForm && isGravityFormsPage) {
        // This looks like form navigation - do minimal cleanup only
         operatonDebugVerbose('Frontend', '🔄 Form navigation detected - minimal cleanup only');

        // Only clear performance-related caches that are safe to clear
        if (window.operatonCaches.domQueryCache && window.operatonCaches.domQueryCache.size > 100) {
          window.operatonCaches.domQueryCache.clear();
        }

        // Don't clear form state, initialization flags, or button manager
        return;
      }

      // This appears to be actual page navigation - safe to do full cleanup
       operatonDebugVerbose('Frontend', '🧹 Page navigation detected - performing cleanup');

      // Clear caches
      window.operatonCaches.domQueryCache.clear();
      window.operatonCaches.formConfigCache.clear();

      // Clear button manager cache but preserve core functionality
      if (window.operatonButtonManager) {
        window.operatonButtonManager.clearCache();
        // Don't clear originalTexts - that should persist
      }

      // Clear performance stats but not core initialization state
      if (window.operatonInitialized && window.operatonInitialized.performanceStats) {
        window.operatonInitialized.performanceStats = {
          initializationAttempts: 0,
          successfulInits: 0,
          totalProcessingTime: 0,
          cacheHits: 0,
        };
      }

      // CRITICAL: Don't clear these as they break form functionality:
      // - window.operatonInitialized.forms
      // - window.operatonInitialized.globalInit
      // - window.operatonInitialized.jQueryReady
      // - Form configurations or state
    });

    $(document).ready(() => {
    });
  }

  // Initialize based on jQuery availability
  if (typeof jQuery !== 'undefined') {
    performInitialization(jQuery);
  } else {
    window.waitForJQuery(() => {
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
      if (typeof jQuery !== 'undefined') {
        window.simplifiedFormDetection();
      } else {
        operatonDebugMinimal('Frontend', 'Window load: jQuery still not available');
      }
    }
  }, 1000);
});

document.addEventListener('DOMContentLoaded', () => {
  if (typeof jQuery !== 'undefined' && !window.operatonInitialized.globalInit) {
    setTimeout(window.simplifiedFormDetection, 100);
  }
});

// =============================================================================
// GLOBAL DEBUGGING FUNCTIONS
// =============================================================================

if (typeof window !== 'undefined') {
  window.operatonDebugFixed = function () {
    const stats = window.operatonInitialized.performanceStats;

     operatonDebugVerbose('Frontend', 'Debug Info:', {
      initializationState: window.operatonInitialized,
      performanceStats: stats,
      cacheStats: {
        domCache: window.operatonCaches.domQueryCache.size,
        configCache: window.operatonCaches.formConfigCache.size,
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

  window.operatonForceCleanup = window.resetFormSystem;

  window.operatonReinitialize = function () {
     operatonDebugVerbose('Frontend', 'MANUAL REINIT: Starting re-initialization');
    window.resetFormSystem();
  };
}

// =============================================================================
// MODULE COMPLETION FLAG
// =============================================================================

window.operatonModulesLoaded = window.operatonModulesLoaded || {};
window.operatonModulesLoaded.main = true;

operatonDebugFrontend('Frontend', 'Main frontend script loaded successfully (modular version)');
