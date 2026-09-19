<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StockInventoryDifferenceReason: string implements HasLabel
{
    case None = 'none';
    case CountingError = 'counting_error';
    case Theft = 'theft';
    case Damage = 'damage';
    case Expiry = 'expiry';
    case UnrecordedUse = 'unrecorded_use';
    case UnrecordedEntry = 'unrecorded_entry';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Aucun écart',
            self::CountingError => 'Erreur de comptage précédent',
            self::Theft => 'Vol',
            self::Damage => 'Dommage / Détérioration',
            self::Expiry => 'Péremption',
            self::UnrecordedUse => 'Utilisation non enregistrée',
            self::UnrecordedEntry => 'Entrée non enregistrée',
            self::Other => 'Autre',
        };
    }
}
