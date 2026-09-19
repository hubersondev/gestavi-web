<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CustomerType: string implements HasColor, HasIcon, HasLabel
{
    case Particulier = 'particulier';
    case Revendeur = 'revendeur';
    case Restaurant = 'restaurant';
    case Hotel = 'hotel';
    case Supermarche = 'supermarche';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Particulier => 'Particulier',
            self::Revendeur => 'Revendeur',
            self::Restaurant => 'Restaurant',
            self::Hotel => 'Hôtel',
            self::Supermarche => 'Supermarché',
            self::Autre => 'Autre',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Particulier => 'gray',
            self::Revendeur => 'primary',
            self::Restaurant => 'warning',
            self::Hotel => 'info',
            self::Supermarche => 'success',
            self::Autre => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Particulier => 'heroicon-o-user',
            self::Revendeur => 'heroicon-o-shopping-bag',
            self::Restaurant => 'heroicon-o-cake',
            self::Hotel => 'heroicon-o-building-office',
            self::Supermarche => 'heroicon-o-building-storefront',
            self::Autre => 'heroicon-o-tag',
        };
    }
}
