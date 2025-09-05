<?php
namespace NHRROB\WPFatalTester\Detectors;

use NHRROB\WPFatalTester\Models\FatalError;

class PHPVersionCompatibilityDetector implements ErrorDetectorInterface {

    private array $deprecatedFeatures = [];
    private array $removedFeatures = [];
    private bool $insideScriptTag = false;
    private bool $insideHeredoc = false;
    private ?string $heredocEndMarker = null;
    private bool $insideMultiLineComment = false;
    private array $newFeatures = [];
    
    public function __construct() {
        $this->initializeDeprecatedFeatures();
        $this->initializeRemovedFeatures();
        $this->initializeNewFeatures();
    }
    
    public function getName(): string {
        return 'PHP Version Compatibility Detector';
    }

    public function detect(string $filePath, string $phpVersion, string $wpVersion): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $errors = [];
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);

        // Reset all state variables for each file
        $this->insideScriptTag = false;
        $this->insideHeredoc = false;
        $this->heredocEndMarker = null;
        $this->insideMultiLineComment = false;

        foreach ($lines as $lineNumber => $line) {
            $lineNumber++; // 1-based line numbers

            // Update parsing states
            $this->updateScriptTagState($line);
            $this->updateHeredocState($line);
            $this->updateMultiLineCommentState($line);

            // Skip if we're inside non-executable contexts
            if ($this->insideHeredoc || $this->insideMultiLineComment) {
                continue;
            }

            // Check for deprecated features
            $errors = array_merge($errors, $this->checkDeprecatedFeatures($line, $filePath, $lineNumber, $phpVersion));
            
            // Check for removed features
            $errors = array_merge($errors, $this->checkRemovedFeatures($line, $filePath, $lineNumber, $phpVersion));
            
            // Check for new features used with older PHP versions
            $errors = array_merge($errors, $this->checkNewFeatures($line, $filePath, $lineNumber, $phpVersion));
            
            // Check for syntax compatibility
            $errors = array_merge($errors, $this->checkSyntaxCompatibility($line, $filePath, $lineNumber, $phpVersion));
        }
        
        return $errors;
    }

    private function checkDeprecatedFeatures(string $line, string $filePath, int $lineNumber, string $phpVersion): array {
        $errors = [];

        // Update script tag state
        $this->updateScriptTagState($line);

        // Skip JavaScript code within PHP echo statements
        if ($this->insideScriptTag || $this->isJavaScriptContext($line)) {
            return $errors;
        }

        foreach ($this->deprecatedFeatures as $feature => $info) {
            if (preg_match($info['pattern'], $line)) {
                $deprecatedVersion = $info['deprecated'];

                // Check if this feature is also in the removed features list
                // If it's removed in the current PHP version, skip the deprecated warning
                $isRemoved = false;
                if (isset($this->removedFeatures[$feature])) {
                    $removedVersion = $this->removedFeatures[$feature]['removed'];
                    if (version_compare($phpVersion, $removedVersion, '>=')) {
                        $isRemoved = true;
                    }
                }

                if (!$isRemoved && version_compare($phpVersion, $deprecatedVersion, '>=')) {
                    $errors[] = new FatalError(
                        type: 'DEPRECATED_PHP_FEATURE',
                        message: "{$feature} is deprecated since PHP {$deprecatedVersion}",
                        file: $filePath,
                        line: $lineNumber,
                        severity: 'warning',
                        suggestion: $info['suggestion'] ?? "Consider using alternative approaches",
                        context: [
                            'feature' => $feature,
                            'deprecated_version' => $deprecatedVersion,
                            'php_version' => $phpVersion
                        ]
                    );
                }
            }
        }
        
        return $errors;
    }

    private function checkRemovedFeatures(string $line, string $filePath, int $lineNumber, string $phpVersion): array {
        $errors = [];

        // Update script tag state
        $this->updateScriptTagState($line);

        // Skip JavaScript code within PHP echo statements
        if ($this->insideScriptTag || $this->isJavaScriptContext($line)) {
            return $errors;
        }

        foreach ($this->removedFeatures as $feature => $info) {
            if (preg_match($info['pattern'], $line)) {
                $removedVersion = $info['removed'];
                
                if (version_compare($phpVersion, $removedVersion, '>=')) {
                    $errors[] = new FatalError(
                        type: 'REMOVED_PHP_FEATURE',
                        message: "{$feature} was removed in PHP {$removedVersion}",
                        file: $filePath,
                        line: $lineNumber,
                        severity: 'error',
                        suggestion: $info['suggestion'] ?? "This feature is no longer available",
                        context: [
                            'feature' => $feature,
                            'removed_version' => $removedVersion,
                            'php_version' => $phpVersion
                        ]
                    );
                }
            }
        }
        
        return $errors;
    }

    private function checkNewFeatures(string $line, string $filePath, int $lineNumber, string $phpVersion): array {
        $errors = [];

        foreach ($this->newFeatures as $feature => $info) {
            if (preg_match($info['pattern'], $line)) {
                $requiredVersion = $info['since'];

                // Normalize version comparison to handle cases like '7.4' vs '7.4.0'
                $normalizedPhpVersion = $this->normalizeVersion($phpVersion);
                $normalizedRequiredVersion = $this->normalizeVersion($requiredVersion);

                if (version_compare($normalizedPhpVersion, $normalizedRequiredVersion, '<')) {
                    // Determine severity based on how far behind the PHP version is
                    $severity = 'error';
                    $suggestion = "Upgrade PHP to version {$requiredVersion} or higher, or use an alternative";

                    // For features that are only slightly ahead, make them warnings instead of errors
                    if (in_array($feature, ['Named arguments', 'Match expression', 'Nullsafe operator', 'Constructor property promotion', 'Union types'])) {
                        $severity = 'warning';
                        $suggestion = "Consider upgrading PHP to version {$requiredVersion} or higher to use this feature";
                    }

                    $errors[] = new FatalError(
                        type: 'PHP_VERSION_REQUIREMENT',
                        message: "{$feature} requires PHP {$requiredVersion} or higher",
                        file: $filePath,
                        line: $lineNumber,
                        severity: $severity,
                        suggestion: $suggestion,
                        context: [
                            'feature' => $feature,
                            'required_version' => $requiredVersion,
                            'current_version' => $phpVersion
                        ]
                    );
                }
            }
        }

        return $errors;
    }

    private function checkSyntaxCompatibility(string $line, string $filePath, int $lineNumber, string $phpVersion): array {
        // Suppress unused parameter warnings - keeping method signature for interface compatibility
        unset($line, $filePath, $lineNumber, $phpVersion);

        $errors = [];

        // This method is now primarily for syntax-specific checks that aren't covered by newFeatures
        // Most feature checks are handled in checkNewFeatures() to avoid duplication

        return $errors;
    }

    private function initializeDeprecatedFeatures(): void {
        $this->deprecatedFeatures = [
            'each() function' => [
                'pattern' => '/(?<![.$])\beach\s*\(/',
                'deprecated' => '7.2.0',
                'suggestion' => 'Use foreach loop instead'
            ],
            'create_function()' => [
                'pattern' => '/\bcreate_function\s*\(/',
                'deprecated' => '7.2.0',
                'suggestion' => 'Use anonymous functions instead'
            ],
            'assert() with string argument' => [
                'pattern' => '/\bassert\s*\(\s*["\']/',
                'deprecated' => '7.2.0',
                'suggestion' => 'Use assert() with boolean expressions'
            ],
            '$php_errormsg variable' => [
                'pattern' => '/\$php_errormsg\b/',
                'deprecated' => '8.0.0',
                'suggestion' => 'Use error_get_last() instead'
            ],
        ];
    }

    private function initializeRemovedFeatures(): void {
        $this->removedFeatures = [
            'mysql extension' => [
                'pattern' => '/\bmysql_\w+\s*\(/',
                'removed' => '7.0.0',
                'suggestion' => 'Use mysqli or PDO instead'
            ],
            'ereg functions' => [
                'pattern' => '/\b(ereg|eregi|split|spliti|sql_regcase)\s*\(/',
                'removed' => '7.0.0',
                'suggestion' => 'Use preg_* functions instead'
            ],
            'mcrypt extension' => [
                'pattern' => '/\bmcrypt_\w+\s*\(/',
                'removed' => '7.2.0',
                'suggestion' => 'Use openssl or sodium extension instead'
            ],
            'each() function' => [
                'pattern' => '/(?<![.$])\beach\s*\(/',
                'removed' => '8.0.0',
                'suggestion' => 'Use foreach loop instead'
            ],
            'create_function()' => [
                'pattern' => '/\bcreate_function\s*\(/',
                'removed' => '8.0.0',
                'suggestion' => 'Use anonymous functions instead'
            ],
            'get_magic_quotes_gpc()' => [
                'pattern' => '/\bget_magic_quotes_gpc\s*\(/',
                'removed' => '8.0.0',
                'suggestion' => 'Magic quotes were removed, no replacement needed'
            ],
            'restore_include_path()' => [
                'pattern' => '/\brestore_include_path\s*\(/',
                'removed' => '8.0.0',
                'suggestion' => 'Use ini_restore() instead'
            ],
        ];
    }

    private function initializeNewFeatures(): void {
        $this->newFeatures = [
            'Null coalescing operator' => [
                'pattern' => '/\?\?/',
                'since' => '7.0.0'
            ],
            'Spaceship operator' => [
                'pattern' => '/\<\=\>/',
                'since' => '7.0.0'
            ],
            'Anonymous classes' => [
                'pattern' => '/new\s+class\s*[\(\{]/',
                'since' => '7.0.0'
            ],
            'Group use declarations' => [
                'pattern' => '/use\s+\w+\\{/',
                'since' => '7.0.0'
            ],
            'Null coalescing assignment' => [
                'pattern' => '/\?\?\=/',
                'since' => '7.4.0'
            ],
            'Arrow functions' => [
                'pattern' => '/fn\s*\(.*?\)\s*=>/',
                'since' => '7.4.0'
            ],
            'Typed properties' => [
                'pattern' => '/^\s*(private|protected|public)\s+\w+\s+\$\w+/',
                'since' => '7.4.0'
            ],
            'Match expression' => [
                'pattern' => '/\bmatch\s*\(/',
                'since' => '8.0.0'
            ],
            'Named arguments' => [
                'pattern' => '/\w+\s*\(\s*\w+\s*:\s*/',
                'since' => '8.0.0'
            ],
            'Nullsafe operator' => [
                'pattern' => '/\?\->/',
                'since' => '8.0.0'
            ],
            'Constructor property promotion' => [
                'pattern' => '/function\s+__construct\s*\([^)]*\b(private|protected|public)\s+/',
                'since' => '8.0.0'
            ],
            'Union types' => [
                'pattern' => '/:\s*\w+\|\w+/',
                'since' => '8.0.0'
            ],
            'Enums' => [
                'pattern' => '/\benum\s+\w+/',
                'since' => '8.1.0'
            ],
            'Readonly properties' => [
                'pattern' => '/\breadonly\s+(private|protected|public)/',
                'since' => '8.1.0'
            ],
            'First-class callable syntax' => [
                'pattern' => '/\w+::\w+\.\.\./',
                'since' => '8.1.0'
            ],
        ];
    }

    private function updateScriptTagState(string $line): void {
        // Check if we're entering a script tag (including within echo statements)
        if (preg_match('/<script[^>]*>/', $line)) {
            $this->insideScriptTag = true;
        }

        // Check if we're exiting a script tag (including within echo statements)
        if (preg_match('/<\/script>/', $line)) {
            $this->insideScriptTag = false;
        }
    }

    private function isJavaScriptContext(string $line): bool {
        // Check for JavaScript patterns within PHP echo statements
        return preg_match('/\$\s*\(\s*["\']/', $line) ||
               preg_match('/jQuery\s*\(/', $line) ||
               preg_match('/<script[^>]*>/', $line) ||
               preg_match('/echo\s+["\']<script/', $line) ||
               preg_match('/\$\(["\'][^"\']*["\']/', $line) ||
               preg_match('/\.each\s*\(/', $line);
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

    /**
     * Normalize version strings to ensure consistent comparison
     * Converts '7.4' to '7.4.0' for proper version_compare behavior
     */
    private function normalizeVersion(string $version): string {
        $parts = explode('.', $version);

        // Ensure we have at least major.minor.patch
        while (count($parts) < 3) {
            $parts[] = '0';
        }

        return implode('.', array_slice($parts, 0, 3));
    }
}
