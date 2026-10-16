<?php

namespace App\Exceptions;

use RuntimeException;

class ForecastException extends RuntimeException
{
    public static function flockNotActive(string $flockCode): self
    {
        return new self("La bande {$flockCode} n'est pas active : pas de prévision.");
    }

    public static function insufficientData(string $flockCode, int $minimum): self
    {
        return new self("Données insuffisantes pour la bande {$flockCode} : au moins {$minimum} relevés sont nécessaires.");
    }
}
