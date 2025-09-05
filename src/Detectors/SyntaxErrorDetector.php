<?php
namespace NHRROB\WPFatalTester\Detectors;

use NHRROB\WPFatalTester\Models\FatalError;

class SyntaxErrorDetector implements ErrorDetectorInterface {
    
    public function getName(): string {
        return 'Syntax Error Detector';
    }

    public function detect(string $filePath, string $phpVersion, string $wpVersion): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $errors = [];
        
        // Check PHP syntax using php -l
        $errors = array_merge($errors, $this->checkPhpSyntax($filePath));
        
        // Check for common syntax issues
        $errors = array_merge($errors, $this->checkCommonSyntaxIssues($filePath));
        
        return $errors;
    }

    private function checkPhpSyntax(string $filePath): array {
        $errors = [];
        
        // Use php -l to check syntax
        $command = "php -l " . escapeshellarg($filePath) . " 2>&1";
        $output = shell_exec($command);
        
        if ($output && strpos($output, 'No syntax errors detected') === false) {
            // Parse the error output
            if (preg_match('/Parse error: (.+) in .+ on line (\d+)/', $output, $matches)) {
                $errors[] = new FatalError(
                    type: 'SYNTAX_ERROR',
                    message: trim($matches[1]),
                    file: $filePath,
                    line: (int)$matches[2],
                    severity: 'error',
                    suggestion: 'Fix the syntax error in the specified line'
                );
            } elseif (preg_match('/Fatal error: (.+) in .+ on line (\d+)/', $output, $matches)) {
                $errors[] = new FatalError(
                    type: 'FATAL_SYNTAX_ERROR',
                    message: trim($matches[1]),
                    file: $filePath,
                    line: (int)$matches[2],
                    severity: 'error',
                    suggestion: 'Fix the fatal syntax error in the specified line'
                );
            }
        }
        
        return $errors;
    }

    private function checkCommonSyntaxIssues(string $filePath): array {
        $errors = [];
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNumber => $line) {
            $lineNumber++; // 1-based line numbers
            
            // Check for missing semicolons (basic check)
            if ($this->isMissingSemicolon($line)) {
                $errors[] = new FatalError(
                    type: 'MISSING_SEMICOLON',
                    message: 'Possible missing semicolon',
                    file: $filePath,
                    line: $lineNumber,
                    severity: 'warning',
                    suggestion: 'Add semicolon at the end of the statement'
                );
            }
            
            // Check for unmatched brackets
            if ($this->hasUnmatchedBrackets($line)) {
                $errors[] = new FatalError(
                    type: 'UNMATCHED_BRACKETS',
                    message: 'Possible unmatched brackets',
                    file: $filePath,
                    line: $lineNumber,
                    severity: 'warning',
                    suggestion: 'Check bracket matching in this line'
                );
            }
        }
        
        return $errors;
    }

    private function isMissingSemicolon(string $line): bool {
        $line = trim($line);

        // Skip empty lines, comments, and control structures
        if (empty($line) ||
            strpos($line, '//') === 0 ||
            strpos($line, '/*') === 0 ||
            strpos($line, '*') === 0 ||
            strpos($line, '<?php') === 0 ||
            strpos($line, '<?') === 0 ||
            strpos($line, '?>') !== false ||
            preg_match('/^\s*(if|else|elseif|while|for|foreach|switch|case|default|try|catch|finally|class|function|interface|trait|namespace|use)\s*[\(\{]/', $line) ||
            preg_match('/[\{\}]\s*$/', $line) ||
            preg_match('/^\s*(public|private|protected)\s+(function|\$)/', $line) ||
            preg_match('/^\s*\/\*/', $line) ||
            preg_match('/\*\/\s*$/', $line)) {
            return false;
        }

        // Skip array declarations and other complex structures
        if (preg_match('/^\s*[\[\(]/', $line) || preg_match('/[\]\)]\s*$/', $line)) {
            return false;
        }

        // Skip lines that end with commas (likely part of multi-line arrays or function calls)
        if (preg_match('/,\s*(?:\/\/.*)?$/', $line)) {
            return false;
        }

        // Skip lines that are part of multi-line structures
        if (preg_match('/^\s*[\)\]\}]/', $line) || preg_match('/[\(\[\{]\s*(?:\/\/.*)?$/', $line)) {
            return false;
        }

        // Skip WordPress hook declarations and similar patterns
        if (preg_match('/add_(action|filter)\s*\(/', $line) ||
            preg_match('/do_action\s*\(/', $line) ||
            preg_match('/apply_filters\s*\(/', $line) ||
            preg_match('/wp_enqueue_(script|style)\s*\(/', $line)) {
            return false;
        }

        // Skip function calls that span multiple lines
        if (preg_match('/\w+\s*\([^)]*$/', $line)) {
            return false;
        }

        // Check if line should end with semicolon but doesn't (be more conservative)
        // Only flag simple variable assignments and basic statements
        if (preg_match('/^\s*\$\w+\s*=\s*[^=\(\[\{]*[^;{\},\(\[\]]\s*$/', $line) ||
            preg_match('/^\s*(echo|print|return|throw)\s+[^;{\}]*[^;{\},]\s*$/', $line) ||
            preg_match('/^\s*\w+\s*\([^)]*\)\s*[^;{\},]\s*$/', $line)) {
            // Additional check: make sure it's not part of a multi-line structure
            if (!preg_match('/\s*(function|class|if|while|for|foreach)\s*\(/', $line)) {
                return true;
            }
        }

        return false;
    }

    private function hasUnmatchedBrackets(string $line): bool {
        // Skip empty lines and comments
        $line = trim($line);
        if (empty($line) || strpos($line, '//') === 0 || strpos($line, '/*') === 0 || strpos($line, '*') === 0) {
            return false;
        }

        // Skip lines that are only closing brackets (with optional comments)
        if (preg_match('/^\s*[\}\]\)]+\s*(?:\/\/.*)?$/', $line)) {
            return false;
        }

        // Skip lines that are only opening brackets for multi-line structures
        if (preg_match('/^\s*[\{\[\(]+\s*(?:\/\/.*)?$/', $line)) {
            return false;
        }

        // Remove string literals and comments to avoid false positives
        $line = preg_replace('/["\'].*?["\']/', '', $line);
        $line = preg_replace('/\/\/.*$/', '', $line);
        $line = preg_replace('/\/\*.*?\*\//', '', $line);

        $openCount = substr_count($line, '(') + substr_count($line, '[') + substr_count($line, '{');
        $closeCount = substr_count($line, ')') + substr_count($line, ']') + substr_count($line, '}');

        // Skip common multi-line structures that are expected to have unmatched brackets
        if (preg_match('/^\s*(if|while|for|foreach|function|class|interface|trait|try|catch|finally|switch|case|default)\s*[\(\{]/', $line) ||
            preg_match('/^\s*(public|private|protected)\s+(function|static)\s/', $line) ||
            preg_match('/^\s*(abstract|final)\s+(class|function)\s/', $line) ||
            preg_match('/^\s*\$\w+\s*=\s*(function|new)\s*[\(\{]/', $line) ||
            preg_match('/add_action\s*\(\s*["\'][^"\']*["\']\s*,\s*function\s*\(\s*\)\s*\{/', $line) ||
            preg_match('/add_filter\s*\(\s*["\'][^"\']*["\']\s*,\s*function\s*\(\s*\)\s*\{/', $line) ||
            preg_match('/array\s*\(/', $line) ||
            preg_match('/\[\s*$/', $line)) {
            return false;
        }

        // Only flag as error if there's a significant imbalance in a single statement line
        $imbalance = abs($openCount - $closeCount);

        // Be more conservative - only flag obvious syntax errors
        // Don't flag lines that end with opening brackets (likely multi-line)
        // Don't flag lines that start with closing brackets (likely multi-line)
        if ($imbalance > 0 && $imbalance <= 3) {
            // Check if this looks like a complete statement that should be balanced
            if (preg_match('/;\s*$/', $line) && $openCount > 0 && $closeCount > 0) {
                return true;
            }
            // Check for obvious syntax errors like unmatched quotes followed by brackets
            if (preg_match('/["\'][^"\']*[\(\[\{]/', $line) || preg_match('/[\)\]\}][^"\']*["\']/', $line)) {
                return true;
            }
        }

        return false;
    }
}
