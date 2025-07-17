<?php
// Clean admin-form.php with single, enhanced field mapping section

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
        
        <!-- SINGLE FIELD MAPPING SECTION -->
        <div class="field-mapping-section">
            <h2><?php _e('Field Mappings', 'operaton-dmn'); ?> <span class="required">*</span></h2>
            <p><?php _e('Map your Gravity Form fields to DMN variables. The system will automatically detect custom radio buttons using the DMN variable names.', 'operaton-dmn'); ?></p>
            
            <div id="form-not-selected-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 4px;">
                <p><strong><?php _e('Please select a Gravity Form first to enable field mapping.', 'operaton-dmn'); ?></strong></p>
            </div>
            
            <div id="field-mappings-container" style="display: none;">
                <div class="mapping-header">
                    <div class="column header-col"><?php _e('DMN Variable Name', 'operaton-dmn'); ?></div>
                    <div class="column header-col"><?php _e('Gravity Form Field', 'operaton-dmn'); ?></div>
                    <div class="column header-col"><?php _e('Data Type', 'operaton-dmn'); ?></div>
                    <div class="column header-col"><?php _e('Radio Button Name (Optional)', 'operaton-dmn'); ?></div>
                    <div class="column header-col"><?php _e('Actions', 'operaton-dmn'); ?></div>
                </div>
                
                <div id="field-mappings">
                    <?php if (!empty($field_mappings)): ?>
                        <?php $index = 0; foreach ($field_mappings as $dmn_var => $mapping): ?>
                            <div class="field-mapping-row" data-index="<?php echo $index; ?>">
                                <div class="column">
                                    <input type="text" 
                                           name="field_mappings_dmn_variable[]" 
                                           value="<?php echo esc_attr($dmn_var); ?>" 
                                           placeholder="e.g., aanvragerAlleenstaand"
                                           class="dmn-variable-input" required />
                                </div>
                                <div class="column">
                                    <select name="field_mappings_field_id[]" class="field-id-select" required>
                                        <option value=""><?php _e('Select Field', 'operaton-dmn'); ?></option>
                                        <?php if (!empty($selected_form_fields)): ?>
                                            <?php foreach ($selected_form_fields as $field): ?>
                                                <option value="<?php echo esc_attr($field['id']); ?>" 
                                                        <?php selected($field['id'], isset($mapping['field_id']) ? $mapping['field_id'] : ''); ?>
                                                        data-type="<?php echo esc_attr($field['type']); ?>">
                                                    <?php echo esc_html($field['id'] . ' - ' . $field['label'] . ' (' . $field['type'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="column">
                                    <select name="field_mappings_type[]" required>
                                        <option value="String" <?php selected('String', isset($mapping['type']) ? $mapping['type'] : 'String'); ?>><?php _e('String', 'operaton-dmn'); ?></option>
                                        <option value="Integer" <?php selected('Integer', isset($mapping['type']) ? $mapping['type'] : ''); ?>><?php _e('Integer', 'operaton-dmn'); ?></option>
                                        <option value="Double" <?php selected('Double', isset($mapping['type']) ? $mapping['type'] : ''); ?>><?php _e('Double', 'operaton-dmn'); ?></option>
                                        <option value="Boolean" <?php selected('Boolean', isset($mapping['type']) ? $mapping['type'] : ''); ?>><?php _e('Boolean', 'operaton-dmn'); ?></option>
                                    </select>
                                </div>
                                <div class="column">
                                    <input type="text" 
                                           name="field_mappings_radio_name[]" 
                                           value="<?php echo esc_attr(isset($mapping['radio_name']) ? $mapping['radio_name'] : ''); ?>" 
                                           placeholder="Auto: <?php echo esc_attr($dmn_var); ?>"
                                           class="radio-name-input"
                                           title="<?php _e('If this field uses custom radio buttons, enter the radio button name attribute. Leave empty to use the DMN variable name.', 'operaton-dmn'); ?>" />
                                </div>
                                <div class="column">
                                    <button type="button" class="button remove-mapping"><?php _e('Remove', 'operaton-dmn'); ?></button>
                                </div>
                            </div>
                        <?php $index++; endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <button type="button" id="add-field-mapping" class="button" disabled>
                    <?php _e('Add Field Mapping', 'operaton-dmn'); ?>
                </button>
            </div>
            
            <div class="radio-detection-help">
                <h4><?php _e('Radio Button Detection', 'operaton-dmn'); ?></h4>
                <p><?php _e('The system will automatically try to detect custom radio buttons using these methods:', 'operaton-dmn'); ?></p>
                <ol>
                    <li><?php _e('Radio buttons with the same name as the DMN variable', 'operaton-dmn'); ?></li>
                    <li><?php _e('Radio buttons specified in the "Radio Button Name" field above', 'operaton-dmn'); ?></li>
                    <li><?php _e('Radio buttons derived from the Gravity Forms field label', 'operaton-dmn'); ?></li>
                </ol>
                <p><?php _e('For best results, use the DMN variable name as the radio button name attribute.', 'operaton-dmn'); ?></p>
            </div>
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
                        field.id + ' - ' + field.label + ' (' + field.type + ')</option>';
            });
        }
        
        return html;
    }
    
    // Function to suggest data type based on field type
    function suggestDataType(fieldType) {
        var suggestions = {
            'text': 'String',
            'textarea': 'String',
            'select': 'String',
            'radio': 'Boolean',
            'checkbox': 'Boolean',
            'number': 'Integer',
            'date': 'String',
            'hidden': 'String'
        };
        
        return suggestions[fieldType] || 'String';
    }
    
    // Update result display fields dropdown
    function updateResultDisplayFields() {
        var $resultSelect = $('#result_display_field');
        var currentValue = $resultSelect.val();
        var configuredValue = '<?php echo $editing && !empty($config->result_display_field) ? esc_js($config->result_display_field) : ''; ?>';
        
        $resultSelect.find('option:not(:first)').remove();
        
        if (currentFormFields.length > 0) {
            $.each(currentFormFields, function(index, field) {
                if (['text', 'textarea', 'hidden', 'number'].indexOf(field.type) !== -1) {
                    var optionText = field.id + ' - ' + field.label + ' (' + field.type + ')';
                    var $option = $('<option value="' + field.id + '">' + optionText + '</option>');
                    $resultSelect.append($option);
                }
            });
            
            var valueToSet = configuredValue || currentValue;
            if (valueToSet) {
                $resultSelect.val(valueToSet);
            }
        }
    }
    
    // Form selection change handler
    $('#form_id').change(function() {
        var selectedOption = $(this).find('option:selected');
        var fields = selectedOption.data('fields');
        
        if (fields && fields.length > 0) {
            currentFormFields = fields;
            
            $('#form-not-selected-notice').hide();
            $('#field-mappings-container').show();
            $('#add-field-mapping').prop('disabled', false);
            
            // Update all existing field dropdowns
            $('.field-id-select').each(function() {
                var currentValue = $(this).val();
                $(this).html(getFieldOptionsHtml());
                $(this).val(currentValue);
            });
            
            updateResultDisplayFields();
        } else {
            currentFormFields = [];
            $('#form-not-selected-notice').show();
            $('#field-mappings-container').hide();
            $('#add-field-mapping').prop('disabled', true);
            $('#result_display_field').find('option:not(:first)').remove();
        }
    });
    
    // Add new field mapping row
    $('#add-field-mapping').click(function() {
        var $container = $('#field-mappings');
        var index = $container.find('.field-mapping-row').length;
        
        var newRow = $(`
            <div class="field-mapping-row" data-index="${index}">
                <div class="column">
                    <input type="text" name="field_mappings_dmn_variable[]" placeholder="e.g., aanvragerAlleenstaand" class="dmn-variable-input" required />
                </div>
                <div class="column">
                    <select name="field_mappings_field_id[]" class="field-id-select" required>
                        ${getFieldOptionsHtml()}
                    </select>
                </div>
                <div class="column">
                    <select name="field_mappings_type[]" required>
                        <option value="String"><?php _e('String', 'operaton-dmn'); ?></option>
                        <option value="Integer"><?php _e('Integer', 'operaton-dmn'); ?></option>
                        <option value="Double"><?php _e('Double', 'operaton-dmn'); ?></option>
                        <option value="Boolean"><?php _e('Boolean', 'operaton-dmn'); ?></option>
                    </select>
                </div>
                <div class="column">
                    <input type="text" name="field_mappings_radio_name[]" placeholder="Auto-detect" class="radio-name-input" />
                </div>
                <div class="column">
                    <button type="button" class="button remove-mapping"><?php _e('Remove', 'operaton-dmn'); ?></button>
                </div>
            </div>
        `);
        
        $container.append(newRow);
    });
    
    // Remove field mapping row
    $(document).on('click', '.remove-mapping', function() {
        $(this).closest('.field-mapping-row').remove();
    });
    
    // Auto-suggest data type when field is selected
    $(document).on('change', '.field-id-select', function() {
        var selectedOption = $(this).find('option:selected');
        var fieldType = selectedOption.data('type');
        var dataTypeSelect = $(this).closest('.field-mapping-row').find('select[name="field_mappings_type[]"]');
        
        if (fieldType) {
            var suggestedType = suggestDataType(fieldType);
            dataTypeSelect.val(suggestedType);
        }
    });
    
    // Auto-fill radio button name placeholder when DMN variable is entered
    $(document).on('input', '.dmn-variable-input', function() {
        var $row = $(this).closest('.field-mapping-row');
        var $radioInput = $row.find('.radio-name-input');
        var dmnVariable = $(this).val().trim();
        
        if (dmnVariable) {
            $radioInput.attr('placeholder', 'Auto: ' + dmnVariable);
        } else {
            $radioInput.attr('placeholder', 'Auto-detect');
        }
    });
    
    // Update endpoint preview when base URL or decision key changes
    function updateEndpointPreview() {
        var baseUrl = $('#dmn_endpoint').val().trim();
        var decisionKey = $('#decision_key').val().trim();
        
        if (baseUrl && decisionKey) {
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
    
    // Form validation
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
    });
    
    // Initialize on page load
    updateEndpointPreview();
    
    // Initialize for editing
    <?php if ($editing && $config->form_id): ?>
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

.field-mapping-section {
    margin-top: 30px;
}

.mapping-header {
    display: grid;
    grid-template-columns: 2fr 2fr 1fr 2fr 1fr;
    gap: 15px;
    padding: 15px;
    background: #f0f0f1;
    border-bottom: 2px solid #ddd;
    font-weight: bold;
    margin-bottom: 10px;
    border-radius: 4px 4px 0 0;
}

.header-col {
    font-weight: 600;
    font-size: 14px;
}

.field-mapping-row {
    display: grid;
    grid-template-columns: 2fr 2fr 1fr 2fr 1fr;
    gap: 15px;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
    background: #fafafa;
}

.field-mapping-row:hover {
    background: #f0f0f0;
}

.field-mapping-row input,
.field-mapping-row select {
    width: 100%;
}

.radio-detection-help {
    background: #f0f0f1;
    padding: 20px;
    border-radius: 4px;
    margin: 20px 0;
}

.radio-detection-help ol {
    margin: 10px 0;
}

.radio-detection-help li {
    margin-bottom: 8px;
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

#add-field-mapping {
    margin-top: 15px;
}

#add-field-mapping:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@media (max-width: 1200px) {
    .mapping-header,
    .field-mapping-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .header-col::before {
        content: attr(data-label) ": ";
        font-weight: bold;
    }
}
</style>