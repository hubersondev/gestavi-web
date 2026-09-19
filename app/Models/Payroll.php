<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'contract_id', 'validated_by', 'paid_by',
        'period_year', 'period_month', 'status',
        'gross_salary', 'cnps_employee', 'cnps_employer', 'its',
        'other_deductions', 'total_deductions', 'net_salary',
        'worked_days', 'leave_days', 'overtime_hours',
        'validated_at', 'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollStatus::class,
            'gross_salary' => 'decimal:2',
            'cnps_employee' => 'decimal:2',
            'cnps_employer' => 'decimal:2',
            'its' => 'decimal:2',
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

    public function getPeriodLabel(): string
    {
        $months = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

        return $months[$this->period_month].' '.$this->period_year;
    }

    public function recalculate(): void
    {
        $gross = $this->items()->where('is_deduction', false)->sum('amount');
        $deductions = $this->items()->where('is_deduction', true)->sum('amount');
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

    public function markAsPaid(int $userId, ?\Carbon\Carbon $date = null): void
    {
        $this->update([
            'status' => PayrollStatus::Paid,
            'paid_by' => $userId,
            'paid_at' => $date ?? now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => PayrollStatus::Cancelled]);
    }
}
