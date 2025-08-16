<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\Chalet;
use App\Models\ChaletBlockedDate;
use App\Models\ChaletTimeSlot;
use App\Services\OverlapDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverlapDetectorTest extends TestCase
{
    use RefreshDatabase;

    protected Chalet $chalet;

    protected array $slots;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure required role exists (Spatie Permission)
        if (! \Spatie\Permission\Models\Role::where('name', 'admin')->exists()) {
            \Spatie\Permission\Models\Role::create(['name' => 'admin']);
        }

        $user = \App\Models\User::factory()->create();
        $this->chalet = Chalet::factory()->create([
            'owner_id' => $user->id,
            'weekend_days' => ['sunday'],
        ]);

        // Create time slots similar to AvailabilityServiceTest
        $this->slots = [
            'A' => ChaletTimeSlot::factory()->create([
                'chalet_id' => $this->chalet->id,
                'start_time' => '08:00:00',
                'end_time' => '15:00:00',
                'is_overnight' => false,
                'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'is_active' => true,
                'weekday_price' => 100,
                'weekend_price' => 150,
            ]),
            'B' => ChaletTimeSlot::factory()->create([
                'chalet_id' => $this->chalet->id,
                'start_time' => '14:00:00',
                'end_time' => '11:00:00', // overnight
                'is_overnight' => true,
                'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'is_active' => true,
                'weekday_price' => 200,
                'weekend_price' => 300,
            ]),
            'C' => ChaletTimeSlot::factory()->create([
                'chalet_id' => $this->chalet->id,
                'start_time' => '16:00:00',
                'end_time' => '01:00:00', // crosses midnight, day-use
                'is_overnight' => false,
                'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'is_active' => true,
                'weekday_price' => 120,
                'weekend_price' => 180,
            ]),
        ];
    }

    /** @test */
    public function it_respects_available_days_in_is_slot_available_on_date()
    {
        // 2025-08-24 is Sunday; slot A available_days exclude Sunday
        $this->assertFalse(OverlapDetector::isSlotAvailableOnDate($this->chalet->id, $this->slots['A']->id, '2025-08-24'));

        // Monday should be allowed (2025-08-25)
        $this->assertTrue(OverlapDetector::isSlotAvailableOnDate($this->chalet->id, $this->slots['A']->id, '2025-08-25'));
    }

    /** @test */
    public function full_day_block_makes_slot_unavailable()
    {
        ChaletBlockedDate::create([
            'chalet_id' => $this->chalet->id,
            'date' => '2025-08-25',
            'time_slot_id' => null, // full day block
            'reason' => 'other',
        ]);

        $this->assertFalse(OverlapDetector::isSlotAvailableOnDate($this->chalet->id, $this->slots['A']->id, '2025-08-25'));
    }

    /** @test */
    public function per_slot_block_overlap_is_detected_across_days()
    {
        // Block slot A on 2025-08-25 (Mon)
        ChaletBlockedDate::create([
            'chalet_id' => $this->chalet->id,
            'date' => '2025-08-25',
            'time_slot_id' => $this->slots['A']->id,
            'reason' => 'maintenance',
        ]);

        // Overnight slot B starts 14:00 on 25th and overlaps with A (14:00-15:00)
        $this->assertFalse(OverlapDetector::isSlotAvailableOnDate($this->chalet->id, $this->slots['B']->id, '2025-08-25'));

        // Also, check the previous day due to overnight spill (+/-1 day window)
        $conflictsPrevDay = OverlapDetector::findConflictingSlots($this->chalet->id, $this->slots['B'], ['2025-08-24']);
        $this->assertNotEmpty($conflictsPrevDay['blocked']);
    }

    /** @test */
    public function confirmed_booking_creates_overlap_conflict()
    {
        // Create a confirmed booking for slot A on 2025-08-25
        $booking = Booking::factory()->create([
            'chalet_id' => $this->chalet->id,
            'user_id' => 1,
            'start_date' => '2025-08-25 08:00:00',
            'end_date' => '2025-08-25 15:00:00',
            'booking_type' => 'day-use',
            'status' => 'confirmed',
            'total_amount' => 100,
        ]);
        $booking->timeSlots()->attach($this->slots['A']->id);

        // Slot A should be unavailable for the same date
        $this->assertFalse(OverlapDetector::isSlotAvailableOnDate($this->chalet->id, $this->slots['A']->id, '2025-08-25'));

        // Overnight slot B also overlaps (14:00-15:00 on 25th)
        $this->assertFalse(OverlapDetector::isSlotAvailableOnDate($this->chalet->id, $this->slots['B']->id, '2025-08-25'));
    }

    /** @test */
    public function get_affected_slots_returns_overlapping_slots_across_window()
    {
        // Target slot A on 2025-08-25; B should be affected due to 14:00-15:00 overlap
        $affected = OverlapDetector::getAffectedSlots($this->chalet->id, $this->slots['A']->id, '2025-08-25');

        $affectedIds = collect($affected)->pluck('slot_id')->unique()->values()->all();
        $this->assertContains($this->slots['B']->id, $affectedIds);
    }
}
