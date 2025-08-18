<?php

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\WordPressCompatibilityDetector;

class WordPressCompatibilityDetectorTest extends TestCase {
    
    private WordPressCompatibilityDetector $detector;
    private string $testFilePath;
    
    protected function setUp(): void {
        $this->detector = new WordPressCompatibilityDetector();
        $this->testFilePath = sys_get_temp_dir() . '/test-wp-compat-' . uniqid() . '.php';
    }
    
    protected function tearDown(): void {
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
    }
    
    public function testSanitizeUrlIsNotFlaggedAsRemoved(): void {
        // Test that sanitize_url() is not flagged as a removed function
        // since it was restored in WordPress 5.9.0
        $phpContent = <<<PHP
<?php
function test_function() {
    \$url = sanitize_url('https://example.com');
    return \$url;
}
PHP;
        
        file_put_contents($this->testFilePath, $phpContent);
        
        // Test with WordPress 6.0 (after 5.9.0 when sanitize_url was restored)
        $errors = $this->detector->detect($this->testFilePath, '8.1', '6.0');
        
        // Filter for removed function errors related to sanitize_url
        $sanitizeUrlErrors = array_filter($errors, function($error) {
            return $error->type === 'REMOVED_FUNCTION' && 
                   isset($error->context['function']) && 
                   $error->context['function'] === 'sanitize_url';
        });
        
        $this->assertEmpty($sanitizeUrlErrors, 'sanitize_url() should not be flagged as removed since it was restored in WordPress 5.9.0');
    }
    
    public function testSanitizeUrlIsNotFlaggedAsDeprecated(): void {
        // Test that sanitize_url() is not flagged as deprecated
        $phpContent = <<<PHP
<?php
function test_function() {
    \$url = sanitize_url('https://example.com');
    return \$url;
}
PHP;
        
        file_put_contents($this->testFilePath, $phpContent);
        
        // Test with WordPress 6.0
        $errors = $this->detector->detect($this->testFilePath, '8.1', '6.0');
        
        // Filter for deprecated function errors related to sanitize_url
        $sanitizeUrlErrors = array_filter($errors, function($error) {
            return $error->type === 'DEPRECATED_FUNCTION' && 
                   isset($error->context['function']) && 
                   $error->context['function'] === 'sanitize_url';
        });
        
        $this->assertEmpty($sanitizeUrlErrors, 'sanitize_url() should not be flagged as deprecated since it was restored in WordPress 5.9.0');
    }
    
    public function testRemovedFunctionStillDetected(): void {
        // Test that actually removed functions are still detected
        $phpContent = <<<PHP
<?php
function test_function() {
    \$result = clean_url('https://example.com'); // This was actually removed
    return \$result;
}
PHP;
        
        file_put_contents($this->testFilePath, $phpContent);
        
        // Test with WordPress 6.0 (after 3.0.0 when clean_url was removed)
        $errors = $this->detector->detect($this->testFilePath, '8.1', '6.0');
        
        // Filter for removed function errors related to clean_url
        $cleanUrlErrors = array_filter($errors, function($error) {
            return $error->type === 'REMOVED_FUNCTION' && 
                   isset($error->context['function']) && 
                   $error->context['function'] === 'clean_url';
        });
        
        $this->assertNotEmpty($cleanUrlErrors, 'clean_url() should still be flagged as removed');
        $this->assertEquals('clean_url', $cleanUrlErrors[array_key_first($cleanUrlErrors)]->context['function']);
        $this->assertEquals('3.0.0', $cleanUrlErrors[array_key_first($cleanUrlErrors)]->context['removed_version']);
    }
    
    public function testDetectorName(): void {
        $this->assertEquals('WordPress Compatibility Detector', $this->detector->getName());
    }
}
