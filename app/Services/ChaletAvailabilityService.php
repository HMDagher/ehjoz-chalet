<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chalet;
use App\Models\ChaletTimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ChaletAvailabilityService
{
    public function __construct(
        private Chalet $chalet
    ) {}

    /**
     * Check if specific time slots are available on a single date
     * Validates that multiple slots are consecutive
     */
    public function isAvailableForDay(string $date, array $timeSlotIds): bool
    {
        $date = Carbon::parse($date)->format('Y-m-d');
        
        // If multiple slots, ensure they are consecutive
        if (count($timeSlotIds) > 1) {
            if (!$this->areTimeSlotsConsecutive($timeSlotIds)) {
                return false;
            }
        }

        foreach ($timeSlotIds as $timeSlotId) {
            if (!$this->isSingleSlotAvailable($date, $timeSlotId)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check if a single time slot is available on a specific date
     */
    private function isSingleSlotAvailable(string $date, int $timeSlotId): bool
    {
        // Check if entire day is blocked
        $dayBlocked = $this->chalet->blockedDates()
            ->where('date', $date)
            ->whereNull('time_slot_id')
            ->exists();
        
        if ($dayBlocked) {
            return false;
        }

        // Check if specific time slot is blocked
        $slotBlocked = $this->chalet->blockedDates()
            ->where('date', $date)
            ->where('time_slot_id', $timeSlotId)
            ->exists();
        
        if ($slotBlocked) {
            return false;
        }

        // Check if already booked (corrected query using many-to-many relationship)
        $alreadyBooked = $this->chalet->bookings()
            ->where(function ($query) use ($date) {
                $query->where(function ($q) use ($date) {
                    // Handles overnight bookings where the checkout day is available
                    $q->whereColumn('start_date', '!=', 'end_date')
                      ->whereDate('start_date', '<=', $date)
                      ->whereDate('end_date', '>', $date);
                })->orWhere(function ($q) use ($date) {
                    // Handles single-day bookings
                    $q->whereColumn('start_date', '=', 'end_date')
                      ->whereDate('start_date', '=', $date);
                });
            })
            ->whereHas('timeSlots', function ($query) use ($timeSlotId) {
                $query->where('chalet_time_slots.id', $timeSlotId);
            })
            ->whereIn('status', ['confirmed', 'pending'])
            ->exists();
        
        return !$alreadyBooked;
    }

    /**
     * Validate that time slots are consecutive (for multiple slot bookings)
     */
    private function areTimeSlotsConsecutive(array $timeSlotIds): bool
    {
        if (count($timeSlotIds) <= 1) {
            return true;
        }

        $timeSlots = $this->chalet->timeSlots()
            ->whereIn('id', $timeSlotIds)
            ->where('is_overnight', false) // Only day-use slots can be consecutive
            ->orderBy('start_time')
            ->get();

        if ($timeSlots->count() !== count($timeSlotIds)) {
            return false; // Some slots don't exist or are overnight
        }

        // Check if each slot's end time matches the next slot's start time
        for ($i = 0; $i < $timeSlots->count() - 1; $i++) {
            $currentSlot = $timeSlots[$i];
            $nextSlot = $timeSlots[$i + 1];
            
            if ($currentSlot->end_time !== $nextSlot->start_time) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if an overnight slot is available for a date range
     */
    public function isAvailableForOvernightRange(string $startDate, string $endDate, int $timeSlotId): bool
    {
        $slot = $this->chalet->timeSlots()->find($timeSlotId);
        
        if (!$slot || !$slot->is_overnight) {
            return false;
        }

        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $currentDate = $start->copy();

        while ($currentDate < $end) {
            if (!$this->isSingleSlotAvailable($currentDate->format('Y-m-d'), $timeSlotId)) {
                return false;
            }
            $currentDate->addDay();
        }

        return true;
    }

    /**
     * Get price for a specific date and time slot (with custom pricing support)
     */
    public function getPrice(string $date, int $timeSlotId): float
    {
        $date = Carbon::parse($date);
        $timeSlot = $this->chalet->timeSlots()->findOrFail($timeSlotId);

        // Check for custom pricing first
        $customPricing = $this->chalet->customPricing()
            ->where('time_slot_id', $timeSlotId)
            ->where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->where('is_active', true)
            ->latest('created_at')
            ->first();

        $basePrice = $this->isWeekend($date) ? $timeSlot->weekend_price : $timeSlot->weekday_price;
        $adjustment = $customPricing ? $customPricing->custom_adjustment : 0;

        return $basePrice + $adjustment;
    }

    /**
     * Calculate total price for multiple time slots on a single day
     */
    public function getTotalPriceForDay(string $date, array $timeSlotIds): float
    {
        $total = 0;
        
        foreach ($timeSlotIds as $timeSlotId) {
            $total += $this->getPrice($date, $timeSlotId);
        }
        
        return $total;
    }

    /**
     * Calculate total price for an overnight booking across date range
     */
    public function getTotalPriceForOvernightRange(string $startDate, string $endDate, int $timeSlotId): float
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $total = 0;
        $currentDate = $start->copy();

        while ($currentDate <= $end) {
            $total += $this->getPrice($currentDate->format('Y-m-d'), $timeSlotId);
            $currentDate->addDay();
        }

        return $total;
    }

    /**
     * Get available day-use time slots for a specific date
     */
    public function getAvailableDayUseSlots(string $date): Collection
    {
        $date = Carbon::parse($date);
        $dayOfWeek = strtolower($date->format('l'));

        return $this->chalet->timeSlots()
            ->where('is_active', true)
            ->where('is_overnight', false)
            ->get()
            ->filter(function ($slot) use ($date, $dayOfWeek) {
                // Check if slot is available on this day of week
                if (!in_array($dayOfWeek, $slot->available_days)) {
                    return false;
                }

                // Check availability
                return $this->isSingleSlotAvailable($date->format('Y-m-d'), $slot->id);
            })
            ->map(function ($slot) use ($date) {
                return [
                    'id' => $slot->id,
                    'name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'duration_hours' => $slot->duration_hours,
                    'price' => $this->getPrice($date->format('Y-m-d'), $slot->id),
                    'allows_extra_hours' => $slot->allows_extra_hours,
                    'extra_hour_price' => $slot->extra_hour_price,
                ];
            });
    }

    /**
     * Get available overnight time slots for a date range
     */
    public function getAvailableOvernightSlots(string $startDate, string $endDate): Collection
    {
        $startDate = Carbon::parse($startDate);
        $dayOfWeek = strtolower($startDate->format('l'));

        return $this->chalet->timeSlots()
            ->where('is_active', true)
            ->where('is_overnight', true)
            ->get()
            ->filter(function ($slot) use ($startDate, $endDate, $dayOfWeek) {
                // Check if slot is available on check-in day of week
                if (!in_array($dayOfWeek, $slot->available_days)) {
                    return false;
                }

                return $this->isAvailableForOvernightRange(
                    $startDate->format('Y-m-d'), 
                    $endDate, 
                    $slot->id
                );
            })
            ->map(function ($slot) use ($startDate, $endDate) {
                $totalPrice = $this->getTotalPriceForOvernightRange(
                    $startDate->format('Y-m-d'), 
                    $endDate, 
                    $slot->id
                );
                $nights = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
                
                return [
                    'id' => $slot->id,
                    'name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'duration_hours' => $slot->duration_hours,
                    'total_price' => $totalPrice,
                    'nights' => max(1, $nights),
                    'average_per_night' => $totalPrice / max(1, $nights),
                ];
            });
    }

    /**
     * Get all possible consecutive slot combinations for a date
     */
    public function getConsecutiveSlotCombinations(string $date): array
    {
        $availableSlots = $this->getAvailableDayUseSlots($date)
            ->sortBy('start_time')
            ->values();

        $combinations = [];
        
        // Single slots
        foreach ($availableSlots as $slot) {
            $combinations[] = [
                'slots' => [$slot],
                'total_duration' => $slot['duration_hours'],
                'total_price' => $slot['price'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
            ];
        }

        // Multiple consecutive slots
        for ($i = 0; $i < $availableSlots->count(); $i++) {
            $currentCombination = [$availableSlots[$i]];
            $totalPrice = $availableSlots[$i]['price'];
            $totalDuration = $availableSlots[$i]['duration_hours'];
            
            for ($j = $i + 1; $j < $availableSlots->count(); $j++) {
                // Check if slots are consecutive
                if ($availableSlots[$j - 1]['end_time'] === $availableSlots[$j]['start_time']) {
                    $currentCombination[] = $availableSlots[$j];
                    $totalPrice += $availableSlots[$j]['price'];
                    $totalDuration += $availableSlots[$j]['duration_hours'];
                    
                    $combinations[] = [
                        'slots' => $currentCombination,
                        'total_duration' => $totalDuration,
                        'total_price' => $totalPrice,
                        'start_time' => $currentCombination[0]['start_time'],
                        'end_time' => $currentCombination[count($currentCombination) - 1]['end_time'],
                    ];
                } else {
                    break; // Not consecutive, stop this combination
                }
            }
        }

        return $combinations;
    }

    private function isWeekend(Carbon $date): bool
    {
        return in_array($date->dayOfWeek, [5, 6, 0]); // Friday, Saturday, Sunday
    }
}