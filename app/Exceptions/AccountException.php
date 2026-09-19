<?php

namespace App\Exceptions;

use Exception;

class AccountException extends Exception
{
    public static function insufficientBalance(string $accountName, float $requested, float $available): self
    {
        return new self(sprintf(
            'Solde insuffisant sur le compte "%s". Demandé : %s XOF, disponible : %s XOF.',
            $accountName,
            number_format($requested, 0, ',', ' '),
            number_format($available, 0, ',', ' '),
        ));
    }

    public static function accountNotActive(string $accountName): self
    {
        return new self("Le compte \"{$accountName}\" n'est pas actif.");
    }

    public static function sameAccountTransfer(): self
    {
        return new self('Le compte source et le compte destinataire doivent être différents.');
    }

    public static function invalidAmount(): self
    {
        return new self('Le montant doit être strictement supérieur à zéro.');
    }

    public static function transferAlreadyProcessed(string $reference): self
    {
        return new self("Le transfert {$reference} a déjà été traité.");
    }

    public static function transferNotCompleted(string $reference): self
    {
        return new self("Le transfert {$reference} n'a pas été complété, aucune contrepassation possible.");
    }

    public static function transferCannotBeCancelled(string $reference): self
    {
        return new self("Le transfert {$reference} ne peut plus être annulé (déjà complété ou annulé).");
    }

    public static function organisationMismatch(): self
    {
        return new self('Les deux comptes doivent appartenir à la même organisation.');
    }
}
