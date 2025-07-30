<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\ChaletBlockedDate;
use App\Observers\BookingObserver;
use App\Observers\PaymentObserver;
use App\Observers\ChaletBlockedDateObserver;

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
