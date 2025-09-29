/**
 * Frontend Fields Module - Operaton DMN Evaluator
 * Result field management, clearing, validation, and input monitoring
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

operatonDebugFrontend('Fields', 'Frontend fields module loading...');

// =============================================================================
// MODULE DEPENDENCY CHECK
// =============================================================================

// Ensure required modules are loaded first
if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.core) {
  operatonDebugMinimal('Fields', 'ERROR: Core module not loaded! Fields module requires frontend-core.js');
  throw new Error('Operaton DMN: Core module must be loaded before Fields module');
}

if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.utils) {
  operatonDebugMinimal('Fields', 'ERROR: Utils module not loaded! Fields module requires frontend-utils.js');
  throw new Error('Operaton DMN: Utils module must be loaded before Fields module');
}

// =============================================================================
// RESULT FIELD CLEARING FUNCTIONS
// =============================================================================

/**
 * Clear all result fields for a specific form
 * Enhanced clearing with multiple selector attempts
 *
 * @param {number} formId - Gravity Forms form ID
 * @param {string} reason - Reason for clearing (for debugging)
 */
window.clearAllResultFields = function (formId, reason) {
  operatonDebugVerbose('Fields', `CLEARING result fields for form ${formId}: ${reason}`);

  const $ = window.jQuery || window.$;
  if (!$) return;

  const $form = window.getCachedElement(`#gform_${formId}`);
  const resultFieldIds = window.getResultFieldIds(formId);

  if (resultFieldIds.length === 0) {
    operatonDebugVerbose('Fields', `No result fields configured for form ${formId}`);
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
            operatonDebugVerbose('Fields', `Clearing field ${fieldId} (${selector}): "${currentValue}"`);
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
      operatonDebugMinimal('Fields', `Result field ${fieldId} not found or already empty`);
    }
  });

  if (clearedCount > 0) {
    operatonDebugVerbose('Fields', `Successfully cleared ${clearedCount} result fields`);

    // Verification
    setTimeout(() => {
      let verifyCount = 0;
      resultFieldIds.forEach(fieldId => {
        const $field = $form.find(`#input_${formId}_${fieldId}`);
        if ($field.length > 0 && $field.val() && $field.val().trim() !== '') {
          operatonDebugMinimal('Fields', `Field ${fieldId} still has value after clearing: "${$field.val()}"`);
        } else {
          verifyCount++;
        }
      });
      operatonDebugVerbose('Fields', `Verified ${verifyCount}/${resultFieldIds.length} fields are properly cleared`);
    }, 100);
  } else {
    operatonDebugVerbose('Fields', 'No result fields needed clearing');
  }
};

/**
 * Clear result fields with message and comprehensive cleanup
 *
 * @param {number} formId - Gravity Forms form ID
 * @param {string} reason - Reason for clearing
 */
window.clearResultFieldWithMessage = function (formId, reason) {
  window.clearAllResultFields(formId, reason);
  window.clearStoredResults(formId);
  window.clearDOMCache(formId);

  if (typeof window.OperatonDecisionFlow !== 'undefined') {
    window.OperatonDecisionFlow.clearCache();
  }
};

/**
 * Clear stored results from sessionStorage and global variables
 *
 * @param {number} formId - Gravity Forms form ID
 */
window.clearStoredResults = function (formId) {
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
};

// =============================================================================
// RESULT FIELD DETECTION AND VALIDATION
// =============================================================================

/**
 * Find result field on current page optimized with caching
 *
 * @param {number} formId - Gravity Forms form ID
 * @returns {jQuery|null} Result field element or null
 */
window.findResultFieldOnCurrentPageOptimized = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) return null;

  const cacheKey = `result_field_${formId}`;
  const cached = window.operatonCaches.domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < 3000) {
    return cached.element;
  }

  const $form = window.getCachedElement(`#gform_${formId}`);
  const resultFieldIds = window.getResultFieldIds(formId);

  if (resultFieldIds.length === 0) {
    operatonDebugVerbose('Fields', `No result fields configured for form ${formId}`);
    return null;
  }

  // Try to find first available result field
  for (const fieldId of resultFieldIds) {
    const selectors = [
      `#input_${formId}_${fieldId}`,
      `input[name="input_${formId}_${fieldId}"]`,
      `select[name="input_${formId}_${fieldId}"]`,
      `textarea[name="input_${formId}_${fieldId}"]`,
    ];

    for (const selector of selectors) {
      const $resultField = $form.find(selector);
      if ($resultField.length > 0) {
        window.operatonCaches.domQueryCache.set(cacheKey, {
          element: $resultField,
          timestamp: Date.now(),
        });
        return $resultField;
      }
    }
  }

  operatonDebugMinimal('Fields', `No result field found on current page for form ${formId}`);
  return null;
};

// =============================================================================
// INPUT CHANGE MONITORING SYSTEM
// =============================================================================

/**
 * Setup non-blocking input monitoring that preserves all input functionality
 * Uses completely passive event monitoring without interfering with form behavior
 *
 * @param {number} formId - Gravity Forms form ID
 */
window.setupInputChangeMonitoring = function (formId) {
  const $ = window.jQuery || window.$;
  const $form = $(`#gform_${formId}`);

  if ($form.length === 0) {
    operatonDebugMinimal('Fields', `Form ${formId} not found for input monitoring`);
    return;
  }

  // Remove ALL existing handlers first
  $form.off('.operaton-clear');

  // Cache result field IDs once
  const resultFieldIds = window.getResultFieldIds(formId);
  operatonDebugVerbose('Fields', `Result field IDs for monitoring form ${formId}:`, resultFieldIds);

  // State tracking for intelligent clearing
  let debounceTimer = null;
  let lastClearTime = 0;
  let inputActivity = new Map(); // Track input activity per field

  /**
   * Check if results should be cleared based on current state
   */
  function shouldClearResults() {
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

  /**
   * Schedule results clearing with debouncing
   */
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
        operatonDebugVerbose('Fields', 'SAFEGUARD: Canceling clear due to active operations');
        return;
      }

      operatonDebugVerbose('Fields', `CLEARING RESULTS: ${reason}`);
      window.clearAllResultFields(formId, reason);
      window.clearStoredResults(formId);
      lastClearTime = Date.now();
    }, delay);
  }

  /**
   * Check if field should be monitored for changes
   */
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
  $form.on('change.operaton-clear', 'input, select, textarea', function (event) {
    const $field = $(this);

    if (!isMonitorableField($field)) {
      return;
    }

    const fieldName = $field.attr('name') || $field.attr('id');
    const fieldValue = $field.val();

    operatonDebugVerbose('Fields', `CHANGE DETECTED: ${fieldName} = "${fieldValue}"`);
    scheduleResultsClear(`Field changed: ${fieldName}`, 300);
  });

  // Method 2: Monitor typing completion with very long delay
  $form.on('input.operaton-clear', 'input[type="text"], input[type="number"], input[type="email"]', function (event) {
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
        if (now - lastActivity < 2000) {
          // If any field was active in last 2 seconds
          stillTyping = true;
          break;
        }
      }

      if (!stillTyping && shouldClearResults()) {
        const finalValue = $field.val();
        operatonDebugVerbose('Fields', `TYPING SESSION COMPLETED: ${fieldName} = "${finalValue}"`);
        window.clearAllResultFields(formId, `Typing completed: ${fieldName}`);
        window.clearStoredResults(formId);
        lastClearTime = Date.now();
      }
    }, 4000); // 4 second delay - very conservative
  });

  // Method 3: Monitor radio buttons (these can have immediate clearing)
  $form.on('change.operaton-clear', 'input[type="radio"]', function (event) {
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

    operatonDebugVerbose('Fields', `RADIO SELECTED: ${radioName} = "${radioValue}"`);
    scheduleResultsClear(`Radio selected: ${radioName}`, 200);
  });

  // Method 4: Monitor checkboxes
  $form.on('change.operaton-clear', 'input[type="checkbox"]', function (event) {
    const $checkbox = $(this);
    const checkboxName = $checkbox.attr('name') || $checkbox.attr('id');
    const isChecked = $checkbox.is(':checked');

    if (!isMonitorableField($checkbox)) {
      return;
    }

    operatonDebugVerbose('Fields', `CHECKBOX TOGGLED: ${checkboxName} = ${isChecked}`);
    scheduleResultsClear(`Checkbox changed: ${checkboxName}`, 200);
  });

  operatonDebugVerbose(
    'Fields',
    `NON-BLOCKING input monitoring active for form ${formId} - all fields monitorable, input unrestricted`
  );
};

// =============================================================================
// NAVIGATION-AWARE FIELD STATE MANAGEMENT
// =============================================================================

/**
 * Bind navigation events with result field preservation
 * Enhanced to properly exclude result fields from change detection
 *
 * @param {number} formId - Gravity Forms form ID
 */
window.bindNavigationEventsOptimized = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Fields', 'jQuery not available for bindNavigationEventsOptimized');
    return;
  }

  const $form = window.getCachedElement(`#gform_${formId}`);
  const resultFieldIds = window.getResultFieldIds(formId);

  // Store form state for comparison - EXCLUDE result fields entirely
  let formStateSnapshot = null;
  let navigationInProgress = false;

  /**
   * Capture current form state excluding result fields
   */
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

    operatonDebugVerbose('Fields', 'Captured form state (excluding result fields):', Object.keys(state));
    return state;
  }

  /**
   * Check if there are actual form changes (non-result fields)
   */
  function hasActualFormChanges(oldState, newState) {
    if (!oldState || !newState) {
      operatonDebugVerbose('Fields', 'No previous state to compare - treating as NO change for navigation');
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
      operatonDebugVerbose('Fields', 'ACTUAL form changes detected (non-result fields):', changedFields);
      return true;
    }

    operatonDebugVerbose('Fields', 'No actual USER INPUT changes detected');
    return false;
  }

  // Capture initial state when document is ready
  setTimeout(() => {
    if (!formStateSnapshot) {
      formStateSnapshot = captureFormState();
      operatonDebugVerbose(
        'Fields',
        `Captured initial form state for form ${formId} with ${
          Object.keys(formStateSnapshot).length
        } input fields (result fields excluded)`
      );
    }
  }, 500);

  // Remove existing navigation handlers
  $form.off('.operaton-navigation-clear');

  // Enhanced navigation event handling
  $form.on(
    'click.operaton-navigation-clear',
    'input[type="submit"], button[type="submit"], .gform_next_button, .gform_previous_button',
    function () {
      navigationInProgress = true;
      operatonDebugVerbose('Fields', 'Navigation initiated - marking as navigation in progress');

      setTimeout(() => {
        navigationInProgress = false;
        operatonDebugVerbose('Fields', 'Navigation window closed');
      }, 3000);
    }
  );

  // Enhanced input change monitoring that excludes result fields
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
      operatonDebugVerbose(
        'Fields',
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
          operatonDebugVerbose('Fields', `USER INPUT change detected: ${fieldName} - clearing results`);
          window.clearAllResultFields(formId, `User input changed: ${fieldName}`);
          window.clearStoredResults(formId);
          formStateSnapshot = currentState;
        }
      }
    }, 300);
  });
};

// =============================================================================
// FIELD TESTING AND DEBUGGING UTILITIES
// =============================================================================

/**
 * Test field detection and display debugging information
 *
 * @param {number} formId - Gravity Forms form ID
 * @returns {string} Test results summary
 */
window.testFieldDetection = function (formId) {
  operatonDebugVerbose('Fields', `=== TESTING FIELD DETECTION FOR FORM ${formId} ===`);

  const $ = window.jQuery || window.$;
  const $form = $(`#gform_${formId}`);
  const resultFieldIds = window.getResultFieldIds(formId);

  operatonDebugVerbose('Fields', `Form found: ${$form.length > 0}`);
  operatonDebugVerbose('Fields', `Result field IDs: [${resultFieldIds.join(', ')}]`);

  $form.find('input, select, textarea').each(function () {
    const $field = $(this);
    const fieldId = $field.attr('id');
    const fieldName = $field.attr('name');
    const fieldType = $field.attr('type') || $field.prop('tagName').toLowerCase();

    operatonDebugVerbose('Fields', `Field: ${fieldId} | Name: ${fieldName} | Type: ${fieldType}`);
    operatonDebugVerbose('Fields', `  Current value: "${$field.val()}"`);
    operatonDebugVerbose(
      'Fields',
      `  Event handlers: ${Object.keys($._data($field[0], 'events') || {}).join(', ') || 'none'}`
    );
  });

  return 'Test complete - check console output';
};

// =============================================================================
// MODULE COMPLETION
// =============================================================================

// Mark module as loaded
window.operatonModulesLoaded = window.operatonModulesLoaded || {};
window.operatonModulesLoaded.fields = true;

operatonDebugFrontend('Fields', 'Frontend fields module loaded successfully');
