// FIXED frontend.js - Prevent duplicate initialization and improve jQuery handling
console.log('Operaton DMN frontend script loading (enhanced with decision flow)...');

// FIXED: Global flag to prevent duplicate initialization
window.operatonDmnInitialized = window.operatonDmnInitialized || false;

jQuery(document).ready(function($) {
    console.log('Enhanced Operaton DMN frontend script loaded');

    // CRITICAL FIX: Wait for operaton_ajax to be available
    function waitForOperatonAjax(callback, maxAttempts = 30) {
        var attempts = 0;
        
        function check() {
            attempts++;
            
            if (typeof window.operaton_ajax !== 'undefined') {
                console.log('✅ operaton_ajax found after', attempts, 'attempts');
                callback();
            } else if (attempts < maxAttempts) {
                console.log('⏳ Waiting for operaton_ajax... attempt', attempts);
                setTimeout(check, 100);
            } else {
                console.error('❌ operaton_ajax not found after', maxAttempts, 'attempts');
                // Emergency fallback
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
                        connection_error: 'Connection error. Please try again.'
                    }
                };
                console.log('🆘 Using emergency fallback for operaton_ajax');
                callback();
            }
        }
        check();
    }

    // FIXED: Initialize only once after operaton_ajax is available
    waitForOperatonAjax(function() {
        console.log('Initializing Enhanced Operaton DMN...');
        
        // Set initialization flag
        window.operatonDmnInitialized = true;
        
        initOperatonDMN();
        
        // Bind events for existing forms
        $('form[id^="gform_"]').each(function() {
            var formId = $(this).attr('id').replace('gform_', '');
            initializeFormEvaluation(formId);
        });
        
        console.log('Enhanced Operaton DMN frontend script initialization complete');
    });

    // FIXED: Debounced form initialization to prevent multiple calls
    var formInitializationTimers = {};
    
    function debouncedFormInitialization(formId) {
        if (formInitializationTimers[formId]) {
            clearTimeout(formInitializationTimers[formId]);
        }
        
        formInitializationTimers[formId] = setTimeout(function() {
            initializeFormEvaluation(formId);
        }, 200);
    }

    // Initialize evaluation for forms
    var initOperatonDMN = function() {
        if (typeof gform !== 'undefined' && gform.initializeOnLoaded) {
            gform.addAction('gform_post_render', function(formId) {
                console.log('Gravity Form rendered, initializing Enhanced Operaton DMN for form:', formId);
                debouncedFormInitialization(formId);
            });
        } else {
            setTimeout(function() {
                $('form[id^="gform_"]').each(function() {
                    var formId = $(this).attr('id').replace('gform_', '');
                    debouncedFormInitialization(formId);
                });
            }, 500);
        }
    };

    // FIXED: Enhanced form initialization with duplicate prevention
    function initializeFormEvaluation(formId) {
        var configVar = 'operaton_config_' + formId;
        if (typeof window[configVar] !== 'undefined') {
            // Check if already initialized for this form
            if (window['operaton_form_' + formId + '_initialized']) {
                console.log('Form', formId, 'already initialized, skipping');
                return;
            }
            
            console.log('Enhanced configuration found for form:', formId);
            var config = window[configVar];
            
            bindEvaluationEvents(formId);
            bindNavigationEvents(formId);
            bindInputChangeListeners(formId);
            
            // NEW: Initialize decision flow summary if enabled
            if (config.show_decision_flow) {
                initializeDecisionFlowSummary(formId);
            }
            
            // Clear any existing results when form initializes
            setTimeout(function() {
                clearResultFieldWithMessage(formId, 'Form initialized');
            }, 200);
            
            // Mark as initialized
            window['operaton_form_' + formId + '_initialized'] = true;
        }
    }

    // NEW: Initialize decision flow summary functionality
    function initializeDecisionFlowSummary(formId) {
        console.log('Initializing decision flow summary for form:', formId);
        
        // Check if we're on the final page and have process data
        var currentPage = getCurrentPage(formId);
        var totalPages = getTotalPages(formId);
        
        if (currentPage === totalPages) {
            // We're on the final page - load decision flow if available
            loadDecisionFlowSummary(formId);
            
            // Also bind a refresh button if one exists
            bindDecisionFlowRefresh(formId);
        }
    }

    // NEW: Load and display decision flow summary
    function loadDecisionFlowSummary(formId) {
        var $summaryContainer = $('#decision-flow-summary-' + formId);
        
        if ($summaryContainer.length === 0) {
            console.log('No decision flow summary container found for form:', formId);
            return;
        }
        
        // Check if we have a stored process instance ID
        var processInstanceId = getStoredProcessInstanceId(formId);
        
        if (!processInstanceId) {
            $summaryContainer.html('<div class="decision-flow-placeholder">' +
                '<h3>🔍 Decision Flow Results</h3>' +
                '<p><em>Complete the evaluation on the previous step to see the detailed decision flow summary here.</em></p>' +
                '</div>');
            return;
        }
        
        // Show loading state
        $summaryContainer.html('<div class="decision-flow-loading">' +
            '<h3>🔍 Decision Flow Results</h3>' +
            '<p>⏳ Loading decision flow summary...</p>' +
            '</div>');
        
        // Fetch decision flow data via REST API
        $.ajax({
            url: operaton_ajax.url.replace('/evaluate', '/decision-flow/' + formId),
            type: 'GET',
            headers: {
                'X-WP-Nonce': operaton_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.html) {
                    $summaryContainer.html(response.html);
                    
                    // Add a refresh button
                    $summaryContainer.append(
                        '<div style="margin-top: 15px;">' +
                        '<button type="button" class="button refresh-decision-flow" data-form-id="' + formId + '">' +
                        '🔄 Refresh Decision Flow' +
                        '</button>' +
                        '</div>'
                    );
                    
                    // Smooth scroll to summary
                    $('html, body').animate({
                        scrollTop: $summaryContainer.offset().top - 100
                    }, 500);
                } else {
                    $summaryContainer.html('<div class="decision-flow-error">' +
                        '<h3>🔍 Decision Flow Results</h3>' +
                        '<p><em>Could not load decision flow summary. Please try refreshing the page.</em></p>' +
                        '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading decision flow:', error);
                $summaryContainer.html('<div class="decision-flow-error">' +
                    '<h3>🔍 Decision Flow Results</h3>' +
                    '<p><em>Error loading decision flow summary: ' + error + '</em></p>' +
                    '</div>');
            }
        });
    }

    // NEW: Bind decision flow refresh functionality
    function bindDecisionFlowRefresh(formId) {
        $(document).off('click.decision-flow', '.refresh-decision-flow[data-form-id="' + formId + '"]');
        $(document).on('click.decision-flow', '.refresh-decision-flow[data-form-id="' + formId + '"]', function(e) {
            e.preventDefault();
            console.log('Refreshing decision flow for form:', formId);
            
            var $button = $(this);
            var originalText = $button.text();
            $button.text('🔄 Refreshing...').prop('disabled', true);
            
            setTimeout(function() {
                loadDecisionFlowSummary(formId);
                $button.text(originalText).prop('disabled', false);
            }, 500);
        });
    }

    // Helper function to get total pages
    function getTotalPages(formId) {
        var $form = $('#gform_' + formId);
        var totalPages = 1;
        
        // Count page break fields
        $form.find('.gfield').each(function() {
            if ($(this).hasClass('gfield_page')) {
                totalPages++;
            }
        });
        
        return totalPages;
    }

function debugResultFields(formId) {
    console.log('=== DEBUGGING RESULT FIELDS ===');
    console.log('Form ID:', formId);
    console.log('Looking for fields 35 and 36...');
    
    // Check all possible selectors
    const selectors = [
        '#input_' + formId + '_35',
        '#input_' + formId + '_36',
        'input[name="input_' + formId + '_35"]',
        'input[name="input_' + formId + '_36"]',
        '#field_' + formId + '_35',
        '#field_' + formId + '_36'
    ];
    
    selectors.forEach(selector => {
        const elements = $(selector);
        console.log('Selector:', selector, 'Found:', elements.length, 'Visible:', elements.filter(':visible').length);
        if (elements.length > 0) {
            elements.each(function() {
                console.log('  Element:', this, 'Visible:', $(this).is(':visible'), 'Type:', this.type);
            });
        }
    });
    
    // Check all visible inputs on current page
    console.log('All visible inputs on current page:');
    $('input:visible, textarea:visible, select:visible').each(function() {
        const name = $(this).attr('name') || 'no-name';
        const id = $(this).attr('id') || 'no-id';
        console.log('  Input:', id, 'Name:', name, 'Type:', this.type);
    });
}


    // Enhanced result processing for process execution
    function handleEvaluateClick($button) {
        var formId = $button.data('form-id');
        var configId = $button.data('config-id');
        var originalText = $button.val();
        
        console.log('Enhanced evaluate button clicked for form:', formId, 'config:', configId);
        
        var configVar = 'operaton_config_' + formId;
        if (typeof window[configVar] === 'undefined') {
            console.error('Configuration not found for form:', formId);
            showError('Configuration error. Please contact the administrator.');
            return;
        }
        
        var config = window[configVar];
        var fieldMappings = config.field_mappings;
        
        console.log('Field mappings:', fieldMappings);
        console.log('Configuration:', config);
        
        // FORCE RADIO BUTTON SYNCHRONIZATION BEFORE VALIDATION
        forceSyncRadioButtons(formId);
        
        // Small delay to ensure sync is complete
        setTimeout(function() {
            continueEvaluation();
        }, 100);
        
        function continueEvaluation() {
            if (!validateForm(formId)) {
                showError('Please fill in all required fields before evaluation.');
                return;
            }
            
            // Collect form data (same as before)
            var formData = {};
            var hasRequiredData = true;
            var missingFields = [];
            
            // Collect all available data for ALL mapped fields
            $.each(fieldMappings, function(dmnVariable, mapping) {
                var fieldId = mapping.field_id;
                console.log('Processing variable:', dmnVariable, 'Field ID:', fieldId);
                
                var value = getGravityFieldValue(formId, fieldId);
                console.log('Found raw value for field', fieldId + ':', value);
                
                // Handle date field conversions
                if (dmnVariable.toLowerCase().indexOf('datum') !== -1 || 
                    dmnVariable.toLowerCase().indexOf('date') !== -1 ||
                    dmnVariable === 'dagVanAanvraag' ||
                    dmnVariable === 'geboortedatumAanvrager' ||
                    dmnVariable === 'geboortedatumPartner') {
                    
                    if (value !== null && value !== '' && value !== undefined) {
                        value = convertDateFormat(value, dmnVariable);
                    }
                }
                
                console.log('Processed value for', dmnVariable + ':', value);
                formData[dmnVariable] = value;
            });
            
            console.log('Collected raw form data:', formData);
            
            // Apply conditional logic for partner-related fields
            var isAlleenstaand = formData['aanvragerAlleenstaand'];
            console.log('User is single (alleenstaand):', isAlleenstaand);
            
            if (isAlleenstaand === 'true' || isAlleenstaand === true) {
                console.log('User is single, setting geboortedatumPartner to null');
                formData['geboortedatumPartner'] = null;
            }
            
            // Validate required fields (excluding conditionally optional ones)
            $.each(fieldMappings, function(dmnVariable, mapping) {
                var value = formData[dmnVariable];
                
                // Skip validation for partner fields when user is single
                if (isAlleenstaand === 'true' || isAlleenstaand === true) {
                    if (dmnVariable === 'geboortedatumPartner') {
                        console.log('Skipping validation for geboortedatumPartner (user is single)');
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
            
            console.log('Final form data after conditional logic:', formData);
            
            if (!hasRequiredData) {
                showError('Please fill in all required fields: ' + missingFields.join(', '));
                return;
            }
            
            // Show loading state
            $button.val('Evaluating...').prop('disabled', true);
            
        // CRITICAL FIX: Check if operaton_ajax is available before making call
        if (typeof window.operaton_ajax === 'undefined') {
            console.error('❌ operaton_ajax not available, cannot make AJAX call');
            showError('System error: AJAX configuration not loaded. Please refresh the page.');
            $button.val(originalText).prop('disabled', false);
            return;
        }
        
        console.log('Making AJAX call to:', window.operaton_ajax.url);


            // Make AJAX call to evaluate/execute process
            $.ajax({
            url: window.operaton_ajax.url, // Use window.operaton_ajax explicitly
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    config_id: configId,
                    form_data: formData
                }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', operaton_ajax.nonce);
                },
                success: function(response) {
                    console.log('AJAX success:', response);
                    // Call this function right before trying to populate results
                    // Add it to handleEvaluateClick function after the AJAX success
                    debugResultFields(formId);
                    if (response.success && response.results) {
                        console.log('Results received:', response.results);
                        
                        var populatedCount = 0;
                        var resultSummary = [];
                        
                        // Process each result
                        $.each(response.results, function(dmnResultField, resultData) {
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
                        
                        // NEW: Store process instance ID if provided
                        if (response.process_instance_id) {
                            storeProcessInstanceId(formId, response.process_instance_id);
                            console.log('Stored process instance ID:', response.process_instance_id);
                        }
                        
                        if (populatedCount > 0) {
                            var message = '✅ Results populated (' + populatedCount + '): ' + resultSummary.join(', ');
                            
                            // Add note about decision flow if process execution was used
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
                            processInstanceId: response.process_instance_id || null
                        };
                        
                        sessionStorage.setItem('operaton_dmn_eval_data_' + formId, JSON.stringify(evalData));
                        
                    } else {
                        console.error('Invalid response structure:', response);
                        showError('No results received from evaluation.');
                    }
                },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('XHR Status:', xhr.status);
                console.error('XHR Response:', xhr.responseText);
                
                var errorMessage = 'Error during evaluation. Please try again.';
                
                // Better error handling
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
                } else {
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.message) {
                            errorMessage = errorResponse.message;
                        }
                    } catch (e) {
                        errorMessage = 'HTTP ' + xhr.status + ': ' + error;
                    }
                }
                
                showError(errorMessage);
                },
                complete: function() {
                    $button.val(originalText).prop('disabled', false);
                }
            });
        }
    }

    // NEW: Store process instance ID for decision flow retrieval
    function storeProcessInstanceId(formId, processInstanceId) {
        sessionStorage.setItem('operaton_process_' + formId, processInstanceId);
        
        // Also store in a global variable for immediate access
        window['operaton_process_' + formId] = processInstanceId;
        
        console.log('Stored process instance ID for form', formId + ':', processInstanceId);
    }

    // NEW: Get stored process instance ID
    function getStoredProcessInstanceId(formId) {
        // Try global variable first
        if (window['operaton_process_' + formId]) {
            return window['operaton_process_' + formId];
        }
        
        // Try session storage
        var processId = sessionStorage.getItem('operaton_process_' + formId);
        if (processId) {
            return processId;
        }
        
        // Try to get from stored evaluation data
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
        
        return null;
    }

    // Enhanced clear function that also clears process data
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
        
        // Clear stored results including process data
        clearStoredResults(formId);
    }

    // Enhanced clear stored results function
    function clearStoredResults(formId) {
        sessionStorage.removeItem('operaton_dmn_result_' + formId);
        sessionStorage.removeItem('operaton_dmn_eval_page_' + formId);
        sessionStorage.removeItem('operaton_dmn_data_' + formId);
        sessionStorage.removeItem('operaton_dmn_eval_data_' + formId);
        
        // NEW: Clear process instance data
        sessionStorage.removeItem('operaton_process_' + formId);
        delete window['operaton_process_' + formId];
        
        console.log('Cleared all stored results and process data for form:', formId);
    }

    // All other existing functions remain the same...
    // (bindEvaluationEvents, getCurrentPage, convertDateFormat, findFieldOnCurrentPage, 
    //  findResultFieldOnCurrentPage, getGravityFieldValue, forceSyncRadioButtons, etc.)

    function bindEvaluationEvents(formId) {
        var selector = '.operaton-evaluate-btn[data-form-id="' + formId + '"]';
        console.log('Binding events for selector:', selector);
        
        $(document).off('click', selector);
        $(document).on('click', selector, function(e) {
            e.preventDefault();
            console.log('Button clicked!', this);
            handleEvaluateClick($(this));
        });
        
        console.log('Event handler bound for form:', formId);
    }

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

    function bindNavigationEvents(formId) {
        var $form = $('#gform_' + formId);
        
        $form.off('click.operaton-nav');
        $form.on('click.operaton-nav', '.gform_previous_button, input[value="Previous"], button:contains("Previous")', function() {
            console.log('Previous button clicked for form:', formId);
            clearResultFieldWithMessage(formId, 'Previous button clicked');
        });
        
        if (typeof gform !== 'undefined') {
            if (gform.removeAction) {
                gform.removeAction('gform_page_loaded', 'operaton_clear_' + formId);
            }
            
            gform.addAction('gform_page_loaded', function(loadedFormId, currentPage) {
                if (loadedFormId == formId) {
                    console.log('Form page loaded for form:', formId, 'page:', currentPage);
                    setTimeout(function() {
                        clearResultFieldWithMessage(formId, 'Page loaded: ' + currentPage);
                    }, 300);
                }
            }, 10, 'operaton_clear_' + formId);
        }
        
        if (typeof gform !== 'undefined') {
            gform.addAction('gform_post_render', function(loadedFormId) {
                if (loadedFormId == formId) {
                    console.log('Form post render for form:', formId);
                    setTimeout(function() {
                        clearResultFieldWithMessage(formId, 'Form post render');
                    }, 500);
                }
            }, 10, 'operaton_clear_render_' + formId);
        }
    }

    function bindInputChangeListeners(formId) {
        var $form = $('#gform_' + formId);
        var configVar = 'operaton_config_' + formId;
        var config = window[configVar];
        
        if (!config || !config.field_mappings) return;
        
        console.log('Binding input change listeners for form:', formId);
        
        $.each(config.field_mappings, function(dmnVariable, mapping) {
            var fieldId = mapping.field_id;
            var selectors = [
                '#input_' + formId + '_' + fieldId,
                'input[name="input_' + formId + '_' + fieldId + '"]',
                'select[name="input_' + formId + '_' + fieldId + '"]',
                'input[name="input_' + fieldId + '"]'
            ];
            
            $.each(selectors, function(index, selector) {
                $form.off('change.operaton', selector);
                $form.on('change.operaton', selector, function() {
                    console.log('Input field changed:', selector, 'New value:', $(this).val());
                    
                    setTimeout(function() {
                        clearResultFieldWithMessage(formId, 'Input changed - result cleared');
                    }, 100);
                });
            });
        });
    }

    // Include all other existing helper functions...
    function convertDateFormat(dateStr, fieldName) {
        if (!dateStr || dateStr === null) {
            return null;
        }
        
        console.log('Converting date for field:', fieldName, 'Input:', dateStr);
        
        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
            console.log('Date already in ISO format:', dateStr);
            return dateStr;
        }
        
        if (/^\d{2}-\d{2}-\d{4}$/.test(dateStr)) {
            var parts = dateStr.split('-');
            var day = parts[0];
            var month = parts[1];
            var year = parts[2];
            var isoDate = year + '-' + month + '-' + day;
            console.log('Converted DD-MM-YYYY to ISO:', dateStr, '->', isoDate);
            return isoDate;
        }
        
        if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
            var parts = dateStr.split('/');
            var month = parts[0];
            var day = parts[1];
            var year = parts[2];
            var isoDate = year + '-' + month + '-' + day;
            console.log('Converted MM/DD/YYYY to ISO:', dateStr, '->', isoDate);
            return isoDate;
        }
        
        try {
            var date = new Date(dateStr);
            if (!isNaN(date.getTime())) {
                var isoDate = date.getFullYear() + '-' + 
                             String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                             String(date.getDate()).padStart(2, '0');
                console.log('Converted via Date parsing:', dateStr, '->', isoDate);
                return isoDate;
            }
        } catch (e) {
            console.error('Error parsing date:', dateStr, e);
        }
        
        console.warn('Could not convert date format for:', dateStr);
        return dateStr;
    }

    function findFieldOnCurrentPage(formId, fieldId) {
        console.log('Searching for field ID', fieldId, 'on current page of form:', formId);
        
        var $form = $('#gform_' + formId);
        
        var selectors = [
            '#input_' + formId + '_' + fieldId,
            'input[name="input_' + formId + '_' + fieldId + '"]',
            'select[name="input_' + formId + '_' + fieldId + '"]',
            'textarea[name="input_' + formId + '_' + fieldId + '"]'
        ];
        
        for (var i = 0; i < selectors.length; i++) {
            var $field = $form.find(selectors[i] + ':visible');
            if ($field.length > 0) {
                console.log('Found field using selector:', selectors[i]);
                return $field.first();
            }
        }
        
        console.log('No field found with ID:', fieldId);
        return null;
    }

    function findResultFieldOnCurrentPage(formId) {
        console.log('Searching for result field on current page of form:', formId);
        
        var $form = $('#gform_' + formId);
        var configVar = 'operaton_config_' + formId;
        var config = window[configVar];
        
        var currentPage = getCurrentPage(formId);
        var evalPage = sessionStorage.getItem('operaton_dmn_eval_page_' + formId);
        
        if (evalPage && parseInt(evalPage) !== currentPage) {
            console.log('Different page than evaluation page, may need to clear result');
        }
        
        if (config && config.result_display_field) {
            console.log('Looking for configured result field ID:', config.result_display_field);
            
            var selectors = [
                '#input_' + formId + '_' + config.result_display_field,
                'input[name="input_' + formId + '_' + config.result_display_field + '"]',
                'select[name="input_' + formId + '_' + config.result_display_field + '"]',
                'textarea[name="input_' + formId + '_' + config.result_display_field + '"]'
            ];
            
            for (var i = 0; i < selectors.length; i++) {
                var $field = $form.find(selectors[i] + ':visible');
                if ($field.length > 0) {
                    console.log('Found configured result field:', $field);
                    return $field.first();
                }
            }
        }
        
        var detectionStrategies = [
            function() {
                return $form.find('label:visible').filter(function() {
                    var text = $(this).text().toLowerCase().trim();
                    return text === 'desired dish' || text === 'result' || text === 'desireddish';
                }).closest('.gfield').find('input:visible, select:visible, textarea:visible').first();
            },
            
            function() {
                return $form.find('label:visible').filter(function() {
                    var text = $(this).text().toLowerCase();
                    return (text.indexOf('desired') !== -1 && text.indexOf('dish') !== -1) ||
                           text.indexOf('result') !== -1;
                }).closest('.gfield').find('input:visible, select:visible, textarea:visible').first();
            },
            
            function() {
                return $form.find('input:visible[name*="dish"], input:visible[id*="dish"], select:visible[name*="dish"], select:visible[id*="dish"], textarea:visible[name*="dish"], textarea:visible[id*="dish"]').first();
            },
            
            function() {
                return $form.find('input:visible[name*="result"], input:visible[id*="result"], select:visible[name*="result"], select:visible[id*="result"], textarea:visible[name*="result"], textarea:visible[id*="result"]').first();
            }
        ];
        
        for (var i = 0; i < detectionStrategies.length; i++) {
            var $field = detectionStrategies[i]();
            if ($field && $field.length > 0) {
                console.log('Found result field using detection strategy', (i + 1), ':', $field);
                return $field;
            }
        }
        
        console.log('No result field found on current page');
        return null;
    }

    function getGravityFieldValue(formId, fieldId) {
        console.log('Getting value for form:', formId, 'field:', fieldId);
        
        var $form = $('#gform_' + formId);
        var value = null;
        
        var standardSelectors = [
            '#input_' + formId + '_' + fieldId,
            'input[name="input_' + formId + '_' + fieldId + '"]',
            'select[name="input_' + formId + '_' + fieldId + '"]',
            'textarea[name="input_' + formId + '_' + fieldId + '"]'
        ];
        
        for (var i = 0; i < standardSelectors.length; i++) {
            var $field = $form.find(standardSelectors[i]);
            if ($field.length > 0) {
                value = getFieldValue($field);
                if (value !== null && value !== '') {
                    console.log('Found value using standard selector:', standardSelectors[i], 'Value:', value);
                    return value;
                }
            }
        }
        
        value = findCustomRadioValue(formId, fieldId);
        if (value !== null) {
            console.log('Found value from custom radio detection:', value);
            return value;
        }
        
        var $radioChecked = $form.find('input[name="input_' + fieldId + '"]:checked');
        if ($radioChecked.length > 0) {
            value = $radioChecked.val();
            console.log('Found standard radio button value:', value);
            return value;
        }
        
        var $checkboxChecked = $form.find('input[name^="input_' + fieldId + '"]:checked');
        if ($checkboxChecked.length > 0) {
            var checkboxValues = [];
            $checkboxChecked.each(function() {
                checkboxValues.push($(this).val());
            });
            value = checkboxValues.length === 1 ? checkboxValues[0] : checkboxValues.join(',');
            console.log('Found checkbox value(s):', value);
            return value;
        }
        
        console.log('No value found for field:', fieldId);
        return null;
    }

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
                        console.log('Found custom radio value for field', fieldId, 'using name', radioName + ':', value);
                        
                        if ($hiddenField.val() !== value) {
                            console.log('Syncing custom radio value to hidden field');
                            $hiddenField.val(value);
                            $hiddenField.trigger('change');
                        }
                        
                        return value;
                    }
                }
            }
        }
        
        var configVar = 'operaton_config_' + formId;
        if (typeof window[configVar] !== 'undefined') {
            var config = window[configVar];
            if (config.field_mappings) {
                var targetDmnVariable = null;
                $.each(config.field_mappings, function(dmnVariable, mapping) {
                    if (mapping.field_id == fieldId) {
                        targetDmnVariable = dmnVariable;
                        return false;
                    }
                });
                
                if (targetDmnVariable) {
                    var $radioChecked = $('input[name="' + targetDmnVariable + '"]:checked');
                    if ($radioChecked.length > 0) {
                        var value = $radioChecked.val();
                        console.log('Found custom radio value using DMN variable name', targetDmnVariable + ':', value);
                        
                        var $hiddenField = $form.find('#input_' + formId + '_' + fieldId);
                        if ($hiddenField.length > 0 && $hiddenField.val() !== value) {
                            console.log('Syncing DMN variable radio value to hidden field');
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

    function forceSyncRadioButtons(formId) {
        console.log('Forcing generic radio button synchronization for form:', formId);
        
        var $form = $('#gform_' + formId);
        var configVar = 'operaton_config_' + formId;
        
        if (typeof window[configVar] === 'undefined') {
            console.log('No configuration found, skipping radio sync');
            return;
        }
        
        var config = window[configVar];
        if (!config.field_mappings) {
            console.log('No field mappings found, skipping radio sync');
            return;
        }
        
        $.each(config.field_mappings, function(dmnVariable, mapping) {
            var fieldId = mapping.field_id;
            var $hiddenField = $form.find('#input_' + formId + '_' + fieldId);
            
            if ($hiddenField.length > 0) {
                var $radioChecked = $('input[name="' + dmnVariable + '"]:checked');
                if ($radioChecked.length > 0) {
                    var radioValue = $radioChecked.val();
                    var hiddenValue = $hiddenField.val();
                    
                    if (radioValue !== hiddenValue) {
                        console.log('Syncing radio to hidden for DMN variable', dmnVariable + ':', radioValue, '-> field', fieldId);
                        $hiddenField.val(radioValue);
                        $hiddenField.trigger('change');
                    }
                }
            }
        });
        
        $form.find('input[type="radio"]:checked').each(function() {
            var $radio = $(this);
            var radioName = $radio.attr('name');
            var radioValue = $radio.val();
            
            if (radioName && radioName.indexOf('input_') !== 0) {
                console.log('Found custom radio button:', radioName, '=', radioValue);
                
                var correspondingFieldId = findFieldIdForRadioName(formId, radioName);
                if (correspondingFieldId) {
                    var $hiddenField = $form.find('#input_' + formId + '_' + correspondingFieldId);
                    if ($hiddenField.length > 0 && $hiddenField.val() !== radioValue) {
                        console.log('Syncing custom radio', radioName, 'to hidden field', correspondingFieldId);
                        $hiddenField.val(radioValue);
                        $hiddenField.trigger('change');
                    }
                }
            }
        });
    }

    function findFieldIdForRadioName(formId, radioName) {
        var configVar = 'operaton_config_' + formId;
        if (typeof window[configVar] !== 'undefined') {
            var config = window[configVar];
            if (config.field_mappings) {
                if (config.field_mappings[radioName]) {
                    return config.field_mappings[radioName].field_id;
                }
            }
        }
        return null;
    }

    function getFieldValue($field) {
        if ($field.length === 0) return null;
        
        var tagName = $field.prop('tagName').toLowerCase();
        var fieldType = $field.attr('type');
        
        if (tagName === 'select') {
            return $field.val();
        } else if (fieldType === 'checkbox' || fieldType === 'radio') {
            return $field.is(':checked') ? $field.val() : null;
        } else if (tagName === 'textarea' || fieldType === 'text' || fieldType === 'email' || fieldType === 'number' || fieldType === 'hidden') {
            var val = $field.val();
            return val && val.trim() !== '' ? val : null;
        }
        
        return $field.val();
    }

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

    function validateForm(formId) {
        if (typeof gform !== 'undefined' && gform.validators && gform.validators[formId]) {
            var isValid = gform.validators[formId]();
            console.log('Gravity Forms validation result:', isValid);
            return isValid;
        }
        
        var $form = $('#gform_' + formId);
        var allValid = true;
        
        $form.find('.gfield_contains_required input, .gfield_contains_required select, .gfield_contains_required textarea').each(function() {
            var $field = $(this);
            var value = getFieldValue($field);
            
            if (!value || value.trim() === '') {
                console.log('Required field is empty:', $field.attr('name'));
                allValid = false;
                return false;
            }
        });
        
        return allValid;
    }

    function showSuccessNotification(message) {
        console.log('Success:', message);
        
        $('.operaton-notification').remove();
        
        var $notification = $('<div class="operaton-notification">' + message + '</div>');
        $notification.css({
            'position': 'fixed',
            'top': '20px',
            'right': '20px',
            'background': '#4CAF50',
            'color': 'white',
            'padding': '15px 20px',
            'border-radius': '6px',
            'box-shadow': '0 3px 15px rgba(0,0,0,0.2)',
            'z-index': 99999,
            'font-family': 'Arial, sans-serif',
            'font-size': '14px',
            'font-weight': 'bold',
            'max-width': '400px',
            'white-space': 'pre-line'
        });
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 6000);
    }

    function showError(message) {
        console.error('Operaton DMN Error:', message);
        
        $('.operaton-notification').remove();
        
        var $notification = $('<div class="operaton-notification">❌ ' + message + '</div>');
        $notification.css({
            'position': 'fixed',
            'top': '20px',
            'right': '20px',
            'background': '#f44336',
            'color': 'white',
            'padding': '15px 20px',
            'border-radius': '6px',
            'box-shadow': '0 3px 15px rgba(0,0,0,0.2)',
            'z-index': 99999,
            'font-family': 'Arial, sans-serif',
            'font-size': '14px',
            'font-weight': 'bold',
            'max-width': '400px'
        });
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 8000);
    }

    function highlightField($field) {
        if ($field && $field.length > 0) {
            var originalBackground = $field.css('background-color');
            var originalBorder = $field.css('border');
            
            $field.css({
                'background-color': '#e8f5e8',
                'border': '2px solid #4CAF50',
                'transition': 'all 0.3s ease'
            });
            
            $('html, body').animate({
                scrollTop: $field.offset().top - 100
            }, 500);
            
            setTimeout(function() {
                $field.css({
                    'background-color': originalBackground,
                    'border': originalBorder
                });
            }, 3000);
        }
    }

    // Initialize the plugin
    console.log('Initializing Enhanced Operaton DMN...');
    initOperatonDMN();
    
    // Bind events for existing forms
    $('form[id^="gform_"]').each(function() {
        var formId = $(this).attr('id').replace('gform_', '');
        initializeFormEvaluation(formId);
    });
    
    console.log('Enhanced Operaton DMN frontend script initialization complete');

    // FIXED: Better cleanup on page unload
    $(window).on('beforeunload', function() {
        window.operatonDmnInitialized = false;
        // Clear form initialization flags
        for (var key in window) {
            if (key.startsWith('operaton_form_') && key.endsWith('_initialized')) {
                delete window[key];
            }
        }
    });

});