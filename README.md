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

Test the current directory (auto-detects plugin name):
```bash
php vendor/bin/fataltest
```

Test a specific plugin:
```bash
php vendor/bin/fataltest my-plugin
```

### What It Tests

The tool automatically tests your code against:
- **PHP Versions**: 8.1, 8.2 (configurable)
- **WordPress Versions**: 6.5, 6.6 (configurable)

### Example Output

```
🚀 Running fatal test for plugin: my-awesome-plugin
   PHP versions: 8.1, 8.2
   WP versions: 6.5, 6.6
   Plugin path: /path/to/my-awesome-plugin

▶️ Testing my-awesome-plugin on PHP 8.1, WP 6.5 (1/4)...
❌ Found 3 error(s) on PHP 8.1, WP 6.5

📋 SYNTAX_ERROR (1 error(s)):
🔴 syntax error, unexpected identifier "create_function"
  Location: plugin.php:25
  💡 Suggestion: Fix the syntax error in the specified line

📋 DEPRECATED_PHP_FEATURE (1 error(s)):
🟡 create_function() is deprecated since PHP 7.2.0
  Location: plugin.php:25
  💡 Suggestion: Use anonymous functions instead

📋 REMOVED_PHP_FEATURE (1 error(s)):
🔴 create_function() was removed in PHP 8.0.0
  Location: plugin.php:25
  💡 Suggestion: Use anonymous functions instead
```

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
