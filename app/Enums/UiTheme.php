<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Thèmes de la charte GESTAVI, au choix de chaque utilisateur.
 */
enum UiTheme: string implements HasDescription, HasLabel
{
    case SousBois = 'sous-bois';
    case Nuit = 'nuit';

    public function getLabel(): string
    {
        return match ($this) {
            self::SousBois => 'Sous-bois',
            self::Nuit => 'Nuit',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SousBois => 'Clair : barre latérale vert profond, fond sable, cartes blanches. Pour le travail de jour.',
            self::Nuit => 'Sombre : fond vert nuit, accent vert clair. Repose les yeux le soir ou en bâtiment.',
        };
    }

    public function isDark(): bool
    {
        return $this === self::Nuit;
    }
}
