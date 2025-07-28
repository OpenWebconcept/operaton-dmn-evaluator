/**
 * Simplified Radio Button Synchronization Handler for Operaton DMN Plugin
 *
 * FIXED VERSION: Resolves conflicts with multiple radio sync scripts
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

(function () {
  'use strict';

  // Prevent multiple instances
  if (window.OperatonRadioSyncSimplified) {
    console.log('Operaton Radio Sync: Already initialized, skipping duplicate');
    return;
  }

  /**
   * Simplified Radio Button Synchronization Manager
   */
  var OperatonRadioSyncSimplified = {
    /**
     * Initialization flag
     */
    initialized: false,

    /**
     * Field mappings for radio button synchronization
     */
    fieldMappings: {
      aanvragerDitKalenderjaarAlAangevraagd: 'input_8_25',
      aanvragerAanmerkingStudieFinanciering: 'input_8_26',
      aanvragerUitkeringBaanbrekers: 'input_8_27',
      aanvragerVoedselbankpasDenBosch: 'input_8_28',
      aanvragerKwijtscheldingGemeentelijkeBelastingen: 'input_8_29',
      aanvragerSchuldhulptrajectKredietbankNederland: 'input_8_30',
      aanvragerHeeftKind4Tm17: 'input_8_31',
    },

    /**
     * Initialize radio button synchronization
     */
    init: function () {
      if (this.initialized) {
        console.log('🔄 Radio Sync: Already initialized');
        return;
      }

      console.log('🔄 Initializing simplified radio button synchronization');

      // Wait for jQuery and DOM to be ready
      this.waitForReady(function () {
        OperatonRadioSyncSimplified.setupSynchronization();
        OperatonRadioSyncSimplified.bindEvents();
        OperatonRadioSyncSimplified.restoreRadioStates();
        OperatonRadioSyncSimplified.initialized = true;

        console.log('✅ Simplified radio button synchronization initialized');
      });
    },

    /**
     * Wait for jQuery and DOM readiness
     */
    waitForReady: function (callback) {
      var attempts = 0;
      var maxAttempts = 100;

      function check() {
        attempts++;

        if (typeof jQuery !== 'undefined' && document.readyState !== 'loading' && document.querySelector('#gform_8')) {
          callback();
          return;
        }

        if (attempts < maxAttempts) {
          setTimeout(check, 100);
        } else {
          console.error('❌ Radio sync: Requirements not met after', maxAttempts, 'attempts');
        }
      }
      check();
    },

    /**
     * Set up synchronization system
     */
    setupSynchronization: function () {
      var $ = jQuery;
      var self = this;

      console.log('Setting up radio button synchronization for', Object.keys(this.fieldMappings).length, 'fields');

      // Set up bidirectional synchronization
      this.setupRadioToHiddenSync();
      this.setupHiddenToRadioSync();
    },

    /**
     * Set up radio button to hidden field synchronization
     */
    setupRadioToHiddenSync: function () {
      var $ = jQuery;
      var self = this;

      // Bind change events to all radio buttons
      $(document).on('change.operaton-radio-sync', 'input[type="radio"]', function () {
        var $radio = $(this);
        var radioName = $radio.attr('name');
        var radioValue = $radio.val();

        // Only sync if this is one of our mapped radio buttons
        if (self.fieldMappings[radioName]) {
          self.syncRadioToHidden(radioName, radioValue);
        }
      });

      console.log('✅ Radio to hidden synchronization set up');
    },

    /**
     * Set up hidden field to radio button synchronization
     */
    setupHiddenToRadioSync: function () {
      var $ = jQuery;
      var self = this;

      // Monitor hidden field changes
      $.each(this.fieldMappings, function (radioName, hiddenFieldId) {
        $(document).on('change.operaton-radio-sync', '#' + hiddenFieldId, function () {
          var value = $(this).val();
          if (value) {
            self.syncHiddenToRadio(radioName, value);
          }
        });
      });

      console.log('✅ Hidden to radio synchronization set up');
    },

    /**
     * Synchronize radio button selection to hidden field
     */
    syncRadioToHidden: function (radioName, value) {
      var $ = jQuery;
      var hiddenFieldId = this.fieldMappings[radioName];

      if (!hiddenFieldId) {
        return;
      }

      var $hiddenField = $('#' + hiddenFieldId);

      if ($hiddenField.length) {
        var currentValue = $hiddenField.val();

        if (currentValue !== value) {
          $hiddenField.val(value);
          $hiddenField.trigger('change');

          console.log('🔄 Synced radio to hidden:', radioName, '=', value, '→', hiddenFieldId);
        }
      }
    },

    /**
     * Synchronize hidden field value to radio button selection
     */
    syncHiddenToRadio: function (radioName, value) {
      var $ = jQuery;

      if (!value || (value !== 'true' && value !== 'false')) {
        return;
      }

      var $radioButton = $('input[type="radio"][name="' + radioName + '"][value="' + value + '"]');

      if ($radioButton.length && !$radioButton.is(':checked')) {
        $radioButton.prop('checked', true);
        $radioButton.trigger('change');

        var hiddenFieldId = this.fieldMappings[radioName];
        console.log('🔄 Synced hidden to radio:', hiddenFieldId, '=', value, '→', radioName);
      }
    },

    /**
     * Restore radio button states from hidden fields
     */
    restoreRadioStates: function () {
      var $ = jQuery;
      var self = this;

      console.log('🔄 Restoring radio button states...');

      $.each(this.fieldMappings, function (radioName, hiddenFieldId) {
        var $hiddenField = $('#' + hiddenFieldId);

        if ($hiddenField.length) {
          var value = $hiddenField.val();

          if (value && (value === 'true' || value === 'false')) {
            self.syncHiddenToRadio(radioName, value);
          } else {
            // Set default value if empty
            var $defaultRadio = $('input[type="radio"][name="' + radioName + '"]:checked');
            if ($defaultRadio.length) {
              self.syncRadioToHidden(radioName, $defaultRadio.val());
            } else {
              // Default to 'false' if no selection
              var $falseRadio = $('input[type="radio"][name="' + radioName + '"][value="false"]');
              if ($falseRadio.length) {
                $falseRadio.prop('checked', true);
                self.syncRadioToHidden(radioName, 'false');
              }
            }
          }
        }
      });
    },

    /**
     * Bind additional events for form integration
     */
    bindEvents: function () {
      var $ = jQuery;
      var self = this;

      // Handle Gravity Forms page navigation
      if (typeof gform !== 'undefined' && gform.addAction) {
        gform.addAction(
          'gform_page_loaded',
          function (form_id, current_page) {
            if (form_id == 8) {
              setTimeout(function () {
                self.restoreRadioStates();
              }, 200);
            }
          },
          10,
          'operaton_radio_sync_simple'
        );
      }

      // Handle form submission validation
      $(document).on('gform_pre_submission_8', function (event) {
        self.validateAndSync();
      });

      console.log('✅ Additional events bound');
    },

    /**
     * Validate and synchronize all radio buttons before form operations
     */
    validateAndSync: function () {
      var $ = jQuery;
      var self = this;

      console.log('🔍 Validating and syncing all radio buttons...');

      $.each(this.fieldMappings, function (radioName, hiddenFieldId) {
        var $hiddenField = $('#' + hiddenFieldId);
        var $radioButtons = $('input[type="radio"][name="' + radioName + '"]');

        if ($hiddenField.length && $radioButtons.length) {
          var hiddenValue = $hiddenField.val();
          var $checkedRadio = $('input[type="radio"][name="' + radioName + '"]:checked');

          // Ensure radio selection matches hidden field
          if ($checkedRadio.length && $checkedRadio.val() !== hiddenValue) {
            $hiddenField.val($checkedRadio.val());
            console.log('🔧 Fixed sync mismatch for:', radioName);
          }

          // Ensure a selection is made
          if (!$checkedRadio.length && hiddenValue) {
            self.syncHiddenToRadio(radioName, hiddenValue);
          }
        }
      });
    },

    /**
     * Force synchronization of all radio buttons
     */
    forceSyncAll: function () {
      console.log('🔄 Force synchronizing all radio buttons...');

      this.restoreRadioStates();
      this.validateAndSync();

      console.log('✅ Force synchronization complete');
    },

    /**
     * Get current synchronization status for debugging
     */
    getStatus: function () {
      var $ = jQuery;
      var status = {
        initialized: this.initialized,
        mappings: this.fieldMappings,
        states: {},
        mismatches: [],
      };

      var self = this;

      $.each(this.fieldMappings, function (radioName, hiddenFieldId) {
        var $hiddenField = $('#' + hiddenFieldId);
        var $checkedRadio = $('input[type="radio"][name="' + radioName + '"]:checked');

        var hiddenValue = $hiddenField.length ? $hiddenField.val() : null;
        var radioValue = $checkedRadio.length ? $checkedRadio.val() : null;

        status.states[radioName] = {
          hidden_field_id: hiddenFieldId,
          hidden_value: hiddenValue,
          radio_value: radioValue,
          in_sync: hiddenValue === radioValue,
        };

        if (hiddenValue !== radioValue) {
          status.mismatches.push({
            radio_name: radioName,
            hidden_value: hiddenValue,
            radio_value: radioValue,
          });
        }
      });

      return status;
    },
  };

  // Enhanced initialization with conflict detection
  function initializeRadioSyncSafely() {
    // Check if other radio sync scripts are running
    var conflictingScripts = ['OperatonRadioSync', 'window.operaton_radio_sync'];

    var hasConflicts = false;
    conflictingScripts.forEach(function (scriptName) {
      if (window[scriptName]) {
        console.warn('⚠️ Radio Sync: Potential conflict detected with', scriptName);
        hasConflicts = true;
      }
    });

    if (hasConflicts) {
      console.log('🔧 Radio Sync: Running in simplified mode to avoid conflicts');
    }

    // Check if jQuery is available
    if (typeof jQuery === 'undefined') {
      console.log('Radio Sync: Waiting for jQuery...');
      setTimeout(initializeRadioSyncSafely, 250);
      return;
    }

    // Check if form exists
    if (!document.querySelector('#gform_8')) {
      console.log('Radio Sync: Waiting for form...');
      setTimeout(initializeRadioSyncSafely, 250);
      return;
    }

    // Initialize the sync system
    OperatonRadioSyncSimplified.init();

    // Make available globally for debugging and external access
    window.OperatonRadioSyncSimplified = OperatonRadioSyncSimplified;

    // Add debug command
    if (typeof console !== 'undefined') {
      window.debugRadioSyncSimplified = function () {
        console.log('Simplified Radio Sync Status:', OperatonRadioSyncSimplified.getStatus());
      };
    }
  }

  // Start initialization based on document state
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      setTimeout(initializeRadioSyncSafely, 500);
    });
  } else {
    setTimeout(initializeRadioSyncSafely, 500);
  }

  // Also initialize on window load as a fallback
  if (typeof window.addEventListener !== 'undefined') {
    window.addEventListener('load', function () {
      setTimeout(function () {
        if (typeof window.OperatonRadioSyncSimplified !== 'undefined') {
          window.OperatonRadioSyncSimplified.forceSyncAll();
        } else {
          // Final attempt to initialize
          initializeRadioSyncSafely();
        }
      }, 1000);
    });
  }
})();
