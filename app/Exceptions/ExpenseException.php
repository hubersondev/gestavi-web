<?php

namespace App\Exceptions;

use Exception;

class ExpenseException extends Exception
{
    public static function invalidAmount(): self
    {
        return new self('Le montant doit être strictement supérieur à zéro.');
    }

    public static function alreadyPaid(string $expenseNumber): self
    {
        return new self("La dépense {$expenseNumber} est déjà intégralement payée.");
    }

    public static function alreadyCancelled(string $expenseNumber): self
    {
        return new self("La dépense {$expenseNumber} est déjà annulée.");
    }

    public static function paymentExceedsBalance(string $expenseNumber, float $balance, float $attempted): self
    {
        return new self(sprintf(
            'Le paiement de %s XOF dépasse le solde dû de la dépense %s (%s XOF).',
            number_format($attempted, 0, ',', ' '),
            $expenseNumber,
            number_format($balance, 0, ',', ' '),
        ));
    }

    public static function accountRequired(string $expenseNumber): self
    {
        return new self("Aucun compte n'est défini pour la dépense {$expenseNumber}. Sélectionnez un compte pour pouvoir enregistrer un paiement.");
    }

    public static function accountNotActive(string $accountName): self
    {
        return new self("Le compte \"{$accountName}\" n'est pas actif.");
    }

    public static function insufficientAccountBalance(string $accountName, float $requested, float $available): self
    {
        return new self(sprintf(
            'Solde insuffisant sur "%s" pour payer la dépense. Demandé : %s XOF, disponible : %s XOF.',
            $accountName,
            number_format($requested, 0, ',', ' '),
            number_format($available, 0, ',', ' '),
        ));
    }
}
