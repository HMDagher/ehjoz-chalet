<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BlockReason: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Maintenance = 'maintenance';
    case PersonalUse = 'personal_use';
    case ExternalBooking = 'external_booking';
    case Booked = 'booked';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Maintenance => 'Maintenance',
            self::PersonalUse => 'Personal Use',
            self::ExternalBooking => 'External Booking',
            self::Booked => 'Booked',
            self::Other => 'Other',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Maintenance => 'warning',
            self::PersonalUse => 'info',
            self::ExternalBooking => 'gray',
            self::Booked => 'success',
            self::Other => 'secondary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Maintenance => 'heroicon-m-wrench-screwdriver',
            self::PersonalUse => 'heroicon-m-user',
            self::ExternalBooking => 'heroicon-m-arrow-top-right-on-square',
            self::Booked => 'heroicon-m-calendar-days',
            self::Other => 'heroicon-m-question-mark-circle',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Maintenance => 'Blocked for maintenance or repairs.',
            self::PersonalUse => 'Blocked for personal use by the owner.',
            self::ExternalBooking => 'Blocked due to an external booking.',
            self::Booked => 'Blocked because it is already booked.',
            self::Other => 'Blocked for another reason.',
        };
    }
}
