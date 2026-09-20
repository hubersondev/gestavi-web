<?php

namespace App\Services\MobileMoney;

use Filament\Support\Contracts\HasLabel;

enum Direction: string implements HasLabel
{
    case In = 'in';
    case Out = 'out';

    public function getLabel(): string
    {
        return match ($this) {
            self::In => 'Encaissement',
            self::Out => 'Décaissement',
        };
    }
}
