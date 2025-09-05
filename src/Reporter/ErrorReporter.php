<?php
namespace NHRROB\WPFatalTester\Reporter;

use NHRROB\WPFatalTester\Models\FatalError;

class ErrorReporter {
    
    private array $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'reset' => "\033[0m",
        'bold' => "\033[1m",
        'dim' => "\033[2m",
    ];
    
    private bool $useColors = true;

    public function __construct(bool $useColors = true) {
        $this->useColors = $useColors && $this->supportsColors();
    }

    public function displayErrors(array $errors, string $phpVersion, string $wpVersion): void {
        if (empty($errors)) {
            return;
        }

        echo $this->colorize("   Errors found on PHP {$phpVersion}, WordPress {$wpVersion}:", 'yellow') . "\n";
        echo $this->colorize("   " . str_repeat('-', 50), 'dim') . "\n";

        $groupedErrors = $this->groupErrorsByType($errors);
        
        foreach ($groupedErrors as $type => $typeErrors) {
            echo $this->colorize("   📋 {$type} (" . count($typeErrors) . " error(s)):", 'cyan') . "\n";
            
            foreach ($typeErrors as $error) {
                $this->displayError($error);
            }
            echo "\n";
        }
    }

    public function displayError(FatalError $error): void {
        $severityIcon = $this->getSeverityIcon($error->severity);
        $severityColor = $this->getSeverityColor($error->severity);

        echo "      {$severityIcon} " . $this->colorize($error->message, $severityColor) . "\n";

        // Display absolute path
        $absolutePath = $error->getAbsoluteFilePath() . ':' . $error->line;
        echo "        " . $this->colorize("Location: {$absolutePath}", 'dim') . "\n";

        // Display relative path if different from absolute
        $relativePath = $error->getRelativeFilePath() . ':' . $error->line;
        if ($relativePath !== basename($error->file) . ':' . $error->line) {
            echo "        " . $this->colorize("Relative: {$relativePath}", 'dim') . "\n";
        }

        if ($error->suggestion) {
            echo "        " . $this->colorize("💡 Suggestion: {$error->suggestion}", 'blue') . "\n";
        }

        if (!empty($error->context)) {
            $contextInfo = $this->formatContext($error->context);
            if ($contextInfo) {
                echo "        " . $this->colorize("ℹ️  Context: {$contextInfo}", 'dim') . "\n";
            }
        }

        echo "\n";
    }

    public function displaySummaryReport(array $allErrors, array $options): void {
        echo $this->colorize("\n📊 DETAILED SUMMARY REPORT", 'bold') . "\n";
        echo $this->colorize(str_repeat('=', 50), 'dim') . "\n\n";

        // Display filtering information
        if (isset($options['severity_filter'])) {
            $severityFilter = $options['severity_filter'];
            if (count($severityFilter) === 1 && $severityFilter[0] === 'error') {
                echo $this->colorize("🔍 Filter: Showing fatal errors only (use --show-all-errors to see warnings)", 'blue') . "\n\n";
            } elseif (count($severityFilter) < 2) {
                echo $this->colorize("🔍 Filter: Showing severity levels: " . implode(', ', $severityFilter), 'blue') . "\n\n";
            } else {
                echo $this->colorize("🔍 Filter: Showing all error types", 'blue') . "\n\n";
            }
        }

        if (empty($allErrors)) {
            $filterText = isset($options['severity_filter']) && count($options['severity_filter']) === 1 && $options['severity_filter'][0] === 'error'
                ? "No fatal errors detected"
                : "No errors detected";
            echo $this->colorize("✅ Excellent! {$filterText}.", 'green') . "\n";
            echo "   Plugin: " . $this->colorize($options['plugin'], 'bold') . "\n";
            echo "   PHP versions tested: " . $this->colorize(implode(', ', $options['php']), 'cyan') . "\n";
            echo "   WordPress versions tested: " . $this->colorize(implode(', ', $options['wp']), 'cyan') . "\n";
            return;
        }

        $totalErrors = 0;
        $errorsByType = [];
        $errorsBySeverity = ['error' => 0, 'warning' => 0];
        
        foreach ($allErrors as $version => $errors) {
            $totalErrors += count($errors);
            
            foreach ($errors as $error) {
                $type = $error->type;
                $severity = $error->severity;
                
                if (!isset($errorsByType[$type])) {
                    $errorsByType[$type] = 0;
                }
                $errorsByType[$type]++;
                
                if (isset($errorsBySeverity[$severity])) {
                    $errorsBySeverity[$severity]++;
                }
            }
        }

        $errorTypeText = isset($options['severity_filter']) && count($options['severity_filter']) === 1 && $options['severity_filter'][0] === 'error'
            ? "Fatal errors"
            : "Errors";
        echo $this->colorize("❌ {$errorTypeText} detected: {$totalErrors} total", 'red') . "\n\n";
        
        echo $this->colorize("📈 Error Breakdown by Severity:", 'bold') . "\n";
        echo "   🔴 Errors: " . $this->colorize($errorsBySeverity['error'], 'red') . "\n";
        echo "   🟡 Warnings: " . $this->colorize($errorsBySeverity['warning'], 'yellow') . "\n\n";
        
        echo $this->colorize("📋 Error Breakdown by Type:", 'bold') . "\n";
        arsort($errorsByType);
        foreach ($errorsByType as $type => $count) {
            echo "   • {$type}: " . $this->colorize($count, 'cyan') . "\n";
        }
        
        echo "\n" . $this->colorize("🔍 Version Compatibility Matrix:", 'bold') . "\n";
        foreach ($allErrors as $version => $errors) {
            [$php, $wp] = explode('-', $version);
            $errorCount = count($errors);
            $status = $errorCount > 0 ? $this->colorize("❌ {$errorCount} error(s)", 'red') : $this->colorize("✅ Pass", 'green');
            echo "   PHP {$php} + WordPress {$wp}: {$status}\n";
        }
        
        echo "\n" . $this->colorize("💡 Recommendations:", 'bold') . "\n";
        echo "   1. Fix all " . $this->colorize("error-level", 'red') . " issues before deploying to production\n";
        echo "   2. Address " . $this->colorize("warning-level", 'yellow') . " issues to ensure future compatibility\n";
        echo "   3. Test your fixes by running the tool again\n";
        echo "   4. Consider setting up automated testing for continuous compatibility checking\n";
    }

    private function groupErrorsByType(array $errors): array {
        $grouped = [];
        
        foreach ($errors as $error) {
            $type = $error->type;
            if (!isset($grouped[$type])) {
                $grouped[$type] = [];
            }
            $grouped[$type][] = $error;
        }
        
        return $grouped;
    }

    private function getSeverityIcon(string $severity): string {
        return match ($severity) {
            'error' => '🔴',
            'warning' => '🟡',
            'info' => '🔵',
            default => '⚪',
        };
    }

    private function getSeverityColor(string $severity): string {
        return match ($severity) {
            'error' => 'red',
            'warning' => 'yellow',
            'info' => 'blue',
            default => 'white',
        };
    }



    private function formatContext(array $context): string {
        $contextParts = [];
        
        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $contextParts[] = "{$key}: {$value}";
            }
        }
        
        return implode(', ', $contextParts);
    }

    private function colorize(string $text, string $color): string {
        if (!$this->useColors || !isset($this->colors[$color])) {
            return $text;
        }
        
        return $this->colors[$color] . $text . $this->colors['reset'];
    }

    private function supportsColors(): bool {
        // Check if we're in a terminal that supports colors
        if (PHP_SAPI !== 'cli') {
            return false;
        }
        
        // Check environment variables
        if (getenv('NO_COLOR') !== false) {
            return false;
        }
        
        if (getenv('FORCE_COLOR') !== false) {
            return true;
        }
        
        // Check if stdout is a terminal
        if (function_exists('posix_isatty') && !posix_isatty(STDOUT)) {
            return false;
        }
        
        // Check TERM environment variable
        $term = getenv('TERM');
        if ($term && strpos($term, 'color') !== false) {
            return true;
        }
        
        return true; // Default to supporting colors
    }

    public function setUseColors(bool $useColors): void {
        $this->useColors = $useColors && $this->supportsColors();
    }
}
