<?php
namespace NHRROB\WPFatalTester\Detectors;

use NHRROB\WPFatalTester\Models\FatalError;

class ThisContextDetector implements ErrorDetectorInterface {

    private array $templatePatterns = [
        '/template/',
        '/templates/',
        '/Template/',
        '/Templates/',
        '/views/',
        '/Views/',
        '/partials/',
        '/Partials/',
    ];

    private ?string $pluginRoot = null;
    
    private array $includePatterns = [
        'include',
        'include_once',
        'require',
        'require_once',
    ];
    
    public function getName(): string {
        return '$this Context Detector';
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
        
        // Check if this is likely a template file
        if ($this->isLikelyTemplateFile($filePath)) {
            $errors = array_merge($errors, $this->checkThisUsageInTemplate($filePath));
        }
        
        return $errors;
    }
    
    /**
     * Check if the file is likely a template file based on path patterns
     */
    private function isLikelyTemplateFile(string $filePath): bool {
        foreach ($this->templatePatterns as $pattern) {
            if (stripos($filePath, $pattern) !== false) {
                return true;
            }
        }
        
        // Check if filename suggests it's a template
        $filename = basename($filePath);
        $templateFilenames = [
            'default.php',
            'advanced.php',
            'preset-1.php',
            'preset-2.php',
            'preset-3.php',
            'layout-1.php',
            'layout-2.php',
            'style-1.php',
            'style-2.php',
        ];
        
        return in_array($filename, $templateFilenames);
    }
    
    /**
     * Check for $this usage in template files
     */
    private function checkThisUsageInTemplate(string $filePath): array {
        $errors = [];
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNumber => $line) {
            $lineNumber++; // 1-based line numbers
            
            // Skip comments and strings
            if ($this->isCommentOrString($line)) {
                continue;
            }
            
            // Check for $this-> usage
            if (preg_match('/\$this\s*->\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $line, $matches)) {
                $methodName = $matches[1];
                
                // Check if this is a method call that could be problematic
                if ($this->isPotentiallyProblematicMethod($methodName)) {
                    $errors[] = new FatalError(
                        type: 'THIS_CONTEXT_ERROR',
                        message: "Usage of '\$this->{$methodName}()' in template file may cause fatal error when included in different contexts",
                        file: $filePath,
                        line: $lineNumber,
                        severity: 'error',
                        suggestion: "Consider using static methods, passing the object as a parameter, or checking if \$this is available before use",
                        context: [
                            'method' => $methodName,
                            'template_file' => basename($filePath),
                            'line_content' => trim($line)
                        ],
                        pluginRoot: $this->pluginRoot
                    );
                }
            }
            
            // Check for $this property access
            if (preg_match('/\$this\s*->\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*(?!\()/', $line, $matches)) {
                $propertyName = $matches[1];
                
                $errors[] = new FatalError(
                    type: 'THIS_CONTEXT_ERROR',
                    message: "Usage of '\$this->{$propertyName}' in template file may cause fatal error when included in different contexts",
                    file: $filePath,
                    line: $lineNumber,
                    severity: 'error',
                    suggestion: "Consider passing the property value as a variable to the template or checking if \$this is available before use",
                    context: [
                        'property' => $propertyName,
                        'template_file' => basename($filePath),
                        'line_content' => trim($line)
                    ],
                    pluginRoot: $this->pluginRoot
                );
            }
        }
        
        return $errors;
    }
    
    /**
     * Check if a method name is potentially problematic when called via $this in templates
     */
    private function isPotentiallyProblematicMethod(string $methodName): bool {
        // Methods that are commonly called in templates and could cause issues
        $problematicMethods = [
            'render_post_meta_dates',
            'get_last_modified_date',
            'render_meta',
            'render_content',
            'render_title',
            'render_excerpt',
            'render_image',
            'render_author',
            'render_categories',
            'render_tags',
            'get_settings',
            'get_id',
            'print_render_attribute_string',
            'add_render_attribute',
        ];

        return in_array($methodName, $problematicMethods) ||
               strpos($methodName, 'render_') === 0 ||
               strpos($methodName, 'get_') === 0 ||
               strpos($methodName, 'print_') === 0;
    }
    
    /**
     * Check if the line is a comment or inside a string
     */
    private function isCommentOrString(string $line): bool {
        $trimmed = trim($line);
        
        // Skip PHP comments
        if (strpos($trimmed, '//') === 0 || strpos($trimmed, '#') === 0) {
            return true;
        }
        
        // Skip block comments (basic check)
        if (strpos($trimmed, '/*') !== false || strpos($trimmed, '*/') !== false) {
            return true;
        }
        
        return false;
    }
}
