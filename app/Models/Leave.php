<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'approved_by',
        'type', 'status',
        'start_date', 'end_date', 'days_count',
        'reason', 'rejection_reason', 'approved_at',
        'notes', 'attachment',
    ];

    protected function casts(): array
    {
        return [
            'type' => LeaveType::class,
            'status' => LeaveStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', LeaveStatus::Pending);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', LeaveStatus::Approved);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->whereYear('start_date', $year);
    }

    public function scopeActive($query)
    {
        return $query->where('status', LeaveStatus::Approved)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === LeaveStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === LeaveStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === LeaveStatus::Rejected;
    }

    public function isCancelled(): bool
    {
        return $this->status === LeaveStatus::Cancelled;
    }

    public function isActive(): bool
    {
        return $this->isApproved()
            && $this->start_date->lte(now())
            && $this->end_date->gte(now());
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    public function approve(int $approverId): void
    {
        $this->update([
            'status' => LeaveStatus::Approved,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);
    }

    public function reject(string $reason, int $rejectorId): void
    {
        $this->update([
            'status' => LeaveStatus::Rejected,
            'rejection_reason' => $reason,
            'approved_by' => $rejectorId,
            'approved_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => LeaveStatus::Cancelled]);
    }
}
