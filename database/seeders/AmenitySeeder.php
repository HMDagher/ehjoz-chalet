<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

final class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        Amenity::insert([
            ['name' => 'WiFi', 'is_active' => true],
            ['name' => 'Air Conditioning', 'is_active' => true],
            ['name' => 'Swimming Pool', 'is_active' => true],
            ['name' => 'Parking', 'is_active' => true],
        ]);
    }
}
