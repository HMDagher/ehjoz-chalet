<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Booking;
use App\Models\Chalet;
use App\Models\User;
use App\Models\ChaletTimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $chalet = Chalet::with('timeSlots')->first();
        $user = User::first();
        $timeSlots = $chalet?->timeSlots ?? collect();
        if (!$chalet || !$user || $timeSlots->isEmpty()) {
            return;
        }

        // Booking 1: Single day, one slot
        $booking1 = Booking::create([
            'chalet_id' => $chalet->id,
            'user_id' => $user->id,
            'booking_reference' => 'BKG-0001',
            'start_date' => Carbon::now()->addDays(1)->setTime(14, 0),
            'end_date' => Carbon::now()->addDays(1)->setTime(22, 0),
            'adults_count' => 2,
            'children_count' => 1,
            'total_guests' => 3,
            'base_slot_price' => 500,
            'seasonal_adjustment' => 0,
            'extra_hours' => 0,
            'extra_hours_amount' => 0,
            'platform_commission' => 50,
            'total_amount' => 550,
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);
        $booking1->timeSlots()->sync([$timeSlots->first()->id]);

        // Booking 2: Single day, multiple slots
        if ($timeSlots->count() > 1) {
            $booking2 = Booking::create([
                'chalet_id' => $chalet->id,
                'user_id' => $user->id,
                'booking_reference' => 'BKG-0002',
                'start_date' => Carbon::now()->addDays(2)->setTime(8, 0),
                'end_date' => Carbon::now()->addDays(2)->setTime(22, 0),
                'adults_count' => 4,
                'children_count' => 2,
                'total_guests' => 6,
                'base_slot_price' => 1500,
                'seasonal_adjustment' => 100,
                'extra_hours' => 2,
                'extra_hours_amount' => 200,
                'platform_commission' => 100,
                'total_amount' => 1900,
                'status' => 'pending',
                'payment_status' => 'pending',
            ]);
            $booking2->timeSlots()->sync($timeSlots->pluck('id')->toArray());
        }

        // Booking 3: Multi-day, one overnight slot
        $overnightSlot = $timeSlots->first(fn($slot) => $slot->is_overnight);
        if ($overnightSlot) {
            $booking3 = Booking::create([
                'chalet_id' => $chalet->id,
                'user_id' => $user->id,
                'booking_reference' => 'BKG-0003',
                'start_date' => Carbon::now()->addDays(3)->setTime(14, 0),
                'end_date' => Carbon::now()->addDays(5)->setTime(12, 0),
                'adults_count' => 2,
                'children_count' => 1,
                'total_guests' => 3,
                'base_slot_price' => 2000,
                'seasonal_adjustment' => 0,
                'extra_hours' => 0,
                'extra_hours_amount' => 0,
                'platform_commission' => 200,
                'total_amount' => 2200,
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ]);
            $booking3->timeSlots()->sync([$overnightSlot->id]);
        }

        // Booking 4: Cancelled booking (single day, one slot)
        $booking4 = Booking::create([
            'chalet_id' => $chalet->id,
            'user_id' => $user->id,
            'booking_reference' => 'BKG-0004',
            'start_date' => Carbon::now()->addDays(6)->setTime(14, 0),
            'end_date' => Carbon::now()->addDays(6)->setTime(22, 0),
            'adults_count' => 1,
            'children_count' => 0,
            'total_guests' => 1,
            'base_slot_price' => 400,
            'seasonal_adjustment' => 0,
            'extra_hours' => 0,
            'extra_hours_amount' => 0,
            'platform_commission' => 40,
            'total_amount' => 440,
            'status' => 'cancelled',
            'payment_status' => 'refunded',
            'cancellation_reason' => 'Customer request',
            'cancelled_at' => Carbon::now()->addDays(5),
        ]);
        $booking4->timeSlots()->sync([$timeSlots->first()->id]);

        // Booking 5: Auto-completed booking (single day, last slot)
        $booking5 = Booking::create([
            'chalet_id' => $chalet->id,
            'user_id' => $user->id,
            'booking_reference' => 'BKG-0005',
            'start_date' => Carbon::now()->subDays(3)->setTime(14, 0),
            'end_date' => Carbon::now()->subDays(3)->setTime(22, 0),
            'adults_count' => 3,
            'children_count' => 2,
            'total_guests' => 5,
            'base_slot_price' => 600,
            'seasonal_adjustment' => 0,
            'extra_hours' => 1,
            'extra_hours_amount' => 100,
            'platform_commission' => 60,
            'total_amount' => 760,
            'status' => 'completed',
            'payment_status' => 'paid',
            'auto_completed_at' => Carbon::now()->subDays(2),
        ]);
        $booking5->timeSlots()->sync([$timeSlots->last()->id]);
    }
}
