<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChaletTimeSlot>
 */
class ChaletTimeSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chalet_id' => 1, // Will be overridden in tests
            'name' => $this->faker->words(2, true),
            'start_time' => '08:00:00',
            'end_time' => '15:00:00',
            'is_overnight' => false,
            'duration_hours' => 7,
            'weekday_price' => $this->faker->randomFloat(2, 50, 200),
            'weekend_price' => $this->faker->randomFloat(2, 80, 300),
            'allows_extra_hours' => true,
            'extra_hour_price' => $this->faker->randomFloat(2, 10, 50),
            'max_extra_hours' => $this->faker->numberBetween(1, 4),
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'is_active' => true,
        ];
    }
}
