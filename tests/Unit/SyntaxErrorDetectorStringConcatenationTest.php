<?php

namespace NHRROB\WPFatalTester\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\SyntaxErrorDetector;

class SyntaxErrorDetectorStringConcatenationTest extends TestCase
{
    private SyntaxErrorDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new SyntaxErrorDetector();
    }

    public function testMultilineStringConcatenationWithNestedFunctionCalls(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_string_concat');
        $content = '<?php
class Helper {
    public function buildQuery($post_type, $in_search_post_types) {
        global $wpdb;
        $where = "";
        
        // This is the exact pattern from Helper.php that was causing false positives
        if (\'any\' === $post_type) {
            $in_search_post_types = get_post_types([\'exclude_from_search\' => false]);
            if (empty($in_search_post_types)) {
                $where .= \' AND 1=0 \';
            } else {
                $where .= " AND {$wpdb->posts}.post_type IN (\'" . join("\', \'",
                    array_map(\'esc_sql\', $in_search_post_types)) . "\')";
            }
        }
        
        return $where;
    }
}
';
        file_put_contents($testFile, $content);

        $errors = $this->detector->detect($testFile, '8.0', '6.6');

        // Filter for only UNMATCHED_BRACKETS errors
        $unmatchedBracketErrors = array_filter($errors, function($error) {
            return $error->type === 'UNMATCHED_BRACKETS';
        });

        // Should have NO unmatched bracket errors for valid multi-line string concatenation
        $this->assertCount(0, $unmatchedBracketErrors,
            'Valid multi-line string concatenation with nested function calls should not be flagged as unmatched brackets');
        
        unlink($testFile);
    }

    public function testVariousStringConcatenationPatterns(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_various_concat');
        $content = '<?php
class StringConcatTest {
    public function testPatterns() {
        global $wpdb;
        
        // Pattern 1: String concat with function call ending with comma
        $sql = "SELECT * FROM table WHERE id IN (\'" . join("\', \'",
            array_map(\'intval\', $ids)) . "\')";
            
        // Pattern 2: More complex nested function calls
        $output = "<div class=\'" . esc_attr(get_option(\'theme_class\',
            \'default-class\')) . "\'>";
            
        // Pattern 3: Multiple concatenations in one statement
        $result = $prefix . "start" . some_function("arg1",
            "arg2") . "end" . $suffix;
            
        // Pattern 4: WordPress-style concatenation with apply_filters
        $class = esc_attr(apply_filters(\'woocommerce_cart_item_class\',
            \'cart_item\', $cart_item, $cart_item_key));
            
        // Pattern 5: Echo with multi-line concatenation
        echo "<tr class=\'" . esc_attr(apply_filters(\'item_class\',
            \'default\', $item)) . "\'>";
    }
}
';
        file_put_contents($testFile, $content);

        $errors = $this->detector->detect($testFile, '8.0', '6.6');

        // Filter for only UNMATCHED_BRACKETS errors
        $unmatchedBracketErrors = array_filter($errors, function($error) {
            return $error->type === 'UNMATCHED_BRACKETS';
        });

        // Should have NO unmatched bracket errors for any of these valid patterns
        $this->assertCount(0, $unmatchedBracketErrors,
            'Various valid string concatenation patterns should not be flagged as unmatched brackets');
        
        unlink($testFile);
    }

    public function testRealBracketMismatchesStillDetected(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_real_mismatches');
        $content = '<?php
class RealErrorTest {
    public function testMethod() {
        // Real syntax error - missing closing parenthesis in function call
        $result = some_function( $arg1, $arg2;
        
        // Real syntax error - extra closing bracket
        $array = array( "key" => "value" ));
        
        // Real syntax error - unmatched string quotes with brackets
        $bad = "unclosed string with function( $arg );
    }
}
';
        file_put_contents($testFile, $content);

        $errors = $this->detector->detect($testFile, '8.0', '6.6');
        
        // Should detect real syntax errors via php -l (not our custom bracket matching)
        // Real syntax errors will be caught by PHP\'s built-in syntax checker
        $syntaxErrors = array_filter($errors, function($error) {
            return in_array($error->type, ['SYNTAX_ERROR', 'FATAL_SYNTAX_ERROR']);
        });
        
        $this->assertNotEmpty($syntaxErrors, 'Real syntax errors should still be detected by PHP syntax checker');
        
        unlink($testFile);
    }

    public function testComplexWordPressPatterns(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_wp_patterns');
        $content = '<?php
class WordPressPatternTest {
    public function renderTemplate($settings, $cart_item, $cart_item_key) {
        // Pattern from Woo_Cart_Helper.php
        echo esc_attr( apply_filters( \'woocommerce_cart_item_class\',
            \'cart_item\', $cart_item, $cart_item_key ) );
            
        // Another complex WordPress pattern
        $permalink = apply_filters( \'woocommerce_cart_item_permalink\',
            $_product->is_visible() ? $_product->get_permalink( $cart_item ) : \'\', $cart_item,
            $cart_item_key );
            
        // Complex string building with multiple function calls
        $output = "<div class=\'" . esc_attr(apply_filters(\'widget_class\',
            get_option(\'default_class\', \'widget\'), $widget_id)) . "\'>" .
            get_the_content(apply_filters(\'content_filter\',
                \'read_more\', $post_id)) . "</div>";
    }
}
';
        file_put_contents($testFile, $content);

        $errors = $this->detector->detect($testFile, '8.0', '6.6');

        // Filter for only UNMATCHED_BRACKETS errors
        $unmatchedBracketErrors = array_filter($errors, function($error) {
            return $error->type === 'UNMATCHED_BRACKETS';
        });

        // Should have NO unmatched bracket errors for complex WordPress patterns
        $this->assertCount(0, $unmatchedBracketErrors,
            'Complex WordPress patterns with multi-line function calls should not be flagged');
        
        unlink($testFile);
    }
}
