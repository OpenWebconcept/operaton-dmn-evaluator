# Changelog
All notable changes to this project will be documented in this file.

## [1.0.0-beta.3] - 2025-07-01

## Issues
- [Issue #1](https://git.open-regels.nl/showcases/operaton-dmn-evaluator/-/issues/1)

## 🔧 New Features Added:

### Separated URL Construction:

- `build_evaluation_endpoint()` method combines base URL + decision key
- Enhanced validation to prevent URL mistakes
- Real-time preview of full evaluation URL

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