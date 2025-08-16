<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeneralSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if we already have settings
        if (DB::table('general_settings')->count() === 0) {
            DB::table('general_settings')->insert([
                'site_name' => 'Ehjoz Chalet',
                'site_description' => 'Book your perfect chalet getaway',
                'support_phone' => '+961 70 123456',
                'support_email' => 'info@ehjozchalet.com',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
