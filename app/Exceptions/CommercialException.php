<?php

namespace App\Exceptions;

use Exception;

class CommercialException extends Exception
{
    public static function creditLimitExceeded(string $customerName, float $limit, float $newBalance): self
    {
        return new self(sprintf(
            'Plafond de crédit dépassé pour "%s". Limite : %s XOF, solde après opération : %s XOF.',
            $customerName,
            number_format($limit, 0, ',', ' '),
            number_format($newBalance, 0, ',', ' '),
        ));
    }

    public static function saleAlreadyCancelled(string $invoiceNumber): self
    {
        return new self("La facture {$invoiceNumber} est déjà annulée.");
    }

    public static function saleAlreadyPaid(string $invoiceNumber): self
    {
        return new self("La facture {$invoiceNumber} est déjà intégralement payée.");
    }

    public static function paymentExceedsBalance(string $invoiceNumber, float $balance, float $attempted): self
    {
        return new self(sprintf(
            'Le paiement de %s XOF dépasse le solde dû de la facture %s (%s XOF).',
            number_format($attempted, 0, ',', ' '),
            $invoiceNumber,
            number_format($balance, 0, ',', ' '),
        ));
    }

    public static function quoteNotAccepted(string $quoteNumber): self
    {
        return new self("Le devis {$quoteNumber} doit être accepté avant d'être converti en facture.");
    }

    public static function quoteAlreadyConverted(string $quoteNumber): self
    {
        return new self("Le devis {$quoteNumber} a déjà été converti en facture.");
    }

    public static function emptyItems(): self
    {
        return new self('Impossible de créer le document : aucune ligne article n\'a été renseignée.');
    }

    public static function invalidAmount(): self
    {
        return new self('Le montant doit être strictement supérieur à zéro.');
    }

    public static function creditNoteNotApplicable(string $creditNoteNumber): self
    {
        return new self("L'avoir {$creditNoteNumber} ne peut pas être imputé : il doit être à l'état \"Émis\" et la facture associée ne doit pas être annulée.");
    }

    public static function creditNoteAlreadyApplied(string $creditNoteNumber): self
    {
        return new self("L'avoir {$creditNoteNumber} est déjà imputé et ne peut plus être annulé.");
    }
}
