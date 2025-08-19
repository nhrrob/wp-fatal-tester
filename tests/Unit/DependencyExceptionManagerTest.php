<?php

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Exceptions\DependencyExceptionManager;

class DependencyExceptionManagerTest extends TestCase {
    
    private DependencyExceptionManager $manager;
    
    protected function setUp(): void {
        $this->manager = new DependencyExceptionManager();
    }
    
    public function testElementorClassExceptions(): void {
        $ecosystems = ['elementor'];
        
        // Test core Elementor classes
        $this->assertTrue($this->manager->isClassExcepted('Controls_Manager', $ecosystems));
        $this->assertTrue($this->manager->isClassExcepted('Widget_Base', $ecosystems));
        $this->assertTrue($this->manager->isClassExcepted('Group_Control_Typography', $ecosystems));
        
        // Test pattern matching
        $this->assertTrue($this->manager->isClassExcepted('Group_Control_Custom', $ecosystems));
        $this->assertTrue($this->manager->isClassExcepted('Widget_Custom', $ecosystems));
        
        // Test non-Elementor classes
        $this->assertFalse($this->manager->isClassExcepted('SomeRandomClass', $ecosystems));
    }
    
    public function testWooCommerceClassExceptions(): void {
        $ecosystems = ['woocommerce'];
        
        // Test core WooCommerce classes
        $this->assertTrue($this->manager->isClassExcepted('WC_Product', $ecosystems));
        $this->assertTrue($this->manager->isClassExcepted('WC_Order', $ecosystems));
        $this->assertTrue($this->manager->isClassExcepted('WC_Payment_Gateway', $ecosystems));
        
        // Test pattern matching
        $this->assertTrue($this->manager->isClassExcepted('WC_Custom_Class', $ecosystems));
        
        // Test non-WooCommerce classes
        $this->assertFalse($this->manager->isClassExcepted('SomeRandomClass', $ecosystems));
    }
    
    public function testFunctionExceptions(): void {
        $elementorEcosystems = ['elementor'];
        $wooEcosystems = ['woocommerce'];
        
        // Test Elementor functions
        $this->assertTrue($this->manager->isFunctionExcepted('elementor_pro_load_plugin', $elementorEcosystems));
        $this->assertTrue($this->manager->isFunctionExcepted('elementor_get_post_id', $elementorEcosystems));
        
        // Test WooCommerce functions
        $this->assertTrue($this->manager->isFunctionExcepted('wc_get_product', $wooEcosystems));
        $this->assertTrue($this->manager->isFunctionExcepted('is_woocommerce', $wooEcosystems));
        $this->assertTrue($this->manager->isFunctionExcepted('woocommerce_get_page_id', $wooEcosystems));
        
        // Test pattern matching
        $this->assertTrue($this->manager->isFunctionExcepted('wc_custom_function', $wooEcosystems));
        $this->assertTrue($this->manager->isFunctionExcepted('elementor_custom_function', $elementorEcosystems));
        
        // Test non-ecosystem functions
        $this->assertFalse($this->manager->isFunctionExcepted('some_random_function', $elementorEcosystems));
        $this->assertFalse($this->manager->isFunctionExcepted('some_random_function', $wooEcosystems));
    }
    
    public function testGlobalExceptions(): void {
        $ecosystems = [];
        
        // Test global class exceptions
        $this->assertTrue($this->manager->isClassExcepted('Composer\\Autoload\\ClassLoader', $ecosystems));
        $this->assertTrue($this->manager->isClassExcepted('Psr\\Log\\LoggerInterface', $ecosystems));
        
        // Test global function exceptions
        $this->assertTrue($this->manager->isFunctionExcepted('wp_doing_ajax', $ecosystems));
        $this->assertTrue($this->manager->isFunctionExcepted('wp_is_json_request', $ecosystems));
    }
    
    public function testMultipleEcosystems(): void {
        $ecosystems = ['elementor', 'woocommerce'];
        
        // Should work with either ecosystem
        $this->assertTrue($this->manager->isClassExcepted('Controls_Manager', $ecosystems));
        $this->assertTrue($this->manager->isClassExcepted('WC_Product', $ecosystems));
        $this->assertTrue($this->manager->isFunctionExcepted('elementor_get_post_id', $ecosystems));
        $this->assertTrue($this->manager->isFunctionExcepted('wc_get_product', $ecosystems));
    }
    
    public function testNoEcosystems(): void {
        $ecosystems = [];
        
        // Should only match global exceptions
        $this->assertTrue($this->manager->isClassExcepted('Composer\\Autoload\\ClassLoader', $ecosystems));
        $this->assertFalse($this->manager->isClassExcepted('Controls_Manager', $ecosystems));
        $this->assertFalse($this->manager->isClassExcepted('WC_Product', $ecosystems));
    }
    
    public function testGetClassExceptionReason(): void {
        $ecosystems = ['elementor'];
        
        $reason = $this->manager->getClassExceptionReason('Controls_Manager', $ecosystems);
        $this->assertStringContains('elementor', $reason);
        $this->assertStringContains('Controls_Manager', $reason);
        
        $reason = $this->manager->getClassExceptionReason('SomeRandomClass', $ecosystems);
        $this->assertNull($reason);
    }
    
    public function testAddCustomEcosystemExceptions(): void {
        $customExceptions = [
            'classes' => ['CustomClass1', 'CustomClass2'],
            'class_patterns' => ['Custom_*'],
            'functions' => ['custom_function'],
            'function_patterns' => ['custom_*'],
        ];
        
        $this->manager->addEcosystemExceptions('custom', $customExceptions);
        
        $ecosystems = ['custom'];
        
        $this->assertTrue($this->manager->isClassExcepted('CustomClass1', $ecosystems));
        $this->assertTrue($this->manager->isClassExcepted('Custom_Widget', $ecosystems));
        $this->assertTrue($this->manager->isFunctionExcepted('custom_function', $ecosystems));
        $this->assertTrue($this->manager->isFunctionExcepted('custom_helper', $ecosystems));
    }
    
    public function testGetEcosystemExceptions(): void {
        $elementorExceptions = $this->manager->getEcosystemExceptions('elementor');
        
        $this->assertArrayHasKey('classes', $elementorExceptions);
        $this->assertArrayHasKey('class_patterns', $elementorExceptions);
        $this->assertArrayHasKey('functions', $elementorExceptions);
        $this->assertArrayHasKey('function_patterns', $elementorExceptions);
        
        $this->assertContains('Controls_Manager', $elementorExceptions['classes']);
        $this->assertContains('Widget_Base', $elementorExceptions['classes']);
    }
    
    public function testCaseInsensitiveEcosystemNames(): void {
        $ecosystems = ['ELEMENTOR'];
        
        $this->assertTrue($this->manager->isClassExcepted('Controls_Manager', $ecosystems));
        
        $ecosystems = ['WooCommerce'];
        
        $this->assertTrue($this->manager->isClassExcepted('WC_Product', $ecosystems));
    }
}
