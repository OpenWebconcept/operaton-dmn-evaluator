<?php
// templates/admin-form.php
$editing = isset($config) && $config;
$field_mappings = $editing ? json_decode($config->field_mappings, true) : array();
?>
<div class="wrap">
    <h1><?php echo $editing ? __('Edit Configuration', 'operaton-dmn') : __('Add New Configuration', 'operaton-dmn'); ?></h1>
    
    <form method="post" id="operaton-config-form">
        <?php wp_nonce_field('save_config'); ?>
        <?php if ($editing): ?>
            <input type="hidden" name="config_id" value="<?php echo $config->id; ?>">
        <?php endif; ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="name"><?php _e('Configuration Name', 'operaton-dmn'); ?></label>
                </th>
                <td>
                    <input type="text" name="name" id="name" class="regular-text" 
                           value="<?php echo $editing ? esc_attr($config->name) : ''; ?>" required>
                    <p class="description"><?php _e('A descriptive name for this configuration.', 'operaton-dmn'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="form_id"><?php _e('Gravity Form', 'operaton-dmn'); ?></label>
                </th>
                <td>
                    <select name="form_id" id="form_id" required>
                        <option value=""><?php _e('Select a form...', 'operaton-dmn'); ?></option>
                        <?php foreach ($gravity_forms as $form): ?>
                            <option value="<?php echo $form['id']; ?>" 
                                    <?php selected($editing ? $config->form_id : '', $form['id']); ?>>
                                <?php echo esc_html($form['title']) . ' (ID: ' . $form['id'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select the Gravity Form to integrate with.', 'operaton-dmn'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="dmn_endpoint"><?php _e('DMN Endpoint URL', 'operaton-dmn'); ?></label>
                </th>
                <td>
                    <input type="url" name="dmn_endpoint" id="dmn_endpoint" class="regular-text" 
                           value="<?php echo $editing ? esc_attr($config->dmn_endpoint) : ''; ?>" required>
                    <p class="description"><?php _e('Full URL to your Operaton DMN evaluation endpoint.', 'operaton-dmn'); ?></p>
                    <p class="description"><strong><?php _e('Example:', 'operaton-dmn'); ?></strong> https://operatondev.open-regels.nl/engine-rest/decision-definition/key/dish/evaluate</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="decision_key"><?php _e('Decision Key', 'operaton-dmn'); ?></label>
                </th>
                <td>
                    <input type="text" name="decision_key" id="decision_key" class="regular-text" 
                           value="<?php echo $editing ? esc_attr($config->decision_key) : ''; ?>" required>
                    <p class="description"><?php _e('The key/ID of your DMN decision table.', 'operaton-dmn'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="result_field"><?php _e('Result Field Name', 'operaton-dmn'); ?></label>
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
        </table>
        
        <h2><?php _e('Field Mappings', 'operaton-dmn'); ?></h2>
        <p><?php _e('Map Gravity Form fields to DMN variables:', 'operaton-dmn'); ?></p>
        
        <div id="field-mappings">
            <?php if (!empty($field_mappings)): ?>
                <?php foreach ($field_mappings as $dmn_var => $mapping): ?>
                    <div class="field-mapping-row">
                        <label><?php _e('DMN Variable:', 'operaton-dmn'); ?></label>
                        <input type="text" name="field_mappings_dmn_variable[]" 
                               value="<?php echo esc_attr($dmn_var); ?>" class="regular-text dmn-variable-input">
                        
                        <label><?php _e('Form Field ID:', 'operaton-dmn'); ?></label>
                        <input type="text" name="field_mappings_field_id[]" 
                               value="<?php echo esc_attr($mapping['field_id']); ?>" class="regular-text">
                        
                        <label><?php _e('Data Type:', 'operaton-dmn'); ?></label>
                        <select name="field_mappings_type[]">
                            <option value="String" <?php selected($mapping['type'], 'String'); ?>>String</option>
                            <option value="Integer" <?php selected($mapping['type'], 'Integer'); ?>>Integer</option>
                            <option value="Double" <?php selected($mapping['type'], 'Double'); ?>>Double</option>
                            <option value="Boolean" <?php selected($mapping['type'], 'Boolean'); ?>>Boolean</option>
                        </select>
                        
                        <button type="button" class="button remove-mapping"><?php _e('Remove', 'operaton-dmn'); ?></button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <button type="button" id="add-field-mapping" class="button">
            <?php _e('Add Field Mapping', 'operaton-dmn'); ?>
        </button>
        
        <?php submit_button($editing ? __('Update Configuration', 'operaton-dmn') : __('Save Configuration', 'operaton-dmn'), 'primary', 'save_config'); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#add-field-mapping').click(function() {
        var newMapping = `
            <div class="field-mapping-row">
                <label><?php _e('DMN Variable:', 'operaton-dmn'); ?></label>
                <input type="text" name="field_mappings_dmn_variable[]" class="regular-text dmn-variable-input">
                
                <label><?php _e('Form Field ID:', 'operaton-dmn'); ?></label>
                <input type="text" name="field_mappings_field_id[]" class="regular-text">
                
                <label><?php _e('Data Type:', 'operaton-dmn'); ?></label>
                <select name="field_mappings_type[]">
                    <option value="String">String</option>
                    <option value="Integer">Integer</option>
                    <option value="Double">Double</option>
                    <option value="Boolean">Boolean</option>
                </select>
                
                <button type="button" class="button remove-mapping"><?php _e('Remove', 'operaton-dmn'); ?></button>
            </div>
        `;
        $('#field-mappings').append(newMapping);
    });
    
    $(document).on('click', '.remove-mapping', function() {
        $(this).closest('.field-mapping-row').remove();
    });
});
</script>

<style>
.field-mapping-row {
    margin-bottom: 15px;
    padding: 15px;
    border: 1px solid #ddd;
    background: #f9f9f9;
}

.field-mapping-row label {
    display: inline-block;
    width: 120px;
    margin-right: 10px;
    font-weight: bold;
}

.field-mapping-row input,
.field-mapping-row select {
    margin-right: 15px;
}
</style>