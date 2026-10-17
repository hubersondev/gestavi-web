<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Encaissement d'un abonnement : une ligne par paiement (souscription payée, renouvellement),
 * numérotée ABO-AAAA-NNNNN pour la facture PDF remise à l'organisation.
 */
class SubscriptionPayment extends Model
{
    protected $fillable = [
        'organisation_id', 'subscription_id', 'invoice_number', 'plan_name', 'billing_cycle',
        'period_from', 'period_to', 'amount', 'currency', 'paid_at',
        'payment_method', 'payment_reference', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'period_from' => 'date',
            'period_to' => 'date',
            'paid_at' => 'date',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePaidBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('paid_at', '>=', $from)->whereDate('paid_at', '<=', $to);
    }

    /**
     * Prochain numéro de facture de l'année (séquence continue, verrouillée le temps de la transaction).
     */
    public static function nextInvoiceNumber(?int $year = null): string
    {
        $year ??= (int) today()->year;
        $prefix = sprintf('ABO-%d-', $year);

        $last = DB::transaction(function () use ($prefix): ?string {
            return static::query()
                ->where('invoice_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('invoice_number')
                ->value('invoice_number');
        });

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /** « 01/10/2026 → 31/10/2026 ». */
    public function periodLabel(): string
    {
        return $this->period_from?->format('d/m/Y').' → '.$this->period_to?->format('d/m/Y');
    }
}
