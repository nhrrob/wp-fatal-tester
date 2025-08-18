<?php
namespace NHRROB\WPFatalTester;

use NHRROB\WPFatalTester\Detectors\ErrorDetectorInterface;
use NHRROB\WPFatalTester\Detectors\SyntaxErrorDetector;
use NHRROB\WPFatalTester\Detectors\UndefinedFunctionDetector;
use NHRROB\WPFatalTester\Detectors\ClassConflictDetector;
use NHRROB\WPFatalTester\Detectors\WordPressCompatibilityDetector;
use NHRROB\WPFatalTester\Detectors\PHPVersionCompatibilityDetector;
use NHRROB\WPFatalTester\Detectors\PluginEcosystemDetector;
use NHRROB\WPFatalTester\Exceptions\DependencyExceptionManager;
use NHRROB\WPFatalTester\Scanner\FileScanner;
use NHRROB\WPFatalTester\Reporter\ErrorReporter;

class FatalTester {
    private array $detectors = [];
    private FileScanner $scanner;
    private ErrorReporter $reporter;
    private array $severityFilter = ['error']; // Default to fatal errors only
    private PluginEcosystemDetector $ecosystemDetector;
    private DependencyExceptionManager $exceptionManager;

    public function __construct(array $severityFilter = ['error'], bool $useColors = true) {
        $this->severityFilter = $severityFilter;
        $this->scanner = new FileScanner();
        $this->reporter = new ErrorReporter($useColors);
        $this->ecosystemDetector = new PluginEcosystemDetector();
        $this->exceptionManager = new DependencyExceptionManager();
        $this->initializeDetectors();
    }

    private function initializeDetectors(): void {
        $this->detectors = [
            new SyntaxErrorDetector(),
            new UndefinedFunctionDetector(),
            new ClassConflictDetector($this->exceptionManager),
            new WordPressCompatibilityDetector(),
            new PHPVersionCompatibilityDetector(),
        ];
    }

    public function run(array $options): void {
        echo "🚀 Running fatal test for plugin: {$options['plugin']}\n";
        echo "   PHP versions: " . implode(', ', $options['php']) . "\n";
        echo "   WP versions: " . implode(', ', $options['wp']) . "\n";
        echo "   Plugin path: " . $this->getPluginPath($options['plugin']) . "\n";

        // Detect and display ecosystems
        $pluginPath = $this->getPluginPath($options['plugin']);
        $detectedEcosystems = $this->ecosystemDetector->detectEcosystems($pluginPath);
        if (!empty($detectedEcosystems)) {
            echo "   Detected ecosystems: " . implode(', ', $detectedEcosystems) . "\n";
        }

        // Display filtering information
        if (count($this->severityFilter) === 1 && $this->severityFilter[0] === 'error') {
            echo "   Filter: Fatal errors only (use --show-all-errors to see warnings)\n";
        } elseif (count($this->severityFilter) < 2) {
            echo "   Filter: Severity levels: " . implode(', ', $this->severityFilter) . "\n";
        } else {
            echo "   Filter: All error types\n";
        }
        echo "\n";

        $pluginPath = $this->getPluginPath($options['plugin']);
        if (!$this->validatePluginPath($pluginPath)) {
            echo "❌ Plugin path not found or invalid: {$pluginPath}\n";
            return;
        }

        $allErrors = [];
        $totalTests = count($options['php']) * count($options['wp']);
        $currentTest = 0;

        foreach ($options['php'] as $php) {
            foreach ($options['wp'] as $wp) {
                $currentTest++;
                echo "▶️ Testing {$options['plugin']} on PHP {$php}, WP {$wp} ({$currentTest}/{$totalTests})...\n";

                $errors = $this->testPluginCompatibility($pluginPath, $php, $wp, $options);
                $filteredErrors = $this->filterErrorsBySeverity($errors);

                if (!empty($filteredErrors)) {
                    $totalErrors = count($errors);
                    $filteredCount = count($filteredErrors);

                    if ($totalErrors === $filteredCount) {
                        echo "❌ Found {$filteredCount} error(s) on PHP {$php}, WP {$wp}\n";
                    } else {
                        echo "❌ Found {$filteredCount} error(s) on PHP {$php}, WP {$wp} ({$totalErrors} total, filtered by severity)\n";
                    }

                    $allErrors["{$php}-{$wp}"] = $filteredErrors;
                    $this->reporter->displayErrors($filteredErrors, $php, $wp);
                } else {
                    $totalErrors = count($errors);
                    if ($totalErrors > 0) {
                        echo "✅ Passed on PHP {$php}, WP {$wp} (no errors matching severity filter, {$totalErrors} total errors filtered out)\n";
                    } else {
                        echo "✅ Passed on PHP {$php}, WP {$wp}\n";
                    }
                }
                echo "\n";
            }
        }

        $this->displaySummary($allErrors, $options);
    }

    private function getPluginPath(string $plugin): string {
        // Try different possible plugin paths
        $possiblePaths = [
            getcwd(), // Current directory
            getcwd() . '/' . $plugin, // Plugin subdirectory
            dirname(getcwd()) . '/' . $plugin, // Parent directory
            '/wp-content/plugins/' . $plugin, // Standard WP plugin directory
        ];

        foreach ($possiblePaths as $path) {
            if (is_dir($path) && $this->isValidPluginDirectory($path)) {
                return realpath($path);
            }
        }

        return getcwd(); // Fallback to current directory
    }

    private function isValidPluginDirectory(string $path): bool {
        // Check if directory contains PHP files
        $phpFiles = glob($path . '/*.php');
        return !empty($phpFiles);
    }

    private function validatePluginPath(string $path): bool {
        return is_dir($path) && is_readable($path);
    }

    private function testPluginCompatibility(string $pluginPath, string $phpVersion, string $wpVersion, array $options = []): array {
        $files = $this->scanner->scanDirectory($pluginPath);
        $allErrors = [];

        // Detect plugin ecosystems (unless disabled)
        $detectedEcosystems = [];
        if (!($options['disable_ecosystem_detection'] ?? false)) {
            $detectedEcosystems = $this->ecosystemDetector->detectEcosystems($pluginPath);
        }

        // Override with forced ecosystems if specified
        if (!empty($options['force_ecosystem'])) {
            $detectedEcosystems = $options['force_ecosystem'];
        }

        // Pass ecosystem information to detectors that support it
        foreach ($this->detectors as $detector) {
            if ($detector instanceof ClassConflictDetector) {
                $detector->setDetectedEcosystems($detectedEcosystems);
            }
        }

        foreach ($files as $file) {
            foreach ($this->detectors as $detector) {
                $errors = $detector->detect($file, $phpVersion, $wpVersion);
                $allErrors = array_merge($allErrors, $errors);
            }
        }

        // Filter out dependency errors if requested
        if ($options['ignore_dependency_errors'] ?? false) {
            $allErrors = array_filter($allErrors, function($error) use ($detectedEcosystems) {
                return $error->type !== 'UNDEFINED_CLASS' ||
                       !$this->exceptionManager->isClassExcepted($error->context['class'] ?? '', $detectedEcosystems);
            });
        }

        return $allErrors;
    }

    private function displaySummary(array $allErrors, array $options): void {
        // Add severity filter info to options for the summary report
        $options['severity_filter'] = $this->severityFilter;
        $this->reporter->displaySummaryReport($allErrors, $options);
    }

    /**
     * Filter errors by severity level
     *
     * @param array $errors Array of FatalError objects
     * @return array Filtered array of FatalError objects
     */
    private function filterErrorsBySeverity(array $errors): array {
        if (empty($this->severityFilter)) {
            return $errors;
        }

        return array_filter($errors, function($error) {
            return in_array($error->severity, $this->severityFilter);
        });
    }

    /**
     * Set the severity filter
     *
     * @param array $severityFilter Array of severity levels to include
     */
    public function setSeverityFilter(array $severityFilter): void {
        $this->severityFilter = $severityFilter;
    }

    /**
     * Get the current severity filter
     *
     * @return array Current severity filter
     */
    public function getSeverityFilter(): array {
        return $this->severityFilter;
    }
}
