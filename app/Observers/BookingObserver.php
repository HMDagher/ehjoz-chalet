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
} 