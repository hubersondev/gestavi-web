<?php

namespace App\Services;

use App\Enums\UiTheme;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;

/**
 * Apparence du panel Farm selon la charte GESTAVI, résolue par utilisateur à chaque requête :
 * thème (Sous-bois clair, Nuit sombre) et couleur principale parmi les présélections.
 * Les neutres sont des gris légèrement verdis communs aux deux thèmes.
 */
class UserAppearance
{
    public const string DEFAULT_PRIMARY = 'vert';

    /** @var array<string, array{label: string, hex: string}> */
    public const array PRIMARY_PRESETS = [
        'vert' => ['label' => 'Vert GESTAVI', 'hex' => '#15803d'],
        'emeraude' => ['label' => 'Émeraude', 'hex' => '#047857'],
        'olive' => ['label' => 'Olive', 'hex' => '#4d7c0f'],
        'lagune' => ['label' => 'Bleu lagune', 'hex' => '#0e7490'],
        'terre' => ['label' => 'Terre', 'hex' => '#b45309'],
    ];

    /** @var array<int, string> gris verdis : fond sable (50) à vert nuit (950) */
    public const array GRAY = [
        50 => '#f6f5ef',
        100 => '#ebe9df',
        200 => '#dfe5e0',
        300 => '#c9d3cc',
        400 => '#9fb3a7',
        500 => '#6b7f73',
        600 => '#4b5f54',
        700 => '#3f5247',
        800 => '#243229',
        900 => '#16241c',
        950 => '#0f1a14',
    ];

    public static function themeFor(?User $user): UiTheme
    {
        return $user?->ui_theme ?? UiTheme::SousBois;
    }

    public static function primaryKeyFor(?User $user): string
    {
        $key = $user?->ui_primary_color;

        return $key !== null && isset(self::PRIMARY_PRESETS[$key]) ? $key : self::DEFAULT_PRIMARY;
    }

    public static function primaryHexFor(?User $user): string
    {
        return self::PRIMARY_PRESETS[self::primaryKeyFor($user)]['hex'];
    }

    public static function isDarkFor(?User $user): bool
    {
        return self::themeFor($user)->isDark();
    }

    /**
     * Palette du panel pour l'utilisateur connecté (ou par défaut hors connexion).
     *
     * @return array<string, array<int, string>>
     */
    public static function panelColors(): array
    {
        $user = Filament::auth()->user();

        return [
            'primary' => Color::hex(self::primaryHexFor($user instanceof User ? $user : null)),
            'gray' => self::GRAY,
            'danger' => Color::Rose,
            'warning' => Color::Amber,
            'success' => Color::Emerald,
            'info' => Color::Blue,
        ];
    }

    public static function currentIsDark(): bool
    {
        $user = Filament::auth()->user();

        return self::isDarkFor($user instanceof User ? $user : null);
    }

    /**
     * @return array<string, string> clé => libellé, pour un champ de choix
     */
    public static function primaryOptions(): array
    {
        return array_map(fn (array $preset): string => $preset['label'], self::PRIMARY_PRESETS);
    }
}
