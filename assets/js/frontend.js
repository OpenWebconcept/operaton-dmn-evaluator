/**
 * FIXED: Enhanced Operaton DMN Frontend Script
 *
 * Simplified jQuery loading and improved initialization timing
 * All global functions defined immediately for inline script compatibility
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

console.log('Operaton DMN frontend script loading (enhanced with decision flow)...');

// CRITICAL: Define global functions FIRST, before jQuery wrapper
// These must be available immediately for inline HTML scripts
window.showEvaluateButton = function (formId) {
  var $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for showEvaluateButton');
    return;
  }

  var $button = $('#operaton-evaluate-' + formId);
  var $summary = $('#decision-flow-summary-' + formId);
  console.log('✅ Showing evaluate button for form', formId);
  $button.addClass('operaton-show-button').show(); // ADD .show()
  $summary.removeClass('operaton-show-summary');
};

window.showDecisionFlowSummary = function (formId) {
  var $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for showDecisionFlowSummary');
    return;
  }

  var $button = $('#operaton-evaluate-' + formId);
  var $summary = $('#decision-flow-summary-' + formId);
  console.log('📊 Showing decision flow summary for form', formId);
  $button.removeClass('operaton-show-button');
  $summary.addClass('operaton-show-summary');
};

window.hideAllElements = function (formId) {
  var $ = window.jQuery || window.$;
  if (!$) {
    console.warn('jQuery not available for hideAllElements');
    return;
  }

  // CHECK: Don't hide if we're on the target page
  var currentPage = getCurrentPageSimple(formId);
  var config = window['operaton_config_' + formId];
  var targetPage = config ? parseInt(config.evaluation_step) || 2 : 2;

  if (currentPage === targetPage) {
    console.log('❌ Skipping hide - we are on target page', currentPage);
    return; // Don't hide on target page
  }

  var $button = $('#operaton-evaluate-' + formId);
  var $summary = $('#decision-flow-summary-' + formId);
  console.log('❌ Hiding all elements for form', formId);
  $button.removeClass('operaton-show-button').hide(); // ADD .hide()
  $summary.removeClass('operaton-show-summary');
};

// Helper function for page detection
function getCurrentPageSimple(formId) {
  var urlParams = new URLSearchParams(window.location.search);
  var pageParam = urlParams.get('gf_page');
  if (pageParam) {
    return parseInt(pageParam);
  }

  var $ = window.jQuery || window.$;
  if ($) {
    var $form = $('#gform_' + formId);
    var $pageField = $form.find('input[name="gform_source_page_number_' + formId + '"]');
    if ($pageField.length > 0) {
      return parseInt($pageField.val()) || 1;
    }
  }

  return 1;
}

// Global button manager - available immediately
window.operatonButtonManager = window.operatonButtonManager || {
  originalTexts: new Map(),

  /**
   * Store original button text safely
   */
  storeOriginalText: function ($button, formId) {
    var buttonId = 'form_' + formId;

    // Get original text from multiple sources
    var originalText = $button.attr('data-original-text') || $button.val() || $button.attr('value') || 'Evaluate';

    // Clean up the text (remove evaluation states)
    if (
      originalText.indexOf('Evaluation') !== -1 ||
      originalText.indexOf('Evaluating') !== -1 ||
      originalText.indexOf('progress') !== -1
    ) {
      originalText = 'Evaluate'; // Fallback to default
    }

    this.originalTexts.set(buttonId, originalText);
    $button.attr('data-original-text', originalText);

    console.log('📝 ButtonManager: Stored original text for form', formId + ':', originalText);
    return originalText;
  },

  /**
   * Get stored original text
   */
  getOriginalText: function (formId) {
    var buttonId = 'form_' + formId;
    var storedText = this.originalTexts.get(buttonId);

    if (!storedText) {
      // Try to get from button element
      var $ = window.jQuery || window.$;
      if ($) {
        var $button = $('#operaton-evaluate-' + formId);
        if ($button.length) {
          storedText = $button.attr('data-original-text') || 'Evaluate';
          this.originalTexts.set(buttonId, storedText);
        } else {
          storedText = 'Evaluate'; // Ultimate fallback
        }
      } else {
        storedText = 'Evaluate';
      }
    }

    console.log('🔍 ButtonManager: Retrieved original text for form', formId + ':', storedText);
    return storedText;
  },

  /**
   * Set button to evaluating state
   */
  setEvaluatingState: function ($button, formId) {
    // Ensure original text is stored first
    this.storeOriginalText($button, formId);

    $button.val('Evaluating...').prop('disabled', true).addClass('operaton-evaluating');

    console.log('🔄 ButtonManager: Set evaluating state for form', formId);
  },

  /**
   * Restore button to original state with multiple fallback attempts
   */
  restoreOriginalState: function ($button, formId) {
    var originalText = this.getOriginalText(formId);

    console.log('🔄 ButtonManager: Restoring button for form', formId, 'to:', originalText);

    // Primary restoration
    $button
      .val(originalText)
      .prop('value', originalText)
      .attr('value', originalText)
      .prop('disabled', false)
      .removeClass('operaton-evaluating');

    // Failsafe restoration after a short delay
    setTimeout(function () {
      $button.val(originalText).prop('disabled', false);
      console.log('✅ ButtonManager: Failsafe restoration completed for form', formId);
    }, 100);

    // Final failsafe after longer delay
    setTimeout(function () {
      if ($button.val() !== originalText || $button.prop('disabled')) {
        $button.val(originalText).prop('disabled', false);
        console.log('🆘 ButtonManager: Emergency restoration triggered for form', formId);
      }
    }, 1000);

    console.log('✅ ButtonManager: Restoration complete for form', formId);
  },
};

// Global registry to prevent duplicate initialization
window.operatonInitialized = window.operatonInitialized || {
  forms: new Set(),
  scripts: new Set(),
  globalInit: false,
  timers: {},
  jQueryReady: false,
};

// SIMPLIFIED: Main initialization function that assumes jQuery is available
(function ($) {
  'use strict';

  // Simple jQuery availability check
  if (typeof $ === 'undefined') {
    console.error('❌ Operaton DMN CRITICAL: jQuery not available despite dependency declaration');

    // Try window.jQuery as fallback
    if (typeof window.jQuery !== 'undefined') {
      $ = window.jQuery;
      console.log('✅ Operaton DMN: Using window.jQuery as fallback');
    } else {
      console.error('❌ Operaton DMN FATAL: No jQuery available, plugin will not work');
      return;
    }
  } else {
    console.log('✅ Operaton DMN: jQuery available immediately, version:', $.fn.jquery);
  }

  // =============================================================================
  // CORE INITIALIZATION FUNCTIONS
  // =============================================================================

  /**
   * Enhanced form initialization with strict duplicate prevention
   */
  function initializeFormEvaluation(formId) {
    formId = parseInt(formId);

    // Check if already initialized
    if (window.operatonInitialized.forms.has(formId)) {
      console.log('Form', formId, 'already initialized, skipping duplicate');
      return;
    }

    var configVar = 'operaton_config_' + formId;
    if (typeof window[configVar] === 'undefined') {
      console.log('No configuration found for form:', formId);
      return;
    }

    console.log('=== INITIALIZING FORM', formId, '===');

    // Mark as initializing immediately
    window.operatonInitialized.forms.add(formId);

    var config = window[configVar];
    console.log('Enhanced configuration found for form:', formId);

    try {
      bindEvaluationEvents(formId);
      bindNavigationEvents(formId);
      bindInputChangeListeners(formId);

      // Initialize decision flow summary if enabled
      if (config.show_decision_flow) {
        initializeDecisionFlowSummary(formId);
      }

      // Clear any existing results when form initializes
      setTimeout(function () {
        clearResultFieldWithMessage(formId, 'Form initialized');
      }, 200);

      console.log('=== FORM', formId, 'INITIALIZATION COMPLETE ===');
    } catch (error) {
      console.error('Error initializing form', formId, ':', error);
      // Remove from initialized set if initialization failed
      window.operatonInitialized.forms.delete(formId);
    }
  }

  /**
   * Better waiting mechanism for operaton_ajax with timeout
   */
  function waitForOperatonAjax(callback, maxAttempts = 50) {
    var attempts = 0;

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
        console.error(
          '❌ Available window objects:',
          Object.keys(window).filter(k => k.includes('operaton'))
        );

        // Create emergency fallback
        createEmergencyOperatonAjax();
        callback();
      }
    }
    check();
  }

  /**
   * Create emergency operaton_ajax fallback
   */
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

  /**
   * Initialize evaluation system
   */
  function initOperatonDMN() {
    console.log('Starting Operaton DMN initialization...');

    // Hook into Gravity Forms events if available
    if (typeof gform !== 'undefined' && gform.addAction) {
      // Remove any existing actions first
      if (gform.removeAction) {
        gform.removeAction('gform_post_render', 'operaton_form_render');
      }

      gform.addAction(
        'gform_post_render',
        function (formId) {
          console.log('Gravity Form rendered via gform action, form:', formId);
          setTimeout(function () {
            debouncedFormInitialization(formId);
          }, 100);
        },
        10,
        'operaton_form_render'
      );

      console.log('Hooked into gform_post_render action');
    }

    // Direct form detection for immediate initialization
    setTimeout(function () {
      console.log('Running direct form detection...');
      $('form[id^="gform_"]').each(function () {
        var $form = $(this);
        var formId = $form.attr('id').replace('gform_', '');

        if (formId && !window.operatonInitialized.forms.has(parseInt(formId))) {
          console.log('Direct detection: Found form', formId);
          debouncedFormInitialization(parseInt(formId));
        }
      });
    }, 500);
  }

  /**
   * Debounced form initialization to prevent rapid duplicate calls
   */
  function debouncedFormInitialization(formId) {
    formId = parseInt(formId);

    // Clear any existing timer for this form
    if (window.operatonInitialized.timers[formId]) {
      clearTimeout(window.operatonInitialized.timers[formId]);
    }

    window.operatonInitialized.timers[formId] = setTimeout(function () {
      initializeFormEvaluation(formId);
      delete window.operatonInitialized.timers[formId];
    }, 300);
  }

  // =============================================================================
  // EVENT BINDING FUNCTIONS
  // =============================================================================

  /**
   * Bind evaluation button events
   */
  function bindEvaluationEvents(formId) {
    var selector = '.operaton-evaluate-btn[data-form-id="' + formId + '"]';
    console.log('Binding events for selector:', selector);

    $(document).off('click.operaton-' + formId, selector);
    $(document).on('click.operaton-' + formId, selector, function (e) {
      e.preventDefault();
      console.log('Button clicked for form:', formId);
      handleEvaluateClick($(this));
    });

    console.log('Event handler bound for form:', formId);
  }

  /**
   * Bind navigation events
   */
  function bindNavigationEvents(formId) {
    var $form = $('#gform_' + formId);

    $form.off('click.operaton-nav-' + formId);
    $form.on(
      'click.operaton-nav-' + formId,
      '.gform_previous_button, input[value="Previous"], button:contains("Previous")',
      function () {
        console.log('Previous button clicked for form:', formId);
        clearResultFieldWithMessage(formId, 'Previous button clicked');
      }
    );

    // Gravity Forms page loaded event
    if (typeof gform !== 'undefined' && gform.addAction) {
      if (gform.removeAction) {
        gform.removeAction('gform_page_loaded', 'operaton_clear_' + formId);
      }

      gform.addAction(
        'gform_page_loaded',
        function (loadedFormId, currentPage) {
          if (loadedFormId == formId) {
            console.log('Form page loaded for form:', formId, 'page:', currentPage);
            setTimeout(function () {
              clearResultFieldWithMessage(formId, 'Page loaded: ' + currentPage);
            }, 300);
          }
        },
        10,
        'operaton_clear_' + formId
      );
    }
  }

  /**
   * Bind input change listeners
   */
  function bindInputChangeListeners(formId) {
    var $form = $('#gform_' + formId);
    var configVar = 'operaton_config_' + formId;
    var config = window[configVar];

    if (!config || !config.field_mappings) return;

    console.log('Binding input change listeners for form:', formId);

    $.each(config.field_mappings, function (dmnVariable, mapping) {
      var fieldId = mapping.field_id;
      var selectors = [
        '#input_' + formId + '_' + fieldId,
        'input[name="input_' + formId + '_' + fieldId + '"]',
        'select[name="input_' + formId + '_' + fieldId + '"]',
        'input[name="input_' + fieldId + '"]',
      ];

      $.each(selectors, function (index, selector) {
        $form.off('change.operaton-' + formId, selector);
        $form.on('change.operaton-' + formId, selector, function () {
          console.log('Input field changed:', selector, 'New value:', $(this).val());

          setTimeout(function () {
            clearResultFieldWithMessage(formId, 'Input changed - result cleared');
          }, 100);
        });
      });
    });
  }

  // =============================================================================
  // DECISION FLOW FUNCTIONS
  // =============================================================================

  /**
   * Initialize decision flow summary functionality
   */
  function initializeDecisionFlowSummary(formId) {
    console.log('Initializing decision flow summary for form:', formId);

    var currentPage = getCurrentPage(formId);
    var totalPages = getTotalPages(formId);

    if (currentPage === totalPages) {
      loadDecisionFlowSummary(formId);
      bindDecisionFlowRefresh(formId);
    }
  }

  /**
   * Load and display decision flow summary
   */
  function loadDecisionFlowSummary(formId) {
    var $summaryContainer = $('#decision-flow-summary-' + formId);

    if ($summaryContainer.length === 0) {
      console.log('No decision flow summary container found for form:', formId);
      return;
    }

    var processInstanceId = getStoredProcessInstanceId(formId);

    if (!processInstanceId) {
      $summaryContainer.html(
        '<div class="decision-flow-placeholder">' +
          '<h3>🔍 Decision Flow Results</h3>' +
          '<p><em>Complete the evaluation on the previous step to see the detailed decision flow summary here.</em></p>' +
          '</div>'
      );
      return;
    }

    $summaryContainer.html(
      '<div class="decision-flow-loading">' +
        '<h3>🔍 Decision Flow Results</h3>' +
        '<p>⏳ Loading decision flow summary...</p>' +
        '</div>'
    );

    $.ajax({
      url: window.operaton_ajax.url.replace('/evaluate', '/decision-flow/' + formId),
      type: 'GET',
      headers: {
        'X-WP-Nonce': window.operaton_ajax.nonce,
      },
      success: function (response) {
        if (response.success && response.html) {
          $summaryContainer.html(response.html);

          $summaryContainer.append(
            '<div style="margin-top: 15px;">' +
              '<button type="button" class="button refresh-decision-flow" data-form-id="' +
              formId +
              '">' +
              '🔄 Refresh Decision Flow' +
              '</button>' +
              '</div>'
          );

          $('html, body').animate(
            {
              scrollTop: $summaryContainer.offset().top - 100,
            },
            500
          );
        } else {
          $summaryContainer.html(
            '<div class="decision-flow-error">' +
              '<h3>🔍 Decision Flow Results</h3>' +
              '<p><em>Could not load decision flow summary.</em></p>' +
              '</div>'
          );
        }
      },
      error: function (xhr, status, error) {
        console.error('Decision flow error:', error);
        $summaryContainer.html(
          '<div class="decision-flow-error">' +
            '<h3>🔍 Decision Flow Results</h3>' +
            '<p><em>Error loading decision flow: ' +
            error +
            '</em></p>' +
            '</div>'
        );
      },
    });
  }

  /**
   * Bind decision flow refresh functionality
   */
  function bindDecisionFlowRefresh(formId) {
    $(document).off('click.decision-flow-' + formId, '.refresh-decision-flow[data-form-id="' + formId + '"]');
    $(document).on(
      'click.decision-flow-' + formId,
      '.refresh-decision-flow[data-form-id="' + formId + '"]',
      function (e) {
        e.preventDefault();
        console.log('Refreshing decision flow for form:', formId);

        var $button = $(this);
        var originalText = $button.text();
        $button.text('🔄 Refreshing...').prop('disabled', true);

        setTimeout(function () {
          loadDecisionFlowSummary(formId);
          $button.text(originalText).prop('disabled', false);
        }, 500);
      }
    );
  }

  // =============================================================================
  // FORM EVALUATION FUNCTIONS
  // =============================================================================

  /**
   * Handle evaluate button click with self-contained button text management
   */
  function handleEvaluateClick($button) {
    var formId = $button.data('form-id');
    var configId = $button.data('config-id');

    console.log('Enhanced evaluate button clicked for form:', formId, 'config:', configId);

    var configVar = 'operaton_config_' + formId;
    if (typeof window[configVar] === 'undefined') {
      console.error('Configuration not found for form:', formId);
      showError('Configuration error. Please contact the administrator.');
      return;
    }

    var config = window[configVar];
    var fieldMappings = config.field_mappings;

    // Use centralized button manager
    window.operatonButtonManager.storeOriginalText($button, formId);

    // Force radio button synchronization before validation
    forceSyncRadioButtons(formId);

    setTimeout(function () {
      continueEvaluation();
    }, 100);

    function continueEvaluation() {
      if (!validateForm(formId)) {
        showError('Please fill in all required fields before evaluation.');
        return;
      }

      // Collect form data
      var formData = {};
      var hasRequiredData = true;
      var missingFields = [];

      $.each(fieldMappings, function (dmnVariable, mapping) {
        var fieldId = mapping.field_id;
        console.log('Processing variable:', dmnVariable, 'Field ID:', fieldId);

        var value = getGravityFieldValue(formId, fieldId);
        console.log('Found raw value for field', fieldId + ':', value);

        // Handle date field conversions
        if (
          dmnVariable.toLowerCase().indexOf('datum') !== -1 ||
          dmnVariable.toLowerCase().indexOf('date') !== -1 ||
          dmnVariable === 'dagVanAanvraag' ||
          dmnVariable === 'geboortedatumAanvrager' ||
          dmnVariable === 'geboortedatumPartner'
        ) {
          if (value !== null && value !== '' && value !== undefined) {
            value = convertDateFormat(value, dmnVariable);
          }
        }

        console.log('Processed value for', dmnVariable + ':', value);
        formData[dmnVariable] = value;
      });

      // Apply conditional logic for partner-related fields
      var isAlleenstaand = formData['aanvragerAlleenstaand'];
      console.log('User is single (alleenstaand):', isAlleenstaand);

      if (isAlleenstaand === 'true' || isAlleenstaand === true) {
        console.log('User is single, setting geboortedatumPartner to null');
        formData['geboortedatumPartner'] = null;
      }

      // Validate required fields
      $.each(fieldMappings, function (dmnVariable, mapping) {
        var value = formData[dmnVariable];

        // Skip validation for partner fields when user is single
        if (isAlleenstaand === 'true' || isAlleenstaand === true) {
          if (dmnVariable === 'geboortedatumPartner') {
            return true;
          }
        }

        if (value === null || value === '' || value === undefined) {
          hasRequiredData = false;
          missingFields.push(dmnVariable + ' (field ID: ' + mapping.field_id + ')');
        } else {
          if (!validateFieldType(value, mapping.type)) {
            showError('Invalid data type for field ' + dmnVariable + '. Expected: ' + mapping.type);
            return false;
          }
        }
      });

      if (!hasRequiredData) {
        showError('Please fill in all required fields: ' + missingFields.join(', '));
        return;
      }

      // Use centralized button manager for evaluating state
      window.operatonButtonManager.setEvaluatingState($button, formId);

      // Check if operaton_ajax is available
      if (typeof window.operaton_ajax === 'undefined') {
        console.error('❌ operaton_ajax not available');
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

            var populatedCount = 0;
            var resultSummary = [];

            $.each(response.results, function (dmnResultField, resultData) {
              var resultValue = resultData.value;
              var fieldId = resultData.field_id;

              console.log('Processing result:', dmnResultField, 'Value:', resultValue, 'Field ID:', fieldId);

              var $resultField = null;

              if (fieldId) {
                $resultField = findFieldOnCurrentPage(formId, fieldId);
              } else {
                $resultField = findResultFieldOnCurrentPage(formId);
              }

              if ($resultField && $resultField.length > 0) {
                $resultField.val(resultValue);
                $resultField.trigger('change');
                $resultField.trigger('input');

                populatedCount++;
                resultSummary.push(dmnResultField + ': ' + resultValue);

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
              var message = '✅ Results populated (' + populatedCount + '): ' + resultSummary.join(', ');

              if (response.process_instance_id && config.show_decision_flow) {
                message += '\n\n📋 Complete the form to see the detailed decision flow summary on the final page.';
              }

              showSuccessNotification(message);
            } else {
              showError('No result fields found on this page to populate.');
            }

            // Store evaluation metadata
            var currentPage = getCurrentPage(formId);
            var evalData = {
              results: response.results,
              page: currentPage,
              timestamp: Date.now(),
              formData: formData,
              processInstanceId: response.process_instance_id || null,
            };

            if (typeof Storage !== 'undefined') {
              sessionStorage.setItem('operaton_dmn_eval_data_' + formId, JSON.stringify(evalData));
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

          var errorMessage = 'Error during evaluation. Please try again.';

          if (xhr.status === 0) {
            errorMessage = 'Connection error. Please check your internet connection and try again.';
          } else if (xhr.status === 400) {
            try {
              var errorResponse = JSON.parse(xhr.responseText);
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
  // UTILITY FUNCTIONS
  // =============================================================================

  /**
   * Store process instance ID for decision flow retrieval
   */
  function storeProcessInstanceId(formId, processInstanceId) {
    if (typeof Storage !== 'undefined') {
      sessionStorage.setItem('operaton_process_' + formId, processInstanceId);
    }
    window['operaton_process_' + formId] = processInstanceId;
    console.log('Stored process instance ID for form', formId + ':', processInstanceId);
  }

  /**
   * Get stored process instance ID
   */
  function getStoredProcessInstanceId(formId) {
    if (window['operaton_process_' + formId]) {
      return window['operaton_process_' + formId];
    }

    if (typeof Storage !== 'undefined') {
      var processId = sessionStorage.getItem('operaton_process_' + formId);
      if (processId) {
        return processId;
      }

      var evalData = sessionStorage.getItem('operaton_dmn_eval_data_' + formId);
      if (evalData) {
        try {
          var parsed = JSON.parse(evalData);
          if (parsed.processInstanceId) {
            return parsed.processInstanceId;
          }
        } catch (e) {
          console.error('Error parsing evaluation data:', e);
        }
      }
    }

    return null;
  }

  /**
   * Clear result field with message
   */
  function clearResultFieldWithMessage(formId, reason) {
    console.log('Clearing result field for form:', formId, 'Reason:', reason);

    var $resultField = findResultFieldOnCurrentPage(formId);
    if ($resultField && $resultField.length > 0) {
      var currentValue = $resultField.val();
      if (currentValue && currentValue.trim() !== '') {
        console.log('Clearing result field value:', currentValue);
        $resultField.val('');
        $resultField.trigger('change');
        $resultField.trigger('input');
      }
    }

    clearStoredResults(formId);
  }

  /**
   * Clear stored results
   */
  function clearStoredResults(formId) {
    if (typeof Storage !== 'undefined') {
      sessionStorage.removeItem('operaton_dmn_result_' + formId);
      sessionStorage.removeItem('operaton_dmn_eval_page_' + formId);
      sessionStorage.removeItem('operaton_dmn_data_' + formId);
      sessionStorage.removeItem('operaton_dmn_eval_data_' + formId);
      sessionStorage.removeItem('operaton_process_' + formId);
    }

    delete window['operaton_process_' + formId];
    console.log('Cleared all stored results and process data for form:', formId);
  }

  /**
   * Get current page
   */
  function getCurrentPage(formId) {
    var urlParams = new URLSearchParams(window.location.search);
    var pageParam = urlParams.get('gf_page');
    if (pageParam) {
      return parseInt(pageParam);
    }

    var $form = $('#gform_' + formId);
    var $pageField = $form.find('input[name="gform_source_page_number_' + formId + '"]');
    if ($pageField.length > 0) {
      return parseInt($pageField.val()) || 1;
    }

    return 1;
  }

  /**
   * Get total pages
   */
  function getTotalPages(formId) {
    var $form = $('#gform_' + formId);
    var totalPages = 1;

    $form.find('.gfield').each(function () {
      if ($(this).hasClass('gfield_page')) {
        totalPages++;
      }
    });

    return totalPages;
  }

  /**
   * Convert date format
   */
  function convertDateFormat(dateStr, fieldName) {
    if (!dateStr || dateStr === null) {
      return null;
    }

    console.log('Converting date for field:', fieldName, 'Input:', dateStr);

    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
      return dateStr;
    }

    if (/^\d{2}-\d{2}-\d{4}$/.test(dateStr)) {
      var parts = dateStr.split('-');
      return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
      var parts = dateStr.split('/');
      return parts[2] + '-' + parts[0] + '-' + parts[1];
    }

    try {
      var date = new Date(dateStr);
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

  /**
   * Find field on current page
   */
  function findFieldOnCurrentPage(formId, fieldId) {
    var $form = $('#gform_' + formId);

    var selectors = [
      '#input_' + formId + '_' + fieldId,
      'input[name="input_' + formId + '_' + fieldId + '"]',
      'select[name="input_' + formId + '_' + fieldId + '"]',
      'textarea[name="input_' + formId + '_' + fieldId + '"]',
    ];

    for (var i = 0; i < selectors.length; i++) {
      var $field = $form.find(selectors[i] + ':visible');
      if ($field.length > 0) {
        return $field.first();
      }
    }

    return null;
  }

  /**
   * Find result field on current page
   */
  function findResultFieldOnCurrentPage(formId) {
    var $form = $('#gform_' + formId);
    var configVar = 'operaton_config_' + formId;
    var config = window[configVar];

    if (config && config.result_display_field) {
      var selectors = [
        '#input_' + formId + '_' + config.result_display_field,
        'input[name="input_' + formId + '_' + config.result_display_field + '"]',
        'select[name="input_' + formId + '_' + config.result_display_field + '"]',
        'textarea[name="input_' + formId + '_' + config.result_display_field + '"]',
      ];

      for (var i = 0; i < selectors.length; i++) {
        var $field = $form.find(selectors[i] + ':visible');
        if ($field.length > 0) {
          return $field.first();
        }
      }
    }

    // Fallback detection strategies
    var detectionStrategies = [
      function () {
        return $form
          .find('label:visible')
          .filter(function () {
            var text = $(this).text().toLowerCase().trim();
            return text === 'desired dish' || text === 'result' || text === 'desireddish';
          })
          .closest('.gfield')
          .find('input:visible, select:visible, textarea:visible')
          .first();
      },

      function () {
        return $form
          .find('label:visible')
          .filter(function () {
            var text = $(this).text().toLowerCase();
            return (text.indexOf('desired') !== -1 && text.indexOf('dish') !== -1) || text.indexOf('result') !== -1;
          })
          .closest('.gfield')
          .find('input:visible, select:visible, textarea:visible')
          .first();
      },

      function () {
        return $form
          .find(
            'input:visible[name*="dish"], input:visible[id*="dish"], select:visible[name*="dish"], select:visible[id*="dish"], textarea:visible[name*="dish"], textarea:visible[id*="dish"]'
          )
          .first();
      },

      function () {
        return $form
          .find(
            'input:visible[name*="result"], input:visible[id*="result"], select:visible[name*="result"], select:visible[id*="result"], textarea:visible[name*="result"], textarea:visible[id*="result"]'
          )
          .first();
      },
    ];

    for (var i = 0; i < detectionStrategies.length; i++) {
      var $field = detectionStrategies[i]();
      if ($field && $field.length > 0) {
        return $field;
      }
    }

    return null;
  }

  /**
   * Get Gravity field value
   */
  function getGravityFieldValue(formId, fieldId) {
    var $form = $('#gform_' + formId);
    var value = null;

    var standardSelectors = [
      '#input_' + formId + '_' + fieldId,
      'input[name="input_' + formId + '_' + fieldId + '"]',
      'select[name="input_' + formId + '_' + fieldId + '"]',
      'textarea[name="input_' + formId + '_' + fieldId + '"]',
    ];

    for (var i = 0; i < standardSelectors.length; i++) {
      var $field = $form.find(standardSelectors[i]);
      if ($field.length > 0) {
        value = getFieldValue($field);
        if (value !== null && value !== '') {
          return value;
        }
      }
    }

    // Check for custom radio values
    value = findCustomRadioValue(formId, fieldId);
    if (value !== null) {
      return value;
    }

    // Standard radio button check
    var $radioChecked = $form.find('input[name="input_' + fieldId + '"]:checked');
    if ($radioChecked.length > 0) {
      return $radioChecked.val();
    }

    // Checkbox check
    var $checkboxChecked = $form.find('input[name^="input_' + fieldId + '"]:checked');
    if ($checkboxChecked.length > 0) {
      var checkboxValues = [];
      $checkboxChecked.each(function () {
        checkboxValues.push($(this).val());
      });
      return checkboxValues.length === 1 ? checkboxValues[0] : checkboxValues.join(',');
    }

    return null;
  }

  /**
   * Find custom radio value
   */
  function findCustomRadioValue(formId, fieldId) {
    var $form = $('#gform_' + formId);

    var $hiddenField = $form.find('#input_' + formId + '_' + fieldId);
    if ($hiddenField.length > 0) {
      var $fieldContainer = $hiddenField.closest('.gfield');
      if ($fieldContainer.length > 0) {
        var fieldLabel = $fieldContainer.find('label').first().text().toLowerCase();
        var possibleRadioNames = generatePossibleRadioNames(fieldLabel, fieldId);

        for (var i = 0; i < possibleRadioNames.length; i++) {
          var radioName = possibleRadioNames[i];
          var $radioChecked = $('input[name="' + radioName + '"]:checked');
          if ($radioChecked.length > 0) {
            var value = $radioChecked.val();

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
    var configVar = 'operaton_config_' + formId;
    if (typeof window[configVar] !== 'undefined') {
      var config = window[configVar];
      if (config.field_mappings) {
        var targetDmnVariable = null;
        $.each(config.field_mappings, function (dmnVariable, mapping) {
          if (mapping.field_id == fieldId) {
            targetDmnVariable = dmnVariable;
            return false;
          }
        });

        if (targetDmnVariable) {
          var $radioChecked = $('input[type="radio"][name="' + targetDmnVariable + '"]:checked');
          if ($radioChecked.length > 0) {
            var value = $radioChecked.val();

            var $hiddenField = $form.find('#input_' + formId + '_' + fieldId);
            if ($hiddenField.length > 0 && $hiddenField.val() !== value) {
              $hiddenField.val(value);
              $hiddenField.trigger('change');
            }

            return value;
          }
        }
      }
    }

    return null;
  }

  /**
   * Generate possible radio names
   */
  function generatePossibleRadioNames(fieldLabel, fieldId) {
    var possibilities = [];

    if (fieldLabel) {
      var cleanLabel = fieldLabel
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, '')
        .replace(/\s+/g, '')
        .trim();

      if (cleanLabel) {
        possibilities.push(cleanLabel);
        possibilities.push('aanvrager' + cleanLabel.charAt(0).toUpperCase() + cleanLabel.slice(1));
      }
    }

    possibilities.push('field_' + fieldId);
    possibilities.push('input_' + fieldId);

    return possibilities;
  }

  /**
   * Force sync radio buttons
   */
  function forceSyncRadioButtons(formId) {
    var $form = $('#gform_' + formId);
    var configVar = 'operaton_config_' + formId;

    if (typeof window[configVar] === 'undefined') {
      return;
    }

    var config = window[configVar];
    if (!config.field_mappings) {
      return;
    }

    $.each(config.field_mappings, function (dmnVariable, mapping) {
      var fieldId = mapping.field_id;
      var $hiddenField = $form.find('#input_' + formId + '_' + fieldId);

      if ($hiddenField.length > 0) {
        var $radioChecked = $('input[name="' + dmnVariable + '"]:checked');
        if ($radioChecked.length > 0) {
          var radioValue = $radioChecked.val();
          var hiddenValue = $hiddenField.val();

          if (radioValue !== hiddenValue) {
            $hiddenField.val(radioValue);
            $hiddenField.trigger('change');
          }
        }
      }
    });

    // Sync any other custom radio buttons
    $form.find('input[type="radio"]:checked').each(function () {
      var $radio = $(this);
      var radioName = $radio.attr('name');
      var radioValue = $radio.val();

      if (radioName && radioName.indexOf('input_') !== 0) {
        var correspondingFieldId = findFieldIdForRadioName(formId, radioName);
        if (correspondingFieldId) {
          var $hiddenField = $form.find('#input_' + formId + '_' + correspondingFieldId);
          if ($hiddenField.length > 0 && $hiddenField.val() !== radioValue) {
            $hiddenField.val(radioValue);
            $hiddenField.trigger('change');
          }
        }
      }
    });
  }

  /**
   * Find field ID for radio name
   */
  function findFieldIdForRadioName(formId, radioName) {
    var configVar = 'operaton_config_' + formId;
    if (typeof window[configVar] !== 'undefined') {
      var config = window[configVar];
      if (config.field_mappings && config.field_mappings[radioName]) {
        return config.field_mappings[radioName].field_id;
      }
    }
    return null;
  }

  /**
   * Get field value
   */
  function getFieldValue($field) {
    if ($field.length === 0) return null;

    var tagName = $field.prop('tagName').toLowerCase();
    var fieldType = $field.attr('type');

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
      var val = $field.val();
      return val && val.trim() !== '' ? val : null;
    }

    return $field.val();
  }

  /**
   * Validate field type
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

  /**
   * Validate form
   */
  function validateForm(formId) {
    if (typeof gform !== 'undefined' && gform.validators && gform.validators[formId]) {
      return gform.validators[formId]();
    }

    var $form = $('#gform_' + formId);
    var allValid = true;

    $form
      .find('.gfield_contains_required input, .gfield_contains_required select, .gfield_contains_required textarea')
      .each(function () {
        var $field = $(this);
        var value = getFieldValue($field);

        if (!value || value.trim() === '') {
          allValid = false;
          return false;
        }
      });

    return allValid;
  }

  /**
   * Show success notification
   */
  function showSuccessNotification(message) {
    $('.operaton-notification').remove();

    var $notification = $('<div class="operaton-notification">' + message + '</div>');
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

    setTimeout(function () {
      $notification.fadeOut(300, function () {
        $(this).remove();
      });
    }, 6000);
  }

  /**
   * Show error
   */
  function showError(message) {
    $('.operaton-notification').remove();

    var $notification = $('<div class="operaton-notification">❌ ' + message + '</div>');
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

    setTimeout(function () {
      $notification.fadeOut(300, function () {
        $(this).remove();
      });
    }, 8000);
  }

  /**
   * Highlight field
   */
  function highlightField($field) {
    if ($field && $field.length > 0) {
      var originalBackground = $field.css('background-color');
      var originalBorder = $field.css('border');

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

      setTimeout(function () {
        $field.css({
          'background-color': originalBackground,
          border: originalBorder,
        });
      }, 3000);
    }
  }

  // =============================================================================
  // MAIN INITIALIZATION SEQUENCE
  // =============================================================================

  // Wait for operaton_ajax and initialize
  waitForOperatonAjax(function () {
    console.log('🚀 Initializing Enhanced Operaton DMN...');

    // Set global initialization flag
    window.operatonInitialized.globalInit = true;

    // Initialize the main system
    initOperatonDMN();

    // Bind events for existing forms
    $('form[id^="gform_"]').each(function () {
      var formId = $(this).attr('id').replace('gform_', '');
      if (formId && !window.operatonInitialized.forms.has(parseInt(formId))) {
        debouncedFormInitialization(parseInt(formId));
      }
    });

    console.log('🎉 Enhanced Operaton DMN frontend script initialization complete');
  });

  // Cleanup on page unload
  $(window).on('beforeunload', function () {
    console.log('Cleaning up Operaton DMN initialization state...');

    // Clear form initialization state
    window.operatonInitialized.forms.clear();
    window.operatonInitialized.globalInit = false;

    // Clear any pending timers
    Object.keys(window.operatonInitialized.timers).forEach(function (formId) {
      clearTimeout(window.operatonInitialized.timers[formId]);
    });
    window.operatonInitialized.timers = {};
  });
})(jQuery);

// =============================================================================
// DOCUMENT READY AND INITIALIZATION LOGIC
// =============================================================================

// Multiple initialization strategies to handle different loading scenarios

// Strategy 1: Check if jQuery is immediately available
if (typeof jQuery !== 'undefined') {
  console.log('✅ Operaton DMN: jQuery available immediately');
  jQuery(document).ready(function () {
    // Main initialization already handled above
  });
} else {
  console.log('⏳ Operaton DMN: jQuery not immediately available, waiting...');

  // Strategy 2: Simple polling for jQuery
  var jqueryCheckAttempts = 0;
  var jqueryCheckInterval = setInterval(function () {
    jqueryCheckAttempts++;

    if (typeof jQuery !== 'undefined') {
      console.log('✅ Operaton DMN: jQuery found after', jqueryCheckAttempts, 'attempts');
      clearInterval(jqueryCheckInterval);

      // Initialize immediately
      jQuery(document).ready(function () {
        // Main initialization already handled above
      });
    } else if (jqueryCheckAttempts > 50) {
      console.error('❌ Operaton DMN: jQuery not found after 50 attempts');
      clearInterval(jqueryCheckInterval);
    }
  }, 100);
}

// Strategy 3: Window load fallback
window.addEventListener('load', function () {
  setTimeout(function () {
    if (!window.operatonInitialized.globalInit) {
      console.log('🔄 Window load: Attempting late initialization...');

      if (typeof jQuery !== 'undefined') {
        // Main initialization already handled above
        console.log('jQuery available on window load');
      } else {
        console.warn('⚠️ Window load: jQuery still not available');
      }
    }
  }, 1000);
});
