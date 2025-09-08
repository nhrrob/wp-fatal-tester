<?php

use PHPUnit\Framework\TestCase;
use NHRROB\WPFatalTester\Detectors\ClassConflictDetector;

class ClassConflictDetectorTest extends TestCase {
    
    private ClassConflictDetector $detector;
    private string $tempDir;
    
    protected function setUp(): void {
        $this->detector = new ClassConflictDetector();
        $this->tempDir = sys_get_temp_dir() . '/wp-fatal-tester-test-' . uniqid();
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
    
    private function createTestFile(string $filename, string $content): string {
        $filePath = $this->tempDir . '/' . $filename;
        file_put_contents($filePath, $content);
        return $filePath;
    }
    
    public function testWordPressQueryClassesNotFlaggedAsUndefined(): void {
        $content = '<?php
        $query = new WP_Query($args);
        $user_query = new WP_User_Query($user_args);
        $comment_query = new WP_Comment_Query($comment_args);
        $term_query = new WP_Term_Query($term_args);
        $site_query = new WP_Site_Query($site_args);
        $meta_query = new WP_Meta_Query($meta_args);
        $date_query = new WP_Date_Query($date_args);
        $tax_query = new WP_Tax_Query($tax_args);
        ';
        
        $filePath = $this->createTestFile('wp_query_test.php', $content);
        $errors = $this->detector->detect($filePath, '8.0', '6.0');
        
        // Filter for undefined class errors only
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });
        
        $this->assertEmpty($undefinedClassErrors, 'WordPress Query classes should not be flagged as undefined');
    }
    
    public function testWooCommerceQueryClassNotFlaggedAsUndefined(): void {
        $content = '<?php
        $wc_query = new WC_Query();
        $wc_product = new WC_Product();
        $wc_order = new WC_Order();
        ';
        
        $filePath = $this->createTestFile('wc_query_test.php', $content);
        
        // Set WooCommerce ecosystem as detected
        $this->detector->setDetectedEcosystems(['woocommerce']);
        
        $errors = $this->detector->detect($filePath, '8.0', '6.0');
        
        // Filter for undefined class errors only
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });
        
        $this->assertEmpty($undefinedClassErrors, 'WooCommerce Query classes should not be flagged as undefined when WooCommerce ecosystem is detected');
    }
    
    public function testPHPExceptionClassesNotFlaggedAsUndefined(): void {
        $content = '<?php
        try {
            // Some code that might throw exceptions
        } catch (Exception $e) {
            // Handle generic exception
        } catch (InvalidArgumentException $e) {
            // Handle invalid argument
        } catch (RuntimeException $e) {
            // Handle runtime exception
        } catch (LogicException $e) {
            // Handle logic exception
        } catch (BadMethodCallException $e) {
            // Handle bad method call
        } catch (OutOfBoundsException $e) {
            // Handle out of bounds
        } catch (UnexpectedValueException $e) {
            // Handle unexpected value
        } catch (DomainException $e) {
            // Handle domain exception
        } catch (LengthException $e) {
            // Handle length exception
        } catch (OutOfRangeException $e) {
            // Handle out of range
        } catch (OverflowException $e) {
            // Handle overflow
        } catch (RangeException $e) {
            // Handle range exception
        } catch (UnderflowException $e) {
            // Handle underflow
        } catch (ErrorException $e) {
            // Handle error exception
        } catch (ParseError $e) {
            // Handle parse error
        } catch (TypeError $e) {
            // Handle type error
        } catch (ArgumentCountError $e) {
            // Handle argument count error
        } catch (ArithmeticError $e) {
            // Handle arithmetic error
        } catch (DivisionByZeroError $e) {
            // Handle division by zero
        } catch (AssertionError $e) {
            // Handle assertion error
        }
        ';
        
        $filePath = $this->createTestFile('php_exceptions_test.php', $content);
        $errors = $this->detector->detect($filePath, '8.0', '6.0');
        
        // Filter for undefined class errors only
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });
        
        $this->assertEmpty($undefinedClassErrors, 'PHP built-in exception classes should not be flagged as undefined');
    }
    
    public function testPHPBuiltinClassesNotFlaggedAsUndefined(): void {
        $content = '<?php
        $date = new DateTime();
        $immutable = new DateTimeImmutable();
        $interval = new DateInterval("P1D");
        $period = new DatePeriod($date, $interval, 5);
        $timezone = new DateTimeZone("UTC");
        
        $pdo = new PDO($dsn, $user, $pass);
        $dom = new DOMDocument();
        $xml = new SimpleXMLElement($xmlString);
        
        $file = new SplFileInfo($path);
        $iterator = new ArrayIterator($array);
        $object = new ArrayObject($array);
        
        $reflection = new ReflectionClass($className);
        $method = new ReflectionMethod($className, $methodName);
        ';
        
        $filePath = $this->createTestFile('php_builtin_test.php', $content);
        $errors = $this->detector->detect($filePath, '8.0', '6.0');
        
        // Filter for undefined class errors only
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });
        
        $this->assertEmpty($undefinedClassErrors, 'PHP built-in classes should not be flagged as undefined');
    }
    
    public function testClassExistsConditionalNotFlaggedAsUndefined(): void {
        $content = '<?php
        // This should NOT be flagged as undefined - it\'s properly guarded with class_exists()
        if ( class_exists( \'\Essential_Addons_Elementor\Pro\Classes\Bootstrap\' ) ) {
            \Essential_Addons_Elementor\Pro\Classes\Bootstrap::instance();
        }

        // This should also NOT be flagged - different syntax variations
        if (class_exists("SomeOtherClass")) {
            new SomeOtherClass();
        }

        if (class_exists(\'AnotherClass\')) {
            AnotherClass::staticMethod();
        }
        ';

        $filePath = $this->createTestFile('class_exists_test.php', $content);
        $errors = $this->detector->detect($filePath, '8.0', '6.0');

        // Filter for undefined class errors only
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });

        $this->assertEmpty($undefinedClassErrors, 'Classes used within class_exists() conditionals should not be flagged as undefined');
    }

    public function testUnguardedClassUsageStillDetected(): void {
        $content = '<?php
        // This SHOULD be flagged as undefined - not guarded by class_exists()
        $unguarded = new UnguardedUndefinedClass();
        UnguardedStaticClass::method();

        // This should NOT be flagged - properly guarded
        if (class_exists("GuardedClass")) {
            new GuardedClass();
        }
        ';

        $filePath = $this->createTestFile('mixed_usage_test.php', $content);
        $errors = $this->detector->detect($filePath, '8.0', '6.0');

        // Filter for undefined class errors only
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });

        $this->assertCount(2, $undefinedClassErrors, 'Unguarded undefined classes should still be detected');

        $errorMessages = array_map(function($error) {
            return $error->message;
        }, $undefinedClassErrors);

        $this->assertContains("Class 'UnguardedUndefinedClass' not found", $errorMessages);
        $this->assertContains("Class 'UnguardedStaticClass' not found", $errorMessages);
    }

    public function testInterfaceExistsAndTraitExistsGuards(): void {
        $content = '<?php
        // These should NOT be flagged - guarded by interface_exists() and trait_exists()
        if (interface_exists("SomeInterface")) {
            class MyClass implements SomeInterface {}
        }

        if (trait_exists("SomeTrait")) {
            class AnotherClass {
                use SomeTrait;
            }
        }
        ';

        $filePath = $this->createTestFile('interface_trait_guards_test.php', $content);
        $errors = $this->detector->detect($filePath, '8.0', '6.0');

        // Filter for undefined class errors only
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });

        $this->assertEmpty($undefinedClassErrors, 'Classes/interfaces/traits guarded by interface_exists() and trait_exists() should not be flagged');
    }

    public function testUndefinedClassStillDetected(): void {
        $content = '<?php
        $custom = new SomeUndefinedCustomClass();
        $another = new AnotherNonExistentClass();
        ';

        $filePath = $this->createTestFile('undefined_class_test.php', $content);
        $errors = $this->detector->detect($filePath, '8.0', '6.0');

        // Filter for undefined class errors only
        $undefinedClassErrors = array_filter($errors, function($error) {
            return $error->type === 'UNDEFINED_CLASS';
        });

        $this->assertCount(2, $undefinedClassErrors, 'Truly undefined classes should still be detected');

        $errorMessages = array_map(function($error) {
            return $error->message;
        }, $undefinedClassErrors);

        // Check if the error messages contain the class names
        $hasUndefinedCustomClass = false;
        $hasAnotherNonExistentClass = false;

        foreach ($errorMessages as $message) {
            if (strpos($message, 'SomeUndefinedCustomClass') !== false) {
                $hasUndefinedCustomClass = true;
            }
            if (strpos($message, 'AnotherNonExistentClass') !== false) {
                $hasAnotherNonExistentClass = true;
            }
        }

        $this->assertTrue($hasUndefinedCustomClass, 'Should detect SomeUndefinedCustomClass as undefined');
        $this->assertTrue($hasAnotherNonExistentClass, 'Should detect AnotherNonExistentClass as undefined');
    }
}
