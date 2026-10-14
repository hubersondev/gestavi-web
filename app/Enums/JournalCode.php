<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Journaux comptables SYSCOHADA utilisés par GESTAVI.
 */
enum JournalCode: string implements HasColor, HasLabel
{
    case Purchases = 'AC';
    case Sales = 'VE';
    case Bank = 'BQ';
    case Cash = 'CA';
    case Miscellaneous = 'OD';
    case Payroll = 'PA';
    case Opening = 'AN';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchases => 'Achats',
            self::Sales => 'Ventes',
            self::Bank => 'Banque',
            self::Cash => 'Caisse',
            self::Miscellaneous => 'Opérations diverses',
            self::Payroll => 'Paie',
            self::Opening => 'À-nouveaux',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Purchases => 'warning',
            self::Sales => 'success',
            self::Bank => 'info',
            self::Cash => 'primary',
            self::Miscellaneous => 'gray',
            self::Payroll => 'danger',
            self::Opening => 'gray',
        };
    }
}
