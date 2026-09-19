<?php

namespace App\Filament\Farm\Resources\Payrolls\Pages;

use App\Filament\Farm\Resources\Payrolls\PayrollResource;
use App\Models\Payroll;
use App\Services\PayrollService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePayroll extends CreateRecord
{
    protected static string $resource = PayrollResource::class;

    protected function associateRecordWithTenant(Model $record, Model $tenant): void
    {
        // Payroll lié au tenant via employee → organisation. Pas d'association directe.
    }

    protected function afterCreate(): void
    {
        /** @var Payroll $payroll */
        $payroll = $this->record;

        if ($payroll->contract) {
            app(PayrollService::class)->generateItemsFromContract($payroll, $payroll->contract);
        }
    }
}
