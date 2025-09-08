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
     * Check if a widget method error should be excluded based on widget configuration
     *
     * @param string $widgetType
     * @param string $methodName
     * @param string $errorType
     * @param array $detectedEcosystems
     * @return bool
     */
    public function isWidgetMethodExcepted(string $widgetType, string $methodName, string $errorType, array $detectedEcosystems = []): bool {
        // Check ecosystem-specific widget exceptions
        foreach ($detectedEcosystems as $ecosystem) {
            $ecosystem = strtolower($ecosystem);
            if (isset($this->ecosystemExceptions[$ecosystem]['widget_exclusions'])) {
                $widgetExclusions = $this->ecosystemExceptions[$ecosystem]['widget_exclusions'];

                // Check if widget type is in exclusion list
                if (isset($widgetExclusions[$widgetType])) {
                    $widgetConfig = $widgetExclusions[$widgetType];

                    // Check if method is specifically excluded
                    if (isset($widgetConfig['methods']) && in_array($methodName, $widgetConfig['methods'])) {
                        return true;
                    }

                    // Check if error type is excluded for this widget
                    if (isset($widgetConfig['error_types']) && in_array($errorType, $widgetConfig['error_types'])) {
                        return true;
                    }

                    // Check if widget is completely excluded
                    if (isset($widgetConfig['exclude_all']) && $widgetConfig['exclude_all'] === true) {
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

                // Additional Elementor classes commonly used in addons
                'Repeater',
                'Global_Typography',
                'Global_Colors',
                'HelperClass', // Common in EA plugins
                'RegExp', // JavaScript RegExp class sometimes referenced

                // Essential Addons specific classes
                'Essential_Addons_Elementor\\Classes\\Bootstrap',
                'Essential_Addons_Elementor\\Classes\\WPDeveloper_Setup_Wizard',
                'Essential_Addons_Elementor\\Classes\\Helper',
                'Essential_Addons_Elementor\\Pro\\Classes\\Helper',
                'Essential_Addons_Elementor\\Classes\\Plugin_Usage_Tracker',
                'Woo_Cart_Shortcode', 'Woo_Product_List', 'Product_Grid',

                // Elementor control classes
                'Control_Choose', 'Base_Data_Control',
            ],
            'class_patterns' => [
                'Elementor\\*',
                'ElementorPro\\*',
                'Essential_Addons_Elementor\\Pro\\*',
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

                // Additional Elementor functions
                'WPDeveloper_Setup_Wizard', // Common in EA plugins
                'Repeater', // Sometimes used as function
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

                // Additional WooCommerce classes
                'Automattic\\WooCommerce\\Utilities\\FeaturesUtil',
                'WC_Admin_Settings', 'WC_Settings_API', 'WC_Integration',
                'WC_Widget', 'WC_Widget_Cart', 'WC_Widget_Product_Categories',
                'WC_Widget_Product_Search', 'WC_Widget_Product_Tag_Cloud',
                'WC_Widget_Products', 'WC_Widget_Recently_Viewed',
                'WC_Widget_Top_Rated_Products', 'WC_Widget_Recent_Reviews',
                'WC_Widget_Layered_Nav', 'WC_Widget_Layered_Nav_Filters',
                'WC_Widget_Price_Filter', 'WC_Widget_Rating_Filter',
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

                // Common CSS class names that get incorrectly detected as PHP classes
                'rating', 'span', 'loader', 'button', 'hover', 'inner', 'time',
                'tamaya', 'winona', 'ujarak', 'rayen', 'wapasha', 'antiman', 'pipaluk', 'quidel', 'saqui',
                'strings', 'child', 'not',

                // JavaScript/Browser classes that get detected
                'XMLHttpRequest', 'DateTimeZone', 'RegExp',

                // Plugin-specific classes that are commonly used across plugins
                'Helper', 'ControlsHelper', 'ClassesHelper', 'AllTraits', 'Elements_Manager',
                'CacheBank', 'NoticeRemover', 'Notices', 'Plugin_Usage_Tracker', 'Asset_Builder',
                'LicenseManager', 'WPDeveloper_Plugin_Installer', 'CheckoutHelperCLass',

                // Third-party plugin classes that might not be loaded
                'RGFormsModel', 'Caldera_Forms_Forms', 'BetterDocs_DB',
                'FluentForm\\App\\Helpers\\Helper',

                // WordPress core classes that might not be loaded during testing
                'WP_Query', 'WP_User_Query', 'WP_Comment_Query', 'WP_Term_Query', 'WP_Site_Query',
                'WP_Network_Query', 'WP_Meta_Query', 'WP_Date_Query', 'WP_Tax_Query',
                'WP_User', 'WP_Post', 'WP_Term', 'WP_Comment', 'WP_Taxonomy', 'WP_Site', 'WP_Network',
                'WP_Error', 'WP_HTTP', 'WP_HTTP_Response', 'WP_HTTP_Requests_Response',
                'WP_Filesystem_Base', 'WP_Roles', 'WP_Role', 'WP_Widget', 'WP_Customize_Manager',
                'WP_List_Table', 'WP_Screen', 'WP_Admin_Bar', 'WP_Theme', 'WP_Locale',
                'WP_Rewrite', 'WP_Hook', 'WP_Dependency', 'WP_Session_Tokens',
                'Plugin_Upgrader', 'WP_Ajax_Upgrader_Skin', 'Automatic_Upgrader_Skin',
                'Theme_Upgrader', 'Core_Upgrader', 'Language_Pack_Upgrader',
                'WP_Upgrader', 'WP_Upgrader_Skin', 'WP_Automatic_Updater',

                // WooCommerce classes
                'WC_Query', 'WC_Product', 'WC_Order', 'WC_Customer', 'WC_Cart', 'WC_Checkout',
                'WC_Payment_Gateway', 'WC_Shipping_Method', 'WC_Tax', 'WC_Coupon',

                // PHP built-in classes
                'DOMDocument', 'DOMElement', 'DOMNode', 'DOMNodeList', 'DOMXPath',
                'Exception', 'ErrorException', 'Error', 'ParseError', 'TypeError',
                'LogicException', 'RuntimeException', 'InvalidArgumentException',
                'DateTime', 'DateTimeImmutable', 'DateInterval', 'DatePeriod', 'DateTimeZone',
                'PDO', 'PDOStatement', 'PDOException', 'mysqli', 'mysqli_stmt', 'mysqli_result',
                'SimpleXMLElement', 'XMLReader', 'XMLWriter', 'XSLTProcessor',
                'ReflectionClass', 'ReflectionMethod', 'ReflectionProperty', 'ReflectionFunction',
                'SplFileInfo', 'SplFileObject', 'DirectoryIterator', 'RecursiveDirectoryIterator',
                'ArrayIterator', 'ArrayObject', 'stdClass', 'Closure',

                // Plugin-specific classes with typos (common in EA)
                'HelperCLass', // Note the typo - this is how it appears in EA code
                'Helper_Class', // Another variant

                // WordPress REST API classes
                'WP_REST_Server',

                // Third-party classes
                'Google_Client', 'EDD_SL_Plugin_Updater',

                // Generic class names that are often CSS/JS related
                'Date', 'Tag', 'Module', 'Control_Media',

                // Pro plugin classes
                'Skin_Base', 'EAEL_Background',
            ],
            'functions' => [
                // WordPress core functions
                '__', '_e', 'esc_html__', 'esc_attr__', 'esc_html_e', 'esc_attr_e',
                'plugin_basename', 'trailingslashit', 'untrailingslashit',
                'wp_doing_ajax', 'wp_doing_cron', 'wp_is_json_request', 'wp_is_xml_request',
                'add_action', 'add_filter', 'remove_action', 'remove_filter',
                'wp_enqueue_script', 'wp_enqueue_style', 'wp_register_script', 'wp_register_style',
                'get_option', 'update_option', 'delete_option', 'add_option',
                'get_post_meta', 'update_post_meta', 'delete_post_meta', 'add_post_meta',
                'wp_nonce_field', 'wp_verify_nonce', 'wp_create_nonce',
                'current_user_can', 'is_user_logged_in', 'wp_get_current_user',
                'wp_die', 'wp_redirect', 'wp_safe_redirect',
                'sanitize_text_field', 'sanitize_email', 'sanitize_url', 'esc_url', 'esc_url_raw',
                'wp_kses', 'wp_kses_post', 'wp_strip_all_tags',
                'get_permalink', 'get_the_ID', 'get_the_title', 'get_the_content',
                'wp_insert_post', 'wp_update_post', 'wp_delete_post',
                'get_posts', 'get_post', 'wp_query',
                'is_admin', 'is_front_page', 'is_home', 'is_single', 'is_page',
                'wp_ajax_*', 'wp_ajax_nopriv_*',

                // Additional WordPress functions commonly used
                'current_time', 'date_i18n', 'set_transient', 'get_transient', 'delete_transient',
                'absint', 'wp_parse_args', 'wp_list_pluck', 'wp_array_slice_assoc',
                'wp_json_encode', 'wp_unslash', 'wp_slash', 'wp_normalize_path',
                'wp_upload_dir', 'wp_get_upload_dir', 'wp_mkdir_p',
                'wp_remote_get', 'wp_remote_post', 'wp_remote_request',
                'wp_schedule_event', 'wp_unschedule_event', 'wp_next_scheduled',
                'wp_mail', 'wp_get_theme', 'get_template_directory', 'get_stylesheet_directory',
                'wp_localize_script', 'wp_add_inline_script', 'wp_add_inline_style',
                'wp_get_attachment_url', 'wp_get_attachment_image', 'wp_get_attachment_image_src',
                'wp_insert_attachment', 'wp_update_attachment_metadata', 'wp_generate_attachment_metadata',

                // CSS functions that get incorrectly detected as PHP functions
                'calc', 'rgba', 'rgb', 'hsl', 'hsla', 'linear-gradient', 'radial-gradient',
                'gradient', 'var', 'url', 'attr', 'counter', 'counters',
                'RotateX', 'RotateY', 'RotateZ', 'SkewX', 'SkewY', 'ScaleX', 'ScaleY',
                'TranslateX', 'TranslateY', 'TranslateZ', 'Perspective',
                'Color', 'Background', 'Desktop', 'Source', 'fills', 'steps', 'Point', 'draws',
                'After', 'To', 'Interval', 'Top', 'Right', 'Bottom', 'Left',
                'media', 'features',

                // CSS pseudo-selectors and keywords
                'not', 'child', 'hover', 'active', 'focus', 'visited', 'disabled',
                'first-child', 'last-child', 'nth-child', 'nth-of-type',

                // JavaScript/Browser APIs that get detected
                'XMLHttpRequest', 'DateTimeZone', 'Duration', 'Type', 'Style', 'Two', 'X',
                'Greek', 'Wave', 'Aylen', 'Saqui', 'Wapasha', 'Nuka', 'Antiman', 'Quidel', 'Shikoba',
                'Pipaluk', 'Moema',

                // Common class names that are actually CSS classes or other non-PHP
                'and', 'or',

                // Additional WordPress functions that are commonly used
                'taxonomy_exists', 'sanitize_term', 'sanitize_post', 'check_ajax_referer', 'sanitize_file_name',
                'sanitize_key', 'setup_postdata', 'email_exists', 'sanitize_user', 'validate_username',
                'username_exists', 'media_handle_upload', 'retrieve_password', 'check_password_reset_key',
                'reset_password', 'wpautop', 'zeroise', 'url_to_postid', 'current_theme_supports',
                'admin_url', 'site_url', 'self_admin_url', 'load_plugin_textdomain', 'human_time_diff',
                'did_action', 'activate_plugin', 'plugins_api', 'sanitize_html_class', '_x',

                // CSS/JavaScript functions that get incorrectly detected
                'void', 's', 'extra', 'query', 'Fields', 'betterdocs_query', 'layout', 'Offset',
                'terms_style', 'read_more_button_style', 'load_more_button_style', 'custom_positioning',
                'EventON', 'nothing_found_style', 'blocks', 'performance', 'Converter', 'status',
                'redirect', 'instance', 'Compatibility_Support', 'WPDeveloper_Core_Installer',
                'eael_init_plugin_updater', 'render_compare_table', 'print_icon', 'print_compare_button',
                'translate', 'scale', 'static_get_products_list', 'static_fields', 'profile',
                'Content', 'term', 'Width', 'Speed', 'Rainbow', 'Fill', 'Box', 'General', 'URL',
                'Sticky', 'Steps', 'Split', 'Required', 'Spacing', 'Woo_Carrier_Agents', 'Size',
                'Login', 'Database', 'Sheets', 'TablePress', 'Dimension', 'ms', 'slides', 'Flow',
                'Harmonic', 'comma', 'valid', 'Padding', 'eael_customize_woo_prod_thumbnail_size',
                'Period', 'tribe_get_events', 'wpforms_display', 'gravity_form',

                // Third-party plugin functions
                'ninja_table_get_table_settings', 'ninja_table_get_table_columns', 'ninjaTablesGetTablesDataByID',
                'eael_get_product_category_name',

                // Additional WordPress functions
                'deactivate_plugins', 'shortcode_atts', 'strip_shortcodes', 'wptexturize', 'sanitize_title',
                'WC', 'esc_html_x', 'tooltip', 'shortcode_unautop', 'single_cat_title', 'single_tag_title',
                'comments_open', 'post_class', 'acf_get_field_groups', 'acf_get_fields', 'acf_get_field',
                'home_url', 'flush_rewrite_rules', 'term_exists', 'dynamic_sidebar',

                // PHP built-in classes that get detected as functions
                'mysqli', 'stdClass',

                // Third-party API functions
                'Google_Client', 'ld_get_mycourses', 'learndash_get_course_price', 'learndash_get_group_price',
                'sfwd_lms_has_access', 'learndash_course_completed', 'learndash_is_user_in_group',
                'learndash_get_user_group_completed_timestamp',

                // Language/locale functions that get detected
                'English', 'Portuguese', 'Chinese',

                // Plugin-specific functions that are commonly used
                'eael_get_widget_settings', 'eael_pagination', 'eael_product_quick_view',
                'eael_avoid_redirect_to_single_page', 'eael_woo_product_grid_actions',
                'eael_validate_html_tag', 'eael_wp_kses', 'eael_allowed_protocols',
                'eael_allowed_tags', 'eael_allowed_icon_tags', 'eael_fetch_color_or_global_color',
                'eael_get_woo_product_gallery_image_srcs', 'eael_sanitize_relation',
                'eael_get_all_user_ordered_products', 'eael_get_current_device_by_screen',
                'eael_get_attachment_id_from_url', 'eael_rating_markup', 'eael_onpage_edit_template_markup',
                'eael_e_optimized_markup', 'eael_checkout_cart_quantity_input_print',
                'eael_woo_cart_totals', 'eael_cart_button_proceed_to_checkout',
                'eael_print_produt_badge_html', 'eael_print_product_title_html',
                'ea_get_woo_checkout_settings', 'ea_set_woo_checkout_settings', 'ea_checkout',
                'ea_order_received', 'ea_order_pay', 'ea_coupon_template', 'ea_login_template',
                'ea_get_woo_cart_settings', 'ea_set_woo_cart_settings',
                'render_template_', 'render_default_template_', 'checkout_order_review_template', 'checkout_order_review_default',
                'woo_cart_style_one', 'woo_cart_style_two', 'woo_cart_collaterals', 'output',
                'set_eael_advanced_accordion_faq', 'include_with_variable', 'prevent_extension_loading',
                'str_to_css_id', 'fix_old_query', 'go_premium', 'find_element_recursive', 'replace_widget_name',
                'WHERE', 'LENGTH', 'current_revision_id', 'relation',

                // More CSS/JS/UI functions that get incorrectly detected
                'break', 'register', 'activate', 'deactivate', 'submit_otp', 'resend_otp', 'hook',
                'api_request', 'user_roles', 'mailchimp_lists', 'list_db_tables', 'list_tablepress_tables',
                'validate_post_types', 'query_dynamic_tags', 'out', 'by', 'total', 'Price', 'Column',
                'Relative', 'format', 'dates', 'Item', 'Tags', 'In', 'Timeout', 'Settings', 'From',
                'plus', 'minus', 'Menu', 'type', 'Delay', 'Tooltip', 'Horizontal', 'Vertical', 'here',
                'day', 'State', 'Timing', 'Comma',
            ],
            'function_patterns' => [
                'wp_*', 'get_*', 'is_*', 'has_*', 'the_*', 'esc_*', 'sanitize_*',
            ],
        ];
    }
}
