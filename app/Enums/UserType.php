<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserType: string implements HasLabel
{
    case System = 'system';
    case Farmer = 'farmer';

    public function getLabel(): string
    {
        return match ($this) {
            self::System => 'Système',
            self::Farmer => 'Fermier',
        };
    }
}
