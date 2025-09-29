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

// Ensure Utils module is loaded
if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.utils) {
  operatonDebugMinimal('Frontend', 'ERROR: Utils module not loaded! This script requires frontend-utils.js');
  throw new Error('Operaton DMN: Utils module must be loaded before main frontend script');
}

// Ensure Fields module is loaded
if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.fields) {
  operatonDebugMinimal('Frontend', 'ERROR: Fields module not loaded! This script requires frontend-fields.js');
  throw new Error('Operaton DMN: Fields module must be loaded before main frontend script');
}

// Ensure Evaluation module is loaded
if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.evaluation) {
  operatonDebugMinimal('Frontend', 'ERROR: Evaluation module not loaded!');
  throw new Error('Operaton DMN: Evaluation module must be loaded before main frontend script');
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

/**
 * Make field logic completely non-blocking
 */
if (window.OperatonFieldLogic) {
  // Override the setupEventListeners to be completely passive
  window.OperatonFieldLogic.setupEventListeners = function (formId, mapping, $form) {
    const self = this;

    // Partner field - use only blur and change, never input
    const $partnerField = $form.find(`#input_${formId}_${mapping.partnerField}`);
    if ($partnerField.length > 0) {
      $partnerField.off('.fieldlogic');

      // Only respond to blur (when user leaves field) and change (when value actually changes)
      $partnerField.on('blur.fieldlogic change.fieldlogic', function () {
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

      $childField.on('blur.fieldlogic change.fieldlogic', function () {
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

  operatonDebugVerbose('Frontend', 'Field logic updated to be completely non-blocking');
}

/**
 * TEST FUNCTION: Verify input functionality
 */
window.testInputFunctionality = function (formId = 8) {
  const $ = jQuery;
  const fields = [
    { id: 14, name: 'partner_geslachtsnaam' },
    { id: 16, name: 'kind_geboorteplaats' },
  ];

  operatonDebugVerbose('Frontend', '=== TESTING INPUT FUNCTIONALITY ===');

  fields.forEach(field => {
    const $field = $(`#input_${formId}_${field.id}`);
    operatonDebugVerbose('Frontend', `Field ${field.name} (${field.id}):`);
    operatonDebugVerbose('Frontend', `  Found: ${$field.length > 0}`);
    operatonDebugVerbose('Frontend', `  Disabled: ${$field.prop('disabled')}`);
    operatonDebugVerbose('Frontend', `  Readonly: ${$field.prop('readonly')}`);
    operatonDebugVerbose('Frontend', `  Current value: "${$field.val()}"`);
    operatonDebugVerbose(
      'Frontend',
      `  Event handlers: ${Object.keys($._data($field[0], 'events') || {}).join(', ') || 'none'}`
    );
  });

  return 'Test complete - check console output';
};

// =============================================================================
// VALIDATION AND UTILITIES
// =============================================================================

window.validateForm = function (formId) {
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
      const value = window.getFieldValue($field);

      if (!value || value.trim() === '') {
        allValid = false;
        return false;
      }
    });

  return allValid;
};

window.getFieldValue = function ($field) {
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
};

window.validateFieldType = function (value, expectedType) {
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
};

window.forceSyncRadioButtons = function (formId) {
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
};

window.getGravityFieldValueOptimized = function (formId, fieldId) {
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
      value = window.getFieldValue($field);
      if (value !== null && value !== '') {
        return value;
      }
    }
  }

  // Check for custom radio values
  value = window.findCustomRadioValueOptimized(formId, fieldId);
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
};

window.findCustomRadioValueOptimized = function (formId, fieldId) {
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
      const possibleRadioNames = window.generatePossibleRadioNames(fieldLabel, fieldId);

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
};

window.generatePossibleRadioNames = function (fieldLabel, fieldId) {
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
};

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
    window.handleEvaluateClick($(this));
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

      operatonDebugFrontend(
        'Frontend',
        `🎉 Operaton DMN initialization complete in ${(initEndTime - initStartTime).toFixed(2)}ms`
      );
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

    $(document).ready(() => {});
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
