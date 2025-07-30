<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ChaletBlockedDate;
use Illuminate\Support\Facades\Cache;

class ChaletBlockedDateObserver
{
    /**
     * Handle the ChaletBlockedDate "created" event.
     */
    public function created(ChaletBlockedDate $chaletBlockedDate): void
    {
        $this->clearChaletAvailabilityCache($chaletBlockedDate->chalet_id);
    }

    /**
     * Handle the ChaletBlockedDate "updated" event.
     */
    public function updated(ChaletBlockedDate $chaletBlockedDate): void
    {
        $this->clearChaletAvailabilityCache($chaletBlockedDate->chalet_id);
    }

    /**
     * Handle the ChaletBlockedDate "deleted" event.
     */
    public function deleted(ChaletBlockedDate $chaletBlockedDate): void
    {
        $this->clearChaletAvailabilityCache($chaletBlockedDate->chalet_id);
    }

    /**
     * Clear availability cache for a specific chalet
     */
    private function clearChaletAvailabilityCache(int $chaletId): void
    {
        // Clear cache by flushing all entries that start with our chalet pattern
        // Since we can't easily pattern match in all cache drivers, we'll clear common patterns
        $bookingTypes = ['day-use', 'overnight'];
        $dateRanges = [
            // Common date ranges that might be cached
            [now()->format('Y-m-d'), now()->addMonths(3)->format('Y-m-d')],
            [now()->format('Y-m-d'), now()->addMonths(1)->format('Y-m-d')],
            [now()->format('Y-m-d'), now()->addDays(90)->format('Y-m-d')],
        ];
        
        $clearedCount = 0;
        foreach ($bookingTypes as $bookingType) {
            foreach ($dateRanges as [$startDate, $endDate]) {
                $cacheKey = "chalet_unavailable_dates_{$chaletId}_{$bookingType}_{$startDate}_{$endDate}";
                if (Cache::has($cacheKey)) {
                    Cache::forget($cacheKey);
                    $clearedCount++;
                }
            }
        }
        
        \Log::info('Cleared availability cache for chalet', [
            'chalet_id' => $chaletId,
            'reason' => 'blocked_date_changed',
            'cleared_keys_count' => $clearedCount
        ]);
    }
}