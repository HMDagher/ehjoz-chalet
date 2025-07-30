<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\DeletePendingBookingsJob;
use App\Jobs\RefreshChaletAvailabilityCacheJob;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new DeletePendingBookingsJob)->everyFiveMinutes();

// Refresh chalet availability cache every hour
Schedule::job(new RefreshChaletAvailabilityCacheJob)->hourly()
    ->name('refresh-chalet-availability-cache')
    ->withoutOverlapping();
