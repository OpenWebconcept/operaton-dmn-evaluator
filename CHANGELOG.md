# Changelog
All notable changes to this project will be documented in this file.

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