<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Booking;

class BookingExpiredAdminNotification extends Notification
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
            ->subject('Booking Expired & Deleted - ' . $this->booking->booking_reference)
            ->greeting('Hello Admin!')
            ->line('A pending booking has been automatically deleted due to non-payment within the 30-minute window.')
            ->line('**Booking Details:**')
            ->line('Booking Reference: **' . $this->booking->booking_reference . '**')
            ->line('Customer: **' . $this->booking->user->name . '** (' . $this->booking->user->email . ')')
            ->line('Customer Phone: **' . ($this->booking->user->phone ?? 'Not provided') . '**')
            ->line('Chalet: **' . optional($this->booking->chalet)->name . '**')
            ->line('Chalet Owner: **' . optional($this->booking->chalet->owner)->name . '**')
            ->line('Booking Type: **' . ucfirst(str_replace('-', ' ', $this->booking->booking_type)) . '**')
            ->line('Check-in: **' . $this->booking->start_date->format('M d, Y \a\t g:i A') . '**')
            ->line('Check-out: **' . $this->booking->end_date->format('M d, Y \a\t g:i A') . '**')
            ->line('Total Amount: **$' . number_format($this->booking->total_amount, 2) . '**')
            ->line('Adults: **' . $this->booking->adults_count . '**')
            ->line('Children: **' . $this->booking->children_count . '**')
            ->line('')
            ->line('**Timeline:**')
            ->line('Created: **' . $this->booking->created_at->format('M d, Y \a\t g:i A') . '**')
            ->line('Expired: **' . now()->format('M d, Y \a\t g:i A') . '**')
            ->line('Duration: **' . $this->booking->created_at->diffForHumans(now(), true) . '**')
            ->line('')
            ->line('**Action Taken:**')
            ->line('✅ Booking record deleted from database')
            ->line('✅ Time slots released and made available')
            ->line('✅ Customer notified via email')
            ->line('')
            ->line('**Follow-up Actions:**')
            ->line('• Monitor if customer contacts support about payment issues')
            ->line('• Check if customer makes a new booking for same dates')
            ->line('• Review payment gateway logs if customer reports technical issues')
            ->line('')
            ->action('View Chalet Details', url('/admin/chalets/' . optional($this->booking->chalet)->id))
            ->line('')
            ->line('This is an automated system notification.')
            ->salutation('Ehjoz Chalet Management System');
    }
}