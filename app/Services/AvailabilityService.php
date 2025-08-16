<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Chalet;
use App\Models\ChaletBlockedDate;
use App\Models\ChaletTimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AvailabilityService
{
    private const CACHE_TTL = 3600; // 1 hour default cache

    /**
     * Check availability for a chalet
     *
     * @param  string  $startDate  "2025-08-20"
     * @param  string|null  $endDate  nullable for day-use, required for overnight
     * @param  string  $bookingType  "day-use" or "overnight"
     * @param  array  $timeSlotIds  empty means check all available slots
     */
    public function checkAvailability(
        int $chaletId,
        string $startDate,
        ?string $endDate = null,
        string $bookingType = 'day-use',
        array $timeSlotIds = []
    ): array {
        try {
            // Validate inputs
            if (! $this->validateInputs($chaletId, $startDate, $endDate, $bookingType)) {
                return [
                    'available' => false,
                    'errors' => ['Invalid input parameters'],
                    'available_slots' => [],
                ];
            }

            // Normalize end_date for day-use
            if ($bookingType === 'day-use' && ! $endDate) {
                $endDate = $startDate;
            }

            // Generate cache key
            $cacheKey = $this->generateCacheKey($chaletId, $startDate, $endDate, $bookingType, $timeSlotIds);

            // Try to get from cache
            if ($cached = Cache::get($cacheKey)) {
                Log::info('Availability check served from cache', ['cache_key' => $cacheKey]);

                return $cached;
            }

            // Get chalet and verify it exists
            $chalet = Chalet::find($chaletId);
            if (! $chalet) {
                return [
                    'available' => false,
                    'errors' => ['Chalet not found'],
                    'available_slots' => [],
                ];
            }

            // Get time slots to check
            $slotsToCheck = $this->getTimeSlotsToCheck($chaletId, $bookingType, $timeSlotIds);

            if ($slotsToCheck->isEmpty()) {
                return [
                    'available' => false,
                    'errors' => ['No time slots found for this booking type'],
                    'available_slots' => [],
                ];
            }

            // Check availability for each slot
            $availabilityResults = $this->checkSlotsAvailability($slotsToCheck, $startDate, $endDate, $bookingType);

            // Cache the results
            Cache::put($cacheKey, $availabilityResults, self::CACHE_TTL);

            return $availabilityResults;

        } catch (\Exception $e) {
            Log::error('Availability check failed', [
                'chalet_id' => $chaletId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'booking_type' => $bookingType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // In testing environment, show the actual error
            $errorMessage = app()->environment('testing')
                ? 'System error: '.$e->getMessage()
                : 'System error occurred during availability check';

            return [
                'available' => false,
                'errors' => [$errorMessage],
                'available_slots' => [],
            ];
        }
    }

    /**
     * Get available slots for a date range and booking type
     */
    private function checkSlotsAvailability(Collection $slots, string $startDate, string $endDate, string $bookingType): array
    {
        $availableSlots = [];
        $errors = [];
        $dateRange = TimeSlotHelper::getDateRange($startDate, $endDate);

        // Check for full day blocking first (optimize with single query over range)
        $chaletId = $slots->first()->chalet_id;
        $fullDayBlockedDates = ChaletBlockedDate::where('chalet_id', $chaletId)
            ->whereNull('time_slot_id') // Full day block has no specific time slot
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->pluck('date')
            ->map(fn ($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        if (!empty($fullDayBlockedDates)) {
            return [
                'available' => false,
                'available_slots' => [],
                'consecutive_combinations' => [],
                'errors' => ['full_day_blocked'],
            ];
        }

        foreach ($slots as $slot) {
            $slotAvailability = $this->checkSingleSlotAvailability($slot, $dateRange, $startDate, $endDate);

            if ($slotAvailability['available']) {
                $availableSlots[] = [
                    'slot_id' => $slot->id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'is_overnight' => $slot->is_overnight,
                    'weekday_price' => $slot->weekday_price,
                    'weekend_price' => $slot->weekend_price,
                    'available_dates' => $slotAvailability['available_dates'],
                    'pricing_info' => $slotAvailability['pricing_info'],
                ];
            } else {
                $errors = array_merge($errors, $slotAvailability['errors']);
            }
        }

        // For day-use bookings, check if consecutive slots can be combined
        if ($bookingType === 'day-use' && count($availableSlots) > 1) {
            $consecutiveCombinations = $this->findConsecutiveCombinations($availableSlots, $startDate);

            return [
                'available' => ! empty($availableSlots),
                'available_slots' => $availableSlots,
                'consecutive_combinations' => $consecutiveCombinations,
                'errors' => array_unique($errors),
            ];
        }

        return [
            'available' => ! empty($availableSlots),
            'available_slots' => $availableSlots,
            'errors' => array_unique($errors),
        ];
    }

    /**
     * Check availability for a single time slot
     *
     * @param  object  $slot
     */
    private function checkSingleSlotAvailability($slot, array $dateRange, string $startDate, string $endDate): array
    {
        $errors = [];
        $availableDates = [];
        $pricingInfo = [];

        foreach ($dateRange as $date) {
            // Check if date is in slot's available_days
            if (! TimeSlotHelper::isDateAllowed($date, $slot->available_days)) {
                $errors[] = "Slot {$slot->id} not available on ".Carbon::parse($date)->format('l');

                continue;
            }

            // Check for conflicts (blocked/booked)
            $conflicts = OverlapDetector::findConflictingSlots($slot->chalet_id, $slot, [$date]);

            if (! empty($conflicts['blocked']) || ! empty($conflicts['booked'])) {
                $conflictReasons = [];

                foreach ($conflicts['blocked'] as $blocked) {
                    $reason = $blocked['reason'] ?? 'unknown reason';
                    // Handle enum reasons (App\Enums\BlockReason) safely
                    if (is_object($reason)) {
                        if (method_exists($reason, 'value')) {
                            $reason = $reason->value;
                        } elseif (method_exists($reason, 'name')) {
                            $reason = $reason->name;
                        } else {
                            $reason = (string) json_encode($reason);
                        }
                    }
                    $conflictReasons[] = 'blocked: '.$reason;
                }

                foreach ($conflicts['booked'] as $booked) {
                    $conflictReasons[] = "booked (booking #{$booked['booking_id']})";
                }

                $errors[] = "Slot {$slot->id} on {$date}: ".implode(', ', $conflictReasons);

                continue;
            }

            // Date is available
            $availableDates[] = $date;

            // Get pricing info for this date
            $pricing = $this->calculateSlotPricing($slot, $date);
            $pricingInfo[$date] = $pricing;
        }

        return [
            'available' => ! empty($availableDates),
            'available_dates' => $availableDates,
            'pricing_info' => $pricingInfo,
            'errors' => $errors,
        ];
    }

    /**
     * Find consecutive time slot combinations for day-use bookings
     */
    private function findConsecutiveCombinations(array $availableSlots, string $date): array
    {
        $combinations = [];
        $slots = collect($availableSlots)->map(function ($slotData) {
            return (object) $slotData;
        });

        // Find all possible consecutive combinations
        for ($i = 0; $i < $slots->count(); $i++) {
            for ($j = $i + 1; $j <= $slots->count(); $j++) {
                $combination = $slots->slice($i, $j - $i);

                if (TimeSlotHelper::isConsecutive($combination)) {
                    $combinations[] = [
                        'slot_ids' => $combination->pluck('slot_id')->toArray(),
                        'start_time' => $combination->first()->start_time,
                        'end_time' => $combination->last()->end_time,
                        'total_duration' => $this->calculateTotalDuration($combination->toArray(), $date),
                    ];
                }
            }
        }

        return $combinations;
    }

    /**
     * Get time slots to check based on booking type and filters
     */
    private function getTimeSlotsToCheck(int $chaletId, string $bookingType, array $timeSlotIds): Collection
    {
        $query = ChaletTimeSlot::where('chalet_id', $chaletId)
            ->where('is_active', true);

        // Filter by booking type
        if ($bookingType === 'day-use') {
            $query->where('is_overnight', false);
        } elseif ($bookingType === 'overnight') {
            $query->where('is_overnight', true);
        }

        // Filter by specific slot IDs if provided
        if (! empty($timeSlotIds)) {
            $query->whereIn('id', $timeSlotIds);
        }

        return $query->get();
    }

    /**
     * Calculate pricing for a slot on a specific date
     *
     * @param  object  $slot
     */
    private function calculateSlotPricing($slot, string $date): array
    {
        // Determine if it's a weekend based on chalet configuration (align with models)
        $chalet = Chalet::find($slot->chalet_id);
        $dayOfWeek = strtolower(Carbon::createFromFormat('Y-m-d', $date)->format('l'));
        $weekendDays = $chalet?->weekend_days ?? ['saturday', 'sunday'];
        $isWeekend = in_array($dayOfWeek, array_map('strtolower', $weekendDays));
        $basePrice = $isWeekend ? $slot->weekend_price : $slot->weekday_price;

        // Check for custom pricing adjustments
        $customPricing = \App\Models\ChaletCustomPricing::where('chalet_id', $slot->chalet_id)
            ->where('time_slot_id', $slot->id)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        $adjustment = 0;
        if ($customPricing) {
            $rawAdjustment = $customPricing->custom_adjustment ?? ($customPricing->custom_price ?? 0);
            $adjustment = max(0, (float) $rawAdjustment);
        }
        $finalPrice = $basePrice + $adjustment;

        return [
            'base_price' => $basePrice,
            'adjustment' => $adjustment,
            'final_price' => max(0, $finalPrice), // Ensure price doesn't go negative
            'is_weekend' => $isWeekend,
            'custom_pricing_id' => $customPricing?->id,
        ];
    }

    /**
     * Calculate total duration for consecutive slots
     *
     * @return int minutes
     */
    private function calculateTotalDuration(array $slots, string $date): int
    {
        if (empty($slots)) {
            return 0;
        }

        // Ensure we have numeric keys
        $slots = array_values($slots);
        $firstSlot = (object) $slots[0];
        $lastSlot = (object) $slots[count($slots) - 1];

        $startDateTime = TimeSlotHelper::convertToDateTime($date, $firstSlot->start_time);
        $endDateTime = TimeSlotHelper::getSlotEndDateTime($startDateTime, $lastSlot->end_time);

        return $startDateTime->diffInMinutes($endDateTime);
    }

    /**
     * Validate input parameters
     */
    private function validateInputs(int $chaletId, string $startDate, ?string $endDate, string $bookingType): bool
    {
        // Validate chalet ID
        if ($chaletId <= 0) {
            return false;
        }

        // Validate booking type
        if (! in_array($bookingType, ['day-use', 'overnight'])) {
            return false;
        }

        // Validate date format
        try {
            Carbon::createFromFormat('Y-m-d', $startDate);
            if ($endDate) {
                Carbon::createFromFormat('Y-m-d', $endDate);
            }
        } catch (\Exception $e) {
            return false;
        }

        // Validate date range requirements
        if (! TimeSlotHelper::validateDateRange($bookingType, $endDate)) {
            return false;
        }

        // Validate end_date is not before start_date
        if ($endDate && $endDate < $startDate) {
            return false;
        }

        // Validate dates are not in the past
        $today = Carbon::today()->format('Y-m-d');
        if ($startDate < $today) {
            return false;
        }

        return true;
    }

    /**
     * Generate cache key for availability check
     */
    private function generateCacheKey(int $chaletId, string $startDate, string $endDate, string $bookingType, array $timeSlotIds): string
    {
        sort($timeSlotIds);
        $slotIds = empty($timeSlotIds) ? 'all' : implode(',', $timeSlotIds);

        return "chalet_availability_{$chaletId}_{$startDate}_{$endDate}_{$bookingType}_{$slotIds}";
    }

    /**
     * Clear availability cache for a chalet
     * This should be called when bookings, blocks, or time slots change
     *
     * @param  array  $affectedDates  Optional array of specific dates to clear
     */
    public static function clearAvailabilityCache(int $chaletId, array $affectedDates = []): void
    {
        try {
            // If specific dates provided, clear only those
            if (! empty($affectedDates)) {
                foreach ($affectedDates as $date) {
                    $pattern = "chalet_availability_{$chaletId}_{$date}_*";
                    Cache::forget($pattern);
                }
            } else {
                // Clear all availability cache for this chalet
                $pattern = "chalet_availability_{$chaletId}_*";
                Cache::forget($pattern);
            }

            Log::info('Availability cache cleared', [
                'chalet_id' => $chaletId,
                'affected_dates' => $affectedDates,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear availability cache', [
                'chalet_id' => $chaletId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get quick availability summary for multiple dates
     * Useful for calendar views
     */
    public function getAvailabilitySummary(int $chaletId, array $dates, string $bookingType = 'day-use'): array
    {
        $summary = [];

        foreach ($dates as $date) {
            $availability = $this->checkAvailability($chaletId, $date, $date, $bookingType);

            $summary[$date] = [
                'available' => $availability['available'],
                'slot_count' => count($availability['available_slots']),
                'has_errors' => ! empty($availability['errors']),
            ];
        }

        return $summary;
    }

    /**
     * Check if specific time slots are available for booking
     * This is the final check before creating a booking
     */
    public function validateBookingRequest(
        int $chaletId,
        array $timeSlotIds,
        string $startDate,
        ?string $endDate,
        string $bookingType
    ): array {
        // Get detailed availability check
        $availability = $this->checkAvailability($chaletId, $startDate, $endDate, $bookingType, $timeSlotIds);

        if (! $availability['available']) {
            return [
                'valid' => false,
                'errors' => $availability['errors'],
            ];
        }

        // Validate that all requested slots are available
        $availableSlotIds = collect($availability['available_slots'])->pluck('slot_id')->toArray();
        $missingSlots = array_diff($timeSlotIds, $availableSlotIds);

        if (! empty($missingSlots)) {
            return [
                'valid' => false,
                'errors' => ['Some requested time slots are not available: '.implode(', ', $missingSlots)],
            ];
        }

        // For day-use, validate consecutive requirement
        if ($bookingType === 'day-use' && count($timeSlotIds) > 1) {
            $requestedSlots = collect($availability['available_slots'])
                ->whereIn('slot_id', $timeSlotIds)
                ->map(function ($slot) {
                    return (object) $slot;
                });

            if (! TimeSlotHelper::isConsecutive($requestedSlots)) {
                return [
                    'valid' => false,
                    'errors' => ['Day-use bookings must use consecutive time slots'],
                ];
            }
        }

        return [
            'valid' => true,
            'availability_data' => $availability,
        ];
    }
}
