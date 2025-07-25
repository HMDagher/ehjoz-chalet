<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Facility;

final class FacilitySeeder extends Seeder
{
    public function run(): void
    {
        Facility::insert([
            ['name' => 'Gym', 'is_active' => true],
            ['name' => 'Spa', 'is_active' => true],
            ['name' => 'Restaurant', 'is_active' => true],
            ['name' => 'Conference Room', 'is_active' => true],
        ]);
    }
}
