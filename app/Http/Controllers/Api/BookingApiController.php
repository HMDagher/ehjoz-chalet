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
            // Validate availability
            if ($bookingType === 'day-use') {
                if (!$availabilityChecker->areMultipleSlotsAvailable($startDate, $slotIds)) {
                    return response()->json(['error' => 'Selected slots are not available'], 400);
                }
                $totalPrice = $availabilityChecker->calculateConsecutiveSlotsPrice($startDate, $slotIds);
                
                // For day-use, set start and end datetime based on selected slots
                $slots = $chalet->timeSlots()->whereIn('id', $slotIds)->orderBy('start_time')->get();
                $firstSlot = $slots->first();
                $lastSlot = $slots->last();
                
                $startDateTime = Carbon::parse($startDate)->setTimeFromTimeString($firstSlot->start_time);
                $endDateTime = Carbon::parse($startDate)->setTimeFromTimeString($lastSlot->end_time);
                
                $startDate = $startDateTime->format('Y-m-d H:i:s');
                $endDate = $endDateTime->format('Y-m-d H:i:s');
            } else {
                if (!$endDate) {
                    return response()->json(['error' => 'End date is required for overnight bookings'], 400);
                }
                
                $slotId = (int) $slotIds[0]; // Overnight bookings use single slot - convert to int
                if (!$availabilityChecker->isOvernightSlotAvailable($startDate, $endDate, $slotId)) {
                    return response()->json(['error' => 'Overnight slot is not available for selected dates'], 400);
                }
                
                $priceData = $availabilityChecker->calculateOvernightPrice($startDate, $endDate, $slotId);
                $totalPrice = $priceData['total_price'];
            }

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
                'base_slot_price' => $totalPrice,
                'seasonal_adjustment' => 0, // Will be calculated based on custom pricing
                'extra_hours' => 0,
                'extra_hours_amount' => 0,
                'platform_commission' => $totalPrice * 0.1, // 10% commission (stored for settlement calculations)
                'total_amount' => $totalPrice, // Total amount is the same as base price (commission is included)
                'status' => 'pending',
                'payment_status' => 'pending',
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
} 