# Changelog
All notable changes to this project will be documented in this file.

## [1.0.0-beta.9] - 2025-07-19

### 🚀 Major Feature: Process Execution with Decision Flow Analysis

#### Revolutionary Enhancement: Dual Execution Modes

##### 1. **Process Execution Mode** ✨ NEW
- **Complete BPMN Process Integration**: Execute full business processes that call multiple DMN decisions
- **Decision Flow Tracking**: Comprehensive tracking of all decision instances throughout process execution
- **Process Instance Management**: Automatic storage and retrieval of process instance IDs
- **Enhanced Result Extraction**: Advanced variable extraction from completed process instances using history API
- **Multi-Decision Orchestration**: Single process execution can involve dozens of interconnected decisions

##### 2. **Execution Mode Selection**
- **Direct Decision Evaluation**: Original single-decision evaluation (backward compatible)
- **Process Execution with Decision Flow**: New comprehensive process-based evaluation
- **Admin Toggle**: Simple radio button selection in configuration interface
- **Flexible Configuration**: Each form can use either mode based on complexity requirements

#### 🔍 Decision Flow Summary System

##### 1. **Excel-Style Results Display**
- **Third Page Integration**: Comprehensive decision flow summary automatically appears on final form page
- **Excel-Style Tables**: Clean, professional table layout with proper headers and borders
- **Input/Output Organization**: Clear separation of decision inputs and outputs in structured rows
- **Visual Data Typing**: Color-coded values (✅/❌ for booleans, purple for numbers, monospace for technical data)
- **Responsive Design**: Mobile-friendly tables that adapt to different screen sizes

##### 2. **Comprehensive Decision Analysis**
- **Complete Process Timeline**: Shows every decision made during process execution in chronological order
- **Variable Tracking**: Displays all input variables and output results for each decision
- **Activity Context**: Shows which BPMN activity triggered each decision evaluation
- **Evaluation Timestamps**: Precise timing information with timezone conversion
- **Process Statistics**: Summary showing total decisions, evaluations, and applied filters

##### 3. **Smart Data Filtering and Organization**
- **Final Compilation Priority**: Automatically prioritizes Activity_FinalResultCompilation results when available
- **Latest Evaluation Fallback**: Uses most recent evaluation for each decision when final compilation unavailable
- **Duplicate Elimination**: Intelligent filtering to show only relevant decision instances
- **Chronological Ordering**: Decisions displayed in execution order for logical flow understanding

#### 🔧 Technical Architecture Enhancements

##### Backend Process Integration
- **Enhanced handle_evaluation()**: Dual-mode evaluation supporting both decision and process execution
- **New handle_process_execution()**: Specialized method for BPMN process start and monitoring
- **Process Instance Storage**: Session-based and user meta storage for process tracking
- **Historical Variable Retrieval**: Advanced API integration for completed process variable extraction
- **Multi-Strategy Result Extraction**: Comprehensive search through process variables for result mapping

##### Database Schema Extensions
- **use_process Column**: Boolean flag for execution mode selection
- **process_key Column**: Storage for BPMN process definition keys
- **show_decision_flow Column**: Toggle for decision flow summary display
- **Automatic Migration**: Seamless database updates for existing installations
- **Backward Compatibility**: All existing configurations continue working unchanged

##### Frontend JavaScript Enhancements
- **Dynamic Button Control**: Intelligent button placement based on execution mode and form page
- **Process Data Management**: Session storage management for process instance tracking
- **Decision Flow Loading**: AJAX-based decision flow summary retrieval with caching
- **State Cleanup**: Automatic clearing of process data when form inputs change
- **Progressive Enhancement**: Graceful degradation for non-process configurations

#### 🎯 Enhanced User Experience

##### Configuration Interface Improvements
- **Execution Mode Selection**: Clear radio button interface for choosing evaluation approach
- **Process Key Configuration**: Dedicated input field for BPMN process definition keys
- **Decision Flow Toggle**: Optional decision flow summary enablement
- **Endpoint Preview**: Real-time URL preview for both decision and process endpoints
- **Enhanced Validation**: Mode-specific validation ensuring proper configuration

##### Form Integration Enhancements
- **Intelligent Button Placement**: Context-aware evaluation button positioning
- **Page-Specific Behavior**: Different behavior on evaluation pages vs. summary pages
- **Process Result Population**: Enhanced result extraction for complex process variables
- **Decision Flow Summary**: Comprehensive third-page decision analysis display
- **Emergency Button Recovery**: Automatic button visibility fixes for edge cases

##### Visual and UX Improvements
- **Professional Styling**: Excel-inspired table design with gradients and hover effects
- **Enhanced Notifications**: Improved success messages with process instance information
- **Loading States**: Professional loading indicators during decision flow retrieval
- **Refresh Functionality**: Manual refresh capability for decision flow data
- **Cache Management**: Intelligent caching with cache-busting support

#### 📊 Decision Flow Features Deep Dive

##### Summary Statistics Dashboard
![Decision Summary](./assets/images/decisionsummary.png)

##### Excel-Style Decision Tables
- **Professional Headers**: Clean "Variable" and "Value" column structure
- **Row Grouping**: Inputs and Outputs grouped with rowspan headers
- **Type-Specific Formatting**: 
  - Booleans: ✅ true / ❌ false with color coding
  - Numbers: Purple highlighting for numeric values
  - Strings: Standard text formatting
  - Null values: Italicized "null" indication

![Decision flow output](./assets/images/decisionflowoutput.png)

##### Metadata and Context
- **Evaluation Timestamps**: Timezone-converted timestamps (e.g., "2025-07-19 19:03:04 (CEST)")
- **Activity Information**: BPMN activity context for each decision
- **Process Instance ID**: Complete traceability to Operaton engine
- **Refresh Controls**: Manual refresh capability with loading states

#### 🔄 Enhanced API Integration

##### Process Execution Flow
1. **Process Start**: BPMN process initiation with input variables
2. **Completion Detection**: Automatic detection of process completion
3. **Variable Extraction**: Comprehensive variable retrieval from process history
4. **Result Mapping**: Advanced mapping of process variables to form fields
5. **Decision Flow Collection**: Gathering of all decision instances for summary

##### History API Integration
- **Historical Variable Access**: Complete process variable history retrieval
- **Decision Instance Tracking**: All decision evaluations throughout process lifecycle
- **Activity Context Preservation**: Maintaining BPMN activity information
- **Comprehensive Data Collection**: Input variables, output results, and metadata

#### 🔒 Compatibility and Migration

##### Backward Compatibility
- **Existing Configurations**: All existing direct decision evaluations continue working
- **Database Migration**: Automatic schema updates with new columns
- **Progressive Enhancement**: New features available without breaking existing functionality
- **Configuration Preservation**: No reconfiguration required for existing setups

##### Migration Features
- **Automatic Database Updates**: Seamless column additions during plugin updates
- **Default Mode Selection**: Direct decision evaluation remains default for existing configurations
- **Optional Upgrade Path**: Users can optionally upgrade to process execution mode
- **Zero Downtime**: Migration occurs transparently during normal plugin operation

#### 🚀 Performance and Reliability

##### Caching and Optimization
- **Decision Flow Caching**: Intelligent caching of decision flow data with configurable expiration
- **Cache Busting**: Manual cache refresh capability for real-time updates
- **Rate Limiting**: API call throttling to prevent excessive requests
- **Session Management**: Efficient process instance storage across form navigation

##### Error Handling and Recovery
- **Graceful Degradation**: Fallback to basic result display if decision flow fails
- **Comprehensive Logging**: Detailed error logging for troubleshooting
- **User-Friendly Messages**: Clear error messages for various failure scenarios
- **Emergency Recovery**: Automatic retry mechanisms for transient failures

#### 🔧 Developer Notes

##### Key Methods Added/Enhanced
- `handle_process_execution()`: New method for BPMN process execution
- `get_decision_flow_summary_html()`: Decision flow summary generation
- `format_decision_flow_summary()`: Excel-style formatting system
- `store_process_instance_id()`: Process instance tracking
- `format_evaluation_time()`: Timezone-aware timestamp formatting

##### Frontend Enhancements
- Enhanced button control with process-aware placement
- Decision flow AJAX loading with cache management
- Process data lifecycle management
- Excel-style CSS framework for professional tables

##### Configuration Extensions
- Process execution mode selection
- Process key configuration
- Decision flow summary toggle
- Enhanced endpoint URL construction

---

**Migration Notes**: Existing installations will automatically receive database schema updates. New "Process Execution" mode is available immediately without requiring reconfiguration of existing decision evaluations.

## [1.0.0-beta.8.1] - 2025-07-18

## Issues
- [Issue #6](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/issues/6)

#### Fixes breaking change of 1.0.0-beta.8
Provides solution for automatic database migration

#### For New Installations
- Plugin activation automatically creates the table with the correct schema
- No manual intervention needed

#### For Existing Installations
- First admin page visit triggers automatic migration
- Missing columns are added automatically
- Users see success without knowing migration happened

#### For Failed Migrations
- Admin shows clear error message
- Instructs user to deactivate/reactivate plugin
- Form editing is disabled until migration succeeds

## [1.0.0-beta.8] - 2025-07-18

## Issues
- [Issue #6](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/issues/6)


### 🆕 Major Feature: Multiple Result Fields Support

#### Key Features Added

##### 1. **Multiple Result Field Mapping**
- **Before**: Limited to one result field per configuration
- **After**: Support for mapping unlimited DMN result fields to different form fields
- **Benefit**: Single evaluation can populate multiple form fields simultaneously
- **Example**: `aanmerkingHeusdenPas` → Field 35, `aanmerkingKindPakket` → Field 36

##### 2. **Enhanced Admin Configuration Interface**
- **Added**: Dedicated "Result Field Mappings" section in admin form
- **Added**: Dynamic result mapping rows with add/remove functionality
- **Added**: Separate validation for input mappings vs result mappings
- **Added**: Real-time field validation and duplicate prevention
- **Benefit**: Clear separation between input variables and output results

##### 3. **Improved Database Schema**
- **Added**: `result_mappings` column for storing multiple result field configurations
- **Structure**: JSON format storing DMN result field names with corresponding form field IDs
- **Migration**: Automatic database schema updates with manual migration support
- **Cleanup**: Removed legacy single-result columns for cleaner schema

##### 4. **Advanced Result Processing**
- **Enhanced**: API response handling for multiple simultaneous results
- **Added**: Individual result field population with error handling per field
- **Added**: Comprehensive success notifications showing all populated results
- **Added**: Visual field highlighting for each populated result field
- **Benefit**: Users see exactly which results were populated and their values

##### 5. **Streamlined User Experience**
- **Enhanced**: Success notifications now show all results: "✅ Results populated (2): aanmerkingHeusdenPas: false, aanmerkingKindPakket: true"
- **Added**: Individual field highlighting for each result
- **Added**: Automatic scrolling to result fields
- **Added**: Clear error messages if specific result fields cannot be found
- **Benefit**: Immediate visual confirmation of all evaluation results

## 🔧 Technical Improvements

### Backend Enhancements
- **Updated**: `save_configuration()` method to handle multiple result mappings
- **Updated**: `handle_evaluation()` method to process multiple API results
- **Enhanced**: Configuration validation to require both input and result mappings
- **Added**: Result field existence validation in selected Gravity Form
- **Improved**: Error handling with specific messaging for missing result fields

### Frontend JavaScript Improvements
- **Replaced**: Single result detection with multiple result field processing
- **Added**: `findFieldOnCurrentPage()` function for precise field targeting
- **Enhanced**: Result population logic to handle multiple simultaneous results
- **Added**: Per-field error handling and success reporting
- **Improved**: Visual feedback system for multiple result confirmations

### Database Architecture
- **Schema**: Simplified structure without backward compatibility
- **Columns**: `result_mappings` (longtext) for multiple result storage
- **Validation**: Both input and result mappings required for configuration
- **Migration**: Manual database update with SQL script for clean deployment

### Admin Interface Updates
- **Added**: Result Field Mappings section with grid layout
- **Enhanced**: Form field selection with real-time validation
- **Added**: Duplicate result field prevention
- **Improved**: Visual separation between input mappings and result mappings
- **Added**: Dynamic row management (add/remove result mappings)

## 📋 Workflow Changes

### Previous Workflow (Single Result)
1. Configure one DMN result field mapping
2. Evaluation populates single form field
3. User sees one result value

### New Workflow (Multiple Results)
1. Configure multiple DMN result field mappings
2. Single evaluation populates multiple form fields simultaneously
3. User sees comprehensive success notification with all results
4. Visual highlighting confirms each populated field

## 🎯 Use Case Examples

### Heusdenpas en Kindpakket (Dutch Social Services)
```json
Input Variables:
- geboortedatumAanvrager: "1987-12-20"
- aanvragerAlleenstaand: true
- maandelijksBrutoInkomenAanvrager: 1200
- aanvragerHeeftKind4Tm17: true

DMN Evaluation Results:
- aanmerkingHeusdenPas: false (Field 35)
- aanmerkingKindPakket: true (Field 36)

User Experience:
✅ Results populated (2): aanmerkingHeusdenPas: false, aanmerkingKindPakket: true
```

### Multi-Decision Loan Processing
```json
Input Variables:
- income: 75000
- creditScore: 720
- loanAmount: 250000

DMN Evaluation Results:
- loanApproved: true (Field 10)
- interestRate: 3.5 (Field 11)
- loanTerm: 30 (Field 12)
- monthlyPayment: 1123.29 (Field 13)
```

## 🔒 Migration & Compatibility

### Database Migration
- **Manual SQL Update**: Simple ALTER TABLE command for existing installations
- **No Data Loss**: Existing configurations continue to work
- **Clean Schema**: Removed legacy columns for streamlined structure

### Configuration Migration
- **Backward Compatibility**: Removed for cleaner codebase
- **Simple Migration**: One-time configuration update required
- **Clear Instructions**: Step-by-step migration guide provided

## 📈 Benefits Summary

1. **Comprehensive Results**: Single evaluation provides complete decision outcomes
2. **Improved Efficiency**: Eliminate multiple API calls for related decisions
3. **Better UX**: Users see all relevant results immediately
4. **Flexible Configuration**: Support for any number of result fields
5. **Enhanced Feedback**: Visual confirmation of each populated result
6. **Cleaner Architecture**: Simplified, purpose-built database schema
7. **Scalable Design**: Easily extensible for future enhancements

## 🚀 Developer Notes

### Key Methods Updated
- `save_configuration()`: Multiple result mapping storage
- `handle_evaluation()`: Multi-result API response processing
- `enqueue_gravity_scripts()`: Enhanced frontend configuration
- Database schema: Streamlined without backward compatibility

### JavaScript Enhancements
- Enhanced AJAX success handling for multiple results
- Individual field targeting and population
- Comprehensive error handling per result field
- Visual feedback system for multiple confirmations

---

## [1.0.0-beta.7] - 2025-07-17

## Issues
- [Issue #5](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/issues/5)

## The key fixes in this release:

- Date Format Conversion: Converting Gravity Forms' DD-MM-YYYY format to ISO YYYY-MM-DD format that the DMN engine expects
- Including All Mapped Fields: Ensuring that all configured field mappings are sent to the DMN engine, even when they're null
- Conditional Field Validation: Properly handling optional fields like partner birth date when the user is single
- Added Radio Button Support: Included the radio button name field with auto-detection
- Improved UX:
    - Auto-suggests data types based on field type
    - Shows placeholder for radio button names based on DMN variable
    - Better responsive design for smaller screens
    - Clear visual hierarchy with grid layout
- Fixed Validation: The form validation now properly checks for duplicates without false positives
- Streamlined Interface: Single, clean field mapping section that's both functional and visually appealing

## [1.0.0-beta.6] - 2025-07-11

## Issues
- [Issue #5](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/issues/5)

## 🆕 New Features Added

### 1. **Current Page Result Population**
- **Before**: Results were displayed in a separate result container below the form
- **After**: Results are automatically populated into designated form fields on the same page
- **Benefit**: Immediate feedback without navigation, cleaner UX

### 2. **Enhanced Admin Configuration**
- **Added**: Result Display Field (Optional) dropdown
- **Added**: Evaluation Step selector  
- **Added**: Database schema auto-migration
- **Benefit**: More control over result placement and form behavior

### 3. **Smart Field Detection**
- **Added**: Automatic detection of result fields by label ("Desired Dish", "Result")
- **Added**: Configurable field mapping via admin dropdown
- **Added**: Multiple detection strategies with fallback options
- **Benefit**: Flexible setup - works with auto-detection or manual configuration

### 4. **Clean State Management**
- **Added**: Automatic result clearing when form inputs change
- **Added**: Result clearing on form navigation (Previous/Next)
- **Added**: Prevention of stale data display
- **Benefit**: Users always get fresh, relevant results

### 5. **Enhanced Visual Feedback**
- **Added**: Green success notifications with auto-dismiss
- **Added**: Field highlighting when populated with results
- **Added**: Smooth scrolling to result field
- **Benefit**: Clear visual confirmation of evaluation success

## 🔧 Technical Improvements

### Database Enhancements
- **Added**: `result_display_field` column for specific field targeting
- **Added**: `evaluation_step` column for step control
- **Added**: Automatic schema migration on plugin updates

### Frontend JavaScript Improvements
- **Replaced**: Complex multi-step navigation with simple current-page population
- **Added**: Input change monitoring for automatic result clearing
- **Added**: Enhanced field detection with multiple strategies
- **Improved**: Error handling and user feedback

### Admin Interface Updates
- **Added**: Form field visualization with clickable field tags
- **Added**: Real-time endpoint URL preview
- **Enhanced**: Field mapping interface with better field selection
- **Added**: Configuration validation with detailed error messages

## 📋 Workflow Changes

### Previous Workflow
1. Fill form → Click Evaluate → See result in container → Continue form

### New Workflow  
1. Fill form → Click Evaluate → Result populates field immediately → Continue form
2. Change input → Result automatically clears → Evaluate again for fresh result

## 🎯 User Experience Improvements

| Aspect | Before | After |
|--------|--------|-------|
| **Result Display** | Separate container | Direct field population |
| **Navigation** | Required manual progression | Immediate feedback |
| **State Management** | Manual result clearing | Automatic cleanup |
| **Visual Feedback** | Basic container display | Notifications + highlighting |
| **Configuration** | Basic field mapping | Advanced field selection + auto-detection |

## 🔒 Maintained Compatibility

- ✅ All existing configurations continue to work
- ✅ Same DMN API integration
- ✅ Same field mapping concepts
- ✅ Same admin interface structure
- ✅ Backward compatible database schema

## 📈 Benefits Summary

1. **Simpler Setup**: Auto-detection reduces configuration complexity
2. **Better UX**: Immediate result feedback without navigation
3. **Cleaner State**: No stale data confusion
4. **More Control**: Optional manual field specification
5. **Enhanced Feedback**: Clear visual confirmation of actions
6. **Reliable Operation**: Automatic cleanup prevents user confusion

## [1.0.0-beta.5] - 2025-07-07

## Issues
- [Issue #3](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/issues/3)

### Output improvement

Provides a much better output of the Update Debug Tests.

Example:

![Gitlab Test Output](./assets/images/output-example.png)

## [1.0.0-beta.4] - 2025-07-07

## Issues
- [Issue #2](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/issues/2)

### 🚀 Major Update: Auto-Update System

Provides an auto-update system that solves GitLab-to-WordPress plugin update corruption issues.

##### Auto-Update System Features::
- **Complete WordPress Extraction Control**: Intercepts and overrides WordPress's default update mechanisms
- **GitLab Integration**: Seamless updates directly from GitLab releases via API
- **Corruption Prevention**: Advanced encoding and folder structure fixes
- **Nuclear Fallback System**: Multiple layers of protection against update failures
- **Real-time Update Tracking**: Detailed logging of every update step
- **Debug Interface**: Advanced debugging tools for monitoring update process

## Debug Dashboard Overview

The Operaton DMN Evaluator plugin features a comprehensive debug dashboard specifically designed for monitoring, troubleshooting, and analyzing the auto-update system. This debugging infrastructure ensures reliable plugin updates from GitLab repositories.

### Debug Dashboard Features Summary:
- **Real-time Update Monitoring**: Tracks all WordPress update hooks and processes
- **Nuclear Override Mode (v11.5)**: Complete extraction system bypass for correct plugin installation
- **Corruption Detection**: Automatically identifies and fixes file corruption during updates
- **Directory Naming Fix**: Ensures correct `operaton-dmn-evaluator` folder naming regardless of GitLab archive structure
- **Comprehensive Logging**: Detailed debug logs with visual dashboard for troubleshooting
- **Emergency Recovery**: Multi-layered fallback strategies for failed updates
- **GitLab Integration**: Seamless connectivity with GitLab API for release detection
- **Admin Interface**: User-friendly debug dashboard accessible via **Operaton DMN** → **Update Debug**

### Key Technical Achievements:
- Intercepts and overrides WordPress extraction methods
- Removes unwanted GitLab artifacts (.github, .gitignore, vendor)
- Prevents file name corruption during ZIP extraction
- Provides real-time status monitoring and success reporting
- Includes automated cleanup and validation systems

---

## [1.0.0-beta.3] - 2025-07-01

## Issues
- [Issue #1](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/issues/1)

## 🔧 New Features Added:

### Separated URL Construction:

- `build_evaluation_endpoint()` method combines base URL + decision key
- Enhanced validation to prevent URL mistakes
- Real-time preview of full evaluation URL

![Separated URL Construction](assets/images/New-Config-URL.png)

### Enhanced Validation:

- Checks that decision key isn't already in base URL
- Validates URL format for Operaton engines
- Prevents common configuration mistakes

### Improved Testing:

- `test_full_endpoint_configuration()` method for comprehensive testing
- `ajax_test_full_config()` AJAX handler for admin interface
- Better error messages for different failure scenarios

### Helper Methods:

- `get_endpoint_examples()` provides configuration examples
- Enhanced logging with actual endpoint URLs used

## 📝 Example Configuration:

### Before:
```
DMN Endpoint URL: https://operatondev.open-regels.nl/engine-rest/decision-definition/key/dish/evaluate
Decision Key: dish (redundant)
```
### After:
```
DMN Base Endpoint URL: https://operatondev.open-regels.nl/engine-rest/decision-definition/key/
Decision Key: dish
→ Automatically builds: https://operatondev.open-regels.nl/engine-rest/decision-definition/key/dish/evaluate
```