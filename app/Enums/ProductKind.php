<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductKind: string implements HasColor, HasLabel
{
    case EggsTray = 'eggs_tray';
    case EggsUnit = 'eggs_unit';
    case LiveBird = 'live_bird';
    case DressedBird = 'dressed_bird';
    case CulledBird = 'culled_bird';
    case Manure = 'manure';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::EggsTray => 'Œufs (plateau)',
            self::EggsUnit => 'Œufs (unité)',
            self::LiveBird => 'Poulet vif',
            self::DressedBird => 'Poulet abattu',
            self::CulledBird => 'Poule de réforme',
            self::Manure => 'Fumier / fiente',
            self::Other => 'Autre',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EggsTray, self::EggsUnit => 'warning',
            self::LiveBird, self::CulledBird => 'success',
            self::DressedBird => 'danger',
            self::Manure => 'gray',
            self::Other => 'info',
        };
    }

    /**
     * Les ventes de ce type de produit sortent des sujets vivants de la bande.
     */
    public function decrementsFlock(): bool
    {
        return in_array($this, [self::LiveBird, self::CulledBird], true);
    }
}
