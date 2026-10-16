<?php

namespace App\Exceptions;

use RuntimeException;

class WeatherException extends RuntimeException
{
    public static function apiKeyMissing(): self
    {
        return new self('Clé OpenWeatherMap absente : renseignez OPENWEATHERMAP_API_KEY dans le fichier .env.');
    }

    public static function apiError(string $message, int $status = 0): self
    {
        return new self(trim("Erreur OpenWeatherMap ({$status}) : ".mb_strimwidth($message, 0, 200, '…')));
    }

    public static function noCoordinates(string $farmName): self
    {
        return new self("La ferme « {$farmName} » n'a ni coordonnées GPS ni ville géolocalisable.");
    }
}
