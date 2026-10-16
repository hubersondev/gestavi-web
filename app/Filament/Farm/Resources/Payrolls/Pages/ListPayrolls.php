<?php

namespace App\Filament\Farm\Resources\Payrolls\Pages;

use App\Filament\Farm\Pages\Reports\HrReportPage;
use App\Filament\Farm\Resources\Payrolls\PayrollResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrolls extends ListRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hr_report')
                ->label('Rapport RH et export paie')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (): string => HrReportPage::getUrl())
                ->visible(fn (): bool => HrReportPage::canAccess()),
            CreateAction::make()->visible(fn () => PayrollResource::canCreate()),
        ];
    }
}
