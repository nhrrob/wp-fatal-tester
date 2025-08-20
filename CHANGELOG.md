# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-08-20

### Added
- **Comprehensive Version Support**: Extended default testing to all major PHP versions (7.4, 8.0, 8.1, 8.2, 8.3) and WordPress versions (6.3, 6.4, 6.5, 6.6)
- **20-Combination Testing Matrix**: Now tests 20 different environment combinations by default for maximum compatibility coverage
- **Essential Addons Integration**: Extensively tested and validated with Essential Addons for Elementor free plugin
- **EA Testing Guide**: Added comprehensive testing guide (`EA_TESTING_GUIDE.md`) for Essential Addons integration

### Fixed
- **False Positive Elimination**: Fixed typed properties being incorrectly flagged as errors when testing PHP 7.4
- **Smart Severity Classification**: Named arguments and match expressions now properly classified as warnings instead of fatal errors
- **Version Comparison Logic**: Improved version normalization to handle cases like '7.4' vs '7.4.0' correctly

### Improved
- **Error Classification**: Better distinction between fatal errors and warnings for PHP 8.0+ features
- **Documentation**: Updated README with new default versions and EA plugin testing information
- **User Experience**: Reduced false positives from 344 to 0 for EA plugin testing

## [1.0.0] - 2024-08-17

### Added
- Complete rewrite with comprehensive error detection
- PHP 7.4+ and WordPress 6.0+ support
- Multi-version compatibility testing (PHP 7.4, 8.1, 8.2 and WordPress 6.5, 6.6)
- Detailed error reporting with suggestions
- Smart file scanning and filtering
- Color-coded terminal output
- Comprehensive error detection including:
  - Syntax errors and PHP compatibility issues
  - Undefined functions and classes
  - WordPress compatibility problems
  - Deprecated features and functions
  - Version-specific requirements

### Features
- **SyntaxErrorDetector**: PHP syntax validation
- **UndefinedFunctionDetector**: Function existence checking
- **ClassConflictDetector**: Class definition validation
- **PHPVersionCompatibilityDetector**: PHP version compatibility
- **WordPressCompatibilityDetector**: WordPress compatibility
- **FileScanner**: Smart PHP file discovery with exclusion rules
- **ErrorReporter**: Formatted output with severity levels and suggestions

### Technical Details
- Modular architecture with pluggable detectors
- PSR-4 autoloading
- Composer package with binary executables
- Cross-platform compatibility
- Comprehensive test coverage

### Installation
```bash
composer require --dev nhrrob/wp-fatal-tester
```

### Usage
```bash
# Test current directory
php vendor/bin/fataltest

# Test specific plugin
php vendor/bin/fataltest my-plugin
```
