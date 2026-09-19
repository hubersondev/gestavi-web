<?php

namespace App\Services;

use App\Enums\FarmLogCategory;
use App\Enums\FarmLogPriority;
use App\Models\Employee;
use App\Models\FarmLog;
use App\Models\Organisation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class EmployeeService
{
    public function createEmployee(array $data, Organisation $organisation): Employee
    {
        $data['organisation_id'] = $organisation->id;

        if (empty($data['employee_number'])) {
            $data['employee_number'] = Employee::generateNumber($organisation->id);
        }

        $employee = Employee::create($data);

        $this->log($employee, 'Embauche enregistrée', "L'employé {$employee->full_name} ({$employee->employee_number}) a été enregistré.");

        return $employee;
    }

    public function terminateEmployee(Employee $employee, string $reason, ?Carbon $date = null): void
    {
        $employee->terminate($reason, $date);

        // Expirer le contrat actif
        $employee->activeContract?->update(['status' => 'expired', 'termination_reason' => $reason, 'termination_date' => $date ?? now()]);

        // Annuler les congés futurs approuvés
        $employee->leaves()
            ->where('status', 'approved')
            ->where('start_date', '>', now())
            ->update(['status' => 'cancelled']);

        $this->log($employee, 'Résiliation', "Résiliation de {$employee->full_name} : {$reason}");
    }

    public function suspend(Employee $employee): void
    {
        $employee->suspend();
        $this->log($employee, 'Suspension', "L'employé {$employee->full_name} a été suspendu.");
    }

    public function reactivate(Employee $employee): void
    {
        $employee->reactivate();
        $this->log($employee, 'Réactivation', "L'employé {$employee->full_name} a été réactivé.");
    }

    private function log(Employee $employee, string $title, string $message): void
    {
        if (! $employee->farm_id) {
            return;
        }

        FarmLog::create([
            'farm_id' => $employee->farm_id,
            'flock_id' => null,
            'recorded_by' => Auth::id(),
            'category' => FarmLogCategory::Hr,
            'priority' => FarmLogPriority::Normal,
            'title' => $title,
            'description' => $message,
            'log_date' => now()->toDateString(),
            'log_time' => now()->toTimeString(),
        ]);
    }
}
