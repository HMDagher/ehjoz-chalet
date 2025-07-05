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
        while ($currentDate < $end) {
            $date = $currentDate->format('Y-m-d');
            $isWeekend = in_array($currentDate->dayOfWeek, [5, 6, 0]); // Friday, Saturday, Sunday
            
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

        $availabilityChecker = new ChaletAvailabilityChecker($chalet);
        $unavailableDayUseDates = [];
        $unavailableOvernightDates = [];

        try {
            // First check for completely blocked dates (full day blocks)
            $fullyBlockedDates = $this->getFullyBlockedDates($chalet, $startDate, $endDate);
            
            // Add fully blocked dates to both arrays
            $unavailableDayUseDates = $fullyBlockedDates;
            $unavailableOvernightDates = $fullyBlockedDates;
            
            // Now check date-specific availability for each type
            $currentDate = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            while ($currentDate <= $end) {
                $dateStr = $currentDate->format('Y-m-d');
                
                // Skip dates we already know are fully blocked
                if (in_array($dateStr, $fullyBlockedDates)) {
                    $currentDate->addDay();
                    continue;
                }

                // Check day-use availability
                $dayUseSlots = $chalet->timeSlots()
                    ->where('is_active', true)
                    ->where('is_overnight', false)
                    ->get();
                
                if ($dayUseSlots->isEmpty()) {
                    // No day-use slots exist, mark as unavailable
                    $unavailableDayUseDates[] = $dateStr;
                    \Log::info('Date marked unavailable for day-use - no day-use slots exist', [
                        'date' => $dateStr
                    ]);
                } else {
                    // Count how many slots are available
                    $availableDayUseSlotCount = 0;
                    $totalDayUseSlotCount = $dayUseSlots->count();
                    
                    foreach ($dayUseSlots as $slot) {
                        if ($availabilityChecker->isDayUseSlotAvailable($dateStr, $slot->id)) {
                            $availableDayUseSlotCount++;
                        }
                    }
                    
                    // Only mark date unavailable for day-use if ALL slots are blocked
                    if ($availableDayUseSlotCount == 0) {
                        $unavailableDayUseDates[] = $dateStr;
                        \Log::info('Date marked unavailable for day-use - all day-use slots blocked', [
                            'date' => $dateStr,
                            'total_day_use_slots' => $totalDayUseSlotCount
                        ]);
                    }
                }

                // Check overnight availability
                $overnightSlots = $chalet->timeSlots()
                    ->where('is_active', true)
                    ->where('is_overnight', true)
                    ->get();
                
                if ($overnightSlots->isEmpty()) {
                    // No overnight slots exist, mark as unavailable
                    $unavailableOvernightDates[] = $dateStr;
                    \Log::info('Date marked unavailable for overnight - no overnight slots exist', [
                        'date' => $dateStr
                    ]);
                } else {
                    // Count how many slots are available
                    $availableOvernightSlotCount = 0;
                    $totalOvernightSlotCount = $overnightSlots->count();
                    
                    // Check if ANY overnight slot is available
                    foreach ($overnightSlots as $slot) {
                        $nextDate = $currentDate->copy()->addDay()->format('Y-m-d');
                        if ($availabilityChecker->isOvernightSlotAvailable($dateStr, $nextDate, $slot->id)) {
                            $availableOvernightSlotCount++;
                        }
                    }
                    
                    // Only mark date unavailable for overnight if ALL slots are blocked
                    if ($availableOvernightSlotCount == 0) {
                        $unavailableOvernightDates[] = $dateStr;
                        \Log::info('Date marked unavailable for overnight - all overnight slots blocked', [
                            'date' => $dateStr,
                            'total_overnight_slots' => $totalOvernightSlotCount
                        ]);
                    }
                }

                $currentDate->addDay();
            }

            // Ensure no duplicates in the arrays
            $unavailableDayUseDates = array_unique($unavailableDayUseDates);
            $unavailableOvernightDates = array_unique($unavailableOvernightDates);

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
        $fullyBlockedDates = [];
        
        try {
            // Get all blocked dates without a specific time slot (fully blocked)
            $blockedDates = $chalet->blockedDates()
                ->whereNull('time_slot_id')
                ->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate)
                ->pluck('date')
                ->toArray();
                
            // Format dates to YYYY-MM-DD
            foreach ($blockedDates as $date) {
                $formattedDate = Carbon::parse($date)->format('Y-m-d');
                $fullyBlockedDates[] = $formattedDate;
            }
            
            \Log::info('Found fully blocked dates', [
                'chalet_id' => $chalet->id, 
                'fully_blocked_dates' => $fullyBlockedDates
            ]);
            
            return array_unique($fullyBlockedDates);
        } catch (\Exception $e) {
            \Log::error('Error getting fully blocked dates: ' . $e->getMessage());
            return [];
        }
    }
} 