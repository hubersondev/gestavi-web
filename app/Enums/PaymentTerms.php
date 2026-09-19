<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentTerms: string implements HasLabel
{
    case Immediate = 'immediate';
    case OnDelivery = 'on_delivery';
    case Net7 = 'net_7';
    case Net15 = 'net_15';
    case Net30 = 'net_30';
    case Net60 = 'net_60';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Immediate => 'Paiement immédiat',
            self::OnDelivery => 'À la livraison',
            self::Net7 => 'Net 7 jours',
            self::Net15 => 'Net 15 jours',
            self::Net30 => 'Net 30 jours',
            self::Net60 => 'Net 60 jours',
            self::Custom => 'Conditions personnalisées',
        };
    }
}
