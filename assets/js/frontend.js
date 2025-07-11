// Complete simplified frontend.js for current page result population
console.log('Operaton DMN frontend script loading...');

jQuery(document).ready(function($) {
    console.log('Operaton DMN frontend script loaded - current page population mode');
    
    // Initialize evaluation for forms
    var initOperatonDMN = function() {
        if (typeof gform !== 'undefined' && gform.initializeOnLoaded) {
            gform.addAction('gform_post_render', function(formId) {
                console.log('Gravity Form rendered, initializing Operaton DMN for form:', formId);
                initializeFormEvaluation(formId);
            });
        } else {
            setTimeout(function() {
                $('form[id^="gform_"]').each(function() {
                    var formId = $(this).attr('id').replace('gform_', '');
                    initializeFormEvaluation(formId);
                });
            }, 500);
        }
    };

// Clear result field when navigating back
function clearResultField(formId) {
    console.log('Clearing result field for form:', formId);
    
    var $resultField = findResultFieldOnCurrentPage(formId);
    if ($resultField && $resultField.length > 0) {
        // Only clear if it contains a result value
        var currentValue = $resultField.val();
        if (currentValue && currentValue.trim() !== '') {
            console.log('Clearing result field value:', currentValue);
            $resultField.val('');
            $resultField.trigger('change');
            $resultField.trigger('input');
        }
    }
    
    // Also clear stored results
    sessionStorage.removeItem('operaton_dmn_result_' + formId);
    delete window['operaton_dmn_result_' + formId];
}

// Enhanced navigation event binding
function bindNavigationEvents(formId) {
    var $form = $('#gform_' + formId);
    
    // Listen for Previous button clicks
    $form.off('click.operaton-nav'); // Remove existing listeners
    $form.on('click.operaton-nav', '.gform_previous_button, input[value="Previous"], button:contains("Previous")', function() {
        console.log('Previous button clicked for form:', formId);
        clearResultFieldWithMessage(formId, 'Previous button clicked');
    });
    
    // Listen for form page changes via Gravity Forms events
    if (typeof gform !== 'undefined') {
        // Remove existing action first
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
    
    // Also clear when the page is fully rendered
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

// Enhanced form initialization that includes input monitoring
function initializeFormEvaluation(formId) {
    var configVar = 'operaton_config_' + formId;
    if (typeof window[configVar] !== 'undefined') {
        console.log('Configuration found for form:', formId);
        bindEvaluationEvents(formId);
        bindNavigationEvents(formId);
        bindInputChangeListeners(formId); // ADD THIS
        
        // Clear any existing results when form initializes
        setTimeout(function() {
            clearResultFieldWithMessage(formId, 'Form initialized');
        }, 200);
    }
}

// Helper function to get current page number
function getCurrentPage(formId) {
    // Try to get from URL first
    var urlParams = new URLSearchParams(window.location.search);
    var pageParam = urlParams.get('gf_page');
    if (pageParam) {
        return parseInt(pageParam);
    }
    
    // Try to get from hidden form field
    var $form = $('#gform_' + formId);
    var $pageField = $form.find('input[name="gform_source_page_number_' + formId + '"]');
    if ($pageField.length > 0) {
        return parseInt($pageField.val()) || 1;
    }
    
    // Default to page 1
    return 1;
}

// Clear result field when form inputs change (indicates user is making new choices)
function bindInputChangeListeners(formId) {
    var $form = $('#gform_' + formId);
    var configVar = 'operaton_config_' + formId;
    var config = window[configVar];
    
    if (!config || !config.field_mappings) return;
    
    console.log('Binding input change listeners for form:', formId);
    
    // Listen for changes to any of the mapped input fields
    $.each(config.field_mappings, function(dmnVariable, mapping) {
        var fieldId = mapping.field_id;
        var selectors = [
            '#input_' + formId + '_' + fieldId,
            'input[name="input_' + formId + '_' + fieldId + '"]',
            'select[name="input_' + formId + '_' + fieldId + '"]',
            'input[name="input_' + fieldId + '"]' // For radio buttons
        ];
        
        // Bind change events to these fields
        $.each(selectors, function(index, selector) {
            $form.off('change.operaton', selector); // Remove existing listeners
            $form.on('change.operaton', selector, function() {
                console.log('Input field changed:', selector, 'New value:', $(this).val());
                
                // Clear the result field since inputs have changed
                setTimeout(function() {
                    clearResultFieldWithMessage(formId, 'Input changed - result cleared');
                }, 100);
            });
        });
    });
}

// Enhanced clear function with better logging
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
            
            // Also clear any Gravity Forms internal storage
            if (typeof gformInitSpinner !== 'undefined') {
                // Clear from Gravity Forms field storage if possible
                $resultField[0].defaultValue = '';
            }
        }
    }
    
    // Clear stored results
    clearStoredResults(formId);
}

// Clear all stored results for a form
function clearStoredResults(formId) {
    sessionStorage.removeItem('operaton_dmn_result_' + formId);
    sessionStorage.removeItem('operaton_dmn_eval_page_' + formId);
    sessionStorage.removeItem('operaton_dmn_data_' + formId);
    delete window['operaton_dmn_result_' + formId];
    
    console.log('Cleared all stored results for form:', formId);
}

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
    
// Update the success handler to better track evaluation state
function handleEvaluateClick($button) {
    var formId = $button.data('form-id');
    var configId = $button.data('config-id');
    var originalText = $button.val();
    
    console.log('Evaluate button clicked for form:', formId, 'config:', configId);
    
    var configVar = 'operaton_config_' + formId;
    if (typeof window[configVar] === 'undefined') {
        console.error('Configuration not found for form:', formId);
        showError('Configuration error. Please contact the administrator.');
        return;
    }
    
    var config = window[configVar];
    var fieldMappings = config.field_mappings;
    
    console.log('Field mappings:', fieldMappings);
    
    if (!validateForm(formId)) {
        showError('Please fill in all required fields before evaluation.');
        return;
    }
    
    // Collect form data
    var formData = {};
    var hasRequiredData = true;
    var missingFields = [];
    
    $.each(fieldMappings, function(dmnVariable, mapping) {
        var fieldId = mapping.field_id;
        console.log('Processing DMN variable:', dmnVariable, 'Field ID:', fieldId);
        
        var value = getGravityFieldValue(formId, fieldId);
        console.log('Found value for field', fieldId + ':', value);
        
        if (value === null || value === '' || value === undefined) {
            hasRequiredData = false;
            missingFields.push(dmnVariable + ' (field ID: ' + fieldId + ')');
        } else {
            if (!validateFieldType(value, mapping.type)) {
                showError('Invalid data type for field ' + dmnVariable + '. Expected: ' + mapping.type);
                return false;
            }
            formData[dmnVariable] = value;
        }
    });
    
    console.log('Collected form data:', formData);
    
    if (!hasRequiredData) {
        showError('Please fill in all required fields: ' + missingFields.join(', '));
        return;
    }
    
    // Show loading state
    $button.val('Evaluating...').prop('disabled', true);
    
    console.log('Making AJAX call to:', operaton_ajax.url);
    
    // Make AJAX call to evaluate
    $.ajax({
        url: operaton_ajax.url,
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
console.log('=== DEBUG: Starting result population ===');
console.log('Response success:', response.success);
console.log('Response result:', response.result);
console.log('Result defined?', response.result !== undefined);
console.log('Result not null?', response.result !== null);

if (response.success && response.result !== undefined && response.result !== null) {
    console.log('=== DEBUG: Conditions met, proceeding ===');
    
    // Store evaluation metadata
    var currentPage = getCurrentPage(formId);
    console.log('Current page:', currentPage);
    
    var evalData = {
        result: response.result,
        page: currentPage,
        timestamp: Date.now(),
        formData: formData
    };
    
    sessionStorage.setItem('operaton_dmn_eval_data_' + formId, JSON.stringify(evalData));
    console.log('Stored evaluation data:', evalData);
    
    // Try to populate result field on current page
    console.log('=== DEBUG: About to search for result field ===');
    var $resultField = findResultFieldOnCurrentPage(formId);
    console.log('=== DEBUG: Result field search returned:', $resultField);
    console.log('=== DEBUG: Result field length:', $resultField ? $resultField.length : 'null/undefined');
    
    if ($resultField && $resultField.length > 0) {
        console.log('=== DEBUG: Found result field, populating ===');
        console.log('Field element:', $resultField[0]);
        console.log('Field current value before population:', $resultField.val());
        
        // Populate the field
        $resultField.val(response.result);
        console.log('Field value after setting:', $resultField.val());
        
        $resultField.trigger('change');
        $resultField.trigger('input');
        console.log('Triggers fired');
        
        // Show success notification
        console.log('=== DEBUG: About to show notification ===');
        showSuccessNotification('✅ Result populated: ' + response.result);
        
        // Highlight the field briefly
        console.log('=== DEBUG: About to highlight field ===');
        highlightField($resultField);
        
    } else {
        console.log('=== DEBUG: No result field found ===');
        showError('No result field found on this page. Please add a field to receive the evaluation result.');
    }
    
} else {
    console.log('=== DEBUG: Conditions NOT met ===');
    console.log('response.success:', response.success);
    console.log('response.result:', response.result);
    showError('No result received from evaluation.');
}
        },

                error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                var errorMessage = 'Error during evaluation. Please try again.';
                
                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.message) {
                        errorMessage = errorResponse.message;
                    }
                } catch (e) {
                    if (xhr.status === 0) {
                        errorMessage = 'Connection error. Please check your internet connection.';
                    } else if (xhr.status === 404) {
                        errorMessage = 'Evaluation service not found.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error occurred during evaluation.';
                    }
                }
                
                showError(errorMessage);
            },
            complete: function() {
                $button.val(originalText).prop('disabled', false);
            }
        });
    }
    
    // Simplified result field finder for current page only
    function findResultFieldOnCurrentPage(formId) {
        console.log('Searching for result field on current page of form:', formId);
        
    var $form = $('#gform_' + formId);
    var configVar = 'operaton_config_' + formId;
    var config = window[configVar];
    
    // Check if we're on the same page where evaluation happened
    var currentPage = getCurrentPage(formId);
    var evalPage = sessionStorage.getItem('operaton_dmn_eval_page_' + formId);
    
    if (evalPage && parseInt(evalPage) !== currentPage) {
        console.log('Different page than evaluation page, may need to clear result');
    }
        
        // Strategy 1: Use configured result display field if specified
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
        
        // Strategy 2: Auto-detect by field label (only visible fields on current page)
        var detectionStrategies = [
            // Look for exact matches first
            function() {
                return $form.find('label:visible').filter(function() {
                    var text = $(this).text().toLowerCase().trim();
                    return text === 'desired dish' || text === 'result' || text === 'desireddish';
                }).closest('.gfield').find('input:visible, select:visible, textarea:visible').first();
            },
            
            // Look for partial matches
            function() {
                return $form.find('label:visible').filter(function() {
                    var text = $(this).text().toLowerCase();
                    return (text.indexOf('desired') !== -1 && text.indexOf('dish') !== -1) ||
                           text.indexOf('result') !== -1;
                }).closest('.gfield').find('input:visible, select:visible, textarea:visible').first();
            },
            
            // Look for fields with "dish" in the name/id
            function() {
                return $form.find('input:visible[name*="dish"], input:visible[id*="dish"], select:visible[name*="dish"], select:visible[id*="dish"], textarea:visible[name*="dish"], textarea:visible[id*="dish"]').first();
            },
            
            // Look for fields with "result" in the name/id
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
    
    // Enhanced field detection for Gravity Forms
    function getGravityFieldValue(formId, fieldId) {
        console.log('Getting value for form:', formId, 'field:', fieldId);
        
        var $form = $('#gform_' + formId);
        var value = null;
        
        // Handle radio buttons
        var $radioChecked = $form.find('input[name="input_' + fieldId + '"]:checked');
        if ($radioChecked.length > 0) {
            value = $radioChecked.val();
            console.log('Found radio button value:', value);
            return value;
        }
        
        // Handle checkboxes
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
        
        // Direct field selectors
        var selectors = [
            '#input_' + formId + '_' + fieldId,
            'input[name="input_' + formId + '_' + fieldId + '"]',
            'select[name="input_' + formId + '_' + fieldId + '"]',
            'textarea[name="input_' + formId + '_' + fieldId + '"]'
        ];
        
        for (var i = 0; i < selectors.length; i++) {
            var $field = $form.find(selectors[i]);
            if ($field.length > 0) {
                value = getFieldValue($field);
                if (value !== null && value !== '') {
                    console.log('Found value using selector:', selectors[i], 'Value:', value);
                    return value;
                }
            }
        }
        
        console.log('No value found for field:', fieldId);
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
    
    // Enhanced notification functions
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
            'max-width': '400px'
        });
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
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
        }, 6000);
    }
    
    // Highlight field to draw attention
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
    console.log('Initializing Operaton DMN...');
    initOperatonDMN();
    
    // Bind events for existing forms
    $('form[id^="gform_"]').each(function() {
        var formId = $(this).attr('id').replace('gform_', '');
        initializeFormEvaluation(formId);
    });
    
    console.log('Operaton DMN frontend script initialization complete');
});