<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Chalet>
 */
class ChaletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => 1, // We'll use a fixed user ID for testing
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->slug(),
            'description' => $this->faker->paragraph(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'max_adults' => $this->faker->numberBetween(2, 10),
            'max_children' => $this->faker->numberBetween(0, 5),
            'bedrooms_count' => $this->faker->numberBetween(1, 5),
            'bathrooms_count' => $this->faker->numberBetween(1, 3),
            'check_in_instructions' => $this->faker->paragraph(),
            'house_rules' => $this->faker->paragraph(),
            'cancellation_policy' => $this->faker->paragraph(),
            'status' => 'active',
            'is_featured' => false,
            'average_rating' => $this->faker->randomFloat(2, 3, 5),
            'total_reviews' => $this->faker->numberBetween(0, 100),
            'total_earnings' => $this->faker->randomFloat(2, 0, 10000),
            'pending_earnings' => $this->faker->randomFloat(2, 0, 1000),
            'total_withdrawn' => $this->faker->randomFloat(2, 0, 5000),
            'weekend_days' => ['friday', 'saturday'],
        ];
    }
}
