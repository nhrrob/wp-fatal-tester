<?php

namespace NHRROB\WPFatalTester\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\SyntaxErrorDetector;

class SyntaxErrorDetectorEnhancedPatternsTest extends TestCase
{
    private SyntaxErrorDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new SyntaxErrorDetector();
    }

    public function testMultiLineApplyFiltersPatterns(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_apply_filters');
        $content = '<?php
// Multi-line apply_filters in HTML context - should not trigger UNMATCHED_BRACKETS
echo "<tr class=\"woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( \'woocommerce_cart_item_class\',
    \'cart_item\', $cart_item, $cart_item_key ) ); ?>\">";

// Multi-line apply_filters in div context
echo "<div class=\"product-class <?php echo esc_attr( apply_filters( \'product_class\',
    \'default\', $product ) ); ?>\">";
';
        file_put_contents($testFile, $content);

        $errors = $this->detector->detect($testFile, '8.0', '6.6');

        // Filter for only UNMATCHED_BRACKETS errors
        $unmatchedBracketErrors = array_filter($errors, function($error) {
            return $error->type === 'UNMATCHED_BRACKETS';
        });

        // Should have NO unmatched bracket errors for valid multi-line apply_filters patterns
        $this->assertCount(0, $unmatchedBracketErrors,
            'Multi-line apply_filters patterns should not be flagged as unmatched brackets');

        unlink($testFile);
    }

    public function testMultiLineJoinPatterns(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_join');
        $content = '<?php
// Multi-line join with array_map - should not trigger UNMATCHED_BRACKETS
$where .= " AND {$wpdb->posts}.post_type IN (\'" . join("\', \'",
    array_map(\'esc_sql\', $in_search_post_types)) . "\')";

// Another join pattern
$list = "(" . join(", ",
    array_map(\'intval\', $ids)) . ")";
';
        file_put_contents($testFile, $content);

        $errors = $this->detector->detect($testFile, '8.0', '6.6');

        // Filter for only UNMATCHED_BRACKETS errors
        $unmatchedBracketErrors = array_filter($errors, function($error) {
            return $error->type === 'UNMATCHED_BRACKETS';
        });

        // Should have NO unmatched bracket errors for valid multi-line join patterns
        $this->assertCount(0, $unmatchedBracketErrors,
            'Multi-line join patterns should not be flagged as unmatched brackets');

        unlink($testFile);
    }

    public function testWpGetAttachmentImageUrlPatterns(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_wp_get_attachment');
        $content = '<?php
// Multi-line wp_get_attachment_image_url - should not trigger UNMATCHED_BRACKETS
$image = \'<div class="eael-timeline-post-image" style="background-image: url(\'. wp_get_attachment_image_url
    (get_post_thumbnail_id(),
        $settings[\'image_size\']) .\')"></div>\';

// Another pattern
$url = wp_get_attachment_image_url(
    get_post_thumbnail_id(),
    \'full\'
);
';
        file_put_contents($testFile, $content);

        $errors = $this->detector->detect($testFile, '8.0', '6.6');

        // Filter for only UNMATCHED_BRACKETS errors
        $unmatchedBracketErrors = array_filter($errors, function($error) {
            return $error->type === 'UNMATCHED_BRACKETS';
        });

        // Should have NO unmatched bracket errors for valid wp_get_attachment_image_url patterns
        $this->assertCount(0, $unmatchedBracketErrors,
            'Multi-line wp_get_attachment_image_url patterns should not be flagged as unmatched brackets');

        unlink($testFile);
    }

    public function testTernaryOperatorPatterns(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_ternary');
        $content = '<?php
// Multi-line ternary operators - should not trigger UNMATCHED_BRACKETS
echo \'<p>\' . wp_trim_words( strip_shortcodes( get_the_excerpt() ? get_the_excerpt() :
        get_the_content() ), $settings[\'excerpt_length\'], $settings[\'expansion_indicator\'] ) . \'</p>\';

// Another ternary pattern
$content = get_the_excerpt() ? get_the_excerpt() :
    get_the_content();
';
        file_put_contents($testFile, $content);

        $errors = $this->detector->detect($testFile, '8.0', '6.6');

        // Filter for only UNMATCHED_BRACKETS errors
        $unmatchedBracketErrors = array_filter($errors, function($error) {
            return $error->type === 'UNMATCHED_BRACKETS';
        });

        // Should have NO unmatched bracket errors for valid ternary operator patterns
        $this->assertCount(0, $unmatchedBracketErrors,
            'Multi-line ternary operator patterns should not be flagged as unmatched brackets');

        unlink($testFile);
    }

    public function testContinuationLinePatterns(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_continuation');
        $content = '<?php
// Continuation lines with function arguments - should not trigger UNMATCHED_BRACKETS
echo esc_attr( apply_filters( \'woocommerce_cart_item_class\',
    \'cart_item\', $cart_item, $cart_item_key ) ); ?>";

// Array_map continuation
$where .= " AND {$wpdb->posts}.post_type IN (\'" . join("\', \'",
    array_map(\'esc_sql\', $in_search_post_types)) . "\')";

// Settings array continuation
$image_url = wp_get_attachment_image_url(get_post_thumbnail_id(),
    $settings[\'image_size\']) .\')"></div>\';
';
        file_put_contents($testFile, $content);

        $errors = $this->detector->detect($testFile, '8.0', '6.6');

        // Filter for only UNMATCHED_BRACKETS errors
        $unmatchedBracketErrors = array_filter($errors, function($error) {
            return $error->type === 'UNMATCHED_BRACKETS';
        });

        // Should have NO unmatched bracket errors for valid continuation line patterns
        $this->assertCount(0, $unmatchedBracketErrors,
            'Valid continuation line patterns should not be flagged as unmatched brackets');

        unlink($testFile);
    }

    public function testRealUnmatchedBracketsStillDetected(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_real_unmatched');
        $content = '<?php
class TestClass {
    public function testMethod() {
        // Real syntax error - missing closing parenthesis
        $result = some_function( $arg1, $arg2;
        
        // Real syntax error - extra closing bracket
        $array = array( \'key\' => \'value\' ));
    }
}
';
        file_put_contents($testFile, $content);

        $errors = $this->detector->detect($testFile, '8.1', '6.5');

        // Should detect real syntax errors via php -l
        $this->assertNotEmpty($errors, 'Real syntax errors should still be detected');

        unlink($testFile);
    }
}
