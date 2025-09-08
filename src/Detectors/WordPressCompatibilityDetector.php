<?php
namespace NHRROB\WPFatalTester\Detectors;

use NHRROB\WPFatalTester\Models\FatalError;

class WordPressCompatibilityDetector implements ErrorDetectorInterface {
    
    private array $deprecatedFunctions = [];
    private array $removedFunctions = [];
    private array $versionRequirements = [];
    private ?string $pluginRoot = null;

    public function __construct() {
        $this->initializeDeprecatedFunctions();
        $this->initializeRemovedFunctions();
        $this->initializeVersionRequirements();
    }

    public function getName(): string {
        return 'WordPress Compatibility Detector';
    }

    /**
     * Set the plugin root path for relative path calculation
     *
     * @param string $pluginRoot Absolute path to the plugin root directory
     * @return void
     */
    public function setPluginRoot(string $pluginRoot): void {
        $this->pluginRoot = $pluginRoot;
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
            
            // Check for deprecated functions
            $errors = array_merge($errors, $this->checkDeprecatedFunctions($line, $filePath, $lineNumber, $wpVersion));
            
            // Check for removed functions
            $errors = array_merge($errors, $this->checkRemovedFunctions($line, $filePath, $lineNumber, $wpVersion));
            
            // Check for version-specific requirements
            $errors = array_merge($errors, $this->checkVersionRequirements($line, $filePath, $lineNumber, $wpVersion));
            
            // Check for WordPress hooks and filters
            $errors = array_merge($errors, $this->checkHooksAndFilters($line, $filePath, $lineNumber, $wpVersion));
        }
        
        return $errors;
    }

    private function checkDeprecatedFunctions(string $line, string $filePath, int $lineNumber, string $wpVersion): array {
        $errors = [];

        // Remove single-line comments first (but preserve the line structure)
        $lineWithoutComments = preg_replace('/\/\/.*$/', '', $line);
        $lineWithoutComments = preg_replace('/\/\*.*?\*\//', '', $lineWithoutComments);

        // Skip if line becomes empty after comment removal
        if (trim($lineWithoutComments) === '') {
            return [];
        }

        foreach ($this->deprecatedFunctions as $function => $info) {
            // Check if the function is called in this line
            if (preg_match('/\b' . preg_quote($function, '/') . '\s*\(/', $lineWithoutComments)) {
                // Skip method calls (contains -> or ::) - these are not deprecated WordPress functions
                // but rather method calls on objects/classes (e.g., $document->get_settings() from Elementor)
                if (strpos($lineWithoutComments, '->') !== false || strpos($lineWithoutComments, '::') !== false) {
                    continue;
                }

                // Skip function definitions (contains 'function' keyword before the function name)
                if (preg_match('/\bfunction\s+' . preg_quote($function, '/') . '\s*\(/', $lineWithoutComments)) {
                    continue;
                }

                $deprecatedVersion = $info['deprecated'];
                $replacement = $info['replacement'] ?? null;

                if (version_compare($wpVersion, $deprecatedVersion, '>=')) {
                    $message = "Function '{$function}' is deprecated since WordPress {$deprecatedVersion}";
                    $suggestion = $replacement ? "Use '{$replacement}' instead" : "Find an alternative implementation";

                    $errors[] = new FatalError(
                        type: 'DEPRECATED_FUNCTION',
                        message: $message,
                        file: $filePath,
                        line: $lineNumber,
                        severity: 'warning',
                        suggestion: $suggestion,
                        context: [
                            'function' => $function,
                            'deprecated_version' => $deprecatedVersion,
                            'replacement' => $replacement,
                            'wp_version' => $wpVersion
                        ],
                        pluginRoot: $this->pluginRoot
                    );
                }
            }
        }

        return $errors;
    }

    private function checkRemovedFunctions(string $line, string $filePath, int $lineNumber, string $wpVersion): array {
        $errors = [];

        // Remove single-line comments first (but preserve the line structure)
        $lineWithoutComments = preg_replace('/\/\/.*$/', '', $line);
        $lineWithoutComments = preg_replace('/\/\*.*?\*\//', '', $lineWithoutComments);

        // Skip if line becomes empty after comment removal
        if (trim($lineWithoutComments) === '') {
            return [];
        }

        foreach ($this->removedFunctions as $function => $info) {
            // Check if the function is called in this line
            if (preg_match('/\b' . preg_quote($function, '/') . '\s*\(/', $lineWithoutComments)) {
                // Skip method calls (contains -> or ::) - these are not removed WordPress functions
                // but rather method calls on objects/classes
                if (strpos($lineWithoutComments, '->') !== false || strpos($lineWithoutComments, '::') !== false) {
                    continue;
                }

                // Skip function definitions (contains 'function' keyword before the function name)
                if (preg_match('/\bfunction\s+' . preg_quote($function, '/') . '\s*\(/', $lineWithoutComments)) {
                    continue;
                }

                $removedVersion = $info['removed'];
                $replacement = $info['replacement'] ?? null;

                if (version_compare($wpVersion, $removedVersion, '>=')) {
                    $message = "Function '{$function}' was removed in WordPress {$removedVersion}";
                    $suggestion = $replacement ? "Use '{$replacement}' instead" : "This function is no longer available";

                    $errors[] = new FatalError(
                        type: 'REMOVED_FUNCTION',
                        message: $message,
                        file: $filePath,
                        line: $lineNumber,
                        severity: 'error',
                        suggestion: $suggestion,
                        context: [
                            'function' => $function,
                            'removed_version' => $removedVersion,
                            'replacement' => $replacement,
                            'wp_version' => $wpVersion
                        ],
                        pluginRoot: $this->pluginRoot
                    );
                }
            }
        }
        
        return $errors;
    }

    private function checkVersionRequirements(string $line, string $filePath, int $lineNumber, string $wpVersion): array {
        $errors = [];

        // Remove single-line comments first (but preserve the line structure)
        $lineWithoutComments = preg_replace('/\/\/.*$/', '', $line);
        $lineWithoutComments = preg_replace('/\/\*.*?\*\//', '', $lineWithoutComments);

        // Skip if line becomes empty after comment removal
        if (trim($lineWithoutComments) === '') {
            return [];
        }

        foreach ($this->versionRequirements as $function => $requiredVersion) {
            // Check if the function is called in this line
            if (preg_match('/\b' . preg_quote($function, '/') . '\s*\(/', $lineWithoutComments)) {
                // Skip method calls (contains -> or ::) - these are not WordPress functions
                // but rather method calls on objects/classes
                if (strpos($lineWithoutComments, '->') !== false || strpos($lineWithoutComments, '::') !== false) {
                    continue;
                }

                // Skip function definitions (contains 'function' keyword before the function name)
                if (preg_match('/\bfunction\s+' . preg_quote($function, '/') . '\s*\(/', $lineWithoutComments)) {
                    continue;
                }

                if (version_compare($wpVersion, $requiredVersion, '<')) {
                    $errors[] = new FatalError(
                        type: 'VERSION_REQUIREMENT',
                        message: "Function '{$function}' requires WordPress {$requiredVersion} or higher",
                        file: $filePath,
                        line: $lineNumber,
                        severity: 'error',
                        suggestion: "Upgrade WordPress to version {$requiredVersion} or higher, or use an alternative",
                        context: [
                            'function' => $function,
                            'required_version' => $requiredVersion,
                            'current_version' => $wpVersion
                        ],
                        pluginRoot: $this->pluginRoot
                    );
                }
            }
        }

        return $errors;
    }

    private function checkHooksAndFilters(string $line, string $filePath, int $lineNumber, string $wpVersion): array {
        $errors = [];

        // Check for deprecated hooks - but be careful to distinguish between deprecated functions
        // and valid action/filter hooks with similar names
        $deprecatedHooks = [
            // Note: wp_head and wp_footer are valid action hooks, not deprecated
            // The deprecated items were the wp_head() and wp_footer() FUNCTIONS, not the hooks
            // We should only flag actual deprecated hooks here, not valid action hooks
        ];

        foreach ($deprecatedHooks as $hook => $info) {
            // Only flag if it's actually used as a hook name in add_action/add_filter/do_action/apply_filters
            // and not just mentioned in a string
            if (preg_match('/(?:add_action|add_filter|do_action|apply_filters)\s*\(\s*["\']' . preg_quote($hook, '/') . '["\']/', $line)) {
                if (version_compare($wpVersion, $info['deprecated'], '>=')) {
                    $errors[] = new FatalError(
                        type: 'DEPRECATED_HOOK',
                        message: "Hook '{$hook}' is deprecated since WordPress {$info['deprecated']}",
                        file: $filePath,
                        line: $lineNumber,
                        severity: 'warning',
                        suggestion: $info['replacement'] ? "Use {$info['replacement']} instead" : "Find an alternative hook",
                        context: [
                            'hook' => $hook,
                            'deprecated_version' => $info['deprecated'],
                            'replacement' => $info['replacement'] ?? null
                        ]
                    );
                }
            }
        }

        return $errors;
    }

    private function initializeDeprecatedFunctions(): void {
        $this->deprecatedFunctions = [
            'get_bloginfo_rss' => ['deprecated' => '2.2.0', 'replacement' => 'get_bloginfo'],
            'wp_get_links' => ['deprecated' => '2.1.0', 'replacement' => 'get_bookmarks'],
            'get_links' => ['deprecated' => '2.1.0', 'replacement' => 'get_bookmarks'],
            'get_links_list' => ['deprecated' => '2.1.0', 'replacement' => 'wp_list_bookmarks'],
            'links_popup_script' => ['deprecated' => '2.1.0', 'replacement' => null],
            'get_linkobjectsbyname' => ['deprecated' => '2.1.0', 'replacement' => 'get_bookmark_by_name'],
            'get_linkobjects' => ['deprecated' => '2.1.0', 'replacement' => 'get_bookmarks'],
            'get_linksbyname' => ['deprecated' => '2.1.0', 'replacement' => 'get_bookmarks'],
            'wp_get_linksbyname' => ['deprecated' => '2.1.0', 'replacement' => 'get_bookmarks'],
            'get_autotoggle' => ['deprecated' => '2.1.0', 'replacement' => null],
            'list_cats' => ['deprecated' => '2.1.0', 'replacement' => 'wp_list_categories'],
            'wp_list_cats' => ['deprecated' => '2.1.0', 'replacement' => 'wp_list_categories'],
            'dropdown_cats' => ['deprecated' => '2.1.0', 'replacement' => 'wp_dropdown_categories'],
            'list_authors' => ['deprecated' => '2.1.0', 'replacement' => 'wp_list_authors'],
            'wp_get_post_cats' => ['deprecated' => '2.1.0', 'replacement' => 'wp_get_post_categories'],
            'wp_set_post_cats' => ['deprecated' => '2.1.0', 'replacement' => 'wp_set_post_categories'],
            'get_archives' => ['deprecated' => '2.1.0', 'replacement' => 'wp_get_archives'],
            'get_author_link' => ['deprecated' => '2.1.0', 'replacement' => 'get_author_posts_url'],
            'link_pages' => ['deprecated' => '2.1.0', 'replacement' => 'wp_link_pages'],
            'get_settings' => ['deprecated' => '2.1.0', 'replacement' => 'get_option'],
            'permalink_link' => ['deprecated' => '1.2.0', 'replacement' => 'the_permalink'],
            'permalink_single_rss' => ['deprecated' => '2.3.0', 'replacement' => 'the_permalink_rss'],
            'wp_get_links' => ['deprecated' => '2.1.0', 'replacement' => 'get_bookmarks'],
            'get_link' => ['deprecated' => '2.1.0', 'replacement' => 'get_bookmark'],
            'edit_link' => ['deprecated' => '2.1.0', 'replacement' => 'get_edit_bookmark_link'],
            'get_linkrating' => ['deprecated' => '2.1.0', 'replacement' => null],
            'get_linkcatname' => ['deprecated' => '2.1.0', 'replacement' => 'get_category'],
        ];
    }

    private function initializeRemovedFunctions(): void {
        $this->removedFunctions = [
            'mysql2date' => ['removed' => '3.9.0', 'replacement' => 'mysql_to_rfc3339'],
            'get_profile' => ['removed' => '2.5.0', 'replacement' => 'get_the_author_meta'],
            'get_usernumposts' => ['removed' => '3.0.0', 'replacement' => 'count_user_posts'],
            'funky_javascript_callback' => ['removed' => '3.0.0', 'replacement' => null],
            'funky_javascript_fix' => ['removed' => '3.0.0', 'replacement' => null],
            'is_taxonomy' => ['removed' => '3.0.0', 'replacement' => 'taxonomy_exists'],
            'is_term' => ['removed' => '3.0.0', 'replacement' => 'term_exists'],
            // Note: sanitize_url() was restored in WordPress 5.9.0 and is no longer deprecated
            'clean_url' => ['removed' => '3.0.0', 'replacement' => 'esc_url'],
            'js_escape' => ['removed' => '3.0.0', 'replacement' => 'esc_js'],
            'wp_specialchars' => ['removed' => '2.8.0', 'replacement' => 'esc_html'],
            'attribute_escape' => ['removed' => '2.8.0', 'replacement' => 'esc_attr'],
        ];
    }

    private function initializeVersionRequirements(): void {
        $this->versionRequirements = [
            'wp_enqueue_block_editor_assets' => '5.0.0',
            'wp_set_script_translations' => '5.0.0',
            'wp_get_environment_type' => '5.5.0',
            'wp_is_application_passwords_available' => '5.6.0',
            'wp_get_duotone_filter_id' => '5.9.0',
            'wp_get_global_settings' => '5.9.0',
            'wp_get_global_styles' => '5.9.0',
            'wp_theme_has_theme_json' => '5.8.0',
            'wp_get_theme_data_custom_templates' => '5.9.0',
            'wp_get_theme_data_template_parts' => '5.9.0',
            'block_core_navigation_render_submenu_icon' => '5.9.0',
            'wp_interactivity_config' => '6.5.0',
            'wp_interactivity_state' => '6.5.0',
            'wp_interactivity_data_wp_context' => '6.5.0',
        ];
    }
}
