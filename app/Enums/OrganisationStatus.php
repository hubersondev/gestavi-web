<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * État d'une organisation cliente, réglé par l'administrateur de la plateforme :
 * - active : usage normal ;
 * - lecture seule : les membres consultent tout mais n'écrivent plus rien (panel et API) ;
 * - suspendue : plus aucun accès aux données, panel et API compris.
 */
enum OrganisationStatus: string implements HasColor, HasDescription, HasLabel
{
    case Active = 'active';
    case ReadOnly = 'read_only';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::ReadOnly => 'Lecture seule',
            self::Suspended => 'Suspendue',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::ReadOnly => 'warning',
            self::Suspended => 'danger',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Active => 'Usage normal : consultation et saisie.',
            self::ReadOnly => 'Les membres consultent leurs données mais ne peuvent plus rien créer, modifier ni supprimer.',
            self::Suspended => 'Plus aucun accès aux données, sur le web comme sur le mobile.',
        };
    }

    /** L'organisation peut-elle écrire dans ses données ? */
    public function allowsWrites(): bool
    {
        return $this === self::Active;
    }

    /** Les membres peuvent-ils encore ouvrir l'espace de l'organisation ? */
    public function allowsAccess(): bool
    {
        return $this !== self::Suspended;
    }
}
