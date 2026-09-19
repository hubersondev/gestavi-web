<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StockMovementType: string implements HasColor, HasIcon, HasLabel
{
    case In = 'in';
    case Out = 'out';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';

    public function getLabel(): string
    {
        return match ($this) {
            self::In => 'Entrée',
            self::Out => 'Sortie',
            self::Adjustment => 'Ajustement',
            self::Transfer => 'Transfert',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'danger',
            self::Adjustment => 'warning',
            self::Transfer => 'info',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::In => 'heroicon-o-arrow-down-circle',
            self::Out => 'heroicon-o-arrow-up-circle',
            self::Adjustment => 'heroicon-o-adjustments-horizontal',
            self::Transfer => 'heroicon-o-arrows-right-left',
        };
    }
}
