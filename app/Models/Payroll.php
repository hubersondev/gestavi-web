<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Enums\PayrollStatus;
use App\Exceptions\AccountException;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Payroll extends Model
{
    use BelongsToOrganisation, HasFactory, LogsAuditTrail, SoftDeletes;

    /** Parent métier dont héritent organisation_id et farm_id. */
    protected static string $organisationParentRelation = 'employee';

    protected $fillable = [
        'organisation_id', 'farm_id',
        'employee_id', 'contract_id', 'payroll_parameter_id', 'validated_by', 'paid_by', 'expense_id',
        'period_year', 'period_month', 'status',
        'gross_salary', 'taxable_gross', 'cnps_employee', 'cnps_employer', 'employer_contributions', 'its', 'cmu_employee',
        'cnps_ceiling_applied', 'fiscal_parts_applied',
        'other_deductions', 'total_deductions', 'net_salary',
        'worked_days', 'leave_days', 'overtime_hours',
        'validated_at', 'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollStatus::class,
            'gross_salary' => 'decimal:2',
            'taxable_gross' => 'decimal:2',
            'cnps_employee' => 'decimal:2',
            'cnps_employer' => 'decimal:2',
            'employer_contributions' => 'array',
            'its' => 'decimal:2',
            'cmu_employee' => 'decimal:2',
            'cnps_ceiling_applied' => 'decimal:2',
            'fiscal_parts_applied' => 'decimal:1',
            'other_deductions' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'validated_at' => 'datetime',
            'paid_at' => 'date',
        ];
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(PayrollParameter::class, 'payroll_parameter_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class)->orderBy('sort_order');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(PayrollItem::class)->where('is_deduction', false)->orderBy('sort_order');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollItem::class)->where('is_deduction', true)->orderBy('sort_order');
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', PayrollStatus::Draft);
    }

    public function scopeValidated($query)
    {
        return $query->where('status', PayrollStatus::Validated);
    }

    public function scopePaid($query)
    {
        return $query->where('status', PayrollStatus::Paid);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === PayrollStatus::Draft;
    }

    public function isValidated(): bool
    {
        return $this->status === PayrollStatus::Validated;
    }

    public function isPaid(): bool
    {
        return $this->status === PayrollStatus::Paid;
    }

    public function isCancelled(): bool
    {
        return $this->status === PayrollStatus::Cancelled;
    }

    public function getPeriodStart(): \Carbon\CarbonImmutable
    {
        return \Carbon\CarbonImmutable::create($this->period_year, $this->period_month, 1)->startOfDay();
    }

    public function getPeriodEnd(): \Carbon\CarbonImmutable
    {
        return $this->getPeriodStart()->endOfMonth();
    }

    public function getPeriodLabel(): string
    {
        $months = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

        return $months[$this->period_month].' '.$this->period_year;
    }

    /**
     * Référence du bulletin utilisée comme pièce justificative comptable.
     */
    public function getReference(): string
    {
        return sprintf(
            'PAIE-%d-%02d-%s',
            $this->period_year,
            $this->period_month,
            $this->employee?->employee_number ?? $this->employee_id,
        );
    }

    /**
     * Totaux : brut = Σ gains ; retenues = Σ retenues (CNPS, ITS…) + autres retenues saisies ;
     * net = brut − retenues, jamais négatif.
     */
    public function recalculate(): void
    {
        $gross = (float) $this->items()->where('is_deduction', false)->sum('amount');
        $itemDeductions = (float) $this->items()->where('is_deduction', true)->sum('amount');
        $otherDeductions = (float) ($this->other_deductions ?? 0);
        $deductions = $itemDeductions + $otherDeductions;

        $this->update([
            'gross_salary' => $gross,
            'total_deductions' => $deductions,
            'net_salary' => max(0, $gross - $deductions),
        ]);
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    public function validate(int $userId): void
    {
        $this->update([
            'status' => PayrollStatus::Validated,
            'validated_by' => $userId,
            'validated_at' => now(),
        ]);
    }

    /**
     * Paye le bulletin : crée la dépense « salaires » imputée à la ferme de l'employé,
     * débite le compte choisi (verrouillé) et lie la dépense au bulletin.
     * La paie apparaît ainsi dans la trésorerie et dans les charges de la ferme.
     */
    public function markAsPaid(int $userId, int $accountId, ?CarbonInterface $date = null): void
    {
        if (! $this->isValidated()) {
            throw new \LogicException('Seul un bulletin validé peut être marqué comme payé.');
        }

        $date ??= now();
        $net = (float) $this->net_salary;

        DB::transaction(function () use ($userId, $accountId, $date, $net): void {
            /** @var Account|null $account */
            $account = Account::lockForUpdate()->find($accountId);

            if (! $account?->is_active) {
                throw AccountException::accountNotActive($account?->name ?? 'compte');
            }

            if ((float) $account->current_balance < $net) {
                throw AccountException::insufficientBalance($account->name, $net, (float) $account->current_balance);
            }

            $employee = $this->employee;
            $expenseId = null;

            if ($net > 0) {
                $expense = Expense::create([
                    'organisation_id' => $employee->organisation_id,
                    'farm_id' => $employee->farm_id,
                    'flock_id' => null,
                    'account_id' => $account->id,
                    'user_id' => $userId,
                    'expense_date' => $date->toDateString(),
                    'category' => ExpenseCategory::Salaries,
                    'status' => ExpenseStatus::Unpaid,
                    'amount' => $net,
                    'paid_amount' => 0,
                    'description' => "Salaire {$this->getPeriodLabel()} — {$employee->full_name}",
                    'reference' => $this->getReference(),
                ]);

                // Débite le compte (catégorie Paie) et passe la dépense en « payée »
                $expense->recordPayment($net, $account->id);
                $expenseId = $expense->id;
            }

            $this->update([
                'status' => PayrollStatus::Paid,
                'paid_by' => $userId,
                'paid_at' => $date,
                'expense_id' => $expenseId,
            ]);
        });
    }

    public function cancel(): void
    {
        $this->update(['status' => PayrollStatus::Cancelled]);
    }
}
