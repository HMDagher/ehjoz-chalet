<?php

namespace Tests\Unit\Services;

use App\Models\Chalet;
use App\Models\ChaletCustomPricing;
use App\Models\ChaletTimeSlot;
use App\Services\PricingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_respects_chalet_weekend_days_and_applies_custom_pricing_for_day_use()
    {
        // Create user and chalet with custom weekend: Sunday only
        $user = \App\Models\User::factory()->create();
        $chalet = Chalet::factory()->create([
            'owner_id' => $user->id,
            'weekend_days' => ['sunday'],
        ]);

        // Create a day-use time slot
        $slot = ChaletTimeSlot::factory()->create([
            'chalet_id' => $chalet->id,
            'is_overnight' => false,
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'weekday_price' => 100,
            'weekend_price' => 200,
            'is_active' => true,
            'available_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
        ]);

        // Add custom pricing +50 for the date
        ChaletCustomPricing::create([
            'chalet_id' => $chalet->id,
            'time_slot_id' => $slot->id,
            'start_date' => '2025-08-24', // Sunday
            'end_date' => '2025-08-24',
            'custom_adjustment' => 50,
            'name' => 'Special Day',
            'is_active' => true,
        ]);

        $calculator = new PricingCalculator;

        // Sunday should be weekend per chalet config => 200 + 50 = 250
        $pricingWeekend = $calculator->calculateBookingPrice(
            $chalet->id,
            [$slot->id],
            '2025-08-24',
            null,
            'day-use'
        );

        $this->assertEquals(250, $pricingWeekend['total_amount']);
        $this->assertTrue($pricingWeekend['slot_details'][0]['is_weekend']);

        // Monday should be weekday => 100 + 50 (if custom pricing added for Monday)
        // Add a Monday custom pricing +50 to compare
        ChaletCustomPricing::create([
            'chalet_id' => $chalet->id,
            'time_slot_id' => $slot->id,
            'start_date' => '2025-08-25', // Monday
            'end_date' => '2025-08-25',
            'custom_adjustment' => 50,
            'name' => 'Monday Adj',
            'is_active' => true,
        ]);

        $pricingWeekday = $calculator->calculateBookingPrice(
            $chalet->id,
            [$slot->id],
            '2025-08-25',
            null,
            'day-use'
        );

        $this->assertEquals(150, $pricingWeekday['total_amount']);
        $this->assertFalse($pricingWeekday['slot_details'][0]['is_weekend']);
    }
}
