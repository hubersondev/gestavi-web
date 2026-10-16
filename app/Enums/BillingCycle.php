<?php

namespace App\Enums;

use App\Models\Plan;
use Filament\Support\Contracts\HasLabel;

/**
 * Périodicité de facturation d'un abonnement.
 */
enum BillingCycle: string implements HasLabel
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => 'Mensuel',
            self::Quarterly => 'Trimestriel',
            self::Yearly => 'Annuel',
        };
    }

    /** Depuis l'état d'un formulaire : cas de l'énumération ou valeur ; mensuel par défaut. */
    public static function coerce(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom((string) $value) ?? self::Monthly;
    }

    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Yearly => 12,
        };
    }

    /**
     * Prix d'une période pour un plan : tarif annuel du plan s'il existe pour le cycle annuel,
     * sinon le tarif mensuel multiplié par le nombre de mois.
     */
    public function priceFor(Plan $plan): float
    {
        if ($this === self::Yearly && $plan->yearly_price !== null && (float) $plan->yearly_price > 0) {
            return (float) $plan->yearly_price;
        }

        return round((float) $plan->monthly_price * $this->months(), 2);
    }
}
