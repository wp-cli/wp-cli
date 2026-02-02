<?php
/**
 * Example demonstrating step-based progress display.
 * 
 * This example shows the new format customization feature added to
 * the Bar progress indicator in php-cli-tools.
 * 
 * Usage: php examples/step-based-progress.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== Step-Based Progress Display Examples ===\n\n";

// Example 1: Default percentage-based format (backward compatible)
echo "1. Default format (percentage-based):\n";
$progress1 = new \cli\progress\Bar('Processing items', 20);
for ($i = 0; $i < 20; $i++) {
    $progress1->tick();
    usleep(50000); // 50ms delay
}
$progress1->finish();

echo "\n\n";

// Example 2: Step-based format using {:current} and {:total}
echo "2. Step-based format (current/total):\n";
$progress2 = new \cli\progress\Bar('Downloading files', 20, 100, '{:msg}  {:current}/{:total} [');
for ($i = 0; $i < 20; $i++) {
    $progress2->tick();
    usleep(50000); // 50ms delay
}
$progress2->finish();

echo "\n\n";

// Example 3: Mixed format with both percentage and steps
echo "3. Mixed format (percentage + steps):\n";
$progress3 = new \cli\progress\Bar('Syncing data', 20, 100, '{:msg}  {:percent}% ({:current}/{:total}) [');
for ($i = 0; $i < 20; $i++) {
    $progress3->tick();
    usleep(50000); // 50ms delay
}
$progress3->finish();

echo "\n\n";

// Example 4: Custom format with just steps and no percentage
echo "4. Simple step counter:\n";
$progress4 = new \cli\progress\Bar('Items processed', 20, 100, '{:current}/{:total} ');
for ($i = 0; $i < 20; $i++) {
    $progress4->tick();
    usleep(50000); // 50ms delay
}
$progress4->finish();

echo "\n\n";

// Example 5: Format with custom separators
echo "5. Custom separators:\n";
$progress5 = new \cli\progress\Bar('Building', 20, 100, '{:msg}: {:current} of {:total} [');
for ($i = 0; $i < 20; $i++) {
    $progress5->tick();
    usleep(50000); // 50ms delay
}
$progress5->finish();

echo "\n\n=== All examples completed! ===\n";
