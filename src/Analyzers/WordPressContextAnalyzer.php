<?php
namespace NHRROB\WPFatalTester\Analyzers;

/**
 * Analyzes WordPress context to determine if code runs in admin, frontend, or ambiguous contexts
 */
class WordPressContextAnalyzer {
    
    private array $adminHooks = [
        'admin_init', 'admin_menu', 'admin_head', 'admin_footer', 'admin_enqueue_scripts',
        'admin_notices', 'admin_bar_menu', 'admin_post_', 'wp_ajax_', 'wp_ajax_nopriv_',
        'load-', 'admin_action_', 'wp_dashboard_setup', 'admin_page_', 'edit_form_',
        'save_post', 'delete_post', 'wp_insert_post', 'pre_get_posts',
        'manage_posts_columns', 'manage_pages_columns', 'manage_users_columns',
        'bulk_actions-', 'handle_bulk_actions-', 'admin_print_styles', 'admin_print_scripts',
        // Elementor editor hooks (run in admin context)
        'elementor/editor/footer', 'elementor/editor/before_enqueue_scripts', 'elementor/editor/after_enqueue_scripts',
        'elementor/editor/wp_head', 'elementor/editor/before_enqueue_styles', 'elementor/editor/after_enqueue_styles',
        'elementor/preview/enqueue_styles', 'elementor/frontend/after_enqueue_styles'
    ];
    
    private array $frontendHooks = [
        'wp_head', 'wp_footer', 'wp_enqueue_scripts', 'template_redirect',
        'init', 'wp_loaded', 'parse_request', 'send_headers', 'wp',
        'template_include', 'get_header', 'get_footer', 'get_sidebar',
        'wp_print_styles', 'wp_print_scripts', 'wp_meta', 'rss_head',
        'atom_head', 'rdf_head', 'rss2_head', 'commentsrss2_head'
    ];
    
    private array $adminFunctions = [
        'is_admin', 'current_user_can', 'wp_verify_nonce', 'check_admin_referer',
        'wp_die', 'wp_redirect', 'add_menu_page', 'add_submenu_page',
        'add_options_page', 'add_management_page', 'add_theme_page',
        'remove_menu_page', 'remove_submenu_page'
    ];
    
    /**
     * Analyze the context of a function call within a file
     */
    public function analyzeContext(string $filePath, int $lineNumber, string $functionName): string {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if (!$lines) {
            return 'unknown';
        }
        
        // Get surrounding context (10 lines before and after)
        $startLine = max(0, $lineNumber - 11);
        $endLine = min(count($lines) - 1, $lineNumber + 9);
        $contextLines = array_slice($lines, $startLine, $endLine - $startLine + 1);
        $context = implode("\n", $contextLines);
        
        // Check for explicit admin context indicators
        if ($this->isInAdminContext($context, $lines, $lineNumber)) {
            return 'admin';
        }
        
        // Check for explicit frontend context indicators
        if ($this->isInFrontendContext($context, $lines, $lineNumber)) {
            return 'frontend';
        }
        
        // Check for conditional loading patterns
        if ($this->hasConditionalLoading($context, $functionName)) {
            return 'conditional';
        }
        
        return 'ambiguous';
    }
    
    /**
     * Check if the function call is in an admin context
     */
    private function isInAdminContext(string $context, array $allLines, int $lineNumber): bool {
        // Check for admin hooks
        foreach ($this->adminHooks as $hook) {
            if (preg_match('/add_action\s*\(\s*["\']' . preg_quote($hook, '/') . '/', $context) ||
                preg_match('/add_filter\s*\(\s*["\']' . preg_quote($hook, '/') . '/', $context)) {
                return true;
            }
        }
        
        // Check for is_admin() conditional
        if (preg_match('/if\s*\(\s*is_admin\s*\(\s*\)\s*\)/', $context)) {
            return true;
        }
        
        // Check for admin-only file patterns
        if (preg_match('/wp-admin\//', $context) || 
            preg_match('/admin\.php/', $context) ||
            preg_match('/admin_/', $context)) {
            return true;
        }
        
        // Check if we're inside an admin callback function
        if ($this->isInsideAdminCallback($allLines, $lineNumber)) {
            return true;
        }

        // Check for Elementor editor context patterns
        if ($this->isInElementorEditorContext($context, $allLines, $lineNumber)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the function call is in Elementor editor context
     */
    private function isInElementorEditorContext(string $context, array $allLines, int $lineNumber): bool {
        // Check for Elementor editor-specific method names
        if (preg_match('/print_template_views|templately_promo|elementor.*editor/i', $context)) {
            return true;
        }

        // Check for Elementor editor file patterns
        if (preg_match('/elementor.*editor|editor.*elementor/i', $context)) {
            return true;
        }

        // Look for method names that suggest editor context (search wider range)
        for ($i = max(0, $lineNumber - 50); $i < min(count($allLines), $lineNumber + 10); $i++) {
            $line = $allLines[$i];
            if (preg_match('/(public|private|protected)?\s*function\s+(print_template_views|templately_promo.*|.*editor.*|.*setup.*wizard.*|data_plugins_content|eael_quick_setup_data)/i', $line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the function call is in a frontend context
     */
    private function isInFrontendContext(string $context, array $allLines, int $lineNumber): bool {
        // Check for frontend hooks
        foreach ($this->frontendHooks as $hook) {
            if (preg_match('/add_action\s*\(\s*["\']' . preg_quote($hook, '/') . '/', $context) ||
                preg_match('/add_filter\s*\(\s*["\']' . preg_quote($hook, '/') . '/', $context)) {
                return true;
            }
        }
        
        // Check for !is_admin() conditional
        if (preg_match('/if\s*\(\s*!\s*is_admin\s*\(\s*\)\s*\)/', $context)) {
            return true;
        }
        
        // Check for template file patterns
        if (preg_match('/template/', $context) || 
            preg_match('/theme/', $context) ||
            preg_match('/frontend/', $context)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if the function has conditional loading (function_exists, etc.)
     */
    private function hasConditionalLoading(string $context, string $functionName): bool {
        // Check for function_exists() check
        if (preg_match('/function_exists\s*\(\s*["\']' . preg_quote($functionName, '/') . '["\']/', $context)) {
            return true;
        }
        
        // Check for is_callable() check
        if (preg_match('/is_callable\s*\(\s*["\']' . preg_quote($functionName, '/') . '["\']/', $context)) {
            return true;
        }
        
        // Check for method_exists() for class methods
        if (preg_match('/method_exists\s*\(/', $context)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if we're inside a callback function that's registered for admin hooks
     */
    private function isInsideAdminCallback(array $lines, int $currentLine): bool {
        // Look backwards to find function/method definition
        for ($i = $currentLine - 1; $i >= 0; $i--) {
            $line = $lines[$i];

            // Found function or method definition
            if (preg_match('/(public|private|protected)?\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $line, $matches)) {
                $functionName = $matches[2];

                // Look for this function being registered to admin hooks in all files
                // This is a simplified approach - in a real implementation, you might want to
                // scan related files or use a more sophisticated approach
                for ($j = 0; $j < count($lines); $j++) {
                    $hookLine = $lines[$j];
                    foreach ($this->adminHooks as $hook) {
                        // Check for various callback patterns:
                        // 1. String callback: 'function_name'
                        // 2. Array callback: [$this, 'method_name'] or array($this, 'method_name')
                        // 3. Static callback: ['ClassName', 'method_name']
                        if (preg_match('/add_action\s*\(\s*["\']' . preg_quote($hook, '/') . '[^"\']*["\'],\s*["\']?' . preg_quote($functionName, '/') . '["\']?/', $hookLine) ||
                            preg_match('/add_action\s*\(\s*["\']' . preg_quote($hook, '/') . '[^"\']*["\'],\s*\[\s*\$this\s*,\s*["\']' . preg_quote($functionName, '/') . '["\']\s*\]/', $hookLine) ||
                            preg_match('/add_action\s*\(\s*["\']' . preg_quote($hook, '/') . '[^"\']*["\'],\s*array\s*\(\s*\$this\s*,\s*["\']' . preg_quote($functionName, '/') . '["\']\s*\)/', $hookLine)) {
                            return true;
                        }
                    }
                }
                break;
            }

            // Stop if we hit another function or class
            if (preg_match('/^(class|interface|trait)\s+/', $line)) {
                break;
            }
        }

        return false;
    }
    
    /**
     * Get severity recommendation based on context
     */
    public function getSeverityForContext(string $context, string $functionName): string {
        switch ($context) {
            case 'admin':
                // Admin functions in admin context should be warnings, not errors
                return 'warning';
            case 'frontend':
                // Admin functions in frontend context are definitely errors
                return 'error';
            case 'conditional':
                // Properly checked functions should be info/warnings
                return 'warning';
            case 'ambiguous':
            default:
                // Ambiguous context for WordPress admin functions should be errors
                // because they require wp-admin/includes/plugin.php to be loaded
                // and will cause fatal errors if called without proper includes
                return 'error';
        }
    }
    
    /**
     * Get context-aware suggestion for the function
     */
    public function getContextAwareSuggestion(string $context, string $functionName): string {
        switch ($context) {
            case 'admin':
                return "Function '{$functionName}' is used in admin context. Consider adding explicit admin checks or including wp-admin/includes/plugin.php if needed.";
            case 'frontend':
                return "Function '{$functionName}' should not be used in frontend context. Include wp-admin/includes/plugin.php or use admin hooks instead.";
            case 'conditional':
                return "Function '{$functionName}' is properly checked with conditional loading. This is good practice.";
            case 'ambiguous':
            default:
                return "Function '{$functionName}' requires wp-admin/includes/plugin.php to be loaded. Add explicit admin context checks, include the required file, or use function_exists() validation.";
        }
    }
}
