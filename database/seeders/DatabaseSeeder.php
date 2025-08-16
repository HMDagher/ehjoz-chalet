<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create roles using Spatie permissions with default guard name
        $roleAdmin = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleOwner = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $roleCustomer = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        // Create an admin user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@admin.com',
        ])->assignRole($roleAdmin);

        // Create owner users
        User::factory()->count(5)->create()->each(function ($user) use ($roleOwner) {
            $user->assignRole($roleOwner);
        });

        // Create customer users
        User::factory()->count(10)->create()->each(function ($user) use ($roleCustomer) {
            $user->assignRole($roleCustomer);
        });

        // Seed amenities and facilities
        $this->call([
            AmenitySeeder::class,
            FacilitySeeder::class,
            ChaletSeeder::class,
            BookingSeeder::class,
            ChaletBlockedDateSeeder::class,
            GeneralSettingsSeeder::class,
        ]);
    }
}
