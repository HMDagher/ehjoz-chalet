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
     * Clear availability cache for a specific chalet
     */
    private function clearChaletAvailabilityCache(int $chaletId): void
    {
        // Clear cache by flushing all entries that start with our chalet pattern
        // Since we can't easily pattern match in all cache drivers, we'll clear common patterns
        $bookingTypes = ['day-use', 'overnight'];
        $dateRanges = [
            // Common date ranges that might be cached
            [now()->format('Y-m-d'), now()->addMonths(3)->format('Y-m-d')],
            [now()->format('Y-m-d'), now()->addMonths(1)->format('Y-m-d')],
            [now()->format('Y-m-d'), now()->addDays(90)->format('Y-m-d')],
        ];
        
        $clearedCount = 0;
        foreach ($bookingTypes as $bookingType) {
            foreach ($dateRanges as [$startDate, $endDate]) {
                $cacheKey = "chalet_unavailable_dates_{$chaletId}_{$bookingType}_{$startDate}_{$endDate}";
                if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                    $clearedCount++;
                }
            }
        }
        
        \Log::info('Cleared availability cache for chalet', [
            'chalet_id' => $chaletId,
            'reason' => 'booking_changed',
            'cleared_keys_count' => $clearedCount
        ]);
    }
} 