<?php

namespace App\Exceptions;

use Exception;

/**
 * Règles métier des abonnements : limites du plan, états incompatibles.
 */
class SubscriptionException extends Exception
{
    public static function limitExceeded(string $resource, int $used, int $max, string $planName): self
    {
        return new self(sprintf(
            'Le plan %s autorise %d %s au maximum ; l\'organisation en compte déjà %d.',
            $planName,
            $max,
            $resource,
            $used,
        ));
    }

    public static function notRenewable(string $status): self
    {
        return new self("Un abonnement « {$status} » ne peut pas être renouvelé : créez un nouvel abonnement.");
    }

    public static function alreadyEnded(): self
    {
        return new self('Cet abonnement est déjà terminé.');
    }
}
