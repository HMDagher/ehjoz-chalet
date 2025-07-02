<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chalet;
use App\Models\ChaletTimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ChaletAvailabilityChecker
{
    private Chalet $chalet;

    public function __construct(Chalet $chalet)
    {
        $this->chalet = $chalet;
    }

    /**
     * Helper: Check if two time ranges overlap
     */
    private function timeRangesOverlap($start1, $end1, $start2, $end2): bool
    {
        // Handles overnight slots (end < start)
        $toMinutes = function($time) {
            [$h, $m, $s] = array_pad(explode(':', $time), 3, 0);
            return ((int)$h) * 60 + (int)$m;
        };
        $s1 = $toMinutes($start1);
        $e1 = $toMinutes($end1);
        $s2 = $toMinutes($start2);
        $e2 = $toMinutes($end2);
        // Normalize overnight
        if ($e1 <= $s1) $e1 += 24 * 60;
        if ($e2 <= $s2) $e2 += 24 * 60;
        return ($s1 < $e2 && $s2 < $e1);
    }

    /**
     * Check if a day-use time slot is available on a specific date
     */
    public function isDayUseSlotAvailable(string $date, int $timeSlotId): bool
    {
        $date = Carbon::parse($date)->format('Y-m-d');
        $slot = $this->chalet->timeSlots()->find($timeSlotId);
        if (!$slot || $slot->is_overnight) {
            \Log::info('Checker: Slot not found or is overnight', ['slot_id' => $timeSlotId, 'date' => $date]);
            return false;
        }
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
        if (!isset($slot->available_days) || !is_array($slot->available_days) || !in_array($dayOfWeek, $slot->available_days)) {
            \Log::info('Checker: Slot not available on this day', ['slot_id' => $timeSlotId, 'date' => $date, 'dayOfWeek' => $dayOfWeek, 'available_days' => $slot->available_days]);
            return false;
        }
        $dayBlocked = $this->chalet->blockedDates()
            ->where('date', $date)
            ->whereNull('time_slot_id')
            ->exists();
        if ($dayBlocked) {
            \Log::info('Checker: Day is blocked', ['slot_id' => $timeSlotId, 'date' => $date]);
            return false;
        }
        $slotBlocked = $this->chalet->blockedDates()
            ->where('date', $date)
            ->where('time_slot_id', $timeSlotId)
            ->exists();
        if ($slotBlocked) {
            \Log::info('Checker: Slot is blocked', ['slot_id' => $timeSlotId, 'date' => $date]);
            return false;
        }
        $alreadyBooked = $this->chalet->bookings()
            ->where(function ($query) use ($date) {
                $query->where(function ($q) use ($date) {
                    $q->whereDate('start_date', '=', $date)
                      ->whereDate('end_date', '=', $date);
                });
            })
            ->whereHas('timeSlots', function ($query) use ($timeSlotId) {
                $query->where('chalet_time_slots.id', $timeSlotId);
            })
            ->whereIn('status', ['confirmed', 'pending'])
            ->exists();
        if ($alreadyBooked) {
            \Log::info('Checker: Slot already booked', ['slot_id' => $timeSlotId, 'date' => $date]);
            return false;
        }
        // Prevent overlap: check for overnight bookings that overlap this slot's time
        $overnightSlots = $this->chalet->timeSlots()->where('is_overnight', true)->get();
        foreach ($overnightSlots as $overnightSlot) {
            $overnightBooking = $this->chalet->bookings()
                ->where(function ($query) use ($date) {
                    $query->whereDate('start_date', '<=', $date)
                          ->whereDate('end_date', '>', $date);
                })
                ->whereHas('timeSlots', function ($query) use ($overnightSlot) {
                    $query->where('chalet_time_slots.id', $overnightSlot->id);
                })
                ->whereIn('status', ['confirmed', 'pending'])
                ->exists();
            if ($overnightBooking && $this->timeRangesOverlap($slot->start_time, $slot->end_time, $overnightSlot->start_time, $overnightSlot->end_time)) {
                \Log::info('Checker: Overlap with overnight booking', ['slot_id' => $timeSlotId, 'overnight_slot_id' => $overnightSlot->id, 'date' => $date]);
                return false;
            }
        }
        \Log::info('Checker: Slot is available', ['slot_id' => $timeSlotId, 'date' => $date]);
        return true;
    }

    /**
     * Check if consecutive day-use slots are available
     */
    public function areConsecutiveSlotsAvailable(string $date, array $slotIds): bool
    {
        \Log::info('Checker: areConsecutiveSlotsAvailable', ['date' => $date, 'slot_ids' => $slotIds]);
        
        // Check if slots are consecutive
        if (!$this->areDayUseSlotIdsConsecutive($slotIds)) {
            \Log::info('Checker: Slots are not consecutive', ['slot_ids' => $slotIds]);
            return false;
        }
        
        // Check availability of each slot
        foreach ($slotIds as $slotId) {
            // Convert slot ID to integer to handle string inputs from frontend
            $slotIdInt = (int) $slotId;
            if (!$this->isDayUseSlotAvailable($date, $slotIdInt)) {
                \Log::info('Checker: Slot not available', ['slot_id' => $slotIdInt, 'date' => $date]);
                return false;
            }
        }
        
        \Log::info('Checker: Consecutive slots are available', ['slot_ids' => $slotIds, 'date' => $date]);
        return true;
    }

    /**
     * Check if multiple day-use slots are available (allows gaps between slots)
     */
    public function areMultipleSlotsAvailable(string $date, array $slotIds): bool
    {
        \Log::info('Checker: areMultipleSlotsAvailable', ['date' => $date, 'slot_ids' => $slotIds]);
        
        // Check availability of each slot individually (allows gaps between slots)
        foreach ($slotIds as $slotId) {
            // Convert slot ID to integer to handle string inputs from frontend
            $slotIdInt = (int) $slotId;
            if (!$this->isDayUseSlotAvailable($date, $slotIdInt)) {
                \Log::info('Checker: Slot not available for day-use booking', ['slot_id' => $slotIdInt, 'date' => $date]);
                return false;
            }
        }
        
        \Log::info('Checker: Multiple slots are available (with gaps allowed)', ['slot_ids' => $slotIds, 'date' => $date]);
        return true;
    }

    /**
     * Check if an overnight slot is available for a date range
     */
    public function isOvernightSlotAvailable(string $startDate, string $endDate, int $timeSlotId): bool
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $slot = $this->chalet->timeSlots()->find($timeSlotId);
        if (!$slot || !$slot->is_overnight) {
            \Log::info('Checker: Overnight slot not found or not overnight', ['slot_id' => $timeSlotId, 'start' => $startDate, 'end' => $endDate]);
            return false;
        }
        $checkInDayOfWeek = strtolower($start->format('l'));
        if (!isset($slot->available_days) || !is_array($slot->available_days) || !in_array($checkInDayOfWeek, $slot->available_days)) {
            \Log::info('Checker: Overnight slot not available on check-in day', ['slot_id' => $timeSlotId, 'start' => $startDate, 'end' => $endDate, 'checkInDayOfWeek' => $checkInDayOfWeek, 'available_days' => $slot->available_days]);
            return false;
        }
        $conflictingBooking = $this->chalet->bookings()
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($q) use ($start, $end) {
                    $q->where('start_date', '<=', $start->format('Y-m-d'))
                      ->where('end_date', '>', $start->format('Y-m-d'));
                })->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_date', '<', $end->format('Y-m-d'))
                      ->where('end_date', '>=', $end->format('Y-m-d'));
                })->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_date', '>=', $start->format('Y-m-d'))
                      ->where('end_date', '<', $end->format('Y-m-d'));
                });
            })
            ->whereHas('timeSlots', function ($query) use ($timeSlotId) {
                $query->where('chalet_time_slots.id', $timeSlotId);
            })
            ->whereIn('status', ['confirmed', 'pending'])
            ->exists();
        if ($conflictingBooking) {
            \Log::info('Checker: Overnight slot has conflicting booking', ['slot_id' => $timeSlotId, 'start' => $startDate, 'end' => $endDate]);
            return false;
        }
        $currentDate = $start->copy();
        while ($currentDate < $end) {
            $dayBlocked = $this->chalet->blockedDates()
                ->where('date', $currentDate->format('Y-m-d'))
                ->where(function ($query) use ($timeSlotId) {
                    $query->whereNull('time_slot_id')
                          ->orWhere('time_slot_id', $timeSlotId);
                })
                ->exists();
            if ($dayBlocked) {
                \Log::info('Checker: Overnight slot day is blocked', ['slot_id' => $timeSlotId, 'date' => $currentDate->format('Y-m-d')]);
                return false;
            }
            // Prevent overlap: check for day-use bookings that overlap this overnight slot's time
            $dayUseSlots = $this->chalet->timeSlots()->where('is_overnight', false)->get();
            foreach ($dayUseSlots as $daySlot) {
                $dayUseBooking = $this->chalet->bookings()
                    ->where(function ($query) use ($currentDate) {
                        $query->whereDate('start_date', '=', $currentDate->format('Y-m-d'))
                              ->whereDate('end_date', '=', $currentDate->format('Y-m-d'));
                    })
                    ->whereHas('timeSlots', function ($query) use ($daySlot) {
                        $query->where('chalet_time_slots.id', $daySlot->id);
                    })
                    ->whereIn('status', ['confirmed', 'pending'])
                    ->exists();
                if ($dayUseBooking && $this->timeRangesOverlap($slot->start_time, $slot->end_time, $daySlot->start_time, $daySlot->end_time)) {
                    \Log::info('Checker: Overlap with day-use booking', ['overnight_slot_id' => $slot->id, 'day_slot_id' => $daySlot->id, 'date' => $currentDate->format('Y-m-d')]);
                    return false;
                }
            }
            $currentDate->addDay();
        }
        \Log::info('Checker: Overnight slot is available', ['slot_id' => $timeSlotId, 'start' => $startDate, 'end' => $endDate]);
        return true;
    }
    
    /**
     * Calculate price for a day-use slot on a specific date
     */
    public function calculateDayUsePrice(string $date, int $timeSlotId): float
    {
        $date = Carbon::parse($date);
        $timeSlot = $this->chalet->timeSlots()->findOrFail($timeSlotId);

        // Get base price (weekday/weekend)
        $isWeekend = in_array($date->dayOfWeek, [5, 6, 0]); // Friday, Saturday, Sunday
        $basePrice = $isWeekend ? $timeSlot->weekend_price : $timeSlot->weekday_price;
        
        // Check for seasonal pricing adjustment
        $customPricing = $this->chalet->customPricing()
            ->where('time_slot_id', $timeSlotId)
            ->where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->where('is_active', true)
            ->latest('created_at')
            ->first();
        
        $adjustment = $customPricing ? $customPricing->custom_adjustment : 0;
        
        return $basePrice + $adjustment;
    }
    
    /**
     * Calculate total price for consecutive day-use slots
     */
    public function calculateConsecutiveSlotsPrice(string $date, array $slotIds): float
    {
        $total = 0;
        foreach ($slotIds as $slotId) {
            // Convert slot ID to integer to handle string inputs from frontend
            $slotIdInt = (int) $slotId;
            $total += $this->calculateDayUsePrice($date, $slotIdInt);
        }
        return $total;
    }
    
    /**
     * Calculate price for an overnight stay
     */
    public function calculateOvernightPrice(string $startDate, string $endDate, int $timeSlotId): array
    {
        \Log::info('calculateOvernightPrice called', [
            'startDate' => $startDate, 
            'endDate' => $endDate, 
            'timeSlotId' => $timeSlotId
        ]);
        
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $total = 0;
        $nights = $start->diffInDays($end);
        $nights = max(1, $nights);
        
        \Log::info('Nights calculated', ['nights' => $nights]);
        
        // Calculate price for each night
        $currentDate = $start->copy();
        $nightPrices = [];
        
        while ($currentDate < $end) {
            $dateStr = $currentDate->format('Y-m-d');
            $nightPrice = $this->calculateDayUsePrice($dateStr, $timeSlotId);
            $total += $nightPrice;
            
            \Log::info('Night price calculated', [
                'date' => $dateStr,
                'price' => $nightPrice,
                'running_total' => $total
            ]);
            
            $nightPrices[] = [
                'date' => $dateStr,
                'price' => $nightPrice
            ];
            
            $currentDate->addDay();
        }
        
        \Log::info('Total overnight price calculated', [
            'total' => $total,
            'nights' => $nights,
            'price_per_night' => $total / $nights,
            'night_prices' => $nightPrices
        ]);
        
        return [
            'total_price' => $total,
            'nights' => $nights,
            'price_per_night' => $total / $nights
        ];
    }
    
    /**
     * Get available day-use slots for a date
     */
    public function getAvailableDayUseSlots(string $date): Collection
    {
        $date = Carbon::parse($date)->format('Y-m-d');
        $slots = $this->chalet->timeSlots()
            ->where('is_active', true)
            ->where('is_overnight', false)
            ->get();
        \Log::info('Checker: getAvailableDayUseSlots', ['date' => $date, 'slot_ids' => $slots->pluck('id')->toArray()]);
        return $slots
            ->filter(function ($slot) use ($date) {
                $result = $this->isDayUseSlotAvailable($date, $slot->id);
                \Log::info('Checker: getAvailableDayUseSlots filter', ['slot_id' => $slot->id, 'result' => $result]);
                return $result;
            })
            ->map(function ($slot) use ($date) {
                $price = $this->calculateDayUsePrice($date, $slot->id);
                return [
                    'id' => $slot->id,
                    'name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'duration_hours' => $slot->duration_hours,
                    'price' => $price
                ];
            });
    }
    
    /**
     * Get available overnight slots for a date range
     */
    public function getAvailableOvernightSlots(string $startDate, string $endDate): Collection
    {
        \Log::info('Checker: getAvailableOvernightSlots called', ['start' => $startDate, 'end' => $endDate]);
        
        $slots = $this->chalet->timeSlots()
            ->where('is_active', true)
            ->where('is_overnight', true)
            ->get();
            
        \Log::info('Checker: getAvailableOvernightSlots found slots', [
            'chalet_id' => $this->chalet->id,
            'slots_count' => $slots->count(),
            'slot_ids' => $slots->pluck('id')->toArray()
        ]);
        
        return $slots
            ->filter(function ($slot) use ($startDate, $endDate) {
                $result = $this->isOvernightSlotAvailable($startDate, $endDate, $slot->id);
                \Log::info('Checker: getAvailableOvernightSlots filter', [
                    'slot_id' => $slot->id, 
                    'slot_name' => $slot->name,
                    'result' => $result
                ]);
                return $result;
            })
            ->map(function ($slot) use ($startDate, $endDate) {
                $priceData = $this->calculateOvernightPrice($startDate, $endDate, $slot->id);
                \Log::info('Checker: getAvailableOvernightSlots mapping slot', [
                    'slot_id' => $slot->id,
                    'price_data' => $priceData
                ]);
                
                return [
                    'id' => $slot->id,
                    'name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'duration_hours' => $slot->duration_hours,
                    'total_price' => $priceData['total_price'],
                    'price_per_night' => $priceData['price_per_night'],
                    'nights' => $priceData['nights']
                ];
            });
    }
    
    /**
     * Find possible consecutive slot combinations
     */
    public function findConsecutiveSlotCombinations(string $date): array
    {
        $availableSlots = $this->getAvailableDayUseSlots($date)->sortBy('start_time')->values();
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
                    break;
                }
            }
        }
        
        return $combinations;
    }
    
    /**
     * Check if day-use slot IDs are consecutive
     */
    private function areDayUseSlotIdsConsecutive(array $slotIds): bool
    {
        if (count($slotIds) <= 1) {
            return true;
        }

        $slots = $this->chalet->timeSlots()
            ->whereIn('id', $slotIds)
            ->where('is_overnight', false)
            ->orderBy('start_time')
            ->get();

        if ($slots->count() !== count($slotIds)) {
            \Log::info('Checker: Some slots not found', ['requested_slots' => $slotIds, 'found_slots' => $slots->pluck('id')->toArray()]);
            return false; // Some slots don't exist or are overnight
        }

        // Check if each slot's end time matches the next slot's start time
        for ($i = 0; $i < $slots->count() - 1; $i++) {
            $currentSlot = $slots[$i];
            $nextSlot = $slots[$i + 1];
            
            \Log::info('Checker: Checking consecutive slots', [
                'current_slot' => ['id' => $currentSlot->id, 'name' => $currentSlot->name, 'end_time' => $currentSlot->end_time],
                'next_slot' => ['id' => $nextSlot->id, 'name' => $nextSlot->name, 'start_time' => $nextSlot->start_time],
                'consecutive' => $currentSlot->end_time === $nextSlot->start_time
            ]);
            
            if ($currentSlot->end_time !== $nextSlot->start_time) {
                return false;
            }
        }

        return true;
    }
} 