# Operaton DMN Evaluator

The Operaton DMN Evaluator plugin integrates WordPress Gravity Forms with Operaton DMN (Decision Model and Notation) engines to provide real-time decision evaluation capabilities. **NEW in v1.0.0-beta.9**: Execute complete BPMN processes with comprehensive decision flow analysis and professional Excel-style result summaries.

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
- DMN decision tables and/or BPMN processes deployed on the Operaton engine

---

## 🚀 Key Features (v1.0.0-beta.9)

### Dual Execution Modes ✨ NEW
- **Direct Decision Evaluation**: Execute single DMN decisions for simple use cases
- **Process Execution with Decision Flow**: Execute complete BPMN processes with comprehensive decision analysis
- **Flexible Configuration**: Choose the appropriate mode based on your complexity requirements
- **Professional Decision Summaries**: Excel-style decision flow analysis on form completion

### Core Capabilities
- **Real-time Evaluation**: Execute decisions/processes directly from Gravity Forms
- **Multiple Result Fields**: Map multiple DMN/process result fields to different form fields
- **Advanced Field Mapping**: Map form fields to DMN variables with comprehensive type validation
- **Professional Decision Flow**: **NEW** - Excel-style decision summaries with complete process analysis
- **Process Instance Tracking**: **NEW** - Complete traceability through Operaton process instances
- **Multi-page Form Support**: Works seamlessly with single and multi-page Gravity Forms

### Integration Features
- **Automatic Button Injection**: Smart evaluation button placement based on execution mode
- **Form Validation**: Full integration with Gravity Forms validation system
- **Clean State Management**: Intelligent clearing of results when form inputs change
- **Visual Feedback**: Enhanced notifications and professional result presentation
- **Comprehensive Debug Support**: Advanced logging and decision flow analysis

---

## Change Log

All notable changes to this project are documented in the [CHANGELOG.md](./CHANGELOG.md).

## Plugin Structure

```
operaton-dmn-evaluator/
├── assets/
│   └── css/
│       └── admin.css                    # Admin styles with process execution UI
│       └── frontend.css                 # Frontend styles with decision flow CSS
│   ├── images/                          # Images for README & CHANGELOG
│   ├── js/
│   │   └── frontend.js                  # Enhanced frontend with process support
├── includes/
│   ├── plugin-updater.php               # Plugin updater
│   └── update-debug.php                 # Debug page for update process
├── scripts/
│   ├── create-release.sh                # Creates release package for the plugin
├── templates/
│   ├── admin-form.php                   # Enhanced configuration with execution modes
│   └── admin-list.php                   # Configuration list page
├── vendor/plugin-update-checker         # Custom update checker library
├── operaton-dmn-evaluator.php           # Main plugin with process execution support
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

# Demo

The familiar [Dish example configured as demo](https://owc-gemeente.open-regels.nl/operaton-dmn-evaluator-2/) shows a multi-step form with a DMN evaluation at the end. The result is displayed in a short popup in the top-right corner of your screen, and the designated field on the form is populated.  

![Form step 2](./assets/images/Screenshot%202025-07-11%20164610.png)

---

# Configuration Guide

## Execution Modes ✨ NEW

### 1. Direct Decision Evaluation
**Best for**: Simple decision logic, single decision tables, basic use cases

- **Single Decision**: Evaluates one DMN decision table directly
- **Simple Configuration**: Just base URL + decision key
- **Immediate Results**: Direct result population in form fields
- **Lightweight**: Minimal API calls and processing
- **Use Cases**: Product recommendations, simple eligibility checks, basic calculations

### 2. Process Execution with Decision Flow ✨ NEW
**Best for**: Complex business logic, multi-step decisions, comprehensive analysis

- **Complete Process**: Executes full BPMN processes with multiple decisions
- **Decision Flow Analysis**: Comprehensive tracking of all decision instances
- **Professional Summaries**: Excel-style decision flow display on final form page
- **Process Tracking**: Complete traceability through process instance IDs
- **Use Cases**: Complex eligibility assessments, multi-criteria evaluations, government applications

## Configuration Settings

### Basic Configuration

Available configurations listed.

![Configuration List](./assets/images/ConfigList.png)

Selecting a configuration opens the corresponding dashboard.

![Config Dashboard - Top](./assets/images/ConfigTop.png)

#### Configuration Name
- **Purpose**: Descriptive identifier for the configuration
- **Required**: Yes
- **Example**: "Heusdenpas Process Evaluation", "Simple Dish Recommendation"

#### Gravity Form Selection
- **Purpose**: Choose which Gravity Form to integrate with evaluation
- **Required**: Yes
- **Note**: Only one configuration per form is allowed
- **Auto-detection**: Field information is automatically loaded when form is selected

### Execution Mode Selection ✨ NEW

#### Choose Your Execution Approach
**Direct Decision Evaluation**
- Radio button selection for simple decision table evaluation
- Requires: Base endpoint URL + Decision key
- Results: Direct field population
- Best for: Single-step decisions

**Process Execution with Decision Flow**
- Radio button selection for comprehensive process execution
- Requires: Base endpoint URL + Process key
- Results: Multiple field population + decision flow summary
- Best for: Multi-step business processes

### DMN/Process Engine Connection

#### Base Endpoint URL
- **Purpose**: Base URL to your Operaton engine
- **Required**: Yes (for both modes)
- **Format**: Should end with `/engine-rest/`
- **Examples**:
  - Operaton Cloud: `https://your-tenant.operaton.cloud/engine-rest/`
  - Self-hosted: `https://operatondev.open-regels.nl/engine-rest/`
  - Local: `http://localhost:8080/engine-rest/`

#### Decision Key (Direct Evaluation Mode)
- **Purpose**: The unique identifier of your DMN decision table
- **Required**: Yes (for direct evaluation)
- **Format**: Alphanumeric characters, hyphens, and underscores only
- **Examples**: `dish`, `loan-approval`, `risk-assessment`
- **Auto-generated URL**: `{Base URL}/decision-definition/key/{Decision Key}/evaluate`

#### Process Key (Process Execution Mode) ✨ NEW
- **Purpose**: The unique identifier of your BPMN process definition
- **Required**: Yes (for process execution)
- **Format**: Alphanumeric characters, hyphens, and underscores only
- **Examples**: `HeusdenpasEvaluationWithIntermediates`, `comprehensive-loan-process`
- **Auto-generated URL**: `{Base URL}/process-definition/key/{Process Key}/start`

#### Decision Flow Summary ✨ NEW
- **Purpose**: Enable comprehensive decision flow analysis on final form page
- **Available**: Only for Process Execution mode
- **When enabled**: Shows professional Excel-style decision summary
- **Features**: Complete process analysis, decision timeline, input/output tracking

### Field Mapping Configuration

![Field Mappings](./assets/images/FieldMappings.png)

#### Input Variable Mapping
Field mappings connect Gravity Form fields to DMN/process input variables.

**Required Components**:
- **Variable Name**: Variable name as defined in your DMN table or process
- **Gravity Forms Field**: Select from available form fields
- **Data Type**: Expected data type for evaluation
- **Radio Button Name**: Optional custom radio button detection

**Supported Data Types**:
- **String**: Text values, select options, radio button values, dates
- **Integer**: Whole numbers (validated for numeric format)
- **Double**: Decimal numbers (validated for numeric format)  
- **Boolean**: True/false values (accepts: true, false, 1, 0, yes, no)

### Result Field Mappings

#### Multiple Result Configuration
Configure multiple result fields to populate different form fields simultaneously.

#### Result Field Mappings
- **Purpose**: Map output variables to specific form fields
- **Required**: At least one result mapping is required
- **Format**: Result Variable Name → Gravity Form Field
- **Process Mode Benefits**: **NEW** - Enhanced result extraction from process variables
- **Examples**: 
  - Direct Decision: `desiredDish` → Field ID 7
  - Process Results: `aanmerkingHeusdenPas` → Field ID 35, `aanmerkingKindPakket` → Field ID 36

**Enhanced Process Result Extraction** ✨ NEW:
- **Comprehensive Variable Search**: Advanced extraction from process history
- **Nested Result Handling**: Supports complex process variable structures
- **Multiple Strategy Extraction**: Fallback methods for reliable result retrieval
- **Container Support**: Handles results nested in process containers

### Form Behavior Settings

#### Evaluation Step
- **Page Selection**: Choose specific form page for evaluation button placement
- **Smart Placement**: System optimizes button placement based on execution mode
- **Current Implementation**: Evaluation button appears with mapped input fields

#### Button Text
- **Purpose**: Customize the text displayed on evaluation button
- **Default**: "Evaluate"
- **Examples**: "Process Application", "Get Recommendations", "Execute Analysis"

---

## Decision Flow Summary ✨ NEW

### Professional Excel-Style Display
When Process Execution mode is enabled with decision flow summary, users see a comprehensive analysis on the final form page:

#### Summary Statistics Dashboard
![Decision Summary](./assets/images/decisionsummary.png)

#### Excel-Style Decision Tables
Professional table layout for each decision with:

**Table Structure**:
- Clean "Variable" and "Value" column headers
- Row grouping for Inputs (📥) and Outputs (📤)
- Professional borders and hover effects

**Enhanced Value Display**:
- **Booleans**: ✅ true / ❌ false with color coding
- **Numbers**: Purple highlighting for numeric values
- **Strings**: Standard monospace formatting
- **Null values**: Italicized "null" indication

**Metadata Footer**:
- **Evaluation Time**: Timezone-converted timestamps (e.g., "2025-07-19 19:03:04 (CEST)")
- **Activity Context**: BPMN activity information
- **Process Traceability**: Complete process instance tracking

### Interactive Features
- **Refresh Button**: Manual refresh capability for updated decision flow data
- **Cache Management**: Intelligent caching with cache-busting support
- **Responsive Design**: Mobile-friendly tables with adaptive layouts
- **Print Support**: Clean printing layout for documentation

![Decision flow output](./assets/images/decisionflowoutput.png)

---

## Form Integration Behavior

### Button Placement and Control ✨ ENHANCED
- **Mode-Aware Placement**: Different placement logic for direct vs. process execution
- **Page-Specific Behavior**: Intelligent button visibility based on form page
- **Emergency Recovery**: Automatic button visibility fixes for edge cases
- **Dynamic Styling**: Professional button styling matching Gravity Forms theme

### Evaluation Process

#### Direct Decision Evaluation Flow
1. **Validation**: Form fields validated before evaluation
2. **Data Collection**: Values extracted from mapped fields
3. **Type Conversion**: Data converted to specified DMN types
4. **API Call**: Direct call to DMN decision evaluation endpoint
5. **Result Processing**: Response parsed and results extracted
6. **Field Population**: Results populated into designated fields
7. **User Feedback**: Success notification displayed

#### Process Execution Flow ✨ NEW
1. **Validation**: Form fields validated before execution
2. **Data Collection**: Values extracted from mapped fields  
3. **Process Start**: BPMN process initiated with input variables
4. **Process Monitoring**: System tracks process instance completion
5. **Variable Extraction**: Historical variables retrieved from completed process
6. **Result Processing**: Enhanced extraction from process variables
7. **Field Population**: Multiple results populated simultaneously
8. **Process Tracking**: Process instance ID stored for decision flow
9. **User Feedback**: Enhanced notifications with process information

### State Management ✨ ENHANCED
- **Process Data Clearing**: **NEW** - Automatic clearing of process instance data
- **Navigation Handling**: Results cleared when navigating between form pages
- **Input Change Detection**: Fresh evaluation required after any input modification
- **Session Management**: **NEW** - Process instance tracking across form sessions

---

## Advanced Features

### Process Instance Tracking ✨ NEW
- **Complete Traceability**: Every process execution is tracked with unique instance ID
- **Session Storage**: Process IDs stored for decision flow retrieval
- **Cross-Page Persistence**: Process tracking maintained across form navigation
- **User Meta Storage**: Process IDs saved for logged-in users

### Decision Flow Analysis ✨ NEW
- **Historical Data Access**: Complete access to all decision instances in process
- **Timeline Construction**: Chronological ordering of decision evaluations
- **Variable Tracking**: Full input/output tracking for each decision
- **Activity Context**: BPMN activity information for process understanding

### Enhanced Result Processing ✨ NEW
- **Multi-Strategy Extraction**: Advanced variable extraction with fallback methods
- **Nested Variable Support**: Handles complex process variable structures
- **Container Awareness**: Supports results nested in process result containers
- **Comprehensive Search**: Multiple extraction strategies for reliable results

### Connection Testing
- **Endpoint Validation**: Test connectivity to DMN engine
- **Mode-Specific Testing**: **NEW** - Different testing for decision vs. process endpoints
- **Full Configuration Test**: Validate complete endpoint configuration
- **Error Diagnosis**: Detailed error messages for troubleshooting

### Debug Support ✨ ENHANCED
- **Process Execution Logging**: **NEW** - Comprehensive logging of process execution flow
- **Decision Flow Debug**: **NEW** - Detailed logging of decision flow retrieval
- **Variable Extraction Debug**: **NEW** - Logging of result extraction strategies
- **Enhanced Console Logging**: Comprehensive debug information when WP_DEBUG enabled

---

## Form Design Best Practices

### Execution Mode Selection
- **Simple Use Cases**: Choose Direct Decision Evaluation for straightforward decision logic
- **Complex Processes**: Choose Process Execution for multi-step business processes requiring comprehensive analysis
- **Decision Flow Needs**: Enable Process Execution with Decision Flow Summary for transparency requirements

### Result Field Placement ✨ ENHANCED
- **Process Mode**: Place result fields on same page as evaluate button for immediate feedback
- **Decision Flow Page**: **NEW** - Reserve final page for decision flow summary display
- **Clear Labeling**: Use descriptive labels reflecting the execution mode
- **Multiple Results**: Group related result fields together for better UX
- **Professional Presentation**: **NEW** - Design final page to accommodate Excel-style decision summaries

### Process-Specific Design Considerations ✨ NEW
- **Three-Page Structure**: Input → Evaluation → Decision Flow Summary
- **Decision Flow Space**: Ensure final page has adequate space for comprehensive summaries
- **Loading States**: Design for decision flow loading with appropriate messaging
- **Summary Context**: Provide context about the decision flow analysis for users

### Field Mapping Strategy ✨ ENHANCED
- **Process Variables**: **NEW** - Map to process input variables that flow through multiple decisions
- **Required Fields**: Ensure all mapped fields are marked as required in Gravity Forms
- **Data Types**: Choose appropriate data types matching your DMN/process expectations
- **Field Order**: Logical flow from input fields to evaluate button to result fields
- **Result Organization**: Group related result fields together for better UX
- **Process Result Mapping**: **NEW** - Consider nested variable structures for process results

### User Experience ✨ ENHANCED
- **Clear Instructions**: Provide clear form instructions about the evaluation/process execution
- **Progress Indication**: Use form progress bars for multi-step forms
- **Error Messages**: Customize error messages for better user guidance
- **Decision Flow Context**: **NEW** - Provide context about decision flow analysis availability
- **Process Expectations**: **NEW** - Set user expectations for comprehensive process evaluation

---

## Troubleshooting

### Common Configuration Issues

**"Configuration error. Please contact the administrator."**
- Check that configuration exists for the form
- Verify form ID matches configuration
- Ensure execution mode is properly selected

**"Process key is required when using process execution."**
- Verify Process Execution mode is selected and process key is entered
- Check process key spelling matches deployed BPMN process

**"Decision key is required when using direct decision evaluation."**
- Verify Direct Decision Evaluation mode is selected and decision key is entered
- Check decision key spelling matches deployed DMN decision

### Process Execution Issues ✨ NEW

**"Failed to start process" or "Process start failed"**
- Verify BPMN process is deployed on Operaton engine
- Check process key matches exactly (case-sensitive)
- Ensure base endpoint URL is correct for process execution
- Verify input variables match process start event requirements

**"No decision flow data available"**
- Ensure process has completed execution
- Check that process includes decision tasks
- Verify "Show Decision Flow Summary" is enabled
- Check process instance ID is being stored correctly

**"Error loading decision flow summary"**
- Verify Operaton engine history API is accessible
- Check process instance exists and has completed
- Ensure decision flow cache is not corrupted (clear cache if needed)

### Result Population Issues ✨ ENHANCED

**"No field found for result: [field_name]"**
- Verify result field mapping is configured correctly
- Check that target form field exists and is visible
- Ensure field ID matches exactly
- **Process Mode**: Verify process variables are being extracted correctly

**"No valid results found in API response"**
- **Direct Mode**: Check DMN decision table output structure
- **Process Mode**: Verify process completion and variable extraction
- Review debug logs for variable extraction details

### Decision Flow Display Issues ✨ NEW

**Decision flow summary not appearing on page 3**
- Verify Process Execution mode is enabled
- Check "Show Decision Flow Summary" is enabled in configuration
- Ensure user has completed evaluation on previous page
- Check that process instance ID is stored correctly

**Excel-style tables not displaying correctly**
- Clear browser cache and reload page
- Check for CSS conflicts with theme
- Verify responsive design settings
- Ensure decision flow data is loading correctly

### API Connection Issues ✨ ENHANCED

**Connection testing failures**
- **Decision Mode**: Test `{base_url}/decision-definition/key/{decision_key}/evaluate`
- **Process Mode**: Test `{base_url}/process-definition/key/{process_key}/start`
- Verify DMN engine/BPMN process deployment status
- Check network firewall and security settings
- Validate authentication if required

### Performance Issues ✨ NEW

**Slow decision flow loading**
- Check Operaton engine performance and load
- Review decision flow cache settings
- Consider reducing process complexity if possible
- Monitor API response times for optimization

**Process execution timeouts**
- Increase WordPress timeout settings if needed
- Optimize BPMN process execution time
- Consider process simplification for complex workflows
- Monitor Operaton engine resource usage

---

## Advanced Configuration

### Timezone Handling ✨ NEW
Decision flow timestamps are automatically converted to the WordPress site timezone:

```php
// Automatic conversion from UTC to local timezone
UTC: 2025-07-19T17:03:04.545+0000
Local: 2025-07-19 19:03:04 (CEST)
```

### Cache Management ✨ NEW
Decision flow summaries are cached for performance:

- **Default Cache**: 10 minutes for successful decision flow data
- **Error Cache**: 2 minutes for failed retrievals
- **Manual Refresh**: Cache-busting available via refresh button
- **Cache Clearing**: Available via admin interface when needed

### Process Variable Extraction ✨ NEW
The plugin uses multiple strategies for extracting process results:

1. **Direct Variable Access**: Simple variable name matching
2. **Nested Container Search**: Searches in result containers (heusdenpasResult, kindpakketResult, etc.)
3. **Comprehensive Variable Search**: Searches all process variables for result fields
4. **Historical API Fallback**: Uses Operaton history API when active variables unavailable

### Debug Features ✨ ENHANCED

#### Enable Debug Mode
Add to your `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

#### Debug Information Available
- **Process Execution Flow**: Complete logging of process start, monitoring, and completion
- **Variable Extraction**: Detailed logging of result extraction strategies
- **Decision Flow Retrieval**: API calls and response processing for decision summaries
- **Field Detection**: Form field discovery and mapping validation
- **Cache Operations**: Cache hit/miss and refresh operations

#### Admin Debug Interface
Access via **Operaton DMN** → **Update Debug** (when WP_DEBUG enabled):
- Real-time update monitoring
- Process execution debugging
- Decision flow analysis tools
- Cache management interface

---

## API Integration Details

### Direct Decision Evaluation Endpoint
```
POST {base_url}/decision-definition/key/{decision_key}/evaluate
Content-Type: application/json

{
  "variables": {
    "season": {"value": "Fall", "type": "String"},
    "guestCount": {"value": 4, "type": "Integer"}
  }
}
```

### Process Execution Endpoint ✨ NEW
```
POST {base_url}/process-definition/key/{process_key}/start
Content-Type: application/json

{
  "variables": {
    "geboortedatumAanvrager": {"value": "1987-12-20", "type": "String"},
    "aanvragerAlleenstaand": {"value": true, "type": "Boolean"},
    "maandelijksBrutoInkomenAanvrager": {"value": 1200, "type": "Integer"}
  }
}
```

### Decision Flow History Endpoint ✨ NEW
```
GET {base_url}/history/decision-instance?processInstanceId={process_id}&includeInputs=true&includeOutputs=true
```

### Process Variables Endpoint ✨ NEW
```
GET {base_url}/history/variable-instance?processInstanceId={process_id}
```

---

## Version Information

- **Plugin Version**: 1.0.0-beta.9
- **Gravity Forms Compatibility**: 2.4+
- **WordPress Compatibility**: 5.0+
- **PHP Requirements**: 7.4+
- **Operaton Engine Compatibility**: 7.x+ (Decision evaluation and Process execution)

---

## What's New in v1.0.0-beta.9 ✨

### Major Features Added
- **🚀 Process Execution Mode**: Complete BPMN process integration with decision flow analysis
- **📊 Excel-Style Decision Summaries**: Professional decision flow display on final form page
- **🔍 Comprehensive Process Tracking**: Complete traceability through process instance IDs
- **⚙️ Dual Execution Modes**: Choose between simple decisions or complex processes
- **📈 Enhanced Result Processing**: Advanced variable extraction from process executions

### Enhanced User Experience
- **Professional Decision Analysis**: Excel-quality tables with proper styling and organization
- **Intelligent Button Placement**: Mode-aware evaluation button positioning
- **Comprehensive Error Handling**: Enhanced error messages and recovery mechanisms
- **Advanced State Management**: Intelligent clearing of process data and form state

### Technical Improvements
- **Database Schema Extensions**: New columns for process execution configuration
- **API Integration Enhancements**: Support for both decision and process endpoints
- **Frontend JavaScript Enhancements**: Process-aware form handling and decision flow loading
- **Caching and Performance**: Intelligent caching with manual refresh capabilities

---

*This plugin now supports both simple decision evaluation and comprehensive process execution with professional decision flow analysis. Choose the mode that best fits your complexity requirements - from simple product recommendations to complex government application processing with complete transparency.*