<?php
/**
 * Plugin Name: Test Plugin Sample
 * Description: A sample plugin to test wp-fatal-tester
 */

// This should be fine - regular WordPress function
if (is_admin()) {
    echo "We're in admin";
}

// This should be flagged - admin-only function without proper include
if (is_plugin_active_for_network('my-plugin/my-plugin.php')) {
    echo "Plugin is active for network";
}

// This should be fine - with proper check
if (function_exists('is_plugin_active')) {
    if (is_plugin_active('another-plugin/another-plugin.php')) {
        echo "Plugin is active";
    }
}

// This should be fine - in admin context (but our static analysis can't know this)
add_action('admin_init', function() {
    if (is_plugin_active_for_network('test-plugin/test-plugin.php')) {
        echo "This runs in admin context";
    }
});

// This should be flagged - truly undefined function
$result = some_undefined_function();
