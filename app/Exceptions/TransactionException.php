<?php

namespace App\Exceptions;

use Exception;

class TransactionException extends Exception
{
    public static function invalidAmount(): self
    {
        return new self('Le montant doit être strictement supérieur à zéro.');
    }

    public static function accountNotActive(string $accountName): self
    {
        return new self("Le compte \"{$accountName}\" n'est pas actif.");
    }

    public static function alreadyReversed(int $id): self
    {
        return new self("La transaction #{$id} a déjà été contrepassée.");
    }

    public static function lockedByReconciliation(int $id): self
    {
        return new self("La transaction #{$id} est pointée dans un rapprochement bancaire clôturé : elle ne peut plus être dépointée.");
    }

    public static function cannotReverseReconciled(int $id): self
    {
        return new self("La transaction #{$id} est rapprochée. Annulez d'abord le rapprochement avant de la contrepasser.");
    }

    public static function integrityError(string $accountName, float $expected, float $actual): self
    {
        return new self(sprintf(
            'Écart d\'intégrité sur le compte "%s". Attendu : %s XOF, actuel : %s XOF.',
            $accountName,
            number_format($expected, 0, ',', ' '),
            number_format($actual, 0, ',', ' '),
        ));
    }
}
