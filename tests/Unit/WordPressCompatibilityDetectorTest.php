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

    public function testMethodCallsNotFlaggedAsDeprecated(): void {
        // Test that method calls like $object->get_settings() are not flagged as deprecated
        $phpContent = <<<PHP
<?php
class TestClass {
    public function test_method() {
        // These should NOT be flagged as deprecated WordPress functions
        \$settings = \$this->get_settings('some_key');
        \$data = \$document->get_settings('another_key');
        \$value = SomeClass::get_settings('static_call');

        // This SHOULD be flagged as deprecated (standalone function call)
        \$old_setting = get_settings('deprecated_call');

        return \$settings;
    }
}
PHP;

        file_put_contents($this->testFilePath, $phpContent);

        $errors = $this->detector->detect($this->testFilePath, '8.1', '6.6');

        // Filter for deprecated function errors related to get_settings
        $getSettingsErrors = array_filter($errors, function($error) {
            return $error->type === 'DEPRECATED_FUNCTION' &&
                   isset($error->context['function']) &&
                   $error->context['function'] === 'get_settings';
        });

        // Should only have 1 error for the standalone function call, not the method calls
        $this->assertCount(1, $getSettingsErrors, 'Only standalone get_settings() calls should be flagged, not method calls');

        // Verify the error is for the correct function
        $error = array_values($getSettingsErrors)[0];
        $this->assertEquals('get_settings', $error->context['function']);
        $this->assertStringContainsString('get_settings(\'deprecated_call\')', file_get_contents($this->testFilePath));
    }

    public function testFunctionDefinitionsNotFlaggedAsDeprecated(): void {
        // Test that function definitions are not flagged as deprecated
        $phpContent = <<<PHP
<?php
class TestClass {
    // This should NOT be flagged as deprecated (function definition)
    public function get_settings(\$key = null) {
        return get_option('my_settings', []);
    }

    // This should NOT be flagged as deprecated (function definition)
    private function clean_url(\$url) {
        return sanitize_url(\$url);
    }
}

// This should NOT be flagged as deprecated (function definition)
function get_settings(\$key) {
    return get_option('custom_settings', []);
}
PHP;

        file_put_contents($this->testFilePath, $phpContent);

        $errors = $this->detector->detect($this->testFilePath, '8.1', '6.6');

        // Filter for deprecated function errors
        $deprecatedErrors = array_filter($errors, function($error) {
            return $error->type === 'DEPRECATED_FUNCTION';
        });

        // Should have no deprecated function errors since these are all function definitions
        $this->assertEmpty($deprecatedErrors, 'Function definitions should not be flagged as deprecated');
    }

    public function testMixedScenarioWithMethodsAndFunctions(): void {
        // Test a realistic scenario mixing method calls, function definitions, and actual deprecated calls
        $phpContent = <<<PHP
<?php
class ElementorWidget {
    // Function definition - should NOT be flagged
    public function get_settings(\$key = null) {
        return \$this->settings[\$key] ?? null;
    }

    public function render() {
        // Method calls - should NOT be flagged
        \$title = \$this->get_settings('title');
        \$document = \$this->get_document();
        \$page_settings = \$document->get_settings('page_settings');

        // Static method call - should NOT be flagged
        \$global_settings = GlobalSettings::get_settings('theme');

        // Standalone deprecated function call - SHOULD be flagged
        \$old_way = get_settings('deprecated_option');

        return '<div>' . \$title . '</div>';
    }
}
PHP;

        file_put_contents($this->testFilePath, $phpContent);

        $errors = $this->detector->detect($this->testFilePath, '8.1', '6.6');

        // Filter for deprecated function errors related to get_settings
        $getSettingsErrors = array_filter($errors, function($error) {
            return $error->type === 'DEPRECATED_FUNCTION' &&
                   isset($error->context['function']) &&
                   $error->context['function'] === 'get_settings';
        });

        // Should only have 1 error for the standalone function call
        $this->assertCount(1, $getSettingsErrors, 'Only the standalone get_settings() call should be flagged');

        // Verify it's the correct line
        $error = array_values($getSettingsErrors)[0];
        $this->assertEquals('get_settings', $error->context['function']);
        $this->assertEquals('2.1.0', $error->context['deprecated_version']);
        $this->assertEquals('get_option', $error->context['replacement']);
    }

    public function testCommentsAreIgnored(): void {
        // Test that function names in comments are not flagged
        $phpContent = <<<PHP
<?php
class TestClass {
    public function test_method() {
        // This comment mentions get_settings() but should not be flagged
        /* Another comment with get_settings() function */

        // This is an actual call and SHOULD be flagged
        \$value = get_settings('test');

        return \$value;
    }
}
PHP;

        file_put_contents($this->testFilePath, $phpContent);

        $errors = $this->detector->detect($this->testFilePath, '8.1', '6.6');

        // Filter for deprecated function errors related to get_settings
        $getSettingsErrors = array_filter($errors, function($error) {
            return $error->type === 'DEPRECATED_FUNCTION' &&
                   isset($error->context['function']) &&
                   $error->context['function'] === 'get_settings';
        });

        // Should only have 1 error for the actual function call, not the comments
        $this->assertCount(1, $getSettingsErrors, 'Comments should be ignored, only actual function calls should be flagged');
    }
}
