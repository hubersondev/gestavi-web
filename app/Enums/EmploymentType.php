<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EmploymentType: string implements HasLabel
{
    case Permanent = 'permanent';
    case Temporary = 'temporary';
    case Daily = 'daily';
    case Seasonal = 'seasonal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent',
            self::Temporary => 'Temporaire',
            self::Daily => 'Journalier',
            self::Seasonal => 'Saisonnier',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Permanent => 'success',
            self::Temporary => 'warning',
            self::Daily => 'info',
            self::Seasonal => 'gray',
        };
    }
}
