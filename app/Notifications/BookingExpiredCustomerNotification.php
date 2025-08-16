<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingExpiredCustomerNotification extends Notification
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
            ->subject('Booking Expired - '.$this->booking->booking_reference)
            ->greeting('Hello '.$this->booking->user->name.'!')
            ->line('We regret to inform you that your booking has expired and been automatically cancelled.')
            ->line('**Booking Details:**')
            ->line('Booking Reference: **'.$this->booking->booking_reference.'**')
            ->line('Chalet: **'.optional($this->booking->chalet)->name.'**')
            ->line('Check-in: **'.$this->booking->start_date->format('M d, Y \a\t g:i A').'**')
            ->line('Check-out: **'.$this->booking->end_date->format('M d, Y \a\t g:i A').'**')
            ->line('Total Amount: **$'.number_format($this->booking->total_amount, 2).'**')
            ->line('')
            ->line('**Why was my booking cancelled?**')
            ->line('Bookings must be paid within 30 minutes of creation to secure your reservation. Since payment was not completed within this timeframe, the booking has been automatically cancelled to make the dates available for other guests.')
            ->line('')
            ->line('**What can I do now?**')
            ->line('• You can make a new booking for the same dates if they are still available')
            ->line('• Contact us if you experienced technical difficulties during payment')
            ->line('• We recommend completing payment immediately after booking to avoid cancellation')
            ->line('')
            ->action('Make New Booking', url('/chalets/'.optional($this->booking->chalet)->slug))
            ->line('')
            ->line('If you have any questions or need assistance, please don\'t hesitate to contact us:')
            ->line('📞 Phone: '.$this->settings->support_phone)
            ->line('📧 Email: '.$this->settings->support_email)
            ->line('')
            ->line('We apologize for any inconvenience and look forward to hosting you soon!')
            ->salutation('Best regards, The Ehjoz Chalet Team');
    }
}
