<?php

namespace NHRROB\WPFatalTester\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\UndefinedFunctionDetector;
use NHRROB\WPFatalTester\Exceptions\DependencyExceptionManager;

class UndefinedFunctionDetectorTest extends TestCase
{
    private UndefinedFunctionDetector $detector;

    protected function setUp(): void
    {
        $exceptionManager = new DependencyExceptionManager();
        $this->detector = new UndefinedFunctionDetector($exceptionManager);
        // Set ecosystems like the main application does
        $this->detector->setDetectedEcosystems(['elementor', 'woocommerce']);
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

    public function testStaticMethodCallsNotFlaggedAsUndefinedFunctions(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_static_methods');
        file_put_contents($testFile, '<?php
// These should NOT be flagged as undefined functions (they are static method calls)
$result1 = Helper::render_post_meta_dates($settings);
$result2 = self::woo_checkout_render_split_template_($checkout, $settings);
$result3 = parent::some_parent_method($param);
$result4 = MyClass::staticMethod();
$result5 = \Namespace\Class::method();

// These SHOULD be flagged as undefined functions (they are actual function calls)
$actual_function = some_undefined_function();
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');

        // Filter to only undefined function errors
        $undefinedFunctionErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_FUNCTION';
        });

        // Should only detect the actual undefined function, not the static method calls
        $this->assertCount(1, $undefinedFunctionErrors);

        $errorMessages = array_map(function($error) {
            return $error->message;
        }, $undefinedFunctionErrors);

        // Should NOT detect static method names as functions
        $this->assertNotContains("Call to undefined function 'render_post_meta_dates'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'woo_checkout_render_split_template_'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'some_parent_method'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'staticMethod'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'method'", $errorMessages);

        // Should detect the actual undefined function
        $this->assertContains("Call to undefined function 'some_undefined_function'", $errorMessages);

        unlink($testFile);
    }

    public function testInstanceMethodCallsNotFlaggedAsUndefinedFunctions(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_instance_methods');
        file_put_contents($testFile, '<?php
// These should NOT be flagged as undefined functions (they are instance method calls)
$result1 = $this->eael_wp_allowed_tags(array("viber"));
$result2 = $object->someMethod($param);
$result3 = $helper->render_something();

// These SHOULD be flagged as undefined functions (they are actual function calls)
$actual_function = another_undefined_function();
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');

        // Filter to only undefined function errors
        $undefinedFunctionErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_FUNCTION';
        });

        // Should only detect the actual undefined function, not the instance method calls
        $this->assertCount(1, $undefinedFunctionErrors);

        $errorMessages = array_map(function($error) {
            return $error->message;
        }, $undefinedFunctionErrors);

        // Should NOT detect instance method names as functions
        $this->assertNotContains("Call to undefined function 'eael_wp_allowed_tags'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'someMethod'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'render_something'", $errorMessages);

        // Should detect the actual undefined function
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

    public function testEssentialAddonsSpecificCases(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_essential_addons');
        file_put_contents($testFile, '<?php
// Exact lines from Essential Addons that are being flagged incorrectly
echo self::woo_checkout_render_split_template_($checkout, $settings);
echo self::woo_checkout_render_multi_steps_template_($checkout, $settings);
Helper::render_post_meta_dates($settings);
$eael_wp_allowed_tags = $this->eael_wp_allowed_tags( array( "viber" ) );

// This should still be flagged as undefined
if (is_plugin_active($basename)) {
    echo "test";
}
');

        $errors = $this->detector->detect($testFile, '7.4', '6.6');

        // Filter to only undefined function errors
        $undefinedFunctionErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_FUNCTION';
        });

        $errorMessages = array_map(function($error) {
            return $error->message;
        }, $undefinedFunctionErrors);

        // Should NOT detect static/instance method calls as functions
        $this->assertNotContains("Call to undefined function 'woo_checkout_render_split_template_'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'woo_checkout_render_multi_steps_template_'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'render_post_meta_dates'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'eael_wp_allowed_tags'", $errorMessages);

        // Should detect the actual undefined function
        $this->assertContains("Call to undefined function 'is_plugin_active'", $errorMessages);

        unlink($testFile);
    }

    public function testActualEssentialAddonsFile(): void
    {
        // Test the actual files that are causing issues
        $testFiles = [
            '../essential-addons-dev/wp-content/plugins/essential-addons-elementor/includes/Traits/Extender.php',
            '../essential-addons-dev/wp-content/plugins/essential-addons-elementor/includes/Classes/Helper.php',
            '../essential-addons-dev/wp-content/plugins/essential-addons-elementor/includes/Template/Post-List/default.php'
        ];

        $allErrorMessages = [];

        foreach ($testFiles as $filePath) {
            if (!file_exists($filePath)) {
                continue;
            }

            $errors = $this->detector->detect($filePath, '7.4', '6.6');

            // Filter to only undefined function errors
            $undefinedFunctionErrors = array_filter($errors, function($error) {
                return $error->type === 'UNDEFINED_FUNCTION';
            });

            $errorMessages = array_map(function($error) {
                return $error->message;
            }, $undefinedFunctionErrors);

            $allErrorMessages = array_merge($allErrorMessages, $errorMessages);
        }

        // Debug: print all error messages to see what's being detected
        foreach ($allErrorMessages as $message) {
            echo "\nDetected: " . $message;
        }

        // Should NOT detect static method calls as functions
        $this->assertNotContains("Call to undefined function 'woo_checkout_render_split_template_'", $allErrorMessages);
        $this->assertNotContains("Call to undefined function 'woo_checkout_render_multi_steps_template_'", $allErrorMessages);
        $this->assertNotContains("Call to undefined function 'render_post_meta_dates'", $allErrorMessages);
        $this->assertNotContains("Call to undefined function 'eael_wp_allowed_tags'", $allErrorMessages);
    }

    public function testStaticFunctionDefinitionsNotFlaggedAsUndefinedFunctions(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_static_function_definitions');
        file_put_contents($testFile, '<?php
class Helper {
    // These should NOT be flagged as undefined functions (they are function definitions)
    public static function eael_pro_validate_html_tag($tag) {
        return $tag;
    }

    public static function render_post_meta_dates($settings) {
        return $settings;
    }

    private static function private_method() {
        return true;
    }

    protected static function protected_method() {
        return true;
    }

    public function instance_method() {
        return true;
    }
}

// Standalone function definitions should also not be flagged
function standalone_function() {
    return true;
}

// This SHOULD be flagged as undefined function (it is an actual function call)
$result = some_undefined_function();
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');

        // Filter to only undefined function errors
        $undefinedFunctionErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_FUNCTION';
        });

        // Should only detect the actual undefined function, not the function definitions
        $this->assertCount(1, $undefinedFunctionErrors);

        $errorMessages = array_map(function($error) {
            return $error->message;
        }, $undefinedFunctionErrors);

        // Should NOT detect function definitions as undefined functions
        $this->assertNotContains("Call to undefined function 'eael_pro_validate_html_tag'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'render_post_meta_dates'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'private_method'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'protected_method'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'instance_method'", $errorMessages);
        $this->assertNotContains("Call to undefined function 'standalone_function'", $errorMessages);

        // Should detect the actual undefined function
        $this->assertContains("Call to undefined function 'some_undefined_function'", $errorMessages);

        unlink($testFile);
    }
}
