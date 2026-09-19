<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MaintenanceType: string implements HasColor, HasIcon, HasLabel
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';
    case Inspection = 'inspection';
    case Cleaning = 'cleaning';
    case Calibration = 'calibration';
    case Replacement = 'replacement';

    public function getLabel(): string
    {
        return match ($this) {
            self::Preventive => 'Préventive',
            self::Corrective => 'Corrective',
            self::Inspection => 'Inspection',
            self::Cleaning => 'Nettoyage',
            self::Calibration => 'Calibration',
            self::Replacement => 'Remplacement',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Preventive => 'info',
            self::Corrective => 'danger',
            self::Inspection => 'primary',
            self::Cleaning => 'success',
            self::Calibration => 'warning',
            self::Replacement => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Preventive => 'heroicon-o-shield-check',
            self::Corrective => 'heroicon-o-wrench-screwdriver',
            self::Inspection => 'heroicon-o-eye',
            self::Cleaning => 'heroicon-o-sparkles',
            self::Calibration => 'heroicon-o-adjustments-horizontal',
            self::Replacement => 'heroicon-o-arrow-path',
        };
    }
}
