<?php
namespace NHRROB\WPFatalTester\Detectors;

class PluginEcosystemDetector {
    
    private array $ecosystemPatterns = [];
    private array $detectedEcosystems = [];
    
    public function __construct() {
        $this->initializeEcosystemPatterns();
    }
    
    /**
     * Detect plugin ecosystems in the given plugin directory
     *
     * @param string $pluginPath Path to the plugin directory
     * @return array Array of detected ecosystems
     */
    public function detectEcosystems(string $pluginPath): array {
        if (!is_dir($pluginPath) || !is_readable($pluginPath)) {
            return [];
        }
        
        $this->detectedEcosystems = [];
        
        // Check plugin headers first
        $this->checkPluginHeaders($pluginPath);
        
        // Check file patterns and code signatures
        $this->checkCodePatterns($pluginPath);
        
        // Check composer.json dependencies
        $this->checkComposerDependencies($pluginPath);
        
        return array_unique($this->detectedEcosystems);
    }
    
    /**
     * Check if a specific ecosystem is detected
     *
     * @param string $ecosystem The ecosystem name (e.g., 'elementor', 'woocommerce')
     * @return bool
     */
    public function hasEcosystem(string $ecosystem): bool {
        return in_array(strtolower($ecosystem), array_map('strtolower', $this->detectedEcosystems));
    }
    
    /**
     * Get all detected ecosystems
     *
     * @return array
     */
    public function getDetectedEcosystems(): array {
        return $this->detectedEcosystems;
    }
    
    private function initializeEcosystemPatterns(): void {
        $this->ecosystemPatterns = [
            'elementor' => [
                'headers' => [
                    'Elementor tested up to',
                    'Elementor Pro tested up to',
                    'Requires Plugins: elementor',
                ],
                'file_patterns' => [
                    '/widgets/',
                    '/controls/',
                    '/modules/',
                    'elementor',
                ],
                'class_patterns' => [
                    'Elementor\\',
                    'Controls_Manager',
                    'Widget_Base',
                    'Group_Control_',
                    'Core\\Kits\\Documents\\Tabs\\',
                ],
                'function_patterns' => [
                    'elementor_pro_load_plugin',
                    'elementor_load_plugin_textdomain',
                ],
                'composer_packages' => [
                    'elementor/elementor',
                ],
            ],
            'woocommerce' => [
                'headers' => [
                    'WC tested up to',
                    'WC requires at least',
                    'Requires Plugins: woocommerce',
                ],
                'file_patterns' => [
                    '/woocommerce/',
                    '/includes/wc-',
                    'wc-',
                ],
                'class_patterns' => [
                    'WooCommerce\\',
                    'WC_',
                    'WP_REST_',
                ],
                'function_patterns' => [
                    'wc_get_',
                    'woocommerce_',
                    'is_woocommerce',
                ],
                'composer_packages' => [
                    'woocommerce/woocommerce',
                ],
            ],
        ];
    }
    
    private function checkPluginHeaders(string $pluginPath): void {
        // Look for main plugin file
        $mainPluginFile = $this->findMainPluginFile($pluginPath);
        
        if (!$mainPluginFile) {
            return;
        }
        
        $content = file_get_contents($mainPluginFile);
        $headers = $this->extractPluginHeaders($content);
        
        foreach ($this->ecosystemPatterns as $ecosystem => $patterns) {
            foreach ($patterns['headers'] as $headerPattern) {
                foreach ($headers as $header) {
                    if (stripos($header, $headerPattern) !== false) {
                        $this->detectedEcosystems[] = $ecosystem;
                        break 2;
                    }
                }
            }
        }
    }
    
    private function findMainPluginFile(string $pluginPath): ?string {
        // Look for PHP files in the root directory
        $phpFiles = glob($pluginPath . '/*.php');
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Check if file contains plugin header
            if (preg_match('/Plugin Name\s*:/i', $content)) {
                return $file;
            }
        }
        
        return null;
    }
    
    private function extractPluginHeaders(string $content): array {
        $headers = [];
        
        // Extract the plugin header comment block
        if (preg_match('/\/\*\*(.*?)\*\//s', $content, $matches)) {
            $headerBlock = $matches[1];
            
            // Split into lines and extract header values
            $lines = explode("\n", $headerBlock);
            foreach ($lines as $line) {
                $line = trim($line, " \t\n\r\0\x0B*");
                if (!empty($line)) {
                    $headers[] = $line;
                }
            }
        }
        
        return $headers;
    }

    private function checkCodePatterns(string $pluginPath): void {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pluginPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $fileCount = 0;
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || $fileCount > 50) { // Limit files to check for performance
                continue;
            }

            $fileCount++;
            $content = file_get_contents($file->getPathname());

            foreach ($this->ecosystemPatterns as $ecosystem => $patterns) {
                // Check file path patterns
                foreach ($patterns['file_patterns'] as $pattern) {
                    if (stripos($file->getPathname(), $pattern) !== false) {
                        $this->detectedEcosystems[] = $ecosystem;
                        continue 2;
                    }
                }

                // Check class patterns in content
                foreach ($patterns['class_patterns'] as $pattern) {
                    if (stripos($content, $pattern) !== false) {
                        $this->detectedEcosystems[] = $ecosystem;
                        continue 2;
                    }
                }

                // Check function patterns in content
                foreach ($patterns['function_patterns'] as $pattern) {
                    if (stripos($content, $pattern) !== false) {
                        $this->detectedEcosystems[] = $ecosystem;
                        continue 2;
                    }
                }
            }
        }
    }

    private function checkComposerDependencies(string $pluginPath): void {
        $composerFile = $pluginPath . '/composer.json';

        if (!file_exists($composerFile)) {
            return;
        }

        $composerContent = file_get_contents($composerFile);
        $composerData = json_decode($composerContent, true);

        if (!$composerData) {
            return;
        }

        $dependencies = array_merge(
            $composerData['require'] ?? [],
            $composerData['require-dev'] ?? []
        );

        foreach ($this->ecosystemPatterns as $ecosystem => $patterns) {
            foreach ($patterns['composer_packages'] as $package) {
                if (isset($dependencies[$package])) {
                    $this->detectedEcosystems[] = $ecosystem;
                    break;
                }
            }
        }
    }

    /**
     * Get ecosystem-specific information
     *
     * @param string $ecosystem
     * @return array
     */
    public function getEcosystemInfo(string $ecosystem): array {
        $ecosystem = strtolower($ecosystem);
        return $this->ecosystemPatterns[$ecosystem] ?? [];
    }

    /**
     * Add a custom ecosystem pattern
     *
     * @param string $ecosystem
     * @param array $patterns
     */
    public function addEcosystemPattern(string $ecosystem, array $patterns): void {
        $this->ecosystemPatterns[strtolower($ecosystem)] = $patterns;
    }
}
