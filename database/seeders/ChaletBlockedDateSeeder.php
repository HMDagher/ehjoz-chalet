<?php

namespace Database\Seeders;

use App\Enums\BlockReason;
use App\Models\Chalet;
use App\Models\ChaletBlockedDate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ChaletBlockedDateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $chalets = Chalet::with('timeSlots')->get();

        if ($chalets->isEmpty()) {
            $this->command->info('No chalets found to block dates for.');
            return;
        }

        $blockedDatesCount = 0;
        foreach ($chalets as $chalet) {
            $numberOfBlockedDates = rand(0, 3);

            for ($i = 0; $i < $numberOfBlockedDates; $i++) {
                $timeSlots = $chalet->timeSlots;
                $blockFullDay = (bool)rand(0, 1) || $timeSlots->isEmpty();
                $reason = collect(BlockReason::cases())->random()->value;

                ChaletBlockedDate::create([
                    'chalet_id' => $chalet->id,
                    'date' => Carbon::now()->addDays(rand(1, 60))->toDateString(),
                    'time_slot_id' => $blockFullDay ? null : $timeSlots->random()->id,
                    'reason' => $reason,
                    'notes' => 'Randomly generated blocked date.',
                ]);
                $blockedDatesCount++;
            }
        }

        $this->command->info("Seeded {$blockedDatesCount} blocked dates across {$chalets->count()} chalets.");
    }
}
