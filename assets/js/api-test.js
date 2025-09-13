/**
 * Operaton DMN API Testing Module
 *
 * Handles configuration testing functionality with detailed result display
 * and user-friendly progress indicators.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

(function ($) {
  'use strict';

  // Module namespace
  window.OperatonDMNTesting = {
    // Configuration
    config: {
      modalId: 'operaton-test-modal',
      testInProgress: false,
      currentTestId: null,
    },

    /**
     * Initialize the testing module
     */
    init: function () {
      this.createTestModal();
      this.bindEvents();

      if (typeof console !== 'undefined' && console.log) {
        console.log('Operaton DMN Testing: Module initialized');
      }
    },

    /**
     * Create the test results modal
     */
    createTestModal: function () {
      if ($('#' + this.config.modalId).length > 0) {
        return; // Modal already exists
      }

      var modalHtml = `
                <div id="${this.config.modalId}" class="operaton-test-modal" style="display: none;">
                    <div class="operaton-test-modal-backdrop"></div>
                    <div class="operaton-test-modal-content">
                        <div class="operaton-test-modal-header">
                            <h3 id="operaton-test-modal-title">Configuration Test Results</h3>
                            <button type="button" class="button operaton-test-modal-close" aria-label="Close">&times;</button>
                        </div>
                        <div class="operaton-test-modal-body">
                            <div id="operaton-test-progress" class="operaton-test-progress" style="display: none;">
                                <div class="operaton-progress-bar">
                                    <div class="operaton-progress-fill"></div>
                                </div>
                                <p class="operaton-progress-text">Testing configuration...</p>
                            </div>
                            <div id="operaton-test-results" class="operaton-test-results"></div>
                        </div>
                        <div class="operaton-test-modal-footer">
                            <button type="button" class="button operaton-test-modal-close">Close</button>
                            <button type="button" class="button button-secondary" id="operaton-test-copy-results" style="display: none;">Copy Results</button>
                        </div>
                    </div>
                </div>
            `;

      $('body').append(modalHtml);
      this.addModalStyles();
    },

    /**
     * Add CSS styles for the modal
     */
    addModalStyles: function () {
      if ($('#operaton-test-modal-styles').length > 0) {
        return; // Styles already added
      }

      var styles = `
                <style id="operaton-test-modal-styles">
                    .operaton-test-modal {
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        z-index: 100000;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    }

                    .operaton-test-modal-backdrop {
                        position: absolute;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.7);
                        cursor: pointer;
                    }

                    .operaton-test-modal-content {
                        position: relative;
                        background: white;
                        margin: 5% auto;
                        width: 90%;
                        max-width: 800px;
                        max-height: 80vh;
                        border-radius: 8px;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                        display: flex;
                        flex-direction: column;
                    }

                    .operaton-test-modal-header {
                        padding: 20px 25px 15px;
                        border-bottom: 1px solid #e1e1e1;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        background: #f8f9fa;
                        border-radius: 8px 8px 0 0;
                    }

                    .operaton-test-modal-header h3 {
                        margin: 0;
                        color: #1d2327;
                        font-size: 18px;
                        font-weight: 600;
                    }

                    .operaton-test-modal-close {
                        background: none;
                        border: none;
                        font-size: 24px;
                        color: #666;
                        cursor: pointer;
                        padding: 0;
                        width: 30px;
                        height: 30px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 4px;
                    }

                    .operaton-test-modal-close:hover {
                        background: #e1e1e1;
                        color: #000;
                    }

                    .operaton-test-modal-body {
                        padding: 25px;
                        flex: 1;
                        overflow-y: auto;
                        max-height: 60vh;
                    }

                    .operaton-test-modal-footer {
                        padding: 15px 25px 20px;
                        border-top: 1px solid #e1e1e1;
                        text-align: right;
                        background: #f8f9fa;
                        border-radius: 0 0 8px 8px;
                    }

                    .operaton-test-modal-footer .button {
                        margin-left: 10px;
                    }

                    /* Progress Bar Styles */
                    .operaton-test-progress {
                        text-align: center;
                        padding: 20px 0;
                    }

                    .operaton-progress-bar {
                        width: 100%;
                        height: 8px;
                        background: #e1e1e1;
                        border-radius: 4px;
                        overflow: hidden;
                        margin-bottom: 15px;
                    }

                    .operaton-progress-fill {
                        height: 100%;
                        background: linear-gradient(90deg, #0073aa, #005a87);
                        border-radius: 4px;
                        animation: operaton-progress-animation 2s ease-in-out infinite;
                        width: 0%;
                        transition: width 0.3s ease;
                    }

                    @keyframes operaton-progress-animation {
                        0%, 100% { opacity: 1; }
                        50% { opacity: 0.7; }
                    }

                    .operaton-progress-text {
                        color: #666;
                        font-size: 14px;
                        margin: 0;
                    }

                    /* Test Results Styles */
                    .operaton-test-summary {
                        background: #f0f8ff;
                        border: 1px solid #0073aa;
                        border-radius: 6px;
                        padding: 20px;
                        margin-bottom: 25px;
                    }

                    .operaton-test-summary.success {
                        background: #d4edda;
                        border-color: #28a745;
                    }

                    .operaton-test-summary.error {
                        background: #f8d7da;
                        border-color: #dc3545;
                    }

                    .operaton-test-summary h4 {
                        margin: 0 0 10px 0;
                        font-size: 16px;
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    }

                    .operaton-test-summary .summary-icon {
                        font-size: 20px;
                    }

                    .operaton-test-details {
                        display: grid;
                        gap: 20px;
                    }

                    .operaton-test-section {
                        background: white;
                        border: 1px solid #e1e1e1;
                        border-radius: 6px;
                        overflow: hidden;
                    }

                    .operaton-test-section-header {
                        background: #f8f9fa;
                        padding: 12px 18px;
                        border-bottom: 1px solid #e1e1e1;
                        font-weight: 600;
                        font-size: 14px;
                        color: #495057;
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    }

                    .operaton-test-section-body {
                        padding: 18px;
                    }

                    .operaton-test-step {
                        display: flex;
                        align-items: flex-start;
                        gap: 12px;
                        margin-bottom: 12px;
                        padding-bottom: 12px;
                        border-bottom: 1px solid #f1f1f1;
                    }

                    .operaton-test-step:last-child {
                        border-bottom: none;
                        margin-bottom: 0;
                        padding-bottom: 0;
                    }

                    .operaton-test-step-icon {
                        font-size: 16px;
                        margin-top: 2px;
                        width: 20px;
                        text-align: center;
                    }

                    .operaton-test-step-content {
                        flex: 1;
                    }

                    .operaton-test-step-title {
                        font-weight: 600;
                        color: #1d2327;
                        margin-bottom: 4px;
                    }

                    .operaton-test-step-message {
                        color: #666;
                        font-size: 13px;
                        line-height: 1.4;
                    }

                    .operaton-test-data-grid {
                        display: grid;
                        grid-template-columns: 1fr 2fr;
                        gap: 8px 15px;
                        font-size: 13px;
                        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                    }

                    .operaton-test-data-label {
                        font-weight: 600;
                        color: #495057;
                    }

                    .operaton-test-data-value {
                        color: #666;
                        word-break: break-word;
                    }

                    /* Responsive */
                    @media (max-width: 768px) {
                        .operaton-test-modal-content {
                            margin: 10px;
                            width: calc(100% - 20px);
                            max-height: calc(100vh - 20px);
                        }

                        .operaton-test-modal-header,
                        .operaton-test-modal-body,
                        .operaton-test-modal-footer {
                            padding: 15px;
                        }

                        .operaton-test-data-grid {
                            grid-template-columns: 1fr;
                            gap: 4px;
                        }
                    }
                </style>
            `;

      $('head').append(styles);
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      var self = this;

      // Close modal events
      $(document).on(
        'click',
        '#' +
          this.config.modalId +
          ' .operaton-test-modal-close, #' +
          this.config.modalId +
          ' .operaton-test-modal-backdrop',
        function () {
          self.closeModal();
        }
      );

      // Copy results
      $(document).on('click', '#operaton-test-copy-results', function () {
        self.copyResultsToClipboard();
      });

      // Escape key to close modal
      $(document).on('keydown', function (e) {
        if (e.keyCode === 27 && $('#' + self.config.modalId).is(':visible')) {
          self.closeModal();
        }
      });
    },

    /**
     * Test configuration (main entry point)
     */
    testConfig: function (configId, configName) {
      if (this.config.testInProgress) {
        alert('A test is already in progress. Please wait for it to complete.');
        return;
      }

      this.config.testInProgress = true;
      this.config.currentTestId = configId;

      this.showModal();
      this.showProgress('Testing configuration "' + configName + '"...');

      // Update modal title
      $('#operaton-test-modal-title').text('Testing Configuration: ' + configName);

      // Animate progress bar
      this.animateProgress([
        { width: '25%', text: 'Validating configuration...' },
        { width: '50%', text: 'Generating test data...' },
        { width: '75%', text: 'Calling API endpoint...' },
        { width: '90%', text: 'Validating response...' },
      ]);

      // Make AJAX call
      $.post(ajaxurl, {
        action: 'operaton_test_configuration_complete',
        _ajax_nonce: operaton_admin.nonce,
        config_id: configId,
      })
        .done(this.handleTestSuccess.bind(this))
        .fail(this.handleTestError.bind(this))
        .always(this.handleTestComplete.bind(this));
    },

    /**
     * Show the modal
     */
    showModal: function () {
      $('#' + this.config.modalId).fadeIn(300);
      $('body').addClass('modal-open');
    },

    /**
     * Close the modal
     */
    closeModal: function () {
      $('#' + this.config.modalId).fadeOut(300);
      $('body').removeClass('modal-open');
      this.config.testInProgress = false;
      this.config.currentTestId = null;
    },

    /**
     * Show progress indicator
     */
    showProgress: function (text) {
      $('#operaton-test-progress').show();
      $('#operaton-test-results').hide();
      $('#operaton-test-copy-results').hide();
      $('.operaton-progress-text').text(text);
      $('.operaton-progress-fill').css('width', '10%');
    },

    /**
     * Animate progress bar through stages
     */
    animateProgress: function (stages) {
      var currentStage = 0;
      var self = this;

      function nextStage() {
        if (currentStage < stages.length && self.config.testInProgress) {
          var stage = stages[currentStage];
          $('.operaton-progress-fill').css('width', stage.width);
          $('.operaton-progress-text').text(stage.text);
          currentStage++;
          setTimeout(nextStage, 800);
        }
      }

      nextStage();
    },

    /**
     * Handle successful test response
     */
    handleTestSuccess: function (response) {
      if (response.success) {
        this.displayTestResults(response.data);
      } else {
        this.displayTestError(response.data);
      }
    },

    /**
     * Handle test error
     */
    handleTestError: function (xhr, status, error) {
      var errorMessage = 'Test request failed';

      if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
        errorMessage = xhr.responseJSON.data.message;
      } else if (error) {
        errorMessage += ': ' + error;
      }

      this.displayTestError({
        message: errorMessage,
        test_type: 'request_error',
      });
    },

    /**
     * Handle test completion
     */
    handleTestComplete: function () {
      $('#operaton-test-progress').hide();
      $('#operaton-test-results').show();
      $('#operaton-test-copy-results').show();
      this.config.testInProgress = false;
    },

    /**
     * Display test results
     */
    displayTestResults: function (results) {
      var html = this.buildResultsHTML(results);
      $('#operaton-test-results').html(html);
    },

    /**
     * Display test error
     */
    displayTestError: function (errorData) {
      var html = this.buildErrorHTML(errorData);
      $('#operaton-test-results').html(html);
    },

    /**
     * Build results HTML
     */
    buildResultsHTML: function (results) {
      var isSuccess = results.success;
      var summaryClass = isSuccess ? 'success' : 'error';
      var summaryIcon = isSuccess ? '✅' : '❌';

      var html = '<div class="operaton-test-summary ' + summaryClass + '">';
      html += '<h4><span class="summary-icon">' + summaryIcon + '</span>' + this.escapeHtml(results.message) + '</h4>';

      if (results.summary) {
        html += '<p>' + this.escapeHtml(results.summary) + '</p>';
      }

      // Basic info
      html += '<div class="operaton-test-data-grid" style="margin-top: 15px;">';
      html += '<div class="operaton-test-data-label">Test Type:</div>';
      html +=
        '<div class="operaton-test-data-value">' +
        this.escapeHtml(results.mode || results.test_type || 'Unknown') +
        '</div>';

      if (results.endpoint_tested) {
        html += '<div class="operaton-test-data-label">Endpoint:</div>';
        html +=
          '<div class="operaton-test-data-value" style="word-break: break-all;">' +
          this.escapeHtml(results.endpoint_tested) +
          '</div>';
      }

      if (results.performance && results.performance.total_time_ms) {
        html += '<div class="operaton-test-data-label">Total Time:</div>';
        html += '<div class="operaton-test-data-value">' + results.performance.total_time_ms + ' ms</div>';
      }
      html += '</div>';

      html += '</div>';

      // Test steps
      if (results.steps && Object.keys(results.steps).length > 0) {
        html += this.buildStepsSection(results.steps);
      }

      // Validation results
      if (results.validation) {
        html += this.buildValidationSection(results.validation);
      }

      // Suggestions for failed tests
      if (!isSuccess && results.suggestion) {
        html += '<div class="operaton-test-section">';
        html += '<div class="operaton-test-section-header">💡 Suggestion</div>';
        html += '<div class="operaton-test-section-body">';
        html += '<p>' + this.escapeHtml(results.suggestion) + '</p>';
        html += '</div>';
        html += '</div>';
      }

      return html;
    },

    /**
     * Build steps section
     */
    buildStepsSection: function (steps) {
      var html = '<div class="operaton-test-section">';
      html += '<div class="operaton-test-section-header">🔧 Test Steps</div>';
      html += '<div class="operaton-test-section-body">';

      for (var stepName in steps) {
        var step = steps[stepName];
        var stepIcon = step.success ? '✅' : '❌';
        var stepTitle = this.formatStepTitle(stepName);

        html += '<div class="operaton-test-step">';
        html += '<div class="operaton-test-step-icon">' + stepIcon + '</div>';
        html += '<div class="operaton-test-step-content">';
        html += '<div class="operaton-test-step-title">' + stepTitle + '</div>';
        html += '<div class="operaton-test-step-message">' + this.escapeHtml(step.message) + '</div>';

        // Additional step details
        if (step.variables_count) {
          html +=
            '<div class="operaton-test-step-message" style="margin-top: 4px;">Variables processed: ' +
            step.variables_count +
            '</div>';
        }
        if (step.http_code) {
          html +=
            '<div class="operaton-test-step-message" style="margin-top: 4px;">HTTP Status: ' +
            step.http_code +
            '</div>';
        }

        html += '</div>';
        html += '</div>';
      }

      html += '</div>';
      html += '</div>';

      return html;
    },

    /**
     * Build validation section
     */
    buildValidationSection: function (validation) {
      var html = '<div class="operaton-test-section">';
      html += '<div class="operaton-test-section-header">🔍 Field Validation</div>';
      html += '<div class="operaton-test-section-body">';

      html += '<div class="operaton-test-data-grid">';
      html += '<div class="operaton-test-data-label">Expected Fields:</div>';
      html += '<div class="operaton-test-data-value">' + (validation.total_expected || 0) + '</div>';

      if (validation.found_fields !== undefined) {
        html += '<div class="operaton-test-data-label">Found Fields:</div>';
        html += '<div class="operaton-test-data-value">' + validation.found_fields + '</div>';
      }

      if (validation.response_structure) {
        html += '<div class="operaton-test-data-label">Response Type:</div>';
        html += '<div class="operaton-test-data-value">' + this.escapeHtml(validation.response_structure) + '</div>';
      }
      html += '</div>';

      // Found field details
      if (validation.found_field_details && Object.keys(validation.found_field_details).length > 0) {
        html += '<h5 style="margin: 15px 0 10px 0; color: #28a745;">✅ Found Fields:</h5>';
        html += '<div class="operaton-test-data-grid">';
        for (var fieldName in validation.found_field_details) {
          var field = validation.found_field_details[fieldName];
          html += '<div class="operaton-test-data-label">' + this.escapeHtml(fieldName) + ':</div>';
          html +=
            '<div class="operaton-test-data-value">' +
            this.escapeHtml(String(field.value)) +
            ' (' +
            field.type +
            ')</div>';
        }
        html += '</div>';
      }

      // Missing fields
      if (validation.missing_fields && validation.missing_fields.length > 0) {
        html += '<h5 style="margin: 15px 0 10px 0; color: #dc3545;">❌ Missing Fields:</h5>';
        html += '<ul style="margin: 0; padding-left: 20px; color: #dc3545;">';
        for (var i = 0; i < validation.missing_fields.length; i++) {
          html += '<li>' + this.escapeHtml(validation.missing_fields[i]) + '</li>';
        }
        html += '</ul>';
      }

      html += '</div>';
      html += '</div>';

      return html;
    },

    /**
     * Build error HTML
     */
    buildErrorHTML: function (errorData) {
      var html = '<div class="operaton-test-summary error">';
      html += '<h4><span class="summary-icon">❌</span>Test Failed</h4>';
      html += '<p>' + this.escapeHtml(errorData.message || 'Unknown error occurred') + '</p>';

      if (errorData.issues && errorData.issues.length > 0) {
        html += '<h5 style="margin: 15px 0 10px 0;">Issues Found:</h5>';
        html += '<ul style="margin: 0; padding-left: 20px;">';
        for (var i = 0; i < errorData.issues.length; i++) {
          html += '<li>' + this.escapeHtml(errorData.issues[i]) + '</li>';
        }
        html += '</ul>';
      }

      html += '</div>';

      return html;
    },

    /**
     * Format step title for display
     */
    formatStepTitle: function (stepName) {
      var titles = {
        connectivity: 'Endpoint Connectivity',
        input_processing: 'Input Variable Processing',
        api_call: 'API Request',
        response_parsing: 'Response Parsing',
      };

      return (
        titles[stepName] ||
        stepName.replace(/_/g, ' ').replace(/\b\w/g, function (l) {
          return l.toUpperCase();
        })
      );
    },

    /**
     * Copy results to clipboard
     */
    copyResultsToClipboard: function () {
      var resultsText = $('#operaton-test-results').text();

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(resultsText).then(function () {
          alert('Test results copied to clipboard!');
        });
      } else {
        // Fallback for older browsers
        var textArea = document.createElement('textarea');
        textArea.value = resultsText;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('Test results copied to clipboard!');
      }
    },

    /**
     * Escape HTML for safe display
     */
    escapeHtml: function (text) {
      if (typeof text !== 'string') {
        return text;
      }

      var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
      };

      return text.replace(/[&<>"']/g, function (m) {
        return map[m];
      });
    },
  };

  // Initialize when document is ready
  $(document).ready(function () {
    if (typeof ajaxurl !== 'undefined') {
      OperatonDMNTesting.init();
    }
  });

  // Global function for backward compatibility
  window.testConfig = function (configId, configName) {
    OperatonDMNTesting.testConfig(configId, configName || 'Configuration #' + configId);
  };
})(jQuery);
