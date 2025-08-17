<?php
namespace NHRROB\WPFatalTester\Detectors;

use NHRROB\WPFatalTester\Models\FatalError;

class ClassConflictDetector implements ErrorDetectorInterface {
    
    private array $declaredClasses = [];
    private array $wordpressClasses = [];
    
    public function __construct() {
        $this->initializeWordPressClasses();
    }
    
    public function getName(): string {
        return 'Class Conflict Detector';
    }

    public function detect(string $filePath, string $phpVersion, string $wpVersion): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return [];
        }

        $errors = [];
        $content = file_get_contents($filePath);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNumber => $line) {
            $lineNumber++; // 1-based line numbers
            
            // Check for class declarations
            if (preg_match('/^\s*class\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $line, $matches)) {
                $className = $matches[1];
                $errors = array_merge($errors, $this->checkClassConflict($className, $filePath, $lineNumber));
            }
            
            // Check for interface declarations
            if (preg_match('/^\s*interface\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $line, $matches)) {
                $interfaceName = $matches[1];
                $errors = array_merge($errors, $this->checkInterfaceConflict($interfaceName, $filePath, $lineNumber));
            }
            
            // Check for trait declarations
            if (preg_match('/^\s*trait\s+([a-zA-Z_][a-zA-Z0-9_]*)/i', $line, $matches)) {
                $traitName = $matches[1];
                $errors = array_merge($errors, $this->checkTraitConflict($traitName, $filePath, $lineNumber));
            }
            
            // Check for undefined class usage
            $classUsages = $this->extractClassUsages($line);
            foreach ($classUsages as $className) {
                if ($this->isUndefinedClass($className)) {
                    $errors[] = new FatalError(
                        type: 'UNDEFINED_CLASS',
                        message: "Class '{$className}' not found",
                        file: $filePath,
                        line: $lineNumber,
                        severity: 'error',
                        suggestion: "Ensure class '{$className}' is defined or properly included",
                        context: ['class' => $className]
                    );
                }
            }
        }
        
        return $errors;
    }

    private function checkClassConflict(string $className, string $filePath, int $lineNumber): array {
        $errors = [];
        
        // Check if class already exists
        if (class_exists($className, false)) {
            $errors[] = new FatalError(
                type: 'CLASS_ALREADY_EXISTS',
                message: "Cannot redeclare class '{$className}'",
                file: $filePath,
                line: $lineNumber,
                severity: 'error',
                suggestion: "Use a different class name or check for duplicate class declarations",
                context: ['class' => $className]
            );
        }
        
        // Check if it conflicts with WordPress core classes
        if (in_array($className, $this->wordpressClasses)) {
            $errors[] = new FatalError(
                type: 'WORDPRESS_CLASS_CONFLICT',
                message: "Class '{$className}' conflicts with WordPress core class",
                file: $filePath,
                line: $lineNumber,
                severity: 'error',
                suggestion: "Use a different class name with a unique prefix to avoid conflicts",
                context: ['class' => $className, 'type' => 'wordpress_core']
            );
        }
        
        // Check if it conflicts with PHP built-in classes
        if ($this->isPHPBuiltinClass($className)) {
            $errors[] = new FatalError(
                type: 'PHP_CLASS_CONFLICT',
                message: "Class '{$className}' conflicts with PHP built-in class",
                file: $filePath,
                line: $lineNumber,
                severity: 'error',
                suggestion: "Use a different class name to avoid conflicts with PHP built-in classes",
                context: ['class' => $className, 'type' => 'php_builtin']
            );
        }
        
        // Track declared classes for future conflict detection
        $this->declaredClasses[] = $className;
        
        return $errors;
    }

    private function checkInterfaceConflict(string $interfaceName, string $filePath, int $lineNumber): array {
        $errors = [];
        
        if (interface_exists($interfaceName, false)) {
            $errors[] = new FatalError(
                type: 'INTERFACE_ALREADY_EXISTS',
                message: "Cannot redeclare interface '{$interfaceName}'",
                file: $filePath,
                line: $lineNumber,
                severity: 'error',
                suggestion: "Use a different interface name or check for duplicate interface declarations",
                context: ['interface' => $interfaceName]
            );
        }
        
        return $errors;
    }

    private function checkTraitConflict(string $traitName, string $filePath, int $lineNumber): array {
        $errors = [];
        
        if (trait_exists($traitName, false)) {
            $errors[] = new FatalError(
                type: 'TRAIT_ALREADY_EXISTS',
                message: "Cannot redeclare trait '{$traitName}'",
                file: $filePath,
                line: $lineNumber,
                severity: 'error',
                suggestion: "Use a different trait name or check for duplicate trait declarations",
                context: ['trait' => $traitName]
            );
        }
        
        return $errors;
    }

    private function extractClassUsages(string $line): array {
        $classes = [];
        
        // Remove comments
        $line = preg_replace('/\/\/.*$/', '', $line);
        $line = preg_replace('/\/\*.*?\*\//', '', $line);
        
        // Match new ClassName()
        if (preg_match_all('/new\s+([a-zA-Z_][a-zA-Z0-9_\\\\]*)\s*\(/', $line, $matches)) {
            $classes = array_merge($classes, $matches[1]);
        }
        
        // Match ClassName::method()
        if (preg_match_all('/([a-zA-Z_][a-zA-Z0-9_\\\\]*)::[a-zA-Z_][a-zA-Z0-9_]*/', $line, $matches)) {
            $classes = array_merge($classes, $matches[1]);
        }
        
        // Match instanceof ClassName
        if (preg_match_all('/instanceof\s+([a-zA-Z_][a-zA-Z0-9_\\\\]*)/', $line, $matches)) {
            $classes = array_merge($classes, $matches[1]);
        }
        
        // Match extends ClassName
        if (preg_match_all('/extends\s+([a-zA-Z_][a-zA-Z0-9_\\\\]*)/', $line, $matches)) {
            $classes = array_merge($classes, $matches[1]);
        }
        
        // Match implements ClassName
        if (preg_match_all('/implements\s+([a-zA-Z_][a-zA-Z0-9_\\\\]*(?:\s*,\s*[a-zA-Z_][a-zA-Z0-9_\\\\]*)*)/', $line, $matches)) {
            foreach ($matches[1] as $implementsList) {
                $interfaces = preg_split('/\s*,\s*/', $implementsList);
                $classes = array_merge($classes, $interfaces);
            }
        }
        
        return array_unique(array_filter($classes));
    }

    private function isUndefinedClass(string $className): bool {
        // Skip built-in PHP classes and common keywords
        if ($this->isPHPBuiltinClass($className) || 
            in_array(strtolower($className), ['self', 'parent', 'static'])) {
            return false;
        }
        
        // Skip WordPress core classes
        if (in_array($className, $this->wordpressClasses)) {
            return false;
        }
        
        // Skip if class exists
        if (class_exists($className, false) || interface_exists($className, false) || trait_exists($className, false)) {
            return false;
        }
        
        // Skip if it's in our declared classes list
        if (in_array($className, $this->declaredClasses)) {
            return false;
        }
        
        return true;
    }

    private function isPHPBuiltinClass(string $className): bool {
        $builtinClasses = [
            'stdClass', 'Exception', 'ErrorException', 'Error', 'ParseError', 'TypeError', 'ArgumentCountError',
            'ArithmeticError', 'DivisionByZeroError', 'CompileError', 'AssertionError',
            'DateTime', 'DateTimeImmutable', 'DateInterval', 'DatePeriod', 'DateTimeZone',
            'PDO', 'PDOStatement', 'PDOException',
            'mysqli', 'mysqli_stmt', 'mysqli_result',
            'DOMDocument', 'DOMElement', 'DOMNode', 'DOMNodeList', 'DOMXPath',
            'SimpleXMLElement', 'XMLReader', 'XMLWriter',
            'SplFileInfo', 'SplFileObject', 'DirectoryIterator', 'RecursiveDirectoryIterator',
            'ArrayIterator', 'ArrayObject', 'SplObjectStorage',
            'ReflectionClass', 'ReflectionMethod', 'ReflectionProperty', 'ReflectionFunction',
            'Closure', 'Generator', 'WeakReference',
        ];
        
        return in_array($className, $builtinClasses) || class_exists($className, false);
    }

    private function initializeWordPressClasses(): void {
        $this->wordpressClasses = [
            'WP_Query', 'WP_Post', 'WP_User', 'WP_Comment', 'WP_Term', 'WP_Taxonomy',
            'WP_Error', 'WP_HTTP_Response', 'WP_REST_Response', 'WP_REST_Request',
            'WP_Widget', 'WP_Customize_Manager', 'WP_Customize_Control', 'WP_Customize_Setting',
            'WP_List_Table', 'WP_Screen', 'WP_Admin_Bar',
            'wpdb', 'WP_Filesystem_Base', 'WP_Upgrader', 'WP_Ajax_Upgrader_Skin',
            'WP_Theme', 'WP_Plugin', 'WP_Locale', 'WP_Roles', 'WP_Role',
            'WP_Session_Tokens', 'WP_User_Meta_Session_Tokens',
            'WP_Rewrite', 'WP_Router', 'WP_Hook',
            'WP_CLI', 'WP_CLI_Command',
        ];
    }
}
