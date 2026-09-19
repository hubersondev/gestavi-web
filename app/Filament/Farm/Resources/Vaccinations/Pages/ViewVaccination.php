<?php

namespace App\Filament\Farm\Resources\Vaccinations\Pages;

use App\Filament\Farm\Resources\Vaccinations\VaccinationResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewVaccination extends ViewRecord
{
    protected static string $resource = VaccinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mark_done')
                ->label('Marquer effectuée')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmer l\'administration')
                ->modalDescription('Enregistrer cette vaccination comme effectuée.')
                ->form([
                    DatePicker::make('administered_date')
                        ->label('Date d\'administration')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()),

                    TextInput::make('birds_treated')
                        ->label('Sujets traités')
                        ->numeric()
                        ->minValue(1)
                        ->default(fn () => $this->record->flock?->quantity_current),

                    TextInput::make('cost')
                        ->label('Coût')
                        ->numeric()
                        ->step(0.01)
                        ->suffix('FCFA'),

                    Textarea::make('observations')
                        ->label('Observations')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->markAsDone([
                        'administered_date' => $data['administered_date'],
                        'birds_treated' => $data['birds_treated'] ?? null,
                        'cost' => $data['cost'] ?? null,
                        'observations' => $data['observations'] ?? null,
                        'administered_by' => \Filament\Facades\Filament::auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Vaccination enregistrée')
                        ->body("Le traitement « {$this->record->treatment_name} » a été enregistré comme effectué.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'administered_date', 'birds_treated', 'cost', 'observations', 'administered_by']);
                })
                ->visible(fn () => $this->record->isActionable() && VaccinationResource::canPerformAction('mark_done')),

            Action::make('reschedule')
                ->label('Reporter')
                ->icon('heroicon-o-calendar')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reporter la vaccination')
                ->form([
                    DatePicker::make('new_date')
                        ->label('Nouvelle date prévue')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->minDate(now()),

                    Textarea::make('reason')
                        ->label('Motif du report')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->reschedule(
                        \Carbon\Carbon::parse($data['new_date']),
                        $data['reason'] ?? '',
                    );

                    Notification::make()
                        ->title('Vaccination reportée')
                        ->body("Le traitement « {$this->record->treatment_name} » a été reporté au ".\Carbon\Carbon::parse($data['new_date'])->format('d/m/Y').'.')
                        ->warning()
                        ->send();

                    $this->refreshFormData(['status', 'scheduled_date', 'notes']);
                })
                ->visible(fn () => $this->record->isActionable() && VaccinationResource::canPerformAction('reschedule')),

            Action::make('skip')
                ->label('Ignorer')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Ignorer la vaccination')
                ->modalDescription('Cette vaccination sera marquée comme ignorée.')
                ->form([
                    Textarea::make('reason')
                        ->label('Motif')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->markAsSkipped($data['reason']);

                    Notification::make()
                        ->title('Vaccination ignorée')
                        ->body("Le traitement « {$this->record->treatment_name} » a été ignoré.")
                        ->danger()
                        ->send();

                    $this->refreshFormData(['status', 'notes']);
                })
                ->visible(fn () => $this->record->isActionable() && VaccinationResource::canPerformAction('skip')),

            EditAction::make()
                ->visible(fn () => VaccinationResource::canEdit($this->record)),
            DeleteAction::make()
                ->visible(fn () => VaccinationResource::canDelete($this->record)),
        ];
    }
}
