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
     * Clear availability cache for a specific chalet and refresh it asynchronously
     */
    private function clearChaletAvailabilityCache(int $chaletId): void
    {
        // Clear existing cache immediately
        $clearedCount = $this->clearCacheEntries($chaletId);
        
        // Dispatch job to refresh cache asynchronously
        \App\Jobs\RefreshChaletAvailabilityCacheJob::dispatch($chaletId);
        
        \Log::info('Cleared availability cache and dispatched refresh job', [
            'chalet_id' => $chaletId,
            'reason' => 'blocked_date_changed',
            'cleared_keys_count' => $clearedCount
        ]);
    }

    /**
     * Clear cache entries for a specific chalet
     */
    private function clearCacheEntries(int $chaletId): int
    {
        $bookingTypes = ['day-use', 'overnight'];
        $dateRanges = [
            // Common date ranges that might be cached
            [now()->format('Y-m-d'), now()->addMonths(3)->format('Y-m-d')],
            [now()->format('Y-m-d'), now()->addMonths(1)->format('Y-m-d')],
            [now()->format('Y-m-d'), now()->addDays(90)->format('Y-m-d')],
            [now()->startOfMonth()->format('Y-m-d'), now()->endOfMonth()->format('Y-m-d')],
            [now()->addMonth()->startOfMonth()->format('Y-m-d'), now()->addMonth()->endOfMonth()->format('Y-m-d')],
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
        
        return $clearedCount;
    }
}