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
} 