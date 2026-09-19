<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;
use App\Exceptions\CommercialException;
use App\Models\Customer;
use App\Models\Flock;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class SaleService
{
    /**
     * Crée une facture depuis ses données et ses lignes.
     * Vérifie le plafond de crédit du client si applicable.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    public function createSale(array $data, array $items): Sale
    {
        if (empty($items)) {
            throw CommercialException::emptyItems();
        }

        $this->assertFlocksNotUnderWithdrawal($items, $data['sale_date'] ?? null);

        return DB::transaction(function () use ($data, $items): Sale {
            $sale = Sale::create($data);

            foreach ($items as $index => $item) {
                $sale->items()->create(array_merge($item, ['sort_order' => $index]));
            }

            // Recalculer les totaux à partir des items créés
            $sale->refresh()->calculateTotals();

            // Vérifier le crédit client après calcul du total
            if ($sale->customer_id && $sale->status !== SaleStatus::Draft) {
                $customer = Customer::find($sale->customer_id);

                if ($customer && (float) $customer->credit_limit > 0) {
                    $newBalance = (float) $customer->balance + (float) $sale->total;

                    if ($newBalance > (float) $customer->credit_limit) {
                        throw CommercialException::creditLimitExceeded(
                            $customer->name,
                            (float) $customer->credit_limit,
                            $newBalance,
                        );
                    }
                }
            }

            // Passer de Draft à Pending si non brouillon
            if ($sale->status === SaleStatus::Draft && (float) $sale->total > 0) {
                $sale->update(['status' => SaleStatus::Pending]);
            }

            $sale->customer?->recalculateBalance();

            return $sale->fresh(['items', 'customer']);
        });
    }

    /**
     * Remplace les lignes d'une facture (édition). Interdit dès qu'un paiement existe.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function syncSaleItems(Sale $sale, array $items): Sale
    {
        if ($sale->isLocked()) {
            throw CommercialException::saleLocked($sale->invoice_number);
        }

        if (empty($items)) {
            throw CommercialException::emptyItems();
        }

        $this->assertFlocksNotUnderWithdrawal($items, $sale->sale_date);

        return DB::transaction(function () use ($sale, $items): Sale {
            $this->syncLines($sale->items(), $items, ['product_name', 'description', 'unit', 'quantity', 'unit_price', 'discount_percent', 'flock_id', 'is_live_birds']);

            $sale->refresh()->calculateTotals();
            $sale->customer?->recalculateBalance();

            return $sale->fresh(['items', 'customer']);
        });
    }

    /**
     * Crée un devis et ses lignes.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $items
     */
    public function createQuote(array $data, array $items): Quote
    {
        if (empty($items)) {
            throw CommercialException::emptyItems();
        }

        return DB::transaction(function () use ($data, $items): Quote {
            $quote = Quote::create($data);

            foreach ($items as $index => $item) {
                $quote->items()->create(array_merge($this->only($item, ['product_name', 'description', 'unit', 'quantity', 'unit_price', 'discount_percent', 'flock_id']), ['sort_order' => $index]));
            }

            $quote->refresh()->calculateTotals();

            return $quote->fresh(['items', 'customer']);
        });
    }

    /**
     * Remplace les lignes d'un devis (édition). Un devis converti n'est plus modifiable.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function syncQuoteItems(Quote $quote, array $items): Quote
    {
        if ($quote->isConverted()) {
            throw CommercialException::quoteAlreadyConverted($quote->quote_number);
        }

        if (empty($items)) {
            throw CommercialException::emptyItems();
        }

        return DB::transaction(function () use ($quote, $items): Quote {
            $this->syncLines($quote->items(), $items, ['product_name', 'description', 'unit', 'quantity', 'unit_price', 'discount_percent', 'flock_id']);

            $quote->refresh()->calculateTotals();

            return $quote->fresh(['items', 'customer']);
        });
    }

    /**
     * Synchronise des lignes saisies (avec `id` optionnel) sur une relation HasMany :
     * mise à jour des lignes existantes, création des nouvelles, suppression des absentes.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  list<string>  $columns
     */
    private function syncLines(\Illuminate\Database\Eloquent\Relations\HasMany $relation, array $items, array $columns): void
    {
        $keptIds = [];

        foreach (array_values($items) as $index => $item) {
            $attributes = array_merge($this->only($item, $columns), ['sort_order' => $index]);
            $existing = ! empty($item['id']) ? $relation->getQuery()->clone()->find($item['id']) : null;

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->getKey();
            } else {
                $keptIds[] = $relation->create($attributes)->getKey();
            }
        }

        $relation->getQuery()->clone()->whereNotIn('id', $keptIds)->get()->each->delete();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function only(array $item, array $columns): array
    {
        return array_intersect_key($item, array_flip($columns));
    }

    /**
     * Garde-fou sanitaire : aucune ligne ne peut porter sur une bande dont un
     * traitement impose encore un délai d'attente (résidus dans la viande ou les œufs).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function assertFlocksNotUnderWithdrawal(array $items, mixed $saleDate = null): void
    {
        $flockIds = collect($items)->pluck('flock_id')->filter()->unique()->values();

        if ($flockIds->isEmpty()) {
            return;
        }

        Flock::query()->whereIn('id', $flockIds)->get()->each(function (Flock $flock) use ($saleDate): void {
            if ($flock->isUnderWithdrawal($saleDate)) {
                throw CommercialException::flockUnderWithdrawal(
                    $flock->code,
                    $flock->getWithdrawalUntil()?->format('d/m/Y') ?? '',
                );
            }
        });
    }

    /**
     * Enregistre un paiement sur une facture (avec écriture comptable si compte fourni).
     */
    public function recordPayment(
        Sale $sale,
        float $amount,
        PaymentMethod $method,
        ?int $accountId = null,
        ?string $reference = null,
        ?\DateTimeInterface $paidAt = null,
    ): Payment {
        if ($amount <= 0) {
            throw CommercialException::invalidAmount();
        }

        if ($sale->isCancelled()) {
            throw CommercialException::saleAlreadyCancelled($sale->invoice_number);
        }

        if ($sale->isPaid()) {
            throw CommercialException::saleAlreadyPaid($sale->invoice_number);
        }

        $balance = (float) $sale->balance;

        if ($amount > $balance + 0.01) {
            throw CommercialException::paymentExceedsBalance($sale->invoice_number, $balance, $amount);
        }

        return $sale->addPayment($amount, $method, $accountId, $reference, $paidAt);
    }

    /**
     * Annule une facture et contrepasse les paiements comptables liés.
     */
    public function cancelSale(Sale $sale): Sale
    {
        if ($sale->isCancelled()) {
            throw CommercialException::saleAlreadyCancelled($sale->invoice_number);
        }

        $sale->cancel();

        return $sale->fresh();
    }

    /**
     * Convertit un devis accepté en facture avec reprise des lignes.
     */
    public function convertQuoteToSale(Quote $quote, ?\DateTimeInterface $dueDate = null): Sale
    {
        if (! $quote->isAccepted()) {
            throw CommercialException::quoteNotAccepted($quote->quote_number);
        }

        if ($quote->converted_sale_id !== null) {
            throw CommercialException::quoteAlreadyConverted($quote->quote_number);
        }

        return DB::transaction(function () use ($quote, $dueDate): Sale {
            $sale = Sale::create([
                'organisation_id' => $quote->organisation_id,
                'farm_id' => $quote->farm_id,
                'customer_id' => $quote->customer_id,
                'user_id' => auth()->id(),
                'quote_id' => $quote->id,
                'sale_date' => now()->toDateString(),
                'due_date' => $dueDate,
                'discount_percent' => $quote->discount_percent,
                'tax_percent' => $quote->tax_percent,
                'status' => SaleStatus::Pending,
            ]);

            foreach ($quote->items as $quoteItem) {
                $sale->items()->create([
                    'flock_id' => $quoteItem->flock_id,
                    'product_name' => $quoteItem->product_name,
                    'description' => $quoteItem->description,
                    'unit' => $quoteItem->unit,
                    'quantity' => $quoteItem->quantity,
                    'unit_price' => $quoteItem->unit_price,
                    'discount_percent' => $quoteItem->discount_percent,
                    'sort_order' => $quoteItem->sort_order,
                ]);
            }

            $sale->refresh()->calculateTotals();

            $quote->update([
                'converted_sale_id' => $sale->id,
                'status' => QuoteStatus::Converted,
            ]);

            return $sale->fresh(['items', 'customer']);
        });
    }
}
