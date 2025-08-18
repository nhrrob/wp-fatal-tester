<?php
namespace NHRROB\WPFatalTester\Detectors;

use NHRROB\WPFatalTester\Models\FatalError;

class PHPVersionCompatibilityDetector implements ErrorDetectorInterface {

    private array $deprecatedFeatures = [];
    private array $removedFeatures = [];
    private bool $insideScriptTag = false;
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

        // Reset script tag state for each file
        $this->insideScriptTag = false;

        foreach ($lines as $lineNumber => $line) {
            $lineNumber++; // 1-based line numbers
            
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
                
                if (version_compare($phpVersion, $deprecatedVersion, '>=')) {
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
                
                if (version_compare($phpVersion, $requiredVersion, '<')) {
                    $errors[] = new FatalError(
                        type: 'PHP_VERSION_REQUIREMENT',
                        message: "{$feature} requires PHP {$requiredVersion} or higher",
                        file: $filePath,
                        line: $lineNumber,
                        severity: 'error',
                        suggestion: "Upgrade PHP to version {$requiredVersion} or higher, or use an alternative",
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
        $errors = [];
        
        // Check for PHP 8+ syntax in older versions
        if (version_compare($phpVersion, '8.0', '<')) {
            // Named arguments
            if (preg_match('/\w+\s*\(\s*\w+\s*:\s*/', $line)) {
                $errors[] = new FatalError(
                    type: 'PHP8_NAMED_ARGUMENTS',
                    message: 'Named arguments require PHP 8.0 or higher',
                    file: $filePath,
                    line: $lineNumber,
                    severity: 'error',
                    suggestion: 'Use positional arguments or upgrade to PHP 8.0+',
                    context: ['required_version' => '8.0', 'current_version' => $phpVersion]
                );
            }
            
            // Match expression
            if (preg_match('/\bmatch\s*\(/', $line)) {
                $errors[] = new FatalError(
                    type: 'PHP8_MATCH_EXPRESSION',
                    message: 'Match expressions require PHP 8.0 or higher',
                    file: $filePath,
                    line: $lineNumber,
                    severity: 'error',
                    suggestion: 'Use switch statement or upgrade to PHP 8.0+',
                    context: ['required_version' => '8.0', 'current_version' => $phpVersion]
                );
            }
            
            // Nullsafe operator
            if (preg_match('/\?\->/', $line)) {
                $errors[] = new FatalError(
                    type: 'PHP8_NULLSAFE_OPERATOR',
                    message: 'Nullsafe operator (?->) requires PHP 8.0 or higher',
                    file: $filePath,
                    line: $lineNumber,
                    severity: 'error',
                    suggestion: 'Use null checks or upgrade to PHP 8.0+',
                    context: ['required_version' => '8.0', 'current_version' => $phpVersion]
                );
            }
        }
        
        // Check for PHP 7.4+ syntax in older versions
        if (version_compare($phpVersion, '7.4', '<')) {
            // Arrow functions
            if (preg_match('/fn\s*\(.*?\)\s*=>/', $line)) {
                $errors[] = new FatalError(
                    type: 'PHP74_ARROW_FUNCTIONS',
                    message: 'Arrow functions require PHP 7.4 or higher',
                    file: $filePath,
                    line: $lineNumber,
                    severity: 'error',
                    suggestion: 'Use anonymous functions or upgrade to PHP 7.4+',
                    context: ['required_version' => '7.4', 'current_version' => $phpVersion]
                );
            }
            
            // Typed properties
            if (preg_match('/^\s*(private|protected|public)\s+\w+\s+\$\w+/', $line)) {
                $errors[] = new FatalError(
                    type: 'PHP74_TYPED_PROPERTIES',
                    message: 'Typed properties require PHP 7.4 or higher',
                    file: $filePath,
                    line: $lineNumber,
                    severity: 'error',
                    suggestion: 'Remove type hints from properties or upgrade to PHP 7.4+',
                    context: ['required_version' => '7.4', 'current_version' => $phpVersion]
                );
            }
        }
        
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
}
