/**
 * Gravity Forms Integration JavaScript for Operaton DMN Plugin
 *
 * Handles form evaluation, button placement, and decision flow integration
 * for Gravity Forms with DMN configurations.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

(function ($) {
  'use strict';

  // CRITICAL FIX: Wait for required objects before initializing
  function initializeWhenReady() {
    function debugAvailableObjects() {
      console.log('=== OPERATON DEBUG INFO ===');
      console.log('typeof operaton_ajax:', typeof operaton_ajax);
      console.log('typeof operaton_gravity:', typeof operaton_gravity);
      console.log('typeof operaton_config_9:', typeof window.operaton_config_9);
      console.log('window.operaton_ajax:', window.operaton_ajax);
      console.log(
        'Available window objects:',
        Object.keys(window).filter(key => key.includes('operaton'))
      );
      console.log('========================');
    }

    debugAvailableObjects();

    // Check if required objects are available
    if (typeof operaton_ajax === 'undefined') {
      console.log('Operaton DMN: operaton_ajax still not available, waiting...');
      setTimeout(initializeWhenReady, 500);
      return;
    }

    console.log('Operaton DMN: operaton_ajax found! Initializing Gravity Forms integration');

    if (typeof operaton_gravity === 'undefined') {
      console.warn('Operaton DMN: operaton_gravity object not found - using defaults');
      window.operaton_gravity = {
        strings: {
          validation_failed: 'Please complete all required fields before evaluation.',
          evaluation_in_progress: 'Evaluation in progress...',
          form_error: 'Form validation failed. Please check your entries.',
        },
      };
    }

    // Initialize the integration
    OperatonGravityForms.init();

    // Make available globally for debugging
    window.OperatonGravityForms = OperatonGravityForms;

    // Add debug command
    if (operaton_ajax.debug) {
      window.operatonDebug = function () {
        console.log('Operaton DMN Debug Info:', OperatonGravityForms.getDebugInfo());
      };
      console.log('Debug mode enabled. Use operatonDebug() to get debug information.');
    }
  }

  // CRITICAL FIX: Update the performEvaluation method in OperatonGravityForms
  var OperatonGravityForms = {
    /**
     * Initialize the Gravity Forms integration
     * Sets up event handlers and form detection
     */
    init: function () {
      console.log('Operaton DMN: Gravity Forms integration initialized');
      this.bindEvents();
      this.detectForms();
    },

    /**
     * Bind event handlers for form interaction
     * Sets up click handlers and form events
     */
    bindEvents: function () {
      // Handle evaluate button clicks
      $(document).on('click', '.operaton-evaluate-btn', this.handleEvaluateClick);

      // Handle form page changes
      $(document).on('gform_page_loaded', this.handlePageLoaded);

      // Handle form rendering
      $(document).on('gform_post_render', this.handleFormRender);

      // Handle decision flow refresh buttons
      $(document).on('click', '.refresh-decision-flow-controlled', this.handleDecisionFlowRefresh);

      // Handle form validation events
      $(document).on('gform_post_validation', this.handleFormValidation);
    },

    /**
     * Detect forms on the current page that have DMN configurations
     * Initializes form-specific functionality
     */
    detectForms: function () {
      var self = this;

      // Find all Gravity Forms on the page
      $('form[id^="gform_"]').each(function () {
        var $form = $(this);
        var formId = self.extractFormId($form);

        if (formId && window['operaton_config_' + formId]) {
          console.log('DMN-enabled form detected:', formId);
          self.initializeForm(formId);
        }
      });
    },

    /**
     * Extract form ID from form element
     * Parses the form ID from the form's ID attribute
     *
     * @param {jQuery} $form Form element
     * @return {number|null} Form ID or null if not found
     */
    extractFormId: function ($form) {
      var formId = $form.attr('id');
      if (formId && formId.indexOf('gform_') === 0) {
        return parseInt(formId.replace('gform_', ''));
      }
      return null;
    },

    /**
     * Initialize a specific form with DMN functionality
     * Sets up form-specific event handlers and UI elements
     *
     * @param {number} formId Gravity Forms form ID
     */
    initializeForm: function (formId) {
      var config = window['operaton_config_' + formId];
      if (!config) {
        return;
      }

      console.log('Initializing DMN functionality for form:', formId);

      // Set up button placement logic
      this.setupButtonPlacement(formId, config);

      // Set up decision flow if enabled
      if (config.show_decision_flow && config.use_process) {
        this.setupDecisionFlow(formId);
      }

      // Add form validation enhancements
      this.setupFormValidation(formId, config);
    },

    /**
     * Set up button placement logic for multi-page forms
     * Handles dynamic button showing/hiding based on current page
     *
     * @param {number} formId Form ID
     * @param {object} config Form configuration
     */
    setupButtonPlacement: function (formId, config) {
      var self = this;
      var targetPage = parseInt(config.evaluation_step) || 2;

      // Initial button placement
      setTimeout(function () {
        self.updateButtonPlacement(formId, targetPage);
      }, 500);

      // Update on page changes
      $(document).on('gform_page_loaded', function (event, form_id, current_page) {
        if (form_id == formId) {
          setTimeout(function () {
            self.updateButtonPlacement(formId, targetPage);
          }, 200);
        }
      });
    },

    /**
     * Update button placement based on current page
     * Shows or hides the evaluate button based on form page
     *
     * @param {number} formId Form ID
     * @param {number} targetPage Target page for button display
     */
    updateButtonPlacement: function (formId, targetPage) {
      var currentPage = this.getCurrentPage(formId);
      var $button = $('#operaton-evaluate-' + formId);

      console.log('Updating button placement - Form:', formId, 'Current page:', currentPage, 'Target:', targetPage);

      if (currentPage === targetPage) {
        this.showEvaluateButton(formId);
      } else {
        $button.hide();
      }
    },

    /**
     * Get the current page number for a multi-page form
     * Determines which page of the form is currently displayed
     *
     * @param {number} formId Form ID
     * @return {number} Current page number
     */
    getCurrentPage: function (formId) {
      // Check URL parameter first
      var urlParams = new URLSearchParams(window.location.search);
      var gfPage = urlParams.get('gf_page');
      if (gfPage) {
        return parseInt(gfPage);
      }

      // Check Gravity Forms page field
      var $pageField = $('#gform_source_page_number_' + formId);
      if ($pageField.length && $pageField.val()) {
        return parseInt($pageField.val());
      }

      // Check for visible page breaks to determine current page
      var $form = $('#gform_' + formId);
      var currentPage = 1;

      $form.find('.gf_page_break').each(function (index) {
        var $pageBreak = $(this);
        if ($pageBreak.is(':visible')) {
          currentPage = index + 2; // Page breaks are 0-indexed, pages are 1-indexed
        }
      });

      return currentPage;
    },

    /**
     * Show the evaluate button for a form
     * Places the button in the appropriate location and makes it visible
     *
     * @param {number} formId Form ID
     */
    showEvaluateButton: function (formId) {
      var $button = $('#operaton-evaluate-' + formId);
      var $form = $('#gform_' + formId);

      if ($button.length && $form.length) {
        var $target = $form.find('.gform_body, .gform_footer').first();

        if ($target.length) {
          $button.detach().appendTo($target);
          $button.show();
          console.log('Evaluate button shown for form:', formId);
        }
      }
    },

    /**
     * Set up decision flow functionality
     * Initializes decision flow display and refresh functionality
     *
     * @param {number} formId Form ID
     */
    setupDecisionFlow: function (formId) {
      var self = this;

      // Set up automatic loading on the summary page
      $(document).on('gform_page_loaded', function (event, form_id, current_page) {
        if (form_id == formId) {
          var config = window['operaton_config_' + formId];
          var targetPage = parseInt(config.evaluation_step) || 2;

          // Show decision flow on the page after evaluation
          if (current_page > targetPage) {
            setTimeout(function () {
              self.loadDecisionFlow(formId);
            }, 500);
          }
        }
      });
    },

    /**
     * Load decision flow summary for a form
     * Fetches and displays the decision flow summary
     *
     * @param {number} formId Form ID
     */
    loadDecisionFlow: function (formId) {
      var $container = $('#decision-flow-summary-' + formId);

      if (!$container.length || $container.hasClass('loading')) {
        return;
      }

      console.log('Loading decision flow for form:', formId);

      $container.addClass('loading');
      $container.html('<div class="operaton-loading"><p>⏳ Loading decision flow summary...</p></div>');
      $container.show();

      $.ajax({
        url: window.location.origin + '/wp-json/operaton-dmn/v1/decision-flow/' + formId + '?cache_bust=' + Date.now(),
        type: 'GET',
        cache: false,
        success: function (response) {
          if (response.success && response.html) {
            $container.html(response.html);
          } else {
            $container.html('<div class="operaton-no-data"><p><em>No decision flow data available.</em></p></div>');
          }
        },
        error: function (xhr, status, error) {
          console.error('Decision flow error:', error);
          $container.html('<div class="operaton-error"><p><em>Error loading decision flow summary.</em></p></div>');
        },
        complete: function () {
          $container.removeClass('loading');
        },
      });
    },

    /**
     * Set up form validation enhancements
     * Adds DMN-specific validation logic
     *
     * @param {number} formId Form ID
     * @param {object} config Form configuration
     */
    setupFormValidation: function (formId, config) {
      // Add validation logic here if needed
      // This could include validating required fields before evaluation
    },

    /**
     * Handle evaluate button click
     * Processes form evaluation when the evaluate button is clicked
     *
     * @param {Event} e Click event
     */
    handleEvaluateClick: function (e) {
      e.preventDefault();

      var $button = $(this);
      var formId = $button.data('form-id');
      var configId = $button.data('config-id');

      console.log('Evaluate button clicked for form:', formId);

      // Get form configuration
      var config = window['operaton_config_' + formId];
      if (!config) {
        console.error('No configuration found for form:', formId);
        OperatonGravityForms.showMessage('error', operaton_gravity.strings.form_error);
        return;
      }

      // Validate form before evaluation
      if (!OperatonGravityForms.validateFormForEvaluation(formId)) {
        OperatonGravityForms.showMessage('error', operaton_gravity.strings.validation_failed);
        return;
      }

      // Collect form data
      var formData = OperatonGravityForms.collectFormData(formId, config);

      if (Object.keys(formData).length === 0) {
        OperatonGravityForms.showMessage('error', 'No data to evaluate. Please fill in the form fields.');
        return;
      }

      // FIXED: Use the centralized button manager instead of local button handling
      if (typeof window.operatonButtonManager !== 'undefined') {
        // Let the frontend.js button manager handle the button state
        console.log('Delegating button management to centralized manager');

        // Trigger the main evaluation handler instead of duplicating logic
        if (typeof handleEvaluateClick === 'function') {
          handleEvaluateClick($button);
          return;
        }
      }

      // FALLBACK: Simplified evaluation if button manager not available
      OperatonGravityForms.performEvaluationSimplified(formId, configId, formData, $button);
    },

    /**
     * Validate form data before evaluation
     * Checks if required fields are filled for DMN evaluation
     *
     * @param {number} formId Form ID
     * @return {boolean} True if form is valid for evaluation
     */
    validateFormForEvaluation: function (formId) {
      var $form = $('#gform_' + formId);
      var config = window['operaton_config_' + formId];

      if (!config || !config.field_mappings) {
        return false;
      }

      // Check if required DMN fields have values
      var hasRequiredData = false;

      $.each(config.field_mappings, function (dmnVariable, mapping) {
        var fieldId = mapping.field_id;
        var $field = $form.find('#input_' + formId + '_' + fieldId);

        if ($field.length) {
          var value = OperatonGravityForms.getFieldValue($field, fieldId, formId);
          if (value !== null && value !== '') {
            hasRequiredData = true;
            return false; // Break the loop
          }
        }
      });

      return hasRequiredData;
    },

    /**
     * Collect form data for DMN evaluation
     * Extracts form field values based on DMN field mappings
     *
     * @param {number} formId Form ID
     * @param {object} config Form configuration
     * @return {object} Form data mapped to DMN variables
     */
    collectFormData: function (formId, config) {
      var formData = {};
      var $form = $('#gform_' + formId);

      if (!config.field_mappings) {
        console.warn('No field mappings found for form:', formId);
        return formData;
      }

      // Collect data based on field mappings
      $.each(config.field_mappings, function (dmnVariable, mapping) {
        var fieldId = mapping.field_id;
        var $field = $form.find('#input_' + formId + '_' + fieldId);

        if ($field.length) {
          var value = OperatonGravityForms.getFieldValue($field, fieldId, formId);

          if (value !== null && value !== '') {
            formData[dmnVariable] = value;
            console.log('Collected field data:', dmnVariable, '=', value);
          }
        }
      });

      return formData;
    },

    /**
     * Get field value based on field type
     * Handles different Gravity Forms field types appropriately
     *
     * @param {jQuery} $field Field element
     * @param {string} fieldId Field ID
     * @param {number} formId Form ID
     * @return {*} Field value
     */
    getFieldValue: function ($field, fieldId, formId) {
      var $form = $('#gform_' + formId);
      var value = null;

      // Handle different field types
      if ($field.is(':radio')) {
        value = $form.find('input[name="input_' + fieldId + '"]:checked').val();
      } else if ($field.is(':checkbox')) {
        var values = [];
        $form.find('input[name="input_' + fieldId + '[]"]:checked').each(function () {
          values.push($(this).val());
        });
        value = values.length > 0 ? values : null;
      } else if ($field.is('select')) {
        value = $field.val();
      } else if ($field.is('textarea')) {
        value = $field.val();
      } else {
        // Text, number, email, etc.
        value = $field.val();
      }

      return value;
    },

    /**
     * Perform DMN evaluation via AJAX
     * Sends form data to the evaluation endpoint
     * CRITICAL FIX: Updated performEvaluation method with better error handling
     *
     * @param {number} formId Form ID
     * @param {number} configId Configuration ID
     * @param {object} formData Form data to evaluate
     * @param {jQuery} $button Evaluate button element
     */
    performEvaluationSimplified: function (formId, configId, formData, $button) {
      console.log('Performing simplified evaluation:', {
        formId: formId,
        configId: configId,
        formData: formData,
      });

      // FIXED: Use button manager if available, otherwise basic state management
      var useButtonManager = typeof window.operatonButtonManager !== 'undefined';
      var originalText = 'Evaluate'; // Safe fallback

      if (useButtonManager) {
        window.operatonButtonManager.setEvaluatingState($button, formId);
      } else {
        // Basic fallback button state
        originalText = $button.val();
        $button.prop('disabled', true).val('Evaluating...');
      }

      // CRITICAL FIX: Ensure operaton_ajax is available
      if (typeof window.operaton_ajax === 'undefined') {
        console.error('❌ operaton_ajax not available');
        OperatonGravityForms.handleEvaluationError('System configuration error. Please refresh the page.');

        // Restore button state
        if (useButtonManager) {
          window.operatonButtonManager.restoreOriginalState($button, formId);
        } else {
          $button.prop('disabled', false).val(originalText);
        }
        return;
      }

      $.ajax({
        url: window.operaton_ajax.url,
        type: 'POST',
        dataType: 'json',
        data: JSON.stringify({
          config_id: configId,
          form_data: formData,
        }),
        contentType: 'application/json',
        beforeSend: function (xhr) {
          xhr.setRequestHeader('X-WP-Nonce', window.operaton_ajax.nonce);
        },
        success: function (response) {
          console.log('Evaluation response:', response);

          if (response.success) {
            OperatonGravityForms.handleEvaluationSuccess(formId, response.results, response.debug_info);
          } else {
            OperatonGravityForms.handleEvaluationError(response.message || 'Evaluation failed');
          }
        },
        error: function (xhr, status, error) {
          console.error('Evaluation AJAX error:', error);
          console.error('XHR status:', xhr.status);
          console.error('XHR response:', xhr.responseText);

          var errorMessage = 'Connection error: ' + error;

          // Better error message based on status
          if (xhr.status === 0) {
            errorMessage = 'Connection failed. Please check your internet connection.';
          } else if (xhr.status === 400) {
            errorMessage = 'Bad request. Please check your form data.';
          } else if (xhr.status === 404) {
            errorMessage = 'Evaluation service not found.';
          } else if (xhr.status === 500) {
            errorMessage = 'Server error occurred.';
          }

          OperatonGravityForms.handleEvaluationError(errorMessage);
        },
        complete: function () {
          // FIXED: Use centralized button manager for restoration
          if (useButtonManager) {
            window.operatonButtonManager.restoreOriginalState($button, formId);
          } else {
            // Basic fallback restoration
            $button.prop('disabled', false).val(originalText);
          }
        },
      });
    },

    /**
     * Handle successful evaluation response
     * Updates form fields with evaluation results
     *
     * @param {number} formId Form ID
     * @param {object} results Evaluation results
     * @param {object} debugInfo Debug information
     */
    handleEvaluationSuccess: function (formId, results, debugInfo) {
      console.log('Evaluation successful for form:', formId, results);

      var config = window['operaton_config_' + formId];
      if (!config || !config.result_mappings) {
        console.warn('No result mappings found for form:', formId);
        return;
      }

      var $form = $('#gform_' + formId);
      var updatedFields = 0;

      // Update form fields with results
      $.each(results, function (dmnResult, resultData) {
        var mapping = config.result_mappings[dmnResult];
        if (mapping && mapping.field_id) {
          var $field = $form.find('#input_' + formId + '_' + mapping.field_id);
          if ($field.length) {
            OperatonGravityForms.setFieldValue($field, resultData.value);
            updatedFields++;

            console.log('Updated field:', mapping.field_id, 'with value:', resultData.value);
          }
        }
      });

      // Show success message
      var message =
        updatedFields > 0
          ? operaton_ajax.strings.success + ' (' + updatedFields + ' fields updated)'
          : operaton_ajax.strings.success;

      OperatonGravityForms.showMessage('success', message);

      // Log debug info if available
      if (debugInfo && operaton_ajax.debug) {
        console.log('Debug Info:', debugInfo);
      }

      // Trigger custom event
      $(document).trigger('operaton_evaluation_success', [formId, results]);

      // REMOVED: Button state management - now handled by centralized manager
    },

    /**
     * Handle evaluation error
     * Displays error message to user
     *
     * @param {string} message Error message
     */
    handleEvaluationError: function (message) {
      console.error('Evaluation error:', message);
      OperatonGravityForms.showMessage('error', message);

      // Trigger custom event
      $(document).trigger('operaton_evaluation_error', [message]);
    },

    /**
     * Set field value based on field type
     * Updates form field with evaluation result
     *
     * @param {jQuery} $field Field element
     * @param {*} value Value to set
     */
    setFieldValue: function ($field, value) {
      if ($field.is(':radio')) {
        // For radio buttons, check the matching value
        $field
          .filter('[value="' + value + '"]')
          .prop('checked', true)
          .trigger('change');
      } else if ($field.is(':checkbox')) {
        // For checkboxes, handle array values
        if (Array.isArray(value)) {
          value.forEach(function (val) {
            $field.filter('[value="' + val + '"]').prop('checked', true);
          });
        } else {
          $field.filter('[value="' + value + '"]').prop('checked', true);
        }
        $field.trigger('change');
      } else {
        // For text, select, textarea, etc.
        $field.val(value).trigger('change');
      }
    },

    /**
     * Show message to user
     * Displays success or error messages
     *
     * @param {string} type Message type ('success' or 'error')
     * @param {string} message Message text
     */
    showMessage: function (type, message) {
      // Create message element
      var messageClass =
        type === 'success' ? 'operaton-message operaton-message-success' : 'operaton-message operaton-message-error';

      var icon = type === 'success' ? '✓' : '⚠';
      var $message = $('<div class="' + messageClass + '">' + icon + ' ' + message + '</div>');

      // Find a good place to show the message
      var $target = $('.operaton-evaluate-btn').first().closest('.gform_body, .gform_footer');

      if (!$target.length) {
        $target = $('form[id^="gform_"]').first();
      }

      if ($target.length) {
        // Remove any existing messages
        $target.find('.operaton-message').remove();

        // Add new message
        $target.prepend($message);

        // Auto-hide after 5 seconds
        setTimeout(function () {
          $message.fadeOut(function () {
            $message.remove();
          });
        }, 5000);

        // Scroll to message if needed
        if ($message.offset()) {
          $('html, body').animate(
            {
              scrollTop: $message.offset().top - 100,
            },
            500
          );
        }
      } else {
        // Fallback to alert if no container found
        alert(message);
      }
    },

    /**
     * Handle Gravity Forms page loaded event
     * Responds to page changes in multi-page forms
     *
     * @param {Event} event Page loaded event
     * @param {number} form_id Form ID
     * @param {number} current_page Current page number
     */
    handlePageLoaded: function (event, form_id, current_page) {
      console.log('GF page loaded - Form:', form_id, 'Page:', current_page);

      // Re-initialize form functionality for the new page
      if (window['operaton_config_' + form_id]) {
        OperatonGravityForms.initializeForm(form_id);
      }
    },

    /**
     * Handle form render event
     * Responds to form rendering (including AJAX forms)
     *
     * @param {Event} event Form render event
     * @param {number} form_id Form ID
     */
    handleFormRender: function (event, form_id) {
      console.log('GF form rendered:', form_id);

      // Initialize DMN functionality if configured
      if (window['operaton_config_' + form_id]) {
        setTimeout(function () {
          OperatonGravityForms.initializeForm(form_id);
        }, 100);
      }
    },

    /**
     * Handle decision flow refresh button click
     * Refreshes the decision flow summary
     *
     * @param {Event} e Click event
     */
    handleDecisionFlowRefresh: function (e) {
      e.preventDefault();

      var $button = $(this);
      var formId = $button.data('form-id');

      if (formId) {
        console.log('Refreshing decision flow for form:', formId);

        // Clear cache and reload
        var $container = $('#decision-flow-summary-' + formId);
        $container.removeClass('loading').empty();

        OperatonGravityForms.loadDecisionFlow(formId);
      }
    },

    /**
     * Handle form validation event
     * Responds to Gravity Forms validation
     *
     * @param {Event} event Validation event
     * @param {number} form_id Form ID
     */
    handleFormValidation: function (event, form_id) {
      console.log('GF form validation:', form_id);

      // Add any custom validation logic here
    },

    /**
     * Get debug information for troubleshooting
     * Returns current state information
     *
     * @return {object} Debug information
     */
    getDebugInfo: function () {
      var debugInfo = {
        initialized: true,
        jquery_version: $.fn.jquery,
        forms_detected: [],
        configurations: {},
        timestamp: new Date().toISOString(),
      };

      // Collect information about detected forms
      $('form[id^="gform_"]').each(function () {
        var formId = OperatonGravityForms.extractFormId($(this));
        if (formId) {
          debugInfo.forms_detected.push(formId);

          if (window['operaton_config_' + formId]) {
            debugInfo.configurations[formId] = {
              has_config: true,
              evaluation_step: window['operaton_config_' + formId].evaluation_step,
              use_process: window['operaton_config_' + formId].use_process,
              show_decision_flow: window['operaton_config_' + formId].show_decision_flow,
            };
          }
        }
      });

      return debugInfo;
    },
  };

  // Initialize when document is ready and operaton_ajax is available
  $(document).ready(function () {
    // Start the initialization process with a small delay
    setTimeout(initializeWhenReady, 100);
  });

  // Handle window load for additional initialization
  $(window).on('load', function () {
    // Re-detect forms in case any were loaded dynamically
    setTimeout(function () {
      if (typeof OperatonGravityForms !== 'undefined') {
        OperatonGravityForms.detectForms();
      }
    }, 1000);
  });
})(jQuery);
