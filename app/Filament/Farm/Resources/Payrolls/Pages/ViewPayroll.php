<?php

namespace App\Filament\Farm\Resources\Payrolls\Pages;

use App\Filament\Farm\Resources\Payrolls\PayrollResource;
use App\Filament\Farm\Resources\Payrolls\Support\PayrollPaymentAction;
use App\Services\PayrollService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('validate')
                ->label('Valider')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Valider le bulletin de paie')
                ->modalDescription('Le bulletin passera en statut "Validé". Cette action est irréversible.')
                ->action(function () {
                    app(PayrollService::class)->validatePayroll($this->record, auth()->id());

                    Notification::make()
                        ->title('Bulletin validé')
                        ->body("Le bulletin de {$this->record->employee?->full_name} pour {$this->record->getPeriodLabel()} a été validé.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'validated_at', 'validated_by']);
                })
                ->visible(fn () => $this->record->isDraft() && PayrollResource::canPerformAction('validate')),

            Action::make('mark_paid')
                ->label('Payer')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->modalHeading('Payer le bulletin')
                ->modalDescription(fn () => 'Le net à payer ('.number_format((float) $this->record->net_salary, 0, ',', ' ').' XOF) sera débité du compte choisi et une dépense « Salaires » sera créée.')
                ->schema(PayrollPaymentAction::schema())
                ->action(function (array $data): void {
                    if (PayrollPaymentAction::handle($this->record, $data)) {
                        $this->refreshFormData(['status', 'paid_at', 'paid_by', 'expense_id']);
                    }
                })
                ->visible(fn () => $this->record->isValidated() && PayrollResource::canPerformAction('mark_paid')),

            Action::make('payslip_pdf')
                ->label('Bulletin PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn () => ! $this->record->isDraft() && PayrollResource::canPerformAction('view'))
                ->action(fn () => PayrollPaymentAction::downloadPayslip($this->record)),

            Action::make('cancel')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Annuler le bulletin de paie')
                ->modalDescription('Le bulletin sera annulé. Cette action est irréversible.')
                ->action(function () {
                    $this->record->cancel();

                    Notification::make()
                        ->title('Bulletin annulé')
                        ->body("Le bulletin de {$this->record->employee?->full_name} pour {$this->record->getPeriodLabel()} a été annulé.")
                        ->warning()
                        ->send();

                    $this->refreshFormData(['status']);
                })
                ->visible(fn () => ! $this->record->isPaid() && PayrollResource::canPerformAction('cancel')),

            EditAction::make()
                ->visible(fn () => $this->record->isDraft() && PayrollResource::canEdit($this->record)),

            DeleteAction::make()
                ->visible(fn () => PayrollResource::canDelete($this->record)),
        ];
    }
}
