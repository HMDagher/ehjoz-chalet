<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Chalet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

use Illuminate\Support\Str;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $chalets = Chalet::with('timeSlots')->get();
        $customers = User::role('customer')->get();

        if ($chalets->isEmpty() || $customers->isEmpty()) {
            $this->command->info('No chalets or customers found to create bookings.');
            return;
        }

        $bookingCount = 0;

        foreach ($chalets as $chalet) {
            $numberOfBookings = rand(0, 5);
            for ($i = 0; $i < $numberOfBookings; $i++) {
                $timeSlots = $chalet->timeSlots;
                if ($timeSlots->isEmpty()) {
                    continue;
                }

                $status = collect(['confirmed', 'pending', 'completed', 'cancelled'])->random();
                $paymentStatus = collect(['paid', 'pending', 'refunded'])->random();
                $dayOffset = rand(-30, 30);

                $this->createBookingForChalet($chalet, $customers->random(), $status, $paymentStatus, $timeSlots, $dayOffset);
                $bookingCount++;
            }
        }

        $this->command->info("Seeded {$bookingCount} bookings across {$chalets->count()} chalets.");
    }

    private function createBookingForChalet(Chalet $chalet, User $customer, string $status, string $paymentStatus, $timeSlots, int $dayOffset): void
    {
        $dayUseSlot = $timeSlots->where('is_overnight', false)->first();
        if (!$dayUseSlot) return;

        $startDate = Carbon::now()->addDays($dayOffset);
        $endDate = (clone $startDate)->setTimeFromTimeString($dayUseSlot->end_time);

        $basePrice = $dayUseSlot->weekday_price;
        $commission = $basePrice * 0.1;
        $totalAmount = $basePrice + $commission;

        $booking = Booking::create([
            'chalet_id' => $chalet->id,
            'user_id' => $customer->id,
            'booking_reference' => 'BKG-' . Str::random(8),
            'start_date' => $startDate->setTimeFromTimeString($dayUseSlot->start_time),
            'end_date' => $endDate,
            'booking_type' => 'day-use',
            'adults_count' => rand(1, $chalet->max_adults),
            'children_count' => rand(0, $chalet->max_children),
            'total_guests' => rand(1, $chalet->max_adults + $chalet->max_children),
            'base_slot_price' => $basePrice,
            'platform_commission' => $commission,
            'total_amount' => $totalAmount,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'cancellation_reason' => $status === 'cancelled' ? 'Customer changed plans' : null,
            'cancelled_at' => $status === 'cancelled' ? Carbon::now() : null,
            'auto_completed_at' => $status === 'completed' ? Carbon::now() : null,
        ]);

        $booking->timeSlots()->sync([$dayUseSlot->id]);
    }
}
