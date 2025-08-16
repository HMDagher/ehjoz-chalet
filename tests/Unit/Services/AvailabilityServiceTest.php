<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TimeSlotHelper;
use App\Services\OverlapDetector;
use App\Services\AvailabilityService;
use App\Models\Chalet;
use App\Models\ChaletTimeSlot;
use App\Models\ChaletBlockedDate;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private $chalet;
    private $timeSlots;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles if they don't exist
        if (!\Spatie\Permission\Models\Role::where('name', 'admin')->exists()) {
            \Spatie\Permission\Models\Role::create(['name' => 'admin']);
        }
        
        // Create test user first
        $user = \App\Models\User::factory()->create();
        
        // Create test chalet
        $this->chalet = Chalet::factory()->create([
            'owner_id' => $user->id,
            'name' => 'Test Chalet',
            'slug' => 'test-chalet',
            'weekend_days' => ['friday', 'saturday']
        ]);

        // Create time slots matching your example
        $this->timeSlots = [
            'A' => ChaletTimeSlot::factory()->create([
                'chalet_id' => $this->chalet->id,
                'start_time' => '08:00:00',
                'end_time' => '15:00:00',
                'is_overnight' => false,
                'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'is_active' => true,
                'weekday_price' => 100,
                'weekend_price' => 150
            ]),
            'B' => ChaletTimeSlot::factory()->create([
                'chalet_id' => $this->chalet->id,
                'start_time' => '14:00:00',
                'end_time' => '11:00:00', // Next day (overnight)
                'is_overnight' => true,
                'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'is_active' => true,
                'weekday_price' => 200,
                'weekend_price' => 300
            ]),
            'C' => ChaletTimeSlot::factory()->create([
                'chalet_id' => $this->chalet->id,
                'start_time' => '16:00:00',
                'end_time' => '01:00:00', // Next day but not overnight
                'is_overnight' => false,
                'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'is_active' => true,
                'weekday_price' => 120,
                'weekend_price' => 180
            ]),
            'D' => ChaletTimeSlot::factory()->create([
                'chalet_id' => $this->chalet->id,
                'start_time' => '21:00:00',
                'end_time' => '05:00:00', // Next day but not overnight
                'is_overnight' => false,
                'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'is_active' => true,
                'weekday_price' => 80,
                'weekend_price' => 120
            ])
        ];
    }

    /** @test */
    public function test_time_slot_helper_cross_midnight_calculation()
    {
        // Test cross-midnight time calculation
        $startDateTime = TimeSlotHelper::convertToDateTime('2025-08-20', '16:00:00');
        $endDateTime = TimeSlotHelper::getSlotEndDateTime($startDateTime, '01:00:00');

        $this->assertEquals('2025-08-20 16:00:00', $startDateTime->format('Y-m-d H:i:s'));
        $this->assertEquals('2025-08-21 01:00:00', $endDateTime->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function test_overnight_slot_date_range()
    {
        // Test overnight slot affects multiple dates
        $slotB = $this->timeSlots['B'];
        $dates = TimeSlotHelper::getSlotsDateRange($slotB, '2025-08-20', '2025-08-22');
        
        $expected = ['2025-08-20', '2025-08-21', '2025-08-22'];
        $this->assertEquals($expected, $dates);
    }

    /** @test */
    public function test_consecutive_slots_validation()
    {
        // Test A (08:00-15:00) and B (14:00-11:00+1) are NOT consecutive (overlap)
        $slotsAB = collect([$this->timeSlots['A'], $this->timeSlots['B']]);
        $this->assertFalse(TimeSlotHelper::isConsecutive($slotsAB));

        // Test A (08:00-15:00) and C (16:00-01:00+1) ARE consecutive (no gap, no overlap)
        // Actually, let's create proper consecutive slots for testing
        $slotE = ChaletTimeSlot::factory()->create([
            'chalet_id' => $this->chalet->id,
            'start_time' => '15:00:00', // Starts exactly when A ends
            'end_time' => '20:00:00',
            'is_overnight' => false,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'is_active' => true,
            'weekday_price' => 80,
            'weekend_price' => 120
        ]);

        $consecutiveSlots = collect([$this->timeSlots['A'], $slotE]);
        $this->assertTrue(TimeSlotHelper::isConsecutive($consecutiveSlots));
    }

    /** @test */
    public function test_overlap_detection_when_slot_a_blocked()
    {
        // Block slot A on 2025-08-20
        ChaletBlockedDate::create([
            'chalet_id' => $this->chalet->id,
            'date' => '2025-08-20',
            'time_slot_id' => $this->timeSlots['A']->id,
            'reason' => 'maintenance'
        ]);

        // Test that slot B on same day is affected (overlaps 14:00-15:00)
        $conflicts = OverlapDetector::findConflictingSlots(
            $this->chalet->id,
            $this->timeSlots['B'],
            ['2025-08-20']
        );

        $this->assertNotEmpty($conflicts['blocked']);

        // Test that slot B from previous day is affected (ends 11:00, blocked starts 08:00)
        $conflicts = OverlapDetector::findConflictingSlots(
            $this->chalet->id,
            $this->timeSlots['B'],
            ['2025-08-19']
        );

        $this->assertNotEmpty($conflicts['blocked']);
    }

    /** @test */
    public function test_availability_service_basic_day_use()
    {
        $availabilityService = new AvailabilityService();
        
        $result = $availabilityService->checkAvailability(
            $this->chalet->id,
            '2025-08-25', // Monday
            null, // end_date null for day-use
            'day-use'
        );

        $this->assertTrue($result['available']);
        $this->assertCount(3, $result['available_slots']); // A, C, D (B is overnight)
        
        // Check that slot A is available
        $slotA = collect($result['available_slots'])->firstWhere('slot_id', $this->timeSlots['A']->id);
        $this->assertNotNull($slotA);
        $this->assertEquals('08:00:00', $slotA['start_time']);
        $this->assertEquals('15:00:00', $slotA['end_time']);
    }

    /** @test */
    public function test_availability_service_overnight_booking()
    {
        $availabilityService = new AvailabilityService();
        
        $result = $availabilityService->checkAvailability(
            $this->chalet->id,
            '2025-08-25',
            '2025-08-27', // 2 nights
            'overnight'
        );

        $this->assertTrue($result['available']);
        $this->assertCount(1, $result['available_slots']); // Only B is overnight
        
        $slotB = $result['available_slots'][0];
        $this->assertEquals($this->timeSlots['B']->id, $slotB['slot_id']);
        $this->assertTrue($slotB['is_overnight']);
    }

    /** @test */
    public function test_availability_with_existing_booking()
    {
        // Create an existing booking for slot A on 2025-08-25
        $existingBooking = Booking::factory()->create([
            'chalet_id' => $this->chalet->id,
            'user_id' => 1,
            'start_date' => '2025-08-25 08:00:00',
            'end_date' => '2025-08-25 15:00:00',
            'booking_type' => 'day-use',
            'status' => 'confirmed',
            'total_amount' => 100
        ]);

        // Attach the time slot to the booking
        $existingBooking->timeSlots()->attach($this->timeSlots['A']->id);

        $availabilityService = new AvailabilityService();
        
        $result = $availabilityService->checkAvailability(
            $this->chalet->id,
            '2025-08-25',
            null,
            'day-use'
        );

        // Should still be available but slot A should not be in the list
        $availableSlotIds = collect($result['available_slots'])->pluck('slot_id')->toArray();
        $this->assertNotContains($this->timeSlots['A']->id, $availableSlotIds);
        
        // Should contain C and D
        $this->assertContains($this->timeSlots['C']->id, $availableSlotIds);
        $this->assertContains($this->timeSlots['D']->id, $availableSlotIds);
    }

    /** @test */
    public function test_availability_with_weekend_pricing()
    {
        $availabilityService = new AvailabilityService();
        
        // Test on Friday (weekend day)
        $result = $availabilityService->checkAvailability(
            $this->chalet->id,
            '2025-08-22', // Friday
            null,
            'day-use'
        );

        $this->assertTrue($result['available']);
        
        $slotA = collect($result['available_slots'])->firstWhere('slot_id', $this->timeSlots['A']->id);
        $this->assertEquals(150, $slotA['weekend_price']); // Weekend price
        
        // Check pricing info shows weekend pricing
        $this->assertTrue($slotA['pricing_info']['2025-08-22']['is_weekend']);
        $this->assertEquals(150, $slotA['pricing_info']['2025-08-22']['final_price']);
    }

    /** @test */
    public function test_full_day_blocking()
    {
        // Block entire day (no specific time slot)
        ChaletBlockedDate::create([
            'chalet_id' => $this->chalet->id,
            'date' => '2025-08-20',
            'time_slot_id' => null, // Full day block
            'reason' => 'other'
        ]);

        $availabilityService = new AvailabilityService();
        
        $result = $availabilityService->checkAvailability(
            $this->chalet->id,
            '2025-08-20',
            null,
            'day-use'
        );

        $this->assertFalse($result['available']);
        $this->assertEmpty($result['available_slots']);
        $this->assertContains('full_day_blocked', $result['errors']);
    }

    /** @test */
    public function test_validation_booking_request()
    {
        $availabilityService = new AvailabilityService();
        
        // Test valid consecutive day-use booking
        $slotE = ChaletTimeSlot::factory()->create([
            'chalet_id' => $this->chalet->id,
            'start_time' => '15:00:00', // Consecutive with A
            'end_time' => '20:00:00',
            'is_overnight' => false,
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'is_active' => true,
            'weekday_price' => 80,
            'weekend_price' => 120
        ]);

        $validation = $availabilityService->validateBookingRequest(
            $this->chalet->id,
            [$this->timeSlots['A']->id, $slotE->id],
            '2025-08-25',
            null,
            'day-use'
        );

        $this->assertTrue($validation['valid']);
        
        // Test invalid non-consecutive booking
        $validation = $availabilityService->validateBookingRequest(
            $this->chalet->id,
            [$this->timeSlots['A']->id, $this->timeSlots['C']->id], // Not consecutive
            '2025-08-25',
            null,
            'day-use'
        );

        $this->assertFalse($validation['valid']);
        $this->assertStringContainsString('consecutive', implode(' ', $validation['errors']));
    }
}