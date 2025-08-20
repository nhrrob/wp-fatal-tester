<?php
namespace NHRROB\WPFatalTester\Detectors;

use NHRROB\WPFatalTester\Models\FatalError;
use NHRROB\WPFatalTester\Exceptions\DependencyExceptionManager;
use NHRROB\WPFatalTester\Analyzers\WordPressContextAnalyzer;

class UndefinedFunctionDetector implements ErrorDetectorInterface {

    private array $wordpressFunctions = [];
    private array $wordpressAdminFunctions = [];
    private array $phpFunctions = [];
    private bool $insideScriptTag = false;
    private DependencyExceptionManager $exceptionManager;
    private array $detectedEcosystems = [];
    private WordPressContextAnalyzer $contextAnalyzer;

    public function __construct(?DependencyExceptionManager $exceptionManager = null) {
        $this->exceptionManager = $exceptionManager ?? new DependencyExceptionManager();
        $this->contextAnalyzer = new WordPressContextAnalyzer();
        $this->initializeWordPressFunctions();
        $this->initializeWordPressAdminFunctions();
        $this->initializePHPFunctions();
    }

    /**
     * Set detected ecosystems for dependency exception handling
     *
     * @param array $ecosystems
     */
    public function setDetectedEcosystems(array $ecosystems): void {
        $this->detectedEcosystems = $ecosystems;
    }
    
    public function getName(): string {
        return 'Undefined Function Detector';
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

            // Update script tag state
            $this->updateScriptTagState($line);

            // Find function calls in the line
            $functionCalls = $this->extractFunctionCalls($line);
            
            foreach ($functionCalls as $functionName) {
                if ($this->isUndefinedFunction($functionName, $phpVersion, $wpVersion)) {
                    // Analyze context for WordPress admin functions
                    $context = 'unknown';
                    $severity = 'error';
                    $suggestion = $this->getSuggestionForFunction($functionName);

                    if (in_array($functionName, $this->wordpressAdminFunctions)) {
                        $context = $this->contextAnalyzer->analyzeContext($filePath, $lineNumber, $functionName);
                        $severity = $this->contextAnalyzer->getSeverityForContext($context, $functionName);
                        $suggestion = $this->contextAnalyzer->getContextAwareSuggestion($context, $functionName);
                    }

                    $errors[] = new FatalError(
                        type: 'UNDEFINED_FUNCTION',
                        message: "Call to undefined function '{$functionName}'",
                        file: $filePath,
                        line: $lineNumber,
                        severity: $severity,
                        suggestion: $suggestion,
                        context: [
                            'function' => $functionName,
                            'php_version' => $phpVersion,
                            'wp_version' => $wpVersion,
                            'wp_context' => $context
                        ]
                    );
                }
            }
        }
        
        return $errors;
    }

    private function extractFunctionCalls(string $line): array {
        $functions = [];

        // Skip if we're inside a script tag
        if ($this->insideScriptTag) {
            return [];
        }

        // Remove comments first
        $line = preg_replace('/\/\/.*$/', '', $line);
        $line = preg_replace('/\/\*.*?\*\//', '', $line);

        // Skip method calls (contains -> or ::) and JavaScript method calls (contains .)
        if (strpos($line, '->') !== false || strpos($line, '::') !== false) {
            return [];
        }

        // Skip JavaScript code (enhanced detection)
        if (preg_match('/\$\s*\(\s*["\']/', $line) ||
            preg_match('/jQuery\s*\(/', $line) ||
            preg_match('/<script[^>]*>/', $line) ||
            preg_match('/echo\s+["\']<script/', $line) ||
            preg_match('/\$\(["\'][^"\']*["\']/', $line)) {
            return [];
        }

        // Skip CSS code (enhanced detection)
        if (preg_match('/\{[^}]*\}/', $line) ||
            preg_match('/:\s*(calc|rgba|rgb|hsl|hsla|linear-gradient|radial-gradient)\s*\(/', $line) ||
            preg_match('/["\'].*\.(css|scss|sass|less).*["\']/', $line) ||
            preg_match('/style\s*=\s*["\']/', $line)) {
            return [];
        }

        // Skip lines that are method definitions
        if (preg_match('/^\s*(private|protected|public)\s+function\s+/', $line)) {
            return [];
        }

        // Skip lines that are class definitions or other declarations
        if (preg_match('/^\s*(class|interface|trait|namespace|use)\s+/', $line)) {
            return [];
        }

        // Skip lines that contain only strings or documentation
        if (preg_match('/^\s*["\'].*["\'][\s,;]*$/', $line)) {
            return [];
        }

        // Skip heredoc/nowdoc content
        if (preg_match('/<<<["\']?\w+["\']?/', $line)) {
            return [];
        }

        // Skip lines with class instantiations, exception handling, and type declarations
        if (preg_match('/\bnew\s+\\\\?[a-zA-Z_][a-zA-Z0-9_\\\\]*\s*\(/', $line) ||
            preg_match('/\bcatch\s*\(\s*\\\\?[a-zA-Z_][a-zA-Z0-9_\\\\]*\s/', $line) ||
            preg_match('/\bthrow\s+new\s+\\\\?[a-zA-Z_][a-zA-Z0-9_\\\\]*\s*\(/', $line) ||
            preg_match('/\bextends\s+\\\\?[a-zA-Z_][a-zA-Z0-9_\\\\]*/', $line) ||
            preg_match('/\bimplements\s+\\\\?[a-zA-Z_][a-zA-Z0-9_\\\\]*/', $line) ||
            preg_match('/\binstanceof\s+\\\\?[a-zA-Z_][a-zA-Z0-9_\\\\]*/', $line)) {
            return [];
        }

        // Remove string literals to avoid matching function names inside strings
        $lineWithoutStrings = preg_replace('/(["\'])(?:(?=(\\\\?))\2.)*?\1/', '', $line);

        // Match function calls: function_name( but exclude JavaScript method calls like .method(
        // Also exclude class instantiations with namespaces like new \ClassName() or new Namespace\ClassName()
        if (preg_match_all('/(?<![.$\\\\])\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $lineWithoutStrings, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $match) {
                $functionName = $match[0];
                $position = $match[1];

                // Skip language constructs and common keywords
                if (in_array(strtolower($functionName), [
                    'if', 'else', 'elseif', 'while', 'for', 'foreach', 'switch', 'case', 'default',
                    'try', 'catch', 'finally', 'class', 'function', 'interface', 'trait',
                    'namespace', 'use', 'echo', 'print', 'return', 'throw', 'include',
                    'require', 'include_once', 'require_once', 'isset', 'empty', 'unset',
                    'array', 'list', 'exit', 'die', 'new', 'clone', 'instanceof',
                    'match', 'enum', 'readonly', // PHP 8.0+ language constructs
                    '__construct', '__destruct', '__call', '__callstatic', '__get', '__set',
                    '__isset', '__unset', '__sleep', '__wakeup', '__serialize', '__unserialize',
                    '__tostring', '__invoke', '__set_state', '__clone', '__debuginfo'
                ])) {
                    continue;
                }

                // Check if this is part of a class instantiation by looking at the text before the function name
                $beforeText = substr($lineWithoutStrings, 0, $position);

                // Skip if preceded by 'new' (with optional namespace separator and whitespace)
                if (preg_match('/\bnew\s+\\\\?\s*$/', $beforeText)) {
                    continue;
                }

                // Skip if it's part of a catch block type hint
                if (preg_match('/\bcatch\s*\(\s*\\\\?\s*$/', $beforeText)) {
                    continue;
                }

                // Skip if it's part of a throw statement
                if (preg_match('/\bthrow\s+new\s+\\\\?\s*$/', $beforeText)) {
                    continue;
                }

                $functions[] = $functionName;
            }
        }

        return array_unique($functions);
    }

    private function isUndefinedFunction(string $functionName, string $phpVersion, string $wpVersion): bool {
        // Check if it's a built-in PHP function
        if (function_exists($functionName)) {
            return false;
        }

        // Check if it's a known WordPress function
        if (in_array($functionName, $this->wordpressFunctions)) {
            return false;
        }

        // Check if it's a WordPress admin function that requires special includes
        if (in_array($functionName, $this->wordpressAdminFunctions)) {
            return true; // These should be flagged as potentially undefined
        }

        // Check dependency exceptions based on detected ecosystems
        if ($this->exceptionManager->isFunctionExcepted($functionName, $this->detectedEcosystems)) {
            return false;
        }

        // Check if it's a PHP function that might not be available in the target version
        if (isset($this->phpFunctions[$functionName])) {
            $requiredVersion = $this->phpFunctions[$functionName];
            return version_compare($phpVersion, $requiredVersion, '<');
        }

        // Check for common WordPress functions that might be missing
        if ($this->isWordPressFunction($functionName)) {
            return false;
        }

        // If we can't determine, assume it might be undefined
        return true;
    }

    private function isWordPressFunction(string $functionName): bool {
        // Common WordPress function patterns
        $wpPatterns = [
            '/^wp_/',
            '/^get_/',
            '/^the_/',
            '/^is_/',
            '/^has_/',
            '/^add_/',
            '/^remove_/',
            '/^do_/',
            '/^apply_/',
            '/^register_/',
            '/^enqueue_/',
            '/^dequeue_/',
        ];
        
        foreach ($wpPatterns as $pattern) {
            if (preg_match($pattern, $functionName)) {
                return true;
            }
        }
        
        return false;
    }

    private function getSuggestionForFunction(string $functionName): string {
        if (in_array($functionName, $this->wordpressAdminFunctions)) {
            return "Include wp-admin/includes/plugin.php before calling '{$functionName}' or check if the function exists with function_exists()";
        }

        if ($this->isWordPressFunction($functionName)) {
            return "Ensure WordPress is loaded before calling '{$functionName}' or check if the function exists with function_exists()";
        }

        if (isset($this->phpFunctions[$functionName])) {
            $requiredVersion = $this->phpFunctions[$functionName];
            return "Function '{$functionName}' requires PHP {$requiredVersion} or higher";
        }

        return "Check if function '{$functionName}' is defined or include the required file/library";
    }

    private function initializeWordPressFunctions(): void {
        // Common WordPress functions that might not be available during testing
        $this->wordpressFunctions = [
            'wp_enqueue_script', 'wp_enqueue_style', 'wp_dequeue_script', 'wp_dequeue_style',
            'add_action', 'add_filter', 'remove_action', 'remove_filter',
            'get_option', 'update_option', 'delete_option',
            'get_post_meta', 'update_post_meta', 'delete_post_meta',
            'get_user_meta', 'update_user_meta', 'delete_user_meta',
            'wp_redirect', 'wp_safe_redirect',
            'wp_die', 'wp_error',
            'sanitize_text_field', 'sanitize_email', 'sanitize_url',
            'esc_html', 'esc_attr', 'esc_url', 'esc_js',
            'wp_nonce_field', 'wp_verify_nonce', 'wp_create_nonce',
            'current_user_can', 'is_user_logged_in',
            'get_current_user_id', 'wp_get_current_user',
            'register_post_type', 'register_taxonomy',
            'add_meta_box', 'remove_meta_box',
            'wp_insert_post', 'wp_update_post', 'wp_delete_post',
            'get_posts', 'get_post', 'wp_query',
            'is_admin', 'is_front_page', 'is_home', 'is_single', 'is_page',
            'get_template_directory', 'get_template_directory_uri',
            'get_stylesheet_directory', 'get_stylesheet_directory_uri',
            'plugin_dir_path', 'plugin_dir_url', 'plugins_url',
        ];
    }

    private function initializeWordPressAdminFunctions(): void {
        // WordPress admin functions that require wp-admin/includes/plugin.php
        $this->wordpressAdminFunctions = [
            'is_plugin_active',
            'is_plugin_active_for_network',
            'is_plugin_inactive',
            'activate_plugin',
            'activate_plugins',
            'deactivate_plugins',
            'validate_plugin',
            'validate_active_plugins',
            'is_network_only_plugin',
            'is_plugin_paused',
            'resume_plugin',
            'get_plugin_data',
            'get_plugins',
            'get_mu_plugins',
            'get_dropins',
            'search_theme_directories',
            'get_broken_themes',
            'wp_clean_plugins_cache',
        ];
    }

    private function initializePHPFunctions(): void {
        // PHP functions with version requirements
        $this->phpFunctions = [
            'str_contains' => '8.0.0',
            'str_starts_with' => '8.0.0',
            'str_ends_with' => '8.0.0',
            'array_key_first' => '7.3.0',
            'array_key_last' => '7.3.0',
            'is_countable' => '7.3.0',
            'hrtime' => '7.3.0',
            'array_merge_recursive' => '7.4.0', // Changed behavior
            'password_hash' => '5.5.0',
            'password_verify' => '5.5.0',
            'hash_equals' => '5.6.0',
            'random_bytes' => '7.0.0',
            'random_int' => '7.0.0',
            'intdiv' => '7.0.0',
            'preg_replace_callback_array' => '7.0.0',
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
}
