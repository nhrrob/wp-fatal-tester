<?php

namespace NHRROB\WPFatalTester\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\SyntaxErrorDetector;

class SyntaxErrorDetectorMultilineEchoTest extends TestCase
{
    private SyntaxErrorDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new SyntaxErrorDetector();
    }

    public function testMultilineEchoStatementsNotFlaggedAsMissingSemicolon(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_multiline_echo');
        file_put_contents($testFile, '<?php
// Multi-line echo statements that should NOT be flagged as missing semicolons
class TwitterFeedElement {
    public function render() {
        echo \'<style>
            .twitter-feed { display: flex; }
        </style>\';
        
        echo "<div class=\"tweet-item\">
                <span class=\"username\">@user</span>
              </div>";
        
        echo \'<style type="text/css">
            .widget { background: red; }
        </style>\';
        
        echo "<script>
                var data = {\"key\": \"value\"};
              </script>";
    }
}
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');
        
        // Filter for only MISSING_SEMICOLON errors
        $missingSemicolonErrors = array_filter($errors, function($error) {
            return $error->type === 'MISSING_SEMICOLON';
        });
        
        // Should have NO missing semicolon errors for valid multi-line echo statements
        $this->assertCount(0, $missingSemicolonErrors, 
            'Multi-line echo statements should not be flagged as missing semicolons');
        
        unlink($testFile);
    }

    public function testLegitimatelyMissingSemicolonsStillDetected(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_missing_semicolons');
        file_put_contents($testFile, '<?php
// These should still be flagged as missing semicolons
class TestClass {
    public function test() {
        $variable = "test"  // Missing semicolon
        echo "simple string"  // Missing semicolon
        print "another string"  // Missing semicolon
    }
}
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');
        
        // Filter for only MISSING_SEMICOLON errors
        $missingSemicolonErrors = array_filter($errors, function($error) {
            return $error->type === 'MISSING_SEMICOLON';
        });
        
        // Should detect 3 missing semicolons
        $this->assertCount(3, $missingSemicolonErrors, 
            'Should still detect legitimate missing semicolons');
        
        unlink($testFile);
    }

    public function testComplexMultilineEchoWithMixedQuotes(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_complex_echo');
        file_put_contents($testFile, '<?php
class ComplexEchoTest {
    public function render($settings) {
        // Complex multi-line echo with PHP variables and mixed quotes
        echo \'<div class="widget-\' . $settings[\'id\'] . \'">
                <h3>\' . $settings[\'title\'] . \'</h3>
                <p class="description">\' . $settings[\'desc\'] . \'</p>
              </div>\';
        
        // Another complex case with double quotes
        echo "<style>
                .widget-{$settings[\'id\']} {
                    background: {$settings[\'bg_color\']};
                }
              </style>";
    }
}
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');
        
        // Filter for only MISSING_SEMICOLON errors
        $missingSemicolonErrors = array_filter($errors, function($error) {
            return $error->type === 'MISSING_SEMICOLON';
        });
        
        // Should have NO missing semicolon errors
        $this->assertCount(0, $missingSemicolonErrors, 
            'Complex multi-line echo statements with mixed quotes should not be flagged');
        
        unlink($testFile);
    }

    public function testSingleLineEchoStatementsStillWork(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_single_line_echo');
        file_put_contents($testFile, '<?php
class SingleLineEchoTest {
    public function render() {
        // These are valid single-line echo statements (should NOT be flagged)
        echo "<div>Complete statement</div>";
        echo \'<span>Another complete statement</span>\';
        print "Print statement with semicolon";
        
        // This should be flagged (missing semicolon)
        echo "incomplete statement"  // Missing semicolon
    }
}
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');
        
        // Filter for only MISSING_SEMICOLON errors
        $missingSemicolonErrors = array_filter($errors, function($error) {
            return $error->type === 'MISSING_SEMICOLON';
        });
        
        // Should detect only 1 missing semicolon (the incomplete statement)
        $this->assertCount(1, $missingSemicolonErrors, 
            'Should detect only the legitimately missing semicolon');
        
        unlink($testFile);
    }
}
