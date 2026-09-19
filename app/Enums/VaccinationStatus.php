<?php

namespace App\Enums;

enum VaccinationStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Skipped = 'skipped';
    case Delayed = 'delayed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Done => 'Effectuée',
            self::Skipped => 'Ignorée',
            self::Delayed => 'Reportée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Done => 'success',
            self::Skipped => 'gray',
            self::Delayed => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Done => 'heroicon-o-check-circle',
            self::Skipped => 'heroicon-o-x-circle',
            self::Delayed => 'heroicon-o-exclamation-triangle',
        };
    }
}
