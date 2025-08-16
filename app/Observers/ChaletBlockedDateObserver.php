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
     * Clear availability cache for a specific chalet and proactively regenerate common ranges
     */
    private function clearChaletAvailabilityCache(int $chaletId): void
    {
        // Clear cache for both booking types without date ranges
        $bookingTypes = ['day-use', 'overnight'];

        $clearedCount = 0;
        $regeneratedCount = 0;

        foreach ($bookingTypes as $bookingType) {
            $cacheKey = "chalet_availability_{$chaletId}_{$bookingType}";
            if (Cache::has($cacheKey)) {
                Cache::forget($cacheKey);
                $clearedCount++;
                $regeneratedCount++;
            }
        }

        \Log::info('Cleared and regenerated availability cache for chalet', [
            'chalet_id' => $chaletId,
            'reason' => 'blocked_date_changed',
            'cleared_keys_count' => $clearedCount,
            'regenerated_keys_count' => $regeneratedCount,
        ]);
    }

    /**
     * Proactively regenerate cache for a specific chalet and date range
     */
    private function regenerateAvailabilityCache(int $chaletId, string $bookingType, string $startDate, string $endDate): void
    {
        try {
            $chalet = \App\Models\Chalet::find($chaletId);
            if (! $chalet) {
                return;
            }

            $cacheKey = "chalet_unavailable_dates_{$chaletId}_{$bookingType}_{$startDate}_{$endDate}";

            // Regenerate cache in background
            dispatch(function () use ($chalet, $bookingType, $startDate, $endDate, $cacheKey) {
                $availabilityChecker = new \App\Services\ChaletAvailabilityChecker($chalet);

                $unavailableDayUseDates = [];
                $unavailableOvernightDates = [];
                $fullyBlockedDates = [];

                $currentDate = \Carbon\Carbon::parse($startDate);
                $endDateCarbon = \Carbon\Carbon::parse($endDate);

                while ($currentDate <= $endDateCarbon) {
                    $dateStr = $currentDate->format('Y-m-d');

                    // Check day-use availability
                    $availableDayUseSlots = $availabilityChecker->getAvailableDayUseSlots($dateStr);
                    if ($availableDayUseSlots->isEmpty()) {
                        $unavailableDayUseDates[] = $dateStr;
                    }

                    // Check overnight availability
                    $nextDay = $currentDate->copy()->addDay()->format('Y-m-d');
                    $availableOvernightSlots = $availabilityChecker->getAvailableOvernightSlots($dateStr, $nextDay);
                    if ($availableOvernightSlots->isEmpty()) {
                        $unavailableOvernightDates[] = $dateStr;
                    }

                    // Check if entire day is blocked
                    if ($availableDayUseSlots->isEmpty() && $availableOvernightSlots->isEmpty()) {
                        $fullyBlockedDates[] = $dateStr;
                    }

                    $currentDate->addDay();
                }

                $cacheData = [
                    'unavailable_day_use_dates' => $unavailableDayUseDates,
                    'unavailable_overnight_dates' => $unavailableOvernightDates,
                    'fully_blocked_dates' => $fullyBlockedDates,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate,
                    ],
                    'generated_at' => now()->toISOString(),
                ];

                Cache::put($cacheKey, $cacheData, now()->addYears(1));

                \Log::info('Proactively regenerated availability cache', [
                    'chalet_id' => $chalet->id,
                    'booking_type' => $bookingType,
                    'cache_key' => $cacheKey,
                    'date_range' => "{$startDate} to {$endDate}",
                ]);
            });

        } catch (\Exception $e) {
            \Log::error('Error regenerating availability cache', [
                'chalet_id' => $chaletId,
                'booking_type' => $bookingType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
