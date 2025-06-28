<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case Omt = 'omt';
    case WhishMoney = 'whish_money';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Omt => 'OMT',
            self::WhishMoney => 'Whish Money',
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::Other => 'Other',
        };
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::Omt => 'info',
            self::WhishMoney => 'info',
            self::Cash => 'success',
            self::BankTransfer => 'primary',
            self::Other => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Omt => 'heroicon-m-banknotes',
            self::WhishMoney => 'heroicon-m-wallet',
            self::Cash => 'heroicon-m-currency-dollar',
            self::BankTransfer => 'heroicon-m-arrow-right-circle',
            self::Other => 'heroicon-m-question-mark-circle',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::Omt => 'Payment via OMT service.',
            self::WhishMoney => 'Payment via Whish Money.',
            self::Cash => 'Cash payment.',
            self::BankTransfer => 'Bank transfer payment.',
            self::Other => 'Other payment method.',
        };
    }
}
