<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCreatedCustomerNotification extends Notification
{
    use Queueable;

    public Booking $booking;

    public $settings;

    public function __construct(Booking $booking, $settings = null)
    {
        $this->booking = $booking;
        $this->settings = $settings;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $minDeposit = $this->booking->total_amount * 0.5;
        $lines = [
            "Booking Reference: {$this->booking->booking_reference}",
            'Total Amount Due: $'.number_format($this->booking->total_amount, 2),
            'Minimum Deposit Required: $'.number_format($minDeposit, 2),
        ];
        if ($this->booking->payment && $this->booking->payment->amount < $this->booking->total_amount) {
            $remaining = $this->booking->remaining_payment ?? ($this->booking->total_amount - $this->booking->payment->amount);
            $lines[] = 'Remaining Payment: $'.number_format($remaining, 2);
        }

        return (new MailMessage)
            ->subject('Your Booking Confirmation - Payment Required')
            ->greeting('Booking Confirmation')
            ->line("Dear {$this->booking->user->name},")
            ->lines($lines)
            ->line("Thank you for your booking at {$this->booking->chalet->name}!")
            ->line('Your booking has been received and is currently pending. To secure your reservation, please complete the payment within 30 minutes. If payment is not received in this timeframe, your booking will be automatically deleted.')
            ->line('If you have any questions, reply to this email or contact us at '.($this->settings->support_email ?? 'info@ehjozchalet.com').'.')
            ->salutation('Thanks for choosing us!');
    }
}
