<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MemberStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Invited => 'Invité',
            self::Suspended => 'Suspendu',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Invited => 'warning',
            self::Suspended => 'danger',
        };
    }
}
