// File: assets/js/admin.js
jQuery(document).ready(function($) {
    var mappingIndex = Date.now(); // Use timestamp to avoid conflicts
    
    // Add new field mapping
    $('#add-field-mapping').click(function() {
        var newMapping = `
            <div class="field-mapping-row">
                <label>` + operatonDmnAdmin.strings.dmnVariable + `</label>
                <input type="text" name="field_mappings[${mappingIndex}][dmn_variable]" class="regular-text" placeholder="e.g., season">
                
                <label>` + operatonDmnAdmin.strings.formFieldId + `</label>
                <input type="text" name="field_mappings[${mappingIndex}][field_id]" class="regular-text" placeholder="e.g., 1">
                
                <label>` + operatonDmnAdmin.strings.dataType + `</label>
                <select name="field_mappings[${mappingIndex}][type]">
                    <option value="String">String</option>
                    <option value="Integer">Integer</option>
                    <option value="Double">Double</option>
                    <option value="Boolean">Boolean</option>
                </select>
                
                <button type="button" class="button remove-mapping">` + operatonDmnAdmin.strings.remove + `</button>
            </div>
        `;
        $('#field-mappings').append(newMapping);
        mappingIndex++;
    });
    
    // Remove field mapping
    $(document).on('click', '.remove-mapping', function() {
        if (confirm(operatonDmnAdmin.strings.confirmRemove)) {
            $(this).closest('.field-mapping-row').remove();
        }
    });
    
    // Form validation
    $('#operaton-config-form').on('submit', function(e) {
        var hasMapping = $('#field-mappings .field-mapping-row').length > 0;
        
        if (!hasMapping) {
            if (!confirm(operatonDmnAdmin.strings.noMappingsWarning)) {
                e.preventDefault();
                return false;
            }
        }
        
        // Validate that each mapping has required fields
        var isValid = true;
        $('#field-mappings .field-mapping-row').each(function() {
            var dmnVar = $(this).find('input[name*="[dmn_variable]"]').val().trim();
            var fieldId = $(this).find('input[name*="[field_id]"]').val().trim();
            
            if (!dmnVar || !fieldId) {
                alert(operatonDmnAdmin.strings.incompleteMapping);
                isValid = false;
                return false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            return false;
        }
    });
});