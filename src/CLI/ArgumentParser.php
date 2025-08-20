<?php
namespace NHRROB\WPFatalTester\CLI;

class ArgumentParser {
    
    private array $options = [];
    private array $arguments = [];
    
    public function __construct() {
        $this->initializeDefaults();
    }
    
    private function initializeDefaults(): void {
        $this->options = [
            'plugin' => null,
            'php' => ['7.4', '8.0', '8.1', '8.2', '8.3'],
            'wp' => ['6.3', '6.4', '6.5', '6.6'],
            'show_all_errors' => false,
            'severity_filter' => ['error'], // Default to only fatal errors
            'help' => false,
            'verbose' => false,
            'no_colors' => false,
            'disable_ecosystem_detection' => false,
            'force_ecosystem' => null,
            'ignore_dependency_errors' => false,
        ];
    }
    
    public function parse(array $argv): array {
        $this->arguments = $argv;
        
        // Remove script name
        array_shift($this->arguments);
        
        // Parse arguments
        for ($i = 0; $i < count($this->arguments); $i++) {
            $arg = $this->arguments[$i];
            
            if ($this->isOption($arg)) {
                $i = $this->parseOption($arg, $i);
            } else {
                // First non-option argument is the plugin name
                if ($this->options['plugin'] === null) {
                    $this->options['plugin'] = $arg;
                }
            }
        }
        
        // Auto-detect plugin if not provided
        if ($this->options['plugin'] === null) {
            $this->options['plugin'] = basename(getcwd());
        }
        
        // Set severity filter based on show_all_errors flag
        if ($this->options['show_all_errors']) {
            $this->options['severity_filter'] = ['error', 'warning'];
        }
        
        return $this->options;
    }
    
    private function isOption(string $arg): bool {
        return strpos($arg, '-') === 0;
    }
    
    private function parseOption(string $arg, int $index): int {
        switch ($arg) {
            case '--show-all-errors':
            case '--all':
                $this->options['show_all_errors'] = true;
                break;
                
            case '--fatal-only':
                $this->options['show_all_errors'] = false;
                $this->options['severity_filter'] = ['error'];
                break;
                
            case '--severity':
                if (isset($this->arguments[$index + 1])) {
                    $severities = explode(',', $this->arguments[$index + 1]);
                    $this->options['severity_filter'] = array_map('trim', $severities);
                    return $index + 1;
                }
                break;
                
            case '--php':
                if (isset($this->arguments[$index + 1])) {
                    $versions = explode(',', $this->arguments[$index + 1]);
                    $this->options['php'] = array_map('trim', $versions);
                    return $index + 1;
                }
                break;
                
            case '--wp':
                if (isset($this->arguments[$index + 1])) {
                    $versions = explode(',', $this->arguments[$index + 1]);
                    $this->options['wp'] = array_map('trim', $versions);
                    return $index + 1;
                }
                break;
                
            case '--help':
            case '-h':
                $this->options['help'] = true;
                break;
                
            case '--verbose':
            case '-v':
                $this->options['verbose'] = true;
                break;
                
            case '--no-colors':
                $this->options['no_colors'] = true;
                break;

            case '--disable-ecosystem-detection':
                $this->options['disable_ecosystem_detection'] = true;
                break;

            case '--force-ecosystem':
                if (isset($this->arguments[$index + 1])) {
                    $ecosystems = explode(',', $this->arguments[$index + 1]);
                    $this->options['force_ecosystem'] = array_map('trim', $ecosystems);
                    return $index + 1;
                }
                break;

            case '--ignore-dependency-errors':
                $this->options['ignore_dependency_errors'] = true;
                break;
        }
        
        return $index;
    }
    
    public function getHelpText(): string {
        return <<<HELP
WordPress Fatal Error Tester

USAGE:
    fataltest [plugin-path] [options]

ARGUMENTS:
    plugin-path         Plugin name or absolute path to plugin directory (defaults to current directory name)

OPTIONS:
    --show-all-errors   Show all errors including warnings (default: fatal errors only)
    --all               Alias for --show-all-errors
    --fatal-only        Show only fatal errors (default behavior)
    --severity LEVELS   Comma-separated list of severity levels to show (error,warning)
    --php VERSIONS      Comma-separated list of PHP versions to test (default: 7.4,8.0,8.1,8.2,8.3)
    --wp VERSIONS       Comma-separated list of WordPress versions to test (default: 6.3,6.4,6.5,6.6)
    --verbose, -v       Enable verbose output
    --no-colors         Disable colored output
    --help, -h          Show this help message

ECOSYSTEM OPTIONS:
    --disable-ecosystem-detection    Disable automatic plugin ecosystem detection
    --force-ecosystem ECOSYSTEMS     Force specific ecosystems (elementor,woocommerce)
    --ignore-dependency-errors       Ignore all dependency-related errors

EXAMPLES:
    fataltest                           # Test current directory, fatal errors only
    fataltest my-plugin                 # Test specific plugin, fatal errors only
    fataltest /path/to/plugin           # Test plugin at absolute path, fatal errors only
    fataltest --show-all-errors         # Show all errors including warnings
    fataltest --severity error,warning  # Explicitly set severity levels
    fataltest --php 8.0,8.1,8.2        # Test against specific PHP versions
    fataltest --wp 6.4,6.5,6.6         # Test against specific WordPress versions
    fataltest --force-ecosystem elementor  # Force Elementor ecosystem detection
    fataltest --disable-ecosystem-detection  # Disable ecosystem detection
    fataltest --ignore-dependency-errors     # Ignore dependency-related errors

SEVERITY LEVELS:
    error    Fatal errors that will break plugin functionality
    warning  Warnings about deprecated features or potential issues

By default, only fatal errors (severity: error) are shown to focus on critical issues.
Use --show-all-errors to see warnings and other non-fatal issues.

HELP;
    }
}
