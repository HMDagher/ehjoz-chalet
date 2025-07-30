<?php

namespace App\Observers;

use App\Models\Booking;
use App\Notifications\BookingCreatedCustomerNotification;

use App\Models\User;
use App\Notifications\BookingCreatedAdminNotification;

class BookingObserver
{
    public function created(Booking $booking)
    {
        // Clear availability cache when new booking is created
        $this->clearChaletAvailabilityCache($booking->chalet_id);
        
        // You may want to load settings from cache or config
        $settings = (object) [
            'support_phone' => config('mail.support_phone', '+961 70 123456'),
            'support_email' => config('mail.support_email', 'info@ehjozchalet.com'),
        ];
        $booking->user->notify(new BookingCreatedCustomerNotification($booking, $settings));

        // Notify all admins
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new BookingCreatedAdminNotification($booking, $settings));
        }
    }

    public function updated(Booking $booking)
    {
        // Clear availability cache when booking status changes
        if ($booking->isDirty('status')) {
            $this->clearChaletAvailabilityCache($booking->chalet_id);
        }
        
        // Only act if status changed to completed
        if ($booking->isDirty('status') && $booking->status->value === 'completed') {
            // Only send if the user is a customer
            if ($booking->user && $booking->user->hasRole('customer')) {
                // Generate a secure token
                $token = bin2hex(random_bytes(32));
                $expiresAt = now()->addDays(7);

                // Create the review record if not exists
                $review = $booking->review;
                if (!$review) {
                    $review = \App\Models\Review::create([
                        'booking_id' => $booking->id,
                        'user_id' => $booking->user_id,
                        'chalet_id' => $booking->chalet_id,
                        'review_token' => $token,
                        'review_token_expires_at' => $expiresAt,
                    ]);
                } else {
                    // If review exists but no token, update it
                    if (!$review->review_token) {
                        $review->review_token = $token;
                        $review->review_token_expires_at = $expiresAt;
                        $review->save();
                    }
                }

                // Generate the review link
                $reviewLink = route('review.submit', ['token' => $review->review_token]);

                // Send the notification
                $booking->user->notify(new \App\Notifications\ReviewInvitationNotification($review, $reviewLink));
            }
        }
    }

    public function deleted(Booking $booking)
    {
        // Clear availability cache when booking is deleted
        $this->clearChaletAvailabilityCache($booking->chalet_id);
    }

    /**
     * Clear availability cache for a specific chalet and proactively regenerate common ranges
     */
    private function clearChaletAvailabilityCache(int $chaletId): void
    {
        // Clear cache for both booking types without date ranges
        $bookingTypes = ['day-use', 'overnight'];
        
        $clearedCount = 0;
        
        $regeneratedCount = 0;
        foreach ($bookingTypes as $bookingType) {
            $cacheKey = "chalet_availability_{$chaletId}_{$bookingType}";
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
                $clearedCount++;
                $regeneratedCount++;
            }
        }
        
        \Log::info('Cleared and regenerated availability cache for chalet', [
            'chalet_id' => $chaletId,
            'reason' => 'booking_changed',
            'cleared_keys_count' => $clearedCount,
            'regenerated_keys_count' => $regeneratedCount
        ]);
    }

    /**
     * Proactively regenerate cache for a specific chalet and date range
     */
    private function regenerateAvailabilityCache(int $chaletId, string $bookingType, string $startDate, string $endDate): void
    {
        try {
            $chalet = \App\Models\Chalet::find($chaletId);
            if (!$chalet) {
                return;
            }

            $cacheKey = "chalet_unavailable_dates_{$chaletId}_{$bookingType}_{$startDate}_{$endDate}";
            
            // Regenerate cache in background
            dispatch(function () use ($chalet, $bookingType, $startDate, $endDate, $cacheKey) {
                $availabilityChecker = new \App\Services\ChaletAvailabilityChecker($chalet);
                
                $unavailableDayUseDates = [];
                $unavailableOvernightDates = [];
                $fullyBlockedDates = [];
                
                $currentDate = \Carbon\Carbon::parse($startDate);
                $endDateCarbon = \Carbon\Carbon::parse($endDate);
                
                while ($currentDate <= $endDateCarbon) {
                    $dateStr = $currentDate->format('Y-m-d');
                    
                    // Check day-use availability
                    $availableDayUseSlots = $availabilityChecker->getAvailableDayUseSlots($dateStr);
                    if ($availableDayUseSlots->isEmpty()) {
                        $unavailableDayUseDates[] = $dateStr;
                    }
                    
                    // Check overnight availability
                    $nextDay = $currentDate->copy()->addDay()->format('Y-m-d');
                    $availableOvernightSlots = $availabilityChecker->getAvailableOvernightSlots($dateStr, $nextDay);
                    if ($availableOvernightSlots->isEmpty()) {
                        $unavailableOvernightDates[] = $dateStr;
                    }
                    
                    // Check if entire day is blocked
                    if ($availableDayUseSlots->isEmpty() && $availableOvernightSlots->isEmpty()) {
                        $fullyBlockedDates[] = $dateStr;
                    }
                    
                    $currentDate->addDay();
                }

                $cacheData = [
                    'unavailable_day_use_dates' => $unavailableDayUseDates,
                    'unavailable_overnight_dates' => $unavailableOvernightDates,
                    'fully_blocked_dates' => $fullyBlockedDates,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ],
                    'generated_at' => now()->toISOString()
                ];

                Cache::put($cacheKey, $cacheData, now()->addYears(1));
                
                \Log::info('Proactively regenerated availability cache', [
                    'chalet_id' => $chalet->id,
                    'booking_type' => $bookingType,
                    'cache_key' => $cacheKey,
                    'date_range' => "{$startDate} to {$endDate}"
                ]);
            });
            
        } catch (\Exception $e) {
            \Log::error('Error regenerating availability cache', [
                'chalet_id' => $chaletId,
                'booking_type' => $bookingType,
                'error' => $e->getMessage()
            ]);
        }
    }
} 