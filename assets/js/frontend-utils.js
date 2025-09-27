/**
 * Operaton DMN Frontend Utilities Module
 * Shared utilities, date conversion, caching helpers, and debug functions
 *
 * Dependencies: frontend-core.js
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

operatonDebugVerbose('Frontend', 'Utils module loading...');

// =============================================================================
// CONFIGURATION AND CACHING UTILITIES
// =============================================================================

/**
 * Get cached form configuration
 * @param {number} formId Form ID
 * @returns {object|null} Form configuration object
 */
function getFormConfigCached(formId) {
  // Use global cache from core module
  const formConfigCache = window.formConfigCache || new Map();
  const cacheKey = `config_${formId}`;

  if (formConfigCache.has(cacheKey)) {
    // Update performance stats if available
    if (window.operatonInitialized && window.operatonInitialized.performanceStats) {
      window.operatonInitialized.performanceStats.cacheHits++;
    }
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
 * Get current page number for a form
 * @param {number} formId Form ID
 * @returns {number} Current page number (1-based)
 */
function getCurrentPageCached(formId) {
  // Check URL parameters first
  const urlParams = new URLSearchParams(window.location.search);
  const pageParam = urlParams.get('gf_page');
  if (pageParam) {
    return parseInt(pageParam);
  }

  // Check form fields using cached elements
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
 * Clear DOM cache for specific form or all forms
 * @param {number|null} formId Form ID to clear cache for, or null for all
 */
function clearDOMCache(formId = null) {
  const domQueryCache = window.domQueryCache || new Map();
  
  if (formId) {
    const keysToDelete = Array.from(domQueryCache.keys()).filter(
      key =>
        key.includes(`_${formId}`) || 
        key.includes(`#gform_${formId}`) || 
        key.includes(`#operaton-evaluate-${formId}`)
    );
    keysToDelete.forEach(key => domQueryCache.delete(key));
  } else {
    domQueryCache.clear();
  }
}

// =============================================================================
// DATE CONVERSION UTILITIES
// =============================================================================

/**
 * Convert date from various formats to ISO format (YYYY-MM-DD)
 * @param {string} dateStr Date string in various formats
 * @param {string} fieldName Field name for debugging
 * @returns {string|null} ISO formatted date or null if invalid
 */
function convertDateFormat(dateStr, fieldName) {
  if (!dateStr || dateStr === null) {
    return null;
  }

  operatonDebugVerbose('Frontend', 'Converting date for field', {fieldName: fieldName, input: dateStr});

  // If already in ISO format (YYYY-MM-DD), return as-is
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    return dateStr;
  }

  // Handle DD-MM-YYYY format (with dashes)
  if (/^\d{2}-\d{2}-\d{4}$/.test(dateStr)) {
    const parts = dateStr.split('-');
    const day = parts[0];
    const month = parts[1];
    const year = parts[2];
    return `${year}-${month}-${day}`;
  }

  // Handle DD/MM/YYYY format (with slashes)
  if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(dateStr)) {
    const parts = dateStr.split('/');
    const day = parts[0].padStart(2, '0');
    const month = parts[1].padStart(2, '0');
    const year = parts[2];

    const convertedDate = `${year}-${month}-${day}`;
    operatonDebugVerbose('Frontend', 'DD/MM/YYYY conversion', {input: dateStr, output: convertedDate});
    return convertedDate;
  }

  // Handle MM/DD/YYYY format (US format)
  if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(dateStr)) {
    operatonDebugVerbose('Frontend', 'Ambiguous date format detected, assuming DD/MM/YYYY', {dateStr: dateStr});
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
      operatonDebugVerbose('Frontend', 'Date object conversion', {input: dateStr, output: result});
      return result;
    }
  } catch (e) {
    operatonDebugMinimal('Frontend', 'Error parsing date', {dateStr: dateStr, error: e.message || e});
  }

  operatonDebugVerbose('Frontend', 'Could not convert date format', {dateStr: dateStr});
  return dateStr;
}

// =============================================================================
// FIELD VALIDATION UTILITIES
// =============================================================================

/**
 * Validate field value against expected type
 * @param {*} value Field value
 * @param {string} expectedType Expected data type
 * @returns {boolean} True if valid
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

// =============================================================================
// PROCESS INSTANCE UTILITIES
// =============================================================================

/**
 * Store process instance ID for later retrieval
 * @param {number} formId Form ID
 * @param {string} processInstanceId Process instance ID
 */
function storeProcessInstanceId(formId, processInstanceId) {
  if (typeof Storage !== 'undefined') {
    sessionStorage.setItem(`operaton_process_${formId}`, processInstanceId);
  }
  window[`operaton_process_${formId}`] = processInstanceId;
  operatonDebugVerbose('Frontend', 'Stored process instance ID', {formId: formId, processInstanceId: processInstanceId});
}

/**
 * Clear stored results and process data for a form
 * @param {number} formId Form ID
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
  operatonDebugVerbose('Frontend', 'Cleared all stored results and process data for form', {formId: formId});
}

// =============================================================================
// RADIO BUTTON UTILITIES
// =============================================================================

/**
 * Generate possible radio button names for field detection
 * @param {string} fieldLabel Field label text
 * @param {number} fieldId Field ID
 * @returns {Array} Array of possible radio names
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
 * Force synchronization of radio buttons with hidden fields
 * @param {number} formId Form ID
 */
function forceSyncRadioButtons(formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for forceSyncRadioButtons');
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

/**
 * Find custom radio value with optimized detection
 * @param {number} formId Form ID
 * @param {number} fieldId Field ID
 * @returns {*} Radio value or null
 */
function findCustomRadioValueOptimized(formId, fieldId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugMinimal('Frontend', 'jQuery not available for findCustomRadioValueOptimized');
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

// =============================================================================
// FIELD DETECTION AND OPTIMIZATION
// =============================================================================

/**
 * Find field on current page with caching and multiple strategies
 * @param {number} formId Form ID
 * @param {number} fieldId Field ID
 * @returns {jQuery|null} Field element or null
 */
function findFieldOnCurrentPageOptimized(formId, fieldId) {
  const cacheKey = `field_${formId}_${fieldId}`;
  const domQueryCache = window.domQueryCache || new Map();
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
// DEBUG AND TESTING FUNCTIONS
// =============================================================================

/**
 * Test delegation system availability
 * Used for debugging and system verification
 */
window.testDelegation = function() {
  operatonDebugVerbose('Frontend', 'Testing delegation availability');
  operatonDebugVerbose('Frontend', 'operatonButtonManager available', {
    available: typeof window.operatonButtonManager !== 'undefined'
  });
  operatonDebugVerbose('Frontend', 'handleEvaluateClick available', {
    available: typeof window.handleEvaluateClick !== 'undefined'
  });
  
  const shouldDelegate = typeof window.operatonButtonManager !== 'undefined' && 
                         typeof window.handleEvaluateClick !== 'undefined';
  
  operatonDebugVerbose('Frontend', 'Should delegate', {shouldDelegate: shouldDelegate});
  
  return {
    operatonButtonManager: typeof window.operatonButtonManager !== 'undefined',
    handleEvaluateClick: typeof window.handleEvaluateClick !== 'undefined',
    shouldDelegate: shouldDelegate
  };
};

/**
 * Get comprehensive debug information
 * Returns system state for troubleshooting
 */
window.operatonDebugFixed = function () {
  const stats = window.operatonInitialized?.performanceStats || {};
  const domQueryCache = window.domQueryCache || new Map();
  const formConfigCache = window.formConfigCache || new Map();

  const debugInfo = {
    initializationState: window.operatonInitialized,
    performanceStats: stats,
    cacheStats: {
      domCache: domQueryCache.size,
      configCache: formConfigCache.size,
      buttonCache: window.operatonButtonManager?.buttonCache?.size || 0,
    },
    status: 'modular - utils module active',
  };

  operatonDebugVerbose('Frontend', 'Debug Info', debugInfo);

  return {
    status: 'modular',
    initialized: window.operatonInitialized?.globalInit || false,
    performance: stats,
    caches: debugInfo.cacheStats,
  };
};

// =============================================================================
// MODULE EXPORTS AND GLOBAL REGISTRATION
// =============================================================================

/**
 * Export utility functions for other modules to use
 */
window.OperatonUtils = {
  // Configuration utilities
  getFormConfigCached: getFormConfigCached,
  getCurrentPageCached: getCurrentPageCached,
  clearDOMCache: clearDOMCache,
  
  // Date utilities
  convertDateFormat: convertDateFormat,
  
  // Validation utilities
  validateFieldType: validateFieldType,
  
  // Process utilities
  storeProcessInstanceId: storeProcessInstanceId,
  clearStoredResults: clearStoredResults,
  
  // Radio button utilities
  generatePossibleRadioNames: generatePossibleRadioNames,
  forceSyncRadioButtons: forceSyncRadioButtons,
  findCustomRadioValueOptimized: findCustomRadioValueOptimized,
  
  // Field utilities
  findFieldOnCurrentPageOptimized: findFieldOnCurrentPageOptimized,
  
  // Debug utilities
  testDelegation: window.testDelegation,
  getDebugInfo: window.operatonDebugFixed
};

// Make key functions globally accessible for backward compatibility
window.getFormConfigCached = getFormConfigCached;
window.getCurrentPageCached = getCurrentPageCached;
window.clearDOMCache = clearDOMCache;
window.convertDateFormat = convertDateFormat;
window.validateFieldType = validateFieldType;
window.storeProcessInstanceId = storeProcessInstanceId;
window.clearStoredResults = clearStoredResults;
window.generatePossibleRadioNames = generatePossibleRadioNames;
window.forceSyncRadioButtons = forceSyncRadioButtons;
window.findCustomRadioValueOptimized = findCustomRadioValueOptimized;
window.findFieldOnCurrentPageOptimized = findFieldOnCurrentPageOptimized;

operatonDebugVerbose('Frontend', 'Utils module loaded - Production version');