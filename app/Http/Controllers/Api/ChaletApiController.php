<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chalet;
use App\Services\AvailabilityService;
use App\Services\PricingCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChaletApiController extends Controller
{
    /**
     * Get available slots for a chalet on specific dates
     */
    public function getAvailability(Request $request, string $slug): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'booking_type' => 'required|in:day-use,overnight',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'nullable|date|after:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            // Find the chalet by slug
            $chalet = Chalet::where('slug', $slug)
                ->where('status', 'active')
                ->first();

            if (!$chalet) {
                return response()->json([
                    'success' => false,
                    'error' => 'Chalet not found or inactive'
                ], 404);
            }

            $bookingType = $request->input('booking_type');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // For day-use, end_date should be the same as start_date
            if ($bookingType === 'day-use') {
                $endDate = $startDate;
            }

            // For overnight, end_date is required
            if ($bookingType === 'overnight' && !$endDate) {
                return response()->json([
                    'success' => false,
                    'error' => 'End date is required for overnight bookings'
                ], 400);
            }

            // Validate date range for overnight bookings (max 30 days to prevent abuse)
            if ($bookingType === 'overnight') {
                $startDateObj = Carbon::parse($startDate);
                $endDateObj = Carbon::parse($endDate);
                $nights = $startDateObj->diffInDays($endDateObj);
                
                if ($nights > 30) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Overnight bookings cannot exceed 30 nights'
                    ], 400);
                }
                
                if ($nights < 1) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Check-out date must be at least one day after check-in date'
                    ], 400);
                }
            }

            // Check availability using AvailabilityService
            $availabilityService = new AvailabilityService();
            $availability = $availabilityService->checkAvailability(
                $chalet->id,
                $startDate,
                $endDate,
                $bookingType
            );

            if (!$availability['available']) {
                $errorMessage = 'No availability found for selected dates';
                if (!empty($availability['errors'])) {
                    $errorMessage .= ': ' . implode(', ', $availability['errors']);
                }
                
                return response()->json([
                    'success' => false,
                    'error' => $errorMessage,
                    'details' => $availability['errors'] ?? []
                ], 404);
            }

            // Check if we have available slots
            if (empty($availability['available_slots'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'No time slots available for selected dates'
                ], 404);
            }

            // Calculate pricing using PricingCalculator
            $pricingCalculator = new PricingCalculator();
            
            // Get time slot IDs from available slots
            $timeSlotIds = collect($availability['available_slots'])
                ->pluck('slot_id')
                ->toArray();

            try {
                $pricing = $pricingCalculator->calculateBookingPrice(
                    $chalet->id,
                    $timeSlotIds,
                    $startDate,
                    $endDate,
                    $bookingType
                );
            } catch (\Exception $e) {
                Log::warning('Pricing calculation failed, using fallback pricing', [
                    'chalet_id' => $chalet->id,
                    'time_slot_ids' => $timeSlotIds,
                    'error' => $e->getMessage()
                ]);
                
                // Use fallback pricing from availability data
                $pricing = $this->createFallbackPricing($availability, $startDate, $endDate, $bookingType);
            }

            // Format response based on booking type
            if ($bookingType === 'day-use') {
                $response = $this->formatDayUseResponse($availability, $pricing, $startDate);
            } else {
                $response = $this->formatOvernightResponse($availability, $pricing, $startDate, $endDate);
            }

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking chalet availability', [
                'slug' => $slug,
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while checking availability'
            ], 500);
        }
    }

    /**
     * Get unavailable dates for a chalet (for datepicker pre-filtering)
     */
    public function getUnavailableDates(Request $request, string $slug): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'booking_type' => 'required|in:day-use,overnight',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'error' => 'Validation failed', 'details' => $validator->errors()], 422);
            }

            // Find the chalet by slug
            $chalet = Chalet::with('timeSlots')->where('slug', $slug)->where('status', 'active')->first();
            if (! $chalet) {
                return response()->json(['success' => false, 'error' => 'Chalet not found or inactive'], 404);
            }

            $bookingType = $request->input('booking_type');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $startDateObj = Carbon::parse($startDate);
            $endDateObj = Carbon::parse($endDate);

            // Validate date range (max 90 days to prevent performance issues)
            $daysDiff = $startDateObj->diffInDays($endDateObj);
            if ($daysDiff > 90) {
                return response()->json(['success' => false, 'error' => 'Date range cannot exceed 90 days'], 400);
            }

            $availabilityService = new AvailabilityService();
            $unavailableDates = [];
            $fullyBlockedDates = [];

            // --- New, more robust logic for overnight bookings ---
            if ($bookingType === 'overnight') {
                $overnightSlot = $chalet->timeSlots->where('is_overnight', true)->where('is_active', true)->first();

                // If no overnight slot exists, all dates are unavailable
                if (! $overnightSlot) {
                    $current = $startDateObj->copy();
                    while ($current->lte($endDateObj)) {
                        $unavailableDates[] = $current->format('Y-m-d');
                        $current->addDay();
                    }
                } else {
                    // 1. Get fully blocked dates (no time slot specified)
                    $fullyBlocked = \App\Models\ChaletBlockedDate::where('chalet_id', $chalet->id)
                        ->whereNull('time_slot_id')
                        ->whereBetween('date', [$startDateObj, $endDateObj])
                        ->pluck('date')
                        ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'));

                    // 2. Get dates where the overnight slot is specifically blocked
                    $slotBlocked = \App\Models\ChaletBlockedDate::where('chalet_id', $chalet->id)
                        ->where('time_slot_id', $overnightSlot->id)
                        ->whereBetween('date', [$startDateObj, $endDateObj])
                        ->pluck('date')
                        ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'));

                    // 3. Get dates for nights that are already booked
                    $bookedNights = \App\Models\Booking::where('chalet_id', $chalet->id)
                        ->whereIn('status', ['confirmed', 'pending'])
                        ->whereHas('timeSlots', fn ($q) => $q->where('chalet_time_slot_id', $overnightSlot->id))
                        ->whereDate('start_date', '<', $endDateObj) // Bookings starting before the range ends
                        ->whereDate('end_date', '>', $startDateObj) // Bookings ending after the range starts
                        ->get()
                        ->flatMap(function ($booking) {
                            // Get all the dates for the nights the booking occupies
                            return \App\Services\TimeSlotHelper::getDateRange($booking->start_date, $booking->end_date);
                        });

                    $unavailableDates = $fullyBlocked->merge($slotBlocked)->merge($bookedNights)->unique()->sort()->values()->all();
                }
            } else {
                // --- Original logic for day-use (which is correct) ---
                $currentDate = $startDateObj->copy();
                while ($currentDate->lte($endDateObj)) {
                    $dateStr = $currentDate->format('Y-m-d');
                    $availability = $availabilityService->checkAvailability($chalet->id, $dateStr, $dateStr, $bookingType);

                    if (! $availability['available']) {
                        if (in_array('full_day_blocked', $availability['errors'] ?? [])) {
                            $fullyBlockedDates[] = $dateStr;
                        } else {
                            $unavailableDates[] = $dateStr;
                        }
                    }
                    $currentDate->addDay();
                }
            }

            // Separate unavailable dates by booking type for better organization
            $response = [
                'fully_blocked_dates' => $fullyBlockedDates,
                'unavailable_day_use_dates' => $bookingType === 'day-use' ? $unavailableDates : [],
                'unavailable_overnight_dates' => $bookingType === 'overnight' ? $unavailableDates : [],
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'total_days' => $daysDiff + 1,
                ],
                'summary' => [
                    'total_dates_checked' => $daysDiff + 1,
                    'fully_blocked_count' => count($fullyBlockedDates),
                    'unavailable_count' => count($unavailableDates),
                    'available_count' => ($daysDiff + 1) - count($fullyBlockedDates) - count($unavailableDates),
                ],
            ];

            return response()->json(['success' => true, 'data' => $response]);

        } catch (\Exception $e) {
            Log::error('Error getting unavailable dates', [
                'slug' => $slug,
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['success' => false, 'error' => 'An error occurred while getting unavailable dates'], 500);
        }
    }

    /**
     * Create fallback pricing when PricingCalculator fails
     */
    private function createFallbackPricing(array $availability, string $startDate, string $endDate, string $bookingType): array
    {
        $totalAmount = 0;
        $slotDetails = [];

        foreach ($availability['available_slots'] as $slot) {
            $basePrice = $slot['weekday_price'] ?? 0;
            $totalAmount += $basePrice;

            $slotDetails[] = [
                'slot_id' => $slot['slot_id'],
                'slot_name' => $slot['name'] ?? "{$slot['start_time']} - {$slot['end_time']}",
                'is_overnight' => $slot['is_overnight'] ?? false,
                'base_price' => $basePrice,
                'total_price' => $basePrice,
                'custom_pricing_applied' => false
            ];
        }

        if ($bookingType === 'overnight') {
            $nights = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
            return [
                'total_amount' => $totalAmount * $nights,
                'currency' => 'USD',
                'booking_type' => 'overnight',
                'nights_count' => $nights,
                'slot_details' => $slotDetails
            ];
        } else {
            return [
                'total_amount' => $totalAmount,
                'currency' => 'USD',
                'booking_type' => 'day-use',
                'slot_details' => $slotDetails
            ];
        }
    }

    /**
     * Format day-use availability response
     */
    private function formatDayUseResponse(array $availability, array $pricing, string $startDate): array
    {
        $slots = [];
        
        foreach ($availability['available_slots'] as $slot) {
            $slots[] = [
                'id' => $slot['slot_id'],
                'name' => $slot['slot_name'] ?? "{$slot['start_time']} - {$slot['end_time']}",
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'price' => $slot['weekday_price'] ?? 0,
                'weekend_price' => $slot['weekend_price'] ?? 0,
                'final_price' => $slot['final_price'] ?? $slot['weekday_price'] ?? 0,
                'has_discount' => false, // Will be calculated by PricingCalculator
                'original_price' => $slot['weekday_price'] ?? 0,
                'discount_percentage' => 0
            ];
        }

        // Apply pricing calculations
        if (!empty($pricing['slot_details'])) {
            foreach ($slots as &$slot) {
                $pricingSlot = collect($pricing['slot_details'])
                    ->firstWhere('slot_id', $slot['id']);
                
                if ($pricingSlot) {
                    $slot['final_price'] = $pricingSlot['total_price'] ?? $slot['final_price'];
                    $slot['has_discount'] = $pricingSlot['custom_pricing_applied'] ?? false;
                    $slot['original_price'] = $pricingSlot['base_price'] ?? $slot['original_price'];
                    $slot['discount_percentage'] = ($pricingSlot['custom_pricing_applied'] ?? false) ? 15 : 0; // Default discount
                }
            }
        }

        return [
            'start_date' => $startDate,
            'end_date' => $startDate,
            'booking_type' => 'day-use',
            'slots' => $slots,
            'total_price' => $pricing['total_amount'] ?? 0,
            'currency' => $pricing['currency'] ?? 'USD'
        ];
    }

    /**
     * Format overnight availability response
     */
    private function formatOvernightResponse(array $availability, array $pricing, string $startDate, string $endDate): array
    {
        $slots = [];
        
        foreach ($availability['available_slots'] as $slot) {
            $slots[] = [
                'id' => $slot['slot_id'],
                'name' => $slot['slot_name'] ?? "Overnight Stay",
                'is_overnight' => true,
                'price_per_night' => $slot['weekday_price'] ?? 0,
                'weekend_price' => $slot['weekend_price'] ?? 0,
                'total_price' => $pricing['total_amount'] ?? 0,
                'has_discount' => false, // Will be calculated by PricingCalculator
                'original_price' => $pricing['total_amount'] ?? 0,
                'discount_percentage' => 0
            ];
        }

        // Apply pricing calculations
        if (!empty($pricing['slot_details'])) {
            foreach ($slots as &$slot) {
                $pricingSlot = collect($pricing['slot_details'])
                    ->firstWhere('slot_id', $slot['id']);
                
                if ($pricingSlot) {
                    $slot['total_price'] = $pricingSlot['total_price'] ?? $slot['total_price'];
                    $slot['has_discount'] = $pricingSlot['custom_pricing_applied'] ?? false;
                    $slot['original_price'] = $pricingSlot['base_price'] ?? $slot['original_price'];
                    $slot['discount_percentage'] = ($pricingSlot['custom_pricing_applied'] ?? false) ? 15 : 0; // Default discount
                }
            }
        }

        // Safely build the nightly breakdown to ensure data integrity for the API response
        $nightlyBreakdown = [];
        $rawBreakdown = $pricing['slot_details'][0]['nightly_breakdown'] ?? [];

        if (is_array($rawBreakdown)) {
            foreach ($rawBreakdown as $night) {
                // Ensure each night has a date and a numeric price before adding it to the response
                if (isset($night['date'], $night['price']) && is_numeric($night['price'])) {
                    $nightlyBreakdown[] = [
                        'date' => $night['date'],
                        'price' => (float) $night['price'],
                    ];
                } else {
                    // Log a warning if data from the pricing service is malformed
                    Log::warning('Malformed nightly breakdown item from PricingCalculator was skipped.', [
                        'night_data' => $night,
                        'slot_id' => $availability['available_slots'][0]['slot_id'] ?? null,
                        'pricing_response' => $pricing,
                    ]);
                }
            }
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'booking_type' => 'overnight',
            'slots' => $slots,
            'total_price' => $pricing['total_amount'] ?? 0,
            'currency' => $pricing['currency'] ?? 'USD',
            'nights_count' => Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)),
            'nightly_breakdown' => $nightlyBreakdown, // Use the sanitized array
        ];
    }
}
