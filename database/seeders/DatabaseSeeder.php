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
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        // Create an admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@admin.com',
            'password' => bcrypt('password'),
        ]);

        // Assign admin role using Spatie permissions
        $admin->assignRole('admin');

        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@owner.com',
            'password' => bcrypt('password'),
        ]);

        $owner->assignRole('owner');

        $customer = User::create([
            'name' => 'Customer User',
            'email' => 'customer@customer.com',
            'password' => bcrypt('password'),
        ]);

        $customer->assignRole('customer');

        // Seed amenities and facilities
        $this->call([
            AmenitySeeder::class,
            FacilitySeeder::class,
            ChaletSeeder::class,
            BookingSeeder::class,
            GeneralSettingsSeeder::class,
        ]);
    }
}
