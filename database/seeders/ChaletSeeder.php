<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Chalet;
use App\Models\ChaletTimeSlot;
use App\Models\ChaletCustomPricing;
use App\Models\User;
use Illuminate\Database\Seeder;

final class ChaletSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->first();
        if (!$admin) {
            return;
        }

        $chalet = Chalet::create([
            'owner_id' => $admin->id,
            'name' => 'Sample Chalet',
            'slug' => 'sample-chalet',
            'description' => 'A beautiful sample chalet for demo purposes.',
            'address' => '123 Demo Street',
            'city' => 'Demo City',
            'latitude' => 24.7136,
            'longitude' => 46.6753,
            'max_adults' => 4,
            'max_children' => 2,
            'bedrooms_count' => 2,
            'bathrooms_count' => 2,
            'check_in_instructions' => 'Check in after 2 PM.',
            'house_rules' => 'No smoking. No pets.',
            'cancellation_policy' => 'Free cancellation up to 24 hours before check-in.',
            'status' => 'active',
            'is_featured' => true,
            'featured_until' => now()->addMonth(),
            'meta_title' => 'Sample Chalet',
            'meta_description' => 'A beautiful sample chalet.',
            'facebook_url' => null,
            'instagram_url' => null,
            'website_url' => null,
            'whatsapp_number' => null,
            'average_rating' => 0,
            'total_reviews' => 0,
            'total_earnings' => 0,
            'pending_earnings' => 0,
            'total_withdrawn' => 0,
            'bank_name' => null,
            'account_holder_name' => null,
            'account_number' => null,
            'iban' => null,
        ]);

        $timeSlots = [
            [
                'name' => 'Morning',
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'is_overnight' => false,
                'duration_hours' => 4,
                'weekday_price' => 200,
                'weekend_price' => 250,
                'allows_extra_hours' => true,
                'extra_hour_price' => 50,
                'max_extra_hours' => 2,
                'available_days' => [1,2,3,4,5,6,7],
                'is_active' => true,
            ],
            [
                'name' => 'Evening',
                'start_time' => '16:00:00',
                'end_time' => '22:00:00',
                'is_overnight' => false,
                'duration_hours' => 6,
                'weekday_price' => 300,
                'weekend_price' => 350,
                'allows_extra_hours' => true,
                'extra_hour_price' => 60,
                'max_extra_hours' => 2,
                'available_days' => [5,6,7],
                'is_active' => true,
            ],
            [
                'name' => 'Overnight',
                'start_time' => '14:00:00',
                'end_time' => '12:00:00',
                'is_overnight' => true,
                'duration_hours' => 22,
                'weekday_price' => 500,
                'weekend_price' => 600,
                'allows_extra_hours' => false,
                'extra_hour_price' => null,
                'max_extra_hours' => null,
                'available_days' => [1,2,3,4,5,6,7],
                'is_active' => true,
            ],
        ];

        foreach ($timeSlots as $slotData) {
            $slot = $chalet->timeSlots()->create($slotData);
            ChaletCustomPricing::create([
                'chalet_id' => $chalet->id,
                'time_slot_id' => $slot->id,
                'start_date' => now()->addDays(7)->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
                'custom_adjustment' => 50, // previously custom_price
                'name' => $slotData['name'] . ' Special',
                'is_active' => true,
            ]);
        }
    }
}
