/**
 * OPTIMIZED: Decision Flow JavaScript for Operaton DMN Plugin
 *
 * Separated from frontend.js and gravity-forms.js to reduce page transition delays
 * and provide better control over decision flow loading timing.
 *
 * @package OperatonDMN
 * @since 1.0.0
 */

(function ($) {
  'use strict';

  // =============================================================================
  // DECISION FLOW MANAGER
  // =============================================================================

  window.OperatonDecisionFlow = {
    /**
     * Cache for loaded decision flows to prevent duplicate requests
     */
    loadedFlows: new Map(),
    loadingFlows: new Set(),

    /**
     * Configuration
     */
    config: {
      loadDelay: 300, // Delay before loading decision flow (ms)
      cacheTimeout: 30000, // Cache timeout (30 seconds)
      retryAttempts: 3,
      retryDelay: 1000,
    },

    /**
     * Initialize decision flow functionality for a specific form
     *
     * @param {number} formId Form ID
     * @param {object} formConfig Form configuration
     */
    initialize: function (formId, formConfig) {
      if (!formConfig || !formConfig.show_decision_flow || !formConfig.use_process) {
        console.log('📊 Decision Flow: Not enabled for form', formId);
        return;
      }

      console.log('📊 Decision Flow: Initializing for form', formId);

      // Set up page change listener
      this.setupPageChangeListener(formId, formConfig);

      // Set up refresh button handler
      this.setupRefreshHandler(formId);

      // Check current page and load if needed
      this.checkAndLoadForCurrentPage(formId, formConfig);
    },

    /**
     * Set up page change listener with debouncing
     *
     * @param {number} formId Form ID
     * @param {object} formConfig Form configuration
     */
    setupPageChangeListener: function (formId, formConfig) {
      const self = this;
      let pageChangeTimeout;

      // Listen for Gravity Forms page changes
      $(document).on('gform_page_loaded', function (event, form_id, current_page) {
        if (form_id != formId) return;

        console.log('📊 Decision Flow: Page loaded -', form_id, 'page', current_page);

        // Clear any existing timeout
        clearTimeout(pageChangeTimeout);

        // Debounce page change handling
        pageChangeTimeout = setTimeout(function () {
          self.handlePageChange(formId, current_page, formConfig);
        }, 100);
      });
    },

    /**
     * Handle page change events
     *
     * @param {number} formId Form ID
     * @param {number} currentPage Current page number
     * @param {object} formConfig Form configuration
     */
    handlePageChange: function (formId, currentPage, formConfig) {
      const targetPage = parseInt(formConfig.evaluation_step) || 2;
      const summaryPage = targetPage + 1;

      console.log(
        '📊 Decision Flow: Page change handled -',
        formId,
        'current:',
        currentPage,
        'summary page:',
        summaryPage
      );

      if (currentPage === summaryPage) {
        // We're on the summary page - load decision flow with delay
        this.loadWithDelay(formId);
      } else {
        // We're not on summary page - hide decision flow
        this.hideDecisionFlow(formId);
      }
    },

    /**
     * Check current page and load decision flow if appropriate
     *
     * @param {number} formId Form ID
     * @param {object} formConfig Form configuration
     */
    checkAndLoadForCurrentPage: function (formId, formConfig) {
      const currentPage = this.getCurrentPage(formId);
      const targetPage = parseInt(formConfig.evaluation_step) || 2;
      const summaryPage = targetPage + 1;

      console.log(
        '📊 Decision Flow: Current page check -',
        formId,
        'current:',
        currentPage,
        'summary page:',
        summaryPage
      );

      if (currentPage === summaryPage) {
        this.loadWithDelay(formId);
      }
    },

    /**
     * Get current page number for a form
     *
     * @param {number} formId Form ID
     * @return {number} Current page number
     */
    getCurrentPage: function (formId) {
      // Check URL parameter first
      const urlParams = new URLSearchParams(window.location.search);
      const gfPage = urlParams.get('gf_page');
      if (gfPage) {
        return parseInt(gfPage);
      }

      // Check form's page field
      const $pageField = $('#gform_source_page_number_' + formId);
      if ($pageField.length && $pageField.val()) {
        return parseInt($pageField.val());
      }

      return 1;
    },

    /**
     * Load decision flow with configurable delay to prevent page transition interference
     *
     * @param {number} formId Form ID
     */
    loadWithDelay: function (formId) {
      const self = this;

      console.log('📊 Decision Flow: Scheduling load with delay for form', formId);

      // Clear any existing delay
      if (this.loadTimeout) {
        clearTimeout(this.loadTimeout);
      }

      // Load with delay to let page transition complete
      this.loadTimeout = setTimeout(function () {
        self.loadDecisionFlow(formId);
      }, this.config.loadDelay);
    },

    /**
     * Load decision flow summary
     *
     * @param {number} formId Form ID
     * @param {boolean} forceReload Force reload even if cached
     */
    loadDecisionFlow: function (formId, forceReload = false) {
      const $container = $('#decision-flow-summary-' + formId);

      if (!$container.length) {
        console.log('📊 Decision Flow: No container found for form', formId);
        return;
      }

      // Check if already loading
      if (this.loadingFlows.has(formId)) {
        console.log('📊 Decision Flow: Already loading for form', formId);
        return;
      }

      // Check cache if not forcing reload
      if (!forceReload && this.loadedFlows.has(formId)) {
        const cached = this.loadedFlows.get(formId);
        const age = Date.now() - cached.timestamp;

        if (age < this.config.cacheTimeout) {
          console.log('📊 Decision Flow: Using cached data for form', formId);
          $container.html(cached.html).show();
          this.addRefreshButton(formId, $container);
          return;
        }
      }

      // Get process instance ID
      const processInstanceId = this.getProcessInstanceId(formId);
      if (!processInstanceId) {
        console.log('📊 Decision Flow: No process instance ID for form', formId);
        this.showPlaceholder($container);
        return;
      }

      console.log('📊 Decision Flow: Loading for form', formId, 'process:', processInstanceId);

      // Mark as loading
      this.loadingFlows.add(formId);

      // Show loading state
      this.showLoading($container);

      // Make AJAX request
      this.makeRequest(formId, $container, 1);
    },

    /**
     * Make AJAX request with retry logic
     *
     * @param {number} formId Form ID
     * @param {jQuery} $container Container element
     * @param {number} attempt Attempt number
     */
    makeRequest: function (formId, $container, attempt) {
      const self = this;

      $.ajax({
        url: window.location.origin + '/wp-json/operaton-dmn/v1/decision-flow/' + formId + '?cache_bust=' + Date.now(),
        type: 'GET',
        cache: false,
        timeout: 10000,
        success: function (response) {
          self.handleSuccess(formId, $container, response);
        },
        error: function (xhr, status, error) {
          self.handleError(formId, $container, xhr, status, error, attempt);
        },
        complete: function () {
          self.loadingFlows.delete(formId);
        },
      });
    },

    /**
     * Handle successful response
     *
     * @param {number} formId Form ID
     * @param {jQuery} $container Container element
     * @param {object} response AJAX response
     */
    handleSuccess: function (formId, $container, response) {
      if (response.success && response.html) {
        console.log('📊 Decision Flow: Loaded successfully for form', formId);

        // Cache the response
        this.loadedFlows.set(formId, {
          html: response.html,
          timestamp: Date.now(),
        });

        // Display the content
        $container.html(response.html).removeClass('loading').show();

        // Add refresh button
        this.addRefreshButton(formId, $container);

        // Smooth scroll to container
        this.scrollToContainer($container);
      } else {
        console.log('📊 Decision Flow: No data available for form', formId);
        this.showNoData($container);
      }
    },

    /**
     * Handle error response with retry logic
     *
     * @param {number} formId Form ID
     * @param {jQuery} $container Container element
     * @param {object} xhr XMLHttpRequest object
     * @param {string} status Status string
     * @param {string} error Error message
     * @param {number} attempt Attempt number
     */
    handleError: function (formId, $container, xhr, status, error, attempt) {
      console.error('📊 Decision Flow: Error loading for form', formId, '- attempt', attempt, ':', error);

      if (attempt < this.config.retryAttempts) {
        // Retry after delay
        const self = this;
        setTimeout(function () {
          console.log('📊 Decision Flow: Retrying for form', formId, '- attempt', attempt + 1);
          self.makeRequest(formId, $container, attempt + 1);
        }, this.config.retryDelay * attempt);
      } else {
        // Max retries reached
        this.showError($container, error);
      }
    },

    /**
     * Get process instance ID for a form
     *
     * @param {number} formId Form ID
     * @return {string|null} Process instance ID
     */
    getProcessInstanceId: function (formId) {
      // Check window variable first
      if (window['operaton_process_' + formId]) {
        return window['operaton_process_' + formId];
      }

      // Check session storage
      if (typeof Storage !== 'undefined') {
        const processId = sessionStorage.getItem('operaton_process_' + formId);
        if (processId) {
          return processId;
        }

        // Check evaluation data
        const evalData = sessionStorage.getItem('operaton_dmn_eval_data_' + formId);
        if (evalData) {
          try {
            const parsed = JSON.parse(evalData);
            if (parsed.processInstanceId) {
              return parsed.processInstanceId;
            }
          } catch (e) {
            console.error('📊 Decision Flow: Error parsing evaluation data:', e);
          }
        }
      }

      return null;
    },

    /**
     * Show loading state
     *
     * @param {jQuery} $container Container element
     */
    showLoading: function ($container) {
      $container
        .addClass('loading')
        .html(
          '<div class="decision-flow-loading">' +
            '<h3>🔍 Decision Flow Results</h3>' +
            '<p>⏳ Loading decision flow summary...</p>' +
            '</div>'
        )
        .show();
    },

    /**
     * Show placeholder when no process instance ID is available
     *
     * @param {jQuery} $container Container element
     */
    showPlaceholder: function ($container) {
      $container
        .removeClass('loading')
        .html(
          '<div class="decision-flow-placeholder">' +
            '<h3>🔍 Decision Flow Results</h3>' +
            '<p><em>Complete the evaluation on the previous step to see the detailed decision flow summary here.</em></p>' +
            '</div>'
        )
        .show();
    },

    /**
     * Show no data message
     *
     * @param {jQuery} $container Container element
     */
    showNoData: function ($container) {
      $container
        .removeClass('loading')
        .html(
          '<div class="decision-flow-empty">' +
            '<h3>🔍 Decision Flow Results</h3>' +
            '<p><em>No decision flow data available.</em></p>' +
            '</div>'
        )
        .show();
    },

    /**
     * Show error message
     *
     * @param {jQuery} $container Container element
     * @param {string} error Error message
     */
    showError: function ($container, error) {
      $container
        .removeClass('loading')
        .html(
          '<div class="decision-flow-error">' +
            '<h3>🔍 Decision Flow Results</h3>' +
            '<p><em>Error loading decision flow: ' +
            $('<div>').text(error).html() +
            '</em></p>' +
            '</div>'
        )
        .show();
    },

    /**
     * Add refresh button to container
     *
     * @param {number} formId Form ID
     * @param {jQuery} $container Container element
     */
    addRefreshButton: function (formId, $container) {
      // Remove existing refresh button
      $container.find('.refresh-decision-flow-controlled').remove();

      // Add new refresh button
      const $refreshButton = $(
        '<div style="margin-top: 15px; text-align: center;">' +
          '<button type="button" class="refresh-decision-flow-controlled" data-form-id="' +
          formId +
          '">' +
          '🔄 Refresh Decision Flow' +
          '</button>' +
          '</div>'
      );

      $container.append($refreshButton);
    },

    /**
     * Set up refresh button handler
     *
     * @param {number} formId Form ID
     */
    setupRefreshHandler: function (formId) {
      const self = this;

      $(document).off(
        'click.decision-flow-' + formId,
        '.refresh-decision-flow-controlled[data-form-id="' + formId + '"]'
      );
      $(document).on(
        'click.decision-flow-' + formId,
        '.refresh-decision-flow-controlled[data-form-id="' + formId + '"]',
        function (e) {
          e.preventDefault();

          const $button = $(this);
          const originalText = $button.text();

          console.log('📊 Decision Flow: Manual refresh requested for form', formId);

          // Update button state
          $button.text('🔄 Refreshing...').prop('disabled', true);

          // Clear cache
          self.loadedFlows.delete(formId);

          // Reload
          self.loadDecisionFlow(formId, true);

          // Restore button after delay
          setTimeout(function () {
            $button.text(originalText).prop('disabled', false);
          }, 2000);
        }
      );
    },

    /**
     * Hide decision flow
     *
     * @param {number} formId Form ID
     */
    hideDecisionFlow: function (formId) {
      const $container = $('#decision-flow-summary-' + formId);
      if ($container.length) {
        console.log('📊 Decision Flow: Hiding for form', formId);
        $container.hide().removeClass('loading');
      }
    },

    /**
     * Smooth scroll to container
     *
     * @param {jQuery} $container Container element
     */
    scrollToContainer: function ($container) {
      if ($container.length && $container.offset()) {
        $('html, body').animate(
          {
            scrollTop: $container.offset().top - 100,
          },
          500
        );
      }
    },

    /**
     * Clear all caches
     */
    clearCache: function () {
      this.loadedFlows.clear();
      this.loadingFlows.clear();
      console.log('📊 Decision Flow: Cache cleared');
    },

    /**
     * Get debug information
     *
     * @return {object} Debug information
     */
    getDebugInfo: function () {
      return {
        loadedFlows: Array.from(this.loadedFlows.keys()),
        loadingFlows: Array.from(this.loadingFlows),
        config: this.config,
        timestamp: new Date().toISOString(),
      };
    },
  };

  // =============================================================================
  // INITIALIZATION
  // =============================================================================

  /**
   * Initialize decision flow when DOM is ready
   */
  $(document).ready(function () {
    console.log('📊 Decision Flow: Manager loaded and ready');

    // Make available globally for debugging
    if (window.operaton_ajax && window.operaton_ajax.debug) {
      window.operatonDecisionFlowDebug = function () {
        console.log('📊 Decision Flow Debug Info:', window.OperatonDecisionFlow.getDebugInfo());
      };
    }
  });

  // =============================================================================
  // INTEGRATION WITH MAIN PLUGIN
  // =============================================================================

  /**
   * Global function to initialize decision flow for a form
   * Called from frontend.js or gravity-forms.js
   *
   * @param {number} formId Form ID
   * @param {object} formConfig Form configuration
   */
  window.initializeDecisionFlowForForm = function (formId, formConfig) {
    if (typeof window.OperatonDecisionFlow !== 'undefined') {
      window.OperatonDecisionFlow.initialize(formId, formConfig);
    } else {
      console.error('📊 Decision Flow: Manager not available');
    }
  };

  /**
   * Global function to load decision flow
   * Called from frontend.js when evaluation completes
   *
   * @param {number} formId Form ID
   * @param {boolean} forceReload Force reload
   */
  window.loadDecisionFlowSummary = function (formId, forceReload = false) {
    if (typeof window.OperatonDecisionFlow !== 'undefined') {
      window.OperatonDecisionFlow.loadDecisionFlow(formId, forceReload);
    } else {
      console.error('📊 Decision Flow: Manager not available');
    }
  };

  /**
   * Global function to hide decision flow
   * Called from frontend.js during page transitions
   *
   * @param {number} formId Form ID
   */
  window.hideDecisionFlowSummary = function (formId) {
    if (typeof window.OperatonDecisionFlow !== 'undefined') {
      window.OperatonDecisionFlow.hideDecisionFlow(formId);
    } else {
      console.error('📊 Decision Flow: Manager not available');
    }
  };
})(jQuery);
