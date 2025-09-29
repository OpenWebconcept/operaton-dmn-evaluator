/**
 * Operaton DMN Frontend Utils Module
 * Utility functions and helper methods
 *
 * @package OperatonDMN
 * @since 1.0.0-beta.18
 */

operatonDebugFrontend('Utils', 'Frontend utils module loading...');

// =============================================================================
// MODULE DEPENDENCY CHECK
// =============================================================================

// Ensure core module is loaded first
if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.core) {
  operatonDebugMinimal('Utils', 'ERROR: Core module not loaded! Utils module requires frontend-core.js');
  throw new Error('Operaton DMN: Core module must be loaded before Utils module');
}

// =============================================================================
// DATE UTILITIES
// =============================================================================

/**
 * Convert various date formats to YYYY-MM-DD format
 * @param {string} dateStr - Date string in various formats
 * @param {string} fieldName - Field name for debugging (optional)
 * @returns {string} Date in YYYY-MM-DD format or original string if conversion fails
 */
window.convertDateFormat = function (dateStr, fieldName) {
  if (!dateStr || dateStr === null) {
    return null;
  }

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

  // Handle DD/MM/YYYY format (with slashes) - THIS WAS THE BUG
  if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(dateStr)) {
    const parts = dateStr.split('/');
    const day = parts[0].padStart(2, '0');
    const month = parts[1].padStart(2, '0');
    const year = parts[2];

    // FIXED: Correct order is YYYY-MM-DD, not YYYY-DD-MM
    const convertedDate = `${year}-${month}-${day}`;
    return convertedDate;
  }

  // Handle MM/DD/YYYY format (US format)
  if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(dateStr)) {
    // Note: This creates ambiguity with DD/MM/YYYY
    // You may need to specify which format your forms use
    operatonDebugMinimal('Utils', 'Ambiguous date format detected:', dateStr, 'Assuming DD/MM/YYYY');
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
      return result;
    }
  } catch (e) {
    operatonDebugMinimal('Utils', 'Error parsing date:', dateStr, e);
  }

  // If all else fails, return original string
  operatonDebugMinimal('Utils', 'Could not convert date format:', dateStr);
  return dateStr;
};

// =============================================================================
// FIELD UTILITIES
// =============================================================================

/**
 * Find a specific field on the current page with optimized caching
 * @param {number} formId - Gravity Forms form ID
 * @param {string|number} fieldId - Field ID to find
 * @returns {jQuery|null} jQuery element or null if not found
 */
window.findFieldOnCurrentPageOptimized = function (formId, fieldId) {
  const cacheKey = `field_${formId}_${fieldId}`;
  const cached = window.operatonCaches.domQueryCache.get(cacheKey);

  if (cached && Date.now() - cached.timestamp < 3000) {
    return cached.element;
  }

  const $ = window.jQuery || window.$;
  const $form = window.getCachedElement(`#gform_${formId}`);

  if (!$form.length) {
    return null;
  }

  // Primary selectors - try direct field access first
  const primarySelectors = [
    `#input_${formId}_${fieldId}`, // Direct field ID
    `#field_${formId}_${fieldId} input:visible`, // Field container with input
    `#field_${formId}_${fieldId} select:visible`, // Field container with select
    `#field_${formId}_${fieldId} textarea:visible`, // Field container with textarea
    `input[name="input_${formId}_${fieldId}"]:visible`, // Input by name
    `select[name="input_${formId}_${fieldId}"]:visible`, // Select by name
    `textarea[name="input_${formId}_${fieldId}"]:visible`, // Textarea by name
  ];

  // Try primary selectors first
  for (const selector of primarySelectors) {
    const $field = $form.find(selector);
    if ($field.length > 0) {
      window.operatonCaches.domQueryCache.set(cacheKey, {
        element: $field.first(),
        timestamp: Date.now(),
      });
      operatonDebugVerbose('Utils', `Field ${fieldId} found with selector: ${selector}`);
      return $field.first();
    }
  }

  // Fallback: check if it's a result display field from config
  const config = window.getFormConfigCached(formId);
  if (config && config.result_display_field && fieldId == config.result_display_field) {
    const configSelectors = [
      `#field_${formId}_${config.result_display_field} input:visible`,
      `#field_${formId}_${config.result_display_field} select:visible`,
      `#field_${formId}_${config.result_display_field} textarea:visible`,
      `input[name="input_${formId}_${config.result_display_field}"]`,
      `select[name="input_${formId}_${config.result_display_field}"]`,
      `textarea[name="input_${formId}_${config.result_display_field}"]`,
    ];

    for (const selector of configSelectors) {
      const $field = $form.find(`${selector}:visible`);
      if ($field.length > 0) {
        window.operatonCaches.domQueryCache.set(cacheKey, {
          element: $field.first(),
          timestamp: Date.now(),
        });
        operatonDebugVerbose('Utils', `Result field ${fieldId} found with config selector: ${selector}`);
        return $field.first();
      }
    }
  }

  // Legacy fallback detection strategies for older field types
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
      operatonDebugVerbose('Utils', `Field ${fieldId} found with legacy strategy`);
      return $field;
    }
  }

  operatonDebugVerbose('Utils', `Field ${fieldId} not found with any selector`);
  return null;
};

// =============================================================================
// ASYNC UTILITIES
// =============================================================================

/**
 * Wait for operaton_ajax configuration to be available
 * @param {Function} callback - Function to call when operaton_ajax is ready
 * @param {number} maxAttempts - Maximum number of attempts (default: 50)
 */
window.waitForOperatonAjax = function (callback, maxAttempts = 50) {
  let attempts = 0;

  function check() {
    attempts++;

    if (typeof window.operaton_ajax !== 'undefined' && window.operaton_ajax.ajax_url) {
      callback();
    } else if (attempts < maxAttempts) {
      if (attempts % 10 === 0) {
        operatonDebugVerbose('Utils', `Waiting for operaton_ajax... (attempt ${attempts})`);
      }
      const delay = Math.min(100 * Math.pow(1.1, attempts), 1000);
      setTimeout(check, delay);
    } else {
      operatonDebugMinimal('Utils', `❌ operaton_ajax not found after ${maxAttempts} attempts`);
      callback();
    }
  }
  check();
};

/**
 * Wait for jQuery to be available
 * @param {Function} callback - Function to call when jQuery is ready
 * @param {number} maxAttempts - Maximum number of attempts (default: 50)
 */
window.waitForJQuery = function (callback, maxAttempts = 50) {
  let attempts = 0;

  function check() {
    attempts++;

    if (typeof jQuery !== 'undefined') {
      callback();
    } else if (attempts < maxAttempts) {
      if (attempts % 10 === 0) {
        operatonDebugVerbose('Utils', `Waiting for jQuery... (attempt ${attempts})`);
      }
      const delay = Math.min(100 * Math.pow(1.1, attempts), 1000);
      setTimeout(check, delay);
    } else {
      operatonDebugMinimal('Utils', `❌ jQuery not found after ${maxAttempts} attempts`);
    }
  }
  check();
};

// =============================================================================
// ERROR HANDLING UTILITIES
// =============================================================================

/**
 * Show error message to user
 * @param {string} message - Error message to display
 * @param {string} type - Error type ('error', 'warning', 'info')
 */
window.showError = function (message, type = 'error') {
  const errorDiv = document.createElement('div');

  const styles = {
    error: 'background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;',
    warning: 'background: #fff3cd; color: #856404; border: 1px solid #ffeaa7;',
    info: 'background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb;',
  };

  errorDiv.style.cssText = `${styles[type]} padding: 10px; border-radius: 4px; margin: 10px 0; font-size: 14px;`;
  errorDiv.textContent = message;

  const forms = document.querySelectorAll('form[id^="gform_"]');
  if (forms.length > 0) {
    forms[0].insertBefore(errorDiv, forms[0].firstChild);

    // Auto-remove after 10 seconds for non-critical messages
    if (type !== 'error') {
      setTimeout(() => {
        if (errorDiv.parentNode) {
          errorDiv.parentNode.removeChild(errorDiv);
        }
      }, 10000);
    }
  }

  operatonDebugMinimal('Utils', `${type.toUpperCase()} shown: ${message}`);
};

/**
 * Show success message to user
 * @param {string} message - Success message to display
 */
window.showSuccess = function (message) {
  const successDiv = document.createElement('div');
  successDiv.style.cssText =
    'background: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; font-size: 14px;';
  successDiv.textContent = message;

  const forms = document.querySelectorAll('form[id^="gform_"]');
  if (forms.length > 0) {
    forms[0].insertBefore(successDiv, forms[0].firstChild);

    // Auto-remove after 5 seconds
    setTimeout(() => {
      if (successDiv.parentNode) {
        successDiv.parentNode.removeChild(successDiv);
      }
    }, 5000);
  }

  operatonDebugVerbose('Utils', `SUCCESS shown: ${message}`);
};

/**
 * Get result field IDs for a specific form based on configuration
 * @param {number} formId - Gravity Forms form ID
 * @returns {Array} Array of result field IDs
 */
window.getResultFieldIds = function (formId) {
  const config = window.getFormConfigCached(formId);
  const resultFieldIds = [];

  if (!config) {
    operatonDebugMinimal('Utils', `No configuration found for form ${formId}`);
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

  operatonDebugVerbose('Utils', `Result field IDs for form ${formId}:`, resultFieldIds);
  return resultFieldIds;
};

// =============================================================================
// VALIDATION UTILITIES
// =============================================================================

/**
 * Validate email format
 * @param {string} email - Email address to validate
 * @returns {boolean} True if valid email format
 */
window.validateEmail = function (email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
};

/**
 * Validate date format (DD/MM/YYYY or YYYY-MM-DD)
 * @param {string} dateStr - Date string to validate
 * @returns {boolean} True if valid date format
 */
window.validateDate = function (dateStr) {
  if (!dateStr || typeof dateStr !== 'string') {
    return false;
  }

  // Check common formats
  const formats = [
    /^\d{1,2}\/\d{1,2}\/\d{4}$/, // DD/MM/YYYY
    /^\d{4}-\d{2}-\d{2}$/, // YYYY-MM-DD
    /^\d{4}\/\d{1,2}\/\d{1,2}$/, // YYYY/MM/DD
  ];

  const matchesFormat = formats.some(format => format.test(dateStr));
  if (!matchesFormat) {
    return false;
  }

  // Try to parse with JavaScript Date
  try {
    const converted = window.convertDateFormat(dateStr);
    const date = new Date(converted);
    return !isNaN(date.getTime());
  } catch (e) {
    return false;
  }
};

/**
 * Validate numeric input
 * @param {string|number} value - Value to validate
 * @param {Object} options - Validation options (min, max, decimals)
 * @returns {boolean} True if valid number
 */
window.validateNumber = function (value, options = {}) {
  const num = parseFloat(value);

  if (isNaN(num)) {
    return false;
  }

  if (options.min !== undefined && num < options.min) {
    return false;
  }

  if (options.max !== undefined && num > options.max) {
    return false;
  }

  if (options.decimals === 0 && num !== Math.floor(num)) {
    return false;
  }

  return true;
};

// =============================================================================
// STRING UTILITIES
// =============================================================================

/**
 * Safely escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} HTML-escaped text
 */
window.escapeHtml = function (text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
};

/**
 * Truncate text to specified length
 * @param {string} text - Text to truncate
 * @param {number} maxLength - Maximum length
 * @param {string} suffix - Suffix to add (default: '...')
 * @returns {string} Truncated text
 */
window.truncateText = function (text, maxLength, suffix = '...') {
  if (!text || text.length <= maxLength) {
    return text;
  }

  return text.substring(0, maxLength - suffix.length) + suffix;
};

/**
 * Convert string to slug format
 * @param {string} text - Text to convert
 * @returns {string} Slug format string
 */
window.toSlug = function (text) {
  return text
    .toString()
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^\w\-]+/g, '')
    .replace(/\-\-+/g, '-');
};

// =============================================================================
// PERFORMANCE UTILITIES
// =============================================================================

/**
 * Debounce function execution
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @param {boolean} immediate - Execute immediately on first call
 * @returns {Function} Debounced function
 */
window.debounce = function (func, wait, immediate) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      timeout = null;
      if (!immediate) func.apply(this, args);
    };
    const callNow = immediate && !timeout;
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
    if (callNow) func.apply(this, args);
  };
};

/**
 * Throttle function execution
 * @param {Function} func - Function to throttle
 * @param {number} limit - Time limit in milliseconds
 * @returns {Function} Throttled function
 */
window.throttle = function (func, limit) {
  let inThrottle;
  return function (...args) {
    if (!inThrottle) {
      func.apply(this, args);
      inThrottle = true;
      setTimeout(() => (inThrottle = false), limit);
    }
  };
};

// =============================================================================
// UTILS DEBUG FUNCTIONS
// =============================================================================

/**
 * Debug function to test utility functions
 * @returns {Object} Test results
 */
window.operatonDebugUtils = function () {
  const utilsInfo = {
    availableFunctions: {
      convertDateFormat: typeof window.convertDateFormat,
      findFieldOnCurrentPageOptimized: typeof window.findFieldOnCurrentPageOptimized,
      getResultFieldIds: typeof window.getResultFieldIds,
      waitForOperatonAjax: typeof window.waitForOperatonAjax,
      waitForJQuery: typeof window.waitForJQuery,
      showError: typeof window.showError,
      showSuccess: typeof window.showSuccess,
      validateEmail: typeof window.validateEmail,
      validateDate: typeof window.validateDate,
      validateNumber: typeof window.validateNumber,
      escapeHtml: typeof window.escapeHtml,
      truncateText: typeof window.truncateText,
      toSlug: typeof window.toSlug,
      debounce: typeof window.debounce,
      throttle: typeof window.throttle,
    },
    testResults: {
      dateConversion: window.convertDateFormat('23-01-1980'),
      emailValidation: window.validateEmail('test@example.com'),
      numberValidation: window.validateNumber('123.45'),
      htmlEscape: window.escapeHtml('<script>alert("test")</script>'),
      textTruncation: window.truncateText('This is a long text', 10),
      slugConversion: window.toSlug('Hello World 123!'),
      resultFieldIds: window.getResultFieldIds(2), // Test with form 2
    },
    moduleStatus: 'utils-module-active',
  };

  operatonDebugVerbose('Utils', 'Utils Debug Info:', utilsInfo);
  return utilsInfo;
};

// =============================================================================
// MODULE COMPLETION FLAG
// =============================================================================

// Mark Utils module as loaded
window.operatonModulesLoaded = window.operatonModulesLoaded || {};
window.operatonModulesLoaded.utils = true;

operatonDebugFrontend('Utils', 'Frontend utils module loaded successfully');
