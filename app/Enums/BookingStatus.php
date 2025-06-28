<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
            self::Refunded => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pending => 'heroicon-m-clock',
            self::Confirmed => 'heroicon-m-check-circle',
            self::Completed => 'heroicon-m-check-badge',
            self::Cancelled => 'heroicon-m-x-circle',
            self::Refunded => 'heroicon-m-arrow-uturn-left',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Pending => 'Booking is pending and not yet confirmed.',
            self::Confirmed => 'Booking is confirmed and reserved.',
            self::Completed => 'Booking has been completed successfully.',
            self::Cancelled => 'Booking was cancelled by the user or admin.',
            self::Refunded => 'Booking was refunded.',
        };
    }
}
