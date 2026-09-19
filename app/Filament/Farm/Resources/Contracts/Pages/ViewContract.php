<?php

namespace App\Filament\Farm\Resources\Contracts\Pages;

use App\Filament\Farm\Resources\Contracts\ContractResource;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_pdf')
                ->label('Télécharger PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): \Symfony\Component\HttpFoundation\StreamedResponse {
                    $contract     = $this->record->load('employee');
                    $employee     = $contract->employee;
                    $organisation = \Filament\Facades\Filament::getTenant();

                    $service    = app(PayrollService::class);
                    $gross      = (float) $contract->base_salary
                        + (float) $contract->transport_allowance
                        + (float) $contract->housing_allowance
                        + (float) $contract->food_allowance;
                    $cnps       = $service->calculateCnpsEmployee($gross);
                    $its        = $service->calculateIts($gross - $cnps);
                    $netSalary  = $gross - $cnps - $its;

                    $grossSalary = $gross;

                    $pdf = Pdf::loadView('pdf.contract', compact(
                        'contract', 'employee', 'organisation',
                        'grossSalary', 'cnps', 'its', 'netSalary'
                    ))->setPaper('a4', 'portrait');

                    $filename = 'contrat-' . ($contract->contract_number ?? $contract->id) . '.pdf';

                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        $filename,
                        ['Content-Type' => 'application/pdf']
                    );
                }),

            EditAction::make()->visible(fn () => ContractResource::canEdit($this->record)),
            DeleteAction::make()->visible(fn () => ContractResource::canDelete($this->record)),
        ];
    }
}
