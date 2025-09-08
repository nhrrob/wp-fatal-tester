<?php
namespace NHRROB\WPFatalTester\Detectors;

interface ErrorDetectorInterface {
    /**
     * Detect potential fatal errors in a PHP file
     *
     * @param string $filePath Path to the PHP file to analyze
     * @param string $phpVersion Target PHP version (e.g., '8.1')
     * @param string $wpVersion Target WordPress version (e.g., '6.5')
     * @return array Array of detected errors
     */
    public function detect(string $filePath, string $phpVersion, string $wpVersion): array;

    /**
     * Get the name of this detector
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set the plugin root path for relative path calculation
     *
     * @param string $pluginRoot Absolute path to the plugin root directory
     * @return void
     */
    public function setPluginRoot(string $pluginRoot): void;
}
