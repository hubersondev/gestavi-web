<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayrollStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Validated => 'Validé',
            self::Paid => 'Payé',
            self::Cancelled => 'Annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Validated => 'info',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }
}
