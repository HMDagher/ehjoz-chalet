<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Chalet;
use App\Services\ChaletAvailabilityChecker;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BookingApiController extends Controller
{
    /**
     * Create a new booking
     */
    public function store(Request $request): JsonResponse
    {
        // Debug: Log the incoming request data
        \Log::info('Booking API request data:', $request->all());
        
        // Validate request
        $request->validate([
            'chalet_id' => 'required|exists:chalets,id',
            'booking_type' => 'required|in:day-use,overnight',
            'start_date' => 'required|date',
            'end_date' => 'required_if:booking_type,overnight|date|after_or_equal:start_date',
            'slot_ids' => 'required|array|min:1',
            'adults_count' => 'required|integer|min:1',
            'children_count' => 'required|integer|min:0',
        ]);

        $chalet = Chalet::findOrFail($request->chalet_id);
        $availabilityChecker = new ChaletAvailabilityChecker($chalet);

        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json(['error' => 'User must be logged in to make a booking'], 401);
        }

        $user = Auth::user();
        $bookingType = $request->booking_type;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $slotIds = $request->slot_ids;

        try {
            // Check if launch promotion is active (15% discount until July 10, 2025)
            $launchDiscountEndDate = '2025-07-10';
            $isLaunchPromoActive = now()->lessThanOrEqualTo($launchDiscountEndDate);
            $originalPrice = 0;
            $discountAmount = 0;
            $discountPercentage = $isLaunchPromoActive ? 15 : 0;
            
            // Validate availability
            if ($bookingType === 'day-use') {
                // Check if slots are consecutive
                if (!$availabilityChecker->areDayUseSlotIdsConsecutive($slotIds)) {
                    return response()->json(['error' => 'Selected slots are not consecutive in time. Please select consecutive slots.'], 400);
                }
                // Enhanced error reporting for slot availability
                foreach ($slotIds as $slotId) {
                    $slotIdInt = (int) $slotId;
                    $reason = $this->getDayUseSlotConflictReason($availabilityChecker, $startDate, $slotIdInt);
                    if ($reason !== null) {
                        return response()->json(['error' => $reason], 400);
                    }
                }
                // Calculate base slot price (sum of standard slot prices, no custom adjustment)
                $baseSlotPrice = 0;
                $seasonalAdjustment = 0;
                foreach ($slotIds as $slotId) {
                    $slot = $chalet->timeSlots()->find($slotId);
                    $date = Carbon::parse($startDate);
                    $isWeekend = in_array($date->dayOfWeek, [5, 6, 0]);
                    $basePrice = $isWeekend ? $slot->weekend_price : $slot->weekday_price;
                    $baseSlotPrice += $basePrice;
                    // Custom adjustment
                    $customPricing = $chalet->customPricing()
                        ->where('time_slot_id', $slotId)
                        ->where('start_date', '<=', $date->format('Y-m-d'))
                        ->where('end_date', '>=', $date->format('Y-m-d'))
                        ->where('is_active', true)
                        ->latest('created_at')
                        ->first();
                    $adjustment = $customPricing ? $customPricing->custom_adjustment : 0;
                    $seasonalAdjustment += $adjustment;
                }
                $totalBeforeDiscount = $baseSlotPrice + $seasonalAdjustment;
                // Apply launch discount
                if ($isLaunchPromoActive) {
                    $discountAmount = round($totalBeforeDiscount * ($discountPercentage / 100), 2);
                } else {
                    $discountAmount = 0;
                }
                $totalPrice = $totalBeforeDiscount - $discountAmount;
                
                // For day-use, set start and end datetime based on selected slots
                $slots = $chalet->timeSlots()->whereIn('id', $slotIds)->orderBy('start_time')->get();
                $firstSlot = $slots->first();
                $lastSlot = $slots->last();
                
                $startDateTime = Carbon::parse($startDate)->setTimeFromTimeString($firstSlot->start_time);
                $endDateTime = Carbon::parse($startDate)->setTimeFromTimeString($lastSlot->end_time);
                if ($lastSlot->end_time === '00:00:00') {
                    $endDateTime->addDay();
                }
                
                $startDate = $startDateTime->format('Y-m-d H:i:s');
                $endDate = $endDateTime->format('Y-m-d H:i:s');
            } else {
                if (!$endDate) {
                    return response()->json(['error' => 'End date is required for overnight bookings'], 400);
                }
                
                $slotId = (int) $slotIds[0]; // Overnight bookings use single slot - convert to int
                $reason = $this->getOvernightSlotConflictReason($availabilityChecker, $startDate, $endDate, $slotId);
                if ($reason !== null) {
                    return response()->json(['error' => $reason], 400);
                }
                
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);
                $baseSlotPrice = 0;
                $seasonalAdjustment = 0;
                $currentDate = $start->copy();
                while ($currentDate < $end) {
                    $slot = $chalet->timeSlots()->find($slotId);
                    $isWeekend = in_array($currentDate->dayOfWeek, [5, 6, 0]);
                    $basePrice = $isWeekend ? $slot->weekend_price : $slot->weekday_price;
                    $baseSlotPrice += $basePrice;
                    $customPricing = $chalet->customPricing()
                        ->where('time_slot_id', $slotId)
                        ->where('start_date', '<=', $currentDate->format('Y-m-d'))
                        ->where('end_date', '>=', $currentDate->format('Y-m-d'))
                        ->where('is_active', true)
                        ->latest('created_at')
                        ->first();
                    $adjustment = $customPricing ? $customPricing->custom_adjustment : 0;
                    $seasonalAdjustment += $adjustment;
                    $currentDate->addDay();
                }
                $totalBeforeDiscount = $baseSlotPrice + $seasonalAdjustment;
                if ($isLaunchPromoActive) {
                    $discountAmount = round($totalBeforeDiscount * ($discountPercentage / 100), 2);
                } else {
                    $discountAmount = 0;
                }
                $totalPrice = $totalBeforeDiscount - $discountAmount;
            }

            // Calculate commission and earnings
            $extraHoursAmount = 0; // Set to 0 for now, but use in formulas
            $platformCommission = round(($baseSlotPrice + $seasonalAdjustment + $extraHoursAmount) * 0.1, 2);
            $ownerEarning = $baseSlotPrice - $platformCommission;
            // Platform earning is what remains after paying the owner from the total amount
            $platformEarning = $totalPrice - $ownerEarning;
            // Create booking
            $booking = Booking::create([
                'chalet_id' => $chalet->id,
                'user_id' => $user->id,
                'booking_reference' => 'BKG-' . strtoupper(Str::random(8)),
                'start_date' => Carbon::parse($startDate),
                'end_date' => Carbon::parse($endDate),
                'booking_type' => $bookingType,
                'adults_count' => $request->adults_count,
                'children_count' => $request->children_count,
                'total_guests' => $request->adults_count + $request->children_count,
                'base_slot_price' => $baseSlotPrice,
                'seasonal_adjustment' => $seasonalAdjustment,
                'extra_hours' => 0,
                'extra_hours_amount' => $extraHoursAmount,
                'platform_commission' => $platformCommission,
                'discount_amount' => $discountAmount,
                'discount_percentage' => $discountPercentage,
                'discount_reason' => $isLaunchPromoActive ? 'Launch Promotion (15% off)' : null,
                'total_amount' => $totalPrice,
                'status' => 'pending',
                'payment_status' => 'pending',
                // Save earnings if columns exist
                'owner_earning' => $ownerEarning,
                'platform_earning' => $platformEarning,
            ]);

            // Attach time slots
            $booking->timeSlots()->sync($slotIds);

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'total_amount' => $booking->total_amount,
                    'original_price' => $baseSlotPrice,
                    'discount_amount' => $discountAmount,
                    'discount_percentage' => $discountPercentage,
                    'status' => $booking->status,
                    'confirmation_url' => route('booking-confirmation', $booking->booking_reference)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error creating booking: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Check if user is authenticated
     */
    public function checkAuth(): JsonResponse
    {
        return response()->json([
            'authenticated' => Auth::check(),
            'user' => Auth::check() ? Auth::user()->only(['id', 'name', 'email']) : null
        ]);
    }

    /**
     * Get user-friendly error message for day-use slot conflict
     */
    private function getDayUseSlotConflictReason(ChaletAvailabilityChecker $checker, string $date, int $slotId): ?string
    {
        $slot = $checker->chalet->timeSlots()->find($slotId);
        if (!$slot || $slot->is_overnight) {
            return 'Selected slot does not exist or is not a day-use slot.';
        }
        $dayOfWeek = strtolower(\Carbon\Carbon::parse($date)->format('l'));
        if (!isset($slot->available_days) || !is_array($slot->available_days) || !in_array($dayOfWeek, $slot->available_days)) {
            return 'Selected slot is not available on this day.';
        }
        $dayBlocked = $checker->chalet->blockedDates()->where('date', $date)->whereNull('time_slot_id')->exists();
        if ($dayBlocked) {
            return 'The entire day is blocked for external booking or maintenance.';
        }
        $slotBlocked = $checker->chalet->blockedDates()->where('date', $date)->where('time_slot_id', $slotId)->exists();
        if ($slotBlocked) {
            return 'This slot is blocked due to an external booking or maintenance.';
        }
        $alreadyBooked = $checker->chalet->bookings()
            ->where(function ($query) use ($date) {
                $query->where(function ($q) use ($date) {
                    $q->whereDate('start_date', '=', $date)
                      ->whereDate('end_date', '=', $date);
                });
            })
            ->whereHas('timeSlots', function ($query) use ($slotId) {
                $query->where('chalet_time_slots.id', $slotId);
            })
            ->whereIn('status', ['confirmed', 'pending'])
            ->exists();
        if ($alreadyBooked) {
            return 'This slot is already booked.';
        }
        $overnightSlots = $checker->chalet->timeSlots()->where('is_overnight', true)->get();
        foreach ($overnightSlots as $overnightSlot) {
            $blockedOvernight = $checker->chalet->blockedDates()->where('date', $date)->where('time_slot_id', $overnightSlot->id)->exists();
            if ($blockedOvernight && $checker->timeRangesOverlap($slot->start_time, $slot->end_time, $overnightSlot->start_time, $overnightSlot->end_time)) {
                return 'This slot overlaps with a blocked overnight slot.';
            }
            $overnightBooking = $checker->chalet->bookings()
                ->where(function ($query) use ($date) {
                    $query->where('start_date', '<=', $date)
                          ->where('end_date', '>', $date);
                })
                ->whereHas('timeSlots', function ($query) use ($overnightSlot) {
                    $query->where('chalet_time_slots.id', $overnightSlot->id);
                })
                ->whereIn('status', ['confirmed', 'pending'])
                ->exists();
            if ($overnightBooking && $checker->timeRangesOverlap($slot->start_time, $slot->end_time, $overnightSlot->start_time, $overnightSlot->end_time)) {
                return 'This slot overlaps with an existing overnight booking.';
            }
        }
        return null;
    }

    /**
     * Get user-friendly error message for overnight slot conflict
     */
    private function getOvernightSlotConflictReason(ChaletAvailabilityChecker $checker, string $startDate, string $endDate, int $slotId): ?string
    {
        $slot = $checker->chalet->timeSlots()->find($slotId);
        if (!$slot || !$slot->is_overnight) {
            return 'Selected slot does not exist or is not an overnight slot.';
        }
        $checkInDayOfWeek = strtolower(\Carbon\Carbon::parse($startDate)->format('l'));
        if (!isset($slot->available_days) || !is_array($slot->available_days) || !in_array($checkInDayOfWeek, $slot->available_days)) {
            return 'Selected overnight slot is not available on this check-in day.';
        }
        $conflictingBooking = $checker->chalet->bookings()
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->where('start_date', '<=', $startDate)
                      ->where('end_date', '>', $startDate);
                })->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->where('start_date', '<', $endDate)
                      ->where('end_date', '>=', $endDate);
                })->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->where('start_date', '>=', $startDate)
                      ->where('end_date', '<', $endDate);
                });
            })
            ->whereIn('status', ['confirmed', 'pending'])
            ->exists();
        if ($conflictingBooking) {
            return 'This overnight slot is already booked for the selected date range.';
        }
        $currentDate = \Carbon\Carbon::parse($startDate)->copy();
        $end = \Carbon\Carbon::parse($endDate);
        while ($currentDate < $end) {
            $currentDateStr = $currentDate->format('Y-m-d');
            $dayBlocked = $checker->chalet->blockedDates()->where('date', $currentDateStr)->whereNull('time_slot_id')->exists();
            if ($dayBlocked) {
                return 'One or more days in the selected range are blocked for external booking or maintenance.';
            }
            $slotBlocked = $checker->chalet->blockedDates()->where('date', $currentDateStr)->where('time_slot_id', $slotId)->exists();
            if ($slotBlocked) {
                return 'This overnight slot is blocked for one or more days in the selected range.';
            }
            $dayUseSlots = $checker->chalet->timeSlots()->where('is_overnight', false)->get();
            foreach ($dayUseSlots as $daySlot) {
                $dayUseBooking = $checker->chalet->bookings()
                    ->where(function ($query) use ($currentDateStr) {
                        $query->whereDate('start_date', '=', $currentDateStr)
                              ->whereDate('end_date', '=', $currentDateStr);
                    })
                    ->whereHas('timeSlots', function ($query) use ($daySlot) {
                        $query->where('chalet_time_slots.id', $daySlot->id);
                    })
                    ->whereIn('status', ['confirmed', 'pending'])
                    ->exists();
                if ($dayUseBooking && $checker->timeRangesOverlap($slot->start_time, $slot->end_time, $daySlot->start_time, $daySlot->end_time)) {
                    return 'This overnight slot overlaps with an existing day-use booking.';
                }
                $daySlotBlocked = $checker->chalet->blockedDates()->where('date', $currentDateStr)->where('time_slot_id', $daySlot->id)->exists();
                if ($daySlotBlocked && $checker->timeRangesOverlap($slot->start_time, $slot->end_time, $daySlot->start_time, $daySlot->end_time)) {
                    return 'This overnight slot overlaps with a blocked day-use slot.';
                }
            }
            $currentDate->addDay();
        }
        return null;
    }

    /**
     * Get valid consecutive slot combinations for a chalet and date (day-use)
     */
    public function consecutiveSlotCombinations(Request $request): JsonResponse
    {
        $request->validate([
            'chalet_id' => 'required|exists:chalets,id',
            'date' => 'required|date',
        ]);
        $chalet = \App\Models\Chalet::findOrFail($request->chalet_id);
        $checker = new \App\Services\ChaletAvailabilityChecker($chalet);
        $combinations = $checker->findConsecutiveSlotCombinations($request->date);
        return response()->json([
            'success' => true,
            'data' => $combinations
        ]);
    }
} 