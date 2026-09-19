<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'renewed_by_contract_id',
        'contract_number', 'type', 'status',
        'start_date', 'end_date',
        'base_salary', 'transport_allowance', 'housing_allowance', 'food_allowance',
        'working_hours_per_week', 'annual_leave_days',
        'position', 'work_location', 'description',
        'termination_reason', 'termination_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContractType::class,
            'status' => ContractStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'termination_date' => 'date',
            'base_salary' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'housing_allowance' => 'decimal:2',
            'food_allowance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Contract $contract): void {
            if (empty($contract->contract_number) && $contract->employee_id) {
                $contract->contract_number = static::generateNumber($contract->employee_id);
            }
        });
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function renewedByContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'renewed_by_contract_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(Contract::class, 'renewed_by_contract_id');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class);
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', ContractStatus::Active);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query
            ->where('status', ContractStatus::Active)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === ContractStatus::Active;
    }

    public function isExpired(): bool
    {
        return $this->status === ContractStatus::Expired;
    }

    public function isCdi(): bool
    {
        return $this->type === ContractType::Cdi;
    }

    public function isCdd(): bool
    {
        return $this->type === ContractType::Cdd;
    }

    public function getTotalPackage(): float
    {
        return (float) ($this->base_salary + $this->transport_allowance + $this->housing_allowance + $this->food_allowance);
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (! $this->end_date || ! $this->isActive()) {
            return null;
        }

        return (int) now()->diffInDays($this->end_date, false);
    }

    public static function generateNumber(int $employeeId): string
    {
        $year = now()->year;
        $count = static::where('employee_id', $employeeId)->whereYear('created_at', $year)->count();

        return 'CTR-'.$year.'-'.str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
