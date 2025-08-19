<?php
namespace NHRROB\WPFatalTester\Detectors;

use NHRROB\WPFatalTester\Models\FatalError;
use NHRROB\WPFatalTester\Exceptions\DependencyExceptionManager;

class ClassConflictDetector implements ErrorDetectorInterface {

    private array $declaredClasses = [];
    private array $wordpressClasses = [];
    private DependencyExceptionManager $exceptionManager;
    private array $detectedEcosystems = [];

    public function __construct(?DependencyExceptionManager $exceptionManager = null) {
        $this->exceptionManager = $exceptionManager ?? new DependencyExceptionManager();
        $this->initializeWordPressClasses();
    }

    /**
     * Set detected ecosystems for dependency exception handling
     *
     * @param array $ecosystems
     */
    public function setDetectedEcosystems(array $ecosystems): void {
        $this->detectedEcosystems = $ecosystems;
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
                    $exceptionReason = $this->exceptionManager->getClassExceptionReason($className, $this->detectedEcosystems);

                    if ($exceptionReason) {
                        // This class is excepted, but we can provide a helpful note
                        continue;
                    }

                    $suggestion = "Ensure class '{$className}' is defined or properly included";

                    // Provide ecosystem-specific suggestions
                    if (!empty($this->detectedEcosystems)) {
                        $ecosystemList = implode(', ', $this->detectedEcosystems);
                        $suggestion .= ". If this class is provided by {$ecosystemList}, ensure the parent plugin is installed and active.";
                    }

                    $errors[] = new FatalError(
                        type: 'UNDEFINED_CLASS',
                        message: "Class '{$className}' not found",
                        file: $filePath,
                        line: $lineNumber,
                        severity: 'error',
                        suggestion: $suggestion,
                        context: [
                            'class' => $className,
                            'detected_ecosystems' => $this->detectedEcosystems
                        ]
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

        // Skip lines that are likely HTML/CSS (contain quotes with class attributes)
        if (preg_match('/["\'].*class\s*=.*["\']/', $line) ||
            preg_match('/["\'].*["\']/', $line) && preg_match('/\.(css|html|js)/', $line)) {
            return [];
        }

        // Skip lines that look like CSS selectors or HTML
        if (preg_match('/^\s*[\.\#]/', $line) ||
            preg_match('/<[^>]+>/', $line) ||
            preg_match('/\{[^}]*\}/', $line)) {
            return [];
        }

        // Match new ClassName() - but not in strings
        if (preg_match_all('/(?<!["\'])new\s+([A-Z][a-zA-Z0-9_\\\\]*)\s*\(/', $line, $matches)) {
            $classes = array_merge($classes, $matches[1]);
        }

        // Match ClassName::method() - but not in strings
        if (preg_match_all('/(?<!["\'])([A-Z][a-zA-Z0-9_\\\\]*)::[a-zA-Z_][a-zA-Z0-9_]*/', $line, $matches)) {
            $classes = array_merge($classes, $matches[1]);
        }

        // Match instanceof ClassName
        if (preg_match_all('/instanceof\s+([A-Z][a-zA-Z0-9_\\\\]*)/', $line, $matches)) {
            $classes = array_merge($classes, $matches[1]);
        }

        // Match extends ClassName
        if (preg_match_all('/extends\s+([A-Z][a-zA-Z0-9_\\\\]*)/', $line, $matches)) {
            $classes = array_merge($classes, $matches[1]);
        }

        // Match implements ClassName
        if (preg_match_all('/implements\s+([A-Z][a-zA-Z0-9_\\\\]*(?:\s*,\s*[A-Z][a-zA-Z0-9_\\\\]*)*)/', $line, $matches)) {
            foreach ($matches[1] as $implementsList) {
                $interfaces = preg_split('/\s*,\s*/', $implementsList);
                $classes = array_merge($classes, $interfaces);
            }
        }

        // Filter out obvious non-class names
        $classes = array_filter($classes, function($className) {
            // Skip single lowercase words (likely CSS classes)
            if (ctype_lower($className) && strlen($className) < 10) {
                return false;
            }
            // Skip common HTML/CSS terms
            $cssTerms = ['hover', 'active', 'focus', 'disabled', 'hidden', 'visible', 'block', 'inline', 'flex'];
            if (in_array(strtolower($className), $cssTerms)) {
                return false;
            }
            return true;
        });

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

        // Check dependency exceptions based on detected ecosystems
        if ($this->exceptionManager->isClassExcepted($className, $this->detectedEcosystems)) {
            return false;
        }

        return true;
    }

    private function isPHPBuiltinClass(string $className): bool {
        $builtinClasses = [
            // Core PHP classes
            'stdClass', 'Closure', 'Generator', 'WeakReference',

            // Exception hierarchy
            'Exception', 'ErrorException', 'Error', 'ParseError', 'TypeError', 'ArgumentCountError',
            'ArithmeticError', 'DivisionByZeroError', 'CompileError', 'AssertionError',
            'LogicException', 'BadFunctionCallException', 'BadMethodCallException', 'DomainException',
            'InvalidArgumentException', 'LengthException', 'OutOfRangeException',
            'RuntimeException', 'OutOfBoundsException', 'OverflowException', 'RangeException',
            'UnderflowException', 'UnexpectedValueException', 'Throwable',

            // Date/Time classes
            'DateTime', 'DateTimeImmutable', 'DateInterval', 'DatePeriod', 'DateTimeZone',

            // Database classes
            'PDO', 'PDOStatement', 'PDOException',
            'mysqli', 'mysqli_stmt', 'mysqli_result', 'mysqli_driver', 'mysqli_warning',

            // XML/DOM classes
            'DOMDocument', 'DOMElement', 'DOMNode', 'DOMNodeList', 'DOMXPath', 'DOMAttr',
            'DOMCharacterData', 'DOMComment', 'DOMDocumentFragment', 'DOMDocumentType',
            'DOMEntity', 'DOMEntityReference', 'DOMNotation', 'DOMProcessingInstruction',
            'DOMText', 'DOMNamedNodeMap', 'DOMImplementation', 'DOMException',
            'SimpleXMLElement', 'SimpleXMLIterator', 'XMLReader', 'XMLWriter',
            'XSLTProcessor', 'LibXMLError',

            // SPL classes
            'SplFileInfo', 'SplFileObject', 'SplTempFileObject',
            'DirectoryIterator', 'FilesystemIterator', 'RecursiveDirectoryIterator',
            'GlobIterator', 'RecursiveIteratorIterator', 'RecursiveTreeIterator',
            'ArrayIterator', 'ArrayObject', 'SplObjectStorage', 'SplDoublyLinkedList',
            'SplStack', 'SplQueue', 'SplHeap', 'SplMaxHeap', 'SplMinHeap', 'SplPriorityQueue',
            'SplFixedArray', 'SplObserver', 'SplSubject',
            'AppendIterator', 'CachingIterator', 'CallbackFilterIterator', 'EmptyIterator',
            'FilterIterator', 'InfiniteIterator', 'IteratorIterator', 'LimitIterator',
            'MultipleIterator', 'NoRewindIterator', 'ParentIterator', 'RecursiveCallbackFilterIterator',
            'RecursiveFilterIterator', 'RecursiveRegexIterator', 'RegexIterator',

            // Reflection classes
            'ReflectionClass', 'ReflectionMethod', 'ReflectionProperty', 'ReflectionFunction',
            'ReflectionParameter', 'ReflectionExtension', 'ReflectionObject', 'ReflectionClassConstant',
            'ReflectionFunctionAbstract', 'ReflectionType', 'ReflectionNamedType', 'ReflectionUnionType',
            'ReflectionIntersectionType', 'ReflectionGenerator', 'ReflectionReference',
            'ReflectionZendExtension', 'ReflectionException',

            // Stream and Filter classes
            'php_user_filter', 'streamWrapper',

            // JSON classes
            'JsonSerializable', 'JsonException',

            // Other built-in classes
            'Traversable', 'Iterator', 'IteratorAggregate', 'Throwable', 'Stringable',
            'Countable', 'Serializable', 'ArrayAccess', 'SeekableIterator',
            'OuterIterator', 'RecursiveIterator', 'SplObserver', 'SplSubject',
            '__PHP_Incomplete_Class',
        ];

        return in_array($className, $builtinClasses) || class_exists($className, false);
    }

    private function initializeWordPressClasses(): void {
        $this->wordpressClasses = [
            // Core WordPress Query classes
            'WP_Query', 'WP_User_Query', 'WP_Comment_Query', 'WP_Term_Query', 'WP_Site_Query',
            'WP_Network_Query', 'WP_Meta_Query', 'WP_Date_Query', 'WP_Tax_Query',

            // Core WordPress object classes
            'WP_Post', 'WP_User', 'WP_Comment', 'WP_Term', 'WP_Taxonomy', 'WP_Site', 'WP_Network',

            // Error and HTTP classes
            'WP_Error', 'WP_HTTP', 'WP_HTTP_Response', 'WP_HTTP_Requests_Response',
            'WP_HTTP_Requests_Hooks', 'WP_HTTP_Cookie', 'WP_HTTP_Encoding',

            // REST API classes
            'WP_REST_Server', 'WP_REST_Response', 'WP_REST_Request', 'WP_REST_Controller',
            'WP_REST_Posts_Controller', 'WP_REST_Users_Controller', 'WP_REST_Comments_Controller',
            'WP_REST_Terms_Controller', 'WP_REST_Attachments_Controller',

            // Admin and UI classes
            'WP_Widget', 'WP_Customize_Manager', 'WP_Customize_Control', 'WP_Customize_Setting',
            'WP_Customize_Panel', 'WP_Customize_Section', 'WP_List_Table', 'WP_Screen', 'WP_Admin_Bar',
            'WP_Posts_List_Table', 'WP_Users_List_Table', 'WP_Comments_List_Table',

            // Database and filesystem classes
            'wpdb', 'WP_Filesystem_Base', 'WP_Filesystem_Direct', 'WP_Filesystem_FTPext',
            'WP_Filesystem_SSH2', 'WP_Filesystem_ftpsockets',

            // Upgrade and installation classes
            'WP_Upgrader', 'WP_Ajax_Upgrader_Skin', 'WP_Upgrader_Skin', 'Plugin_Upgrader',
            'Theme_Upgrader', 'Core_Upgrader', 'Language_Pack_Upgrader', 'Automatic_Upgrader_Skin',
            'WP_Automatic_Updater',

            // Theme and plugin classes
            'WP_Theme', 'WP_Plugin', 'WP_Locale', 'WP_Roles', 'WP_Role', 'WP_User_Meta_Session_Tokens',

            // Session and authentication classes
            'WP_Session_Tokens', 'WP_User_Meta_Session_Tokens',

            // Rewrite and routing classes
            'WP_Rewrite', 'WP_Router', 'WP_Hook', 'WP_Dependency',

            // CLI classes (if WP-CLI is available)
            'WP_CLI', 'WP_CLI_Command',

            // Media and image classes
            'WP_Image_Editor', 'WP_Image_Editor_GD', 'WP_Image_Editor_Imagick',

            // Cron and background processing
            'WP_Cron', 'WP_Background_Process',

            // Mail classes
            'WP_Mail', 'PHPMailer\\PHPMailer\\PHPMailer', 'PHPMailer\\PHPMailer\\SMTP',

            // XML-RPC classes
            'WP_XML_RPC_Server', 'IXR_Value', 'IXR_Message', 'IXR_Server', 'IXR_IntrospectionServer',
            'IXR_Request', 'IXR_Client', 'IXR_ClientMulticall', 'IXR_Error', 'IXR_Date', 'IXR_Base64',

            // Feed classes
            'WP_Feed_Cache', 'WP_Feed_Cache_Transient', 'WP_SimplePie_File', 'WP_SimplePie_Sanitize_KSES',

            // Embed classes
            'WP_Embed', 'WP_oEmbed', 'WP_oEmbed_Controller',
        ];
    }
}
