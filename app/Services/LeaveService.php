<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Leave;
use Carbon\Carbon;

class LeaveService
{
    public function createLeave(Employee $employee, array $data): Leave
    {
        $start = Carbon::parse($data['start_date']);
        $end   = Carbon::parse($data['end_date']);

        $data['employee_id'] = $employee->id;
        $data['days_count']  = $this->calculateDays($start, $end);
        $data['status']      = 'pending';

        return Leave::create($data);
    }

    public function approve(Leave $leave, int $approverId): void
    {
        $leave->approve($approverId);

        // Si le congé commence maintenant, passer l'employé "en congé"
        if ($leave->start_date->lte(now()) && $leave->end_date->gte(now())) {
            $leave->employee->update(['status' => 'on_leave']);
        }
    }

    public function reject(Leave $leave, string $reason, int $rejectorId): void
    {
        $leave->reject($reason, $rejectorId);
    }

    public function calculateDays(Carbon $start, Carbon $end): int
    {
        return (int) $start->diffInDays($end) + 1;
    }

    public function getRemainingDays(Employee $employee, string $type = 'annual', int $year = null): int
    {
        $year ??= now()->year;

        return $employee->getRemainingAnnualLeaveDays($year);
    }
}
