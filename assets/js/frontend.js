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
        
        console.log('Field mappings:', fieldMappings);
        
        // Collect form data based on field mappings
        var formData = {};
        var hasRequiredData = true;
        var missingFields = [];
        
        $.each(fieldMappings, function(dmnVariable, mapping) {
            var fieldId = mapping.field_id;
            console.log('Processing DMN variable:', dmnVariable, 'Field ID:', fieldId);
            
            // Try to get the field value using multiple strategies
            var value = getGravityFieldValue(formId, fieldId);
            
            console.log('Found value for field', fieldId + ':', value);
            
            if (value === null || value === '' || value === undefined) {
                hasRequiredData = false;
                missingFields.push(dmnVariable + ' (field ID: ' + fieldId + ')');
            } else {
                formData[dmnVariable] = value;
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
                    $('#operaton-result-' + formId).fadeIn(200);
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
        console.log('Getting value for form:', formId, 'field:', fieldId);
        
        // Strategy 1: Standard Gravity Forms naming convention: input_{form_id}_{field_id}
        var selectors = [
            'input[name="input_' + formId + '_' + fieldId + '"]',
            'select[name="input_' + formId + '_' + fieldId + '"]',
            'textarea[name="input_' + formId + '_' + fieldId + '"]',
            '#input_' + formId + '_' + fieldId,
            // Strategy 2: Alternative naming patterns
            'input[name="input_' + fieldId + '"]',
            'select[name="input_' + fieldId + '"]',
            'textarea[name="input_' + fieldId + '"]',
            '#input_' + fieldId,
            // Strategy 3: Handle radio buttons and checkboxes
            'input[name="input_' + formId + '_' + fieldId + '"]:checked',
            'input[name="input_' + fieldId + '"]:checked'
        ];
        
        for (var i = 0; i < selectors.length; i++) {
            var selector = selectors[i];
            var $field = $(selector);
            console.log('Trying selector:', selector, 'Found:', $field.length);
            
            if ($field.length > 0) {
                var value = $field.val();
                console.log('Value from selector:', selector, 'Value:', value);
                
                if (value !== '' && value !== null && value !== undefined) {
                    return value;
                }
            }
        }
        
        // Strategy 4: Look for any input with a data attribute or class that contains the field ID
        var $possibleFields = $('input, select, textarea').filter(function() {
            var name = $(this).attr('name') || '';
            var id = $(this).attr('id') || '';
            return name.indexOf('_' + fieldId) > -1 || id.indexOf('_' + fieldId) > -1;
        });
        
        if ($possibleFields.length > 0) {
            console.log('Found possible fields:', $possibleFields);
            var value = $possibleFields.first().val();
            if (value !== '' && value !== null && value !== undefined) {
                console.log('Using value from possible field:', value);
                return value;
            }
        }
        
        console.log('No value found for field:', fieldId);
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
                    
                    console.log('Field - Name:', name, 'ID:', id, 'Type:', type, 'Value:', value);
                });
            } else {
                // Try to find fields without form wrapper
                console.log('Form wrapper not found, checking all fields on page:');
                $('input, select, textarea').each(function() {
                    var $field = $(this);
                    var name = $field.attr('name') || '';
                    var id = $field.attr('id') || '';
                    var type = $field.attr('type');
                    var value = $field.val();
                    
                    if (name.indexOf('input_') === 0 || id.indexOf('input_') === 0) {
                        console.log('Gravity Field - Name:', name, 'ID:', id, 'Type:', type, 'Value:', value);
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
        
        // Also debug if no forms found
        setTimeout(function() {
            if ($('form[id^="gform_"]').length === 0) {
                console.log('No Gravity Forms found, debugging all form fields:');
                debugFormFields('unknown');
            }
        }, 1000);
    }
    
    // Add a manual debug function that can be called from browser console
    window.operatonDebugFields = function(formId) {
        debugFormFields(formId || 'all');
    };
    
    // Add helper to test field detection
    window.operatonTestField = function(formId, fieldId) {
        var value = getGravityFieldValue(formId, fieldId);
        console.log('Test result for form:', formId, 'field:', fieldId, 'value:', value);
        return value;
    };
});