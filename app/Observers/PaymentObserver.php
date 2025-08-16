<?php

namespace App\Observers;

use App\Models\Payment;
use App\Notifications\BookingPaymentNotification;

class PaymentObserver
{
    public function created(Payment $payment)
    {
        $booking = $payment->booking;
        $owner = $booking->chalet->owner ?? $booking->chaletOwner;
        $customer = $booking->user;
        $settings = (object) [
            'support_phone' => config('mail.support_phone', '+961 70 123456'),
            'support_email' => config('mail.support_email', 'info@ehjozchalet.com'),
        ];

        // Send to customer
        $customer->notify(new BookingPaymentNotification($booking, $payment, $owner, $customer, $settings));

        // Send to owner if email exists and is different from customer
        if ($owner && $owner->email && $owner->email !== $customer->email) {
            $owner->notify(new BookingPaymentNotification($booking, $payment, $owner, $customer, $settings));
        }
    }
}
