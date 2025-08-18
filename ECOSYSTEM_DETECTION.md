# Plugin Ecosystem Detection

This document describes the new plugin ecosystem detection feature in wp-fatal-tester that reduces false positives when testing addon plugins for popular WordPress ecosystems like Elementor and WooCommerce.

## Overview

The wp-fatal-tester now automatically detects when a plugin is an addon/extension for popular WordPress plugin ecosystems and excludes dependency-related classes and functions from being flagged as fatal errors.

## Supported Ecosystems

### Elementor
- **Detection Methods:**
  - Plugin headers: `Elementor tested up to`, `Elementor Pro tested up to`, `Requires Plugins: elementor`
  - File patterns: `/widgets/`, `/controls/`, `/modules/`, `elementor`
  - Class usage: `Elementor\*`, `Controls_Manager`, `Widget_Base`, `Group_Control_*`
  - Function usage: `elementor_*` functions

- **Excluded Classes:**
  - Core classes: `Controls_Manager`, `Widget_Base`, `Plugin`, `Utils`, `Icons_Manager`
  - Group controls: `Group_Control_Typography`, `Group_Control_Background`, etc.
  - Widgets: `Widget_*` pattern
  - Namespaced classes: `Elementor\*`, `ElementorPro\*`

- **Excluded Functions:**
  - `elementor_*` pattern functions
  - `elementor_is_edit_mode()`, `elementor_get_post_id()`, etc.

### WooCommerce
- **Detection Methods:**
  - Plugin headers: `WC tested up to`, `WC requires at least`, `Requires Plugins: woocommerce`
  - File patterns: `/woocommerce/`, `/includes/wc-`, `wc-`
  - Class usage: `WC_*`, `WooCommerce\*`
  - Function usage: `wc_*`, `woocommerce_*`, `is_woocommerce*`

- **Excluded Classes:**
  - Core classes: `WC_Product`, `WC_Order`, `WC_Customer`, `WC_Cart`
  - Payment gateways: `WC_Payment_Gateway`
  - All `WC_*` pattern classes

- **Excluded Functions:**
  - `wc_*` pattern functions
  - `woocommerce_*` pattern functions
  - `is_woocommerce()`, `is_shop()`, `is_cart()`, etc.

## CLI Options

### Ecosystem Control Options

```bash
# Disable automatic ecosystem detection
fataltest --disable-ecosystem-detection

# Force specific ecosystems (comma-separated)
fataltest --force-ecosystem elementor,woocommerce

# Ignore all dependency-related errors
fataltest --ignore-dependency-errors
```

### Examples

```bash
# Test Elementor addon with automatic detection
fataltest my-elementor-addon

# Test with forced Elementor ecosystem
fataltest my-plugin --force-ecosystem elementor

# Test without ecosystem detection
fataltest my-plugin --disable-ecosystem-detection

# Test and ignore all dependency errors
fataltest my-plugin --ignore-dependency-errors
```

## How It Works

1. **Ecosystem Detection**: The tool scans plugin files for:
   - Plugin header information
   - File and directory patterns
   - Class and function usage patterns
   - Composer dependencies

2. **Exception Management**: Detected ecosystems enable whitelists of:
   - Classes that should not be flagged as undefined
   - Functions that should not be flagged as undefined
   - Pattern-based matching for dynamic class/function names

3. **Error Filtering**: During analysis:
   - Undefined class/function errors are checked against ecosystem exceptions
   - Matching errors are suppressed or marked as expected dependencies
   - Non-matching errors are still reported as usual

## Benefits

- **Reduced False Positives**: Eliminates noise from expected dependency classes
- **Focused Testing**: Highlights actual compatibility issues in your code
- **Better Developer Experience**: Cleaner output for addon plugin developers
- **Ecosystem Awareness**: Understands plugin relationships and dependencies

## Adding Custom Ecosystems

You can extend the system to support additional ecosystems by adding patterns to the `DependencyExceptionManager`:

```php
$exceptionManager = new DependencyExceptionManager();
$exceptionManager->addEcosystemExceptions('my-ecosystem', [
    'classes' => ['MyFramework_Base', 'MyFramework_Helper'],
    'class_patterns' => ['MyFramework_*'],
    'functions' => ['my_framework_init'],
    'function_patterns' => ['my_framework_*'],
]);
```

## Technical Implementation

### Key Components

1. **PluginEcosystemDetector**: Analyzes plugin structure and code to identify ecosystems
2. **DependencyExceptionManager**: Manages whitelists and exception rules for each ecosystem
3. **ClassConflictDetector**: Enhanced to use ecosystem context when checking for undefined classes
4. **CLI Integration**: New command-line options for ecosystem control

### Detection Accuracy

The detection system uses multiple signals to accurately identify ecosystems:
- Plugin headers are the most reliable indicator
- Code patterns provide secondary confirmation
- File structure analysis adds additional context
- Composer dependencies offer definitive proof

This multi-layered approach ensures high accuracy while minimizing false positives and negatives.

## Troubleshooting

### Ecosystem Not Detected
- Check plugin headers for ecosystem-specific fields
- Ensure your plugin uses standard ecosystem patterns
- Use `--force-ecosystem` to manually specify ecosystems

### Too Many Errors Suppressed
- Use `--disable-ecosystem-detection` to see all errors
- Review your plugin's actual dependencies
- Consider if your plugin truly requires the detected ecosystem

### False Ecosystem Detection
- Check for unintended ecosystem patterns in your code
- Use `--disable-ecosystem-detection` to bypass automatic detection
- Report false positives to help improve detection accuracy
