<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmployeeStatus: string implements HasLabel
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Terminated = 'terminated';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::OnLeave => 'En congé',
            self::Suspended => 'Suspendu',
            self::Terminated => 'Résilié',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::OnLeave => 'warning',
            self::Suspended => 'danger',
            self::Terminated => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::OnLeave => 'heroicon-o-clock',
            self::Suspended => 'heroicon-o-pause-circle',
            self::Terminated => 'heroicon-o-x-circle',
        };
    }
}
