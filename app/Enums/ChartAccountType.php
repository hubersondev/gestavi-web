<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Nature d'un compte du plan comptable.
 */
enum ChartAccountType: string implements HasLabel
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';
    case Treasury = 'treasury';

    public function getLabel(): string
    {
        return match ($this) {
            self::Asset => 'Actif',
            self::Liability => 'Passif (tiers)',
            self::Equity => 'Capitaux propres',
            self::Revenue => 'Produit',
            self::Expense => 'Charge',
            self::Treasury => 'Trésorerie',
        };
    }
}
