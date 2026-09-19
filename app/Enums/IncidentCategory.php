<?php

namespace App\Enums;

enum IncidentCategory: string
{
    case Respiratory = 'respiratory';
    case Digestive = 'digestive';
    case Parasitic = 'parasitic';
    case Viral = 'viral';
    case Bacterial = 'bacterial';
    case Nutritional = 'nutritional';
    case Environmental = 'environmental';
    case Behavioral = 'behavioral';
    case Reproductive = 'reproductive';

    public function label(): string
    {
        return match ($this) {
            self::Respiratory => 'Respiratoire',
            self::Digestive => 'Digestif',
            self::Parasitic => 'Parasitaire',
            self::Viral => 'Viral',
            self::Bacterial => 'Bactérien',
            self::Nutritional => 'Nutritionnel',
            self::Environmental => 'Environnemental',
            self::Behavioral => 'Comportemental',
            self::Reproductive => 'Reproductif',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Respiratory, self::Viral, self::Bacterial => 'danger',
            self::Parasitic => 'warning',
            self::Nutritional, self::Environmental => 'info',
            default => 'gray',
        };
    }
}
