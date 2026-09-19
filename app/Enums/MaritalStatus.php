<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MaritalStatus: string implements HasLabel
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Single => 'Célibataire',
            self::Married => 'Marié(e)',
            self::Divorced => 'Divorcé(e)',
            self::Widowed => 'Veuf/Veuve',
        };
    }
}
