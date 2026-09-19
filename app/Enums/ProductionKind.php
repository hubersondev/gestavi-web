<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Orientation zootechnique d'un type de volaille : détermine les indicateurs
 * calculés (IC/GMQ pour la chair, taux de ponte pour les pondeuses).
 */
enum ProductionKind: string implements HasColor, HasLabel
{
    case Broiler = 'broiler';
    case Layer = 'layer';
    case Breeder = 'breeder';
    case Mixed = 'mixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Broiler => 'Chair',
            self::Layer => 'Pondeuse',
            self::Breeder => 'Reproducteur',
            self::Mixed => 'Mixte',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Broiler => 'warning',
            self::Layer => 'success',
            self::Breeder => 'info',
            self::Mixed => 'gray',
        };
    }

    public function tracksWeight(): bool
    {
        return $this !== self::Layer;
    }

    public function tracksEggs(): bool
    {
        return $this !== self::Broiler;
    }
}
