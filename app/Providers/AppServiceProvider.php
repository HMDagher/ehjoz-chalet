<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Booking;
use App\Models\ChaletBlockedDate;
use App\Models\Payment;
use App\Observers\BookingObserver;
use App\Observers\ChaletBlockedDateObserver;
use App\Observers\PaymentObserver;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Booking::observe(BookingObserver::class);
        Payment::observe(PaymentObserver::class);
        ChaletBlockedDate::observe(ChaletBlockedDateObserver::class);
    }
}
