<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Booking;
use App\Enums\BookingStatus;
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
            ->get();
        
        if ($bookingsToDelete->isNotEmpty()) {
            \Log::info('DeletePendingBookingsJob: Deleting expired pending bookings', [
                'count' => $bookingsToDelete->count(),
                'cutoff_time' => $cutoffTime->toDateTimeString(),
                'booking_ids' => $bookingsToDelete->pluck('id')->toArray(),
                'booking_references' => $bookingsToDelete->pluck('booking_reference')->toArray()
            ]);
            
            // Delete the bookings
            $deletedCount = Booking::where('status', BookingStatus::Pending)
                ->where('created_at', '<', $cutoffTime)
                ->delete();
            
            \Log::info('DeletePendingBookingsJob: Completed deletion', [
                'deleted_count' => $deletedCount
            ]);
        } else {
            \Log::debug('DeletePendingBookingsJob: No expired pending bookings to delete');
        }
    }
}
