<?php

namespace App\Filament\Farm\Resources\Payrolls\Pages;

use App\Filament\Farm\Resources\Payrolls\PayrollResource;
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
                ->label('Marquer payé')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Marquer comme payé')
                ->modalDescription('Le bulletin passera en statut "Payé". Confirmez-vous ?')
                ->action(function () {
                    $this->record->markAsPaid(auth()->id());

                    Notification::make()
                        ->title('Bulletin marqué comme payé')
                        ->body("Le bulletin de {$this->record->employee?->full_name} pour {$this->record->getPeriodLabel()} a été marqué comme payé.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'paid_at', 'paid_by']);
                })
                ->visible(fn () => $this->record->isValidated() && PayrollResource::canPerformAction('mark_paid')),

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
