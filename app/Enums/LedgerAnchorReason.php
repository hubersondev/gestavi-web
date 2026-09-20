<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Motif d'un ancrage de la chaîne de hachage du ledger.
 */
enum LedgerAnchorReason: string implements HasLabel
{
    case Scheduled = 'scheduled';
    case FiscalYearClose = 'fiscal_year_close';
    case Rebuild = 'rebuild';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Ancrage planifié',
            self::FiscalYearClose => 'Clôture d\'exercice',
            self::Rebuild => 'Reconstruction du journal',
            self::Manual => 'Ancrage manuel',
        };
    }
}
