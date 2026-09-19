<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AccountType: string implements HasColor, HasIcon, HasLabel
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileMoney = 'mobile_money';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Caisse',
            self::Bank => 'Banque',
            self::MobileMoney => 'Mobile Money',
            self::Other => 'Autre',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Bank => 'primary',
            self::MobileMoney => 'warning',
            self::Other => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Cash => 'heroicon-o-banknotes',
            self::Bank => 'heroicon-o-building-library',
            self::MobileMoney => 'heroicon-o-device-phone-mobile',
            self::Other => 'heroicon-o-wallet',
        };
    }
}
