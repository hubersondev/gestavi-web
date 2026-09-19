<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MobileMoneyOperator: string implements HasColor, HasLabel
{
    case Orange = 'orange';
    case Mtn = 'mtn';
    case Moov = 'moov';
    case Wave = 'wave';

    public function getLabel(): string
    {
        return match ($this) {
            self::Orange => 'Orange Money',
            self::Mtn => 'MTN MoMo',
            self::Moov => 'Moov Money',
            self::Wave => 'Wave',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Orange => 'warning',
            self::Mtn => 'warning',
            self::Moov => 'info',
            self::Wave => 'primary',
        };
    }
}
