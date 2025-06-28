<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ChaletStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';
    case PendingApproval = 'pending_approval';
    case Suspended = 'suspended';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::PendingApproval => 'Pending Approval',
            self::Suspended => 'Suspended',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::PendingApproval => 'warning',
            self::Suspended => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Active => 'heroicon-m-check-badge',
            self::Inactive => 'heroicon-m-pause',
            self::PendingApproval => 'heroicon-m-clock',
            self::Suspended => 'heroicon-m-x-circle',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Active => 'The chalet is active and visible to users.',
            self::Inactive => 'The chalet is inactive and hidden from users.',
            self::PendingApproval => 'The chalet is awaiting admin approval.',
            self::Suspended => 'The chalet is suspended due to a policy violation or admin action.',
        };
    }
}
