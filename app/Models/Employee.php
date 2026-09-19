<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'organisation_id', 'farm_id', 'user_id',
        'employee_number', 'first_name', 'last_name',
        'gender', 'birth_date', 'birth_place', 'nationality', 'marital_status',
        'cnps_number', 'payment_method',
        'phone', 'phone_secondary', 'email', 'address', 'city',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
        'department', 'status',
        'hire_date', 'termination_date', 'termination_reason',
        'bank_name', 'bank_account', 'mobile_money_number', 'mobile_money_operator',
        'id_type', 'id_number', 'id_expiry_date', 'id_document', 'photo',
        'is_resident', 'is_on_site', 'accommodation', 'residence_start_date',
        'requires_patrol', 'patrol_interval_hours',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'birth_date' => 'date',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'id_expiry_date' => 'date',
            'residence_start_date' => 'date',
            'is_resident' => 'boolean',
            'is_on_site' => 'boolean',
            'requires_patrol' => 'boolean',
        ];
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->orderByDesc('start_date');
    }

    public function activeContract(): HasOne
    {
        return $this->hasOne(Contract::class)->ofMany(
            ['start_date' => 'max'],
            fn ($q) => $q->where('status', 'active')
        );
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class)->orderByDesc('start_date');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class)->orderByDesc('attendance_date');
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class)->orderByDesc('period_year')->orderByDesc('period_month');
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', EmployeeStatus::Active);
    }

    public function scopeForOrganisation($query, int $organisationId)
    {
        return $query->where('organisation_id', $organisationId);
    }

    public function scopeForFarm($query, int $farmId)
    {
        return $query->where('farm_id', $farmId);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isActive(): bool
    {
        return $this->status === EmployeeStatus::Active;
    }

    public function isResident(): bool
    {
        return (bool) $this->is_resident;
    }

    public function isOnSite(): bool
    {
        return (bool) $this->is_on_site;
    }

    public function isGuard(): bool
    {
        return (bool) $this->requires_patrol;
    }

    public function isOnLeave(): bool
    {
        return $this->status === EmployeeStatus::OnLeave;
    }

    public function isSuspended(): bool
    {
        return $this->status === EmployeeStatus::Suspended;
    }

    public function isTerminated(): bool
    {
        return $this->status === EmployeeStatus::Terminated;
    }

    public function getAge(): ?int
    {
        return $this->birth_date?->age;
    }

    public function getSeniorityInYears(): ?int
    {
        return $this->hire_date?->diffInYears(now());
    }

    public function getSeniorityInMonths(): ?int
    {
        return $this->hire_date?->diffInMonths(now());
    }

    public function getCurrentSalary(): ?float
    {
        return $this->activeContract?->base_salary;
    }

    public function getPositionAttribute(): ?string
    {
        return $this->activeContract?->position;
    }

    public function getRemainingAnnualLeaveDays(int $year): int
    {
        $allocated = $this->activeContract?->annual_leave_days ?? 24;
        $used = $this->leaves()
            ->where('type', 'annual')
            ->whereIn('status', ['approved', 'cancelled'])
            ->whereYear('start_date', $year)
            ->sum('days_count');

        return max(0, $allocated - $used);
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    public function terminate(string $reason, ?\Carbon\Carbon $date = null): void
    {
        $this->update([
            'status' => EmployeeStatus::Terminated,
            'termination_date' => $date ?? now(),
            'termination_reason' => $reason,
        ]);
    }

    public function suspend(): void
    {
        $this->update(['status' => EmployeeStatus::Suspended]);
    }

    public function reactivate(): void
    {
        $this->update(['status' => EmployeeStatus::Active]);
    }

    public static function generateNumber(int $organisationId): string
    {
        $year = now()->year;
        $count = static::withTrashed()
            ->where('organisation_id', $organisationId)
            ->whereYear('created_at', $year)
            ->count();

        return 'EMP-'.$year.'-'.str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
