<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Partial => 'Partial',
            self::Paid => 'Paid',
            self::Refunded => 'Refunded',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Partial => 'info',
            self::Paid => 'success',
            self::Refunded => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pending => 'heroicon-m-clock',
            self::Partial => 'heroicon-m-adjustments-horizontal',
            self::Paid => 'heroicon-m-currency-dollar',
            self::Refunded => 'heroicon-m-arrow-uturn-left',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Pending => 'Payment is pending and not yet received.',
            self::Partial => 'Partial payment has been made.',
            self::Paid => 'Payment is fully received.',
            self::Refunded => 'Payment has been refunded.',
        };
    }
}
