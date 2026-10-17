<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BankStatementLineStatus: string implements HasColor, HasLabel
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Ignored = 'ignored';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unmatched => 'À rapprocher',
            self::Matched => 'Rapprochée',
            self::Ignored => 'Ignorée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unmatched => 'warning',
            self::Matched => 'success',
            self::Ignored => 'gray',
        };
    }
}
