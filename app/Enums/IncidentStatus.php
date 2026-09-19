<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Detected = 'detected';
    case UnderTreatment = 'under_treatment';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';
    case Chronic = 'chronic';

    public function label(): string
    {
        return match ($this) {
            self::Detected => 'Détecté',
            self::UnderTreatment => 'En traitement',
            self::Monitoring => 'Surveillance',
            self::Resolved => 'Résolu',
            self::Chronic => 'Chronique',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Detected => 'warning',
            self::UnderTreatment => 'info',
            self::Monitoring => 'primary',
            self::Resolved => 'success',
            self::Chronic => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Detected => 'heroicon-o-exclamation-triangle',
            self::UnderTreatment => 'heroicon-o-beaker',
            self::Monitoring => 'heroicon-o-eye',
            self::Resolved => 'heroicon-o-check-circle',
            self::Chronic => 'heroicon-o-arrow-path',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Detected, self::UnderTreatment, self::Monitoring]);
    }
}
