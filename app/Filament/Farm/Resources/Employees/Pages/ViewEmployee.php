<?php

namespace App\Filament\Farm\Resources\Employees\Pages;

use App\Filament\Farm\Resources\Employees\EmployeeResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('suspend')
                ->label('Suspendre')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Suspendre l\'employé')
                ->modalDescription('L\'employé passera en statut "Suspendu".')
                ->action(function () {
                    $this->record->suspend();

                    Notification::make()
                        ->title('Employé suspendu')
                        ->body("{$this->record->full_name} a été suspendu.")
                        ->warning()
                        ->send();

                    $this->refreshFormData(['status']);
                })
                ->visible(fn () => $this->record->isActive() && EmployeeResource::canPerformAction('suspend')),

            Action::make('reactivate')
                ->label('Réactiver')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Réactiver l\'employé')
                ->modalDescription('L\'employé repassera en statut "Actif".')
                ->action(function () {
                    $this->record->reactivate();

                    Notification::make()
                        ->title('Employé réactivé')
                        ->body("{$this->record->full_name} est de nouveau actif.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                })
                ->visible(fn () => $this->record->isSuspended() && EmployeeResource::canPerformAction('reactivate')),

            Action::make('terminate')
                ->label('Résilier le contrat')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Résilier le contrat de l\'employé')
                ->modalDescription('Cette action est irréversible. L\'employé passera en statut "Résilié".')
                ->form([
                    Textarea::make('reason')
                        ->label('Motif de résiliation')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->terminate($data['reason']);

                    Notification::make()
                        ->title('Contrat résilié')
                        ->body("Le contrat de {$this->record->full_name} a été résilié.")
                        ->danger()
                        ->send();

                    $this->refreshFormData(['status', 'termination_date', 'termination_reason']);
                })
                ->visible(fn () => ($this->record->isActive() || $this->record->isSuspended()) && EmployeeResource::canPerformAction('terminate')),

            EditAction::make()
                ->visible(fn () => EmployeeResource::canEdit($this->record)),

            DeleteAction::make()
                ->visible(fn () => EmployeeResource::canDelete($this->record)),
        ];
    }
}
