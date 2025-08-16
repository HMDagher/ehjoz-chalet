<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingExpiredAdminNotification;
use App\Notifications\BookingExpiredCustomerNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeletePendingBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $cutoffTime = now()->subMinutes(30);

        // Get the bookings that will be deleted for logging
        $bookingsToDelete = Booking::where('status', BookingStatus::Pending)
            ->where('created_at', '<', $cutoffTime)
            ->with('timeSlots') // Load time slots for logging
            ->get();

        if ($bookingsToDelete->isNotEmpty()) {
            \Log::info('DeletePendingBookingsJob: Deleting expired pending bookings', [
                'count' => $bookingsToDelete->count(),
                'cutoff_time' => $cutoffTime->toDateTimeString(),
                'booking_ids' => $bookingsToDelete->pluck('id')->toArray(),
                'booking_references' => $bookingsToDelete->pluck('booking_reference')->toArray(),
                'chalet_ids' => $bookingsToDelete->pluck('chalet_id')->unique()->toArray(),
            ]);

            // Get settings for notifications
            $settings = (object) [
                'support_phone' => config('mail.support_phone', '+961 70 123456'),
                'support_email' => config('mail.support_email', 'info@ehjozchalet.com'),
            ];

            // Get all admins once for efficiency
            $admins = User::role('admin')->get();

            // Delete each booking individually to ensure proper cleanup
            $deletedCount = 0;
            $notificationsSent = 0;

            foreach ($bookingsToDelete as $booking) {
                try {
                    // Load necessary relationships for notifications
                    $booking->load(['user', 'chalet.owner']);

                    // Send notifications before deletion
                    try {
                        // Notify customer if they have customer role
                        if ($booking->user && $booking->user->hasRole('customer')) {
                            $booking->user->notify(new BookingExpiredCustomerNotification($booking, $settings));
                            \Log::info('DeletePendingBookingsJob: Customer notification sent', [
                                'booking_id' => $booking->id,
                                'customer_email' => $booking->user->email,
                            ]);
                        }

                        // Notify all admins
                        foreach ($admins as $admin) {
                            $admin->notify(new BookingExpiredAdminNotification($booking, $settings));
                        }

                        $notificationsSent++;

                        \Log::info('DeletePendingBookingsJob: Admin notifications sent', [
                            'booking_id' => $booking->id,
                            'admin_count' => $admins->count(),
                        ]);

                    } catch (\Exception $e) {
                        \Log::error('DeletePendingBookingsJob: Failed to send notifications', [
                            'booking_id' => $booking->id,
                            'booking_reference' => $booking->booking_reference,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with deletion even if notifications fail
                    }

                    // Detach time slots first (though cascade should handle this)
                    $booking->timeSlots()->detach();

                    // Delete the booking
                    $booking->delete();
                    $deletedCount++;

                    \Log::debug('DeletePendingBookingsJob: Deleted booking', [
                        'booking_id' => $booking->id,
                        'booking_reference' => $booking->booking_reference,
                        'chalet_id' => $booking->chalet_id,
                    ]);

                } catch (\Exception $e) {
                    \Log::error('DeletePendingBookingsJob: Failed to delete booking', [
                        'booking_id' => $booking->id,
                        'booking_reference' => $booking->booking_reference,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            \Log::info('DeletePendingBookingsJob: Completed deletion', [
                'deleted_count' => $deletedCount,
                'expected_count' => $bookingsToDelete->count(),
                'notifications_sent' => $notificationsSent,
                'admin_count' => $admins->count(),
            ]);
        } else {
            \Log::debug('DeletePendingBookingsJob: No expired pending bookings to delete');
        }
    }
}
