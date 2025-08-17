# Contributing to WordPress Fatal Error Tester

Thank you for your interest in contributing to the WordPress Fatal Error Tester! This document provides guidelines and information for contributors.

## How to Contribute

### Reporting Issues

1. **Search existing issues** first to avoid duplicates
2. **Use the issue template** when creating new issues
3. **Provide detailed information** including:
   - PHP version
   - WordPress version
   - Plugin being tested
   - Expected vs actual behavior
   - Steps to reproduce

### Submitting Pull Requests

1. **Fork the repository** and create a feature branch
2. **Follow coding standards** (PSR-12)
3. **Add tests** for new functionality
4. **Update documentation** as needed
5. **Ensure all tests pass** before submitting

### Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/wp-fatal-tester.git
cd wp-fatal-tester

# Install dependencies
composer install

# Run tests
php vendor/bin/phpunit
```

### Coding Standards

- Follow PSR-12 coding standards
- Use meaningful variable and method names
- Add PHPDoc comments for public methods
- Keep methods focused and single-purpose

### Adding New Detectors

To add a new error detector:

1. Create a new class implementing `ErrorDetectorInterface`
2. Add it to the `FatalTester::initializeDetectors()` method
3. Write comprehensive tests
4. Update documentation

### Testing

- Write unit tests for new functionality
- Test against multiple PHP versions (7.4, 8.0, 8.1, 8.2, 8.3)
- Test with various WordPress plugins
- Ensure backward compatibility

### Documentation

- Update README.md for new features
- Add examples for new functionality
- Update CHANGELOG.md following semantic versioning
- Include inline code documentation

## Code of Conduct

- Be respectful and inclusive
- Focus on constructive feedback
- Help others learn and grow
- Maintain a professional tone

## Questions?

Feel free to open an issue for questions or join the discussion in existing issues.

Thank you for contributing!
