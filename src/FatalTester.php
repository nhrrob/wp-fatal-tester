<?php
namespace NHRROB\WPFatalTester;

class FatalTester {
    public function run(array $options): void {
        echo "🚀 Running fatal test for plugin: {$options['plugin']}\n";
        echo "   PHP versions: " . implode(', ', $options['php']) . "\n";
        echo "   WP versions: " . implode(', ', $options['wp']) . "\n";

        foreach ($options['php'] as $php) {
            foreach ($options['wp'] as $wp) {
                echo "▶️ Testing {$options['plugin']} on PHP {$php}, WP {$wp}...\n";
                // Simulate fatal check (replace with actual logic)
                $result = $this->simulate($options['plugin'], $php, $wp);

                if (!$result) {
                    echo "❌ Failed on PHP {$php}, WP {$wp}\n";
                    return;
                }
            }
        }

        echo "✅ All tests passed!\n";
    }

    private function simulate(string $plugin, string $php, string $wp): bool {
        // TODO: integrate with wp-env or Docker for real plugin testing
        return true;
    }
}
