<?php
namespace NHRROB\WPFatalTester;

use NHRROB\WPFatalTester\Detectors\ErrorDetectorInterface;
use NHRROB\WPFatalTester\Detectors\SyntaxErrorDetector;
use NHRROB\WPFatalTester\Detectors\UndefinedFunctionDetector;
use NHRROB\WPFatalTester\Detectors\ClassConflictDetector;
use NHRROB\WPFatalTester\Detectors\WordPressCompatibilityDetector;
use NHRROB\WPFatalTester\Detectors\PHPVersionCompatibilityDetector;
use NHRROB\WPFatalTester\Scanner\FileScanner;
use NHRROB\WPFatalTester\Reporter\ErrorReporter;

class FatalTester {
    private array $detectors = [];
    private FileScanner $scanner;
    private ErrorReporter $reporter;

    public function __construct() {
        $this->scanner = new FileScanner();
        $this->reporter = new ErrorReporter();
        $this->initializeDetectors();
    }

    private function initializeDetectors(): void {
        $this->detectors = [
            new SyntaxErrorDetector(),
            new UndefinedFunctionDetector(),
            new ClassConflictDetector(),
            new WordPressCompatibilityDetector(),
            new PHPVersionCompatibilityDetector(),
        ];
    }

    public function run(array $options): void {
        echo "🚀 Running fatal test for plugin: {$options['plugin']}\n";
        echo "   PHP versions: " . implode(', ', $options['php']) . "\n";
        echo "   WP versions: " . implode(', ', $options['wp']) . "\n";
        echo "   Plugin path: " . $this->getPluginPath($options['plugin']) . "\n\n";

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

                $errors = $this->testPluginCompatibility($pluginPath, $php, $wp);

                if (!empty($errors)) {
                    echo "❌ Found " . count($errors) . " error(s) on PHP {$php}, WP {$wp}\n";
                    $allErrors["{$php}-{$wp}"] = $errors;
                    $this->reporter->displayErrors($errors, $php, $wp);
                } else {
                    echo "✅ Passed on PHP {$php}, WP {$wp}\n";
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

    private function testPluginCompatibility(string $pluginPath, string $phpVersion, string $wpVersion): array {
        $files = $this->scanner->scanDirectory($pluginPath);
        $allErrors = [];

        foreach ($files as $file) {
            foreach ($this->detectors as $detector) {
                $errors = $detector->detect($file, $phpVersion, $wpVersion);
                $allErrors = array_merge($allErrors, $errors);
            }
        }

        return $allErrors;
    }

    private function displaySummary(array $allErrors, array $options): void {
        $this->reporter->displaySummaryReport($allErrors, $options);
    }
}
