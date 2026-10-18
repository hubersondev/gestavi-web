<?php

namespace App\Exceptions;

use App\Services\BankReconciliationService;
use Exception;

class BankReconciliationException extends Exception
{
    public static function alreadyOpen(string $accountName): self
    {
        return new self("Un rapprochement est déjà en cours sur le compte \"{$accountName}\". Clôturez-le ou supprimez-le avant d'en ouvrir un autre.");
    }

    public static function notAfterLastCompleted(string $lastDate): self
    {
        return new self("Un rapprochement au {$lastDate} est déjà clôturé sur ce compte : le relevé doit être daté après cette date.");
    }

    public static function pointedAfterDate(int $count, string $date): self
    {
        return new self("{$count} transaction(s) pointée(s) dans ce rapprochement sont postérieures au {$date} : dépointez-les avant d'avancer la date du relevé.");
    }

    public static function completed(): self
    {
        return new self('Ce rapprochement est clôturé : il ne peut plus être modifié.');
    }

    public static function unbalanced(float $difference): self
    {
        return new self(sprintf(
            'Écart de %s entre le solde du relevé et le solde pointé : rapprochez ou pointez les mouvements manquants avant de clôturer.',
            BankReconciliationService::money($difference),
        ));
    }

    public static function foreignTransaction(int $transactionId): self
    {
        return new self("La transaction #{$transactionId} n'appartient pas au compte rapproché.");
    }

    public static function afterStatement(int $transactionId, string $date): self
    {
        return new self("La transaction #{$transactionId} est postérieure au relevé du {$date} : elle se pointe dans le rapprochement suivant.");
    }

    public static function transactionAlreadyReconciled(int $transactionId): self
    {
        return new self("La transaction #{$transactionId} est déjà pointée dans un autre rapprochement.");
    }

    public static function transactionAlreadyMatched(int $transactionId): self
    {
        return new self("La transaction #{$transactionId} est déjà associée à une autre ligne du relevé.");
    }

    public static function lineAlreadyMatched(): self
    {
        return new self('Cette ligne de relevé est déjà associée à une transaction.');
    }

    public static function amountMismatch(float $lineAmount, float $transactionAmount): self
    {
        return new self(sprintf(
            'Le montant de la ligne (%s) ne correspond pas à celui de la transaction (%s).',
            BankReconciliationService::money($lineAmount),
            BankReconciliationService::money($transactionAmount),
        ));
    }

    /**
     * @param  list<string>  $missing
     */
    public static function unreadableStatement(array $missing): self
    {
        return new self('Colonnes introuvables dans le relevé : '.implode(', ', $missing).'. Attendu : une ligne d\'en-tête avec une colonne Date et une colonne Montant (ou Débit et Crédit), plus éventuellement Libellé et Référence.');
    }

    public static function cannotDeleteCompleted(): self
    {
        return new self('Un rapprochement clôturé ne se supprime pas : il fait partie de la piste d\'audit.');
    }
}
