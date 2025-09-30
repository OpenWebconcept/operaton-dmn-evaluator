/**
 * Operaton DMN Frontend Forms Module
 * Form detection, initialization, and navigation handling
 *
 * @package OperatonDMN
 * @since 1.0.0-beta.18
 */

operatonDebugFrontend('Forms', 'Frontend forms module loading...');

// =============================================================================
// MODULE DEPENDENCY CHECK
// =============================================================================

// Ensure required modules are loaded first
if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.core) {
  operatonDebugMinimal('Forms', 'ERROR: Core module not loaded! Forms module requires frontend-core.js');
  throw new Error('Operaton DMN: Core module must be loaded before Forms module');
}

if (!window.operatonModulesLoaded || !window.operatonModulesLoaded.ui) {
  operatonDebugMinimal('Forms', 'ERROR: UI module not loaded! Forms module requires frontend-ui.js');
  throw new Error('Operaton DMN: UI module must be loaded before Forms module');
}

// =============================================================================
// FORM DETECTION AND DISCOVERY
// =============================================================================

/**
 * Simplified form detection that finds all DMN-enabled forms
 */
window.simplifiedFormDetection = function () {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugVerbose('Forms', 'jQuery not available for form detection');
    return;
  }

  // Prevent concurrent detection
  if (window.operatonInitialized.initInProgress) {
    operatonDebugVerbose('Forms', 'Form detection already in progress, skipping');
    return;
  }

  window.operatonInitialized.initInProgress = true;

  try {
    window.operatonInitialized.performanceStats.initializationAttempts++;

    $('form[id^="gform_"]').each(function () {
      const $form = $(this);
      const formId = parseInt($form.attr('id').replace('gform_', ''));

      if (formId && !isNaN(formId)) {
        const config = window.getFormConfigCached(formId);
        if (config) {
          operatonDebugVerbose('Forms', `🎯 DMN-enabled form detected: ${formId}`);
          window.simpleFormInitialization(formId);
        }
      }
    });

    operatonDebugVerbose('Forms', '✅ Simplified detection complete');
  } finally {
    window.operatonInitialized.initInProgress = false;
  }
};

// =============================================================================
// FORM INITIALIZATION
// =============================================================================

/**
 * Initialize a single form with DMN functionality
 * @param {number} formId - Gravity Forms form ID
 */
window.simpleFormInitialization = function (formId) {
  formId = parseInt(formId);

  // Prevent concurrent initialization
  const initKey = `init_${formId}`;
  if (window.operatonInitialized[initKey]) {
    return;
  }

  // Prevent duplicate initialization
  if (window.operatonInitialized.forms.has(formId)) {
    return;
  }

  const config = window.getFormConfigCached(formId);
  if (!config) {
    return;
  }

  // Set progress flag
  window.operatonInitialized[initKey] = true;

  try {
    // Mark as initialized
    window.operatonInitialized.forms.add(formId);
    window.operatonInitialized.performanceStats.successfulInits++;

    operatonDebugVerbose('Forms', `=== INITIALIZING FORM ${formId} ===`);

    // Initialize UI components
    window.handleButtonPlacement(formId);

    // Setup navigation and change detection
    window.setupPageChangeDetection(formId);

    // CRITICAL: Setup input change monitoring for result field clearing
    if (typeof window.setupInputChangeMonitoring === 'function') {
      window.setupInputChangeMonitoring(formId);
    }

    // Initialize field logic if available
    if (window.OperatonFieldLogic && typeof window.OperatonFieldLogic.initializeForm === 'function') {
      window.OperatonFieldLogic.initializeForm(formId);
    }

    // Handle initial state and result preservation
    setTimeout(() => {
      const currentPage = window.getCurrentPageCached(formId);
      const isDecisionFlowPage = currentPage === (parseInt(config.evaluation_step) || 2) + 1;

      const $ = window.jQuery || window.$;
      const hasExistingResults =
        $(`#gform_${formId}`)
          .find('input[value]:visible, textarea:not(:empty):visible, select option:selected:visible')
          .filter(function () {
            const fieldName = $(this).attr('name') || $(this).attr('id') || '';
            const resultFieldIds = window.getResultFieldIds ? window.getResultFieldIds(formId, config) : [];
            return resultFieldIds.some(
              id => fieldName.includes(`input_${formId}_${id}`) || fieldName.includes(`_${id}`)
            );
          }).length > 0;

      if (hasExistingResults) {
        operatonDebugVerbose(
          'Forms',
          `PRESERVING existing results during re-initialization - Form ${formId}, page ${currentPage}`
        );
        // Only clear stored process data, keep the visible results
        if (typeof Storage !== 'undefined') {
          sessionStorage.removeItem(`operaton_process_${formId}`);
        }
        return; // Don't clear anything else
      }

      if (!isDecisionFlowPage) {
        operatonDebugVerbose(
          'Forms',
          `Clearing results on fresh initialization - Form ${formId}, page ${currentPage} (no existing results found)`
        );
        if (typeof window.clearResultFieldWithMessage === 'function') {
          window.clearResultFieldWithMessage(formId, 'Form initialized (no existing results)');
        }
      } else {
        operatonDebugVerbose('Forms', `PRESERVING results on decision flow page - Form ${formId}, page ${currentPage}`);
        // Only clear stored data, not the actual result fields
        if (typeof window.clearStoredResults === 'function') {
          window.clearStoredResults(formId);
        }
      }
    }, 500);

    operatonDebugVerbose('Forms', `=== FORM ${formId} INITIALIZATION COMPLETE ===`);
  } catch (error) {
    operatonDebugMinimal('Forms', `Error initializing form ${formId}:`, error);
    window.operatonInitialized.forms.delete(formId);
  } finally {
    delete window.operatonInitialized[initKey];
  }
};

// =============================================================================
// PAGE CHANGE DETECTION AND NAVIGATION HANDLING
// =============================================================================

/**
 * Setup page change detection and navigation handlers for a form
 * @param {number} formId - Gravity Forms form ID
 *
 * MINIMAL FIX: Removed duplicate captureFormState() and hasActualFormChanges() functions.
 * Now delegates navigation event handling to window.bindNavigationEventsOptimized() from Fields module.
 */
window.setupPageChangeDetection = function (formId) {
  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugVerbose('Forms', 'jQuery not available for setupPageChangeDetection');
    return;
  }

  const $form = window.getCachedElement(`#gform_${formId}`);
  if (!$form.length) {
    operatonDebugVerbose('Forms', `Form ${formId} not found for page change detection`);
    return;
  }

  const config = window.getFormConfigCached(formId);
  if (!config) {
    operatonDebugVerbose('Forms', `No config found for form ${formId} page change detection`);
    return;
  }

  // Bind to Gravity Forms page load event
  $(document).on(`gform_page_loaded.operaton_${formId}`, function (event, formIdFromEvent, currentPage) {
    if (parseInt(formIdFromEvent) !== parseInt(formId)) {
      return;
    }

    operatonDebugVerbose('Forms', `📄 Page ${currentPage} loaded for form ${formId}`);

    // Clear DOM cache for this form
    if (typeof window.clearDOMCache === 'function') {
      window.clearDOMCache(formId);
    }

    // Re-initialize button placement
    if (typeof window.handleButtonPlacement === 'function') {
      window.handleButtonPlacement(formId);
    }
  });

  // MINIMAL CHANGE: Delegate navigation event binding to Fields module
  // This replaces ~150 lines of duplicate code that was previously here
  if (typeof window.bindNavigationEventsOptimized === 'function') {
    window.bindNavigationEventsOptimized(formId);
    operatonDebugVerbose('Forms', `✅ Navigation handling delegated to Fields module for form ${formId}`);
  } else {
    operatonDebugMinimal('Forms', `WARNING: bindNavigationEventsOptimized not available for form ${formId}`);
  }

  operatonDebugVerbose('Forms', `✅ Page change detection setup complete for form ${formId}`);
};

// =============================================================================
// MAIN DMN SYSTEM INITIALIZATION
// =============================================================================

/**
 * Initialize the main Operaton DMN system
 */
window.initOperatonDMN = function () {
  // Prevent duplicate global initialization
  if (window.operatonInitialized.globalInit) {
    return;
  }

  operatonDebugFrontend('Forms', '🚀 Starting Operaton DMN initialization...');

  const $ = window.jQuery || window.$;
  if (!$) {
    operatonDebugVerbose('Forms', 'jQuery not available for initOperatonDMN');
    return;
  }

  // Bind to Gravity Forms confirmation event
  $(document).on('gform_confirmation_loaded', function (event, formId) {
    operatonDebugVerbose('Forms', `Form ${formId} submitted - confirmation loaded`);
  });

  // Hook into Gravity Forms events if available
  if (typeof gform !== 'undefined' && gform.addAction) {
    // Remove any existing handlers first
    if (gform.removeAction) {
      gform.removeAction('gform_post_render', 'operaton_form_render');
    }

    gform.addAction(
      'gform_post_render',
      function (formId) {
        window.clearDOMCache(formId);

        // Small delay to ensure DOM is fully rendered
        setTimeout(() => {
          window.simpleFormInitialization(formId);
        }, 100);
      },
      10,
      'operaton_form_render'
    );
  }

  // Initial form detection
  setTimeout(() => {
    window.simplifiedFormDetection();
  }, 200);

  // Set global flag
  window.operatonInitialized.globalInit = true;

  operatonDebugFrontend('Forms', '✅ DMN system initialization complete');
};

// =============================================================================
// FORM STATE MANAGEMENT
// =============================================================================

/**
 * Form state manager for tracking initialization and status
 */
window.operatonFormState = window.operatonFormState || {
  initializationStates: new Map(),
  navigationStates: new Map(),

  /**
   * Set initialization state for form
   */
  setInitState: function (formId, state) {
    this.initializationStates.set(formId, {
      state: state,
      timestamp: Date.now(),
    });
    operatonDebugVerbose('Forms', `Initialization state set for form ${formId}: ${state}`);
  },

  /**
   * Get initialization state for form
   */
  getInitState: function (formId) {
    return this.initializationStates.get(formId);
  },

  /**
   * Set navigation state for form
   */
  setNavState: function (formId, state) {
    this.navigationStates.set(formId, {
      state: state,
      timestamp: Date.now(),
    });
    operatonDebugVerbose('Forms', `Navigation state set for form ${formId}: ${state}`);
  },

  /**
   * Get navigation state for form
   */
  getNavState: function (formId) {
    return this.navigationStates.get(formId);
  },

  /**
   * Clear states for form
   */
  clearFormStates: function (formId) {
    this.initializationStates.delete(formId);
    this.navigationStates.delete(formId);
    operatonDebugVerbose('Forms', `Cleared form states for form ${formId}`);
  },

  /**
   * Clear all states
   */
  clearAllStates: function () {
    this.initializationStates.clear();
    this.navigationStates.clear();
    operatonDebugVerbose('Forms', 'Cleared all form states');
  },
};

// =============================================================================
// FIELD LOGIC INTEGRATION
// =============================================================================

/**
 * Enhanced field logic that integrates with form system
 * Handles alleenstaand and children logic without interfering with result fields
 * This version matches what frontend.js expects for proper override functionality
 */
window.OperatonFieldLogic = window.OperatonFieldLogic || {
  // Track forms that have been initialized
  initializedForms: new Set(),

  // Form-specific field mappings
  fieldMappings: {
    2: {
      partnerField: 14,
      alleenstaandField: 33,
      childField: 16,
      childrenField: 34,
    },
    4: {
      partnerField: 14,
      alleenstaandField: 33,
      childField: 16,
      childrenField: 34,
    },
    5: {
      partnerField: 14,
      alleenstaandField: 33,
      childField: 16,
      childrenField: 34,
    },
  },

  /**
   * Initialize field logic for a specific form
   */
  initializeForm: function (formId) {
    if (this.initializedForms.has(formId)) {
      operatonDebugVerbose('Forms', `Field logic already initialized for form ${formId}`);
      return;
    }

    const mapping = this.fieldMappings[formId];
    if (!mapping) {
      operatonDebugVerbose('Forms', `No field logic mapping for form ${formId}`);
      return;
    }

    operatonDebugVerbose('Forms', `Initializing field logic for form ${formId}`);

    const $ = window.jQuery || window.$;
    const $form = $(`#gform_${formId}`);

    if ($form.length === 0) {
      operatonDebugMinimal('Forms', `Form ${formId} not found for field logic`);
      return;
    }

    // Set initial values based on current field content
    this.updateAlleenstaandLogic(formId, mapping, $form);
    this.updateChildrenLogic(formId, mapping, $form);

    // Setup event listeners with proper namespacing
    this.setupEventListeners(formId, mapping, $form);

    this.initializedForms.add(formId);
    operatonDebugVerbose('Forms', `✅ Field logic initialized for form ${formId}`);
  },

  /**
   * Update alleenstaand field based on partner surname
   */
  updateAlleenstaandLogic: function (formId, mapping, $form) {
    const $ = window.jQuery || window.$;
    const $partnerField = $form.find(`#input_${formId}_${mapping.partnerField}`);

    if ($partnerField.length === 0) {
      operatonDebugMinimal('Forms', `Partner field not found: #input_${formId}_${mapping.partnerField}`);
      return;
    }

    const partnerValue = $partnerField.val();
    const isEmpty = !partnerValue || partnerValue.trim() === '';
    const isAlleenstaand = isEmpty;

    operatonDebugVerbose('Forms', `Partner field "${partnerValue}" -> alleenstaand: ${isAlleenstaand}`);

    // Update the radio field directly
    const radioSelector = `input[name="input_${mapping.alleenstaandField}"][value="${
      isAlleenstaand ? 'true' : 'false'
    }"]`;
    const $radio = $form.find(radioSelector);

    if ($radio.length > 0) {
      // Set flag to prevent interference
      window.operatonFieldLogicUpdating = true;

      $radio.prop('checked', true).trigger('change');

      setTimeout(() => {
        window.operatonFieldLogicUpdating = false;
      }, 100);
    }
  },

  /**
   * Update children field based on child birthplace
   */
  updateChildrenLogic: function (formId, mapping, $form) {
    const $ = window.jQuery || window.$;
    const $childField = $form.find(`#input_${formId}_${mapping.childField}`);

    if ($childField.length === 0) {
      operatonDebugMinimal('Forms', `Child field not found: #input_${formId}_${mapping.childField}`);
      return;
    }

    const childValue = $childField.val();
    const hasValue = childValue && childValue.trim() !== '';
    const hasChildren = hasValue;

    operatonDebugVerbose('Forms', `Child field "${childValue}" -> has children: ${hasChildren}`);

    // Update the radio field directly
    const radioSelector = `input[name="input_${mapping.childrenField}"][value="${hasChildren ? 'true' : 'false'}"]`;
    const $radio = $form.find(radioSelector);

    if ($radio.length > 0) {
      // Set flag to prevent interference
      window.operatonFieldLogicUpdating = true;

      $radio.prop('checked', true).trigger('change');

      setTimeout(() => {
        window.operatonFieldLogicUpdating = false;
      }, 100);
    }
  },

  /**
   * Setup event listeners with proper integration
   */
  setupEventListeners: function (formId, mapping, $form) {
    const $ = window.jQuery || window.$;
    const self = this;

    // Partner field listener
    const $partnerField = $form.find(`#input_${formId}_${mapping.partnerField}`);
    if ($partnerField.length > 0) {
      // Remove existing listeners to prevent duplicates
      $partnerField.off('.fieldlogic');

      // Add debounced listener
      let partnerTimeout;
      $partnerField.on('input.fieldlogic change.fieldlogic', function () {
        clearTimeout(partnerTimeout);
        partnerTimeout = setTimeout(() => {
          if (!window.operatonPopulatingResults && !window.operatonFieldLogicUpdating) {
            operatonDebugVerbose('Forms', 'Partner field changed - updating alleenstaand logic');
            self.updateAlleenstaandLogic(formId, mapping, $form);
          }
        }, 150);
      });
    }

    // Child field listener
    const $childField = $form.find(`#input_${formId}_${mapping.childField}`);
    if ($childField.length > 0) {
      // Remove existing listeners to prevent duplicates
      $childField.off('.fieldlogic');

      // Add debounced listener
      let childTimeout;
      $childField.on('input.fieldlogic change.fieldlogic', function () {
        clearTimeout(childTimeout);
        childTimeout = setTimeout(() => {
          if (!window.operatonPopulatingResults && !window.operatonFieldLogicUpdating) {
            operatonDebugVerbose('Forms', 'Child field changed - updating children logic');
            self.updateChildrenLogic(formId, mapping, $form);
          }
        }, 150);
      });
    }
  },

  /**
   * Clear initialization for a form (useful for cleanup)
   */
  clearForm: function (formId) {
    const $ = window.jQuery || window.$;
    const $form = $(`#gform_${formId}`);

    // Remove event listeners
    $form.find('input').off('.fieldlogic');

    // Remove from initialized set
    this.initializedForms.delete(formId);

    operatonDebugVerbose('Forms', `Field logic cleared for form ${formId}`);
  },

  /**
   * Add new form mapping
   */
  addFormMapping: function (formId, mapping) {
    this.fieldMappings[formId] = mapping;
    operatonDebugVerbose('Forms', `Added field mapping for form ${formId}:`, mapping);
  },
};

// =============================================================================
// FORM DEBUG FUNCTIONS
// =============================================================================

/**
 * Debug function to inspect forms state
 * @returns {Object} Current forms state information
 */
window.operatonDebugForms = function () {
  const formsInfo = {
    initializedForms: Array.from(window.operatonInitialized.forms),
    initializationStates: Array.from(window.operatonFormState.initializationStates.entries()),
    navigationStates: Array.from(window.operatonFormState.navigationStates.entries()),
    globalInitialized: window.operatonInitialized.globalInit,
    initInProgress: window.operatonInitialized.initInProgress,
    performanceStats: window.operatonInitialized.performanceStats,
    availableFunctions: {
      simplifiedFormDetection: typeof window.simplifiedFormDetection,
      simpleFormInitialization: typeof window.simpleFormInitialization,
      setupPageChangeDetection: typeof window.setupPageChangeDetection,
      initOperatonDMN: typeof window.initOperatonDMN,
    },
    moduleStatus: 'forms-module-active',
  };

  operatonDebugVerbose('Forms', 'Forms Debug Info:', formsInfo);
  return formsInfo;
};

// =============================================================================
// MODULE COMPLETION FLAG
// =============================================================================

// Mark Forms module as loaded
window.operatonModulesLoaded = window.operatonModulesLoaded || {};
window.operatonModulesLoaded.forms = true;

operatonDebugFrontend('Forms', 'Frontend forms module loaded successfully');
