<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Chalet;
use App\Http\Controllers\Api\ChaletApiController;
use Illuminate\Http\Request;

class TestUnavailableDates extends Command
{
    protected $signature = 'test:unavailable-dates 
                            {chalet_id : The chalet ID to test}
                            {booking_type : The booking type (day-use or overnight)}
                            {--start_date= : Start date (YYYY-MM-DD), defaults to today}
                            {--end_date= : End date (YYYY-MM-DD), defaults to 90 days from start}';

    protected $description = 'Test the getUnavailableDates functionality for a specific chalet';

    public function handle()
    {
        $chaletId = $this->argument('chalet_id');
        $bookingType = $this->argument('booking_type');
        $startDate = $this->option('start_date') ?: now()->format('Y-m-d');
        $endDate = $this->option('end_date') ?: now()->addDays(90)->format('Y-m-d');

        // Validate booking type
        if (!in_array($bookingType, ['day-use', 'overnight'])) {
            $this->error('Booking type must be either "day-use" or "overnight"');
            return 1;
        }

        // Find chalet
        $chalet = Chalet::find($chaletId);
        if (!$chalet) {
            $this->error("Chalet with ID {$chaletId} not found");
            return 1;
        }

        $this->info("Testing unavailable dates for Chalet: {$chalet->name} (ID: {$chaletId})");
        $this->info("Booking Type: {$bookingType}");
        $this->info("Date Range: {$startDate} to {$endDate}");
        $this->line('');

        // Show chalet time slots
        $this->info('=== CHALET TIME SLOTS ===');
        $timeSlots = $chalet->timeSlots()->where('is_active', true)->get();
        foreach ($timeSlots as $slot) {
            $type = $slot->is_overnight ? 'Overnight' : 'Day-use';
            $this->line("ID: {$slot->id} | {$slot->name} | {$slot->start_time}-{$slot->end_time} | Type: {$type}");
        }
        $this->line('');

        // Show blocked dates
        $this->info('=== BLOCKED DATES ===');
        $blockedDates = $chalet->blockedDates()
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->get();
        
        if ($blockedDates->isEmpty()) {
            $this->line('No blocked dates in the specified range');
        } else {
            foreach ($blockedDates as $blocked) {
                $slotInfo = $blocked->time_slot_id ? "Slot ID: {$blocked->time_slot_id}" : "ENTIRE DAY";
                $reason = $blocked->reason instanceof \BackedEnum ? $blocked->reason->value : $blocked->reason;
                $this->line("{$blocked->date->format('Y-m-d')} | {$slotInfo} | Reason: {$reason}");
            }
        }
        $this->line('');

        // Show existing bookings
        $this->info('=== EXISTING BOOKINGS ===');
        $bookings = $chalet->bookings()
            ->whereIn('status', ['confirmed', 'pending'])
            ->where(function($query) use ($startDate, $endDate) {
                $query->where(function($q) use ($startDate, $endDate) {
                    // Booking starts within the range
                    $q->whereBetween('start_date', [$startDate, $endDate]);
                })->orWhere(function($q) use ($startDate, $endDate) {
                    // Booking ends within the range
                    $q->whereBetween('end_date', [$startDate, $endDate]);
                })->orWhere(function($q) use ($startDate, $endDate) {
                    // Booking spans the entire range
                    $q->where('start_date', '<=', $startDate)
                      ->where('end_date', '>=', $endDate);
                })->orWhere(function($q) use ($startDate, $endDate) {
                    // For day-use bookings, also check if booking starts on any date in range
                    $q->whereDate('start_date', '>=', $startDate)
                      ->whereDate('start_date', '<=', $endDate);
                });
            })
            ->with('timeSlots')
            ->get();

        if ($bookings->isEmpty()) {
            $this->line('No bookings in the specified range');
        } else {
            foreach ($bookings as $booking) {
                $slotNames = $booking->timeSlots->pluck('name')->join(', ');
                $status = $booking->status instanceof \BackedEnum ? $booking->status->value : $booking->status;
                $this->line("{$booking->start_date->format('Y-m-d')} to {$booking->end_date->format('Y-m-d')} | {$booking->booking_type} | Slots: {$slotNames} | Status: {$status}");
            }
        }
        $this->line('');

        // Test the API controller
        $this->info('=== TESTING API CONTROLLER ===');
        $controller = new ChaletApiController();
        
        // Create a mock request
        $request = new Request([
            'booking_type' => $bookingType,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        try {
            $response = $controller->getUnavailableDates($request, $chalet->slug);
            $responseData = json_decode($response->getContent(), true);

            if ($responseData['success']) {
                $data = $responseData['data'];
                
                $this->info('API Response:');
                $this->line("Day-use unavailable dates: " . count($data['unavailable_day_use_dates']));
                $this->line("Overnight unavailable dates: " . count($data['unavailable_overnight_dates']));
                $this->line("Fully blocked dates: " . count($data['fully_blocked_dates']));
                $this->line('');

                // Show specific dates based on booking type
                $unavailableDates = $bookingType === 'day-use' 
                    ? $data['unavailable_day_use_dates'] 
                    : $data['unavailable_overnight_dates'];

                $this->info("=== UNAVAILABLE DATES FOR {$bookingType} ===");
                if (empty($unavailableDates)) {
                    $this->line('No unavailable dates found');
                } else {
                    $this->line('First 20 unavailable dates:');
                    foreach (array_slice($unavailableDates, 0, 20) as $date) {
                        $this->line("- {$date}");
                    }
                    if (count($unavailableDates) > 20) {
                        $this->line("... and " . (count($unavailableDates) - 20) . " more dates");
                    }
                }

                $this->line('');
                $this->info('=== FULLY BLOCKED DATES ===');
                if (empty($data['fully_blocked_dates'])) {
                    $this->line('No fully blocked dates found');
                } else {
                    foreach ($data['fully_blocked_dates'] as $date) {
                        $this->line("- {$date}");
                    }
                }

            } else {
                $this->error('API Error: ' . $responseData['error']);
            }

        } catch (\Exception $e) {
            $this->error('Exception: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
        }

        return 0;
    }
}