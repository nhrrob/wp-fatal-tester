<?php

namespace NHRROB\WPFatalTester\Tests\Unit;

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\TemplateMethodContextDetector;

class TemplateMethodContextDetectorTest extends TestCase
{
    private TemplateMethodContextDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new TemplateMethodContextDetector();
    }

    public function testDetectsEAProPostListMethods(): void
    {
        // Create a file that matches template patterns
        $tempDir = sys_get_temp_dir() . '/test_templates_' . uniqid();
        mkdir($tempDir);
        $testFile = $tempDir . '/default.php'; // This matches template filename pattern
        file_put_contents($testFile, '<?php
// EA Pro Post_List widget template with problematic methods
<div class="post-meta">
    <?php echo $this->render_post_meta_dates(); ?>
</div>
<div class="modified-date">
    <?php echo $this->get_last_modified_date(); ?>
</div>
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');
        
        // Should detect both EA Pro specific methods
        $this->assertCount(2, $errors);
        
        // Check first error (render_post_meta_dates)
        $this->assertEquals('TEMPLATE_METHOD_CONTEXT_ERROR', $errors[0]->type);
        $this->assertStringContainsString('render_post_meta_dates', $errors[0]->message);
        $this->assertStringContainsString('EA Pro Post_List widget', $errors[0]->message);
        $this->assertStringContainsString('AJAX load more operations', $errors[0]->message);
        $this->assertEquals('error', $errors[0]->severity);
        $this->assertEquals('ea_pro_ajax_context', $errors[0]->context['issue_type']);
        $this->assertEquals('Post_List', $errors[0]->context['widget_type']);

        // Check second error (get_last_modified_date)
        $this->assertEquals('TEMPLATE_METHOD_CONTEXT_ERROR', $errors[1]->type);
        $this->assertStringContainsString('get_last_modified_date', $errors[1]->message);
        $this->assertStringContainsString('EA Pro Post_List widget', $errors[1]->message);
        $this->assertEquals('error', $errors[1]->severity);
        $this->assertEquals('ea_pro_ajax_context', $errors[1]->context['issue_type']);

        // Cleanup
        unlink($testFile);
        rmdir($tempDir);
    }

    public function testDetectsGeneralWidgetMethods(): void
    {
        // Create a file that matches template patterns
        $tempDir = sys_get_temp_dir() . '/test_templates_' . uniqid();
        mkdir($tempDir);
        $testFile = $tempDir . '/advanced.php'; // This matches template filename pattern
        file_put_contents($testFile, '<?php
// General widget template with potentially problematic methods
<div class="widget-content">
    <?php echo $this->render_content(); ?>
    <?php echo $this->get_settings(); ?>
    <?php $this->print_render_attribute_string("wrapper"); ?>
</div>
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');

        // Should detect all three widget methods
        $this->assertCount(3, $errors);

        foreach ($errors as $error) {
            $this->assertEquals('TEMPLATE_METHOD_CONTEXT_ERROR', $error->type);
            $this->assertEquals('warning', $error->severity);
            $this->assertEquals('widget_context', $error->context['issue_type']);
        }

        // Cleanup
        unlink($testFile);
        rmdir($tempDir);
    }

    public function testDetectsProblematicMethodPatterns(): void
    {
        // Create a file that matches template patterns
        $tempDir = sys_get_temp_dir() . '/test_templates_' . uniqid();
        mkdir($tempDir);
        $testFile = $tempDir . '/preset-1.php'; // This matches template filename pattern
        file_put_contents($testFile, '<?php
// Template with methods following problematic patterns
<div class="content">
    <?php echo $this->render_custom_field(); ?>
    <?php echo $this->get_custom_data(); ?>
    <?php $this->display_widget_info(); ?>
</div>
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');

        // Should detect all three pattern-based methods
        $this->assertCount(3, $errors);

        foreach ($errors as $error) {
            $this->assertEquals('TEMPLATE_METHOD_CONTEXT_ERROR', $error->type);
            $this->assertEquals('info', $error->severity);
            $this->assertEquals('potential_context', $error->context['issue_type']);
        }

        // Cleanup
        unlink($testFile);
        rmdir($tempDir);
    }

    public function testIgnoresNonTemplateFiles(): void
    {
        $testFile = tempnam(sys_get_temp_dir(), 'test_non_template');
        file_put_contents($testFile, '<?php
// This is not a template file
class SomeClass {
    public function someMethod() {
        return $this->render_post_meta_dates();
    }
}
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');
        
        // Should not detect any errors since it\'s not a template file
        $this->assertCount(0, $errors);
        
        unlink($testFile);
    }

    public function testDetectsTemplateFilesByPath(): void
    {
        // Create a temporary directory structure
        $tempDir = sys_get_temp_dir() . '/test_templates_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/templates');
        
        $testFile = $tempDir . '/templates/post-list.php';
        file_put_contents($testFile, '<?php
// Template file in templates directory
<div class="post">
    <?php echo $this->render_post_meta_dates(); ?>
</div>
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');
        
        // Should detect the error since it's in a templates directory
        $this->assertCount(1, $errors);
        $this->assertEquals('TEMPLATE_METHOD_CONTEXT_ERROR', $errors[0]->type);
        
        // Cleanup
        unlink($testFile);
        rmdir($tempDir . '/templates');
        rmdir($tempDir);
    }

    public function testIgnoresCommentsAndStrings(): void
    {
        // Create a file that matches template patterns
        $tempDir = sys_get_temp_dir() . '/test_templates_' . uniqid();
        mkdir($tempDir);
        $testFile = $tempDir . '/layout-1.php'; // This matches template filename pattern
        file_put_contents($testFile, '<?php
// Template with comments and strings
<div class="content">
    // This should be ignored: $this->render_post_meta_dates();
    /* This should also be ignored: $this->get_last_modified_date(); */
    echo "This string should be ignored: $this->render_content()";
    <?php echo $this->render_post_meta_dates(); // This should be detected ?>
</div>
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');

        // Should only detect the actual method call, not the ones in comments/strings
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('render_post_meta_dates', $errors[0]->message);

        // Cleanup
        unlink($testFile);
        rmdir($tempDir);
    }

    public function testProvidesSpecificSuggestionsForEAProMethods(): void
    {
        // Create a file that matches template patterns
        $tempDir = sys_get_temp_dir() . '/test_templates_' . uniqid();
        mkdir($tempDir);
        $testFile = $tempDir . '/default.php'; // This matches template filename pattern
        file_put_contents($testFile, '<?php
// Template for testing EA Pro specific suggestions
<div class="dates">
    <?php echo $this->render_post_meta_dates(); ?>
    <?php echo $this->get_last_modified_date(); ?>
</div>
');

        $errors = $this->detector->detect($testFile, '8.1', '6.5');

        $this->assertCount(2, $errors);

        // Check render_post_meta_dates suggestion
        $this->assertStringContainsString('Replace with direct date rendering logic', $errors[0]->suggestion);
        $this->assertStringContainsString('AJAX load more operations', $errors[0]->suggestion);

        // Check get_last_modified_date suggestion
        $this->assertStringContainsString('get_the_modified_date()', $errors[1]->suggestion);
        $this->assertStringContainsString('WordPress function directly', $errors[1]->suggestion);

        // Cleanup
        unlink($testFile);
        rmdir($tempDir);
    }

    public function testDetectorName(): void
    {
        $this->assertEquals('Template Method Context Detector', $this->detector->getName());
    }
}
