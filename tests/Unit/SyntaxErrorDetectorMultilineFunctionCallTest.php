<?php

namespace NHRROB\WPFatalTester\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\SyntaxErrorDetector;

class SyntaxErrorDetectorMultilineFunctionCallTest extends TestCase {

    private SyntaxErrorDetector $detector;

    protected function setUp(): void {
        $this->detector = new SyntaxErrorDetector();
    }

    public function testMultilineMethodCallsNotFlaggedAsUnmatchedBrackets(): void {
        $testFile = tempnam(sys_get_temp_dir(), 'test_multiline_method_calls');
        $content = '<?php
class TestClass {
    public function testMethod() {
        // Multi-line method call like in Essential Addons
        $this->add_render_attribute( \'eael-woo-product-carousel-wrap\', \'data-stretch\',
            $settings[ \'carousel_stretch\' ][ \'size\' ] );

        // Another pattern
        $this->add_render_attribute( \'eael-woo-product-carousel-wrap\', \'data-rotate\',
            $settings[ \'carousel_rotate\' ][ \'size\' ] );

        // Function call pattern
        wp_enqueue_script( \'my-script\', \'path/to/script.js\',
            array( \'jquery\' ), \'1.0.0\', true );

        // Array pattern
        $array = array(
            \'key1\' => \'value1\',
            \'key2\' => \'value2\'
        );
    }
}
';
        file_put_contents($testFile, $content);

        $errors = $this->detector->detect($testFile, '8.1', '6.5');

        // Filter for only UNMATCHED_BRACKETS errors
        $unmatchedBracketErrors = array_filter($errors, function($error) {
            return $error->type === 'UNMATCHED_BRACKETS';
        });

        // Should have NO unmatched bracket errors for valid multi-line function calls
        $this->assertCount(0, $unmatchedBracketErrors,
            'Valid multi-line function calls should not be flagged as unmatched brackets');
        
        unlink($testFile);
    }

    public function testSpecificEssentialAddonsPattern(): void {
        $testFile = tempnam(sys_get_temp_dir(), 'test_ea_pattern');
        file_put_contents($testFile, '<?php
class Woo_Product_Carousel {
    public function render() {
        if ( !empty( $settings[ \'carousel_stretch\' ][ \'size\' ] ) ) {
            $this->add_render_attribute( \'eael-woo-product-carousel-wrap\', \'data-stretch\',
                $settings[ \'carousel_stretch\' ][ \'size\' ] );
        }
        
        if ( !empty( $settings[ \'margin\' ][ \'size\' ] ) ) {
            $this->add_render_attribute( \'eael-woo-product-carousel-wrap\', \'data-margin\',
                $settings[ \'margin\' ][ \'size\' ] );
        }
    }
}
');

        $errors = $this->detector->detect($testFile, '8.0', '6.6');
        
        // Filter for only UNMATCHED_BRACKETS errors
        $unmatchedBracketErrors = array_filter($errors, function($error) {
            return $error->type === 'UNMATCHED_BRACKETS';
        });
        
        // Should have NO unmatched bracket errors for this specific pattern
        $this->assertCount(0, $unmatchedBracketErrors, 
            'Essential Addons multi-line method call pattern should not be flagged');
        
        unlink($testFile);
    }

    public function testRealUnmatchedBracketsStillDetected(): void {
        $testFile = tempnam(sys_get_temp_dir(), 'test_real_unmatched');
        file_put_contents($testFile, '<?php
class TestClass {
    public function testMethod() {
        // Real syntax error - missing closing parenthesis
        $result = some_function( $arg1, $arg2;
        
        // Real syntax error - extra closing bracket
        $array = array( \'key\' => \'value\' ));
    }
}
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');
        
        // Should detect real syntax errors via php -l
        $this->assertNotEmpty($errors, 'Real syntax errors should still be detected');
        
        unlink($testFile);
    }

    public function testComplexMultilineStructures(): void {
        $testFile = tempnam(sys_get_temp_dir(), 'test_complex_multiline');
        file_put_contents($testFile, '<?php
class TestClass {
    public function testMethod() {
        // Complex nested function calls
        $this->some_method(
            array(
                \'key1\' => $this->another_method( \'arg1\', \'arg2\',
                    $nested_array[ \'key\' ][ \'subkey\' ] ),
                \'key2\' => \'value2\'
            ),
            \'additional_arg\'
        );
        
        // WordPress hook with closure
        add_action( \'wp_enqueue_scripts\', function() {
            wp_enqueue_script( \'my-script\', 
                plugin_dir_url( __FILE__ ) . \'assets/script.js\',
                array( \'jquery\' ), \'1.0.0\', true );
        });
    }
}
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');
        
        // Filter for only UNMATCHED_BRACKETS errors
        $unmatchedBracketErrors = array_filter($errors, function($error) {
            return $error->type === 'UNMATCHED_BRACKETS';
        });
        
        // Should have NO unmatched bracket errors for complex but valid structures
        $this->assertCount(0, $unmatchedBracketErrors, 
            'Complex multi-line structures should not be flagged as unmatched brackets');
        
        unlink($testFile);
    }
}
