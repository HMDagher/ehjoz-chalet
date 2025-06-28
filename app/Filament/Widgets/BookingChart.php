<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use App\Models\Booking;
use Illuminate\Support\Carbon;

class BookingChart extends ChartWidget
{
    protected static ?string $heading = 'Bookings Over Time';

    protected static ?int $sort = 2;

    protected function getFilters(): ?array
    {
        return [
            'year' => 'This Year',
            'month' => 'This Month',
            'week' => 'This Week',
            'today' => 'Today',
        ];
    }

    protected function getData(): array
    {
        $filter = $this->filter;

        $trend = Trend::model(Booking::class);

        $data = match ($filter) {
            'today' => $trend->between(
                now()->startOfDay(),
                now()->endOfDay()
            )->perHour()->count(),

            'week' => $trend->between(
                now()->startOfWeek(),
                now()->endOfWeek()
            )->perDay()->count(),

            'month' => $trend->between(
                now()->startOfMonth(),
                now()->endOfMonth()
            )->perDay()->count(),

            'year' => $trend->between(
                now()->startOfYear(),
                now()->endOfYear()
            )->perMonth()->count(),

            default => $trend->between(
                now()->startOfYear(),
                now()->endOfYear()
            )->perMonth()->count(),
        };

        return [
            'datasets' => [
                [
                    'label' => 'Bookings',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(function (TrendValue $value) use ($filter) {
                $date = Carbon::parse($value->date);
                return match ($filter) {
                    'today' => $date->format('H:i'),
                    'week', 'month' => $date->format('M d'),
                    'year' => $date->format('M Y'),
                    default => $date->format('M Y'),
                };
            }),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
