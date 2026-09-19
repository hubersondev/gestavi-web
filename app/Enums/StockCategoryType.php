<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StockCategoryType: string implements HasColor, HasIcon, HasLabel
{
    case Feed = 'feed';
    case Medicine = 'medicine';
    case Material = 'material';
    case Packaging = 'packaging';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Feed => 'Aliment',
            self::Medicine => 'Médicament / Vaccin',
            self::Material => 'Matériel',
            self::Packaging => 'Emballage',
            self::Other => 'Autre',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Feed => 'warning',
            self::Medicine => 'success',
            self::Material => 'gray',
            self::Packaging => 'info',
            self::Other => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Feed => 'heroicon-o-beaker',
            self::Medicine => 'heroicon-o-heart',
            self::Material => 'heroicon-o-wrench-screwdriver',
            self::Packaging => 'heroicon-o-archive-box',
            self::Other => 'heroicon-o-tag',
        };
    }
}
