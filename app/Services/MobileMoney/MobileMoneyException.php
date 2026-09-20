<?php

namespace App\Services\MobileMoney;

use RuntimeException;

class MobileMoneyException extends RuntimeException
{
    public static function invalidPayload(string $operator, string $reason): self
    {
        return new self("Notification {$operator} invalide : {$reason}.");
    }

    public static function unknownOrganisation(string $operator, ?string $merchant): self
    {
        return new self(sprintf(
            'Notification %s : aucun compte mobile money ne correspond au marchand « %s ». Renseignez le numéro marchand sur le compte de l\'organisation.',
            $operator,
            $merchant ?? '—',
        ));
    }

    public static function alreadyProcessed(string $externalId): self
    {
        return new self("La transaction {$externalId} a déjà été rapprochée ou ignorée.");
    }

    public static function amountMismatch(string $invoiceNumber, float $balance, float $amount): self
    {
        return new self(sprintf(
            'La facture %s a un solde dû de %s XOF, incompatible avec l\'encaissement de %s XOF.',
            $invoiceNumber,
            number_format($balance, 0, ',', ' '),
            number_format($amount, 0, ',', ' '),
        ));
    }
}
