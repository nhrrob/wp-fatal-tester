<?php

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\PluginEcosystemDetector;

class PluginEcosystemDetectorTest extends TestCase {
    
    private PluginEcosystemDetector $detector;
    private string $testPluginPath;
    
    protected function setUp(): void {
        $this->detector = new PluginEcosystemDetector();
        $this->testPluginPath = sys_get_temp_dir() . '/test-plugin-' . uniqid();
        mkdir($this->testPluginPath, 0777, true);
    }
    
    protected function tearDown(): void {
        $this->removeDirectory($this->testPluginPath);
    }
    
    public function testDetectElementorFromPluginHeader(): void {
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Test Elementor Addon
 * Description: A test plugin for Elementor
 * Elementor tested up to: 3.15.0
 * Version: 1.0.0
 */

// Plugin code here
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        $ecosystems = $this->detector->detectEcosystems($this->testPluginPath);
        
        $this->assertContains('elementor', $ecosystems);
    }
    
    public function testDetectElementorFromClassUsage(): void {
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Test Plugin
 */

class TestWidget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'test-widget';
    }
}
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        $ecosystems = $this->detector->detectEcosystems($this->testPluginPath);
        
        $this->assertContains('elementor', $ecosystems);
    }
    
    public function testDetectWooCommerceFromHeader(): void {
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Test WooCommerce Extension
 * WC tested up to: 8.0.0
 * Requires Plugins: woocommerce
 */

// Plugin code here
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        $ecosystems = $this->detector->detectEcosystems($this->testPluginPath);
        
        $this->assertContains('woocommerce', $ecosystems);
    }
    
    public function testDetectWooCommerceFromClassUsage(): void {
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Test Plugin
 */

class TestPaymentGateway extends WC_Payment_Gateway {
    public function __construct() {
        parent::__construct();
    }
}
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        $ecosystems = $this->detector->detectEcosystems($this->testPluginPath);
        
        $this->assertContains('woocommerce', $ecosystems);
    }
    
    public function testDetectMultipleEcosystems(): void {
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Test Multi-Ecosystem Plugin
 * Elementor tested up to: 3.15.0
 * WC tested up to: 8.0.0
 */

class TestWidget extends \Elementor\Widget_Base {
    public function process_payment() {
        return wc_get_order(123);
    }
}
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        $ecosystems = $this->detector->detectEcosystems($this->testPluginPath);
        
        $this->assertContains('elementor', $ecosystems);
        $this->assertContains('woocommerce', $ecosystems);
    }
    
    public function testNoEcosystemDetected(): void {
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Regular WordPress Plugin
 */

class TestPlugin {
    public function init() {
        add_action('init', [$this, 'setup']);
    }
}
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        $ecosystems = $this->detector->detectEcosystems($this->testPluginPath);
        
        $this->assertEmpty($ecosystems);
    }
    
    public function testHasEcosystem(): void {
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Test Elementor Addon
 * Elementor tested up to: 3.15.0
 */
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        $this->detector->detectEcosystems($this->testPluginPath);
        
        $this->assertTrue($this->detector->hasEcosystem('elementor'));
        $this->assertTrue($this->detector->hasEcosystem('Elementor')); // Case insensitive
        $this->assertFalse($this->detector->hasEcosystem('woocommerce'));
    }
    
    public function testAddCustomEcosystemPattern(): void {
        $customPattern = [
            'headers' => ['Custom Framework tested up to'],
            'class_patterns' => ['CustomFramework\\'],
            'function_patterns' => ['custom_framework_'],
        ];
        
        $this->detector->addEcosystemPattern('custom', $customPattern);
        
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Test Custom Framework Plugin
 * Custom Framework tested up to: 1.0.0
 */
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        $ecosystems = $this->detector->detectEcosystems($this->testPluginPath);
        
        $this->assertContains('custom', $ecosystems);
    }
    
    private function removeDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
