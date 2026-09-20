<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToOrganisation, HasFactory;

    /** Parent métier dont héritent organisation_id et farm_id. */
    protected static string $organisationParentRelation = 'employee';

    protected $fillable = [
        'organisation_id', 'farm_id',
        'employee_id', 'recorded_by',
        'attendance_date', 'status',
        'check_in', 'check_out',
        'late_minutes', 'overtime_hours',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'attendance_date' => 'date',
            'overtime_hours' => 'decimal:2',
        ];
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('attendance_date', $date);
    }

    public function scopePresent($query)
    {
        return $query->where('status', AttendanceStatus::Present);
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', AttendanceStatus::Absent);
    }

    public function scopeForPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('attendance_date', [$from, $to]);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isPresent(): bool
    {
        return $this->status === AttendanceStatus::Present;
    }

    public function isAbsent(): bool
    {
        return $this->status === AttendanceStatus::Absent;
    }

    public function isLate(): bool
    {
        return $this->status === AttendanceStatus::Late;
    }

    public function getDurationMinutes(): ?int
    {
        if (! $this->check_in || ! $this->check_out) {
            return null;
        }

        // check_in et check_out sont des strings HH:MM
        $in = \Carbon\Carbon::createFromTimeString($this->check_in);
        $out = \Carbon\Carbon::createFromTimeString($this->check_out);

        return (int) $in->diffInMinutes($out);
    }
}
