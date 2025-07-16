<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chalet;
use App\Services\ChaletAvailabilityChecker;
use App\Services\ChaletSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ChaletApiController extends Controller
{
    /**
     * Get available slots for a chalet on specific dates
     */
    public function getAvailability(Request $request, string $slug): JsonResponse
    {
        $chalet = Chalet::where('slug', $slug)->where('status', 'active')->first();
        
        if (!$chalet) {
            return response()->json(['error' => 'Chalet not found'], 404);
        }

        $bookingType = $request->get('booking_type', 'overnight');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if (!$startDate) {
            return response()->json(['error' => 'Start date is required'], 400);
        }

        $availabilityChecker = new ChaletAvailabilityChecker($chalet);
        $searchService = new ChaletSearchService();

        try {
            if ($bookingType === 'day-use') {
                $availableSlots = $availabilityChecker->getAvailableDayUseSlots($startDate);
                $combinations = $availabilityChecker->findConsecutiveSlotCombinations($startDate);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'slots' => $availableSlots->toArray(),
                        'combinations' => $combinations,
                        'booking_type' => 'day-use'
                    ]
                ]);
            } else {
                // For overnight, we need both start and end dates
                if (!$endDate) {
                    $endDate = Carbon::parse($startDate)->addDay()->format('Y-m-d');
                }
                
                \Log::info('Getting available overnight slots', [
                    'chalet_id' => $chalet->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]);
                
                // Get all time slots for debugging
                $allTimeSlots = $chalet->timeSlots()->get();
                \Log::info('All time slots for chalet', [
                    'chalet_id' => $chalet->id,
                    'slots_count' => $allTimeSlots->count(),
                    'slots' => $allTimeSlots->map(function($slot) {
                        return [
                            'id' => $slot->id,
                            'name' => $slot->name,
                            'is_overnight' => $slot->is_overnight,
                            'is_active' => $slot->is_active
                        ];
                    })->toArray()
                ]);
                
                $availableSlots = $availabilityChecker->getAvailableOvernightSlots($startDate, $endDate);
                
                \Log::info('Available overnight slots result', [
                    'count' => $availableSlots->count(),
                    'slots' => $availableSlots->toArray()
                ]);
                
                // Generate nightly breakdown for each available slot
                $nightlyBreakdown = [];
                if ($availableSlots->isNotEmpty()) {
                    $slot = $availableSlots->first();
                    \Log::info('Using first available slot for nightly breakdown', [
                        'slot' => $slot
                    ]);
                    $nightlyBreakdown = $this->generateNightlyBreakdown($chalet, $availabilityChecker, $startDate, $endDate, $slot['id']);
                } else {
                    \Log::warning('No available overnight slots found for nightly breakdown');
                }
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'slots' => $availableSlots->toArray(),
                        'booking_type' => 'overnight',
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'nightly_breakdown' => $nightlyBreakdown
                    ]
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error checking availability: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generate a breakdown of prices for each night in an overnight stay
     */
    private function generateNightlyBreakdown(Chalet $chalet, ChaletAvailabilityChecker $availabilityChecker, string $startDate, string $endDate, int $timeSlotId): array
    {
        \Log::info('generateNightlyBreakdown called', [
            'chalet_id' => $chalet->id,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'timeSlotId' => $timeSlotId
        ]);
        
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $breakdown = [];
        
        // Calculate price for each night
        $currentDate = $start->copy();
        $weekendDays = $chalet->weekend_days ?? [5, 6, 0];
        while ($currentDate < $end) {
            $date = $currentDate->format('Y-m-d');
            $isWeekend = in_array($currentDate->dayOfWeek, $weekendDays); // Chalet-specific weekend
            // Get the time slot
            $timeSlot = $chalet->timeSlots()->findOrFail($timeSlotId);
            \Log::info('Processing night', [
                'date' => $date,
                'isWeekend' => $isWeekend,
                'timeSlot' => $timeSlot->name
            ]);
            // Get base price (weekday/weekend)
            $basePrice = $isWeekend ? $timeSlot->weekend_price : $timeSlot->weekday_price;
            // Check for seasonal pricing adjustment
            $customPricing = $chalet->customPricing()
                ->where('time_slot_id', $timeSlotId)
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->where('is_active', true)
                ->latest('created_at')
                ->first();
            $adjustment = $customPricing ? $customPricing->custom_adjustment : 0;
            $customPricingName = $customPricing ? $customPricing->name : null;
            $finalPrice = $basePrice + $adjustment;
            \Log::info('Night price details', [
                'basePrice' => $basePrice,
                'hasCustomPricing' => $customPricing ? true : false,
                'customPricingName' => $customPricingName,
                'adjustment' => $adjustment,
                'finalPrice' => $finalPrice
            ]);
            $breakdown[] = [
                'date' => $date,
                'is_weekend' => $isWeekend,
                'base_price' => (float)$basePrice,
                'custom_adjustment' => (float)$adjustment,
                'custom_pricing_name' => $customPricingName,
                'final_price' => (float)$finalPrice
            ];
            $currentDate->addDay();
        }
        
        \Log::info('Nightly breakdown generated', [
            'nights_count' => count($breakdown),
            'breakdown' => $breakdown
        ]);
        
        return $breakdown;
    }

    /**
     * Calculate price for selected slots
     */
    public function calculatePrice(Request $request, string $slug): JsonResponse
    {
        $chalet = Chalet::where('slug', $slug)->where('status', 'active')->first();
        
        if (!$chalet) {
            return response()->json(['error' => 'Chalet not found'], 404);
        }

        $bookingType = $request->get('booking_type');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $slotIds = $request->get('slot_ids', []);

        if (!$bookingType || !$startDate) {
            return response()->json(['error' => 'Booking type and start date are required'], 400);
        }

        $availabilityChecker = new ChaletAvailabilityChecker($chalet);

        try {
            if ($bookingType === 'day-use') {
                if (empty($slotIds)) {
                    return response()->json(['error' => 'Slot IDs are required for day-use booking'], 400);
                }

                // Check if slots are consecutive
                if (!$availabilityChecker->areConsecutiveSlotsAvailable($startDate, $slotIds)) {
                    return response()->json(['error' => 'Selected slots are not available or not consecutive'], 400);
                }

                $totalPrice = $availabilityChecker->calculateConsecutiveSlotsPrice($startDate, $slotIds);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_price' => $totalPrice,
                        'booking_type' => 'day-use',
                        'date' => $startDate,
                        'slot_ids' => $slotIds
                    ]
                ]);
            } else {
                if (!$endDate) {
                    $endDate = Carbon::parse($startDate)->addDay()->format('Y-m-d');
                }

                if (empty($slotIds)) {
                    return response()->json(['error' => 'Slot ID is required for overnight booking'], 400);
                }

                $slotId = $slotIds[0]; // Overnight bookings use single slot
                
                if (!$availabilityChecker->isOvernightSlotAvailable($startDate, $endDate, $slotId)) {
                    return response()->json(['error' => 'Overnight slot is not available for selected dates'], 400);
                }

                $priceData = $availabilityChecker->calculateOvernightPrice($startDate, $endDate, $slotId);
                $nightlyBreakdown = $this->generateNightlyBreakdown($chalet, $availabilityChecker, $startDate, $endDate, $slotId);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_price' => $priceData['total_price'],
                        'price_per_night' => $priceData['price_per_night'],
                        'nights' => $priceData['nights'],
                        'booking_type' => 'overnight',
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'slot_id' => $slotId,
                        'nightly_breakdown' => $nightlyBreakdown
                    ]
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error calculating price: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get unavailable dates for a chalet (for datepicker pre-filtering)
     * Returns two separate arrays for day-use and overnight unavailable dates
     */
    public function getUnavailableDates(Request $request, string $slug): JsonResponse
    {
        $chalet = Chalet::where('slug', $slug)->where('status', 'active')->first();
        
        if (!$chalet) {
            return response()->json(['error' => 'Chalet not found'], 404);
        }

        $startDate = $request->get('start_date', now()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->addMonths(3)->format('Y-m-d')); // Default 3 months ahead

        try {
            // Get fully blocked dates (entire day blocked)
            $fullyBlockedDates = $this->getFullyBlockedDates($chalet, $startDate, $endDate);
            
            // Get slot-specific blocked dates
            $slotBlockedDates = $this->getSlotBlockedDates($chalet, $startDate, $endDate);
            
            // Get existing bookings that block dates
            $bookedDates = $this->getBookedDates($chalet, $startDate, $endDate);
            
            // Combine all blocked dates
            $unavailableDayUseDates = array_merge($fullyBlockedDates, $slotBlockedDates['day_use'], $bookedDates['day_use']);
            $unavailableOvernightDates = array_merge($fullyBlockedDates, $slotBlockedDates['overnight'], $bookedDates['overnight']);
            
            // Remove duplicates and sort
            $unavailableDayUseDates = array_unique($unavailableDayUseDates);
            $unavailableOvernightDates = array_unique($unavailableOvernightDates);
            sort($unavailableDayUseDates);
            sort($unavailableOvernightDates);

            return response()->json([
                'success' => true,
                'data' => [
                    'unavailable_day_use_dates' => $unavailableDayUseDates,
                    'unavailable_overnight_dates' => $unavailableOvernightDates,
                    'fully_blocked_dates' => $fullyBlockedDates,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error getting unavailable dates: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Get dates that are fully blocked (no slots available for either booking type)
     */
    private function getFullyBlockedDates(Chalet $chalet, string $startDate, string $endDate): array
    {
        try {
            // Get all blocked dates without a specific time slot (fully blocked)
            $blockedDates = $chalet->blockedDates()
                ->whereNull('time_slot_id')
                ->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate)
                ->pluck('date')
                ->map(function($date) {
                    return Carbon::parse($date)->format('Y-m-d');
                })
                ->toArray();
            
            return array_unique($blockedDates);
        } catch (\Exception $e) {
            \Log::error('Error getting fully blocked dates: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get slot-specific blocked dates optimized for performance
     */
    private function getSlotBlockedDates(Chalet $chalet, string $startDate, string $endDate): array
    {
        try {
            // Get all slot-specific blocked dates in one query
            $blockedDates = $chalet->blockedDates()
                ->whereNotNull('time_slot_id')
                ->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate)
                ->with('timeSlot')
                ->get();

            $dayUseBlocked = [];
            $overnightBlocked = [];
            $availabilityChecker = new ChaletAvailabilityChecker($chalet);

            // Get all active slots once
            $dayUseSlots = $chalet->timeSlots()->where('is_active', true)->where('is_overnight', false)->get();
            $overnightSlots = $chalet->timeSlots()->where('is_active', true)->where('is_overnight', true)->get();

            foreach ($blockedDates as $blockedDate) {
                $dateStr = Carbon::parse($blockedDate->date)->format('Y-m-d');
                $blockedSlot = $blockedDate->timeSlot;
                
                if (!$blockedSlot || !$blockedSlot->is_active) {
                    continue;
                }

                if ($blockedSlot->is_overnight) {
                    // Blocked overnight slot affects overnight bookings directly
                    $overnightBlocked[] = $dateStr;
                    
                    // Check if it affects day-use slots due to overlap
                    // Only block day-use if there's actual time overlap
                    $hasOverlapWithDayUse = false;
                    foreach ($dayUseSlots as $daySlot) {
                        if ($availabilityChecker->timeRangesOverlap(
                            $daySlot->start_time, $daySlot->end_time,
                            $blockedSlot->start_time, $blockedSlot->end_time
                        )) {
                            $hasOverlapWithDayUse = true;
                            break;
                        }
                    }
                    
                    // Only add to day-use blocked if there's actual overlap
                    if ($hasOverlapWithDayUse) {
                        $dayUseBlocked[] = $dateStr;
                    }
                } else {
                    // Blocked day-use slot - check if ALL day-use slots are blocked for this date
                    $blockedSlotsForDate = $blockedDates->where('date', $blockedDate->date)->pluck('time_slot_id')->toArray();
                    $dayUseSlotsForDate = $dayUseSlots->pluck('id')->toArray();
                    
                    // If all day-use slots are blocked, mark date as unavailable
                    if (count(array_intersect($blockedSlotsForDate, $dayUseSlotsForDate)) === count($dayUseSlotsForDate)) {
                        $dayUseBlocked[] = $dateStr;
                    }
                    
                    // Check if blocked day-use slot affects overnight slots due to overlap
                    foreach ($overnightSlots as $overnightSlot) {
                        if ($availabilityChecker->timeRangesOverlap(
                            $blockedSlot->start_time, $blockedSlot->end_time,
                            $overnightSlot->start_time, $overnightSlot->end_time
                        )) {
                            $overnightBlocked[] = $dateStr;
                            break; // One overlap is enough to block the date
                        }
                    }
                }
            }

            return [
                'day_use' => array_unique($dayUseBlocked),
                'overnight' => array_unique($overnightBlocked)
            ];
        } catch (\Exception $e) {
            \Log::error('Error getting slot blocked dates: ' . $e->getMessage());
            return ['day_use' => [], 'overnight' => []];
        }
    }

    /**
     * Get dates blocked by existing bookings
     */
    private function getBookedDates(Chalet $chalet, string $startDate, string $endDate): array
    {
        try {
            // Get all confirmed/pending bookings in the date range
            $bookings = $chalet->bookings()
                ->whereIn('status', ['confirmed', 'pending'])
                ->where(function($query) use ($startDate, $endDate) {
                    $query->where(function($q) use ($startDate, $endDate) {
                        // Booking starts within range
                        $q->whereBetween('start_date', [$startDate, $endDate]);
                    })->orWhere(function($q) use ($startDate, $endDate) {
                        // Booking ends within range
                        $q->whereBetween('end_date', [$startDate, $endDate]);
                    })->orWhere(function($q) use ($startDate, $endDate) {
                        // Booking spans the entire range
                        $q->where('start_date', '<=', $startDate)
                          ->where('end_date', '>=', $endDate);
                    });
                })
                ->with('timeSlots')
                ->get();

            $dayUseBooked = [];
            $overnightBooked = [];

            foreach ($bookings as $booking) {
                $start = Carbon::parse($booking->start_date);
                $end = Carbon::parse($booking->end_date);
                
                // Generate all dates in the booking range
                $current = $start->copy();
                while ($current <= $end) {
                    $dateStr = $current->format('Y-m-d');
                    
                    // Check if date is within our query range
                    if ($dateStr >= $startDate && $dateStr <= $endDate) {
                        foreach ($booking->timeSlots as $slot) {
                            if ($slot->is_overnight) {
                                $overnightBooked[] = $dateStr;
                            } else {
                                $dayUseBooked[] = $dateStr;
                            }
                        }
                    }
                    
                    $current->addDay();
                }
            }

            return [
                'day_use' => array_unique($dayUseBooked),
                'overnight' => array_unique($overnightBooked)
            ];
        } catch (\Exception $e) {
            \Log::error('Error getting booked dates: ' . $e->getMessage());
            return ['day_use' => [], 'overnight' => []];
        }
    }
} 