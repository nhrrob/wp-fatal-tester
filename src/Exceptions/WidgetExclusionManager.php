<?php
namespace NHRROB\WPFatalTester\Exceptions;

class WidgetExclusionManager {
    
    private array $widgetExclusions = [];
    private array $temporaryExclusions = [];
    private array $reportingModes = [
        'fatal_only' => 'Show only fatal errors (exclude known false positives)',
        'all_errors' => 'Show all errors including excluded items for debugging',
        'debug_mode' => 'Show all errors with exclusion status annotations'
    ];
    private string $currentMode = 'fatal_only';
    private ?string $configFile = null;
    
    public function __construct(?string $configFile = null) {
        $this->configFile = $configFile;
        $this->initializeDefaultExclusions();
        $this->loadConfigurationFile();
    }
    
    /**
     * Initialize default widget exclusions for known false positives
     */
    private function initializeDefaultExclusions(): void {
        $this->widgetExclusions = [
            'elementor' => [
                // Content Timeline - currently no load more, but might be added
                'content_timeline' => [
                    'status' => 'temporary_exclude',
                    'reason' => 'No load more functionality currently, but may be added in future',
                    'methods' => ['*'], // All methods for now
                    'error_types' => ['TEMPLATE_METHOD_CONTEXT_ERROR', 'THIS_CONTEXT_ERROR'],
                    'review_date' => '2024-12-01', // Review when Elementor updates
                    'future_proof' => true
                ],
                
                // Post Carousel - typically no load more
                'post_carousel' => [
                    'status' => 'exclude',
                    'reason' => 'Carousel widgets typically do not have load more functionality',
                    'methods' => ['*'], // All methods
                    'error_types' => ['TEMPLATE_METHOD_CONTEXT_ERROR', 'THIS_CONTEXT_ERROR'],
                    'future_proof' => false
                ],

                // Product Carousel - typically no load more
                'product_carousel' => [
                    'status' => 'exclude',
                    'reason' => 'Carousel widgets typically do not have load more functionality',
                    'methods' => ['*'],
                    'error_types' => ['TEMPLATE_METHOD_CONTEXT_ERROR', 'THIS_CONTEXT_ERROR'],
                    'future_proof' => false
                ],

                // Media Carousel - typically no load more
                'media_carousel' => [
                    'status' => 'exclude',
                    'reason' => 'Carousel widgets typically do not have load more functionality',
                    'methods' => ['*'],
                    'error_types' => ['TEMPLATE_METHOD_CONTEXT_ERROR', 'THIS_CONTEXT_ERROR'],
                    'future_proof' => false
                ],

                // Testimonial Carousel - typically no load more
                'testimonial_carousel' => [
                    'status' => 'exclude',
                    'reason' => 'Carousel widgets typically do not have load more functionality',
                    'methods' => ['*'],
                    'error_types' => ['TEMPLATE_METHOD_CONTEXT_ERROR', 'THIS_CONTEXT_ERROR'],
                    'future_proof' => false
                ],

                // Logo Carousel - typically no load more
                'logo_carousel' => [
                    'status' => 'exclude',
                    'reason' => 'Carousel widgets typically do not have load more functionality',
                    'methods' => ['*'],
                    'error_types' => ['TEMPLATE_METHOD_CONTEXT_ERROR', 'THIS_CONTEXT_ERROR'],
                    'future_proof' => false
                ],
                
                // Post List - has load more, should NOT be excluded
                'post_list' => [
                    'status' => 'include',
                    'reason' => 'Has load more functionality, errors are legitimate',
                    'methods' => [],
                    'error_types' => [],
                    'future_proof' => true
                ],
                
                // Post Grid - has load more, should NOT be excluded
                'post_grid' => [
                    'status' => 'include',
                    'reason' => 'Has load more functionality, errors are legitimate',
                    'methods' => [],
                    'error_types' => [],
                    'future_proof' => true
                ],

                // WooCommerce Account Dashboard - typically no load more
                'woo_account_dashboard' => [
                    'status' => 'exclude',
                    'reason' => 'Account dashboard widgets typically do not have load more functionality',
                    'methods' => ['*'],
                    'error_types' => ['THIS_CONTEXT_ERROR', 'TEMPLATE_METHOD_CONTEXT_ERROR'],
                    'future_proof' => false
                ],

                // LearnDash Courses - may have pagination but not AJAX load more
                'ld_courses' => [
                    'status' => 'exclude',
                    'reason' => 'LearnDash course widgets typically use pagination, not AJAX load more',
                    'methods' => ['*'],
                    'error_types' => ['THIS_CONTEXT_ERROR', 'TEMPLATE_METHOD_CONTEXT_ERROR'],
                    'future_proof' => false
                ]
            ]
        ];
    }
    
    /**
     * Check if a widget method error should be excluded
     *
     * @param string $ecosystem
     * @param string $widgetType
     * @param string $methodName
     * @param string $errorType
     * @return array Returns ['exclude' => bool, 'reason' => string, 'status' => string]
     */
    public function shouldExcludeError(string $ecosystem, string $widgetType, string $methodName, string $errorType): array {
        $ecosystem = strtolower($ecosystem);
        $result = [
            'exclude' => false,
            'reason' => '',
            'status' => 'unknown',
            'future_proof' => false
        ];
        
        // Check if widget is configured (check temporary exclusions first)
        $widgetConfig = null;
        if (isset($this->temporaryExclusions[$ecosystem][$widgetType])) {
            $widgetConfig = $this->temporaryExclusions[$ecosystem][$widgetType];
        } elseif (isset($this->widgetExclusions[$ecosystem][$widgetType])) {
            $widgetConfig = $this->widgetExclusions[$ecosystem][$widgetType];
        } else {
            return $result;
        }
        $result['status'] = $widgetConfig['status'];
        $result['reason'] = $widgetConfig['reason'] ?? '';
        $result['future_proof'] = $widgetConfig['future_proof'] ?? false;
        
        // If status is 'include', never exclude
        if ($widgetConfig['status'] === 'include') {
            return $result;
        }
        
        // Check if error type should be excluded
        if (in_array($errorType, $widgetConfig['error_types']) || in_array('*', $widgetConfig['error_types'])) {
            // Check if method should be excluded
            if (in_array($methodName, $widgetConfig['methods']) || in_array('*', $widgetConfig['methods'])) {
                $result['exclude'] = true;
            }
        }
        
        return $result;
    }
    
    /**
     * Set the reporting mode
     *
     * @param string $mode One of: fatal_only, all_errors, debug_mode
     */
    public function setReportingMode(string $mode): void {
        if (array_key_exists($mode, $this->reportingModes)) {
            $this->currentMode = $mode;
        }
    }
    
    /**
     * Get the current reporting mode
     */
    public function getReportingMode(): string {
        return $this->currentMode;
    }
    
    /**
     * Get available reporting modes
     */
    public function getAvailableReportingModes(): array {
        return $this->reportingModes;
    }
    
    /**
     * Check if an error should be shown based on current reporting mode
     *
     * @param array $exclusionResult Result from shouldExcludeError()
     * @return bool
     */
    public function shouldShowError(array $exclusionResult): bool {
        switch ($this->currentMode) {
            case 'fatal_only':
                return !$exclusionResult['exclude'];
            
            case 'all_errors':
            case 'debug_mode':
                return true; // Show everything
            
            default:
                return !$exclusionResult['exclude'];
        }
    }
    
    /**
     * Add a temporary exclusion for a widget
     *
     * @param string $ecosystem
     * @param string $widgetType
     * @param array $config
     */
    public function addTemporaryExclusion(string $ecosystem, string $widgetType, array $config): void {
        $this->temporaryExclusions[$ecosystem][$widgetType] = array_merge([
            'status' => 'temporary_exclude',
            'methods' => [],
            'error_types' => [],
            'reason' => 'Temporary exclusion',
            'future_proof' => true
        ], $config);
    }
    
    /**
     * Load configuration from external file
     */
    private function loadConfigurationFile(): void {
        if (!$this->configFile || !file_exists($this->configFile)) {
            return;
        }
        
        $config = json_decode(file_get_contents($this->configFile), true);
        if ($config && isset($config['widget_exclusions'])) {
            $this->widgetExclusions = array_merge_recursive($this->widgetExclusions, $config['widget_exclusions']);
        }
        
        if ($config && isset($config['reporting_mode'])) {
            $this->setReportingMode($config['reporting_mode']);
        }
    }
    
    /**
     * Save current configuration to file
     */
    public function saveConfiguration(?string $filePath = null): bool {
        $filePath = $filePath ?? $this->configFile;
        if (!$filePath) {
            return false;
        }
        
        $config = [
            'widget_exclusions' => $this->widgetExclusions,
            'reporting_mode' => $this->currentMode,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        return file_put_contents($filePath, json_encode($config, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get all widget exclusions for a specific ecosystem
     */
    public function getWidgetExclusions(string $ecosystem): array {
        return $this->widgetExclusions[strtolower($ecosystem)] ?? [];
    }
    
    /**
     * Get statistics about exclusions
     */
    public function getExclusionStats(): array {
        $stats = [
            'total_ecosystems' => count($this->widgetExclusions),
            'total_widgets' => 0,
            'excluded_widgets' => 0,
            'temporary_exclusions' => 0,
            'future_proof_widgets' => 0
        ];
        
        foreach ($this->widgetExclusions as $ecosystem => $widgets) {
            $stats['total_widgets'] += count($widgets);
            
            foreach ($widgets as $widget => $config) {
                if (in_array($config['status'], ['exclude', 'temporary_exclude'])) {
                    $stats['excluded_widgets']++;
                }
                
                if ($config['status'] === 'temporary_exclude') {
                    $stats['temporary_exclusions']++;
                }
                
                if ($config['future_proof'] ?? false) {
                    $stats['future_proof_widgets']++;
                }
            }
        }
        
        return $stats;
    }
}
