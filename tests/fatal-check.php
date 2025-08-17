<?php
// Internal test to verify plugin loading
$plugin = $argv[1] ?? basename(getcwd());

try {
    define('WP_USE_THEMES', false);
    require __DIR__ . "/../../wp-load.php";
    include_once WP_PLUGIN_DIR . "/{$plugin}/{$plugin}.php";
    echo "✔️ {$plugin} loaded successfully.\n";
} catch (Throwable $e) {
    echo "❌ {$plugin} fatal: " . $e->getMessage() . "\n";
}
