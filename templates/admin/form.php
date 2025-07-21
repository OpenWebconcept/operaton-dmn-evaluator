<?php
/**
 * Enhanced admin-form.php with process execution support
 * Updated to use CSS classes from admin.css
 */

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

<div class="operaton-dmn-admin-wrap">
    <div class="operaton-dmn-header">
        <h1><?php echo $editing ? __('Edit Configuration', 'operaton-dmn') : __('Add New Configuration', 'operaton-dmn'); ?></h1>
        <p><?php echo $editing ? __('Update your DMN configuration settings below.', 'operaton-dmn') : __('Create a new DMN configuration to connect your Gravity Form with Operaton decision tables.', 'operaton-dmn'); ?></p>
    </div>
    
    <?php if (!class_exists('GFForms')): ?>
        <div class="operaton-notice error">
            <h4><?php _e('Gravity Forms Required', 'operaton-dmn'); ?></h4>
            <p><?php _e('Gravity Forms is required for this plugin to work. Please install and activate Gravity Forms.', 'operaton-dmn'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($needs_migration): ?>
        <div class="operaton-notice error">
            <h4><?php _e('Database Update Required', 'operaton-dmn'); ?></h4>
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
    
    <div class="operaton-config-form-wrap">
        <form method="post" id="operaton-config-form">
            <?php wp_nonce_field('save_config'); ?>
            <?php if ($editing): ?>
                <input type="hidden" name="config_id" value="<?php echo $config->id; ?>">
            <?php endif; ?>
            
            <!-- BASIC CONFIGURATION SECTION -->
            <div class="operaton-form-section">
                <h3><?php _e('Basic Configuration', 'operaton-dmn'); ?></h3>
                
                <div class="operaton-form-row">
                    <label for="name"><?php _e('Configuration Name', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    <input type="text" name="name" id="name" 
                           value="<?php echo $editing ? esc_attr($config->name) : ''; ?>" required>
                    <p class="operaton-form-description"><?php _e('A descriptive name for this configuration.', 'operaton-dmn'); ?></p>
                </div>
                
                <div class="operaton-form-row">
                    <label for="form_id"><?php _e('Gravity Form', 'operaton-dmn'); ?> <span class="required">*</span></label>
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
                    <p class="operaton-form-description"><?php _e('Select the Gravity Form to integrate with.', 'operaton-dmn'); ?></p>
                </div>
                
                <div class="operaton-form-row">
                    <label for="dmn_endpoint"><?php _e('Operaton Base Endpoint URL', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    <input type="url" name="dmn_endpoint" id="dmn_endpoint" 
                           value="<?php echo $editing ? esc_attr($config->dmn_endpoint) : ''; ?>" required>
                    <p class="operaton-form-description"><?php _e('Base URL to your Operaton engine (without the specific endpoint path).', 'operaton-dmn'); ?></p>
                    <div class="operaton-form-hint">
                        <strong><?php _e('Example:', 'operaton-dmn'); ?></strong> https://operatondev.open-regels.nl/engine-rest/
                    </div>
                    
                    <button type="button" id="test-endpoint" class="button button-secondary"><?php _e('Test Connection', 'operaton-dmn'); ?></button>
                    <div id="endpoint-test-result"></div>
                </div>
            </div>

            <!-- EXECUTION MODE SECTION -->
            <div class="operaton-form-section">
                <h3><?php _e('Execution Mode', 'operaton-dmn'); ?></h3>
                
                <div class="operaton-execution-mode">
                    <label>
                        <input type="radio" name="use_process" value="0" id="mode-decision" 
                               <?php checked(!$use_process); ?> />
                        <div class="operaton-execution-mode-content">
                            <div class="operaton-execution-mode-title"><?php _e('Direct Decision Evaluation', 'operaton-dmn'); ?></div>
                            <div class="operaton-execution-mode-description">
                                <?php _e('Directly evaluate a single DMN decision table. Best for simple decision logic.', 'operaton-dmn'); ?>
                            </div>
                        </div>
                    </label>
                    
                    <label>
                        <input type="radio" name="use_process" value="1" id="mode-process" 
                               <?php checked($use_process); ?> />
                        <div class="operaton-execution-mode-content">
                            <div class="operaton-execution-mode-title"><?php _e('Process Execution with Decision Flow', 'operaton-dmn'); ?></div>
                            <div class="operaton-execution-mode-description">
                                <?php _e('Execute a BPMN process that calls multiple decisions. Provides detailed decision flow summary.', 'operaton-dmn'); ?>
                            </div>
                        </div>
                    </label>
                </div>
                
                <!-- Decision Key (for direct evaluation) -->
                <div class="operaton-form-row" id="decision-key-row" style="<?php echo $use_process ? 'display: none;' : ''; ?>">
                    <label for="decision_key"><?php _e('Decision Key', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    <input type="text" name="decision_key" id="decision_key" 
                           value="<?php echo $editing ? esc_attr($config->decision_key) : ''; ?>">
                    <p class="operaton-form-description"><?php _e('The key/ID of your DMN decision table (e.g., "HeusdenpasAanvraagEindresultaat").', 'operaton-dmn'); ?></p>
                    <div id="decision-endpoint-preview" class="endpoint-preview">
                        <strong><?php _e('Decision Evaluation URL:', 'operaton-dmn'); ?></strong><br>
                        <span class="preview-url"></span>
                    </div>
                </div>

                <!-- Process Key (for process execution) -->
                <div class="operaton-form-row" id="process-key-row" style="<?php echo !$use_process ? 'display: none;' : ''; ?>">
                    <label for="process_key"><?php _e('Process Key', 'operaton-dmn'); ?> <span class="required">*</span></label>
                    <input type="text" name="process_key" id="process_key" 
                           value="<?php echo esc_attr($process_key); ?>">
                    <p class="operaton-form-description"><?php _e('The key/ID of your BPMN process (e.g., "HeusdenpasProcess").', 'operaton-dmn'); ?></p>
                    <div id="process-endpoint-preview" class="endpoint-preview">
                        <strong><?php _e('Process Start URL:', 'operaton-dmn'); ?></strong><br>
                        <span class="preview-url"></span>
                    </div>
                </div>

                <!-- Decision Flow Summary Option -->
                <div class="operaton-form-row" id="decision-flow-row" style="<?php echo !$use_process ? 'display: none;' : ''; ?>">
                    <label>
                        <input type="checkbox" name="show_decision_flow" id="show_decision_flow" value="1" 
                               <?php checked($show_decision_flow); ?> />
                        <?php _e('Show Decision Flow Summary', 'operaton-dmn'); ?>
                    </label>
                    <p class="operaton-form-description">
                        <?php _e('When enabled, the final page of your form will show a comprehensive summary of all decisions made during the process execution, including inputs, outputs, and decision logic flow.', 'operaton-dmn'); ?>
                    </p>
                </div>
            </div>
                
            <!-- FORM BEHAVIOR SECTION -->
            <div class="operaton-form-section">
                <h3><?php _e('Form Behavior', 'operaton-dmn'); ?></h3>
                
                <div class="operaton-form-row">
                    <label for="evaluation_step"><?php _e('Evaluation Step', 'operaton-dmn'); ?></label>
                    <select name="evaluation_step" id="evaluation_step">
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
                    <p class="operaton-form-description"><?php _e('Choose which page of the form should show the evaluate button.', 'operaton-dmn'); ?></p>
                </div>
                
                <div class="operaton-form-row">
                    <label for="button_text"><?php _e('Button Text', 'operaton-dmn'); ?></label>
                    <input type="text" name="button_text" id="button_text" 
                           value="<?php echo $editing ? esc_attr($config->button_text) : 'Evaluate'; ?>">
                    <p class="operaton-form-description"><?php _e('Text to display on the evaluation button.', 'operaton-dmn'); ?></p>
                </div>
            </div>
        
            <!-- INPUT FIELD MAPPINGS SECTION -->
            <div class="operaton-form-section">
                <h3><?php _e('Input Field Mappings', 'operaton-dmn'); ?> <span class="required">*</span></h3>
                <p class="operaton-form-description"><?php _e('Map your Gravity Form fields to DMN/Process input variables.', 'operaton-dmn'); ?></p>
                
                <div id="form-not-selected-notice" class="operaton-notice warning">
                    <p><strong><?php _e('Please select a Gravity Form first to enable field mapping.', 'operaton-dmn'); ?></strong></p>
                </div>
                
                <div id="field-mappings-container" style="display: none;">
                    <table class="operaton-mapping-table">
                        <thead>
                            <tr>
                                <th><?php _e('Variable Name', 'operaton-dmn'); ?></th>
                                <th><?php _e('Gravity Form Field', 'operaton-dmn'); ?></th>
                                <th><?php _e('Data Type', 'operaton-dmn'); ?></th>
                                <th><?php _e('Radio Button Name (Optional)', 'operaton-dmn'); ?></th>
                                <th><?php _e('Actions', 'operaton-dmn'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="field-mappings">
                            <?php if (!empty($field_mappings)): ?>
                                <?php $index = 0; foreach ($field_mappings as $dmn_var => $mapping): ?>
                                    <tr class="field-mapping-row" data-index="<?php echo $index; ?>">
                                        <td>
                                            <input type="text" 
                                                   name="field_mappings_dmn_variable[]" 
                                                   value="<?php echo esc_attr($dmn_var); ?>" 
                                                   placeholder="e.g., aanvragerAlleenstaand"
                                                   class="dmn-variable-input" required />
                                        </td>
                                        <td>
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
                                        </td>
                                        <td>
                                            <select name="field_mappings_type[]" required>
                                                <option value="String" <?php selected('String', isset($mapping['type']) ? $mapping['type'] : 'String'); ?>><?php _e('String', 'operaton-dmn'); ?></option>
                                                <option value="Integer" <?php selected('Integer', isset($mapping['type']) ? $mapping['type'] : ''); ?>><?php _e('Integer', 'operaton-dmn'); ?></option>
                                                <option value="Double" <?php selected('Double', isset($mapping['type']) ? $mapping['type'] : ''); ?>><?php _e('Double', 'operaton-dmn'); ?></option>
                                                <option value="Boolean" <?php selected('Boolean', isset($mapping['type']) ? $mapping['type'] : ''); ?>><?php _e('Boolean', 'operaton-dmn'); ?></option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="field_mappings_radio_name[]" 
                                                   value="<?php echo esc_attr(isset($mapping['radio_name']) ? $mapping['radio_name'] : ''); ?>" 
                                                   placeholder="Auto: <?php echo esc_attr($dmn_var); ?>"
                                                   class="radio-name-input" />
                                        </td>
                                        <td>
                                            <button type="button" class="button-remove"><?php _e('Remove', 'operaton-dmn'); ?></button>
                                        </td>
                                    </tr>
                                <?php $index++; endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <button type="button" id="add-field-mapping" class="operaton-add-mapping" disabled>
                        <?php _e('Add Input Field Mapping', 'operaton-dmn'); ?>
                    </button>
                </div>
            </div>

            <!-- RESULT MAPPINGS SECTION -->
            <div class="operaton-form-section">
                <h3><?php _e('Result Field Mappings', 'operaton-dmn'); ?> <span class="required">*</span></h3>
                <p class="operaton-form-description"><?php _e('Map result variables to Gravity Form fields where results should be displayed.', 'operaton-dmn'); ?></p>
                
                <div id="result-mappings-container" style="display: none;">
                    <table class="operaton-mapping-table">
                        <thead>
                            <tr>
                                <th><?php _e('Result Variable Name', 'operaton-dmn'); ?></th>
                                <th><?php _e('Gravity Form Field', 'operaton-dmn'); ?></th>
                                <th><?php _e('Actions', 'operaton-dmn'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="result-mappings">
                            <?php if (!empty($result_mappings)): ?>
                                <?php $index = 0; foreach ($result_mappings as $dmn_result => $mapping): ?>
                                    <tr class="result-mapping-row" data-index="<?php echo $index; ?>">
                                        <td>
                                            <input type="text" 
                                                   name="result_mappings_dmn_result[]" 
                                                   value="<?php echo esc_attr($dmn_result); ?>" 
                                                   placeholder="e.g., aanmerkingHeusdenPas"
                                                   class="dmn-result-input" required />
                                        </td>
                                        <td>
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
                                        </td>
                                        <td>
                                            <button type="button" class="button-remove"><?php _e('Remove', 'operaton-dmn'); ?></button>
                                        </td>
                                    </tr>
                                <?php $index++; endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <button type="button" id="add-result-mapping" class="operaton-add-mapping" disabled>
                        <?php _e('Add Result Field Mapping', 'operaton-dmn'); ?>
                    </button>
                </div>
            </div>

            <div class="operaton-form-actions">
                <?php submit_button($editing ? __('Update Configuration', 'operaton-dmn') : __('Save Configuration', 'operaton-dmn'), 'primary', 'save_config', false); ?>
                <a href="<?php echo admin_url('admin.php?page=operaton-dmn'); ?>" class="button">
                    <?php _e('Cancel', 'operaton-dmn'); ?>
                </a>
            </div>
        </form>
    </div>
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
            <tr class="field-mapping-row" data-index="${index}">
                <td>
                    <input type="text" name="field_mappings_dmn_variable[]" placeholder="e.g., aanvragerAlleenstaand" class="dmn-variable-input" required />
                </td>
                <td>
                    <select name="field_mappings_field_id[]" class="field-id-select" required>
                        ${getFieldOptionsHtml()}
                    </select>
                </td>
                <td>
                    <select name="field_mappings_type[]" required>
                        <option value="String"><?php _e('String', 'operaton-dmn'); ?></option>
                        <option value="Integer"><?php _e('Integer', 'operaton-dmn'); ?></option>
                        <option value="Double"><?php _e('Double', 'operaton-dmn'); ?></option>
                        <option value="Boolean"><?php _e('Boolean', 'operaton-dmn'); ?></option>
                    </select>
                </td>
                <td>
                    <input type="text" name="field_mappings_radio_name[]" placeholder="Auto-detect" class="radio-name-input" />
                </td>
                <td>
                    <button type="button" class="button-remove"><?php _e('Remove', 'operaton-dmn'); ?></button>
                </td>
            </tr>
        `);
        
        $container.append(newRow);
    });
    
    // Add new result mapping row
    $('#add-result-mapping').click(function() {
        var $container = $('#result-mappings');
        var index = $container.find('.result-mapping-row').length;
        
        var newRow = $(`
            <tr class="result-mapping-row" data-index="${index}">
                <td>
                    <input type="text" name="result_mappings_dmn_result[]" placeholder="e.g., aanmerkingHeusdenPas" class="dmn-result-input" required />
                </td>
                <td>
                    <select name="result_mappings_field_id[]" class="result-field-id-select" required>
                        ${getFieldOptionsHtml()}
                    </select>
                </td>
                <td>
                    <button type="button" class="button-remove"><?php _e('Remove', 'operaton-dmn'); ?></button>
                </td>
            </tr>
        `);
        
        $container.append(newRow);
    });
    
    // Remove field mapping row
    $(document).on('click', '.button-remove', function() {
        $(this).closest('tr').remove();
    });
    
    // Auto-suggest data type when field is selected
    $(document).on('change', '.field-id-select', function() {
        var selectedOption = $(this).find('option:selected');
        var fieldType = selectedOption.data('type');
        var dataTypeSelect = $(this).closest('tr').find('select[name="field_mappings_type[]"]');
        
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
        var $row = $(this).closest('tr');
        var $radioInput = $row.find('.radio-name-input');
        var dmnVariable = $(this).val().trim();
        
        if (dmnVariable) {
            $radioInput.attr('placeholder', 'Auto: ' + dmnVariable);
        } else {
            $radioInput.attr('placeholder', 'Auto-detect');
        }
    });
    
    // Replace the updateEndpointPreviews function
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
                // Process start URL
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
    
    // Test endpoint functionality - ENHANCED VERSION
    $('#test-endpoint').click(function() {
        var baseEndpoint = $('#dmn_endpoint').val().trim();
        var decisionKey = $('#decision_key').val().trim();
        var processKey = $('#process_key').val().trim();
        var useProcess = $('input[name="use_process"]:checked').val() === '1';
        
        if (!baseEndpoint) {
            alert('<?php _e('Please enter a base endpoint URL first.', 'operaton-dmn'); ?>');
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        $button.text('<?php _e('Testing...', 'operaton-dmn'); ?>').prop('disabled', true);
        
        // Clear previous results
        $('#endpoint-test-result').html('');
        
        // FIXED: Proper URL construction for engine testing
        var testPromises = [];
        
        // CORRECTED: Build proper engine base URL for version testing
        var cleanBaseUrl = baseEndpoint;
        
        // Remove any decision/process specific paths
        cleanBaseUrl = cleanBaseUrl.replace(/\/decision-definition.*$/, '');
        cleanBaseUrl = cleanBaseUrl.replace(/\/process-definition.*$/, '');
        
        // Ensure it ends with /engine-rest (no trailing slash)
        if (!cleanBaseUrl.endsWith('/engine-rest')) {
            if (cleanBaseUrl.endsWith('/')) {
                cleanBaseUrl += 'engine-rest';
            } else {
                cleanBaseUrl += '/engine-rest';
            }
        }
        
        // Test 1: Engine version endpoint (FIXED URL)
        var engineTestUrl = cleanBaseUrl + '/version';
        console.log('FIXED: Testing engine connectivity:', engineTestUrl);
        
        var engineTest = $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'operaton_test_endpoint',
                endpoint: engineTestUrl,  // This should be the correct /version endpoint
                nonce: '<?php echo wp_create_nonce('operaton_test_endpoint'); ?>'
            }
        });
        
        testPromises.push(engineTest);
        
        // Test 2: Specific endpoint based on mode (FIXED URL construction)
        if (useProcess && processKey) {
            // Test process endpoint
            var processTestUrl = cleanBaseUrl + '/process-definition/key/' + processKey;
            console.log('Testing process endpoint:', processTestUrl);
            
            var processTest = $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'operaton_test_endpoint',
                    endpoint: processTestUrl,
                    nonce: '<?php echo wp_create_nonce('operaton_test_endpoint'); ?>'
                }
            });
            
            testPromises.push(processTest);
            
        } else if (!useProcess && decisionKey) {
            // Test decision endpoint
            var decisionTestUrl = cleanBaseUrl + '/decision-definition/key/' + decisionKey;
            console.log('Testing decision endpoint:', decisionTestUrl);
            
            var decisionTest = $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'operaton_test_endpoint',
                    endpoint: decisionTestUrl,
                    nonce: '<?php echo wp_create_nonce('operaton_test_endpoint'); ?>'
                }
            });
            
            testPromises.push(decisionTest);
        }
        
        // Process all test results
        $.when.apply($, testPromises).done(function() {
            var results = Array.prototype.slice.call(arguments);
            var allSuccess = true;
            var resultHtml = '<div style="margin-top: 15px;">';
            
            // Process engine test result
            if (results.length > 0) {
                var engineResult = results[0];
                if (Array.isArray(engineResult)) engineResult = engineResult[0]; // Handle multiple promises
                
                if (engineResult && engineResult.success) {
                    resultHtml += '<div class="notice notice-success" style="margin: 5px 0; padding: 8px 12px;">';
                    resultHtml += '<p><strong>✅ Engine Connection:</strong> ' + engineResult.data.message + '</p>';
                    resultHtml += '<p><small>Tested: ' + engineTestUrl + '</small></p>';
                    resultHtml += '</div>';
                } else {
                    allSuccess = false;
                    var errorMsg = engineResult && engineResult.data ? engineResult.data.message : 'Engine connection failed';
                    resultHtml += '<div class="notice notice-error" style="margin: 5px 0; padding: 8px 12px;">';
                    resultHtml += '<p><strong>❌ Engine Connection:</strong> ' + errorMsg + '</p>';
                    resultHtml += '<p><small>Tested: ' + engineTestUrl + '</small></p>';
                    resultHtml += '</div>';
                }
            }
            
            // Process specific endpoint test result
            if (results.length > 1) {
                var specificResult = results[1];
                if (Array.isArray(specificResult)) specificResult = specificResult[0];
                
                var endpointType = useProcess ? 'Process Definition' : 'Decision Definition';
                var keyValue = useProcess ? processKey : decisionKey;
                var testedUrl = useProcess ? 
                    (cleanBaseUrl + '/process-definition/key/' + processKey) : 
                    (cleanBaseUrl + '/decision-definition/key/' + decisionKey);
                
                if (specificResult && specificResult.success) {
                    resultHtml += '<div class="notice notice-success" style="margin: 5px 0; padding: 8px 12px;">';
                    resultHtml += '<p><strong>✅ ' + endpointType + ':</strong> "' + keyValue + '" found and accessible</p>';
                    resultHtml += '<p><small>Tested: ' + testedUrl + '</small></p>';
                    resultHtml += '</div>';
                } else {
                    allSuccess = false;
                    var errorMsg = specificResult && specificResult.data ? specificResult.data.message : 'Definition not found';
                    resultHtml += '<div class="notice notice-error" style="margin: 5px 0; padding: 8px 12px;">';
                    resultHtml += '<p><strong>❌ ' + endpointType + ':</strong> "' + keyValue + '" - ' + errorMsg + '</p>';
                    resultHtml += '<p><small>Tested: ' + testedUrl + '</small></p>';
                    resultHtml += '</div>';
                }
            }
            
            // Overall status
            if (allSuccess) {
                resultHtml += '<div class="notice notice-success" style="margin: 10px 0; padding: 8px 12px; border-left: 4px solid #46b450;">';
                resultHtml += '<p><strong>🎉 All Tests Passed!</strong> Your configuration appears to be working correctly.</p>';
                resultHtml += '</div>';
            } else {
                resultHtml += '<div class="notice notice-warning" style="margin: 10px 0; padding: 8px 12px; border-left: 4px solid #ffb900;">';
                resultHtml += '<p><strong>⚠️ Some Tests Failed</strong> Please check your configuration. The evaluation may still work if the engine is accessible.</p>';
                resultHtml += '</div>';
            }
            
            resultHtml += '</div>';
            $('#endpoint-test-result').html(resultHtml);
            
        }).fail(function() {
            $('#endpoint-test-result').html(
                '<div class="notice notice-error" style="margin: 10px 0; padding: 8px 12px;">' +
                '<p><strong>❌ Connection Test Failed</strong> Unable to test endpoints. Please check your configuration.</p>' +
                '</div>'
            );
        }).always(function() {
            $button.text(originalText).prop('disabled', false);
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
/* Form-specific styles that complement admin.css */
.operaton-config-form-wrap {
    max-width: 1200px;
    margin: 0 auto;
}

.operaton-form-section {
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.13);
}

.operaton-form-row {
    margin-bottom: 20px;
}

.operaton-form-row input[type="text"],
.operaton-form-row input[type="url"],
.operaton-form-row select {
    width: 100%;
    max-width: 500px;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
    font-size: 14px;
}

.operaton-form-row label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #1d2327;
}

.operaton-form-description {
    margin-top: 5px;
    font-size: 13px;
    color: #646970;
    line-height: 1.4;
}

.operaton-form-hint {
    background: #f0f8ff;
    border: 1px solid #b3d9ff;
    border-radius: 4px;
    padding: 12px;
    margin-top: 10px;
    font-size: 13px;
    color: #0c5460;
}

.operaton-execution-mode {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
}

.operaton-execution-mode label {
    display: flex;
    align-items: flex-start;
    margin-bottom: 12px;
    cursor: pointer;
    padding: 10px;
    border-radius: 4px;
    transition: background-color 0.15s ease-in-out;
}

.operaton-execution-mode label:hover {
    background: rgba(0, 115, 170, 0.05);
}

.operaton-execution-mode input[type="radio"] {
    margin-right: 10px;
    margin-top: 2px;
}

.operaton-execution-mode-content {
    flex: 1;
}

.operaton-execution-mode-title {
    font-weight: 600;
    color: #1d2327;
    margin-bottom: 5px;
}

.operaton-execution-mode-description {
    font-size: 13px;
    color: #646970;
    line-height: 1.4;
}

.operaton-mapping-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    overflow: hidden;
    background: white;
}

.operaton-mapping-table th {
    background: #f6f7f7;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    font-size: 12px;
    color: #1d2327;
    border-bottom: 1px solid #c3c4c7;
}

.operaton-mapping-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #dcdcde;
    vertical-align: middle;
}

.operaton-mapping-table tr:hover {
    background: #f8f9fa;
}

.operaton-mapping-table input[type="text"],
.operaton-mapping-table select {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #8c8f94;
    border-radius: 3px;
    font-size: 13px;
    margin: 0;
    box-shadow: none;
}

.button-remove {
    background: #d63638;
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 11px;
    transition: background-color 0.15s ease-in-out;
}

.button-remove:hover {
    background: #a00;
}

.operaton-add-mapping {
    margin-top: 10px;
    background: #46b450;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: background-color 0.15s ease-in-out;
}

.operaton-add-mapping:hover {
    background: #3a8b40;
}

.operaton-add-mapping:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.operaton-form-actions {
    background: #f6f7f7;
    padding: 20px;
    border-top: 1px solid #c3c4c7;
    text-align: right;
    margin-top: 20px;
}

.operaton-form-actions .button {
    margin-left: 10px;
}

.endpoint-preview {
    margin-top: 10px;
    padding: 10px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-family: "Courier New", monospace;
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
    word-break: break-all;
}

.endpoint-preview strong {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

#endpoint-test-result {
    margin-top: 10px;
}

#endpoint-test-result .notice {
    margin: 0;
    padding: 8px 12px;
}

/* Responsive adjustments */
@media (max-width: 782px) {
    .operaton-config-form-wrap {
        margin: 0 10px;
    }
    
    .operaton-form-section {
        padding: 15px;
    }
    
    .operaton-mapping-table {
        font-size: 12px;
    }
    
    .operaton-mapping-table th,
    .operaton-mapping-table td {
        padding: 6px 8px;
    }
    
    .operaton-form-row input[type="text"],
    .operaton-form-row input[type="url"],
    .operaton-form-row select {
        max-width: 100%;
    }
    
    .operaton-form-actions {
        text-align: center;
    }
    
    .operaton-form-actions .button {
        margin: 5px;
        display: block;
        width: 100%;
        max-width: 200px;
    }
}
</style>