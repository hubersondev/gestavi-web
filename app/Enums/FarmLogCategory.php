<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum FarmLogCategory: string implements HasColor, HasIcon, HasLabel
{
    case Health = 'health';
    case Mortality = 'mortality';
    case Production = 'production';
    case Feeding = 'feeding';
    case Environment = 'environment';
    case Maintenance = 'maintenance';
    case Visit = 'visit';
    case Delivery = 'delivery';
    case Incident = 'incident';
    case Observation = 'observation';
    case Decision = 'decision';
    case Reminder = 'reminder';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Health => 'Santé',
            self::Mortality => 'Mortalité',
            self::Production => 'Production',
            self::Feeding => 'Alimentation',
            self::Environment => 'Environnement',
            self::Maintenance => 'Maintenance',
            self::Visit => 'Visite',
            self::Delivery => 'Livraison',
            self::Incident => 'Incident',
            self::Observation => 'Observation',
            self::Decision => 'Décision',
            self::Reminder => 'Rappel',
            self::Other => 'Autre',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Health => 'success',
            self::Mortality => 'danger',
            self::Production => 'info',
            self::Feeding => 'warning',
            self::Environment => 'gray',
            self::Maintenance => 'warning',
            self::Visit => 'primary',
            self::Delivery => 'info',
            self::Incident => 'danger',
            self::Observation => 'gray',
            self::Decision => 'primary',
            self::Reminder => 'warning',
            self::Other => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Health => 'heroicon-o-heart',
            self::Mortality => 'heroicon-o-x-circle',
            self::Production => 'heroicon-o-arrow-trending-up',
            self::Feeding => 'heroicon-o-beaker',
            self::Environment => 'heroicon-o-sun',
            self::Maintenance => 'heroicon-o-wrench-screwdriver',
            self::Visit => 'heroicon-o-user-circle',
            self::Delivery => 'heroicon-o-truck',
            self::Incident => 'heroicon-o-exclamation-triangle',
            self::Observation => 'heroicon-o-eye',
            self::Decision => 'heroicon-o-check-badge',
            self::Reminder => 'heroicon-o-bell',
            self::Other => 'heroicon-o-document-text',
        };
    }
}
