# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-08-17

### Added
- Complete rewrite with comprehensive error detection
- PHP 7.4+ and WordPress 6.0+ support
- Multi-version compatibility testing (PHP 8.1, 8.2 and WordPress 6.5, 6.6)
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
