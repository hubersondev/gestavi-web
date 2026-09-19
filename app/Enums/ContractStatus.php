<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContractStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Renewed = 'renewed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Active => 'Actif',
            self::Expired => 'Expiré',
            self::Renewed => 'Renouvelé',
            self::Cancelled => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Active => 'success',
            self::Expired => 'danger',
            self::Renewed => 'info',
            self::Cancelled => 'gray',
        };
    }
}
