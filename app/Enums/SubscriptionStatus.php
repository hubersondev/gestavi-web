<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Cycle de vie d'un abonnement : essai gratuit, actif (payé), annulé (accès maintenu jusqu'à
 * l'échéance), expiré (échéance passée, organisation ramenée au plan gratuit).
 */
enum SubscriptionStatus: string implements HasColor, HasLabel
{
    case Trial = 'trial';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Trial => 'Essai',
            self::Active => 'Actif',
            self::Cancelled => 'Annulé',
            self::Expired => 'Expiré',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Trial => 'info',
            self::Active => 'success',
            self::Cancelled => 'gray',
            self::Expired => 'danger',
        };
    }

    /** Statuts qui donnent encore accès au plan (tant que l'échéance n'est pas passée). */
    public static function grantingAccess(): array
    {
        return [self::Trial, self::Active, self::Cancelled];
    }
}
