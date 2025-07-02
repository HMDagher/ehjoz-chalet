<?php

namespace App\Observers;

use App\Models\Booking;
use App\Mail\BookingCreatedCustomerMail;
use Illuminate\Support\Facades\Mail;

class BookingObserver
{
    public function created(Booking $booking)
    {
        // You may want to load settings from cache or config
        $settings = (object) [
            'support_phone' => config('mail.support_phone', '+961 70 123456'),
            'support_email' => config('mail.support_email', 'info@ehjozchalet.com'),
        ];
        Mail::to($booking->user->email)->send(new BookingCreatedCustomerMail($booking, $settings));
    }
} 