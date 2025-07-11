# Operaton DMN Evaluator

The Operaton DMN Evaluator plugin integrates WordPress Gravity Forms with Operaton DMN (Decision Model and Notation) engines to provide real-time decision evaluation capabilities. This guide covers all configuration options and capabilities.

## 📍 Repository Information

### Primary Development Repository
🚀 **Active development happens on GitLab**: [git.open-regels.nl/showcases/operaton-dmn-evaluator](https://git.open-regels.nl/showcases/operaton-dmn-evaluator)

### Public Mirror
📋 **GitHub mirror for visibility**: [github.com/OpenWebconcept/operaton-dmn-evaluator](https://github.com/OpenWebconcept/operaton-dmn-evaluator)

### Where to Go for:

| Need | Location | Link |
|------|----------|------|
| 🐛 **Report Bugs** | GitLab Issues | [Create Issue](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/issues/new) |
| ✨ **Feature Requests** | GitLab Issues | [Create Issue](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/issues/new) |
| 💾 **Latest Releases** | GitLab Releases | [View Releases](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/releases) |
| 🔄 **Auto-Updates** | Configured via GitLab | [Release System](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/releases) |

> **Note**: Active development happens on GitLab. GitHub is a read-only mirror for visibility within the OpenWebconcept ecosystem.

## About OpenWebconcept

This plugin is part of the [OpenWebconcept](https://github.com/OpenWebconcept) ecosystem - a collection of WordPress building blocks for government and public sector websites.

![OWC logo](./assets/images/OWC-logo.jpg)

## Prerequisites

- WordPress with admin access
- Gravity Forms plugin installed and activated
- Access to an Operaton DMN engine (cloud or self-hosted)
- DMN decision tables deployed on the Operaton engine

---

## Change Log

[[1.0.0-beta.5] - 2025-07-07](./CHANGELOG.md#100-beta5---2025-07-07)<br>
[[1.0.0-beta.4] - 2025-07-07](./CHANGELOG.md#100-beta4---2025-07-07)<br>
[[1.0.0-beta.3] - 2025-07-01](./CHANGELOG.md#100-beta3---2025-07-01)<br>

All notable changes to this project are documented in the [CHANGELOG.md](./CHANGELOG.md).

## Plugin Structure

```
operaton-dmn-evaluator/
├── assets/
│   └── css/
│       └── admin.css                    # Admin styles
│       └── frontend.css                 # Frontend styles
│   ├── images/                          # Images for README & CHANGELOG
│   ├── js/
│   │   └── frontend.js                  # Frontend JavaScript
├── includes/
│   ├── plugin-updater.php               # Plugin updater
│   └── update-debug.php                 # Debug page for update process
├── scripts/
│   ├── create-release.sh                # Creates release package for the plugin
├── templates/
│   ├── admin-form.php                   # Configuration form page
│   └── admin-list.php                   # Configuration list page
├── vendor/plugin-update-checker         # Custom update checker library
├── operaton-dmn-evaluator.php           # Main plugin file
└── README.md                            # This file
```

## Installation

1. **Create Plugin Directory:**
   ```bash
   cd /wp-content/plugins/
   mkdir operaton-dmn-evaluator
   cd operaton-dmn-evaluator
   ```

2. **Download source code as zip:**

   ![download source code](./assets/images/dowload-source-code.png)

3. **Extract zip in Plugin Directory:**

4. **Activate Plugin:**
   - Go to WordPress Admin → Plugins
   - Find "Operaton DMN Evaluator" and activate it


# Configuration Guide

## Plugin Features

### Core Capabilities
- **Real-time DMN Evaluation**: Execute DMN decisions directly from Gravity Forms
- **Flexible Field Mapping**: Map form fields to DMN variables with type validation
- **Multiple Data Types**: Support for String, Integer, Double, and Boolean types
- **Result Population**: Automatically populate form fields with evaluation results
- **Multi-page Form Support**: Works with single and multi-page Gravity Forms
- **Error Handling**: Comprehensive validation and user-friendly error messages
- **Configuration Management**: Easy-to-use admin interface for managing multiple configurations

### Integration Features
- **Automatic Button Injection**: Evaluation buttons are automatically added to configured forms
- **Form Validation**: Integrates with Gravity Forms validation system
- **Clean State Management**: Results are cleared when form inputs change
- **Visual Feedback**: Success notifications and field highlighting
- **Debug Support**: Comprehensive logging for troubleshooting

## Configuration Settings

### Basic Configuration

Available configurations listed.

![Configuration List](./assets/images/ConfigList.png)

Selecting a configuration opens the corresponding dashboard.

![Config Dashboard - Top](./assets/images/ConfigTop.png)

#### Configuration Name
- **Purpose**: Descriptive identifier for the configuration
- **Required**: Yes
- **Example**: "Dish Recommendation Engine", "Loan Approval System"

#### Gravity Form Selection
- **Purpose**: Choose which Gravity Form to integrate with DMN evaluation
- **Required**: Yes
- **Note**: Only one configuration per form is allowed
- **Auto-detection**: Field information is automatically loaded when form is selected

### DMN Engine Connection

#### DMN Base Endpoint URL
- **Purpose**: Base URL to your Operaton DMN engine
- **Required**: Yes
- **Format**: Should end with `/engine-rest/decision-definition/key/`
- **Examples**:
  - Operaton Cloud: `https://your-tenant.operaton.cloud/engine-rest/decision-definition/key/`
  - Self-hosted: `https://operatondev.open-regels.nl/engine-rest/decision-definition/key/`
  - Local: `http://localhost:8080/engine-rest/decision-definition/key/`
- **Validation**: URL format is validated and tested
- **Test Feature**: Built-in connection testing available

![Test Connection](./assets/images/TestConnection.png)

#### Decision Key
- **Purpose**: The unique identifier of your DMN decision table
- **Required**: Yes
- **Format**: Alphanumeric characters, hyphens, and underscores only
- **Examples**: `dish`, `loan-approval`, `risk-assessment`
- **Note**: Must match the decision key in your deployed DMN model

#### Full Evaluation URL
- **Auto-generated**: `{Base Endpoint URL}{Decision Key}/evaluate`
- **Example**: `https://operatondev.open-regels.nl/engine-rest/decision-definition/key/dish/evaluate`

### Field Mapping Configuration

![Field Mappings](./assets/images/FieldMappings.png)

#### DMN Variable Mapping
Field mappings connect Gravity Form fields to DMN decision table inputs.

**Required Components**:
- **DMN Variable**: Variable name as defined in your DMN table
- **Gravity Forms Field**: Select from available form fields
- **Data Type**: Expected data type for DMN evaluation

**Supported Data Types**:
- **String**: Text values, select options, radio button values
- **Integer**: Whole numbers (validated for numeric format)
- **Double**: Decimal numbers (validated for numeric format)  
- **Boolean**: True/false values (accepts: true, false, 1, 0, yes, no)

**Auto-suggestions**: Data types are automatically suggested based on Gravity Forms field types

**Validation**: 
- Field existence is verified
- Data type compatibility is checked
- Duplicate field usage is prevented

### Result Configuration

![Result Configuration](./assets/images/ResultConfig.png)

#### Result Field Name
- **Purpose**: Name of the output variable from your DMN decision table
- **Required**: Yes
- **Examples**: `desiredDish`, `approved`, `riskLevel`
- **Note**: Must match exactly with DMN table output variable name

#### Result Display Field (Optional)
- **Purpose**: Specify which form field should receive the evaluation result
- **Options**: Auto-populated from available text, textarea, number, and hidden fields
- **Auto-detection**: If not specified, system will automatically detect suitable fields
- **Detection Priority**:
  1. Fields with labels containing "desired dish", "result"
  2. Fields with "dish" or "result" in name/ID
  3. Visible text fields on current page

### Form Behavior Settings

#### Evaluation Step
- **Auto-detect (recommended)**: System determines optimal placement
- **Manual Selection**: Choose specific form step (Step 1, 2, 3, etc.)
- **Current Implementation**: Evaluation button appears on same page as result field

#### Button Text
- **Purpose**: Customize the text displayed on evaluation button
- **Default**: "Evaluate"
- **Examples**: "Get Recommendation", "Check Eligibility", "Calculate Result"

## Form Integration Behavior

### Button Placement
- Evaluation buttons are automatically injected into configured forms
- Buttons appear on the same page as mapped input fields
- Styling matches Gravity Forms theme

### Evaluation Process
1. **Validation**: Form fields are validated before evaluation
2. **Data Collection**: Values are extracted from mapped fields
3. **Type Conversion**: Data is converted to specified DMN types
4. **API Call**: Request is sent to Operaton DMN engine
5. **Result Processing**: Response is parsed and result extracted
6. **Field Population**: Result is populated into designated field
7. **User Feedback**: Success notification is displayed

### State Management
- Results are cleared when form inputs change
- Results are cleared when navigating between form pages
- Fresh evaluation is required after any input modification
- Clean state prevents stale data confusion

## Advanced Features

### Connection Testing
- **Endpoint Validation**: Test connectivity to DMN engine
- **Full Configuration Test**: Validate complete endpoint with decision key
- **Error Diagnosis**: Detailed error messages for troubleshooting

### Debug Support
- **Console Logging**: Comprehensive debug information when WP_DEBUG is enabled
- **Field Detection Debug**: Tools to verify field mapping detection
- **AJAX Response Logging**: Full API request/response logging

### Error Handling
- **Network Errors**: Connection timeout and connectivity issues
- **API Errors**: DMN engine error responses with user-friendly messages
- **Validation Errors**: Field validation with specific error descriptions
- **Configuration Errors**: Setup validation with correction guidance

## Form Design Best Practices

### Result Field Placement
- **Same Page**: Place result field on same page as evaluate button for immediate feedback
- **Clear Labeling**: Use descriptive labels like "Recommended Dish" or "Approval Result"
- **Field Type**: Use text fields for most results, select fields for predefined options

### Field Mapping Strategy
- **Required Fields**: Ensure all mapped fields are marked as required in Gravity Forms
- **Data Types**: Choose appropriate data types matching your DMN table expectations
- **Field Order**: Logical flow from input fields to evaluate button to result field

### User Experience
- **Clear Instructions**: Provide clear form instructions about the evaluation process
- **Progress Indication**: Use form progress bars for multi-step forms
- **Error Messages**: Customize error messages for better user guidance

## Troubleshooting

### Common Configuration Issues

**"Configuration error. Please contact the administrator."**
- Check that configuration exists for the form
- Verify form ID matches configuration

**"No result field found on this page."**
- Add a text field to receive results
- Configure specific result display field
- Check field labeling for auto-detection

**"Connection failed" or "Endpoint not found (404)"**
- Verify DMN base endpoint URL format
- Check decision key matches deployed DMN model
- Test network connectivity to DMN engine

### Field Detection Issues
- Use browser console to debug field detection
- Check field visibility on current page
- Verify field naming conventions for auto-detection
- Configure specific field ID if auto-detection fails

### API Connection Issues
- Verify DMN engine is running and accessible
- Check decision table deployment status
- Validate decision key spelling and case sensitivity
- Review network firewall and security settings

## Example Configurations

### Dish Recommendation System
```
Configuration Name: Dish Example
Gravity Form: Dish Selection Form (ID: 2)
DMN Base Endpoint: https://operatondev.open-regels.nl/engine-rest/decision-definition/key/
Decision Key: dish
Result Field Name: desiredDish
Button Text: Get Recommendation

Field Mappings:
- season (String) → Season dropdown (Field ID: 1)
- guestCount (Integer) → Guest Count number field (Field ID: 3)

Result Display Field: Desired Dish text field (Field ID: 7)
```

### Loan Approval System
```
Configuration Name: Loan Approval
Gravity Form: Loan Application (ID: 5)
DMN Base Endpoint: https://your-bank.operaton.cloud/engine-rest/decision-definition/key/
Decision Key: loan-approval
Result Field Name: approved
Button Text: Check Eligibility

Field Mappings:
- income (Double) → Annual Income (Field ID: 2)
- creditScore (Integer) → Credit Score (Field ID: 3)
- loanAmount (Double) → Requested Amount (Field ID: 4)
- hasCollateral (Boolean) → Collateral Available (Field ID: 5)

Result Display Field: Approval Status (Field ID: 6)
```

## Version Information

- **Plugin Version**: 1.0.0-beta.6
- **Gravity Forms Compatibility**: 2.4+
- **WordPress Compatibility**: 5.0+
- **PHP Requirements**: 7.4+

## Support and Documentation

For additional support:
- Check WordPress admin debug logs when WP_DEBUG is enabled
- Use browser developer tools console for frontend debugging
- Review Operaton DMN engine logs for API-related issues
- Consult Operaton DMN documentation for decision table modeling

---

*This guide covers the current beta implementation focusing on same-page result population. Future versions may include additional features such as multi-step result population and advanced field mapping options.*