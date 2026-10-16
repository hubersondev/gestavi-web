<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EquipmentMovementType: string implements HasColor, HasIcon, HasLabel
{
    case Transfer = 'transfer';
    case Entry = 'entry';
    case Exit = 'exit';
    case Loan = 'loan';
    case Return = 'return';
    case Theft = 'theft';

    public function getLabel(): string
    {
        return match ($this) {
            self::Transfer => 'Transfert',
            self::Entry => 'Entrée',
            self::Exit => 'Sortie',
            self::Loan => 'Prêt',
            self::Return => 'Retour',
            self::Theft => 'Vol',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Transfer => 'info',
            self::Entry => 'success',
            self::Exit => 'danger',
            self::Loan => 'warning',
            self::Return => 'primary',
            self::Theft => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Transfer => 'heroicon-o-arrows-right-left',
            self::Entry => 'heroicon-o-arrow-down-circle',
            self::Exit => 'heroicon-o-arrow-up-circle',
            self::Loan => 'heroicon-o-arrow-top-right-on-square',
            self::Return => 'heroicon-o-arrow-uturn-left',
            self::Theft => 'heroicon-o-shield-exclamation',
        };
    }
}
