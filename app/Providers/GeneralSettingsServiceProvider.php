<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class GeneralSettingsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        try {
            if (Schema::hasTable('general_settings')) {
                $settings = DB::table('general_settings')->first();
                View::share('settings', $settings);
            }
        } catch (\Exception $e) {
            // Log the error or handle it gracefully
            report($e);
        }
    }
}
