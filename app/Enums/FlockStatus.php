<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FlockStatus: string implements HasLabel
{
    case Planned = 'planned';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => 'Planifié',
            self::Active => 'En cours',
            self::Completed => 'Terminé',
            self::Cancelled => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::Active => 'success',
            self::Completed => 'gray',
            self::Cancelled => 'danger',
        };
    }
}
