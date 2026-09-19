<?php

namespace App\Exceptions;

use Exception;

class StockException extends Exception
{
    public static function invalidQuantity(): self
    {
        return new self('La quantité doit être strictement supérieure à zéro.');
    }

    public static function insufficientStock(string $stockName, float $requested, float $available): self
    {
        return new self(sprintf(
            'Stock insuffisant pour "%s". Demandé : %s, disponible : %s.',
            $stockName,
            rtrim(rtrim(number_format($requested, 2, ',', ' '), '0'), ','),
            rtrim(rtrim(number_format($available, 2, ',', ' '), '0'), ','),
        ));
    }

    public static function emptyItems(): self
    {
        return new self('Impossible de créer le bon de commande : aucune ligne n\'a été renseignée.');
    }

    public static function receptionExceedsOrdered(string $productName, float $requested, float $remaining): self
    {
        return new self(sprintf(
            'Réception refusée pour « %s » : %s demandés alors qu\'il ne reste que %s à recevoir.',
            $productName,
            rtrim(rtrim(number_format($requested, 2, ',', ' '), '0'), ','),
            rtrim(rtrim(number_format($remaining, 2, ',', ' '), '0'), ','),
        ));
    }

    public static function purchaseOrderNotReceivable(string $orderNumber): self
    {
        return new self("Le bon de commande {$orderNumber} ne peut pas être réceptionné à cet état.");
    }

    public static function purchaseOrderAlreadyCancelled(string $orderNumber): self
    {
        return new self("Le bon de commande {$orderNumber} est déjà annulé.");
    }

    public static function purchaseOrderCannotBeCancelled(string $orderNumber): self
    {
        return new self("Le bon de commande {$orderNumber} ne peut plus être annulé à cet état.");
    }

    public static function lotBlocked(string $lotNumber): self
    {
        return new self("Le lot {$lotNumber} est bloqué et ne peut pas être consommé.");
    }
}
