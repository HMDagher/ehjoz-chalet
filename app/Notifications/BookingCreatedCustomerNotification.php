<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Booking;

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
        // You can use plain text or simple HTML here
        return (new MailMessage)
            ->subject('Your Booking Confirmation - Payment Required')
            ->greeting('Booking Confirmation')
            ->line("Dear {$this->booking->user->name},")
            ->line("Thank you for your booking at {$this->booking->chalet->name}!")
            ->line("Booking Reference: {$this->booking->booking_reference}")
            ->line("Your booking has been received and is currently pending. To secure your reservation, please complete the payment within 30 minutes. If payment is not received in this timeframe, your booking will be automatically deleted.")
            ->line("Total Amount Due: $" . number_format($this->booking->total_amount, 2))
            ->line("If you have any questions, reply to this email or contact us at " . ($this->settings->support_email ?? 'info@ehjozchalet.com') . ".")
            ->salutation('Thanks for choosing us!');
    }
}