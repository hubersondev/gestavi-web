<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Grandeurs prévues par bande ; chacune se lit directement dans les relevés quotidiens.
 */
enum PredictionType: string implements HasLabel
{
    case Mortality = 'mortality';
    case LayingRate = 'laying_rate';
    case Weight = 'weight';
    case FeedPerBird = 'feed_per_bird';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mortality => 'Mortalité cumulée',
            self::LayingRate => 'Taux de ponte',
            self::Weight => 'Poids moyen',
            self::FeedPerBird => 'Aliment par sujet',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Mortality, self::LayingRate => '%',
            self::Weight, self::FeedPerBird => 'g',
        };
    }

    /** Colonne du standard de souche qui sert de référence. */
    public function standardColumn(): string
    {
        return match ($this) {
            self::Mortality => 'expected_mortality_cumul',
            self::LayingRate => 'expected_laying_rate',
            self::Weight => 'expected_weight_g',
            self::FeedPerBird => 'expected_feed_per_day_g',
        };
    }

    /** Une valeur plus haute que prévu est-elle une mauvaise nouvelle ? */
    public function higherIsWorse(): bool
    {
        return $this === self::Mortality;
    }
}
