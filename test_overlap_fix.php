<?php

require_once 'vendor/autoload.php';

use App\Services\ChaletAvailabilityChecker;
use App\Models\Chalet;

// Test the overlap detection fix
$chalet = Chalet::find(12);
$checker = new ChaletAvailabilityChecker($chalet);

echo "Testing Full Day slot (35) availability on July 26, 2025:\n";
echo "Day Shift (34) is blocked on this date\n";
echo "Full Day (35) should be unavailable due to overlap\n\n";

$isAvailable = $checker->isDayUseSlotAvailable('2025-07-26', 35);
echo "Result: " . ($isAvailable ? "AVAILABLE ❌ (BUG!)" : "NOT AVAILABLE ✅ (FIXED!)") . "\n";

echo "\nTesting Night Stay slot (36) availability on July 26, 2025:\n";
echo "Night Stay (36) should be available (no overlap)\n";

$isAvailable = $checker->isDayUseSlotAvailable('2025-07-26', 36);
echo "Result: " . ($isAvailable ? "AVAILABLE ✅" : "NOT AVAILABLE ❌ (BUG!)") . "\n";