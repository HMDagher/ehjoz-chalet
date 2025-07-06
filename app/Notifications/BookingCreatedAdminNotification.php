<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Booking;

class BookingCreatedAdminNotification extends Notification
{
    use Queueable;

    public Booking $booking;
    public object $settings;

    public function __construct(Booking $booking, object $settings)
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
        return (new MailMessage)
            ->subject('New Booking Created')
            ->greeting('Hello Admin!')
            ->line('A new booking has been created.')
            ->line('Booking Reference: ' . $this->booking->booking_reference)
            ->line('Customer: ' . $this->booking->user->name)
            ->line('Chalet: ' . optional($this->booking->chalet)->name)
            ->line('Start Date: ' . $this->booking->start_date)
            ->line('End Date: ' . $this->booking->end_date);
    }
}
