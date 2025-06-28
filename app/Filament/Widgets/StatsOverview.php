<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Chalet;
use App\Models\Booking;
use App\Enums\BookingStatus;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

final class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Customers', User::role('customer')->count())
                ->icon('heroicon-m-users')
                ->description('Total registered customers'),
            Stat::make('Chalets', Chalet::count())
                ->icon('heroicon-m-home-modern')
                ->description('Total chalets'),
            Stat::make('Total Bookings', Booking::where('status', BookingStatus::Completed)->count())
                ->icon('heroicon-m-calendar-days')
                ->description('Completed bookings'),
            Stat::make('Total Booking Amount', number_format(
                    (float) Booking::where('status', BookingStatus::Completed)->sum('total_amount'), 2
                ) . ' USD')
                ->icon('heroicon-m-banknotes')
                ->description('Sum of completed bookings'),
        ];
    }
}
