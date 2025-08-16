<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReviewInvitationNotification extends Notification
{
    use Queueable;

    public Review $review;

    public string $reviewLink;

    public function __construct(Review $review, string $reviewLink)
    {
        $this->review = $review;
        $this->reviewLink = $reviewLink;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('We Value Your Feedback! Please Review Your Stay')
            ->greeting('Hello!')
            ->line('Thank you for staying with us. We would love to hear your feedback about your recent booking.')
            ->action('Leave a Review', $this->reviewLink)
            ->line('Your feedback helps us improve our service. Thank you!');
    }
}
