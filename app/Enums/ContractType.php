<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractType: string implements HasLabel
{
    case Cdi = 'cdi';
    case Cdd = 'cdd';
    case Seasonal = 'seasonal';
    case Intern = 'intern';
    case Freelance = 'freelance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cdi => 'CDI',
            self::Cdd => 'CDD',
            self::Seasonal => 'Saisonnier',
            self::Intern => 'Stage',
            self::Freelance => 'Freelance',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cdi => 'success',
            self::Cdd => 'info',
            self::Seasonal => 'warning',
            self::Intern => 'gray',
            self::Freelance => 'purple',
        };
    }
}
