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
     */
    public function getUnavailableDates(Request $request, string $slug): JsonResponse
    {
        $chalet = Chalet::where('slug', $slug)->where('status', 'active')->first();
        
        if (!$chalet) {
            return response()->json(['error' => 'Chalet not found'], 404);
        }

        $bookingType = $request->get('booking_type', 'overnight');
        $startDate = $request->get('start_date', now()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->addMonths(3)->format('Y-m-d')); // Default 3 months ahead

        $availabilityChecker = new ChaletAvailabilityChecker($chalet);
        $unavailableDates = [];

        try {
            $currentDate = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            while ($currentDate <= $end) {
                $dateStr = $currentDate->format('Y-m-d');
                $hasAvailableSlot = false;

                if ($bookingType === 'day-use') {
                    // For day-use: check if ANY day-use slot is available
                    $dayUseSlots = $chalet->timeSlots()
                        ->where('is_active', true)
                        ->where('is_overnight', false)
                        ->get();
                    
                    if ($dayUseSlots->isEmpty()) {
                        // No day-use slots exist, mark as unavailable
                        $unavailableDates[] = $dateStr;
                        \Log::info('Date marked unavailable - no day-use slots exist', [
                            'date' => $dateStr,
                            'booking_type' => $bookingType
                        ]);
                    } else {
                        // Check if ANY day-use slot is available
                        foreach ($dayUseSlots as $slot) {
                            if ($availabilityChecker->isDayUseSlotAvailable($dateStr, $slot->id)) {
                                $hasAvailableSlot = true;
                                \Log::info('Day-use slot available', [
                                    'date' => $dateStr,
                                    'slot_id' => $slot->id,
                                    'slot_name' => $slot->name
                                ]);
                                break; // Found an available slot, no need to check others
                            }
                        }
                        
                        if (!$hasAvailableSlot) {
                            $unavailableDates[] = $dateStr;
                            \Log::info('Date marked unavailable - all day-use slots blocked', [
                                'date' => $dateStr,
                                'booking_type' => $bookingType,
                                'total_day_use_slots' => $dayUseSlots->count()
                            ]);
                        }
                    }
                } else {
                    // For overnight: check if ANY overnight slot is available
                    $overnightSlots = $chalet->timeSlots()
                        ->where('is_active', true)
                        ->where('is_overnight', true)
                        ->get();
                    
                    if ($overnightSlots->isEmpty()) {
                        // No overnight slots exist, mark as unavailable
                        $unavailableDates[] = $dateStr;
                        \Log::info('Date marked unavailable - no overnight slots exist', [
                            'date' => $dateStr,
                            'booking_type' => $bookingType
                        ]);
                    } else {
                        // Check if ANY overnight slot is available
                        foreach ($overnightSlots as $slot) {
                            $nextDate = $currentDate->copy()->addDay()->format('Y-m-d');
                            if ($availabilityChecker->isOvernightSlotAvailable($dateStr, $nextDate, $slot->id)) {
                                $hasAvailableSlot = true;
                                \Log::info('Overnight slot available', [
                                    'date' => $dateStr,
                                    'slot_id' => $slot->id,
                                    'slot_name' => $slot->name
                                ]);
                                break; // Found an available slot, no need to check others
                            }
                        }
                        
                        if (!$hasAvailableSlot) {
                            $unavailableDates[] = $dateStr;
                            \Log::info('Date marked unavailable - all overnight slots blocked', [
                                'date' => $dateStr,
                                'booking_type' => $bookingType,
                                'total_overnight_slots' => $overnightSlots->count()
                            ]);
                        }
                    }
                }

                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'unavailable_dates' => $unavailableDates,
                    'booking_type' => $bookingType,
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
} 