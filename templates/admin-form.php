<?php
// Enhanced admin-form.php with process execution support

$editing = isset($config) && $config;
$field_mappings = $editing ? json_decode($config->field_mappings, true) : array();

// Handle result_mappings safely
$result_mappings = array();
if ($editing) {
    if (property_exists($config, 'result_mappings') && !empty($config->result_mappings)) {
        $result_mappings = json_decode($config->result_mappings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result_mappings = array();
        }
    }
}

// Get form fields for the selected form
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

// Check if database migration is needed
global $wpdb;
$table_name = $wpdb->prefix . 'operaton_dmn_configs';
$columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
$needs_migration = !in_array('result_mappings', $columns);

// Get current process settings
$use_process = $editing && property_exists($config, 'use_process') ? $config->use_process : false;
$process_key = $editing && property_exists($config, 'process_key') ? $config->process_key : '';
$show_decision_flow = $editing && property_exists($config, 'show_decision_flow') ? $config->show_decision_flow : false;
?>
<div class="wrap">
    <h1><?php echo $editing ? __('Edit Configuration', 'operaton-dmn') : __('Add New Configuration', 'operaton-dmn'); ?></h1>
    
    <?php if (!class_exists('GFForms')): ?>
        <div class="notice notice-error">
            <p><?php _e('Gravity Forms is required for this plugin to work. Please install and activate Gravity Forms.', 'operaton-dmn'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($needs_migration): ?>
        <div class="notice notice-error">
            <p><strong><?php _e('Database Update Required', 'operaton-dmn'); ?></strong></p>
            <p><?php _e('The plugin database needs to be updated. Please deactivate and reactivate the plugin to complete the update.', 'operaton-dmn'); ?></p>
            <p>
                <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-primary">
                    <?php _e('Go to Plugins Page', 'operaton-dmn'); ?>
                </a>
            </p>
        </div>
        
        <div style="opacity: 0.5; pointer-events: none;">
            <p><em><?php _e('Configuration editing is disabled until database update is complete.', 'operaton-dmn'); ?></em></p>
        </div>
        
        <?php return; // Stop rendering the form ?>
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
                        <label for="dmn_endpoint"><?php _e('Operaton Base Endpoint URL', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="url" name="dmn_endpoint" id="dmn_endpoint" class="regular-text" 
                               value="<?php echo $editing ? esc_attr($config->dmn_endpoint) : ''; ?>" required>
                        <p class="description"><?php _e('Base URL to your Operaton engine (without the specific endpoint path).', 'operaton-dmn'); ?></p>
                        <p class="description"><strong><?php _e('Example:', 'operaton-dmn'); ?></strong> https://operatondev.open-regels.nl/engine-rest/</p>
                        <button type="button" id="test-endpoint" class="button button-secondary"><?php _e('Test Connection', 'operaton-dmn'); ?></button>
                        <div id="endpoint-test-result"></div>
                    </td>
                </tr>

                <!-- NEW: Execution Mode Selection -->
                <tr>
                    <th scope="row">
                        <label><?php _e('Execution Mode', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php _e('Choose execution mode', 'operaton-dmn'); ?></legend>
                            
                            <label>
                                <input type="radio" name="use_process" value="0" id="mode-decision" 
                                       <?php checked(!$use_process); ?> />
                                <strong><?php _e('Direct Decision Evaluation', 'operaton-dmn'); ?></strong>
                            </label>
                            <p class="description" style="margin-left: 25px;">
                                <?php _e('Directly evaluate a single DMN decision table. Best for simple decision logic.', 'operaton-dmn'); ?>
                            </p>
                            
                            <label style="margin-top: 10px; display: block;">
                                <input type="radio" name="use_process" value="1" id="mode-process" 
                                       <?php checked($use_process); ?> />
                                <strong><?php _e('Process Execution with Decision Flow', 'operaton-dmn'); ?></strong>
                            </label>
                            <p class="description" style="margin-left: 25px;">
                                <?php _e('Execute a BPMN process that calls multiple decisions. Provides detailed decision flow summary.', 'operaton-dmn'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <!-- Decision Key (for direct evaluation) -->
                <tr id="decision-key-row" style="<?php echo $use_process ? 'display: none;' : ''; ?>">
                    <th scope="row">
                        <label for="decision_key"><?php _e('Decision Key', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="decision_key" id="decision_key" class="regular-text" 
                               value="<?php echo $editing ? esc_attr($config->decision_key) : ''; ?>">
                        <p class="description"><?php _e('The key/ID of your DMN decision table (e.g., "HeusdenpasAanvraagEindresultaat").', 'operaton-dmn'); ?></p>
                        <div id="decision-endpoint-preview" class="endpoint-preview">
                            <strong><?php _e('Decision Evaluation URL:', 'operaton-dmn'); ?></strong><br>
                            <span class="preview-url"></span>
                        </div>
                    </td>
                </tr>

                <!-- Process Key (for process execution) -->
                <tr id="process-key-row" style="<?php echo !$use_process ? 'display: none;' : ''; ?>">
                    <th scope="row">
                        <label for="process_key"><?php _e('Process Key', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="process_key" id="process_key" class="regular-text" 
                               value="<?php echo esc_attr($process_key); ?>">
                        <p class="description"><?php _e('The key/ID of your BPMN process (e.g., "HeusdenpasProcess").', 'operaton-dmn'); ?></p>
                        <div id="process-endpoint-preview" class="endpoint-preview">
                            <strong><?php _e('Process Start URL:', 'operaton-dmn'); ?></strong><br>
                            <span class="preview-url"></span>
                        </div>
                    </td>
                </tr>

                <!-- Decision Flow Summary Option -->
                <tr id="decision-flow-row" style="<?php echo !$use_process ? 'display: none;' : ''; ?>">
                    <th scope="row">
                        <label for="show_decision_flow"><?php _e('Show Decision Flow Summary', 'operaton-dmn'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_decision_flow" id="show_decision_flow" value="1" 
                                   <?php checked($show_decision_flow); ?> />
                            <?php _e('Display detailed decision flow summary on the final form page', 'operaton-dmn'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, the final page of your form will show a comprehensive summary of all decisions made during the process execution, including inputs, outputs, and decision logic flow.', 'operaton-dmn'); ?>
                        </p>
                    </td>
                </tr>
                
<tr>
    <th scope="row">
        <label for="evaluation_step"><?php _e('Evaluation Step', 'operaton-dmn'); ?></label>
    </th>
    <td>
        <select name="evaluation_step" id="evaluation_step" class="regular-text">
            <?php 
            $current_step = ($editing && property_exists($config, 'evaluation_step')) ? $config->evaluation_step : '2';
            // Convert legacy 'auto' to '2'
            if ($current_step === 'auto') {
                $current_step = '2';
            }
            ?>
            <option value="1" <?php selected($current_step, '1'); ?>><?php _e('Page 1', 'operaton-dmn'); ?></option>
            <option value="2" <?php selected($current_step, '2'); ?>><?php _e('Page 2', 'operaton-dmn'); ?></option>
            <option value="3" <?php selected($current_step, '3'); ?>><?php _e('Page 3', 'operaton-dmn'); ?></option>
        </select>
        <p class="description"><?php _e('Choose which page of the form should show the evaluate button.', 'operaton-dmn'); ?></p>
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
        
        <!-- INPUT FIELD MAPPINGS SECTION -->
        <div class="field-mapping-section">
            <h2><?php _e('Input Field Mappings', 'operaton-dmn'); ?> <span class="required">*</span></h2>
            <p><?php _e('Map your Gravity Form fields to DMN/Process input variables.', 'operaton-dmn'); ?></p>
            
            <div id="form-not-selected-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 4px;">
                <p><strong><?php _e('Please select a Gravity Form first to enable field mapping.', 'operaton-dmn'); ?></strong></p>
            </div>
            
            <div id="field-mappings-container" style="display: none;">
                <div class="mapping-header">
                    <div class="column header-col"><?php _e('Variable Name', 'operaton-dmn'); ?></div>
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
                                           class="radio-name-input" />
                                </div>
                                <div class="column">
                                    <button type="button" class="button remove-mapping"><?php _e('Remove', 'operaton-dmn'); ?></button>
                                </div>
                            </div>
                        <?php $index++; endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <button type="button" id="add-field-mapping" class="button" disabled>
                    <?php _e('Add Input Field Mapping', 'operaton-dmn'); ?>
                </button>
            </div>
        </div>

        <!-- RESULT MAPPINGS SECTION -->
        <div class="result-mapping-section">
            <h2><?php _e('Result Field Mappings', 'operaton-dmn'); ?> <span class="required">*</span></h2>
            <p><?php _e('Map result variables to Gravity Form fields where results should be displayed.', 'operaton-dmn'); ?></p>
            
            <div id="result-mappings-container" style="display: none;">
                <div class="mapping-header">
                    <div class="column header-col"><?php _e('Result Variable Name', 'operaton-dmn'); ?></div>
                    <div class="column header-col"><?php _e('Gravity Form Field', 'operaton-dmn'); ?></div>
                    <div class="column header-col"><?php _e('Actions', 'operaton-dmn'); ?></div>
                </div>
                
                <div id="result-mappings">
                    <?php if (!empty($result_mappings)): ?>
                        <?php $index = 0; foreach ($result_mappings as $dmn_result => $mapping): ?>
                            <div class="result-mapping-row" data-index="<?php echo $index; ?>">
                                <div class="column">
                                    <input type="text" 
                                           name="result_mappings_dmn_result[]" 
                                           value="<?php echo esc_attr($dmn_result); ?>" 
                                           placeholder="e.g., aanmerkingHeusdenPas"
                                           class="dmn-result-input" required />
                                </div>
                                <div class="column">
                                    <select name="result_mappings_field_id[]" class="result-field-id-select" required>
                                        <option value=""><?php _e('Select Field', 'operaton-dmn'); ?></option>
                                        <?php if (!empty($selected_form_fields)): ?>
                                            <?php foreach ($selected_form_fields as $field): ?>
                                                <option value="<?php echo esc_attr($field['id']); ?>" 
                                                        <?php selected($field['id'], isset($mapping['field_id']) ? $mapping['field_id'] : ''); ?>>
                                                    <?php echo esc_html($field['id'] . ' - ' . $field['label'] . ' (' . $field['type'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="column">
                                    <button type="button" class="button remove-result-mapping"><?php _e('Remove', 'operaton-dmn'); ?></button>
                                </div>
                            </div>
                        <?php $index++; endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <button type="button" id="add-result-mapping" class="button" disabled>
                    <?php _e('Add Result Field Mapping', 'operaton-dmn'); ?>
                </button>
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
    
    // Execution mode change handler
    $('input[name="use_process"]').change(function() {
        var useProcess = $(this).val() === '1';
        
        if (useProcess) {
            $('#decision-key-row').hide();
            $('#process-key-row, #decision-flow-row').show();
            $('#decision_key').prop('required', false);
            $('#process_key').prop('required', true);
        } else {
            $('#decision-key-row').show();
            $('#process-key-row, #decision-flow-row').hide();
            $('#decision_key').prop('required', true);
            $('#process_key').prop('required', false);
        }
        
        updateEndpointPreviews();
    });
    
    // Form selection change handler
    $('#form_id').change(function() {
        var selectedOption = $(this).find('option:selected');
        var fields = selectedOption.data('fields');
        
        if (fields && fields.length > 0) {
            currentFormFields = fields;
            
            $('#form-not-selected-notice').hide();
            $('#field-mappings-container, #result-mappings-container').show();
            $('#add-field-mapping, #add-result-mapping').prop('disabled', false);
            
            // Update all existing field dropdowns
            $('.field-id-select, .result-field-id-select').each(function() {
                var currentValue = $(this).val();
                $(this).html(getFieldOptionsHtml());
                $(this).val(currentValue);
            });
        } else {
            currentFormFields = [];
            $('#form-not-selected-notice').show();
            $('#field-mappings-container, #result-mappings-container').hide();
            $('#add-field-mapping, #add-result-mapping').prop('disabled', true);
        }
    });
    
    // Add new input field mapping row
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
    
    // Add new result mapping row
    $('#add-result-mapping').click(function() {
        var $container = $('#result-mappings');
        var index = $container.find('.result-mapping-row').length;
        
        var newRow = $(`
            <div class="result-mapping-row" data-index="${index}">
                <div class="column">
                    <input type="text" name="result_mappings_dmn_result[]" placeholder="e.g., aanmerkingHeusdenPas" class="dmn-result-input" required />
                </div>
                <div class="column">
                    <select name="result_mappings_field_id[]" class="result-field-id-select" required>
                        ${getFieldOptionsHtml()}
                    </select>
                </div>
                <div class="column">
                    <button type="button" class="button remove-result-mapping"><?php _e('Remove', 'operaton-dmn'); ?></button>
                </div>
            </div>
        `);
        
        $container.append(newRow);
    });
    
    // Remove field mapping row
    $(document).on('click', '.remove-mapping', function() {
        $(this).closest('.field-mapping-row').remove();
    });
    
    // Remove result mapping row
    $(document).on('click', '.remove-result-mapping', function() {
        $(this).closest('.result-mapping-row').remove();
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
    
// FIX: Replace the updateEndpointPreviews function in admin-form.php
function updateEndpointPreviews() {
    var baseUrl = $('#dmn_endpoint').val().trim();
    var decisionKey = $('#decision_key').val().trim();
    var processKey = $('#process_key').val().trim();
    var useProcess = $('input[name="use_process"]:checked').val() === '1';
    
    if (baseUrl) {
        // Normalize base URL - remove any trailing path components that shouldn't be there
        var cleanBaseUrl = baseUrl;
        
        // Remove common endpoint paths that might be incorrectly included
        cleanBaseUrl = cleanBaseUrl.replace(/\/decision-definition.*$/, '');
        cleanBaseUrl = cleanBaseUrl.replace(/\/process-definition.*$/, '');
        
        // Ensure it ends with /engine-rest
        if (!cleanBaseUrl.endsWith('/engine-rest')) {
            if (cleanBaseUrl.endsWith('/')) {
                cleanBaseUrl += 'engine-rest';
            } else {
                cleanBaseUrl += '/engine-rest';
            }
        }
        
        // Ensure trailing slash
        if (!cleanBaseUrl.endsWith('/')) {
            cleanBaseUrl += '/';
        }
        
        if (!useProcess && decisionKey) {
            // Decision evaluation URL
            var decisionUrl = cleanBaseUrl + 'decision-definition/key/' + decisionKey + '/evaluate';
            $('#decision-endpoint-preview .preview-url').text(decisionUrl);
            $('#decision-endpoint-preview').show();
        } else {
            $('#decision-endpoint-preview').hide();
        }
        
        if (useProcess && processKey) {
            // FIXED: Process start URL (remove duplicate path components)
            var processUrl = cleanBaseUrl + 'process-definition/key/' + processKey + '/start';
            $('#process-endpoint-preview .preview-url').text(processUrl);
            $('#process-endpoint-preview').show();
        } else {
            $('#process-endpoint-preview').hide();
        }
    } else {
        $('.endpoint-preview').hide();
    }
}
    
    $('#dmn_endpoint, #decision_key, #process_key').on('input keyup', updateEndpointPreviews);
    
    // Test endpoint functionality
    $('#test-endpoint').click(function() {
        var baseEndpoint = $('#dmn_endpoint').val().trim();
        
        if (!baseEndpoint) {
            alert('<?php _e('Please enter a base endpoint URL first.', 'operaton-dmn'); ?>');
            return;
        }
        
        // Test basic connectivity to the engine
        var testUrl = baseEndpoint;
        if (!testUrl.endsWith('/')) {
            testUrl += '/';
        }
        testUrl += 'version'; // Test the version endpoint
        
        var $button = $(this);
        var originalText = $button.text();
        $button.text('<?php _e('Testing...', 'operaton-dmn'); ?>').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'operaton_test_endpoint',
                endpoint: testUrl,
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
        
        var useProcess = $('input[name="use_process"]:checked').val() === '1';
        
        if (useProcess) {
            if (!$('#process_key').val().trim()) {
                alert('<?php _e('Process key is required when using process execution.', 'operaton-dmn'); ?>');
                e.preventDefault();
                return false;
            }
        } else {
            if (!$('#decision_key').val().trim()) {
                alert('<?php _e('Decision key is required when using direct decision evaluation.', 'operaton-dmn'); ?>');
                e.preventDefault();
                return false;
            }
        }
        
        var inputMappings = $('.field-mapping-row').length;
        if (inputMappings === 0) {
            alert('<?php _e('At least one input field mapping is required.', 'operaton-dmn'); ?>');
            e.preventDefault();
            return false;
        }
        
        var resultMappings = $('.result-mapping-row').length;
        if (resultMappings === 0) {
            alert('<?php _e('At least one result field mapping is required.', 'operaton-dmn'); ?>');
            e.preventDefault();
            return false;
        }
        
        // Additional validation for complete mappings
        var hasEmptyInput = false;
        $('.field-mapping-row').each(function() {
            var dmnVar = $(this).find('.dmn-variable-input').val().trim();
            var fieldId = $(this).find('.field-id-select').val();
            
            if (dmnVar === '' || fieldId === '') {
                hasEmptyInput = true;
                return false;
            }
        });
        
        if (hasEmptyInput) {
            alert('<?php _e('All input field mapping entries must be complete.', 'operaton-dmn'); ?>');
            e.preventDefault();
            return false;
        }
        
        var hasEmptyResult = false;
        $('.result-mapping-row').each(function() {
            var dmnResult = $(this).find('.dmn-result-input').val().trim();
            var fieldId = $(this).find('.result-field-id-select').val();
            
            if (dmnResult === '' || fieldId === '') {
                hasEmptyResult = true;
                return false;
            }
        });
        
        if (hasEmptyResult) {
            alert('<?php _e('All result field mapping entries must be complete.', 'operaton-dmn'); ?>');
            e.preventDefault();
            return false;
        }
    });
    
    // Initialize on page load
    updateEndpointPreviews();
    
    // Initialize execution mode display
    $('input[name="use_process"]:checked').trigger('change');
    
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

.field-mapping-section,
.result-mapping-section {
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
    border-radius: 4px;
}

.result-mapping-section .mapping-header {
    grid-template-columns: 2fr 2fr 1fr;
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

.result-mapping-row {
    display: grid;
    grid-template-columns: 2fr 2fr 1fr;
    gap: 15px;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
    background: #fafafa;
}

.field-mapping-row:hover,
.result-mapping-row:hover {
    background: #f0f0f0;
}

.field-mapping-row input,
.field-mapping-row select,
.result-mapping-row input,
.result-mapping-row select {
    width: 100%;
}

#endpoint-test-result {
    margin-top: 10px;
}

#endpoint-test-result .notice {
    margin: 0;
    padding: 8px 12px;
}

.endpoint-preview {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    color: #666;
    display: none;
    word-break: break-all;
}

.endpoint-preview.show {
    display: block;
}

.endpoint-preview .preview-url {
    color: #0073aa;
    font-weight: bold;
}

#add-field-mapping,
#add-result-mapping {
    margin-top: 15px;
}

#add-field-mapping:disabled,
#add-result-mapping:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Execution mode styling */
fieldset {
    border: none;
    padding: 0;
    margin: 0;
}

fieldset label {
    display: block;
    margin: 0 0 5px 0;
    font-weight: normal;
}

fieldset input[type="radio"] {
    margin-right: 8px;
}

/* Process-specific highlighting */
#process-key-row.highlight,
#decision-flow-row.highlight {
    background: #f0f8ff;
    border-left: 4px solid #0073aa;
    padding-left: 10px;
}

/* Responsive design */
@media (max-width: 1200px) {
    .mapping-header,
    .field-mapping-row,
    .result-mapping-row {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .header-col {
        background: #e8f4f8;
        padding: 8px;
        margin: 2px 0;
        border-radius: 3px;
    }
}

/* Enhanced visual feedback */
.notice {
    border-radius: 4px;
}

.notice.notice-success {
    border-left-color: #46b450;
}

.notice.notice-error {
    border-left-color: #dc3232;
}

/* Better form organization */
.form-table th {
    vertical-align: top;
    padding-top: 15px;
}

.form-table td {
    padding-bottom: 20px;
}

/* Mode selection styling */
fieldset legend {
    font-weight: 600;
    margin-bottom: 10px;
}

/* Decision flow summary info */
#decision-flow-row .description {
    background: #e8f4f8;
    padding: 10px;
    border-radius: 4px;
    border-left: 3px solid #0073aa;
    margin-top: 8px;
}
</style>
<?php