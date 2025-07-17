# Changelog
All notable changes to this project will be documented in this file.

## [1.0.0-beta.7] - 2025-07-11

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