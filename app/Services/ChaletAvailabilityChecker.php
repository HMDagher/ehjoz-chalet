<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chalet;
use App\Models\ChaletTimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ChaletAvailabilityChecker
{
    public Chalet $chalet;

    public function __construct(Chalet $chalet)
    {
        $this->chalet = $chalet;
    }

    /**
     * Helper: Check if two time ranges overlap with a grace period (in minutes)
     */
    private function timeRangesOverlapWithGrace($start1, $end1, $start2, $end2, $graceMinutes = 15): bool
    {
        $toMinutes = function($time) {
            [$h, $m, $s] = array_pad(explode(':', $time), 3, 0);
            return ((int)$h) * 60 + (int)$m;
        };
        
        $s1 = $toMinutes($start1);
        $e1 = $toMinutes($end1);
        $s2 = $toMinutes($start2);
        $e2 = $toMinutes($end2);
        
        // Handle overnight slots that span midnight
        if ($e1 <= $s1) {
            $e1 += 24 * 60; // Add 24 hours to end time
        }
        if ($e2 <= $s2) {
            $e2 += 24 * 60; // Add 24 hours to end time
        }
        
        // Calculate overlap
        $overlapStart = max($s1, $s2);
        $overlapEnd = min($e1, $e2);
        $overlap = $overlapEnd - $overlapStart;
        
        \Log::info('Checker: timeRangesOverlapWithGrace calculation', [
            'start1' => $start1,
            'end1' => $end1,
            'start2' => $start2,
            'end2' => $end2,
            's1_minutes' => $s1,
            'e1_minutes' => $e1,
            's2_minutes' => $s2,
            'e2_minutes' => $e2,
            'overlap_start' => $overlapStart,
            'overlap_end' => $overlapEnd,
            'overlap_minutes' => $overlap,
            'grace_minutes' => $graceMinutes,
            'result' => $overlap > $graceMinutes
        ]);
        
        return $overlap > $graceMinutes;
    }

    /**
     * Alias for timeRangesOverlapWithGrace for backward compatibility
     */
    public function timeRangesOverlap($start1, $end1, $start2, $end2): bool
    {
        return $this->timeRangesOverlapWithGrace($start1, $end1, $start2, $end2, 0);
    }

    /**
     * Check if a day-use time slot is available on a specific date
     * - Slot must not be blocked
     * - Entire day must not be blocked
     * - Slot must not be booked
     * - No overlapping overnight booking or block
     */
    public function isDayUseSlotAvailable(string $date, int $timeSlotId): bool
    {
        $date = Carbon::parse($date)->format('Y-m-d');
        $slot = $this->chalet->timeSlots()->find($timeSlotId);
        if (!$slot || $slot->is_overnight) {
            \Log::info('Checker: Slot not found or is overnight', ['slot_id' => $timeSlotId, 'date' => $date]);
            return false;
        }
        
        \Log::info('Checker: Checking day-use slot availability', [
            'slot_id' => $timeSlotId, 
            'slot_name' => $slot->name,
            'date' => $date,
            'slot_time' => $slot->start_time . '-' . $slot->end_time
        ]);
        
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
        if (!isset($slot->available_days) || !is_array($slot->available_days) || !in_array($dayOfWeek, $slot->available_days)) {
            \Log::info('Checker: Slot not available on this day', ['slot_id' => $timeSlotId, 'date' => $date, 'dayOfWeek' => $dayOfWeek, 'available_days' => $slot->available_days]);
            return false;
        }
        // Entire day blocked
        $dayBlocked = $this->chalet->blockedDates()
            ->where('date', $date)
            ->whereNull('time_slot_id')
            ->exists();
        if ($dayBlocked) {
            \Log::info('Checker: Day is blocked', ['slot_id' => $timeSlotId, 'date' => $date]);
            return false;
        }
        // Slot blocked
        $slotBlocked = $this->chalet->blockedDates()
            ->where('date', $date)
            ->where('time_slot_id', $timeSlotId)
            ->exists();
        if ($slotBlocked) {
            \Log::info('Checker: Slot is blocked', ['slot_id' => $timeSlotId, 'date' => $date]);
            return false;
        }
        // Slot already booked
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
        // Check for overlaps with other blocked day-use slots
        $blockedDayUseSlots = $this->chalet->blockedDates()
            ->where('date', $date)
            ->whereNotNull('time_slot_id')
            ->with('timeSlot')
            ->get();
            
        foreach ($blockedDayUseSlots as $blockedDate) {
            $blockedSlot = $blockedDate->timeSlot;
            if (!$blockedSlot || $blockedSlot->id === $timeSlotId) {
                continue; // Skip if slot not found or it's the same slot we're checking
            }
            
            // Check for time overlap with blocked day-use slot
            if ($this->timeRangesOverlap($slot->start_time, $slot->end_time, $blockedSlot->start_time, $blockedSlot->end_time)) {
                \Log::info('Checker: Overlap with blocked day-use slot', [
                    'slot_id' => $timeSlotId,
                    'blocked_slot_id' => $blockedSlot->id,
                    'date' => $date,
                    'slot_time' => $slot->start_time . '-' . $slot->end_time,
                    'blocked_slot_time' => $blockedSlot->start_time . '-' . $blockedSlot->end_time
                ]);
                return false;
            }
        }

        // Check for blocked or booked overnight slots that overlap in time
        $overnightSlots = $this->chalet->timeSlots()->where('is_overnight', true)->get();
        foreach ($overnightSlots as $overnightSlot) {
            // For overnight slots, we need to check both the current date and the previous date
            // because overnight slots can start on the previous day and end on the current day
            
            // Check if overnight slot is blocked on the current date (starts on this date)
            $blockedOvernightCurrentDate = $this->chalet->blockedDates()
                ->where('date', $date)
                ->where('time_slot_id', $overnightSlot->id)
                ->exists();
            
            // Check if overnight slot is blocked on the previous date (started yesterday, ends today)
            $previousDate = Carbon::parse($date)->subDay()->format('Y-m-d');
            $blockedOvernightPreviousDate = $this->chalet->blockedDates()
                ->where('date', $previousDate)
                ->where('time_slot_id', $overnightSlot->id)
                ->exists();
            
            // If overnight slot is blocked on current date, check for overlap (no grace period for blocking)
            if ($blockedOvernightCurrentDate && $this->timeRangesOverlap($slot->start_time, $slot->end_time, $overnightSlot->start_time, $overnightSlot->end_time)) {
                \Log::info('Checker: Overlap with blocked overnight slot (current date)', [
                    'slot_id' => $timeSlotId, 
                    'overnight_slot_id' => $overnightSlot->id, 
                    'date' => $date,
                    'slot_time' => $slot->start_time . '-' . $slot->end_time,
                    'overnight_time' => $overnightSlot->start_time . '-' . $overnightSlot->end_time
                ]);
                return false;
            }
            
            // If overnight slot is blocked on previous date and extends to current date, check for overlap
            if ($blockedOvernightPreviousDate && $this->timeRangesOverlap($slot->start_time, $slot->end_time, $overnightSlot->start_time, $overnightSlot->end_time)) {
                \Log::info('Checker: Overlap with blocked overnight slot (previous date extending to current)', [
                    'slot_id' => $timeSlotId, 
                    'overnight_slot_id' => $overnightSlot->id, 
                    'date' => $date,
                    'previous_date' => $previousDate,
                    'slot_time' => $slot->start_time . '-' . $slot->end_time,
                    'overnight_time' => $overnightSlot->start_time . '-' . $overnightSlot->end_time
                ]);
                return false;
            }
            
            // Check for booked overnight slots
            $overnightBooking = $this->chalet->bookings()
                ->where(function ($query) use ($date) {
                    $query->where('start_date', '<=', $date)
                          ->where('end_date', '>', $date);
                })
                ->whereHas('timeSlots', function ($query) use ($overnightSlot) {
                    $query->where('chalet_time_slots.id', $overnightSlot->id);
                })
                ->whereIn('status', ['confirmed', 'pending'])
                ->exists();
            if ($overnightBooking && $this->timeRangesOverlapWithGrace($slot->start_time, $slot->end_time, $overnightSlot->start_time, $overnightSlot->end_time, 15)) {
                \Log::info('Checker: Overlap with overnight booking', [
                    'slot_id' => $timeSlotId, 
                    'overnight_slot_id' => $overnightSlot->id, 
                    'date' => $date
                ]);
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
     * - Slot must not be blocked for any day in range
     * - Entire day must not be blocked for any day in range
     * - Slot must not be booked for any overlapping date range
     * - No overlapping day-use booking or block for any day in range
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
        // Check for any conflicting bookings in the date range
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
            ->whereIn('status', ['confirmed', 'pending'])
            ->exists();
        if ($conflictingBooking) {
            \Log::info('Checker: Overnight slot has conflicting booking', ['slot_id' => $timeSlotId, 'start' => $startDate, 'end' => $endDate]);
            return false;
        }
        // Check each day in the range for blocks and overlapping day-use bookings/blocks
        $currentDate = $start->copy();
        while ($currentDate < $end) {
            $currentDateStr = $currentDate->format('Y-m-d');
            
            \Log::info('Checker: Checking overnight availability for date', [
                'overnight_slot_id' => $timeSlotId,
                'current_date' => $currentDateStr,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            // Entire day blocked
            $dayBlocked = $this->chalet->blockedDates()
                ->where('date', $currentDateStr)
                ->whereNull('time_slot_id')
                ->exists();
            if ($dayBlocked) {
                \Log::info('Checker: Overnight slot day is blocked (entire day)', [
                    'slot_id' => $timeSlotId, 
                    'date' => $currentDateStr
                ]);
                return false;
            }
            // Slot blocked
            $slotBlocked = $this->chalet->blockedDates()
                ->where('date', $currentDateStr)
                ->where('time_slot_id', $timeSlotId)
                ->exists();
            if ($slotBlocked) {
                \Log::info('Checker: Overnight slot is specifically blocked', [
                    'slot_id' => $timeSlotId, 
                    'date' => $currentDateStr
                ]);
                return false;
            }
            // Check for overlapping day-use bookings and blocked day-use slots
            $dayUseSlots = $this->chalet->timeSlots()->where('is_overnight', false)->get();
            foreach ($dayUseSlots as $daySlot) {
                // Booked day-use slot
                $dayUseBooking = $this->chalet->bookings()
                    ->where(function ($query) use ($currentDateStr) {
                        $query->whereDate('start_date', '=', $currentDateStr)
                              ->whereDate('end_date', '=', $currentDateStr);
                    })
                    ->whereHas('timeSlots', function ($query) use ($daySlot) {
                        $query->where('chalet_time_slots.id', $daySlot->id);
                    })
                    ->whereIn('status', ['confirmed', 'pending'])
                    ->exists();
                if ($dayUseBooking && $this->timeRangesOverlapWithGrace($slot->start_time, $slot->end_time, $daySlot->start_time, $daySlot->end_time, 15)) {
                    \Log::info('Checker: Overlap with day-use booking', [
                        'overnight_slot_id' => $slot->id, 
                        'day_slot_id' => $daySlot->id, 
                        'date' => $currentDateStr
                    ]);
                    return false;
                }
                
                // Check for blocked day-use slots that overlap with overnight slot
                $daySlotBlocked = $this->chalet->blockedDates()
                    ->where('date', $currentDateStr)
                    ->where('time_slot_id', $daySlot->id)
                    ->exists();
                
                \Log::info('Checker: Checking day-use slot for overlap', [
                    'overnight_slot_id' => $slot->id,
                    'overnight_start_time' => $slot->start_time,
                    'overnight_end_time' => $slot->end_time,
                    'day_slot_id' => $daySlot->id,
                    'day_slot_name' => $daySlot->name,
                    'day_start_time' => $daySlot->start_time,
                    'day_end_time' => $daySlot->end_time,
                    'date' => $currentDateStr,
                    'day_slot_blocked' => $daySlotBlocked
                ]);
                
                if ($daySlotBlocked) {
                    $overlaps = $this->timeRangesOverlapWithGrace($slot->start_time, $slot->end_time, $daySlot->start_time, $daySlot->end_time, 15);
                    \Log::info('Checker: Time overlap check result', [
                        'overlaps' => $overlaps,
                        'overnight_slot_id' => $slot->id,
                        'day_slot_id' => $daySlot->id,
                        'day_slot_name' => $daySlot->name,
                        'date' => $currentDateStr
                    ]);
                    
                    if ($overlaps) {
                        \Log::info('Checker: Overlap with blocked day-use slot', [
                            'overnight_slot_id' => $slot->id, 
                            'day_slot_id' => $daySlot->id,
                            'day_slot_name' => $daySlot->name,
                            'date' => $currentDateStr
                        ]);
                        return false;
                    }
                }
            }
            
            // Special check for overnight slots: also check the next day for blocked day-use slots
            // This is because overnight slots span from current day 19:00 to next day 17:00
            if ($slot->is_overnight) {
                $nextDate = $currentDate->copy()->addDay()->format('Y-m-d');
                $nextDayDayUseSlots = $this->chalet->timeSlots()->where('is_overnight', false)->get();
                
                foreach ($nextDayDayUseSlots as $daySlot) {
                    // Check for blocked day-use slots on the next day
                    $nextDaySlotBlocked = $this->chalet->blockedDates()
                        ->where('date', $nextDate)
                        ->where('time_slot_id', $daySlot->id)
                        ->exists();
                    
                    \Log::info('Checker: Checking next day day-use slot for overlap', [
                        'overnight_slot_id' => $slot->id,
                        'overnight_start_time' => $slot->start_time,
                        'overnight_end_time' => $slot->end_time,
                        'day_slot_id' => $daySlot->id,
                        'day_slot_name' => $daySlot->name,
                        'day_start_time' => $daySlot->start_time,
                        'day_end_time' => $daySlot->end_time,
                        'current_date' => $currentDateStr,
                        'next_date' => $nextDate,
                        'next_day_slot_blocked' => $nextDaySlotBlocked
                    ]);
                    
                    if ($nextDaySlotBlocked) {
                        $overlaps = $this->timeRangesOverlapWithGrace($slot->start_time, $slot->end_time, $daySlot->start_time, $daySlot->end_time, 15);
                        \Log::info('Checker: Next day time overlap check result', [
                            'overlaps' => $overlaps,
                            'overnight_slot_id' => $slot->id,
                            'day_slot_id' => $daySlot->id,
                            'day_slot_name' => $daySlot->name,
                            'current_date' => $currentDateStr,
                            'next_date' => $nextDate
                        ]);
                        
                        if ($overlaps) {
                            \Log::info('Checker: Overlap with blocked day-use slot on next day', [
                                'overnight_slot_id' => $slot->id, 
                                'day_slot_id' => $daySlot->id,
                                'day_slot_name' => $daySlot->name,
                                'current_date' => $currentDateStr,
                                'next_date' => $nextDate
                            ]);
                            return false;
                        }
                    }
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

        // Use chalet's weekend_days, fallback to [5,6,0] if not set
        $weekendDays = $this->chalet->weekend_days;
        if (is_string($weekendDays)) {
            $weekendDays = json_decode($weekendDays, true);
        }
        $weekendDays = $weekendDays ?? [5, 6, 0];
        $isWeekend = in_array($date->dayOfWeek, $weekendDays);
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
     * Check if launch promotion is active (15% discount until July 10, 2025)
     */
    private function isLaunchPromoActive(): bool
    {
        $launchDiscountEndDate = '2025-07-15';
        return now()->lessThanOrEqualTo($launchDiscountEndDate);
    }
    
    /**
     * Apply launch discount if applicable
     */
    private function applyLaunchDiscount(float $price): array
    {
        $originalPrice = $price;
        $discount = 0;
        $discountPercentage = 0;
        
        if ($this->isLaunchPromoActive()) {
            $discountPercentage = 15;
            $discount = $originalPrice * ($discountPercentage / 100);
            $price = $originalPrice - $discount;
        }
        
        return [
            'final_price' => $price,
            'original_price' => $originalPrice,
            'discount' => $discount,
            'discount_percentage' => $discountPercentage,
            'has_discount' => $discount > 0
        ];
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
        
        // Apply launch discount
        $priceData = $this->applyLaunchDiscount($total);
        
        return $priceData['final_price'];
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
        
        // Apply launch discount
        $priceData = $this->applyLaunchDiscount($total);
        $finalTotal = $priceData['final_price'];
        
        \Log::info('Total overnight price calculated', [
            'subtotal' => $total,
            'final_total' => $finalTotal,
            'has_discount' => $priceData['has_discount'],
            'discount_amount' => $priceData['discount'],
            'nights' => $nights,
            'price_per_night' => $finalTotal / $nights,
            'night_prices' => $nightPrices
        ]);
        
        return [
            'total_price' => $finalTotal,
            'original_price' => $priceData['original_price'],
            'discount' => $priceData['discount'],
            'discount_percentage' => $priceData['discount_percentage'],
            'has_discount' => $priceData['has_discount'],
            'nights' => $nights,
            'price_per_night' => $finalTotal / $nights
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
                $priceData = $this->applyLaunchDiscount($price);
                
                return [
                    'id' => $slot->id,
                    'name' => $slot->name,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'duration_hours' => $slot->duration_hours,
                    'price' => $priceData['final_price'],
                    'original_price' => $priceData['has_discount'] ? $priceData['original_price'] : null,
                    'has_discount' => $priceData['has_discount'],
                    'discount_percentage' => $priceData['discount_percentage']
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
                    'nights' => $priceData['nights'],
                    'original_price' => $priceData['has_discount'] ? $priceData['original_price'] : null,
                    'discount' => $priceData['has_discount'] ? $priceData['discount'] : null,
                    'discount_percentage' => $priceData['has_discount'] ? $priceData['discount_percentage'] : null,
                    'has_discount' => $priceData['has_discount']
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
     * Check if day-use slot IDs are consecutive in time (not just by ID)
     */
    public function areDayUseSlotIdsConsecutive(array $slotIds): bool
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
            if ($currentSlot->end_time !== $nextSlot->start_time) {
                return false;
            }
        }

        return true;
    }
}