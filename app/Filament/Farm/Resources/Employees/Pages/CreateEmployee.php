<?php

namespace App\Filament\Farm\Resources\Employees\Pages;

use App\Filament\Farm\Resources\Employees\EmployeeResource;
use App\Models\Employee;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();
        $data['organisation_id'] = $tenant->getKey();

        if (empty($data['employee_number'])) {
            $data['employee_number'] = Employee::generateNumber($tenant->getKey());
        }

        return Employee::create($data);
    }

    protected function associateRecordWithTenant(Model $record, Model $tenant): void
    {
        // Géré manuellement via organisation_id dans handleRecordCreation
    }
}
