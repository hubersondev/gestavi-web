<?php

namespace App\Enums;

enum IncidentSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Faible',
            self::Medium => 'Moyen',
            self::High => 'Élevé',
            self::Critical => 'Critique',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'success',
            self::Medium => 'warning',
            self::High => 'danger',
            self::Critical => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Low => 'heroicon-o-check-circle',
            self::Medium => 'heroicon-o-exclamation-circle',
            self::High => 'heroicon-o-exclamation-triangle',
            self::Critical => 'heroicon-o-fire',
        };
    }
}
