<?php

namespace App\Services\Sms;

/**
 * Normalisation des numéros de téléphone ivoiriens au format E.164 (+225XXXXXXXXXX).
 *
 * Formats acceptés : « 07 07 07 07 07 », « 0707070707 », « 2250707070707 »,
 * « +225 07 07 07 07 07 », « 00225… ». Les numéros à 8 chiffres (ancien plan
 * de numérotation, avant 2021) et tout autre format sont refusés (null).
 */
final class PhoneNumber
{
    public static function normalize(?string $raw, ?string $countryCode = null): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $countryCode = $countryCode ?? (string) config('sms.default_country_code', '225');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Numéro national à 10 chiffres commençant par 0 (01, 05, 07 …)
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '+'.$countryCode.$digits;
        }

        // Indicatif + 10 chiffres nationaux
        if (strlen($digits) === strlen($countryCode) + 10 && str_starts_with($digits, $countryCode) && $digits[strlen($countryCode)] === '0') {
            return '+'.$digits;
        }

        return null;
    }

    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }

    /**
     * Deux numéros désignent-ils la même ligne (après normalisation) ?
     */
    public static function same(?string $a, ?string $b): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);

        return $na !== null && $na === $nb;
    }
}
