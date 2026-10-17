<?php

namespace App\Models;

use App\Enums\BankStatementLineStatus;
use App\Enums\TransactionType;
use Database\Factories\BankStatementLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne d'un relevé importé : montant signé (positif = entrée sur le compte, négatif = sortie),
 * associée à une transaction de l'application ou ignorée (frais déjà saisis autrement, doublon…).
 */
class BankStatementLine extends Model
{
    /** @use HasFactory<BankStatementLineFactory> */
    use HasFactory;

    protected $fillable = [
        'bank_reconciliation_id', 'line_date', 'label', 'amount', 'reference',
        'status', 'matched_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'line_date' => 'date',
            'amount' => 'decimal:2',
            'status' => BankStatementLineStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (BankStatementLine $line): void {
            if ($line->isMatched() && $line->reconciliation?->isDraft() && $line->matchedTransaction?->is_reconciled) {
                $line->matchedTransaction->unreconcile();
            }
        });
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function matchedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }

    /** Sens attendu de la transaction correspondante. */
    public function expectedTransactionType(): TransactionType
    {
        return (float) $this->amount >= 0 ? TransactionType::Credit : TransactionType::Debit;
    }

    public function absoluteAmount(): float
    {
        return abs((float) $this->amount);
    }

    public function isMatched(): bool
    {
        return $this->status === BankStatementLineStatus::Matched;
    }

    public function isUnmatched(): bool
    {
        return $this->status === BankStatementLineStatus::Unmatched;
    }

    public function isIgnored(): bool
    {
        return $this->status === BankStatementLineStatus::Ignored;
    }
}
