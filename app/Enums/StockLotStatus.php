<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StockLotStatus: string implements HasColor, HasIcon, HasLabel
{
    case Available = 'available';
    case Low = 'low';
    case Depleted = 'depleted';
    case Expired = 'expired';
    case Blocked = 'blocked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Low => 'Stock bas',
            self::Depleted => 'Épuisé',
            self::Expired => 'Périmé',
            self::Blocked => 'Bloqué',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Low => 'warning',
            self::Depleted => 'gray',
            self::Expired => 'danger',
            self::Blocked => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Available => 'heroicon-o-check-circle',
            self::Low => 'heroicon-o-exclamation-triangle',
            self::Depleted => 'heroicon-o-minus-circle',
            self::Expired => 'heroicon-o-clock',
            self::Blocked => 'heroicon-o-lock-closed',
        };
    }
}
