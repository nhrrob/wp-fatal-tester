<?php
namespace NHRROB\WPFatalTester\Detectors;

use NHRROB\WPFatalTester\Models\FatalError;
use NHRROB\WPFatalTester\Exceptions\WidgetExclusionManager;

class TemplateMethodContextDetector implements ErrorDetectorInterface {

    private array $templatePatterns = [
        '/template/',
        '/templates/',
        '/Template/',
        '/Templates/',
        '/views/',
        '/Views/',
        '/partials/',
        '/Partials/',
        '/widgets/',
        '/Widgets/',
        '/elementor/',
        '/Elementor/',
    ];

    private ?string $pluginRoot = null;
    private WidgetExclusionManager $widgetExclusionManager;
    private array $detectedEcosystems = [];

    /**
     * EA Pro Post_List widget specific methods that cause AJAX load more issues
     */
    private array $eaProPostListMethods = [
        'render_post_meta_dates',
        'get_last_modified_date',
    ];

    public function __construct(?string $configFile = null) {
        $this->widgetExclusionManager = new WidgetExclusionManager($configFile);
    }

    /**
     * Set detected ecosystems for widget exclusion context
     */
    public function setDetectedEcosystems(array $ecosystems): void {
        $this->detectedEcosystems = $ecosystems;
    }

    /**
     * Get the widget exclusion manager for external configuration
     */
    public function getWidgetExclusionManager(): WidgetExclusionManager {
        return $this->widgetExclusionManager;
    }

    /**
     * Set the reporting mode for widget exclusions
     */
    public function setReportingMode(string $mode): void {
        $this->widgetExclusionManager->setReportingMode($mode);
    }
    
    /**
     * Common widget methods that may cause context issues
     */
    private array $widgetContextMethods = [
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
        'get_widget_settings',
        'get_widget_id',
        'render_widget_content',
    ];
    
    public function getName(): string {
        return 'Template Method Context Detector';
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
            $errors = array_merge($errors, $this->checkTemplateMethodContextIssues($filePath));
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
            'post-list.php',
            'post-grid.php',
            'content.php',
            'item.php',
        ];
        
        return in_array($filename, $templateFilenames);
    }
    
    /**
     * Check for template method context issues
     */
    private function checkTemplateMethodContextIssues(string $filePath): array {
        $errors = [];
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNumber => $line) {
            $lineNumber++; // 1-based line numbers

            // Skip comments and strings
            if ($this->isCommentOrString($line)) {
                continue;
            }

            // Remove string literals to avoid matching content inside strings
            $lineWithoutStrings = $this->removeStringLiterals($line);

            // Check for $this-> method calls
            if (preg_match('/\$this\s*->\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $lineWithoutStrings, $matches)) {
                $methodName = $matches[1];
                
                // Check for EA Pro Post_List specific methods
                if (in_array($methodName, $this->eaProPostListMethods)) {
                    $error = $this->createWidgetMethodError(
                        $methodName,
                        $filePath,
                        $lineNumber,
                        $line,
                        'post_list',
                        'ea_pro_ajax_context',
                        'error',
                        "EA Pro Post_List widget method '\$this->{$methodName}()' called in template may cause fatal error during AJAX load more operations",
                        $this->getEAProSpecificSuggestion($methodName)
                    );

                    if ($error) {
                        $errors[] = $error;
                    }
                }
                // Check for other widget context methods
                elseif (in_array($methodName, $this->widgetContextMethods)) {
                    $widgetType = $this->detectWidgetTypeFromPath($filePath);
                    $error = $this->createWidgetMethodError(
                        $methodName,
                        $filePath,
                        $lineNumber,
                        $line,
                        $widgetType,
                        'widget_context',
                        'warning',
                        "Widget method '\$this->{$methodName}()' called in template may cause fatal error when template is included in different contexts",
                        $this->getGeneralWidgetSuggestion($methodName)
                    );

                    if ($error) {
                        $errors[] = $error;
                    }
                }
                // Check for methods that follow problematic patterns
                elseif ($this->isProblematicMethodPattern($methodName)) {
                    $widgetType = $this->detectWidgetTypeFromPath($filePath);
                    $error = $this->createWidgetMethodError(
                        $methodName,
                        $filePath,
                        $lineNumber,
                        $line,
                        $widgetType,
                        'potential_context',
                        'info',
                        "Method '\$this->{$methodName}()' in template follows a pattern that may cause context errors",
                        "Verify that this method is available in all contexts where this template might be included, especially during AJAX operations"
                    );

                    if ($error) {
                        $errors[] = $error;
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Create a widget method error with exclusion checking
     */
    private function createWidgetMethodError(
        string $methodName,
        string $filePath,
        int $lineNumber,
        string $line,
        string $widgetType,
        string $issueType,
        string $severity,
        string $message,
        string $suggestion
    ): ?FatalError {
        $errorType = 'TEMPLATE_METHOD_CONTEXT_ERROR';

        // Check if this error should be excluded
        foreach ($this->detectedEcosystems as $ecosystem) {
            $exclusionResult = $this->widgetExclusionManager->shouldExcludeError(
                $ecosystem,
                $widgetType,
                $methodName,
                $errorType
            );

            // If widget exclusion manager says to show the error, create it
            if ($this->widgetExclusionManager->shouldShowError($exclusionResult)) {
                $context = [
                    'method' => $methodName,
                    'template_file' => basename($filePath),
                    'line_content' => trim($line),
                    'issue_type' => $issueType,
                    'widget_type' => $widgetType,
                    'description' => 'This method exists only in the widget class context and will not be available when the template is included during AJAX load more operations'
                ];

                // Add exclusion information in debug mode
                if ($this->widgetExclusionManager->getReportingMode() === 'debug_mode') {
                    $context['exclusion_info'] = $exclusionResult;
                }

                return new FatalError(
                    type: $errorType,
                    message: $message,
                    file: $filePath,
                    line: $lineNumber,
                    severity: $severity,
                    suggestion: $suggestion,
                    context: $context,
                    pluginRoot: $this->pluginRoot
                );
            }
        }

        return null; // Error was excluded
    }

    /**
     * Detect widget type from file path
     */
    private function detectWidgetTypeFromPath(string $filePath): string {
        $path = strtolower($filePath);

        // Common widget type patterns
        $widgetPatterns = [
            'post-list' => 'post_list',
            'post_list' => 'post_list',
            'post-grid' => 'post_grid',
            'post_grid' => 'post_grid',
            'content-timeline' => 'content_timeline',
            'content_timeline' => 'content_timeline',
            'post-carousel' => 'post_carousel',
            'post_carousel' => 'post_carousel',
            'product-carousel' => 'product_carousel',
            'product_carousel' => 'product_carousel',
            'media-carousel' => 'media_carousel',
            'media_carousel' => 'media_carousel',
            'testimonial-carousel' => 'testimonial_carousel',
            'testimonial_carousel' => 'testimonial_carousel',
            'logo-carousel' => 'logo_carousel',
            'logo_carousel' => 'logo_carousel',
        ];

        foreach ($widgetPatterns as $pattern => $widgetType) {
            if (strpos($path, $pattern) !== false) {
                return $widgetType;
            }
        }

        // Default to unknown widget type
        return 'unknown_widget';
    }

    /**
     * Get EA Pro specific suggestion for the method
     */
    private function getEAProSpecificSuggestion(string $methodName): string {
        switch ($methodName) {
            case 'render_post_meta_dates':
                return "Replace with direct date rendering logic or pass the formatted date as a variable to the template. This method is only available in the Post_List widget class and will cause fatal errors during AJAX load more operations.";
            case 'get_last_modified_date':
                return "Use get_the_modified_date() WordPress function directly or pass the modified date as a variable to the template. This method is only available in the Post_List widget class.";
            default:
                return "This EA Pro Post_List widget method should be called before including the template and the result passed as a variable, or replaced with equivalent WordPress functions.";
        }
    }
    
    /**
     * Get general widget suggestion for the method
     */
    private function getGeneralWidgetSuggestion(string $methodName): string {
        if (strpos($methodName, 'render_') === 0) {
            return "Consider moving the rendering logic outside the template or ensuring the widget object is properly available in all contexts where this template is used.";
        } elseif (strpos($methodName, 'get_') === 0) {
            return "Call this method before including the template and pass the result as a variable, or use equivalent WordPress functions directly.";
        } else {
            return "Ensure this method is available in all contexts where this template might be included, or pass the required data as variables to the template.";
        }
    }
    
    /**
     * Check if method name follows a problematic pattern
     */
    private function isProblematicMethodPattern(string $methodName): bool {
        $problematicPrefixes = ['render_', 'get_', 'print_', 'display_', 'show_', 'output_'];
        
        foreach ($problematicPrefixes as $prefix) {
            if (strpos($methodName, $prefix) === 0) {
                return true;
            }
        }
        
        return false;
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

    /**
     * Remove string literals from a line to avoid matching content inside strings
     */
    private function removeStringLiterals(string $line): string {
        // Remove double-quoted strings
        $line = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '""', $line);
        // Remove single-quoted strings
        $line = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", "''", $line);
        return $line;
    }
}
