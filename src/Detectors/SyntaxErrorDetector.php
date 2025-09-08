<?php
namespace NHRROB\WPFatalTester\Detectors;

use NHRROB\WPFatalTester\Models\FatalError;

class SyntaxErrorDetector implements ErrorDetectorInterface {

    private bool $insideHeredoc = false;
    private ?string $heredocEndMarker = null;
    private bool $insideMultiLineComment = false;
    private ?string $pluginRoot = null;

    public function getName(): string {
        return 'Syntax Error Detector';
    }

    /**
     * Set the plugin root path for relative path calculation
     *
     * @param string $pluginRoot Absolute path to the plugin root directory
     * @return void
     */
    public function setPluginRoot(string $pluginRoot): void {
        $this->pluginRoot = $pluginRoot;
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
                    suggestion: 'Fix the syntax error in the specified line',
                    pluginRoot: $this->pluginRoot
                );
            } elseif (preg_match('/Fatal error: (.+) in .+ on line (\d+)/', $output, $matches)) {
                $errors[] = new FatalError(
                    type: 'FATAL_SYNTAX_ERROR',
                    message: trim($matches[1]),
                    file: $filePath,
                    line: (int)$matches[2],
                    severity: 'error',
                    suggestion: 'Fix the fatal syntax error in the specified line',
                    pluginRoot: $this->pluginRoot
                );
            }
        }
        
        return $errors;
    }

    private function checkCommonSyntaxIssues(string $filePath): array {
        $errors = [];
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        // Reset state variables for each file
        $this->insideHeredoc = false;
        $this->heredocEndMarker = null;
        $this->insideMultiLineComment = false;

        foreach ($lines as $lineNumber => $line) {
            $lineNumber++; // 1-based line numbers

            // Update parsing states
            $this->updateHeredocState($line);
            $this->updateMultiLineCommentState($line);

            // Skip if we're inside non-executable contexts
            if ($this->insideHeredoc || $this->insideMultiLineComment) {
                continue;
            }

            // Check for missing semicolons (basic check)
            if ($this->isMissingSemicolon($line)) {
                $errors[] = new FatalError(
                    type: 'MISSING_SEMICOLON',
                    message: 'Possible missing semicolon',
                    file: $filePath,
                    line: $lineNumber,
                    severity: 'warning',
                    suggestion: 'Add semicolon at the end of the statement',
                    pluginRoot: $this->pluginRoot
                );
            }

            // Check for unmatched brackets
            // Check for unmatched brackets
            if ($this->hasUnmatchedBrackets($line)) {
                $errors[] = new FatalError(
                    type: 'UNMATCHED_BRACKETS',
                    message: 'Possible unmatched brackets',
                    file: $filePath,
                    line: $lineNumber,
                    severity: 'warning',
                    suggestion: 'Check bracket matching in this line',
                    pluginRoot: $this->pluginRoot
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

        // Skip HEREDOC/NOWDOC start lines
        if (preg_match('/<<<\s*(["\']?)(\w+)\1\s*$/', $line)) {
            return false;
        }

        // Skip PHPDoc comments
        if (preg_match('/^\s*\*\s*@/', $line)) {
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

        // Skip multi-line string literals in echo/print statements
        // These are lines that start with echo/print and an opening quote but don't have a closing quote + semicolon
        if (preg_match('/^\s*(echo|print)\s+/', $line)) {
            // Check if this looks like the start of a multi-line string
            // Count quotes to see if string is not closed
            $singleQuotes = substr_count($line, "'");
            $doubleQuotes = substr_count($line, '"');

            // If line starts with echo and has an odd number of quotes (unclosed string), skip it
            if (($singleQuotes % 2 === 1) || ($doubleQuotes % 2 === 1)) {
                return false;
            }
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
            preg_match('/^\s*(echo|print|throw)\s+[^;{\}]*[^;{\},]\s*$/', $line) ||
            preg_match('/^\s*\w+\s*\([^)]*\)\s*[^;{\},]\s*$/', $line)) {
            // Additional check: make sure it's not part of a multi-line structure
            if (!preg_match('/\s*(function|class|if|while|for|foreach)\s*\(/', $line)) {
                return true;
            }
        }

        // Skip multi-line return statements that end with logical operators
        if (preg_match('/^\s*return\s+.*(\|\||&&|or|and)\s*$/', $line)) {
            return false;
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

        // Skip multi-line function calls and method calls (check BEFORE string removal)
        // Pattern 1: Lines that end with a comma (likely continuation of function arguments)
        if (preg_match('/,\s*(?:\/\/.*)?$/', $line)) {
            return false;
        }

        // Pattern 2: Method calls that span multiple lines (e.g., $this->method( 'arg1', 'arg2',)
        // More flexible pattern to catch various method call formats
        if (preg_match('/\$\w+\s*->\s*\w+\s*\(.*[^)]\s*$/', $line)) {
            return false;
        }

        // Pattern 3: Function calls that span multiple lines (e.g., function_name( 'arg1', 'arg2',)
        // More flexible pattern to catch various function call formats
        if (preg_match('/\w+\s*\(.*[^)]\s*$/', $line) && !preg_match('/;\s*$/', $line)) {
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

        // Pattern 4: Lines that are clearly continuation of multi-line calls (indented and start with arguments)
        if (preg_match('/^\s+[^=\w\$]/', $line) && ($openCount > 0 || $closeCount > 0)) {
            return false;
        }

        // Pattern 5: Lines that are continuation/closing lines of multi-line method calls
        // These typically have more closing brackets than opening brackets and end with );
        // Note: line is already trimmed, so we don't check for leading whitespace
        if (preg_match('/.*\)\s*;\s*$/', $line) && $closeCount > $openCount) {
            return false;
        }

        // Pattern 6: Lines that end with opening brackets but don't end with semicolon (multi-line structures)
        if (preg_match('/[\(\[\{]\s*(?:\/\/.*)?$/', $line) && !preg_match('/;\s*$/', $line)) {
            return false;
        }

        // Pattern 7: Lines that start with closing brackets (likely end of multi-line structure)
        if (preg_match('/^\s*[\)\]\}]/', $line)) {
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

    private function updateHeredocState(string $line): void {
        // If we're already inside a heredoc, check for the end marker
        if ($this->insideHeredoc && $this->heredocEndMarker !== null) {
            // Check if this line contains only the end marker (possibly with whitespace)
            if (preg_match('/^\s*' . preg_quote($this->heredocEndMarker, '/') . '\s*;?\s*$/', $line)) {
                $this->insideHeredoc = false;
                $this->heredocEndMarker = null;
            }
            return;
        }

        // Check if we're starting a heredoc or nowdoc
        if (preg_match('/<<<\s*(["\']?)(\w+)\1\s*$/', $line, $matches)) {
            $this->insideHeredoc = true;
            $this->heredocEndMarker = $matches[2];
        }
    }

    private function updateMultiLineCommentState(string $line): void {
        // Check if we're starting a multi-line comment
        if (preg_match('/\/\*/', $line) && !preg_match('/\/\*.*?\*\//', $line)) {
            $this->insideMultiLineComment = true;
        }

        // Check if we're ending a multi-line comment
        if ($this->insideMultiLineComment && preg_match('/\*\//', $line)) {
            $this->insideMultiLineComment = false;
        }
    }
}
