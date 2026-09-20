<?php

namespace App\Models\Concerns;

/**
 * Totaux d'un document commercial (facture, devis) à partir de ses lignes :
 * sous-total, remise (en % ou montant), TVA, total TTC. Une seule formule
 * partagée au lieu d'une copie par modèle.
 */
trait CalculatesDocumentTotals
{
    /**
     * @return array{subtotal: float, discount_amount: float, tax_amount: float, total: float}
     */
    public function computeTotalsFromLines(): array
    {
        $subtotal = (float) $this->items()->sum('total');

        $discountAmount = (float) $this->discount_percent > 0
            ? $subtotal * ((float) $this->discount_percent / 100)
            : (float) ($this->discount_amount ?? 0);

        $afterDiscount = $subtotal - $discountAmount;

        $taxAmount = (float) $this->tax_percent > 0
            ? $afterDiscount * ((float) $this->tax_percent / 100)
            : 0.0;

        return [
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($afterDiscount + $taxAmount, 2),
        ];
    }
}
