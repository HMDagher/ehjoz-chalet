<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Booking;
use App\Models\Payment;

class BookingPaymentMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public Payment $payment;
    public $owner;
    public $customer;
    public $settings;

    public function __construct(Booking $booking, Payment $payment, $owner, $customer, $settings = null)
    {
        $this->booking = $booking;
        $this->payment = $payment;
        $this->owner = $owner;
        $this->customer = $customer;
        $this->settings = $settings;
    }

    public function build()
    {
        $status = $this->payment->status;
        $subject = match($status) {
            'paid' => 'Payment Complete - Booking Confirmed',
            'partial' => 'Partial Payment Received - Booking Confirmed',
            'pending' => 'Payment Pending - Action Required',
            'refunded' => 'Payment Refunded - Booking Updated',
            default => 'Payment Update - Booking Status Changed'
        };

        return $this->subject($subject)
            ->view('emails.booking-payment');
    }
} 