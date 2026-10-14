<?php

namespace App\Filament\Farm\Components;

use App\Services\CurrentFiscalYear;
use Filament\Forms\Components\DatePicker;

/**
 * Date d'une pièce (facture, dépense, mouvement…) : proposée dans l'exercice actif et accompagnée
 * d'un avertissement, non bloquant, quand la valeur saisie en sort.
 */
class FiscalYearDatePicker
{
    public static function make(string $name): DatePicker
    {
        return DatePicker::make($name)
            ->default(fn (): string => app(CurrentFiscalYear::class)->defaultDate()->toDateString())
            ->live(onBlur: true)
            ->helperText(fn (mixed $state): ?string => app(CurrentFiscalYear::class)->dateWarning($state));
    }
}
