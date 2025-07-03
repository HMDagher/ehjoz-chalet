<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Booking;
use App\Models\Payment;

class BookingPaymentNotification extends Notification
{
    use Queueable;

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

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $status = $this->payment->status;
        $subject = match($status) {
            'paid' => 'Payment Complete - Booking Confirmed',
            'partial' => 'Partial Payment Received - Booking Confirmed',
            'pending' => 'Payment Pending - Action Required',
            'refunded' => 'Payment Refunded - Booking Updated',
            default => 'Payment Update - Booking Status Changed'
        };

        $remaining = $this->booking->remaining_payment ?? ($this->booking->total_amount - $this->payment->amount);
        $lines = [
            "Booking Reference: {$this->booking->booking_reference}",
            "Total Amount: $" . number_format($this->booking->total_amount, 2),
            "Total Paid: $" . number_format($this->payment->amount, 2),
        ];
        if ($remaining > 0.01) {
            $lines[] = "Remaining Payment: $" . number_format($remaining, 2);
        }
        return (new MailMessage)
            ->subject($subject)
            ->greeting('Payment Update')
            ->line("Dear {$notifiable->name},")
            ->lines($lines)
            ->line("If you have any questions, reply to this email or contact us at " . ($this->settings->support_email ?? 'info@ehjozchalet.com') . ".")
            ->salutation('Thank you!');
    }
}