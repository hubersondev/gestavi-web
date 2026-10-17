<?php

namespace App\Exceptions;

use Exception;

class BankReconciliationException extends Exception
{
    public static function alreadyOpen(string $accountName): self
    {
        return new self("Un rapprochement est déjà en cours sur le compte \"{$accountName}\". Clôturez-le ou supprimez-le avant d'en ouvrir un autre.");
    }

    public static function completed(): self
    {
        return new self('Ce rapprochement est clôturé : il ne peut plus être modifié.');
    }

    public static function unbalanced(float $difference): self
    {
        return new self(sprintf(
            'Écart de %s XOF entre le solde du relevé et le solde pointé : rapprochez ou pointez les mouvements manquants avant de clôturer.',
            number_format($difference, 0, ',', ' '),
        ));
    }

    public static function foreignTransaction(int $transactionId): self
    {
        return new self("La transaction #{$transactionId} n'appartient pas au compte rapproché.");
    }

    public static function transactionAlreadyReconciled(int $transactionId): self
    {
        return new self("La transaction #{$transactionId} est déjà pointée dans un autre rapprochement.");
    }

    public static function lineAlreadyMatched(): self
    {
        return new self('Cette ligne de relevé est déjà associée à une transaction.');
    }

    public static function amountMismatch(float $lineAmount, float $transactionAmount): self
    {
        return new self(sprintf(
            'Le montant de la ligne (%s XOF) ne correspond pas à celui de la transaction (%s XOF).',
            number_format($lineAmount, 0, ',', ' '),
            number_format($transactionAmount, 0, ',', ' '),
        ));
    }

    /**
     * @param  list<string>  $missing
     */
    public static function unreadableStatement(array $missing): self
    {
        return new self('Colonnes introuvables dans le relevé : '.implode(', ', $missing).'. Attendu : une colonne Date, une colonne Libellé et une colonne Montant (ou Débit et Crédit).');
    }

    public static function cannotDeleteCompleted(): self
    {
        return new self('Un rapprochement clôturé ne se supprime pas : il fait partie de la piste d\'audit.');
    }
}
