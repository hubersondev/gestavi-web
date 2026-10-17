<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Importance d'une annonce de la plateforme aux organisations.
 */
enum AnnouncementLevel: string implements HasColor, HasLabel
{
    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';

    public function getLabel(): string
    {
        return match ($this) {
            self::Info => 'Information',
            self::Warning => 'Important',
            self::Danger => 'Urgent',
        };
    }

    public function getColor(): string
    {
        return $this->value;
    }

    /** Statut de notification correspondant (filtrage par préférences). */
    public function notificationStatus(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Warning => 'warning',
            self::Danger => 'danger',
        };
    }
}
