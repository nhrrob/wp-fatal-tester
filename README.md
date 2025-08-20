# WordPress Fatal Error Tester

A comprehensive CLI tool to detect fatal PHP errors and compatibility issues in WordPress plugins and themes. This tool helps developers identify potential fatal errors before deploying to production by testing code against different PHP and WordPress version combinations.

## Features

### 🔍 **Comprehensive Error Detection**
- **Syntax Errors**: Detects PHP syntax errors, missing semicolons, and bracket mismatches
- **Undefined Functions**: Identifies calls to undefined or removed functions
- **Class Conflicts**: Detects class redeclaration and undefined class usage
- **PHP Version Compatibility**: Tests against PHP 7.4, 8.0, 8.1, 8.2, and 8.3
- **WordPress Compatibility**: Tests against WordPress 6.0+ versions
- **Deprecated Features**: Warns about deprecated PHP and WordPress functions

### 📊 **Detailed Reporting**
- Color-coded error output with severity levels
- File location and line number information
- Contextual suggestions for fixing errors
- Comprehensive summary reports
- Version compatibility matrix

### ⚡ **Smart Analysis**
- Scans PHP files recursively
- Excludes common non-essential directories (node_modules, vendor, tests)
- Detects WordPress-specific patterns and functions
- Identifies version-specific syntax and features

## Installation

```bash
composer require --dev nhrrob/wp-fatal-tester
```

## Usage

### Basic Usage

Test the current directory (auto-detects plugin name, shows fatal errors only):
```bash
vendor/bin/fataltest
```

Test a specific plugin (shows fatal errors only):
```bash
vendor/bin/fataltest my-plugin
```

Show all errors including warnings:
```bash
vendor/bin/fataltest --show-all-errors
```

### Plugin Name Parameter

The plugin name parameter is **optional** when running the tool from within a WordPress plugin directory. The tool will automatically detect the current plugin name based on the directory structure. You only need to specify a plugin name when:

- Running from outside the plugin directory
- Testing a plugin located in a different path
- Overriding the auto-detected plugin name

### Command Line Options

```bash
# Show help
vendor/bin/fataltest --help

# Test with all error types (including warnings)
vendor/bin/fataltest my-plugin --show-all-errors

# Test specific PHP and WordPress versions
vendor/bin/fataltest my-plugin --php 8.0,8.1,8.2 --wp 6.3,6.4,6.5,6.6

# Custom severity filtering
vendor/bin/fataltest my-plugin --severity error,warning

# Disable colored output
vendor/bin/fataltest my-plugin --no-colors
```

### What It Tests

The tool automatically tests your code against **all major PHP and WordPress versions**:
- **PHP Versions**: 7.4, 8.0, 8.1, 8.2, 8.3 (configurable via `--php` option)
- **WordPress Versions**: 6.3, 6.4, 6.5, 6.6 (configurable via `--wp` option)

This comprehensive testing matrix ensures your plugin works across **20 different environment combinations** by default, providing maximum compatibility coverage for WordPress plugins.

### Default Behavior

**By default, the tool shows only fatal errors** to focus on critical issues that will break plugin functionality. This filtering approach helps developers concentrate on the most important issues first.

- **Fatal errors** (severity: `error`) are issues that will prevent your plugin from working
- **Warnings** (severity: `warning`) are about deprecated features or potential future issues

Use `--show-all-errors` to see warnings and other non-fatal issues when needed.

### Tested with Essential Addons for Elementor

wp-fatal-tester has been extensively tested with the **Essential Addons for Elementor** free plugin and passes all compatibility tests across PHP 7.4-8.3 and WordPress 6.3-6.6. The tool includes smart ecosystem detection to prevent false positives for Elementor and WooCommerce dependencies.

See `EA_TESTING_GUIDE.md` for detailed testing instructions and integration examples.

### Example Output

When you run `vendor/bin/fataltest` from within your plugin directory:

```
🚀 Running fatal test for plugin: my-awesome-plugin
   PHP versions: 7.4, 8.0, 8.1, 8.2, 8.3
   WP versions: 6.3, 6.4, 6.5, 6.6
   Plugin path: /path/to/my-awesome-plugin
   Filter: Fatal errors only (use --show-all-errors to see warnings)

▶️ Testing my-awesome-plugin on PHP 7.4, WP 6.3 (1/20)...
❌ Found 2 error(s) on PHP 8.1, WP 6.5 (15,847 total, filtered by severity)

📋 SYNTAX_ERROR (1 error(s)):
🔴 syntax error, unexpected identifier "create_function"
  Location: plugin.php:25
  💡 Suggestion: Fix the syntax error in the specified line

📋 REMOVED_PHP_FEATURE (1 error(s)):
🔴 create_function() was removed in PHP 8.0.0
  Location: plugin.php:25
  💡 Suggestion: Use anonymous functions instead

# Note: DEPRECATED_PHP_FEATURE warnings and undefined WordPress functions
# are filtered out by default. Use --show-all-errors to see them.
```

**Notice**: The example shows "15,847 total" errors but only 2 fatal errors are displayed. This demonstrates how the filtering helps you focus on critical issues while the high total count reflects WordPress function dependencies.

### Quick Start Tips

1. **Install in your plugin directory**: `composer require --dev nhrrob/wp-fatal-tester`
2. **Run from plugin root**: `vendor/bin/fataltest` (no plugin name needed)
3. **Focus on fatal errors first**: The default output shows only critical issues
4. **Don't worry about high error counts**: Thousands of errors are normal due to WordPress dependencies
5. **Use `--show-all-errors` sparingly**: Only when you need to see warnings and deprecated features

## Error Types Detected

### 🔴 **Fatal Errors (Must Fix)**
- **SYNTAX_ERROR**: PHP syntax errors that prevent code execution
- **UNDEFINED_FUNCTION**: Calls to functions that don't exist
- **UNDEFINED_CLASS**: Usage of classes that aren't defined
- **REMOVED_PHP_FEATURE**: Features removed in target PHP versions
- **VERSION_REQUIREMENT**: Features requiring newer PHP/WordPress versions

### 🟡 **Warnings (Should Fix)**
- **DEPRECATED_PHP_FEATURE**: PHP features deprecated in target versions
- **DEPRECATED_FUNCTION**: WordPress functions deprecated in target versions
- **DEPRECATED_HOOK**: WordPress hooks deprecated in target versions
- **MISSING_SEMICOLON**: Potential missing semicolons
- **UNMATCHED_BRACKETS**: Potential bracket mismatches

## Supported Versions

### PHP Versions
- ✅ PHP 7.4+
- ✅ PHP 8.0
- ✅ PHP 8.1
- ✅ PHP 8.2
- ✅ PHP 8.3

### WordPress Versions
- ✅ WordPress 6.0+
- ✅ WordPress 6.1
- ✅ WordPress 6.2
- ✅ WordPress 6.3
- ✅ WordPress 6.4
- ✅ WordPress 6.5
- ✅ WordPress 6.6

## Architecture

The tool is built with a modular architecture:

### Core Components
- **FatalTester**: Main orchestrator class
- **FileScanner**: Scans and filters PHP files
- **ErrorReporter**: Formats and displays results

### Error Detectors
- **SyntaxErrorDetector**: PHP syntax validation
- **UndefinedFunctionDetector**: Function existence checking
- **ClassConflictDetector**: Class definition validation
- **PHPVersionCompatibilityDetector**: PHP version compatibility
- **WordPressCompatibilityDetector**: WordPress compatibility

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## License

MIT License - see LICENSE file for details.

## Changelog

### v1.0.0
- ✅ Complete rewrite with comprehensive error detection
- ✅ PHP 7.4+ and WordPress 6.0+ support
- ✅ Multi-version compatibility testing
- ✅ Detailed error reporting with suggestions
- ✅ Smart file scanning and filtering
- ✅ Color-coded terminal output
