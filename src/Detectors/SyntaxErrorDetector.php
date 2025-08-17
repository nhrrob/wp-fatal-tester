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

        // Check if line should end with semicolon but doesn't (be more conservative)
        if (preg_match('/^\s*\$\w+\s*=.*[^;{\}]\s*$/', $line) ||
            preg_match('/^\s*(echo|print|return|throw)\s+.*[^;{\}]\s*$/', $line)) {
            return true;
        }

        return false;
    }

    private function hasUnmatchedBrackets(string $line): bool {
        // Skip empty lines and comments
        $line = trim($line);
        if (empty($line) || strpos($line, '//') === 0 || strpos($line, '/*') === 0 || strpos($line, '*') === 0) {
            return false;
        }

        // Remove string literals to avoid false positives
        $line = preg_replace('/["\'].*?["\']/', '', $line);

        $openCount = substr_count($line, '(') + substr_count($line, '[') + substr_count($line, '{');
        $closeCount = substr_count($line, ')') + substr_count($line, ']') + substr_count($line, '}');

        // Only flag as error if there's a significant imbalance and it's not a multi-line structure
        $imbalance = abs($openCount - $closeCount);
        return $imbalance > 0 && $imbalance <= 2 && !preg_match('/^\s*(if|while|for|foreach|function|class)\s*\(/', $line);
    }
}
