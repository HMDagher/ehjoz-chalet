<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Chalet;
use App\Models\ChaletTimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BookingService
{
    private AvailabilityService $availabilityService;
    private PricingCalculator $pricingCalculator;
    
    public function __construct(
        AvailabilityService $availabilityService,
        PricingCalculator $pricingCalculator
    ) {
        $this->availabilityService = $availabilityService;
        $this->pricingCalculator = $pricingCalculator;
    }

    /**
     * Create a new booking
     * 
     * @param array $bookingData
     * @return array
     */
    public function createBooking(array $bookingData): array
    {
        try {
            // Validate booking request
            $validation = $this->validateBookingRequest($bookingData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'errors' => $validation['errors'],
                    'booking' => null
                ];
            }

            $data = $validation['data'];

            // Use database transaction to ensure consistency
            $result = DB::transaction(function () use ($data) {
                return $this->processBookingCreation($data);
            });

            // Clear relevant caches
            $this->clearBookingRelatedCaches($data['chalet_id'], $data['start_date'], $data['end_date']);

            return $result;

        } catch (\Exception $e) {
            Log::error('Booking creation failed', [
                'booking_data' => $bookingData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'errors' => ['Booking system temporarily unavailable. Please try again.'],
                'booking' => null
            ];
        }
    }

    /**
     * Process booking creation within database transaction
     * 
     * @param array $data
     * @return array
     */
    private function processBookingCreation(array $data): array
    {
        // Lock the chalet and date range to prevent race conditions
        $lockKey = $this->generateLockKey($data['chalet_id'], $data['start_date'], $data['end_date']);
        
        // Get a SELECT FOR UPDATE lock on the chalet
        $chalet = Chalet::where('id', $data['chalet_id'])->lockForUpdate()->first();
        
        if (!$chalet) {
            return [
                'success' => false,
                'errors' => ['Chalet not found'],
                'booking' => null
            ];
        }

        // Double-check availability (race condition protection)
        $availabilityCheck = $this->availabilityService->validateBookingRequest(
            $data['chalet_id'],
            $data['time_slot_ids'],
            $data['start_date'],
            $data['end_date'],
            $data['booking_type']
        );

        if (!$availabilityCheck['valid']) {
            return [
                'success' => false,
                'errors' => ['Slots are no longer available: ' . implode(', ', $availabilityCheck['errors'])],
                'booking' => null
            ];
        }

        // Calculate pricing
        $pricing = $this->pricingCalculator->calculateBookingPrice(
            $data['chalet_id'],
            $data['time_slot_ids'],
            $data['start_date'],
            $data['end_date'],
            $data['booking_type']
        );

        // Create booking datetime range
        $bookingDatetimes = $this->createBookingDatetimes($data, $availabilityCheck['availability_data']);

        // Create the booking record
        $booking = Booking::create([
            'chalet_id' => $data['chalet_id'],
            'user_id' => $data['user_id'],
            'start_date' => $bookingDatetimes['start_datetime'],
            'end_date' => $bookingDatetimes['end_datetime'],
            'total_amount' => $pricing['total_amount'],
            'booking_type' => $data['booking_type'],
            'status' => 'pending', // Admin approval required
            'booking_reference' => $this->generateBookingReference(),
            'internal_notes' => $data['notes'] ?? null,
            'total_guests' => $data['guest_count'] ?? 1,
            'special_requests' => $data['special_requests'] ?? null
        ]);

        // Attach time slots to booking
        $this->attachTimeSlotsToBooking($booking, $data['time_slot_ids'], $pricing);

        // Log booking creation
        Log::info('Booking created successfully', [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'chalet_id' => $data['chalet_id'],
            'user_id' => $data['user_id'],
            'total_amount' => $pricing['total_amount']
        ]);

        return [
            'success' => true,
            'booking' => $this->formatBookingResponse($booking, $pricing),
            'errors' => []
        ];
    }

    /**
     * Create booking start and end datetimes
     * 
     * @param array $data
     * @param array $availabilityData
     * @return array
     */
    private function createBookingDatetimes(array $data, array $availabilityData): array
    {
        $timeSlots = collect($availabilityData['available_slots'])
            ->whereIn('slot_id', $data['time_slot_ids'])
            ->sortBy('start_time');

        $firstSlot = $timeSlots->first();
        $lastSlot = $timeSlots->last();

        // For day-use: use the booking date with slot times
        if ($data['booking_type'] === 'day-use') {
            $startDatetime = TimeSlotHelper::convertToDateTime($data['start_date'], $firstSlot['start_time']);
            $endDatetime = TimeSlotHelper::getSlotEndDateTime(
                TimeSlotHelper::convertToDateTime($data['start_date'], $lastSlot['start_time']),
                $lastSlot['end_time']
            );
        } 
        // For overnight: use date range with slot times
        else {
            $startDatetime = TimeSlotHelper::convertToDateTime($data['start_date'], $firstSlot['start_time']);
            $endDatetime = TimeSlotHelper::convertToDateTime($data['end_date'], $firstSlot['end_time']);
        }

        return [
            'start_datetime' => $startDatetime->format('Y-m-d H:i:s'),
            'end_datetime' => $endDatetime->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Attach time slots to booking
     * 
     * @param Booking $booking
     * @param array $timeSlotIds
     * @param array $pricing
     */
    private function attachTimeSlotsToBooking(Booking $booking, array $timeSlotIds, array $pricing): void
    {
        // Simple pivot table relationship - no additional data stored
        $booking->timeSlots()->attach($timeSlotIds);
    }

    /**
     * Validate booking request
     * 
     * @param array $data
     * @return array
     */
    private function validateBookingRequest(array $data): array
    {
        $errors = [];
        $normalized = [];

        // Validate required fields
        $requiredFields = ['chalet_id', 'user_id', 'booking_type', 'start_date', 'time_slot_ids'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_array($data[$field]) && empty($data[$field]))) {
                $errors[] = "{$field} is required";
                continue;
            }
            $normalized[$field] = $data[$field];
        }

        // Validate user exists
        if (isset($normalized['user_id'])) {
            try {
                $user = User::findOrFail($normalized['user_id']);
                $normalized['user'] = $user;
            } catch (ModelNotFoundException $e) {
                $errors[] = 'User not found';
            }
        }

        // Validate chalet exists
        if (isset($normalized['chalet_id'])) {
            try {
                $chalet = Chalet::where('id', $normalized['chalet_id'])
                    ->where('status', \App\Enums\ChaletStatus::Active)
                    ->firstOrFail();
                $normalized['chalet'] = $chalet;
            } catch (ModelNotFoundException $e) {
                $errors[] = 'Chalet not found or not active';
            }
        }

        // Validate booking type
        if (isset($normalized['booking_type']) && !in_array($normalized['booking_type'], ['day-use', 'overnight'])) {
            $errors[] = 'booking_type must be either "day-use" or "overnight"';
        }

        // Validate dates
        $this->validateBookingDates($data, $normalized, $errors);

        // Validate time slots
        $this->validateTimeSlots($data, $normalized, $errors);

        // Validate optional fields
        $this->validateOptionalFields($data, $normalized, $errors);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $normalized
        ];
    }

    /**
     * Validate booking dates
     * 
     * @param array $data
     * @param array &$normalized
     * @param array &$errors
     */
    private function validateBookingDates(array $data, array &$normalized, array &$errors): void
    {
        // Validate start_date
        if (isset($data['start_date'])) {
            try {
                $startDate = Carbon::createFromFormat('Y-m-d', $data['start_date']);
                if ($startDate->isPast()) {
                    $errors[] = 'start_date cannot be in the past';
                } else {
                    $normalized['start_date'] = $data['start_date'];
                }
            } catch (\Exception $e) {
                $errors[] = 'start_date must be in format Y-m-d';
            }
        }

        // Validate end_date for overnight bookings
        if (isset($normalized['booking_type']) && $normalized['booking_type'] === 'overnight') {
            if (empty($data['end_date'])) {
                $errors[] = 'end_date is required for overnight bookings';
            } else {
                try {
                    $endDate = Carbon::createFromFormat('Y-m-d', $data['end_date']);
                    if (isset($normalized['start_date']) && $endDate->lt(Carbon::createFromFormat('Y-m-d', $normalized['start_date']))) {
                        $errors[] = 'end_date must be after or equal to start_date';
                    } else {
                        $normalized['end_date'] = $data['end_date'];
                    }
                } catch (\Exception $e) {
                    $errors[] = 'end_date must be in format Y-m-d';
                }
            }
        } else {
            $normalized['end_date'] = $data['end_date'] ?? null;
        }
    }

    /**
     * Validate time slots
     * 
     * @param array $data
     * @param array &$normalized
     * @param array &$errors
     */
    private function validateTimeSlots(array $data, array &$normalized, array &$errors): void
    {
        if (isset($data['time_slot_ids']) && is_array($data['time_slot_ids'])) {
            // Validate slot IDs are integers
            $slotIds = array_filter($data['time_slot_ids'], 'is_numeric');
            if (count($slotIds) !== count($data['time_slot_ids'])) {
                $errors[] = 'All time_slot_ids must be valid integers';
                return;
            }

            // Validate slots exist and belong to the chalet
            if (isset($normalized['chalet_id'])) {
                $existingSlots = ChaletTimeSlot::where('chalet_id', $normalized['chalet_id'])
                    ->whereIn('id', $slotIds)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();

                $missingSlots = array_diff($slotIds, $existingSlots);
                if (!empty($missingSlots)) {
                    $errors[] = 'Time slots not found or not active: ' . implode(', ', $missingSlots);
                }
            }

            $normalized['time_slot_ids'] = array_map('intval', $slotIds);
        }
    }

    /**
     * Validate optional fields
     * 
     * @param array $data
     * @param array &$normalized
     * @param array &$errors
     */
    private function validateOptionalFields(array $data, array &$normalized, array &$errors): void
    {
        // Guest count
        if (isset($data['guest_count'])) {
            if (!is_numeric($data['guest_count']) || $data['guest_count'] < 1) {
                $errors[] = 'guest_count must be a positive integer';
            } else {
                $normalized['guest_count'] = (int) $data['guest_count'];
            }
        }

        // Notes
        if (isset($data['notes'])) {
            $normalized['notes'] = trim($data['notes']);
        }

        // Special requests
        if (isset($data['special_requests'])) {
            $normalized['special_requests'] = trim($data['special_requests']);
        }
    }

    /**
     * Generate unique booking reference
     * 
     * @return string
     */
    private function generateBookingReference(): string
    {
        $prefix = 'BK';
        $timestamp = Carbon::now()->format('ymdHis');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return $prefix . $timestamp . $random;
    }

    /**
     * Generate lock key for preventing race conditions
     * 
     * @param int $chaletId
     * @param string $startDate
     * @param string|null $endDate
     * @return string
     */
    private function generateLockKey(int $chaletId, string $startDate, ?string $endDate): string
    {
        return "booking_lock_{$chaletId}_{$startDate}_" . ($endDate ?? 'null');
    }

    /**
     * Format booking response
     * 
     * @param Booking $booking
     * @param array $pricing
     * @return array
     */
    private function formatBookingResponse(Booking $booking, array $pricing): array
    {
        return [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'status' => $booking->status,
            'chalet' => [
                'id' => $booking->chalet->id,
                'name' => $booking->chalet->name,
                'slug' => $booking->chalet->slug
            ],
            'user' => [
                'id' => $booking->user->id,
                'name' => $booking->user->name,
                'email' => $booking->user->email
            ],
            'booking_details' => [
                'start_date' => $booking->start_date,
                'end_date' => $booking->end_date,
                'booking_type' => $booking->booking_type,
                'guest_count' => $booking->total_guests,
                'notes' => $booking->internal_notes,
                'special_requests' => $booking->special_requests
            ],
            'time_slots' => $booking->timeSlots->map(function($slot) {
                return [
                    'slot_id' => $slot->id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'is_overnight' => $slot->is_overnight
                ];
            })->toArray(),
            'pricing' => $pricing,
            'created_at' => $booking->created_at,
            'updated_at' => $booking->updated_at
        ];
    }

    /**
     * Clear booking-related caches
     * 
     * @param int $chaletId
     * @param string $startDate
     * @param string|null $endDate
     */
    private function clearBookingRelatedCaches(int $chaletId, string $startDate, ?string $endDate): void
    {
        // Clear availability cache
        $dates = TimeSlotHelper::getDateRange($startDate, $endDate ?? $startDate);
        AvailabilityService::clearAvailabilityCache($chaletId, $dates);

        // Clear search cache
        ChaletSearchService::clearSearchCache($chaletId);
    }

    /**
     * Update booking status (for admin approval workflow)
     * 
     * @param int $bookingId
     * @param string $status
     * @param int $adminUserId
     * @param string|null $reason
     * @return array
     */
    public function updateBookingStatus(int $bookingId, string $status, int $adminUserId, ?string $reason = null): array
    {
        try {
            $booking = Booking::findOrFail($bookingId);
            
            // Validate status transition
            if (!$this->isValidStatusTransition($booking->status, $status)) {
                return [
                    'success' => false,
                    'errors' => ["Cannot change status from {$booking->status} to {$status}"],
                    'booking' => null
                ];
            }

            return DB::transaction(function () use ($booking, $status, $adminUserId, $reason) {
                $oldStatus = $booking->status;
                
                // Update booking status
                $booking->update([
                    'status' => $status,
                    'internal_notes' => $reason,
                    'updated_at' => Carbon::now()
                ]);

                // Log status change
                Log::info('Booking status updated', [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                    'admin_user_id' => $adminUserId,
                    'reason' => $reason
                ]);

                // If booking is cancelled or rejected, clear caches to make slots available again
                if (in_array($status, ['cancelled', 'rejected'])) {
                    $this->clearBookingRelatedCaches(
                        $booking->chalet_id,
                        Carbon::parse($booking->start_date)->format('Y-m-d'),
                        Carbon::parse($booking->end_date)->format('Y-m-d')
                    );
                }

                return [
                    'success' => true,
                    'booking' => [
                        'id' => $booking->id,
                        'booking_reference' => $booking->booking_reference,
                        'status' => $booking->status,

                        'status_updated_at' => $booking->updated_at
                    ],
                    'errors' => []
                ];
            });

        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'errors' => ['Booking not found'],
                'booking' => null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update booking status', [
                'booking_id' => $bookingId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'errors' => ['Failed to update booking status'],
                'booking' => null
            ];
        }
    }

    /**
     * Check if status transition is valid
     * 
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            'pending' => ['confirmed', 'cancelled', 'rejected'],
            'confirmed' => ['completed', 'cancelled'],
            'completed' => ['refunded'],
            'cancelled' => [],
            'rejected' => [],
            'refunded' => []
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Cancel a booking
     * 
     * @param int $bookingId
     * @param int $userId
     * @param string|null $reason
     * @return array
     */
    public function cancelBooking(int $bookingId, int $userId, ?string $reason = null): array
    {
        try {
            $booking = Booking::where('id', $bookingId)
                ->where('user_id', $userId)
                ->firstOrFail();

            // Check if booking can be cancelled
            if (!in_array($booking->status, ['pending', 'confirmed'])) {
                return [
                    'success' => false,
                    'errors' => ['Booking cannot be cancelled in its current status'],
                    'booking' => null
                ];
            }

            // Check cancellation policy (implement based on your business rules)
            $cancellationCheck = $this->checkCancellationPolicy($booking);
            if (!$cancellationCheck['allowed']) {
                return [
                    'success' => false,
                    'errors' => $cancellationCheck['reasons'],
                    'booking' => null
                ];
            }

            return $this->updateBookingStatus($bookingId, 'cancelled', $userId, $reason);

        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'errors' => ['Booking not found or you do not have permission to cancel it'],
                'booking' => null
            ];
        }
    }

    /**
     * Check cancellation policy
     * 
     * @param Booking $booking
     * @return array
     */
    private function checkCancellationPolicy(Booking $booking): array
    {
        $startDate = Carbon::parse($booking->start_date);
        $hoursUntilStart = Carbon::now()->diffInHours($startDate, false);

        // Example policy: Can cancel up to 24 hours before start
        $minimumCancellationHours = 24;

        if ($hoursUntilStart < $minimumCancellationHours) {
            return [
                'allowed' => false,
                'reasons' => ["Bookings can only be cancelled at least {$minimumCancellationHours} hours before the start time"]
            ];
        }

        return [
            'allowed' => true,
            'reasons' => []
        ];
    }

    /**
     * Get booking details
     * 
     * @param int $bookingId
     * @param int|null $userId
     * @return array
     */
    public function getBookingDetails(int $bookingId, ?int $userId = null): array
    {
        try {
            $query = Booking::with(['chalet', 'user', 'timeSlots']);
            
            // If user ID provided, ensure they can only see their own bookings
            if ($userId) {
                $query->where('user_id', $userId);
            }

            $booking = $query->findOrFail($bookingId);

            return [
                'success' => true,
                'booking' => [
                    'id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'status' => $booking->status,
                    'chalet' => [
                        'id' => $booking->chalet->id,
                        'name' => $booking->chalet->name,
                        'slug' => $booking->chalet->slug,
                        'address' => $booking->chalet->address,
                        'images' => $booking->chalet->getMedia('gallery')->map(function($media) {
                            return $media->getUrl();
                        })->toArray()
                    ],
                    'user' => [
                        'id' => $booking->user->id,
                        'name' => $booking->user->name,
                        'email' => $booking->user->email
                    ],
                    'booking_details' => [
                        'start_date' => $booking->start_date,
                        'end_date' => $booking->end_date,
                        'booking_type' => $booking->booking_type,
                        'total_amount' => $booking->total_amount,
                        'guest_count' => $booking->total_guests,
                        'notes' => $booking->internal_notes,
                        'special_requests' => $booking->special_requests
                    ],
                    'time_slots' => $booking->timeSlots->map(function($slot) {
                        return [
                            'slot_id' => $slot->id,
                            'start_time' => $slot->start_time,
                            'end_time' => $slot->end_time,
                            'is_overnight' => $slot->is_overnight
                        ];
                    })->toArray(),
                    'timestamps' => [
                        'created_at' => $booking->created_at,
                        'updated_at' => $booking->updated_at,
                        'status_updated_at' => $booking->status_updated_at
                    ]
                ]
            ];

        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'errors' => ['Booking not found'],
                'booking' => null
            ];
        }
    }

    /**
     * Get user's bookings with filters
     * 
     * @param int $userId
     * @param array $filters
     * @return array
     */
    public function getUserBookings(int $userId, array $filters = []): array
    {
        try {
            $query = Booking::with(['chalet', 'timeSlots'])
                ->where('user_id', $userId);

            // Apply filters
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['booking_type'])) {
                $query->where('booking_type', $filters['booking_type']);
            }

            if (isset($filters['date_from'])) {
                $query->where('start_date', '>=', $filters['date_from']);
            }

            if (isset($filters['date_to'])) {
                $query->where('start_date', '<=', $filters['date_to']);
            }

            // Order by most recent first
            $bookings = $query->orderBy('created_at', 'desc')->get();

            return [
                'success' => true,
                'bookings' => $bookings->map(function($booking) {
                    return [
                        'id' => $booking->id,
                        'booking_reference' => $booking->booking_reference,
                        'status' => $booking->status,
                        'chalet_name' => $booking->chalet->name,
                        'start_date' => $booking->start_date,
                        'end_date' => $booking->end_date,
                        'booking_type' => $booking->booking_type,
                        'total_amount' => $booking->total_amount,
                        'created_at' => $booking->created_at
                    ];
                })->toArray(),
                'total_count' => $bookings->count()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get user bookings', [
                'user_id' => $userId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'errors' => ['Failed to retrieve bookings'],
                'bookings' => []
            ];
        }
    }
}