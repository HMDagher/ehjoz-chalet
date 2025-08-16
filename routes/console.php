<?php

declare(strict_types=1);

use App\Jobs\DeletePendingBookingsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new DeletePendingBookingsJob)->everyFiveMinutes();
