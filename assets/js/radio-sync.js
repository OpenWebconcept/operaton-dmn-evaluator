/**
 * Multi-Form Radio Button Synchronization Handler for Operaton DMN Plugin
 *
 * FIXED VERSION: Supports multiple forms and prevents infinite loops
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

(function () {
  'use strict';

  // Prevent multiple instances
  if (window.OperatonRadioSyncMultiForm) {
    console.log('Operaton Radio Sync: Already initialized, skipping duplicate');
    return;
  }

  /**
   * Multi-Form Radio Button Synchronization Manager
   */
  var OperatonRadioSyncMultiForm = {
    /**
     * Initialization flag
     */
    initialized: false,

    /**
     * Track which forms have been initialized
     */
    initializedForms: new Set(),

    /**
     * Form-specific field mappings
     * Key: formId, Value: mapping object
     */
    formFieldMappings: {
      8: {
        aanvragerDitKalenderjaarAlAangevraagd: 'input_8_25',
        aanvragerAanmerkingStudieFinanciering: 'input_8_26',
        aanvragerUitkeringBaanbrekers: 'input_8_27',
        aanvragerVoedselbankpasDenBosch: 'input_8_28',
        aanvragerKwijtscheldingGemeentelijkeBelastingen: 'input_8_29',
        aanvragerSchuldhulptrajectKredietbankNederland: 'input_8_30',
        aanvragerHeeftKind4Tm17: 'input_8_31',
      },
      11: {
        aanvragerDitKalenderjaarAlAangevraagd: 'input_11_25',
        aanvragerAanmerkingStudieFinanciering: 'input_11_26',
        aanvragerUitkeringBaanbrekers: 'input_11_27',
        aanvragerVoedselbankpasDenBosch: 'input_11_28',
        aanvragerKwijtscheldingGemeentelijkeBelastingen: 'input_11_29',
        aanvragerSchuldhulptrajectKredietbankNederland: 'input_11_30',
        aanvragerHeeftKind4Tm17: 'input_11_31',
      },
    },

    /**
     * Initialize radio button synchronization for all forms
     */
    init: function () {
      if (this.initialized) {
        console.log('Multi-form Radio Sync: Already initialized');
        return;
      }

      console.log('Initializing multi-form radio button synchronization');

      // Wait for jQuery and DOM to be ready
      this.waitForReady(() => {
        this.setupGlobalSynchronization();
        this.bindGlobalEvents();
        this.initializeDetectedForms();
        this.initialized = true;

        console.log('Multi-form radio button synchronization initialized');
      });
    },

    /**
     * Wait for jQuery and DOM readiness (improved version)
     */
    waitForReady: function (callback) {
      var attempts = 0;
      var maxAttempts = 50; // Reduced from 100 to prevent long waits

      function check() {
        attempts++;

        // Check for jQuery and basic DOM readiness
        if (typeof jQuery !== 'undefined' && document.readyState !== 'loading') {
          callback();
          return;
        }

        if (attempts < maxAttempts) {
          setTimeout(check, 100);
        } else {
          console.error('Radio sync: Basic requirements not met after', maxAttempts, 'attempts');
          // Try to initialize anyway if jQuery is available
          if (typeof jQuery !== 'undefined') {
            console.log('Radio sync: Attempting initialization with jQuery only');
            callback();
          }
        }
      }
      check();
    },

    /**
     * Initialize forms that are detected on the page
     */
    initializeDetectedForms: function () {
      var $ = jQuery;
      var self = this;

      // Look for any forms that match our configurations
      Object.keys(this.formFieldMappings).forEach(function (formId) {
        var $form = $('#gform_' + formId);
        if ($form.length > 0) {
          console.log('Detected form for radio sync:', formId);
          self.initializeFormSync(formId);
        }
      });

      // Also scan for forms dynamically (in case form IDs change)
      $('form[id^="gform_"]').each(function () {
        var formId = parseInt($(this).attr('id').replace('gform_', ''));
        if (formId && !self.initializedForms.has(formId)) {
          // Check if this form has the radio pattern we expect
          var hasExpectedRadios = $(this).find('input[name^="aanvrager"][type="radio"]').length > 0;
          if (hasExpectedRadios) {
            console.log('Auto-detected form with radio sync pattern:', formId);
            self.autoConfigureForm(formId);
            self.initializeFormSync(formId);
          }
        }
      });
    },

    /**
     * Auto-configure a form by detecting its field pattern
     */
    autoConfigureForm: function (formId) {
      var $ = jQuery;
      var mapping = {};

      // Look for hidden fields that match our pattern
      $(`#gform_${formId} input[type="hidden"][id^="input_${formId}_"]`).each(function () {
        var $hidden = $(this);
        var hiddenId = $hidden.attr('id');
        var fieldNumber = hiddenId.replace(`input_${formId}_`, '');

        // Try to find a corresponding radio button name by checking the admin labels
        var possibleRadioNames = [
          'aanvragerDitKalenderjaarAlAangevraagd',
          'aanvragerAanmerkingStudieFinanciering',
          'aanvragerUitkeringBaanbrekers',
          'aanvragerVoedselbankpasDenBosch',
          'aanvragerKwijtscheldingGemeentelijkeBelastingen',
          'aanvragerSchuldhulptrajectKredietbankNederland',
          'aanvragerHeeftKind4Tm17',
        ];

        // Map based on field numbers (assuming same pattern as form 8)
        var baseFieldNumbers = [25, 26, 27, 28, 29, 30, 31];
        var baseIndex = baseFieldNumbers.indexOf(parseInt(fieldNumber));

        if (baseIndex >= 0 && baseIndex < possibleRadioNames.length) {
          mapping[possibleRadioNames[baseIndex]] = hiddenId;
        }
      });

      if (Object.keys(mapping).length > 0) {
        this.formFieldMappings[formId] = mapping;
        console.log('Auto-configured radio sync for form', formId, ':', mapping);
      }
    },

    /**
     * Initialize synchronization for a specific form
     */
    initializeFormSync: function (formId) {
      if (this.initializedForms.has(formId)) {
        console.log('Radio sync already initialized for form:', formId);
        return;
      }

      var mapping = this.formFieldMappings[formId];
      if (!mapping) {
        console.log('No radio sync mapping for form:', formId);
        return;
      }

      console.log('Initializing radio sync for form:', formId);

      // Set flag to prevent interference with other systems
      window.operatonRadioSyncInProgress = true;

      this.setupFormSpecificSync(formId, mapping);
      this.restoreRadioStatesForForm(formId, mapping);

      this.initializedForms.add(formId);

      setTimeout(() => {
        window.operatonRadioSyncInProgress = false;
      }, 500);

      console.log('Radio sync initialized for form:', formId);
    },

    /**
     * Set up synchronization for a specific form
     */
    setupFormSpecificSync: function (formId, mapping) {
      var $ = jQuery;
      var self = this;

      // Radio to hidden synchronization for this form
      Object.keys(mapping).forEach(function (radioName) {
        $(document).off(`change.radio-sync-${formId}-${radioName}`);
        $(document).on(
          `change.radio-sync-${formId}-${radioName}`,
          `input[type="radio"][name="${radioName}"]`,
          function () {
            var $radio = $(this);
            var radioValue = $radio.val();
            self.syncRadioToHidden(formId, radioName, radioValue);
          }
        );
      });

      // Hidden to radio synchronization for this form
      Object.entries(mapping).forEach(function ([radioName, hiddenFieldId]) {
        $(document).off(`change.radio-sync-${formId}-${hiddenFieldId}`);
        $(document).on(`change.radio-sync-${formId}-${hiddenFieldId}`, '#' + hiddenFieldId, function () {
          var value = $(this).val();
          if (value) {
            self.syncHiddenToRadio(formId, radioName, value);
          }
        });
      });

      console.log('Form-specific sync set up for form:', formId);
    },

    /**
     * Set up global synchronization system (backwards compatibility)
     */
    setupGlobalSynchronization: function () {
      // This method is kept for backwards compatibility
      // The actual work is now done in setupFormSpecificSync
      console.log('Global synchronization system ready');
    },

    /**
     * Synchronize radio button selection to hidden field
     */
    syncRadioToHidden: function (formId, radioName, value) {
      var $ = jQuery;
      var mapping = this.formFieldMappings[formId];

      if (!mapping) {
        return;
      }

      var hiddenFieldId = mapping[radioName];
      if (!hiddenFieldId) {
        return;
      }

      var $hiddenField = $('#' + hiddenFieldId);

      if ($hiddenField.length) {
        var currentValue = $hiddenField.val();

        if (currentValue !== value) {
          $hiddenField.val(value);
          $hiddenField.trigger('change');

          console.log('Synced radio to hidden (form', formId + '):', radioName, '=', value, '→', hiddenFieldId);
        }
      }
    },

    /**
     * Synchronize hidden field value to radio button selection
     */
    syncHiddenToRadio: function (formId, radioName, value) {
      var $ = jQuery;

      if (!value || (value !== 'true' && value !== 'false')) {
        return;
      }

      var $radioButton = $('input[type="radio"][name="' + radioName + '"][value="' + value + '"]');

      if ($radioButton.length && !$radioButton.is(':checked')) {
        $radioButton.prop('checked', true);
        $radioButton.trigger('change');

        var mapping = this.formFieldMappings[formId];
        var hiddenFieldId = mapping ? mapping[radioName] : 'unknown';
        console.log('Synced hidden to radio (form', formId + '):', hiddenFieldId, '=', value, '→', radioName);
      }
    },

    /**
     * Restore radio button states from hidden fields for a specific form
     */
    restoreRadioStatesForForm: function (formId, mapping) {
      var $ = jQuery;
      var self = this;

      console.log('Restoring radio button states for form:', formId);

      Object.entries(mapping).forEach(function ([radioName, hiddenFieldId]) {
        var $hiddenField = $('#' + hiddenFieldId);

        if ($hiddenField.length) {
          var value = $hiddenField.val();

          if (value && (value === 'true' || value === 'false')) {
            self.syncHiddenToRadio(formId, radioName, value);
          } else {
            // Set default value if empty
            var $defaultRadio = $('input[type="radio"][name="' + radioName + '"]:checked');
            if ($defaultRadio.length) {
              self.syncRadioToHidden(formId, radioName, $defaultRadio.val());
            } else {
              // Default to 'false' if no selection
              var $falseRadio = $('input[type="radio"][name="' + radioName + '"][value="false"]');
              if ($falseRadio.length) {
                $falseRadio.prop('checked', true);
                self.syncRadioToHidden(formId, radioName, 'false');
              }
            }
          }
        }
      });
    },

    /**
     * Bind global events for form integration
     */
    bindGlobalEvents: function () {
      var $ = jQuery;
      var self = this;

      // Handle Gravity Forms page navigation for all forms
      if (typeof gform !== 'undefined' && gform.addAction) {
        gform.addAction(
          'gform_page_loaded',
          function (form_id, current_page) {
            if (self.formFieldMappings[form_id]) {
              setTimeout(function () {
                self.restoreRadioStatesForForm(form_id, self.formFieldMappings[form_id]);
              }, 200);
            }
          },
          10,
          'operaton_radio_sync_multi_form'
        );
      }

      // Handle form submission validation for all configured forms
      Object.keys(this.formFieldMappings).forEach(function (formId) {
        $(document).on('gform_pre_submission_' + formId, function (event) {
          self.validateAndSyncForm(formId);
        });
      });

      console.log('Global events bound for multi-form radio sync');
    },

    /**
     * Validate and synchronize all radio buttons for a specific form
     */
    validateAndSyncForm: function (formId) {
      var $ = jQuery;
      var self = this;
      var mapping = this.formFieldMappings[formId];

      if (!mapping) {
        return;
      }

      console.log('Validating and syncing radio buttons for form:', formId);

      Object.entries(mapping).forEach(function ([radioName, hiddenFieldId]) {
        var $hiddenField = $('#' + hiddenFieldId);
        var $radioButtons = $('input[type="radio"][name="' + radioName + '"]');

        if ($hiddenField.length && $radioButtons.length) {
          var hiddenValue = $hiddenField.val();
          var $checkedRadio = $('input[type="radio"][name="' + radioName + '"]:checked');

          // Ensure radio selection matches hidden field
          if ($checkedRadio.length && $checkedRadio.val() !== hiddenValue) {
            $hiddenField.val($checkedRadio.val());
            console.log('Fixed sync mismatch for form', formId, ':', radioName);
          }

          // Ensure a selection is made
          if (!$checkedRadio.length && hiddenValue) {
            self.syncHiddenToRadio(formId, radioName, hiddenValue);
          }
        }
      });
    },

    /**
     * Force synchronization of all radio buttons for a specific form
     */
    forceSyncForm: function (formId) {
      var mapping = this.formFieldMappings[formId];
      if (!mapping) {
        console.log('No mapping found for form:', formId);
        return;
      }

      console.log('Force synchronizing radio buttons for form:', formId);

      this.restoreRadioStatesForForm(formId, mapping);
      this.validateAndSyncForm(formId);

      console.log('Force synchronization complete for form:', formId);
    },

    /**
     * Force synchronization for all forms
     */
    forceSyncAll: function () {
      var self = this;
      console.log('Force synchronizing all forms...');

      Object.keys(this.formFieldMappings).forEach(function (formId) {
        if (document.querySelector('#gform_' + formId)) {
          self.forceSyncForm(formId);
        }
      });

      console.log('Force synchronization complete for all forms');
    },

    /**
     * Add a new form configuration
     */
    addFormConfiguration: function (formId, mapping) {
      this.formFieldMappings[formId] = mapping;
      console.log('Added radio sync configuration for form:', formId, mapping);

      // Initialize immediately if the form exists
      if (document.querySelector('#gform_' + formId)) {
        this.initializeFormSync(formId);
      }
    },

    /**
     * Remove form configuration (cleanup)
     */
    removeFormConfiguration: function (formId) {
      var $ = jQuery;

      // Remove event listeners
      Object.keys(this.formFieldMappings[formId] || {}).forEach(function (radioName) {
        $(document).off(`change.radio-sync-${formId}-${radioName}`);
      });

      // Remove from tracking
      delete this.formFieldMappings[formId];
      this.initializedForms.delete(formId);

      console.log('Removed radio sync configuration for form:', formId);
    },

    /**
     * Get current synchronization status for debugging
     */
    getStatus: function () {
      var $ = jQuery;
      var status = {
        initialized: this.initialized,
        initializedForms: Array.from(this.initializedForms),
        formMappings: this.formFieldMappings,
        formStates: {},
      };

      var self = this;

      Object.entries(this.formFieldMappings).forEach(function ([formId, mapping]) {
        status.formStates[formId] = {};

        Object.entries(mapping).forEach(function ([radioName, hiddenFieldId]) {
          var $hiddenField = $('#' + hiddenFieldId);
          var $checkedRadio = $('input[type="radio"][name="' + radioName + '"]:checked');

          var hiddenValue = $hiddenField.length ? $hiddenField.val() : null;
          var radioValue = $checkedRadio.length ? $checkedRadio.val() : null;

          status.formStates[formId][radioName] = {
            hidden_field_id: hiddenFieldId,
            hidden_value: hiddenValue,
            radio_value: radioValue,
            in_sync: hiddenValue === radioValue,
            form_exists: document.querySelector('#gform_' + formId) !== null,
          };
        });
      });

      return status;
    },

    /**
     * Bind additional events - kept for backwards compatibility
     */
    bindEvents: function () {
      // This method is kept for backwards compatibility
      // Actual binding is now done in bindGlobalEvents
    },

    setupRadioToHiddenSync: function () {
      // This method is kept for backwards compatibility
      // Actual setup is now done in setupFormSpecificSync
    },

    setupHiddenToRadioSync: function () {
      // This method is kept for backwards compatibility
      // Actual setup is now done in setupFormSpecificSync
    },

    restoreRadioStates: function () {
      // This method is kept for backwards compatibility
      // Now calls the multi-form version
      this.forceSyncAll();
    },
  };

  // Enhanced initialization with better error handling
  function initializeRadioSyncSafely() {
    // Check if other radio sync scripts are running
    var conflictingScripts = ['OperatonRadioSync', 'OperatonRadioSyncSimplified'];

    var hasConflicts = false;
    conflictingScripts.forEach(function (scriptName) {
      if (window[scriptName]) {
        console.warn('Radio Sync: Potential conflict detected with', scriptName);
        hasConflicts = true;
      }
    });

    if (hasConflicts) {
      console.log('Radio Sync: Running in multi-form mode alongside other scripts');
    }

    // Check if jQuery is available
    if (typeof jQuery === 'undefined') {
      console.log('Radio Sync: Waiting for jQuery...');
      setTimeout(initializeRadioSyncSafely, 250);
      return;
    }

    // Initialize the sync system (no longer waits for specific form)
    OperatonRadioSyncMultiForm.init();

    // Make available globally
    window.OperatonRadioSyncMultiForm = OperatonRadioSyncMultiForm;

    // Add debug commands
    if (typeof console !== 'undefined') {
      window.debugRadioSyncMultiForm = function () {
        console.log('Multi-Form Radio Sync Status:', OperatonRadioSyncMultiForm.getStatus());
      };

      window.forceRadioSync = function (formId) {
        if (formId) {
          OperatonRadioSyncMultiForm.forceSyncForm(formId);
        } else {
          OperatonRadioSyncMultiForm.forceSyncAll();
        }
      };
    }
  }

  // Start initialization based on document state
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(initializeRadioSyncSafely, 100);
    });
  } else {
    setTimeout(initializeRadioSyncSafely, 100);
  }

  // Also initialize on window load as a fallback
  if (typeof window.addEventListener !== 'undefined') {
    window.addEventListener('load', function () {
      setTimeout(function () {
        if (typeof window.OperatonRadioSyncMultiForm !== 'undefined') {
          window.OperatonRadioSyncMultiForm.forceSyncAll();
        } else {
          // Final attempt to initialize
          initializeRadioSyncSafely();
        }
      }, 500);
    });
  }
})();
