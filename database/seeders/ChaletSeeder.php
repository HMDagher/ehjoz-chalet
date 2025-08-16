<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Chalet;
use App\Models\ChaletCustomPricing;
use App\Models\User;
use Illuminate\Database\Seeder;

use Illuminate\Support\Str;

final class ChaletSeeder extends Seeder
{
    public function run(): void
    {
        $owners = User::role('owner')->get();
        if ($owners->isEmpty()) {
            $this->command->info('No owners found. Please seed owner users first.');
            return;
        }

        $chaletData = [
            [
                'name' => 'The Mountain Lodge',
                'description' => 'A cozy lodge nestled in the mountains, perfect for a winter getaway.',
                'city' => 'Aspen',
                'address' => '123 Mountain Pass',
            ],
            [
                'name' => 'Beachside Bungalow',
                'description' => 'A beautiful bungalow right on the beach. Wake up to the sound of waves.',
                'city' => 'Malibu',
                'address' => '456 Ocean Drive',
            ],
            [
                'name' => 'Urban Penthouse',
                'description' => 'A luxurious penthouse in the heart of the city with stunning skyline views.',
                'city' => 'New York',
                'address' => '789 High Street',
            ],
            [
                'name' => 'Desert Oasis',
                'description' => 'A modern oasis in the desert with a private pool and stunning sunset views.',
                'city' => 'Palm Springs',
                'address' => '101 Desert Road',
            ],
            [
                'name' => 'Lakeside Cabin',
                'description' => 'A rustic cabin on the lake, perfect for fishing and kayaking.',
                'city' => 'Lake Tahoe',
                'address' => '212 Lakeside Ave',
            ],
            [
                'name' => 'Forest Retreat',
                'description' => 'A secluded retreat in the forest, ideal for nature lovers.',
                'city' => 'Asheville',
                'address' => '333 Forest Trail',
            ],
            [
                'name' => 'Country Farmhouse',
                'description' => 'A charming farmhouse in the countryside with a large garden.',
                'city' => 'Napa Valley',
                'address' => '444 Vineyard Lane',
            ],
            [
                'name' => 'Ski-In/Ski-Out Condo',
                'description' => 'A convenient condo with direct access to the ski slopes.',
                'city' => 'Whistler',
                'address' => '555 Ski Slope Way',
            ],
            [
                'name' => 'Historic Townhouse',
                'description' => 'A beautifully restored historic townhouse in a charming old town.',
                'city' => 'Charleston',
                'address' => '666 Cobblestone St',
            ],
            [
                'name' => 'Modern Villa',
                'description' => 'A sleek and modern villa with all the latest amenities.',
                'city' => 'Miami',
                'address' => '777 Modern Ave',
            ],
        ];

        foreach ($chaletData as $data) {
            $chalet = Chalet::create([
                'owner_id' => $owners->random()->id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'],
                'address' => $data['address'],
                'city' => $data['city'],
                'latitude' => rand(-90, 90),
                'longitude' => rand(-180, 180),
                'max_adults' => rand(2, 10),
                'max_children' => rand(0, 5),
                'bedrooms_count' => rand(1, 5),
                'bathrooms_count' => rand(1, 4),
                'check_in_instructions' => 'Check in after 3 PM.',
                'house_rules' => 'No loud parties. Respect the neighbors.',
                'cancellation_policy' => 'Flexible cancellation policy.',
                'status' => 'active',
                'is_featured' => (bool)rand(0, 1),
                'featured_until' => now()->addDays(rand(10, 30)),
                'meta_title' => $data['name'],
                'meta_description' => $data['description'],
                'weekend_days' => ['friday', 'saturday'],
            ]);

            $this->seedTimeSlots($chalet);
        }

        $this->command->info('Seeded ' . count($chaletData) . ' chalets.');
    }

    private function seedTimeSlots(Chalet $chalet): void
    {
        $timeSlotPool = [
            [
                'name' => 'Morning Session',
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'is_overnight' => false,
            ],
            [
                'name' => 'Afternoon Session',
                'start_time' => '13:00:00',
                'end_time' => '18:00:00',
                'is_overnight' => false,
            ],
            [
                'name' => 'Evening Session',
                'start_time' => '19:00:00',
                'end_time' => '23:00:00',
                'is_overnight' => false,
            ],
            [
                'name' => 'Full Day Rental',
                'start_time' => '09:00:00',
                'end_time' => '21:00:00',
                'is_overnight' => false,
            ],
            [
                'name' => 'Overnight Stay',
                'start_time' => '16:00:00',
                'end_time' => '11:00:00',
                'is_overnight' => true,
            ],
        ];

        shuffle($timeSlotPool);
        $numberOfSlots = rand(1, 3);
        $selectedSlots = array_slice($timeSlotPool, 0, $numberOfSlots);

        foreach ($selectedSlots as $slotData) {
            $slot = $chalet->timeSlots()->create(array_merge($slotData, [
                'duration_hours' => rand(4, 12),
                'weekday_price' => rand(150, 450),
                'weekend_price' => rand(250, 600),
                'allows_extra_hours' => (bool)rand(0, 1),
                'extra_hour_price' => rand(25, 50),
                'max_extra_hours' => rand(1, 3),
                'available_days' => collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->random(rand(5, 7))->toArray(),
                'is_active' => true,
            ]));

            if (rand(0, 1)) { // Randomly decide whether to add custom pricing
                ChaletCustomPricing::create([
                    'chalet_id' => $chalet->id,
                    'time_slot_id' => $slot->id,
                    'start_date' => now()->addDays(rand(3, 8))->toDateString(),
                    'end_date' => now()->addDays(rand(10, 20))->toDateString(),
                    'custom_adjustment' => rand(-50, 100),
                    'name' => 'Seasonal Special',
                    'is_active' => true,
                ]);
            }
        }
    }
}
