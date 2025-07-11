<?php
// Updated admin-form.php with dropdown field selection

$editing = isset($config) && $config;
$field_mappings = $editing ? json_decode($config->field_mappings, true) : array();

// Get form fields for the selected form to show available options
$selected_form_fields = array();
if ($editing && $config->form_id) {
    if (class_exists('GFAPI')) {
        $form = GFAPI::get_form($config->form_id);
        if ($form && isset($form['fields'])) {
            foreach ($form['fields'] as $field) {
                $selected_form_fields[] = array(
                    'id' => $field->id,
                    'label' => $field->label,
                    'type' => $field->type
                );
            }
        }
    }
}
?>
<div class="wrap">
    <h1><?php echo $editing ? __('Edit Configuration', 'operaton-dmn') : __('Add New Configuration', 'operaton-dmn'); ?></h1>
    
    <?php if (!class_exists('GFForms')): ?>
        <div class="notice notice-error">
            <p><?php _e('Gravity Forms is required for this plugin to work. Please install and activate Gravity Forms.', 'operaton-dmn'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" id="operaton-config-form">
        <?php wp_nonce_field('save_config'); ?>
        <?php if ($editing): ?>
            <input type="hidden" name="config_id" value="<?php echo $config->id; ?>">
        <?php endif; ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="name"><?php _e('Configuration Name', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="name" id="name" class="regular-text" 
                               value="<?php echo $editing ? esc_attr($config->name) : ''; ?>" required>
                        <p class="description"><?php _e('A descriptive name for this configuration.', 'operaton-dmn'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="form_id"><?php _e('Gravity Form', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <select name="form_id" id="form_id" required>
                            <option value=""><?php _e('Select a form...', 'operaton-dmn'); ?></option>
                            <?php if (!empty($gravity_forms)): ?>
                                <?php foreach ($gravity_forms as $form): ?>
                                    <option value="<?php echo $form['id']; ?>" 
                                            <?php selected($editing ? $config->form_id : '', $form['id']); ?>
                                            data-fields="<?php echo esc_attr(json_encode($form['field_list'] ?? array())); ?>">
                                        <?php echo esc_html($form['title']) . ' (ID: ' . $form['id'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled><?php _e('No Gravity Forms found', 'operaton-dmn'); ?></option>
                            <?php endif; ?>
                        </select>
                        <p class="description"><?php _e('Select the Gravity Form to integrate with. Field mappings will be available after selecting a form.', 'operaton-dmn'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="dmn_endpoint"><?php _e('DMN Base Endpoint URL', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="url" name="dmn_endpoint" id="dmn_endpoint" class="regular-text" 
                               value="<?php echo $editing ? esc_attr($config->dmn_endpoint) : ''; ?>" required>
                        <p class="description"><?php _e('Base URL to your Operaton DMN engine (without the decision key).', 'operaton-dmn'); ?></p>
                        <p class="description"><strong><?php _e('Example:', 'operaton-dmn'); ?></strong> https://operatondev.open-regels.nl/engine-rest/decision-definition/key/</p>
                        <p class="description"><em><?php _e('The decision key will be automatically appended to create the full evaluation URL.', 'operaton-dmn'); ?></em></p>
                        <button type="button" id="test-endpoint" class="button button-secondary"><?php _e('Test Connection', 'operaton-dmn'); ?></button>
                        <div id="endpoint-test-result"></div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="decision_key"><?php _e('Decision Key', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="decision_key" id="decision_key" class="regular-text" 
                               value="<?php echo $editing ? esc_attr($config->decision_key) : ''; ?>" required>
                        <p class="description"><?php _e('The key/ID of your DMN decision table (e.g., "dish", "loan-approval").', 'operaton-dmn'); ?></p>
                        <div id="full-endpoint-preview" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-family: monospace; font-size: 12px; color: #666; display: none;">
                            <strong><?php _e('Full Evaluation URL:', 'operaton-dmn'); ?></strong><br>
                            <span id="preview-url"></span>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="result_field"><?php _e('Result Field Name', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="result_field" id="result_field" class="regular-text" 
                               value="<?php echo $editing ? esc_attr($config->result_field) : ''; ?>" required>
                        <p class="description"><?php _e('The name of the output field from your DMN table (e.g., "desiredDish", "approved").', 'operaton-dmn'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="result_display_field"><?php _e('Result Display Field (Optional)', 'operaton-dmn'); ?></label>
                    </th>
                    <td>
                        <select name="result_display_field" id="result_display_field" class="regular-text">
                            <option value=""><?php _e('Select field to populate with result...', 'operaton-dmn'); ?></option>
                        </select>
                        <p class="description"><?php _e('Choose a field to automatically populate with the evaluation result. Leave empty to use automatic field detection.', 'operaton-dmn'); ?></p>
                        
                        <?php if ($editing && !empty($config->result_display_field)): ?>
                        <p class="description" style="color: #666; font-style: italic;">
                            <strong><?php _e('Currently configured:', 'operaton-dmn'); ?></strong> 
                            Field ID <?php echo esc_html($config->result_display_field); ?>
                        </p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="evaluation_step"><?php _e('Evaluation Step', 'operaton-dmn'); ?></label>
                    </th>
                    <td>
                        <select name="evaluation_step" id="evaluation_step" class="regular-text">
                            <option value="auto" <?php selected($editing && isset($config->evaluation_step) ? $config->evaluation_step : 'auto', 'auto'); ?>><?php _e('Auto-detect (recommended)', 'operaton-dmn'); ?></option>
                            <option value="1" <?php selected($editing && isset($config->evaluation_step) ? $config->evaluation_step : '', '1'); ?>>Step 1</option>
                            <option value="2" <?php selected($editing && isset($config->evaluation_step) ? $config->evaluation_step : '', '2'); ?>>Step 2</option>
                            <option value="3" <?php selected($editing && isset($config->evaluation_step) ? $config->evaluation_step : '', '3'); ?>>Step 3</option>
                        </select>
                        <p class="description"><?php _e('Choose which step of the form should show the evaluate button. Auto-detect will place it appropriately.', 'operaton-dmn'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="button_text"><?php _e('Button Text', 'operaton-dmn'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="button_text" id="button_text" class="regular-text" 
                               value="<?php echo $editing ? esc_attr($config->button_text) : 'Evaluate'; ?>">
                        <p class="description"><?php _e('Text to display on the evaluation button.', 'operaton-dmn'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2><?php _e('Field Mappings', 'operaton-dmn'); ?> <span class="required">*</span></h2>
        <p><?php _e('Map Gravity Form fields to DMN variables. At least one mapping is required.', 'operaton-dmn'); ?></p>
        
        <div id="form-not-selected-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 4px;">
            <p><strong><?php _e('Please select a Gravity Form first to enable field mapping.', 'operaton-dmn'); ?></strong></p>
        </div>
        
        <div id="field-mappings-container" style="display: none;">
            <div id="field-mappings">
                <?php if (!empty($field_mappings)): ?>
                    <?php foreach ($field_mappings as $dmn_var => $mapping): ?>
                        <div class="field-mapping-row">
                            <div class="form-field">
                                <label><?php _e('DMN Variable:', 'operaton-dmn'); ?></label>
                                <input type="text" name="field_mappings_dmn_variable[]" 
                                       value="<?php echo esc_attr($dmn_var); ?>" class="regular-text dmn-variable-input" required>
                            </div>
                            
                            <div class="form-field">
                                <label><?php _e('Gravity Forms Field:', 'operaton-dmn'); ?></label>
                                <select name="field_mappings_field_id[]" class="field-id-select" required>
                                    <option value=""><?php _e('Select a field...', 'operaton-dmn'); ?></option>
                                    <?php if (!empty($selected_form_fields)): ?>
                                        <?php foreach ($selected_form_fields as $field): ?>
                                            <option value="<?php echo esc_attr($field['id']); ?>" 
                                                    <?php selected($mapping['field_id'], $field['id']); ?>
                                                    data-type="<?php echo esc_attr($field['type']); ?>">
                                                <?php echo esc_html($field['label']) . ' (ID: ' . $field['id'] . ', Type: ' . $field['type'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <label><?php _e('Data Type:', 'operaton-dmn'); ?></label>
                                <select name="field_mappings_type[]" class="data-type-select" required>
                                    <option value="String" <?php selected($mapping['type'], 'String'); ?>>String</option>
                                    <option value="Integer" <?php selected($mapping['type'], 'Integer'); ?>>Integer</option>
                                    <option value="Double" <?php selected($mapping['type'], 'Double'); ?>>Double</option>
                                    <option value="Boolean" <?php selected($mapping['type'], 'Boolean'); ?>>Boolean</option>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <button type="button" class="button remove-mapping"><?php _e('Remove', 'operaton-dmn'); ?></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" id="add-field-mapping" class="button add-field-mapping" disabled>
                <?php _e('Add Field Mapping', 'operaton-dmn'); ?>
            </button>
        </div>
        
        <div class="operaton-help">
            <h4><?php _e('Configuration Help', 'operaton-dmn'); ?></h4>
            <ul>
                <li><strong><?php _e('DMN Base Endpoint:', 'operaton-dmn'); ?></strong> <?php _e('The base URL to your Operaton engine, ending with "/key/"', 'operaton-dmn'); ?></li>
                <li><strong><?php _e('Decision Key:', 'operaton-dmn'); ?></strong> <?php _e('The specific decision table identifier', 'operaton-dmn'); ?></li>
                <li><strong><?php _e('DMN Variable:', 'operaton-dmn'); ?></strong> <?php _e('The variable name as defined in your DMN table', 'operaton-dmn'); ?></li>
                <li><strong><?php _e('Gravity Forms Field:', 'operaton-dmn'); ?></strong> <?php _e('Select from available form fields - field info is automatically populated', 'operaton-dmn'); ?></li>
                <li><strong><?php _e('Data Type:', 'operaton-dmn'); ?></strong> <?php _e('The expected data type for the DMN evaluation', 'operaton-dmn'); ?></li>
            </ul>
        </div>
        
        <?php submit_button($editing ? __('Update Configuration', 'operaton-dmn') : __('Save Configuration', 'operaton-dmn'), 'primary', 'save_config'); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var currentFormFields = [];
    
    // Function to get field options HTML
    function getFieldOptionsHtml() {
        var html = '<option value=""><?php _e('Select a field...', 'operaton-dmn'); ?></option>';
        
        if (currentFormFields.length > 0) {
            $.each(currentFormFields, function(index, field) {
                html += '<option value="' + field.id + '" data-type="' + field.type + '">' + 
                        field.label + ' (ID: ' + field.id + ', Type: ' + field.type + ')</option>';
            });
        }
        
        return html;
    }
    
function updateResultDisplayFields() {
    var $resultSelect = $('#result_display_field');
    var currentValue = $resultSelect.val();
    
    // Store the currently configured value from PHP
    var configuredValue = '<?php echo $editing && !empty($config->result_display_field) ? esc_js($config->result_display_field) : ''; ?>';
    
    // Clear existing options except the first one
    $resultSelect.find('option:not(:first)').remove();
    
    if (currentFormFields.length > 0) {
        $.each(currentFormFields, function(index, field) {
            // Only show text, textarea, number and hidden fields as potential result fields
            if (['text', 'textarea', 'hidden', 'number'].indexOf(field.type) !== -1) {
                var optionText = field.label + ' (ID: ' + field.id + ', Type: ' + field.type + ')';
                var $option = $('<option value="' + field.id + '">' + optionText + '</option>');
                $resultSelect.append($option);
            }
        });
        
        // Restore the configured value or current selection
        var valueToSet = configuredValue || currentValue;
        if (valueToSet) {
            $resultSelect.val(valueToSet);
        }
    }
    
    console.log('Result display field dropdown updated. Configured value:', configuredValue);
}

    // Function to suggest data type based on field type
    function suggestDataType(fieldType) {
        var suggestions = {
            'text': 'String',
            'textarea': 'String',
            'select': 'String',
            'radio': 'String',
            'checkbox': 'Boolean',
            'number': 'Integer',
            'phone': 'String',
            'email': 'String',
            'website': 'String',
            'name': 'String',
            'address': 'String',
            'date': 'String',
            'time': 'String',
            'list': 'String',
            'multiselect': 'String',
            'fileupload': 'String',
            'captcha': 'String',
            'section': 'String',
            'page': 'String',
            'html': 'String',
            'hidden': 'String',
            'post_title': 'String',
            'post_content': 'String',
            'post_excerpt': 'String',
            'post_tags': 'String',
            'post_category': 'String',
            'post_image': 'String',
            'post_custom_field': 'String',
            'product': 'Double',
            'quantity': 'Integer',
            'option': 'String',
            'shipping': 'Double',
            'total': 'Double',
            'calculation': 'Double',
            'pricing': 'Double'
        };
        
        return suggestions[fieldType] || 'String';
    }
    
    // Enhanced field mapping functionality
    $('#add-field-mapping').click(function() {
        var newMapping = `
            <div class="field-mapping-row">
                <div class="form-field">
                    <label><?php _e('DMN Variable:', 'operaton-dmn'); ?></label>
                    <input type="text" name="field_mappings_dmn_variable[]" class="regular-text dmn-variable-input" required>
                </div>
                
                <div class="form-field">
                    <label><?php _e('Gravity Forms Field:', 'operaton-dmn'); ?></label>
                    <select name="field_mappings_field_id[]" class="field-id-select" required>
                        ${getFieldOptionsHtml()}
                    </select>
                </div>
                
                <div class="form-field">
                    <label><?php _e('Data Type:', 'operaton-dmn'); ?></label>
                    <select name="field_mappings_type[]" class="data-type-select" required>
                        <option value="String">String</option>
                        <option value="Integer">Integer</option>
                        <option value="Double">Double</option>
                        <option value="Boolean">Boolean</option>
                    </select>
                </div>
                
                <div class="form-field">
                    <button type="button" class="button remove-mapping"><?php _e('Remove', 'operaton-dmn'); ?></button>
                </div>
            </div>
        `;
        $('#field-mappings').append(newMapping);
        updateEndpointPreview();
    });
    
    // Remove mapping
    $(document).on('click', '.remove-mapping', function() {
        $(this).closest('.field-mapping-row').remove();
    });
    
    // Auto-suggest data type when field is selected
    $(document).on('change', '.field-id-select', function() {
        var selectedOption = $(this).find('option:selected');
        var fieldType = selectedOption.data('type');
        var dataTypeSelect = $(this).closest('.field-mapping-row').find('.data-type-select');
        
        if (fieldType) {
            var suggestedType = suggestDataType(fieldType);
            dataTypeSelect.val(suggestedType);
        }
    });
    
// Update the existing form selection change handler to include result field update
$('#form_id').change(function() {
    var selectedOption = $(this).find('option:selected');
    var fields = selectedOption.data('fields');
    
    if (fields && fields.length > 0) {
        currentFormFields = fields;
        
        // Show field mappings container
        $('#form-not-selected-notice').hide();
        $('#field-mappings-container').show();
        $('#add-field-mapping').prop('disabled', false);
        
        // Update all existing field dropdowns
        $('.field-id-select').each(function() {
            var currentValue = $(this).val();
            $(this).html(getFieldOptionsHtml());
            $(this).val(currentValue);
        });
        
        // Update result display field dropdown
        updateResultDisplayFields();
        
    } else {
        currentFormFields = [];
        $('#form-not-selected-notice').show();
        $('#field-mappings-container').hide();
        $('#add-field-mapping').prop('disabled', true);
        
        // Clear result display field dropdown
        $('#result_display_field').find('option:not(:first)').remove();
    }
});

    // Update endpoint preview when base URL or decision key changes
    function updateEndpointPreview() {
        var baseUrl = $('#dmn_endpoint').val().trim();
        var decisionKey = $('#decision_key').val().trim();
        
        if (baseUrl && decisionKey) {
            // Ensure base URL ends with /
            if (!baseUrl.endsWith('/')) {
                baseUrl += '/';
            }
            
            var fullUrl = baseUrl + decisionKey + '/evaluate';
            $('#preview-url').text(fullUrl);
            $('#full-endpoint-preview').show();
        } else {
            $('#full-endpoint-preview').hide();
        }
    }
    
    // Bind preview update to input changes
    $('#dmn_endpoint, #decision_key').on('input keyup', updateEndpointPreview);
    
    // Test endpoint functionality
    $('#test-endpoint').click(function() {
        var baseEndpoint = $('#dmn_endpoint').val().trim();
        var decisionKey = $('#decision_key').val().trim();
        
        if (!baseEndpoint) {
            alert('<?php _e('Please enter a base endpoint URL first.', 'operaton-dmn'); ?>');
            return;
        }
        
        if (!decisionKey) {
            alert('<?php _e('Please enter a decision key first.', 'operaton-dmn'); ?>');
            return;
        }
        
        // Build full endpoint URL
        var fullEndpoint = baseEndpoint;
        if (!fullEndpoint.endsWith('/')) {
            fullEndpoint += '/';
        }
        fullEndpoint += decisionKey + '/evaluate';
        
        var $button = $(this);
        var originalText = $button.text();
        $button.text('<?php _e('Testing...', 'operaton-dmn'); ?>').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'operaton_test_endpoint',
                endpoint: fullEndpoint,
                nonce: '<?php echo wp_create_nonce('operaton_test_endpoint'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#endpoint-test-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    $('#endpoint-test-result').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $('#endpoint-test-result').html('<div class="notice notice-error"><p><?php _e('Connection test failed.', 'operaton-dmn'); ?></p></div>');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Enhanced form validation
    $('#operaton-config-form').submit(function(e) {
        var formSelected = $('#form_id').val();
        if (!formSelected) {
            alert('<?php _e('Please select a Gravity Form first.', 'operaton-dmn'); ?>');
            e.preventDefault();
            return false;
        }
        
        var mappings = $('.field-mapping-row').length;
        if (mappings === 0) {
            alert('<?php _e('At least one field mapping is required.', 'operaton-dmn'); ?>');
            e.preventDefault();
            return false;
        }
        
        // Check for empty required fields in mappings
        var hasEmpty = false;
        var hasDuplicateFields = false;
        var usedFields = [];
        
        $('.field-mapping-row').each(function() {
            var dmnVar = $(this).find('.dmn-variable-input').val().trim();
            var fieldId = $(this).find('.field-id-select').val();
            
            if (dmnVar === '' || fieldId === '') {
                hasEmpty = true;
                return false;
            }
            
            // Check for duplicate field usage
            if (usedFields.indexOf(fieldId) !== -1) {
                hasDuplicateFields = true;
                return false;
            }
            usedFields.push(fieldId);
        });
        
        if (hasEmpty) {
            alert('<?php _e('All field mapping entries must be complete.', 'operaton-dmn'); ?>');
            e.preventDefault();
            return false;
        }
        
        if (hasDuplicateFields) {
            alert('<?php _e('Each form field can only be mapped once.', 'operaton-dmn'); ?>');
            e.preventDefault();
            return false;
        }
        
        // Validate that base endpoint doesn't include decision key
        var baseUrl = $('#dmn_endpoint').val().trim();
        var decisionKey = $('#decision_key').val().trim();
        
        if (baseUrl.includes(decisionKey)) {
            alert('<?php _e('The base endpoint URL should not include the decision key. Please remove the decision key from the URL.', 'operaton-dmn'); ?>');
            e.preventDefault();
            return false;
        }
    });
    
    // Initialize on page load
    updateEndpointPreview();
    
    // Initialize result display fields if editing
    <?php if ($editing && $config->form_id): ?>
    // Trigger the change event to populate dropdowns
    setTimeout(function() {
        $('#form_id').trigger('change');
    }, 100);
    <?php endif; ?>

    // Check initial state
    if ($('#form_id').val()) {
        $('#form_id').trigger('change');
    }
});
</script>

<style>
.required {
    color: #d63638;
}

.field-mapping-row {
    display: flex;
    align-items: end;
    gap: 15px;
    margin-bottom: 15px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.field-mapping-row .form-field {
    flex: 1;
}

.field-mapping-row .form-field:last-child {
    flex: 0 0 auto;
}

.field-mapping-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.field-mapping-row input,
.field-mapping-row select {
    width: 100%;
}

.field-id-select {
    min-width: 250px;
}

#endpoint-test-result {
    margin-top: 10px;
}

#endpoint-test-result .notice {
    margin: 0;
    padding: 8px 12px;
}

#full-endpoint-preview {
    word-break: break-all;
}

.operaton-help {
    background: #f0f0f1;
    padding: 20px;
    border-radius: 4px;
    margin: 20px 0;
}

.operaton-help ul {
    margin: 10px 0;
}

.operaton-help li {
    margin-bottom: 8px;
}

.add-field-mapping {
    margin-top: 15px;
}

.add-field-mapping:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .field-mapping-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .field-mapping-row .form-field:last-child {
        flex: 1;
    }
}
</style>