<?php

namespace Tests\Feature;

use App\Models\Chalet;
use App\Models\ChaletTimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ChaletApiControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user and chalet
        $this->user = User::factory()->create();
        $this->chalet = Chalet::factory()->create([
            'owner_id' => $this->user->id,
            'status' => 'active',
            'slug' => 'test-chalet'
        ]);
        
        // Create time slots for the chalet
        $this->dayUseSlot = ChaletTimeSlot::factory()->create([
            'chalet_id' => $this->chalet->id,
            'is_overnight' => false,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'weekday_price' => 100.00,
            'weekend_price' => 150.00,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'is_active' => true
        ]);
        
        $this->overnightSlot = ChaletTimeSlot::factory()->create([
            'chalet_id' => $this->chalet->id,
            'is_overnight' => true,
            'start_time' => '15:00:00',
            'end_time' => '11:00:00',
            'weekday_price' => 200.00,
            'weekend_price' => 250.00,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_can_get_chalet_availability_for_day_use()
    {
        $response = $this->getJson("/api/chalet/{$this->chalet->slug}/availability?" . http_build_query([
            'booking_type' => 'day-use',
            'start_date' => now()->addDay()->format('Y-m-d')
        ]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'start_date',
                        'end_date',
                        'booking_type',
                        'slots',
                        'total_price',
                        'currency'
                    ]
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('day-use', $response->json('data.booking_type'));
    }

    /** @test */
    public function it_can_get_chalet_availability_for_overnight()
    {
        $startDate = now()->addDay()->format('Y-m-d');
        $endDate = now()->addDays(2)->format('Y-m-d');
        
        $response = $this->getJson("/api/chalet/{$this->chalet->slug}/availability?" . http_build_query([
            'booking_type' => 'overnight',
            'start_date' => $startDate,
            'end_date' => $endDate
        ]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'start_date',
                        'end_date',
                        'booking_type',
                        'slots',
                        'total_price',
                        'currency',
                        'nights_count',
                        'nightly_breakdown'
                    ]
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals('overnight', $response->json('data.booking_type'));
    }

    /** @test */
    public function it_can_get_unavailable_dates()
    {
        $startDate = now()->addDay()->format('Y-m-d');
        $endDate = now()->addDays(7)->format('Y-m-d');
        
        $response = $this->getJson("/api/chalet/{$this->chalet->slug}/unavailable-dates?" . http_build_query([
            'booking_type' => 'day-use',
            'start_date' => $startDate,
            'end_date' => $endDate
        ]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'fully_blocked_dates',
                        'unavailable_day_use_dates',
                        'unavailable_overnight_dates',
                        'date_range',
                        'summary'
                    ]
                ]);

        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function it_returns_404_for_inactive_chalet()
    {
        $this->chalet->update(['status' => 'inactive']);
        
        $response = $this->getJson("/api/chalet/{$this->chalet->slug}/availability?" . http_build_query([
            'booking_type' => 'day-use',
            'start_date' => now()->addDay()->format('Y-m-d')
        ]));

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'error' => 'Chalet not found or inactive'
                ]);
    }

    /** @test */
    public function it_returns_400_for_invalid_booking_type()
    {
        $response = $this->getJson("/api/chalet/{$this->chalet->slug}/availability?" . http_build_query([
            'booking_type' => 'invalid-type',
            'start_date' => now()->addDay()->format('Y-m-d')
        ]));

        $response->assertStatus(422);
    }

    /** @test */
    public function it_returns_400_for_past_dates()
    {
        $response = $this->getJson("/api/chalet/{$this->chalet->slug}/availability?" . http_build_query([
            'booking_type' => 'day-use',
            'start_date' => now()->subDay()->format('Y-m-d')
        ]));

        $response->assertStatus(422);
    }

    /** @test */
    public function it_returns_400_for_overnight_without_end_date()
    {
        $response = $this->getJson("/api/chalet/{$this->chalet->slug}/availability?" . http_build_query([
            'booking_type' => 'overnight',
            'start_date' => now()->addDay()->format('Y-m-d')
        ]));

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'error' => 'End date is required for overnight bookings'
                ]);
    }

    /** @test */
    public function it_returns_400_for_overnight_exceeding_30_nights()
    {
        $startDate = now()->addDay()->format('Y-m-d');
        $endDate = now()->addDays(32)->format('Y-m-d');
        
        $response = $this->getJson("/api/chalet/{$this->chalet->slug}/availability?" . http_build_query([
            'booking_type' => 'overnight',
            'start_date' => $startDate,
            'end_date' => $endDate
        ]));

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'error' => 'Overnight bookings cannot exceed 30 nights'
                ]);
    }

    /** @test */
    public function it_returns_400_for_unavailable_dates_exceeding_90_days()
    {
        $startDate = now()->addDay()->format('Y-m-d');
        $endDate = now()->addDays(92)->format('Y-m-d');
        
        $response = $this->getJson("/api/chalet/{$this->chalet->slug}/unavailable-dates?" . http_build_query([
            'booking_type' => 'day-use',
            'start_date' => $startDate,
            'end_date' => $endDate
        ]));

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'error' => 'Date range cannot exceed 90 days'
                ]);
    }

    /** @test */
    public function it_rejects_overnight_booking_with_unavailable_nights()
    {
        // Create a chalet with a time slot that's not available on Monday and Saturday
        $this->chalet->update(['weekend_days' => ['friday', 'saturday']]);
        
        // Update the overnight slot to exclude Monday and Saturday
        $this->overnightSlot->update([
            'available_days' => ['tuesday', 'wednesday', 'thursday', 'friday', 'sunday']
        ]);
        
        // Try to book from Tuesday to Tuesday (7 nights) - this includes Saturday and Monday nights
        $startDate = '2025-08-19'; // Tuesday
        $endDate = '2025-08-26';   // Tuesday (7 nights)
        
        $response = $this->getJson("/api/chalet/{$this->chalet->slug}/availability?" . http_build_query([
            'booking_type' => 'overnight',
            'start_date' => $startDate,
            'end_date' => $endDate
        ]));

        // Should be rejected because Saturday and Monday nights are not available
        $response->assertStatus(404)
                ->assertJson([
                    'success' => false
                ]);
        
        // Check the response details for more specific error information
        $responseData = $response->json();
        $this->assertStringContainsString('No availability found for selected dates', $responseData['error']);
        $this->assertStringContainsString('Overnight booking requires all', $responseData['error']);
        $this->assertStringContainsString('Missing: 2 night(s)', $responseData['error']);
        
        // Verify the details array contains the specific error
        $this->assertContains('Overnight booking requires all 8 nights to be available. Only 6 nights available. Missing: 2 night(s).', $responseData['details']);
    }

    /** @test */
    public function it_accepts_overnight_booking_when_all_nights_are_available()
    {
        // Create a chalet with a time slot that's available on all days
        $this->chalet->update(['weekend_days' => ['friday', 'saturday']]);
        
        // Update the overnight slot to be available on all days
        $this->overnightSlot->update([
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']
        ]);
        
        // Try to book from Tuesday to Tuesday (7 nights) - all nights should be available
        $startDate = '2025-08-19'; // Tuesday
        $endDate = '2025-08-26';   // Tuesday (7 nights)
        
        $response = $this->getJson("/api/chalet/{$this->chalet->slug}/availability?" . http_build_query([
            'booking_type' => 'overnight',
            'start_date' => $startDate,
            'end_date' => $endDate
        ]));

        // Should be accepted because all nights are available
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);
        
        $responseData = $response->json();
        $this->assertEquals('overnight', $responseData['data']['booking_type']);
        $this->assertEquals(7, $responseData['data']['nights_count']);
    }
}
