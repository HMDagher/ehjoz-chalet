<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
        // $this->call([
        //     DataSeeder::class,
        //     // AmenitySeeder::class,
        //     // FacilitySeeder::class,
        //     // ChaletSeeder::class,
        //     // BookingSeeder::class,
        // ]);


        // DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // // Truncate existing tables
        // DB::table('users')->truncate();
        // DB::table('roles')->truncate();
        // DB::table('model_has_roles')->truncate();
        // DB::table('amenities')->truncate();
        // DB::table('facilities')->truncate();
        // DB::table('chalets')->truncate();
        // DB::table('amenity_chalet')->truncate();
        // DB::table('chalet_facility')->truncate();
        // DB::table('chalet_time_slots')->truncate();
        // DB::table('media')->truncate();
        // DB::table('general_settings')->truncate();


        // // Seed users table
        // DB::table('users')->insert([
        //     ['id' => 1, 'name' => 'Admin User', 'email' => 'admin@admin.com', 'password' => '$2y$12$wswXy3ggT2BfSlVB18E40e06eL36KODeEANcuy.idEjPKHXyPcQ9C', 'phone' => null, 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-22 17:41:59', 'updated_at' => '2025-06-22 17:41:59'],
        //     ['id' => 3, 'name' => 'Raed al Hamra', 'email' => 'theviewbymimo@gmail.com', 'password' => '$2y$12$FTTjTdyU0/ZeBYBb3hYNeeqNdIbvEc5T/OH5JulroT1aeFmsDXDFi', 'phone' => '+96181065922', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-22 17:49:57', 'updated_at' => '2025-06-22 17:49:57'],
        //     ['id' => 4, 'name' => 'Dany Abu dehn', 'email' => 'Dannyaboudehn5@gmail.com', 'password' => '$2y$12$7Wp6VsvOTkdW375SM188GOCiczXHbOOrgyqtM1IN59NN2L//bkKj.', 'phone' => '+96176415100', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-22 18:41:32', 'updated_at' => '2025-06-22 18:41:32'],
        //     ['id' => 5, 'name' => 'Mazen Al Halabi', 'email' => 'mazenhalabi42@gmail.com', 'password' => '$2y$12$UYnOEY.vsB7vJbhjVMqfcut1OFyPepWkQO02cXXfT4EkGNQSw9k7u', 'phone' => '+96170844992', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-22 19:09:56', 'updated_at' => '2025-06-22 19:09:56'],
        //     ['id' => 6, 'name' => 'Rola Amin', 'email' => 'SayfHasbaya@Ehjozchalet.com', 'password' => '$2y$12$gyU29JAA6SLdddM990.ITeInDo710e41CoDoGzRGJXQJcjHvYm4ju', 'phone' => '+96170127115', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-22 19:43:49', 'updated_at' => '2025-06-22 19:44:36'],
        //     ['id' => 11, 'name' => 'Raed lHamra', 'email' => 'theviewbymimo@ehjozchalet.com', 'password' => '$2y$12$AIRGPZRwItrgz1dU0G72FeIQvdEKtPSNNlV1QOEBqm8K4u90VhYbS', 'phone' => '+9613914707', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-22 21:48:01', 'updated_at' => '2025-06-22 21:48:41'],
        //     ['id' => 12, 'name' => 'Robil Al Aanaz', 'email' => 'FamilyLand@ehjozchalet.com', 'password' => '$2y$12$hde/3h.mQwYIFF8wwFxpee0UddzWCyQ7c6eGiEwt0vHBDPpSJl812', 'phone' => '+96170716406', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-22 22:05:09', 'updated_at' => '2025-06-22 22:05:09'],
        //     ['id' => 13, 'name' => 'Nizar Amin', 'email' => 'Hasbanivacationhouse@ehjozchalet.com', 'password' => '$2y$12$tHRDcvXDMdpSKy5c21sxyeP/WYy3wVFUy2va9OX5Ak61WHjywCFli', 'phone' => '+9613425933', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-22 22:26:06', 'updated_at' => '2025-06-22 22:26:06'],
        //     ['id' => 14, 'name' => 'Al Tayr', 'email' => 'Aahawak@ehjozchalet.com', 'password' => '$2y$12$0o7O1JbuplkN9rczN46Esu6OIBGloCOXd5koaTh2C.j39EOT0OkVq', 'phone' => '+96170367151', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-22 22:42:36', 'updated_at' => '2025-06-22 22:42:36'],
        //     ['id' => 15, 'name' => 'Wasim Jabr', 'email' => 'GoldenHills@ehjozchalet.com', 'password' => '$2y$12$nKtjy959i8Y25mg1HB85aeDb2I3bv53m7Oc31h/Yf6P2iVuvEbGoC', 'phone' => '+96170690776', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-22 22:52:55', 'updated_at' => '2025-06-24 06:56:33'],
        //     ['id' => 16, 'name' => 'Souzi', 'email' => 'Souzi@ehjozchalet.com', 'password' => '$2y$12$ckO5CcGFG267TG9.6peOyusyuqzsXrtWeflAHLVv1EDDR9XHUuz3i', 'phone' => '+97466112918', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-23 08:09:29', 'updated_at' => '2025-06-23 08:09:29'],
        //     ['id' => 17, 'name' => 'Helena khair', 'email' => 'Helena@ehjozchalet.com', 'password' => '$2y$12$/52b2hK8MCvCiEtvhNqj1uUQD0nAn37pguPKKmZ3k3lLCdzJ1XWn2', 'phone' => '+96170449657', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-23 10:17:10', 'updated_at' => '2025-06-23 10:17:10'],
        //     ['id' => 18, 'name' => 'Taymour Raad', 'email' => 'Taymour@ehjozchalet.com', 'password' => '$2y$12$LfhX9y2a69s2vqHDV2uDIeHbuZkT1zDBFfuYLRATBq/mrKOU/NDyG', 'phone' => '+96181088981', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-23 11:09:58', 'updated_at' => '2025-06-23 11:09:58'],
        //     ['id' => 19, 'name' => 'Sary Badawi', 'email' => 'ChaletDeLaVie@ehjozchalet.com', 'password' => '$2y$12$ozQfjFnBTWBB9aT.iCaZ/eHXaafPRZh93nuRaUXBospMBb/jtT0LO', 'phone' => '+96176891619', 'email_verified_at' => null, 'phone_verified_at' => null, 'remember_token' => null, 'created_at' => '2025-06-26 07:08:31', 'updated_at' => '2025-06-26 07:08:31'],
        // ]);

        // // Seed roles table
        // DB::table('roles')->insert([
        //     ['id' => 1, 'name' => 'admin', 'guard_name' => 'web', 'created_at' => '2025-06-23 21:50:28', 'updated_at' => '2025-06-23 21:50:28'],
        //     ['id' => 2, 'name' => 'owner', 'guard_name' => 'web', 'created_at' => '2025-06-23 21:50:28', 'updated_at' => '2025-06-23 21:50:28'],
        //     ['id' => 3, 'name' => 'customer', 'guard_name' => 'web', 'created_at' => '2025-06-23 21:50:28', 'updated_at' => '2025-06-23 21:50:28'],
        // ]);

        // // Seed model_has_roles table
        // DB::table('model_has_roles')->insert([
        //     ['role_id' => 1, 'model_type' => 'App\\Models\\User', 'model_id' => 1],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 3],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 4],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 5],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 6],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 11],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 12],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 13],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 14],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 15],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 16],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 17],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 18],
        //     ['role_id' => 2, 'model_type' => 'App\\Models\\User', 'model_id' => 19],
        // ]);

        // // Seed amenities table
        // DB::table('amenities')->insert([
        //     ['id' => 1, 'name' => 'Air Conditioner', 'icon' => null, 'is_active' => 1, 'created_at' => '2025-06-22 17:54:22', 'updated_at' => '2025-06-22 17:54:22'],
        //     ['id' => 2, 'name' => 'Television', 'icon' => null, 'is_active' => 1, 'created_at' => '2025-06-22 17:54:45', 'updated_at' => '2025-06-22 17:54:45'],
        //     ['id' => 3, 'name' => 'Geyser', 'icon' => null, 'is_active' => 1, 'created_at' => '2025-06-22 17:55:25', 'updated_at' => '2025-06-22 17:55:25'],
        //     ['id' => 4, 'name' => 'Wifi', 'icon' => null, 'is_active' => 1, 'created_at' => '2025-06-22 17:55:42', 'updated_at' => '2025-06-22 17:55:42'],
        //     ['id' => 5, 'name' => 'Room Heater', 'icon' => null, 'is_active' => 1, 'created_at' => '2025-06-22 17:55:58', 'updated_at' => '2025-06-22 17:55:58'],
        //     ['id' => 6, 'name' => 'Billiard', 'icon' => null, 'is_active' => 1, 'created_at' => '2025-06-22 19:23:49', 'updated_at' => '2025-06-22 19:23:49'],
        //     ['id' => 7, 'name' => 'Ping pong', 'icon' => null, 'is_active' => 1, 'created_at' => '2025-06-22 19:24:18', 'updated_at' => '2025-06-22 19:24:18'],
        // ]);

        // // Seed facilities table
        // DB::table('facilities')->insert([
        //     ['id' => 1, 'name' => 'Outdoor pool', 'icon' => null, 'is_active' => 1, 'created_at' => '2025-06-22 18:00:19', 'updated_at' => '2025-06-22 18:00:19'],
        //     ['id' => 2, 'name' => 'Indoor pool', 'icon' => null, 'is_active' => 1, 'created_at' => '2025-06-22 18:00:34', 'updated_at' => '2025-06-22 18:00:34'],
        //     ['id' => 3, 'name' => 'BBQ Area', 'icon' => null, 'is_active' => 1, 'created_at' => '2025-06-22 18:00:53', 'updated_at' => '2025-06-22 18:00:53'],
        //     ['id' => 4, 'name' => 'Fully Equipped Kitchen', 'icon' => null, 'is_active' => 1, 'created_at' => '2025-06-22 18:01:41', 'updated_at' => '2025-06-22 18:01:41'],
        // ]);
        
        // // Seed chalets table
        // DB::table('chalets')->insert([
        //     [
        //         'id' => 1, 'owner_id' => 3, 'name' => 'The View By Mimo', 'slug' => 'The_View_By_Mimo',
        //         'description' => '<p>🛏️ Accommodates up to 10 people</p><p>🛌 3 bedrooms, including a spacious master bedroom</p><p>🏊‍♂️ Indoor pool for year-round relaxation</p><p>🌅 Outdoor infinity pool with breathtaking views</p><p>👶 Kids\' pool for safe and fun play</p><p>⚡ 24/7 electricity</p><p>🛜 Free Wi-Fi connectivity</p>',
        //         'address' => null, 'city' => 'Hasbaya, South Lebanon', 'latitude' => null, 'longitude' => null,
        //         'max_adults' => 10, 'max_children' => 5, 'bedrooms_count' => 3, 'bathrooms_count' => 3,
        //         'check_in_instructions' => null, 'house_rules' => null, 'cancellation_policy' => 'Cancellation at least before 1 week',
        //         'status' => 'active', 'is_featured' => 1, 'featured_until' => '2025-09-30 21:07:26',
        //         'meta_title' => null, 'meta_description' => null, 'facebook_url' => null, 'instagram_url' => null, 'website_url' => null, 'whatsapp_number' => null,
        //         'average_rating' => 0, 'total_reviews' => null, 'total_earnings' => 0, 'pending_earnings' => 0, 'total_withdrawn' => 0,
        //         'bank_name' => null, 'account_holder_name' => null, 'account_number' => null, 'iban' => null,
        //         'created_at' => '2025-06-22 18:17:22', 'updated_at' => '2025-06-27 17:44:03'
        //     ],
        //     [
        //         'id' => 13, 'owner_id' => 19, 'name' => 'Chalet De La Vie', 'slug' => 'Chalet_De_La_Vie',
        //         'description' => '<p>• One master bedroom with a private bathroom<br> • Additional bathroom located downstairs<br> • Two douches (shower areas)<br> • Salon with 3 beds – ideal for group stays or relaxation<br> • Indoor kitchen and barbecue area – perfect for gatherings and meals<br> • 10-meter pool, with a depth ranging approximately from 150 cm to 230 cm<br> • Spacious grassy area with a cozy fire pit corner for evening hangouts<br> • Outdoor space accommodates up to 50–60 guests – great for events or celebrations<br> • Sleeping capacity: comfortably fits up to 5 guests<br> • Private parking for 6 to 7 cars<br> • Wi-Fi available 24/7<br> • Electricity 24/7 – no interruptions</p>',
        //         'address' => null, 'city' => 'Hasbaya', 'latitude' => null, 'longitude' => null,
        //         'max_adults' => null, 'max_children' => null, 'bedrooms_count' => null, 'bathrooms_count' => null,
        //         'check_in_instructions' => null, 'house_rules' => null, 'cancellation_policy' => null,
        //         'status' => 'active', 'is_featured' => 1, 'featured_until' => '2025-06-30 10:14:42',
        //         'meta_title' => null, 'meta_description' => null, 'facebook_url' => null, 'instagram_url' => null, 'website_url' => null, 'whatsapp_number' => null,
        //         'average_rating' => 0, 'total_reviews' => null, 'total_earnings' => 0, 'pending_earnings' => 0, 'total_withdrawn' => 0,
        //         'bank_name' => null, 'account_holder_name' => null, 'account_number' => null, 'iban' => null,
        //         'created_at' => '2025-06-26 07:28:44', 'updated_at' => '2025-06-26 07:28:44'
        //     ]
        // ]);
        
        // // Seed amenity_chalet table
        // DB::table('amenity_chalet')->insert([
        //     ['chalet_id' => 1, 'amenity_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        //     ['chalet_id' => 1, 'amenity_id' => 5, 'created_at' => now(), 'updated_at' => now()],
        //     // ... Add all other amenity_chalet entries
        //     ['chalet_id' => 12, 'amenity_id' => 4, 'created_at' => now(), 'updated_at' => now()],
        // ]);
        
        // // Seed chalet_facility table
        // DB::table('chalet_facility')->insert([
        //     ['chalet_id' => 1, 'facility_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        //     ['chalet_id' => 1, 'facility_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        //     // ... Add all other chalet_facility entries
        //     ['chalet_id' => 12, 'facility_id' => 4, 'created_at' => now(), 'updated_at' => now()],
        // ]);
        
        // // Seed chalet_time_slots table
        // DB::table('chalet_time_slots')->insert([
        //     [
        //         'chalet_id' => 1, 'name' => 'Day Shift', 'start_time' => '10:00:00', 'end_time' => '18:00:00', 'is_overnight' => 0, 'duration_hours' => 8,
        //         'weekday_price' => 120.00, 'weekend_price' => 150.00, 'allows_extra_hours' => 1, 'extra_hour_price' => 10.00, 'max_extra_hours' => null,
        //         'is_active' => 1, 'available_days' => '["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"]',
        //         'created_at' => '2025-06-22 18:26:03', 'updated_at' => '2025-06-22 21:53:57'
        //     ],
        //     [
        //         'chalet_id' => 13, 'name' => 'Over Night Shift', 'start_time' => '20:00:00', 'end_time' => '18:00:00', 'is_overnight' => 1, 'duration_hours' => 22,
        //         'weekday_price' => 180.00, 'weekend_price' => 200.00, 'allows_extra_hours' => 0, 'extra_hour_price' => null, 'max_extra_hours' => null,
        //         'is_active' => 1, 'available_days' => '["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"]',
        //         'created_at' => '2025-06-27 18:31:55', 'updated_at' => '2025-06-27 18:31:55'
        //     ]
        // ]);
        
        // // Seed general_settings table
        // DB::table('general_settings')->insert([
        //     [
        //         'id' => 1,
        //         'site_name' => 'EhjozChalet',
        //         'site_description' => 'EhjozChalet is Lebanon’s first online platform dedicated to helping you easily find, compare, and book chalets in Hasbaya and nearby regions. Whether you\'re planning a peaceful escape, a family weekend, or a private pool retreat, our curated selection of chalets offers something for everyone.\n\nWith verified listings, real-time availability, exclusive discounts, and zero phone calls needed, EhjozChalet makes your booking experience fast, simple, and secure. Start your next adventure today!',
        //         'site_logo' => null, 'site_favicon' => null, 'theme_color' => '#05a3bf',
        //         'support_email' => 'info@ehjozchalet.com', 'support_phone' => '+961 79193959',
        //         'google_analytics_id' => null, 'posthog_html_snippet' => null,
        //         'seo_title' => 'EhjozChalet', 'seo_keywords' => 'chalet booking Lebanon, Hasbaya chalets, rent chalet Hasbaya, South Lebanon stays, chalets with pools Lebanon, family getaway Hasbaya, book chalet online',
        //         'seo_metadata' => '{"Title": "Book Chalets in Hasbaya"}',
        //         'email_settings' => '{...}', 'email_from_address' => null, 'email_from_name' => null,
        //         'social_network' => '{...}', 'more_configs' => null,
        //         'created_at' => '2025-06-23 19:49:50', 'updated_at' => '2025-06-23 21:02:53'
        //     ]
        // ]);


        // DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
