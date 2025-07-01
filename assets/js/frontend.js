// assets/js/frontend.js - IMPROVED VERSION
jQuery(document).ready(function($) {
    console.log('Operaton DMN frontend script loaded');
    
    // Wait for Gravity Forms to fully initialize
    var initOperatonDMN = function() {
        if (typeof gform !== 'undefined' && gform.initializeOnLoaded) {
            gform.addAction('gform_post_render', function(formId) {
                console.log('Gravity Form rendered, initializing Operaton DMN for form:', formId);
                initializeFormEvaluation(formId);
            });
        } else {
            // Fallback for when gform is not available
            setTimeout(function() {
                $('form[id^="gform_"]').each(function() {
                    var formId = $(this).attr('id').replace('gform_', '');
                    initializeFormEvaluation(formId);
                });
            }, 500);
        }
    };
    
    // Initialize evaluation for a specific form
    function initializeFormEvaluation(formId) {
        var configVar = 'operaton_config_' + formId;
        if (typeof window[configVar] !== 'undefined') {
            console.log('Configuration found for form:', formId);
            
            // Re-bind event handlers for this form
            bindEvaluationEvents(formId);
        }
    }
    
    // Bind evaluation events for a specific form
    function bindEvaluationEvents(formId) {
        var selector = '.operaton-evaluate-btn[data-form-id="' + formId + '"]';
        
        // Remove existing handlers to prevent duplicates
        $(document).off('click', selector);
        
        // Bind new handler
        $(document).on('click', selector, function(e) {
            e.preventDefault();
            handleEvaluateClick($(this));
        });
    }
    
    // Handle evaluate button clicks
    function handleEvaluateClick($button) {
        var formId = $button.data('form-id');
        var configId = $button.data('config-id');
        var originalText = $button.val();
        
        console.log('Evaluate button clicked for form:', formId, 'config:', configId);
        
        // Get the configuration for this form
        var configVar = 'operaton_config_' + formId;
        if (typeof window[configVar] === 'undefined') {
            console.error('Configuration not found for form:', formId);
            showError('Configuration error. Please contact the administrator.');
            return;
        }
        
        var config = window[configVar];
        var fieldMappings = config.field_mappings;
        
        console.log('Field mappings:', fieldMappings);
        
        // Validate form before collecting data
        if (!validateForm(formId)) {
            showError('Please fill in all required fields before evaluation.');
            return;
        }
        
        // Collect form data based on field mappings
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
                // Validate data type
                if (!validateFieldType(value, mapping.type)) {
                    showError('Invalid data type for field ' + dmnVariable + '. Expected: ' + mapping.type);
                    return false;
                }
                formData[dmnVariable] = value;
            }
        });
        
        console.log('Collected form data:', formData);
        console.log('Missing fields:', missingFields);
        
        // Validate that we have all required data
        if (!hasRequiredData) {
            showError('Please fill in all required fields: ' + missingFields.join(', '));
            return;
        }
        
        // Show loading state
        $button.val('Evaluating...').prop('disabled', true);
        hideResult(formId);
        showLoading(formId);
        
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
                hideLoading(formId);
                
                if (response.success && response.result !== undefined && response.result !== null) {
                    showResult(formId, response.result);
                } else {
                    showError('No result received from evaluation.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                hideLoading(formId);
                
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
    
    // Enhanced field detection for Gravity Forms
    function getGravityFieldValue(formId, fieldId) {
        console.log('Getting value for form:', formId, 'field:', fieldId);
        
        var $form = $('#gform_' + formId);
        var value = null;
        
        // Strategy 1: Direct field selectors (most common)
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
        
        // Strategy 2: Handle multi-part fields (name, address, etc.)
        var $multiFields = $form.find('[id^="input_' + formId + '_' + fieldId + '_"]');
        if ($multiFields.length > 0) {
            var multiValue = {};
            $multiFields.each(function() {
                var $this = $(this);
                var subFieldId = $this.attr('id').split('_').pop();
                var subValue = getFieldValue($this);
                if (subValue) {
                    multiValue[subFieldId] = subValue;
                }
            });
            
            if (Object.keys(multiValue).length > 0) {
                console.log('Found multi-part field value:', multiValue);
                return JSON.stringify(multiValue);
            }
        }
        
        // Strategy 3: Handle radio buttons and checkboxes
        var $radioChecked = $form.find('input[name="input_' + formId + '_' + fieldId + '"]:checked');
        if ($radioChecked.length > 0) {
            value = $radioChecked.val();
            console.log('Found radio/checkbox value:', value);
            return value;
        }
        
        // Strategy 4: Handle file uploads
        var $fileField = $form.find('input[name="input_' + formId + '_' + fieldId + '"]');
        if ($fileField.length > 0 && $fileField.attr('type') === 'file') {
            var files = $fileField[0].files;
            if (files && files.length > 0) {
                return files[0].name;
            }
        }
        
        // Strategy 5: Fallback - search by partial ID match
        var $possibleFields = $form.find('input, select, textarea').filter(function() {
            var id = $(this).attr('id') || '';
            var name = $(this).attr('name') || '';
            return id.indexOf('_' + fieldId + '_') > -1 || 
                   id.indexOf('_' + fieldId) > -1 || 
                   name.indexOf('_' + fieldId + '_') > -1 || 
                   name.indexOf('_' + fieldId) > -1;
        });
        
        if ($possibleFields.length > 0) {
            value = getFieldValue($possibleFields.first());
            if (value !== null && value !== '') {
                console.log('Found value using fallback search:', value);
                return value;
            }
        }
        
        console.log('No value found for field:', fieldId);
        return null;
    }
    
    // Get value from a jQuery field object
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
    
    // Validate field type
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
                return true; // Any value can be a string
        }
    }
    
    // Validate form using Gravity Forms built-in validation
    function validateForm(formId) {
        if (typeof gform !== 'undefined' && gform.validators && gform.validators[formId]) {
            // Use Gravity Forms built-in validation
            var isValid = gform.validators[formId]();
            console.log('Gravity Forms validation result:', isValid);
            return isValid;
        }
        
        // Fallback validation - check required fields
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
    
    // Show result
    function showResult(formId, result) {
        var $resultContainer = $('#operaton-result-' + formId);
        $resultContainer.find('.result-content').html('<strong>' + escapeHtml(result) + '</strong>');
        $resultContainer.fadeIn(200);
    }
    
    // Hide result
    function hideResult(formId) {
        $('#operaton-result-' + formId).hide();
    }
    
    // Show loading indicator
    function showLoading(formId) {
        var $resultContainer = $('#operaton-result-' + formId);
        $resultContainer.find('.result-content').html('<span class="operaton-spinner"></span> Evaluating...');
        $resultContainer.show();
    }
    
    // Hide loading indicator
    function hideLoading(formId) {
        // Loading will be replaced by result or hidden by hideResult
    }
    
    // Show error message
    function showError(message) {
        if (typeof console !== 'undefined') {
            console.error('Operaton DMN Error:', message);
        }
        alert(message);
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Debug functions for development
    if (operaton_ajax.debug) {
        window.operatonDebugFields = function(formId) {
            console.log('=== Debug Form Fields for Form ID: ' + formId + ' ===');
            
            var $form = $('#gform_' + formId);
            if ($form.length) {
                $form.find('input, select, textarea').each(function() {
                    var $field = $(this);
                    var name = $field.attr('name');
                    var id = $field.attr('id');
                    var type = $field.attr('type');
                    var value = getFieldValue($field);
                    
                    console.log('Field - Name:', name, 'ID:', id, 'Type:', type, 'Value:', value);
                });
            }
            console.log('=== End Debug ===');
        };
        
        window.operatonTestField = function(formId, fieldId) {
            var value = getGravityFieldValue(formId, fieldId);
            console.log('Test result for form:', formId, 'field:', fieldId, 'value:', value);
            return value;
        };
        
        // Auto-debug on page load if debug parameter is present
        if (window.location.search.indexOf('operaton_debug=1') > -1) {
            setTimeout(function() {
                $('form[id^="gform_"]').each(function() {
                    var formId = $(this).attr('id').replace('gform_', '');
                    window.operatonDebugFields(formId);
                });
            }, 1000);
        }
    }
    
    // Initialize the plugin
    initOperatonDMN();
    
    // Re-initialize when forms are dynamically loaded (AJAX forms)
    $(document).on('gform_post_render', function(event, formId, currentPage) {
        console.log('Form re-rendered:', formId, 'page:', currentPage);
        initializeFormEvaluation(formId);
    });
});