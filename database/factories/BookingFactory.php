<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
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
            'user_id' => 1, // Will be overridden in tests
            'booking_reference' => 'BK-' . $this->faker->unique()->numerify('######'),
            'start_date' => $this->faker->dateTimeBetween('now', '+1 month'),
            'end_date' => null, // For day-use bookings
            'booking_type' => 'day-use',
            'extra_hours' => 0,
            'adults_count' => $this->faker->numberBetween(1, 6),
            'children_count' => $this->faker->numberBetween(0, 3),
            'total_guests' => function (array $attributes) {
                return $attributes['adults_count'] + $attributes['children_count'];
            },
            'base_slot_price' => $this->faker->randomFloat(2, 100, 500),
            'seasonal_adjustment' => 0,
            'extra_hours_amount' => 0,
            'platform_commission' => function (array $attributes) {
                return $attributes['base_slot_price'] * 0.1; // 10% commission
            },
            'discount_amount' => 0,
            'discount_percentage' => 0,
            'total_amount' => function (array $attributes) {
                return $attributes['base_slot_price'] + $attributes['extra_hours_amount'] - $attributes['discount_amount'];
            },
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'owner_earning' => function (array $attributes) {
                return $attributes['total_amount'] - $attributes['platform_commission'];
            },
            'platform_earning' => function (array $attributes) {
                return $attributes['platform_commission'];
            },
            'remaining_payment' => 0,
        ];
    }
}
