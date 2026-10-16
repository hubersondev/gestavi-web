<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockMovementReason: string implements HasColor, HasLabel
{
    case Purchase = 'purchase';
    case Consumption = 'consumption';
    case Sale = 'sale';
    case Loss = 'loss';
    case Theft = 'theft';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Return = 'return';
    case Production = 'production';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchase => 'Achat',
            self::Consumption => 'Consommation',
            self::Sale => 'Vente',
            self::Loss => 'Perte / Casse',
            self::Theft => 'Vol',
            self::Adjustment => 'Ajustement',
            self::TransferIn => 'Transfert entrant',
            self::TransferOut => 'Transfert sortant',
            self::Return => 'Retour',
            self::Production => 'Production (collecte, abattage)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Purchase => 'success',
            self::Consumption => 'primary',
            self::Sale => 'success',
            self::Loss => 'danger',
            self::Theft => 'danger',
            self::Adjustment => 'warning',
            self::TransferIn, self::TransferOut => 'info',
            self::Return => 'gray',
            self::Production => 'primary',
        };
    }

    /**
     * Motifs qui représentent une perte subie (pas une consommation ni une vente).
     *
     * @return list<self>
     */
    public static function losses(): array
    {
        return [self::Loss, self::Theft];
    }
}
