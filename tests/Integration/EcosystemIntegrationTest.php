<?php

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\ClassConflictDetector;
use NHRROB\WPFatalTester\Detectors\PluginEcosystemDetector;
use NHRROB\WPFatalTester\Exceptions\DependencyExceptionManager;

class EcosystemIntegrationTest extends TestCase {
    
    private string $testPluginPath;
    private ClassConflictDetector $detector;
    private PluginEcosystemDetector $ecosystemDetector;
    private DependencyExceptionManager $exceptionManager;
    
    protected function setUp(): void {
        $this->testPluginPath = sys_get_temp_dir() . '/test-plugin-' . uniqid();
        mkdir($this->testPluginPath, 0777, true);
        
        $this->exceptionManager = new DependencyExceptionManager();
        $this->detector = new ClassConflictDetector($this->exceptionManager);
        $this->ecosystemDetector = new PluginEcosystemDetector();
    }
    
    protected function tearDown(): void {
        $this->removeDirectory($this->testPluginPath);
    }
    
    public function testElementorAddonDoesNotReportElementorClassesAsUndefined(): void {
        // Create an Elementor addon plugin
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Test Elementor Addon
 * Elementor tested up to: 3.15.0
 */

class TestWidget extends \Elementor\Widget_Base {
    public function get_controls_manager() {
        return Controls_Manager::instance();
    }
    
    public function add_typography_control() {
        \$this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .title',
            ]
        );
    }
}
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        // Detect ecosystems
        $ecosystems = $this->ecosystemDetector->detectEcosystems($this->testPluginPath);
        $this->assertContains('elementor', $ecosystems);
        
        // Set detected ecosystems on the detector
        $this->detector->setDetectedEcosystems($ecosystems);
        
        // Test the plugin file
        $errors = $this->detector->detect($this->testPluginPath . '/test-plugin.php', '8.1', '6.5');
        
        // Filter for undefined class errors
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });
        
        // Should not report Elementor classes as undefined
        $elementorClassErrors = array_filter($undefinedClassErrors, function($error) {
            $className = $error->context['class'] ?? '';
            return in_array($className, ['Controls_Manager', 'Group_Control_Typography']);
        });
        
        $this->assertEmpty($elementorClassErrors, 'Elementor classes should not be reported as undefined in Elementor addons');
    }
    
    public function testWooCommerceExtensionDoesNotReportWooCommerceClassesAsUndefined(): void {
        // Create a WooCommerce extension plugin
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Test WooCommerce Extension
 * WC tested up to: 8.0.0
 */

class TestPaymentGateway extends WC_Payment_Gateway {
    public function process_payment(\$order_id) {
        \$order = wc_get_order(\$order_id);
        return \$order instanceof WC_Order;
    }
}
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        // Detect ecosystems
        $ecosystems = $this->ecosystemDetector->detectEcosystems($this->testPluginPath);
        $this->assertContains('woocommerce', $ecosystems);
        
        // Set detected ecosystems on the detector
        $this->detector->setDetectedEcosystems($ecosystems);
        
        // Test the plugin file
        $errors = $this->detector->detect($this->testPluginPath . '/test-plugin.php', '8.1', '6.5');
        
        // Filter for undefined class errors
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });
        
        // Should not report WooCommerce classes as undefined
        $wooClassErrors = array_filter($undefinedClassErrors, function($error) {
            $className = $error->context['class'] ?? '';
            return in_array($className, ['WC_Payment_Gateway', 'WC_Order']);
        });
        
        $this->assertEmpty($wooClassErrors, 'WooCommerce classes should not be reported as undefined in WooCommerce extensions');
    }
    
    public function testRegularPluginStillReportsUndefinedClasses(): void {
        // Create a regular WordPress plugin
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Regular WordPress Plugin
 */

class TestPlugin {
    public function init() {
        \$manager = new UndefinedClass();
        return \$manager;
    }
}
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        // Detect ecosystems (should be empty)
        $ecosystems = $this->ecosystemDetector->detectEcosystems($this->testPluginPath);
        $this->assertEmpty($ecosystems);
        
        // Set detected ecosystems on the detector
        $this->detector->setDetectedEcosystems($ecosystems);
        
        // Test the plugin file
        $errors = $this->detector->detect($this->testPluginPath . '/test-plugin.php', '8.1', '6.5');
        
        // Should report undefined classes
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS' && 
                   ($error->context['class'] ?? '') === 'UndefinedClass';
        });
        
        $this->assertNotEmpty($undefinedClassErrors, 'Regular plugins should still report truly undefined classes');
    }
    
    public function testMixedEcosystemPlugin(): void {
        // Create a plugin that uses both Elementor and WooCommerce
        $pluginContent = <<<PHP
<?php
/**
 * Plugin Name: Mixed Ecosystem Plugin
 * Elementor tested up to: 3.15.0
 * WC tested up to: 8.0.0
 */

class TestMixedWidget extends \Elementor\Widget_Base {
    public function get_product_data() {
        \$product = wc_get_product(123);
        if (\$product instanceof WC_Product) {
            return \$product->get_name();
        }
        return '';
    }
    
    public function add_controls() {
        \$this->add_control(
            'product_id',
            [
                'type' => Controls_Manager::NUMBER,
                'label' => 'Product ID',
            ]
        );
    }
}
PHP;
        
        file_put_contents($this->testPluginPath . '/test-plugin.php', $pluginContent);
        
        // Detect ecosystems
        $ecosystems = $this->ecosystemDetector->detectEcosystems($this->testPluginPath);
        $this->assertContains('elementor', $ecosystems);
        $this->assertContains('woocommerce', $ecosystems);
        
        // Set detected ecosystems on the detector
        $this->detector->setDetectedEcosystems($ecosystems);
        
        // Test the plugin file
        $errors = $this->detector->detect($this->testPluginPath . '/test-plugin.php', '8.1', '6.5');
        
        // Filter for undefined class errors
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });
        
        // Should not report classes from either ecosystem as undefined
        $ecosystemClassErrors = array_filter($undefinedClassErrors, function($error) {
            $className = $error->context['class'] ?? '';
            return in_array($className, ['Controls_Manager', 'WC_Product']);
        });
        
        $this->assertEmpty($ecosystemClassErrors, 'Mixed ecosystem plugins should not report classes from detected ecosystems as undefined');
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
