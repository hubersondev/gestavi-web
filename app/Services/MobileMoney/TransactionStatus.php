<?php

namespace App\Services\MobileMoney;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransactionStatus: string implements HasColor, HasLabel
{
    case Received = 'received';
    case Matched = 'matched';
    case Ignored = 'ignored';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'À rapprocher',
            self::Matched => 'Rapproché',
            self::Ignored => 'Ignoré',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received => 'warning',
            self::Matched => 'success',
            self::Ignored => 'gray',
        };
    }
}
