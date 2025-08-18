<?php
namespace NHRROB\WPFatalTester\Exceptions;

class DependencyExceptionManager {
    
    private array $ecosystemExceptions = [];
    private array $globalExceptions = [];
    
    public function __construct() {
        $this->initializeExceptions();
    }
    
    /**
     * Check if a class should be excluded from undefined class errors
     *
     * @param string $className
     * @param array $detectedEcosystems
     * @return bool
     */
    public function isClassExcepted(string $className, array $detectedEcosystems = []): bool {
        // Check global exceptions first
        if (in_array($className, $this->globalExceptions['classes'] ?? [])) {
            return true;
        }
        
        // Check ecosystem-specific exceptions
        foreach ($detectedEcosystems as $ecosystem) {
            $ecosystem = strtolower($ecosystem);
            if (isset($this->ecosystemExceptions[$ecosystem]['classes'])) {
                if (in_array($className, $this->ecosystemExceptions[$ecosystem]['classes'])) {
                    return true;
                }
                
                // Check for pattern matches
                foreach ($this->ecosystemExceptions[$ecosystem]['class_patterns'] ?? [] as $pattern) {
                    // Handle wildcard patterns
                    if (str_ends_with($pattern, '*')) {
                        $prefix = rtrim($pattern, '*');
                        if (str_starts_with($className, $prefix)) {
                            return true;
                        }
                    }
                    // Handle exact matches and substring matches
                    if ($pattern === $className || strpos($className, $pattern) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if a function should be excluded from undefined function errors
     *
     * @param string $functionName
     * @param array $detectedEcosystems
     * @return bool
     */
    public function isFunctionExcepted(string $functionName, array $detectedEcosystems = []): bool {
        // Check global exceptions first
        if (in_array($functionName, $this->globalExceptions['functions'] ?? [])) {
            return true;
        }
        
        // Check ecosystem-specific exceptions
        foreach ($detectedEcosystems as $ecosystem) {
            $ecosystem = strtolower($ecosystem);
            if (isset($this->ecosystemExceptions[$ecosystem]['functions'])) {
                if (in_array($functionName, $this->ecosystemExceptions[$ecosystem]['functions'])) {
                    return true;
                }
                
                // Check for pattern matches
                foreach ($this->ecosystemExceptions[$ecosystem]['function_patterns'] ?? [] as $pattern) {
                    // Handle wildcard patterns
                    if (str_ends_with($pattern, '*')) {
                        $prefix = rtrim($pattern, '*');
                        if (str_starts_with($functionName, $prefix)) {
                            return true;
                        }
                    }
                    // Handle exact matches and substring matches
                    if ($pattern === $functionName || strpos($functionName, $pattern) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get exception reason for a class
     *
     * @param string $className
     * @param array $detectedEcosystems
     * @return string|null
     */
    public function getClassExceptionReason(string $className, array $detectedEcosystems = []): ?string {
        foreach ($detectedEcosystems as $ecosystem) {
            $ecosystem = strtolower($ecosystem);
            if (isset($this->ecosystemExceptions[$ecosystem]['classes']) && 
                in_array($className, $this->ecosystemExceptions[$ecosystem]['classes'])) {
                return "Class '{$className}' is provided by {$ecosystem} plugin dependency";
            }
        }
        
        return null;
    }
    
    /**
     * Add custom exceptions for an ecosystem
     *
     * @param string $ecosystem
     * @param array $exceptions
     */
    public function addEcosystemExceptions(string $ecosystem, array $exceptions): void {
        $ecosystem = strtolower($ecosystem);
        if (!isset($this->ecosystemExceptions[$ecosystem])) {
            $this->ecosystemExceptions[$ecosystem] = [];
        }
        
        $this->ecosystemExceptions[$ecosystem] = array_merge_recursive(
            $this->ecosystemExceptions[$ecosystem],
            $exceptions
        );
    }
    
    /**
     * Get all exceptions for an ecosystem
     *
     * @param string $ecosystem
     * @return array
     */
    public function getEcosystemExceptions(string $ecosystem): array {
        $ecosystem = strtolower($ecosystem);
        return $this->ecosystemExceptions[$ecosystem] ?? [];
    }
    
    private function initializeExceptions(): void {
        $this->initializeElementorExceptions();
        $this->initializeWooCommerceExceptions();
        $this->initializeGlobalExceptions();
    }
    
    private function initializeElementorExceptions(): void {
        $this->ecosystemExceptions['elementor'] = [
            'classes' => [
                // Core Elementor classes
                'Controls_Manager',
                'Widget_Base',
                'Core\\Base\\Module',
                'Core\\Base\\App',
                'Plugin',
                'Elementor\\Plugin',
                'Utils',
                'Icons_Manager',
                'Scheme_Color',
                'Scheme_Typography',
                'TagsModule',
                
                // Group Controls
                'Group_Control_Typography',
                'Group_Control_Text_Shadow',
                'Group_Control_Box_Shadow',
                'Group_Control_Border',
                'Group_Control_Background',
                'Group_Control_Image_Size',
                'Group_Control_Css_Filter',
                
                // Modules and Components
                'Modules\\DynamicTags\\Module',
                'Core\\Kits\\Documents\\Tabs\\Global_Colors',
                'Core\\Kits\\Documents\\Tabs\\Global_Typography',
                'Core\\DocumentTypes\\Page',
                'Core\\DocumentTypes\\Post',
                
                // Widget classes
                'Widget_Heading',
                'Widget_Image',
                'Widget_Text_Editor',
                'Widget_Button',
                'Widget_Divider',
                'Widget_Spacer',
                'Widget_Google_Maps',
                'Widget_Icon',
                'Widget_Icon_List',
                'Widget_Counter',
                'Widget_Progress',
                'Widget_Testimonial',
                'Widget_Tabs',
                'Widget_Accordion',
                'Widget_Toggle',
                'Widget_Social_Icons',
                'Widget_Alert',
                'Widget_Audio',
                'Widget_Shortcode',
                'Widget_Html',
                'Widget_Sidebar',
                'Widget_Menu_Anchor',
                'Widget_Read_More',
            ],
            'class_patterns' => [
                'Elementor\\*',
                'ElementorPro\\*',
                'Group_Control_*',
                'Widget_*',
                'Core\\*',
                'Modules\\*',
            ],
            'functions' => [
                'elementor_pro_load_plugin',
                'elementor_load_plugin_textdomain',
                'elementor_get_post_id',
                'elementor_get_edit_mode',
                'elementor_is_edit_mode',
                'elementor_is_preview_mode',
            ],
            'function_patterns' => [
                'elementor_*',
            ],
        ];
    }
    
    private function initializeWooCommerceExceptions(): void {
        $this->ecosystemExceptions['woocommerce'] = [
            'classes' => [
                // Core WooCommerce classes
                'WooCommerce',
                'WC_Product',
                'WC_Order',
                'WC_Customer',
                'WC_Cart',
                'WC_Checkout',
                'WC_Payment_Gateway',
                'WC_Shipping_Method',
                'WC_Tax',
                'WC_Coupon',
                'WC_Session',
                'WC_Query',
                'WC_Admin',
                'WC_AJAX',
                'WC_API',
                'WC_Auth',
                'WC_Cache_Helper',
                'WC_Comments',
                'WC_Countries',
                'WC_Currency',
                'WC_Data_Store',
                'WC_DateTime',
                'WC_Deprecated_Hooks',
                'WC_Download_Handler',
                'WC_Emails',
                'WC_Form_Handler',
                'WC_Frontend_Scripts',
                'WC_Geolocation',
                'WC_HTTPS',
                'WC_Install',
                'WC_Logger',
                'WC_Order_Factory',
                'WC_Post_Data',
                'WC_Product_Factory',
                'WC_REST_API',
                'WC_Shortcodes',
                'WC_Template_Loader',
                'WC_Tracker',
                'WC_Validation',
                'WC_Webhook',
            ],
            'class_patterns' => [
                'WC_*',
                'WooCommerce\\*',
            ],
            'functions' => [
                'wc_get_product',
                'wc_get_order',
                'wc_get_customer',
                'wc_get_cart',
                'wc_get_checkout',
                'wc_get_page_id',
                'wc_get_template',
                'wc_get_template_part',
                'wc_locate_template',
                'wc_price',
                'wc_format_decimal',
                'wc_clean',
                'wc_sanitize_tooltip',
                'wc_help_tip',
                'wc_add_notice',
                'wc_print_notices',
                'wc_get_notices',
                'wc_clear_notices',
                'is_woocommerce',
                'is_shop',
                'is_product_category',
                'is_product_tag',
                'is_product',
                'is_cart',
                'is_checkout',
                'is_account_page',
                'is_wc_endpoint_url',
            ],
            'function_patterns' => [
                'wc_*',
                'woocommerce_*',
                'is_wc_*',
                'is_woocommerce*',
            ],
        ];
    }
    
    private function initializeGlobalExceptions(): void {
        $this->globalExceptions = [
            'classes' => [
                // Common third-party classes that might not be loaded during testing
                'Composer\\Autoload\\ClassLoader',
                'Psr\\Log\\LoggerInterface',
                'Monolog\\Logger',
                'Twig\\Environment',
                'Symfony\\Component\\HttpFoundation\\Request',
                'Symfony\\Component\\HttpFoundation\\Response',
                'Doctrine\\DBAL\\Connection',
                'GuzzleHttp\\Client',
            ],
            'functions' => [
                // Common functions that might not be available during testing
                'wp_doing_ajax',
                'wp_doing_cron',
                'wp_is_json_request',
                'wp_is_xml_request',
            ],
        ];
    }
}
