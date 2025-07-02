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
                
                $availableSlots = $availabilityChecker->getAvailableOvernightSlots($startDate, $endDate);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'slots' => $availableSlots->toArray(),
                        'booking_type' => 'overnight',
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ]
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error checking availability: ' . $e->getMessage()], 500);
        }
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
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_price' => $priceData['total_price'],
                        'price_per_night' => $priceData['price_per_night'],
                        'nights' => $priceData['nights'],
                        'booking_type' => 'overnight',
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'slot_id' => $slotId
                    ]
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error calculating price: ' . $e->getMessage()], 500);
        }
    }
} 