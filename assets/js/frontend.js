// assets/js/frontend.js
jQuery(document).ready(function($) {
    console.log('Operaton DMN frontend script loaded');
    
    // Handle evaluate button clicks
    $(document).on('click', '.operaton-evaluate-btn', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var formId = $button.data('form-id');
        var configId = $button.data('config-id');
        var originalText = $button.val();
        
        console.log('Evaluate button clicked for form:', formId, 'config:', configId);
        
        // Get the configuration for this form
        var configVar = 'operaton_config_' + formId;
        if (typeof window[configVar] === 'undefined') {
            console.error('Configuration not found for form:', formId);
            alert('Configuration error. Please contact the administrator.');
            return;
        }
        
        var config = window[configVar];
        var fieldMappings = config.field_mappings;
        
        // Collect form data based on field mappings
        var formData = {};
        var hasRequiredData = true;
        var missingFields = [];
        
        $.each(fieldMappings, function(dmnVariable, mapping) {
            var fieldId = mapping.field_id;
            var fieldSelector = '#input_' + formId + '_' + fieldId;
            var $field = $(fieldSelector);
            
            console.log('Looking for field:', fieldSelector, 'Found:', $field.length);
            
            if ($field.length) {
                var value = $field.val();
                if (value === '' || value === null) {
                    hasRequiredData = false;
                    missingFields.push(dmnVariable);
                } else {
                    formData[fieldId] = value;
                }
            } else {
                // Try alternative selectors for different field types
                var altSelectors = [
                    'input[name="input_' + fieldId + '"]',
                    'select[name="input_' + fieldId + '"]',
                    'textarea[name="input_' + fieldId + '"]',
                    'input[name="input_' + formId + '_' + fieldId + '"]'
                ];
                
                var found = false;
                $.each(altSelectors, function(index, selector) {
                    var $altField = $(selector);
                    if ($altField.length) {
                        var value = $altField.val();
                        if (value !== '' && value !== null) {
                            formData[fieldId] = value;
                            found = true;
                            return false; // break loop
                        }
                    }
                });
                
                if (!found) {
                    hasRequiredData = false;
                    missingFields.push(dmnVariable + ' (field ID: ' + fieldId + ')');
                }
            }
        });
        
        console.log('Collected form data:', formData);
        console.log('Missing fields:', missingFields);
        
        // Validate that we have all required data
        if (!hasRequiredData) {
            alert('Please fill in all required fields: ' + missingFields.join(', '));
            return;
        }
        
        // Show loading state
        $button.val('Evaluating...').prop('disabled', true);
        $('#operaton-result-' + formId).hide();
        
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
                if (response.success && response.result) {
                    $('#operaton-result-' + formId + ' .result-content').html('<strong>' + response.result + '</strong>');
                    $('#operaton-result-' + formId).slideDown();
                } else {
                    alert('No result received from evaluation.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                var errorMessage = 'Error during evaluation. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                alert(errorMessage);
            },
            complete: function() {
                $button.val(originalText).prop('disabled', false);
            }
        });
    });
    
    // Enhanced field detection for Gravity Forms
    function getGravityFieldValue(formId, fieldId) {
        var selectors = [
            '#input_' + formId + '_' + fieldId,
            'input[name="input_' + fieldId + '"]',
            'select[name="input_' + fieldId + '"]',
            'textarea[name="input_' + fieldId + '"]',
            'input[name="input_' + formId + '_' + fieldId + '"]',
            // Handle radio buttons and checkboxes
            'input[name="input_' + fieldId + '"]:checked',
            'input[name="input_' + formId + '_' + fieldId + '"]:checked'
        ];
        
        for (var i = 0; i < selectors.length; i++) {
            var $field = $(selectors[i]);
            if ($field.length) {
                var value = $field.val();
                if (value !== '' && value !== null && value !== undefined) {
                    return value;
                }
            }
        }
        
        return null;
    }
    
    // Debug function to help with field mapping
    function debugFormFields(formId) {
        if (typeof console !== 'undefined' && console.log) {
            console.log('=== Debug Form Fields for Form ID: ' + formId + ' ===');
            
            // Find all input fields
            var $form = $('#gform_' + formId);
            if ($form.length) {
                $form.find('input, select, textarea').each(function() {
                    var $field = $(this);
                    var name = $field.attr('name');
                    var id = $field.attr('id');
                    var type = $field.attr('type');
                    var value = $field.val();
                    
                    if (name && name.indexOf('input_') === 0) {
                        console.log('Field - Name:', name, 'ID:', id, 'Type:', type, 'Value:', value);
                    }
                });
            }
            console.log('=== End Debug ===');
        }
    }
    
    // Auto-debug on page load if in development mode
    if (window.location.search.indexOf('operaton_debug=1') > -1) {
        // Find all Gravity Forms and debug their fields
        $('form[id^="gform_"]').each(function() {
            var formId = $(this).attr('id').replace('gform_', '');
            debugFormFields(formId);
        });
    }
});