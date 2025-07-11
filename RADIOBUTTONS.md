# Gravity Forms Dynamic Radio Button Script

## Overview
This script automatically updates radio button selections based on text field inputs in Gravity Forms. It dynamically detects the correct form and field IDs across different websites using the same form structure.

## Functionality

### Field Mapping
The script monitors two text fields and controls two radio button groups:

| Text Field | Controls Radio Group | Logic |
|------------|---------------------|-------|
| Partner surname (`partner_geslachtsnaam`) | "Bent u alleenstaand?" | Empty = "true" (Ja - single)<br>Has content = "false" (Nee - not single) |
| Child birthplace (`kind_geboorteplaats`) | "Heeft u kinderen?" | Empty = "false" (Nee - no children)<br>Has content = "true" (Ja - has children) |

### Key Features

- **Auto-detection**: Automatically finds the correct Gravity Form on the page regardless of form ID
- **Field identification**: Locates fields by their labels and names, not hardcoded IDs
- **Real-time updates**: Radio buttons update instantly as users type or clear text fields
- **Cross-site compatibility**: Works across multiple websites with the same form structure but different IDs
- **Infinite loop prevention**: Uses debouncing to prevent rapid successive updates
- **No event triggers**: Directly sets radio button values without triggering change events to avoid loops

### Technical Details

**Field Detection Methods:**
- Partner field: Searches for labels containing "geslachtsnaam" or field name "input_14"
- Child field: Searches for labels containing "geboorteplaats" or field name "input_16"  
- Radio groups: Identifies by labels containing "alleenstaand" and "kinderen"

**Radio Button Values:**
- Radio buttons use string values: `"true"` and `"false"`
- Script maps logic to these values appropriately

**Events Monitored:**
- `input`, `change`, `blur`, `keyup` events on text fields (with 100ms debouncing)
- Gravity Forms `gform_post_render` events for dynamic form updates

**Loop Prevention:**
- Uses `setTimeout` debouncing (100ms delay) to prevent multiple rapid calls
- Removed `trigger('change')` calls that caused infinite loops

## Installation

Add this complete code to your WordPress theme's `functions.php` file:

```php
add_filter( 'gform_field_value_sa_alleenstaand', 'pre_check_alleenstaand' );
function pre_check_alleenstaand( $value ) {
    return 'Ja'; // Default to single
}

add_filter( 'gform_field_value_sa_kinderen', 'pre_check_kinderen' );
function pre_check_kinderen( $value ) {
    return 'Nee'; // Default to no children
}

// Use wp_footer to ensure script loads
add_action( 'wp_footer', 'add_dynamic_field_scripts_footer' );
function add_dynamic_field_scripts_footer() {
    if ( is_admin() ) return;
    ?>
    <script type="text/javascript">
    console.log('=== ROBUST GRAVITY FORM SCRIPT LOADED ===');
    
    jQuery(document).ready(function($) {
        console.log('Starting robust form detection');
        
        // Function to find form with specific fields
        function findTargetForm() {
            console.log('--- Searching for target form ---');
            
            var targetForm = null;
            var fieldMapping = null;
            
            // Look for forms that contain our target fields
            $('form[id^="gform_"]').each(function() {
                var formId = $(this).attr('id').replace('gform_', '');
                console.log('Checking form ID:', formId);
                
                // Skip non-numeric form IDs (like "json-js")
                if (!/^\d+$/.test(formId)) {
                    console.log('Skipping non-numeric form ID:', formId);
                    return true; // continue
                }
                
                var form = $(this);
                
                // Look for fields with our expected characteristics
                var partnerField = null;
                var kindField = null;
                var alleenstaandRadios = null;
                var kinderenRadios = null;
                
                // Find text fields by label content
                form.find('input[type="text"]').each(function() {
                    var fieldId = $(this).attr('id');
                    var fieldName = $(this).attr('name');
                    var label = $(this).closest('.gfield').find('label, .gfield_label').text().toLowerCase();
                    
                    // Look specifically for partner surname field (geslachtsnaam), not birthdate
                    if (label.includes('geslachtsnaam') || fieldName === 'input_14' || 
                        (label.includes('partner') && label.includes('geslachtsnaam'))) {
                        partnerField = '#' + fieldId;
                        console.log('Found partner SURNAME field:', partnerField, 'Label:', label);
                    }
                    
                    // Look for child birthplace field
                    if (label.includes('geboorteplaats') || fieldName === 'input_16' || 
                        (label.includes('kind') && label.includes('geboorteplaats'))) {
                        kindField = '#' + fieldId;
                        console.log('Found child BIRTHPLACE field:', kindField, 'Label:', label);
                    }
                });
                
                // Find radio groups by label content
                form.find('input[type="radio"]').each(function() {
                    var fieldName = $(this).attr('name');
                    var label = $(this).closest('.gfield').find('label, .gfield_label').text().toLowerCase();
                    
                    if (label.includes('alleenstaand') || fieldName === 'input_17') {
                        alleenstaandRadios = 'input[name="' + fieldName + '"]';
                        console.log('Found alleenstaand radios:', alleenstaandRadios, 'Label:', label);
                    }
                    
                    if (label.includes('kinderen') || fieldName === 'input_18') {
                        kinderenRadios = 'input[name="' + fieldName + '"]';
                        console.log('Found kinderen radios:', kinderenRadios, 'Label:', label);
                    }
                });
                
                // Check if we found all required fields
                if (partnerField && kindField && alleenstaandRadios && kinderenRadios) {
                    console.log('*** FOUND TARGET FORM:', formId, '***');
                    targetForm = formId;
                    fieldMapping = {
                        partnerField: partnerField,
                        kindField: kindField,
                        alleenstaandRadios: alleenstaandRadios,
                        kinderenRadios: kinderenRadios
                    };
                    return false; // break out of loop
                }
            });
            
            if (targetForm && fieldMapping) {
                console.log('Target form found:', targetForm);
                console.log('Field mapping:', fieldMapping);
                return { formId: targetForm, fields: fieldMapping };
            } else {
                console.log('No suitable form found with all required fields');
                return null;
            }
        }
        
        // Main logic functions
        function updateAlleenstaandField(fields) {
            console.log('--- Updating Alleenstaand ---');
            
            var partnerField = $(fields.partnerField);
            var alleenstaandRadios = $(fields.alleenstaandRadios);
            
            if (partnerField.length === 0 || alleenstaandRadios.length === 0) {
                console.log('Fields not found, skipping');
                return;
            }
            
            var partnerValue = partnerField.val();
            var isEmpty = !partnerValue || partnerValue.trim() === '';
            var targetValue = isEmpty ? 'true' : 'false';  // Changed to true/false strings
            
            console.log('Partner value:', '"' + partnerValue + '"', 'Empty:', isEmpty, '-> Setting to:', targetValue);
            
            alleenstaandRadios.each(function() {
                var shouldCheck = $(this).val() === targetValue;
                $(this).prop('checked', shouldCheck);
                console.log('Radio button value:', $(this).val(), 'Should check:', shouldCheck);
            });
        }
        
        function updateKinderenField(fields) {
            console.log('--- Updating Kinderen ---');
            
            var kindField = $(fields.kindField);
            var kinderenRadios = $(fields.kinderenRadios);
            
            if (kindField.length === 0 || kinderenRadios.length === 0) {
                console.log('Fields not found, skipping');
                return;
            }
            
            var kindValue = kindField.val();
            var hasValue = kindValue && kindValue.trim() !== '';
            var targetValue = hasValue ? 'true' : 'false';  // Changed to true/false strings
            
            console.log('Child value:', '"' + kindValue + '"', 'Has content:', hasValue, '-> Setting to:', targetValue);
            
            kinderenRadios.each(function() {
                var shouldCheck = $(this).val() === targetValue;
                $(this).prop('checked', shouldCheck);
                console.log('Radio button value:', $(this).val(), 'Should check:', shouldCheck);
            });
        }
        
        function setupEventListeners(fields) {
            console.log('--- Setting up event listeners ---');
            
            var partnerField = $(fields.partnerField);
            var kindField = $(fields.kindField);
            
            if (partnerField.length > 0) {
                // Use debouncing to prevent multiple rapid calls
                var partnerTimeout;
                partnerField.on('input change blur keyup', function() {
                    clearTimeout(partnerTimeout);
                    partnerTimeout = setTimeout(function() {
                        console.log('Partner field changed (debounced)');
                        updateAlleenstaandField(fields);
                    }, 100);
                });
                console.log('Partner field listeners added');
            }
            
            if (kindField.length > 0) {
                // Use debouncing to prevent multiple rapid calls
                var kindTimeout;
                kindField.on('input change blur keyup', function() {
                    clearTimeout(kindTimeout);
                    kindTimeout = setTimeout(function() {
                        console.log('Child field changed (debounced)');
                        updateKinderenField(fields);
                    }, 100);
                });
                console.log('Child field listeners added');
            }
        }
        
        // Main initialization
        function initialize() {
            console.log('=== ROBUST INITIALIZATION ===');
            
            var formData = findTargetForm();
            
            if (!formData) {
                console.log('No suitable Gravity Form found');
                return false;
            }
            
            var fields = formData.fields;
            
            // Test field existence
            console.log('Field test:');
            console.log('- Partner field (' + fields.partnerField + '):', $(fields.partnerField).length > 0);
            console.log('- Kind field (' + fields.kindField + '):', $(fields.kindField).length > 0);
            console.log('- Alleenstaand radios (' + fields.alleenstaandRadios + '):', $(fields.alleenstaandRadios).length);
            console.log('- Kinderen radios (' + fields.kinderenRadios + '):', $(fields.kinderenRadios).length);
            
            // Debug radio button values
            console.log('--- Radio Button Values ---');
            $(fields.alleenstaandRadios).each(function(index) {
                console.log('Alleenstaand radio', index, 'value:', $(this).val());
            });
            $(fields.kinderenRadios).each(function(index) {
                console.log('Kinderen radio', index, 'value:', $(this).val());
            });
            
            // Initialize field values
            updateAlleenstaandField(fields);
            updateKinderenField(fields);
            
            // Setup event listeners
            setupEventListeners(fields);
            
            console.log('=== ROBUST INITIALIZATION COMPLETE ===');
            return true;
        }
        
        // Initialize immediately
        var initialized = initialize();
        
        // Reinitialize on Gravity Forms events
        $(document).on('gform_post_render', function(event, form_id, current_page) {
            console.log('Form post render:', form_id);
            if (!initialized || /^\d+$/.test(form_id)) { // Only for numeric form IDs
                setTimeout(initialize, 100);
            }
        });
        
        // Fallback initialization
        setTimeout(function() {
            if (!initialized) {
                console.log('Fallback initialization');
                initialize();
            }
        }, 3000);
    });
    </script>
    <?php
}
```

## Browser Support
- Works with jQuery-enabled WordPress sites
- Compatible with Gravity Forms 2.9.11+
- Requires modern browsers with ES5+ support

## Debugging
The script provides detailed console logging for troubleshooting:
- Form detection process
- Field mapping results  
- Radio button values detection
- Real-time value changes with debouncing
- Event listener status

## Example Console Output

When working correctly, you'll see output like:
```
=== ROBUST INITIALIZATION ===
--- Searching for target form ---
Checking form ID: 8
Found partner SURNAME field: #input_8_14 Label: partner_geslachtsnaam
Found child BIRTHPLACE field: #input_8_16 Label: kind_geboorteplaats
Found alleenstaand radios: input[name="input_17"] Label: bent u alleenstaand?
Found kinderen radios: input[name="input_18"] Label: heeft u kinderen?
*** FOUND TARGET FORM: 8 ***
--- Radio Button Values ---
Alleenstaand radio 0 value: true
Alleenstaand radio 1 value: false
Kinderen radio 0 value: true
Kinderen radio 1 value: false
--- Updating Alleenstaand ---
Partner value: "Iakovida" Empty: false -> Setting to: false
Radio button value: true Should check: false
Radio button value: false Should check: true
--- Updating Kinderen ---
Child value: "'s-Gravenhage" Has content: true -> Setting to: true
Radio button value: true Should check: true
Radio button value: false Should check: false
=== ROBUST INITIALIZATION COMPLETE ===
```

## Troubleshooting

If the script isn't working:

1. **Check console output** - Look for error messages or missing field detections
2. **Verify field labels** - Ensure your form has fields with labels containing "geslachtsnaam", "geboorteplaats", "alleenstaand", and "kinderen"
3. **Confirm radio button values** - The script expects radio buttons with string values "true" and "false"
4. **Test debouncing** - Look for "(debounced)" in console messages to confirm event handling is working
5. **Verify no infinite loops** - Console should not show rapid repeated updates
6. **Confirm Gravity Forms version** - Script tested with version 2.9.11+
7. **Test jQuery availability** - The script requires jQuery to be loaded

## Version History

### v2.0 (Current)
- **Fixed infinite loop issue** by removing `trigger('change')` calls
- **Added debouncing** (100ms) to prevent rapid successive updates
- **Updated value mapping** to use "true"/"false" strings instead of "Ja"/"Nee"
- **Enhanced debugging** with radio button value detection
- **Improved stability** with better event handling

### v1.0 (Original)
- Basic field detection and radio button updating
- Had infinite loop issues with event triggers