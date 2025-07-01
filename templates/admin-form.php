<?php
// templates/admin-form.php - IMPROVED VERSION
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
                        <p class="description"><?php _e('Select the Gravity Form to integrate with.', 'operaton-dmn'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="dmn_endpoint"><?php _e('DMN Endpoint URL', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="url" name="dmn_endpoint" id="dmn_endpoint" class="regular-text" 
                               value="<?php echo $editing ? esc_attr($config->dmn_endpoint) : ''; ?>" required>
                        <p class="description"><?php _e('Full URL to your Operaton DMN evaluation endpoint.', 'operaton-dmn'); ?></p>
                        <p class="description"><strong><?php _e('Example:', 'operaton-dmn'); ?></strong> https://operatondev.open-regels.nl/engine-rest/decision-definition/key/dish/evaluate</p>
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
                        <p class="description"><?php _e('The key/ID of your DMN decision table.', 'operaton-dmn'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="result_field"><?php _e('Result Field Name', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="result_field" id="result_field" class="regular-text" 
                               value="<?php echo $editing ? esc_attr($config->result_field) : ''; ?>" required>
                        <p class="description"><?php _e('The name of the output field from your DMN table (e.g., "desiredDish").', 'operaton-dmn'); ?></p>
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
        
        <div id="available-fields" style="display: none;">
            <h4><?php _e('Available Form Fields:', 'operaton-dmn'); ?></h4>
            <div id="fields-list"></div>
        </div>
        
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
                            <label><?php _e('Form Field ID:', 'operaton-dmn'); ?></label>
                            <input type="text" name="field_mappings_field_id[]" 
                                   value="<?php echo esc_attr($mapping['field_id']); ?>" class="regular-text field-id-input" required>
                        </div>
                        
                        <div class="form-field">
                            <label><?php _e('Data Type:', 'operaton-dmn'); ?></label>
                            <select name="field_mappings_type[]" required>
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
        
        <button type="button" id="add-field-mapping" class="add-field-mapping">
            <?php _e('Add Field Mapping', 'operaton-dmn'); ?>
        </button>
        
        <div class="operaton-help">
            <h4><?php _e('Field Mapping Help', 'operaton-dmn'); ?></h4>
            <ul>
                <li><?php _e('DMN Variable: The variable name as defined in your DMN table', 'operaton-dmn'); ?></li>
                <li><?php _e('Form Field ID: The numeric ID of the Gravity Forms field (e.g., "1", "2", "3")', 'operaton-dmn'); ?></li>
                <li><?php _e('Data Type: The expected data type for the DMN evaluation', 'operaton-dmn'); ?></li>
            </ul>
        </div>
        
        <?php submit_button($editing ? __('Update Configuration', 'operaton-dmn') : __('Save Configuration', 'operaton-dmn'), 'primary', 'save_config'); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Enhanced field mapping functionality
    $('#add-field-mapping').click(function() {
        var newMapping = `
            <div class="field-mapping-row">
                <div class="form-field">
                    <label><?php _e('DMN Variable:', 'operaton-dmn'); ?></label>
                    <input type="text" name="field_mappings_dmn_variable[]" class="regular-text dmn-variable-input" required>
                </div>
                
                <div class="form-field">
                    <label><?php _e('Form Field ID:', 'operaton-dmn'); ?></label>
                    <input type="text" name="field_mappings_field_id[]" class="regular-text field-id-input" required>
                </div>
                
                <div class="form-field">
                    <label><?php _e('Data Type:', 'operaton-dmn'); ?></label>
                    <select name="field_mappings_type[]" required>
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
    });
    
    $(document).on('click', '.remove-mapping', function() {
        $(this).closest('.field-mapping-row').remove();
    });
    
    // Show available fields when form is selected
    $('#form_id').change(function() {
        var selectedOption = $(this).find('option:selected');
        var fields = selectedOption.data('fields');
        
        if (fields && fields.length > 0) {
            var fieldsList = $('#fields-list');
            fieldsList.empty();
            
            $.each(fields, function(index, field) {
                fieldsList.append('<span class="field-tag" data-field-id="' + field.id + '">' + 
                    field.label + ' (ID: ' + field.id + ', Type: ' + field.type + ')</span>');
            });
            
            $('#available-fields').show();
        } else {
            $('#available-fields').hide();
        }
    });
    
    // Click on field tag to auto-fill field ID
    $(document).on('click', '.field-tag', function() {
        var fieldId = $(this).data('field-id');
        var lastFieldInput = $('.field-id-input').last();
        if (lastFieldInput.length && lastFieldInput.val() === '') {
            lastFieldInput.val(fieldId);
        }
    });
    
    // Test endpoint functionality
    $('#test-endpoint').click(function() {
        var endpoint = $('#dmn_endpoint').val();
        if (!endpoint) {
            alert('<?php _e('Please enter an endpoint URL first.', 'operaton-dmn'); ?>');
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        $button.text('<?php _e('Testing...', 'operaton-dmn'); ?>').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'operaton_test_endpoint',
                endpoint: endpoint,
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
        var mappings = $('.field-mapping-row').length;
        if (mappings === 0) {
            alert('<?php _e('At least one field mapping is required.', 'operaton-dmn'); ?>');
            e.preventDefault();
            return false;
        }
        
        // Check for empty required fields in mappings
        var hasEmpty = false;
        $('.field-mapping-row').each(function() {
            var dmnVar = $(this).find('.dmn-variable-input').val().trim();
            var fieldId = $(this).find('.field-id-input').val().trim();
            
            if (dmnVar === '' || fieldId === '') {
                hasEmpty = true;
                return false;
            }
        });
        
        if (hasEmpty) {
            alert('<?php _e('All field mapping entries must be complete.', 'operaton-dmn'); ?>');
            e.preventDefault();
            return false;
        }
    });
    
    // Trigger form selection change if editing
    <?php if ($editing && $config->form_id): ?>
    $('#form_id').trigger('change');
    <?php endif; ?>
});
</script>

<style>
.required {
    color: #d63638;
}

.field-tag {
    display: inline-block;
    background: #f0f0f1;
    padding: 4px 8px;
    margin: 2px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
    border: 1px solid #c3c4c7;
}

.field-tag:hover {
    background: #dcdcde;
}

#available-fields {
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    padding: 15px;
    margin: 15px 0;
    border-radius: 4px;
}

#fields-list {
    margin-top: 10px;
}

#endpoint-test-result {
    margin-top: 10px;
}

#endpoint-test-result .notice {
    margin: 0;
    padding: 8px 12px;
}
</style>