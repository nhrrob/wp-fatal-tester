<?php

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\ClassConflictDetector;
use NHRROB\WPFatalTester\Detectors\PluginEcosystemDetector;
use NHRROB\WPFatalTester\Exceptions\DependencyExceptionManager;

class QueryClassesIntegrationTest extends TestCase {
    
    private string $tempDir;
    
    protected function setUp(): void {
        $this->tempDir = sys_get_temp_dir() . '/wp-fatal-tester-integration-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }
    
    protected function tearDown(): void {
        $this->removeDirectory($this->tempDir);
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
    
    private function createTestPlugin(string $content): string {
        $pluginFile = $this->tempDir . '/test-plugin.php';
        file_put_contents($pluginFile, $content);
        return $pluginFile;
    }
    
    public function testWordPressQueryClassesNotReportedAsErrors(): void {
        $pluginContent = '<?php
/**
 * Plugin Name: Test Plugin with Query Classes
 * Description: A test plugin that uses various WordPress Query classes
 * Version: 1.0.0
 */

class TestQueryPlugin {
    public function init() {
        // WordPress core query classes
        $query = new WP_Query(array("post_type" => "post"));
        $user_query = new WP_User_Query(array("role" => "subscriber"));
        $comment_query = new WP_Comment_Query(array("status" => "approve"));
        $term_query = new WP_Term_Query(array("taxonomy" => "category"));
        $site_query = new WP_Site_Query(array("number" => 10));
        $meta_query = new WP_Meta_Query(array());
        $date_query = new WP_Date_Query(array());
        $tax_query = new WP_Tax_Query(array());
        
        // PHP exception handling
        try {
            throw new InvalidArgumentException("Test exception");
        } catch (InvalidArgumentException $e) {
            error_log($e->getMessage());
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
        } catch (LogicException $e) {
            error_log($e->getMessage());
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
        
        // PHP built-in classes
        $date = new DateTime();
        $pdo = new PDO("sqlite::memory:");
        $dom = new DOMDocument();
        $reflection = new ReflectionClass(__CLASS__);
    }
}
';
        
        $pluginFile = $this->createTestPlugin($pluginContent);

        // Use ClassConflictDetector directly
        $detector = new ClassConflictDetector();
        $errors = $detector->detect($pluginFile, '8.0', '6.0');

        // Filter for undefined class errors
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });

        $this->assertEmpty($undefinedClassErrors, 'WordPress Query classes and PHP built-in classes should not be flagged as undefined');
    }
    
    public function testWooCommerceQueryClassesNotReportedAsErrors(): void {
        $pluginContent = '<?php
/**
 * Plugin Name: Test WooCommerce Plugin
 * Description: A test plugin that uses WooCommerce classes
 * Version: 1.0.0
 * WC tested up to: 8.0
 */

class TestWooCommercePlugin {
    public function init() {
        // WooCommerce query and core classes
        $wc_query = new WC_Query();
        $product = new WC_Product();
        $order = new WC_Order();
        $customer = new WC_Customer();
        $cart = new WC_Cart();
        
        // Should not be flagged as undefined when WooCommerce ecosystem is detected
    }
}
';
        
        $pluginFile = $this->createTestPlugin($pluginContent);

        // Use ClassConflictDetector with WooCommerce ecosystem detection
        $ecosystemDetector = new PluginEcosystemDetector();
        $detectedEcosystems = $ecosystemDetector->detectEcosystems(dirname($pluginFile));

        $detector = new ClassConflictDetector();
        $detector->setDetectedEcosystems($detectedEcosystems);
        $errors = $detector->detect($pluginFile, '8.0', '6.0');

        // Filter for undefined class errors
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });

        $this->assertEmpty($undefinedClassErrors, 'WooCommerce classes should not be flagged as undefined when WooCommerce ecosystem is detected');
    }
    
    public function testTrulyUndefinedClassesStillDetected(): void {
        $pluginContent = '<?php
/**
 * Plugin Name: Test Plugin with Undefined Classes
 * Description: A test plugin that uses undefined classes
 * Version: 1.0.0
 */

class TestUndefinedPlugin {
    public function init() {
        // These should still be detected as undefined
        $custom = new SomeCustomUndefinedClass();
        $another = new AnotherNonExistentClass();
    }
}
';
        
        $pluginFile = $this->createTestPlugin($pluginContent);

        // Use ClassConflictDetector directly
        $detector = new ClassConflictDetector();
        $errors = $detector->detect($pluginFile, '8.0', '6.0');

        // Filter for undefined class errors
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });

        $this->assertCount(2, $undefinedClassErrors, 'Truly undefined classes should still be detected');

        $errorMessages = array_map(function($error) {
            return $error->message;
        }, $undefinedClassErrors);

        $hasUndefinedCustomClass = false;
        $hasAnotherNonExistentClass = false;

        foreach ($errorMessages as $message) {
            if (strpos($message, 'SomeCustomUndefinedClass') !== false) {
                $hasUndefinedCustomClass = true;
            }
            if (strpos($message, 'AnotherNonExistentClass') !== false) {
                $hasAnotherNonExistentClass = true;
            }
        }

        $this->assertTrue($hasUndefinedCustomClass, 'Should detect SomeCustomUndefinedClass as undefined');
        $this->assertTrue($hasAnotherNonExistentClass, 'Should detect AnotherNonExistentClass as undefined');
    }
}
