<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EquipmentCondition: string implements HasColor, HasLabel
{
    case New = 'new';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';
    case Broken = 'broken';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Neuf',
            self::Good => 'Bon',
            self::Fair => 'Correct',
            self::Poor => 'Mauvais',
            self::Broken => 'Hors service',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'success',
            self::Good => 'success',
            self::Fair => 'warning',
            self::Poor => 'danger',
            self::Broken => 'danger',
        };
    }
}
