<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Enums\TransactionCategory;
use App\Exceptions\ExpenseException;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Expense extends Model
{
    use BelongsToOrganisation, HasFactory, LogsAuditTrail, SoftDeletes;

    protected $fillable = [
        'organisation_id', 'farm_id', 'flock_id',
        'supplier_id', 'account_id', 'user_id',
        'expense_number', 'expense_date', 'due_date', 'paid_at',
        'category', 'status',
        'amount', 'tax_percent', 'tax_amount', 'paid_amount', 'balance',
        'description', 'reference', 'attachment', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'status' => ExpenseStatus::class,
            'expense_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense): void {
            if ($expense->organisation_id === null && $expense->farm_id !== null) {
                $expense->organisation_id = Farm::find($expense->farm_id)?->organisation_id;
            }

            if (empty($expense->expense_number) && $expense->organisation_id) {
                $expense->expense_number = self::generateExpenseNumber($expense->organisation_id, $expense->expense_date ? \Illuminate\Support\Carbon::parse($expense->expense_date)->year : null);
            }

            if ($expense->balance === null || (float) $expense->balance === 0.0) {
                $expense->balance = (float) $expense->amount - (float) ($expense->paid_amount ?? 0);
            }
        });
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [ExpenseStatus::Unpaid, ExpenseStatus::Partial]);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', ExpenseStatus::Paid);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [ExpenseStatus::Unpaid, ExpenseStatus::Partial])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    public function scopeForCategory(Builder $query, ExpenseCategory|string $category): Builder
    {
        return $query->where('category', $category instanceof ExpenseCategory ? $category->value : $category);
    }

    public function scopeForDateRange(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->whereBetween('expense_date', [$start, $end]);
    }

    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where('farm_id', $farmId);
    }

    public function scopeForFlock(Builder $query, int $flockId): Builder
    {
        return $query->where('flock_id', $flockId);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isUnpaid(): bool
    {
        return $this->status === ExpenseStatus::Unpaid;
    }

    public function isPartial(): bool
    {
        return $this->status === ExpenseStatus::Partial;
    }

    public function isPaid(): bool
    {
        return $this->status === ExpenseStatus::Paid;
    }

    public function isCancelled(): bool
    {
        return $this->status === ExpenseStatus::Cancelled;
    }

    public function hasBalance(): bool
    {
        return (float) $this->balance > 0;
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && ! $this->isPaid()
            && ! $this->isCancelled();
    }

    public function getDaysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(now());
    }

    public function getPaymentProgress(): float
    {
        if ((float) $this->amount <= 0) {
            return 0;
        }

        return min(100, ((float) $this->paid_amount / (float) $this->amount) * 100);
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    /**
     * Enregistre un paiement sur la dépense et débite le compte associé.
     * Si aucun compte n'a été défini au préalable sur la dépense, il faut en fournir un.
     */
    public function recordPayment(float $amount, ?int $accountId = null): void
    {
        if ($amount <= 0) {
            throw ExpenseException::invalidAmount();
        }

        if ($this->isCancelled()) {
            throw ExpenseException::alreadyCancelled($this->expense_number);
        }

        if ($this->isPaid()) {
            throw ExpenseException::alreadyPaid($this->expense_number);
        }

        $balance = (float) $this->balance;

        if ($amount > $balance + 0.01) {
            throw ExpenseException::paymentExceedsBalance($this->expense_number, $balance, $amount);
        }

        $accountId ??= $this->account_id;

        if (! $accountId) {
            throw ExpenseException::accountRequired($this->expense_number);
        }

        DB::transaction(function () use ($amount, $accountId): void {
            /** @var Account|null $account */
            $account = Account::lockForUpdate()->find($accountId);

            if (! $account?->is_active) {
                throw ExpenseException::accountNotActive($account?->name ?? 'compte');
            }

            if ((float) $account->current_balance < $amount) {
                throw ExpenseException::insufficientAccountBalance($account->name, $amount, (float) $account->current_balance);
            }

            $account->debit(
                amount: $amount,
                description: "Paiement dépense {$this->expense_number} — {$this->description}",
                category: $this->category === ExpenseCategory::Salaries
                    ? TransactionCategory::Payroll
                    : TransactionCategory::Expense,
                referenceType: self::class,
                referenceId: $this->id,
                referenceNumber: $this->expense_number,
            );

            $newPaid = (float) $this->paid_amount + $amount;
            $newBalance = max(0, (float) $this->amount - $newPaid);

            $newStatus = match (true) {
                $newBalance <= 0 => ExpenseStatus::Paid,
                $newPaid > 0 => ExpenseStatus::Partial,
                default => ExpenseStatus::Unpaid,
            };

            $this->update([
                'account_id' => $this->account_id ?? $accountId,
                'paid_amount' => $newPaid,
                'balance' => $newBalance,
                'status' => $newStatus,
                'paid_at' => $newStatus === ExpenseStatus::Paid ? now() : $this->paid_at,
            ]);
        });
    }

    /**
     * Annule la dépense et contrepasse les paiements déjà effectués sur le compte lié.
     */
    public function cancel(): void
    {
        if ($this->isCancelled()) {
            throw ExpenseException::alreadyCancelled($this->expense_number);
        }

        DB::transaction(function (): void {
            if ((float) $this->paid_amount > 0 && $this->account_id) {
                $this->account?->credit(
                    amount: (float) $this->paid_amount,
                    description: "Annulation dépense {$this->expense_number}",
                    category: TransactionCategory::Refund,
                    referenceType: self::class,
                    referenceId: $this->id,
                    referenceNumber: $this->expense_number,
                );
            }

            $this->update([
                'status' => ExpenseStatus::Cancelled,
                'balance' => 0,
            ]);
        });
    }

    public static function generateExpenseNumber(int $organisationId, ?int $year = null): string
    {
        // Numérotation par année du document, pas de la saisie : une pièce de 2025 saisie en 2026 reste en 2025
        $year ??= now()->year;
        $sequence = app(\App\Services\NumberSequence::class);

        return $sequence->nextFormatted(
            prefix: 'DEP-',
            key: 'expense',
            scopeId: $organisationId,
            year: $year,
            seedUsing: fn (): int => $sequence->maxSuffix('expenses', 'expense_number', "DEP-{$year}-", ['organisation_id' => $organisationId]),
        );
    }
}
