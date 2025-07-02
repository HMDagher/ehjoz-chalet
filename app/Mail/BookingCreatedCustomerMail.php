<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Booking;

class BookingCreatedCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public $settings;

    public function __construct(Booking $booking, $settings = null)
    {
        $this->booking = $booking;
        $this->settings = $settings;
    }

    public function build()
    {
        return $this->subject('Your Booking Confirmation - Payment Required')
            ->view('emails.booking-created-customer');
    }
} 