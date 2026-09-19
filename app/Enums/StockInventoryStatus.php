<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StockInventoryStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case PendingValidation = 'pending_validation';
    case Validated = 'validated';
    case Adjusted = 'adjusted';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::PendingValidation => 'En attente de validation',
            self::Validated => 'Validé',
            self::Adjusted => 'Ajusté au stock',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingValidation => 'warning',
            self::Validated => 'info',
            self::Adjusted => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Draft => 'heroicon-o-pencil-square',
            self::PendingValidation => 'heroicon-o-clock',
            self::Validated => 'heroicon-o-check-badge',
            self::Adjusted => 'heroicon-o-check-circle',
        };
    }
}
