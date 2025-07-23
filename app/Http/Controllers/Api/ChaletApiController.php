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
     * This method properly handles time slot overlaps and cross-day effects
     */
    public function getUnavailableDates(Request $request, string $slug): JsonResponse
    {
        $chalet = Chalet::where('slug', $slug)->where('status', 'active')->first();
        
        if (!$chalet) {
            return response()->json(['error' => 'Chalet not found'], 404);
        }

        $startDate = $request->get('start_date', now()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->addMonths(3)->format('Y-m-d'));

        try {
            // Get all time slots for this chalet
            $allTimeSlots = $chalet->timeSlots()->where('is_active', true)->get();
            
            // Get all unavailable slots (blocked + booked)
            $unavailableSlots = $this->getAllUnavailableSlots($chalet, $startDate, $endDate);
            
            // Calculate which dates are unavailable for each booking type
            $unavailableDates = $this->calculateUnavailableDatesWithOverlaps($chalet, $unavailableSlots, $allTimeSlots, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => [
                    'unavailable_day_use_dates' => $unavailableDates['day_use'],
                    'unavailable_overnight_dates' => $unavailableDates['overnight'],
                    'fully_blocked_dates' => $unavailableDates['fully_blocked'],
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting unavailable dates: ' . $e->getMessage(), [
                'chalet_slug' => $slug,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error getting unavailable dates: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Get all blocked dates from the database
     */
    private function getAllBlockedDates(Chalet $chalet, string $startDate, string $endDate): array
    {
        try {
            $blockedDates = $chalet->blockedDates()
                ->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate)
                ->get();

            $result = [];
            foreach ($blockedDates as $blockedDate) {
                $result[] = [
                    'date' => Carbon::parse($blockedDate->date)->format('Y-m-d'),
                    'time_slot_id' => $blockedDate->time_slot_id,
                    'type' => 'blocked'
                ];
            }

            return $result;
        } catch (\Exception $e) {
            \Log::error('Error getting blocked dates: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all booked slots from existing bookings
     */
    private function getAllBookedSlots(Chalet $chalet, string $startDate, string $endDate): array
    {
        try {
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

            $result = [];
            foreach ($bookings as $booking) {
                $start = Carbon::parse($booking->start_date);
                $end = Carbon::parse($booking->end_date);
                
                // For each day in the booking range
                $current = $start->copy();
                while ($current <= $end) {
                    $dateStr = $current->format('Y-m-d');
                    
                    // Check if date is within our query range
                    if ($dateStr >= $startDate && $dateStr <= $endDate) {
                        foreach ($booking->timeSlots as $slot) {
                            $result[] = [
                                'date' => $dateStr,
                                'time_slot_id' => $slot->id,
                                'type' => 'booked'
                            ];
                        }
                    }
                    
                    $current->addDay();
                }
            }

            return $result;
        } catch (\Exception $e) {
            \Log::error('Error getting booked slots: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate which dates are unavailable based on blocked/booked slots and overlaps
     */
    private function calculateUnavailableDates(Chalet $chalet, array $unavailableSlots, $allTimeSlots, string $startDate, string $endDate): array
    {
        $availabilityChecker = new ChaletAvailabilityChecker($chalet);
        $dayUseSlots = $allTimeSlots->where('is_overnight', false);
        $overnightSlots = $allTimeSlots->where('is_overnight', true);
        
        // Group unavailable slots by date
        $unavailableByDate = [];
        foreach ($unavailableSlots as $unavailableSlot) {
            $date = $unavailableSlot['date'];
            if (!isset($unavailableByDate[$date])) {
                $unavailableByDate[$date] = [];
            }
            $unavailableByDate[$date][] = $unavailableSlot;
        }

        $unavailableDayUseDates = [];
        $unavailableOvernightDates = [];
        $fullyBlockedDates = [];

        // Check each date in the range
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $unavailableForDate = $unavailableByDate[$dateStr] ?? [];
            
            // Check if entire day is blocked (no time_slot_id specified)
            $hasFullDayBlock = false;
            foreach ($unavailableForDate as $unavailable) {
                if ($unavailable['time_slot_id'] === null) {
                    $hasFullDayBlock = true;
                    break;
                }
            }
            
            if ($hasFullDayBlock) {
                // Entire day is blocked - affects all booking types
                $fullyBlockedDates[] = $dateStr;
                $unavailableDayUseDates[] = $dateStr;
                $unavailableOvernightDates[] = $dateStr;
                
                // Also check if overnight slots affect next day's day-use slots
                foreach ($overnightSlots as $overnightSlot) {
                    $nextDay = $current->copy()->addDay()->format('Y-m-d');
                    if ($nextDay <= $endDate) {
                        // Check if overnight slot overlaps with next day's day-use slots
                        foreach ($dayUseSlots as $daySlot) {
                            if ($this->slotsOverlapAcrossDays($overnightSlot, $daySlot, $dateStr, $nextDay)) {
                                if (!in_array($nextDay, $unavailableDayUseDates)) {
                                    $unavailableDayUseDates[] = $nextDay;
                                }
                            }
                        }
                    }
                }
            } else {
                // Check specific slot conflicts
                $blockedSlotIds = [];
                foreach ($unavailableForDate as $unavailable) {
                    if ($unavailable['time_slot_id'] !== null) {
                        $blockedSlotIds[] = $unavailable['time_slot_id'];
                    }
                }
                
                if (!empty($blockedSlotIds)) {
                    // Get the actual slot objects
                    $blockedSlots = $allTimeSlots->whereIn('id', $blockedSlotIds);
                    
                    // Calculate which slots are affected by overlaps
                    $affectedSlotIds = $this->calculateAffectedSlots($chalet, $blockedSlots, $allTimeSlots, $dateStr, $availabilityChecker);
                    
                    // Check if day-use is unavailable
                    $availableDayUseSlots = $dayUseSlots->whereNotIn('id', $affectedSlotIds);
                    if ($availableDayUseSlots->isEmpty()) {
                        $unavailableDayUseDates[] = $dateStr;
                    }
                    
                    // Check if overnight is unavailable
                    $availableOvernightSlots = $overnightSlots->whereNotIn('id', $affectedSlotIds);
                    if ($availableOvernightSlots->isEmpty()) {
                        $unavailableOvernightDates[] = $dateStr;
                    }
                    
                    // Check cross-day effects for overnight slots
                    foreach ($blockedSlots as $blockedSlot) {
                        if ($blockedSlot->is_overnight) {
                            $nextDay = $current->copy()->addDay()->format('Y-m-d');
                            if ($nextDay <= $endDate) {
                                foreach ($dayUseSlots as $daySlot) {
                                    if ($this->slotsOverlapAcrossDays($blockedSlot, $daySlot, $dateStr, $nextDay)) {
                                        if (!in_array($nextDay, $unavailableDayUseDates)) {
                                            $unavailableDayUseDates[] = $nextDay;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            $current->addDay();
        }

        return [
            'day_use' => array_unique($unavailableDayUseDates),
            'overnight' => array_unique($unavailableOvernightDates),
            'fully_blocked' => array_unique($fullyBlockedDates)
        ];
    }

    /**
     * Calculate which slots are affected by blocked slots due to time overlaps
     */
    private function calculateAffectedSlots(Chalet $chalet, $blockedSlots, $allTimeSlots, string $date, ChaletAvailabilityChecker $availabilityChecker): array
    {
        $affectedSlotIds = [];
        
        foreach ($blockedSlots as $blockedSlot) {
            // The blocked slot itself is affected
            $affectedSlotIds[] = $blockedSlot->id;
            
            // Check which other slots overlap with this blocked slot
            foreach ($allTimeSlots as $slot) {
                if ($slot->id === $blockedSlot->id) {
                    continue; // Skip the blocked slot itself
                }
                
                // Check for time overlap on the same day
                if ($availabilityChecker->timeRangesOverlap(
                    $blockedSlot->start_time, 
                    $blockedSlot->end_time,
                    $slot->start_time, 
                    $slot->end_time
                )) {
                    $affectedSlotIds[] = $slot->id;
                }
            }
        }
        
        return array_unique($affectedSlotIds);
    }

    /**
     * Check if an overnight slot overlaps with a day-use slot across days
     */
    private function slotsOverlapAcrossDays($overnightSlot, $daySlot, string $overnightDate, string $dayUseDate): bool
    {
        // For overnight slots that end the next day
        if (!$overnightSlot->is_overnight) {
            return false;
        }
        
        // Parse times
        $overnightStart = Carbon::parse($overnightDate . ' ' . $overnightSlot->start_time);
        $overnightEnd = Carbon::parse($dayUseDate . ' ' . $overnightSlot->end_time); // Next day
        
        $dayUseStart = Carbon::parse($dayUseDate . ' ' . $daySlot->start_time);
        $dayUseEnd = Carbon::parse($dayUseDate . ' ' . $daySlot->end_time);
        
        // Check if they overlap
        return $overnightStart < $dayUseEnd && $overnightEnd > $dayUseStart;
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

            // Group blocked dates by date for easier processing
            $blockedByDate = $blockedDates->groupBy('date');

            foreach ($blockedByDate as $date => $dateBlockedSlots) {
                $dateStr = Carbon::parse($date)->format('Y-m-d');
                
                $blockedDayUseSlots = [];
                $blockedOvernightSlots = [];
                
                // Separate blocked slots by type
                foreach ($dateBlockedSlots as $blockedDate) {
                    $blockedSlot = $blockedDate->timeSlot;
                    
                    if (!$blockedSlot || !$blockedSlot->is_active) {
                        continue;
                    }
                    
                    if ($blockedSlot->is_overnight) {
                        $blockedOvernightSlots[] = $blockedSlot;
                    } else {
                        $blockedDayUseSlots[] = $blockedSlot;
                    }
                }
                
                // Handle blocked overnight slots
                foreach ($blockedOvernightSlots as $blockedSlot) {
                    // Blocked overnight slot affects overnight bookings directly
                    $overnightBlocked[] = $dateStr;
                    
                    // Check if ALL day-use slots are affected by this blocked overnight slot
                    $availableDayUseSlots = [];
                    foreach ($dayUseSlots as $daySlot) {
                        $overlaps = $availabilityChecker->timeRangesOverlap(
                            $daySlot->start_time, $daySlot->end_time,
                            $blockedSlot->start_time, $blockedSlot->end_time
                        );
                        
                        // If this day-use slot doesn't overlap, it's still available
                        if (!$overlaps) {
                            $availableDayUseSlots[] = $daySlot->id;
                        }
                    }
                    
                    // Only block day-use for this date if NO day-use slots are available
                    if (count($availableDayUseSlots) === 0) {
                        $dayUseBlocked[] = $dateStr;
                    }
                }
                
                // Handle blocked day-use slots
                if (!empty($blockedDayUseSlots)) {
                    $blockedDayUseSlotIds = collect($blockedDayUseSlots)->pluck('id')->toArray();
                    $allDayUseSlotIds = $dayUseSlots->pluck('id')->toArray();
                    
                    // Only mark date as unavailable for day-use if ALL day-use slots are blocked
                    if (count(array_intersect($blockedDayUseSlotIds, $allDayUseSlotIds)) === count($allDayUseSlotIds)) {
                        $dayUseBlocked[] = $dateStr;
                    }
                    
                    // Check if blocked day-use slots affect overnight slots due to overlap
                    foreach ($blockedDayUseSlots as $blockedSlot) {
                        foreach ($overnightSlots as $overnightSlot) {
                            if ($availabilityChecker->timeRangesOverlap(
                                $blockedSlot->start_time, $blockedSlot->end_time,
                                $overnightSlot->start_time, $overnightSlot->end_time
                            )) {
                                $overnightBlocked[] = $dateStr;
                                break 2; // Break both loops - one overlap is enough
                            }
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

    /**
     * Get all unavailable slots from blocked dates and bookings
     */
    private function getAllUnavailableSlots(Chalet $chalet, string $startDate, string $endDate): array
    {
        $unavailableSlots = [];

        // Get blocked dates
        $blockedDates = $chalet->blockedDates()
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->get();

        foreach ($blockedDates as $blockedDate) {
            $unavailableSlots[] = [
                'date' => Carbon::parse($blockedDate->date)->format('Y-m-d'),
                'time_slot_id' => $blockedDate->time_slot_id, // null means entire day blocked
                'type' => 'blocked'
            ];
        }

        // Get booked slots from existing bookings
        $bookings = $chalet->bookings()
            ->whereIn('status', ['confirmed', 'pending'])
            ->where(function($query) use ($startDate, $endDate) {
                $query->where(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('start_date', [$startDate, $endDate]);
                })->orWhere(function($q) use ($startDate, $endDate) {
                    $q->whereBetween('end_date', [$startDate, $endDate]);
                })->orWhere(function($q) use ($startDate, $endDate) {
                    $q->where('start_date', '<=', $startDate)
                      ->where('end_date', '>=', $endDate);
                });
            })
            ->with('timeSlots')
            ->get();

        foreach ($bookings as $booking) {
            $start = Carbon::parse($booking->start_date);
            $end = Carbon::parse($booking->end_date);
            
            $current = $start->copy();
            while ($current <= $end) {
                $dateStr = $current->format('Y-m-d');
                
                if ($dateStr >= $startDate && $dateStr <= $endDate) {
                    foreach ($booking->timeSlots as $slot) {
                        $unavailableSlots[] = [
                            'date' => $dateStr,
                            'time_slot_id' => $slot->id,
                            'type' => 'booked'
                        ];
                    }
                }
                
                $current->addDay();
            }
        }

        return $unavailableSlots;
    }

    /**
     * Calculate unavailable dates considering all time slot overlaps
     */
    private function calculateUnavailableDatesWithOverlaps(Chalet $chalet, array $unavailableSlots, $allTimeSlots, string $startDate, string $endDate): array
    {
        $dayUseSlots = $allTimeSlots->where('is_overnight', false);
        $overnightSlots = $allTimeSlots->where('is_overnight', true);
        
        // Group unavailable slots by date
        $unavailableByDate = [];
        foreach ($unavailableSlots as $slot) {
            $date = $slot['date'];
            if (!isset($unavailableByDate[$date])) {
                $unavailableByDate[$date] = [];
            }
            $unavailableByDate[$date][] = $slot;
        }

        $unavailableDayUseDates = [];
        $unavailableOvernightDates = [];
        $fullyBlockedDates = [];

        // Process each date in the range
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $unavailableForDate = $unavailableByDate[$dateStr] ?? [];
            
            // Check for full day blocks (no time_slot_id)
            $hasFullDayBlock = false;
            foreach ($unavailableForDate as $unavailable) {
                if ($unavailable['time_slot_id'] === null) {
                    $hasFullDayBlock = true;
                    break;
                }
            }
            
            if ($hasFullDayBlock) {
                // Entire day is blocked
                $fullyBlockedDates[] = $dateStr;
                $unavailableDayUseDates[] = $dateStr;
                
                // Only add to overnight unavailable if there are overnight slots
                if ($overnightSlots->isNotEmpty()) {
                    $unavailableOvernightDates[] = $dateStr;
                }
                
                // Check cross-day effects for overnight slots
                $this->addCrossDayEffects($dateStr, $overnightSlots, $dayUseSlots, $unavailableDayUseDates, $endDate);
            } else {
                // Process specific slot conflicts
                $this->processSlotConflicts($chalet, $dateStr, $unavailableForDate, $allTimeSlots, $dayUseSlots, $overnightSlots, $unavailableDayUseDates, $unavailableOvernightDates, $endDate);
            }
            
            $current->addDay();
        }

        return [
            'day_use' => array_values(array_unique($unavailableDayUseDates)),
            'overnight' => array_values(array_unique($unavailableOvernightDates)),
            'fully_blocked' => array_values(array_unique($fullyBlockedDates))
        ];
    }

    /**
     * Process slot-specific conflicts and overlaps
     */
    private function processSlotConflicts(Chalet $chalet, string $dateStr, array $unavailableForDate, $allTimeSlots, $dayUseSlots, $overnightSlots, array &$unavailableDayUseDates, array &$unavailableOvernightDates, string $endDate): void
    {
        $blockedSlotIds = [];
        foreach ($unavailableForDate as $unavailable) {
            if ($unavailable['time_slot_id'] !== null) {
                $blockedSlotIds[] = $unavailable['time_slot_id'];
            }
        }
        
        if (empty($blockedSlotIds)) {
            return;
        }

        // Get blocked slot objects
        $blockedSlots = $allTimeSlots->whereIn('id', $blockedSlotIds);
        
        // Calculate all affected slots due to overlaps
        $affectedSlotIds = $this->calculateAllAffectedSlots($chalet, $blockedSlots, $allTimeSlots);
        
        // Check day-use availability
        $availableDayUseSlots = $dayUseSlots->whereNotIn('id', $affectedSlotIds);
        if ($availableDayUseSlots->isEmpty()) {
            $unavailableDayUseDates[] = $dateStr;
        }
        
        // Check overnight availability - only if there are overnight slots to begin with
        if ($overnightSlots->isNotEmpty()) {
            $availableOvernightSlots = $overnightSlots->whereNotIn('id', $affectedSlotIds);
            if ($availableOvernightSlots->isEmpty()) {
                $unavailableOvernightDates[] = $dateStr;
            }
        }
        
        // Check cross-day effects for blocked overnight slots
        foreach ($blockedSlots as $blockedSlot) {
            if ($blockedSlot->is_overnight) {
                $this->addCrossDayEffects($dateStr, collect([$blockedSlot]), $dayUseSlots, $unavailableDayUseDates, $endDate);
            }
        }
    }

    /**
     * Calculate all slots affected by blocked slots (including overlaps)
     */
    private function calculateAllAffectedSlots(Chalet $chalet, $blockedSlots, $allTimeSlots): array
    {
        $availabilityChecker = new ChaletAvailabilityChecker($chalet);
        $affectedSlotIds = [];
        
        foreach ($blockedSlots as $blockedSlot) {
            // The blocked slot itself is affected
            $affectedSlotIds[] = $blockedSlot->id;
            
            // Find overlapping slots
            foreach ($allTimeSlots as $slot) {
                if ($slot->id === $blockedSlot->id) {
                    continue;
                }
                
                // Check for time overlap
                if ($this->slotsOverlapSameDay($blockedSlot, $slot, $availabilityChecker)) {
                    $affectedSlotIds[] = $slot->id;
                }
            }
        }
        
        return array_unique($affectedSlotIds);
    }

    /**
     * Check if two slots overlap on the same day
     */
    private function slotsOverlapSameDay($slot1, $slot2, ChaletAvailabilityChecker $availabilityChecker): bool
    {
        // Check if either slot crosses midnight (end time < start time)
        $slot1CrossesMidnight = $this->timeToMinutes($slot1->end_time) <= $this->timeToMinutes($slot1->start_time);
        $slot2CrossesMidnight = $this->timeToMinutes($slot2->end_time) <= $this->timeToMinutes($slot2->start_time);
        
        // Handle slots that cross midnight (either overnight or day-use that crosses midnight)
        if ($slot1->is_overnight || $slot2->is_overnight || $slot1CrossesMidnight || $slot2CrossesMidnight) {
            // For slots that cross midnight, we need special handling
            return $this->overnightSlotsOverlap($slot1, $slot2);
        }
        
        // Regular same-day overlap check for slots that don't cross midnight
        return $availabilityChecker->timeRangesOverlap(
            $slot1->start_time, 
            $slot1->end_time,
            $slot2->start_time, 
            $slot2->end_time
        );
    }

    /**
     * Check if slots overlap when one or both cross midnight
     */
    private function overnightSlotsOverlap($slot1, $slot2): bool
    {
        // Convert times to minutes for easier comparison
        $slot1Start = $this->timeToMinutes($slot1->start_time);
        $slot1End = $this->timeToMinutes($slot1->end_time);
        $slot2Start = $this->timeToMinutes($slot2->start_time);
        $slot2End = $this->timeToMinutes($slot2->end_time);
        
        // Handle slots that cross midnight (either overnight or day-use that crosses midnight)
        // If end time <= start time, it means the slot crosses midnight
        if ($slot1End <= $slot1Start) {
            $slot1End += 24 * 60; // Add 24 hours
        }
        if ($slot2End <= $slot2Start) {
            $slot2End += 24 * 60; // Add 24 hours
        }
        
        // Check for overlap
        return $slot1Start < $slot2End && $slot1End > $slot2Start;
    }

    /**
     * Add cross-day effects for overnight slots
     */
    private function addCrossDayEffects(string $dateStr, $overnightSlots, $dayUseSlots, array &$unavailableDayUseDates, string $endDate): void
    {
        $nextDay = Carbon::parse($dateStr)->addDay()->format('Y-m-d');
        if ($nextDay > $endDate) {
            return;
        }
        
        foreach ($overnightSlots as $overnightSlot) {
            if (!$overnightSlot->is_overnight) {
                continue;
            }
            
            // Count how many day-use slots are affected by this overnight slot
            $affectedDayUseSlots = 0;
            $totalDayUseSlots = $dayUseSlots->count();
            
            foreach ($dayUseSlots as $daySlot) {
                if ($this->overnightAffectsDayUse($overnightSlot, $daySlot)) {
                    $affectedDayUseSlots++;
                }
            }
            
            // Only block the entire day if ALL day-use slots are affected
            if ($affectedDayUseSlots === $totalDayUseSlots && $totalDayUseSlots > 0) {
                if (!in_array($nextDay, $unavailableDayUseDates)) {
                    $unavailableDayUseDates[] = $nextDay;
                }
            }
        }
    }

    /**
     * Check if an overnight slot affects a day-use slot on the next day
     */
    private function overnightAffectsDayUse($overnightSlot, $daySlot): bool
    {
        // Overnight slot ends on next day, check if it overlaps with day-use start
        $overnightEndMinutes = $this->timeToMinutes($overnightSlot->end_time);
        $dayUseStartMinutes = $this->timeToMinutes($daySlot->start_time);
        
        // If overnight ends after day-use starts, there's an overlap
        return $overnightEndMinutes > $dayUseStartMinutes;
    }

    /**
     * Convert time string to minutes since midnight
     */
    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', $time);
        return (int)$parts[0] * 60 + (int)$parts[1];
    }
}