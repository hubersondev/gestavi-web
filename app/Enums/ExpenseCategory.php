<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ExpenseCategory: string implements HasColor, HasIcon, HasLabel
{
    case Feed = 'feed';
    case Medicine = 'medicine';
    case Veterinary = 'veterinary';
    case Fuel = 'fuel';
    case Utilities = 'utilities';
    case Rent = 'rent';
    case Maintenance = 'maintenance';
    case Equipment = 'equipment';
    case Transport = 'transport';
    case Services = 'services';
    case Taxes = 'taxes';
    case Salaries = 'salaries';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Feed => 'Aliment',
            self::Medicine => 'Médicaments / Vaccins',
            self::Veterinary => 'Soins vétérinaires',
            self::Fuel => 'Carburant',
            self::Utilities => 'Eau / Électricité',
            self::Rent => 'Loyer / Location',
            self::Maintenance => 'Entretien bâtiment',
            self::Equipment => 'Équipement / Matériel',
            self::Transport => 'Transport / Logistique',
            self::Services => 'Services extérieurs',
            self::Taxes => 'Taxes / Impôts',
            self::Salaries => 'Salaires',
            self::Other => 'Autre',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Feed => 'warning',
            self::Medicine, self::Veterinary => 'success',
            self::Fuel => 'danger',
            self::Utilities => 'info',
            self::Rent => 'primary',
            self::Maintenance, self::Equipment => 'gray',
            self::Transport => 'warning',
            self::Services => 'info',
            self::Taxes => 'danger',
            self::Salaries => 'primary',
            self::Other => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Feed => 'heroicon-o-beaker',
            self::Medicine => 'heroicon-o-heart',
            self::Veterinary => 'heroicon-o-user-circle',
            self::Fuel => 'heroicon-o-fire',
            self::Utilities => 'heroicon-o-bolt',
            self::Rent => 'heroicon-o-home',
            self::Maintenance => 'heroicon-o-wrench-screwdriver',
            self::Equipment => 'heroicon-o-cube',
            self::Transport => 'heroicon-o-truck',
            self::Services => 'heroicon-o-briefcase',
            self::Taxes => 'heroicon-o-document-text',
            self::Salaries => 'heroicon-o-banknotes',
            self::Other => 'heroicon-o-tag',
        };
    }
}
