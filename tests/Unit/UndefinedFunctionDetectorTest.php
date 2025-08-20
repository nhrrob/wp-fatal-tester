<?php

namespace NHRROB\WPFatalTester\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\UndefinedFunctionDetector;

class UndefinedFunctionDetectorTest extends TestCase
{
    private UndefinedFunctionDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new UndefinedFunctionDetector();
    }

    public function testClassInstantiationsNotFlaggedAsUndefinedFunctions(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_class_instantiations');
        file_put_contents($testFile, '<?php
// These should NOT be flagged as undefined functions (they are class instantiations)
$query = new WP_Query($args);
$wc_query = new WC_Query();
$exception = new Exception("test");
$dom = new DOMDocument();
$roles = new WP_Roles();

// Exception handling - should NOT be flagged
try {
    throw new InvalidArgumentException("test");
} catch (Exception $e) {
    error_log($e->getMessage());
} catch (RuntimeException $e) {
    error_log($e->getMessage());
}

// These SHOULD be flagged as undefined functions (they are actual function calls)
$result = some_undefined_function();
$another = another_undefined_function($param);
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');
        
        // Filter to only undefined function errors
        $undefinedFunctionErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_FUNCTION';
        });

        // Should only detect the actual undefined functions, not class instantiations
        $this->assertCount(2, $undefinedFunctionErrors);

        $errorMessages = array_map(function($error) {
            return $error->message;
        }, $undefinedFunctionErrors);

        // Should detect these undefined functions
        $this->assertContains("Call to undefined function 'some_undefined_function'", $errorMessages);
        $this->assertContains("Call to undefined function 'another_undefined_function'", $errorMessages);

        // Should NOT detect these class names as functions
        $this->assertNotContains("Call to undefined function 'WP_Query'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'WC_Query'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'Exception'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'DOMDocument'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'WP_Roles'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'InvalidArgumentException'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'RuntimeException'", $errorMessages);

        unlink($testFile);
    }

    public function testExceptionHandlingNotFlaggedAsUndefinedFunctions(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_exception_handling');
        file_put_contents($testFile, '<?php
try {
    // Some code that might throw
    throw new CustomException("test");
} catch (CustomException $e) {
    // Handle custom exception
} catch (Exception $e) {
    // Handle general exception
}
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');
        
        // Filter to only undefined function errors
        $undefinedFunctionErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_FUNCTION';
        });

        $errorMessages = array_map(function($error) {
            return $error->message;
        }, $undefinedFunctionErrors);

        // Should NOT detect exception class names as functions
        $this->assertNotContains("Call to undefined function 'CustomException'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'Exception'", $errorMessages);
    }

    public function testActualFunctionCallsStillDetected(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_actual_functions');
        file_put_contents($testFile, '<?php
// These should be flagged as undefined functions
$result1 = undefined_function_one();
$result2 = undefined_function_two($param);
$result3 = another_undefined_function();
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');

        // Filter to only undefined function errors
        $undefinedFunctionErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_FUNCTION';
        });

        // Should detect all three undefined functions
        $this->assertCount(3, $undefinedFunctionErrors);

        $errorMessages = array_map(function($error) {
            return $error->message;
        }, $undefinedFunctionErrors);

        $this->assertContains("Call to undefined function 'undefined_function_one'", $errorMessages);
        $this->assertContains("Call to undefined function 'undefined_function_two'", $errorMessages);
        $this->assertContains("Call to undefined function 'another_undefined_function'", $errorMessages);

        unlink($testFile);
    }

    public function testWordPressAdminFunctionsDetectedAsUndefined(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_wp_admin_functions');
        file_put_contents($testFile, '<?php
// These WordPress admin functions should be flagged as undefined
// because they require wp-admin/includes/plugin.php to be loaded
if (is_plugin_active_for_network("my-plugin/my-plugin.php")) {
    echo "Plugin is active for network";
}

if (is_plugin_active("another-plugin/another-plugin.php")) {
    echo "Plugin is active";
}

activate_plugin("test-plugin/test-plugin.php");
deactivate_plugins(array("test-plugin/test-plugin.php"));
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');

        // Filter to only undefined function errors
        $undefinedFunctionErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_FUNCTION';
        });

        $errorMessages = array_map(function($error) {
            return $error->message;
        }, $undefinedFunctionErrors);

        // These WordPress admin functions should be detected as undefined
        $this->assertContains("Call to undefined function 'is_plugin_active_for_network'", $errorMessages);
        $this->assertContains("Call to undefined function 'is_plugin_active'", $errorMessages);
        $this->assertContains("Call to undefined function 'activate_plugin'", $errorMessages);
        $this->assertContains("Call to undefined function 'deactivate_plugins'", $errorMessages);

        unlink($testFile);
    }
}
