<?php

namespace App\Filament\Farm\Resources\HealthIncidents\Pages;

use App\Filament\Farm\Resources\HealthIncidents\HealthIncidentResource;
use App\Enums\IncidentStatus;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewHealthIncident extends ViewRecord
{
    protected static string $resource = HealthIncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start_treatment')
                ->label('Démarrer le traitement')
                ->icon('heroicon-o-beaker')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Démarrer le traitement')
                ->form([
                    Textarea::make('treatment_applied')
                        ->label('Traitement à appliquer')
                        ->required()
                        ->rows(3)
                        ->default(fn () => $this->record->treatment_applied),

                    DatePicker::make('treatment_start_date')
                        ->label('Date de début')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()),
                ])
                ->action(function (array $data) {
                    $this->record->startTreatment($data['treatment_applied'], $data['treatment_start_date']);

                    Notification::make()
                        ->title('Traitement démarré')
                        ->body("L'incident « {$this->record->title} » est passé en traitement.")
                        ->info()
                        ->send();

                    $this->refreshFormData(['status', 'treatment_applied', 'treatment_start_date']);
                })
                ->visible(fn () => $this->record->isDetected() && HealthIncidentResource::canPerformAction('start_treatment')),

            Action::make('start_monitoring')
                ->label('Passer en surveillance')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Passer en surveillance')
                ->modalDescription('Le traitement est terminé. L\'incident passe en phase de surveillance.')
                ->form([
                    DatePicker::make('treatment_end_date')
                        ->label('Date de fin de traitement')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'treatment_end_date' => $data['treatment_end_date'] ?? now()->toDateString(),
                    ]);
                    $this->record->startMonitoring();

                    Notification::make()
                        ->title('Incident en surveillance')
                        ->body("L'incident « {$this->record->title} » est maintenant sous surveillance.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'treatment_end_date']);
                })
                ->visible(fn () => $this->record->isUnderTreatment() && HealthIncidentResource::canPerformAction('start_monitoring')),

            Action::make('resolve')
                ->label('Résoudre')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading("Résoudre l'incident")
                ->modalDescription("Marquer cet incident comme résolu.")
                ->form([
                    DatePicker::make('resolution_date')
                        ->label('Date de résolution')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now()),

                    Textarea::make('resolution_notes')
                        ->label('Notes de résolution')
                        ->rows(3)
                        ->placeholder('Résultat, évolution, conclusion...'),

                    Textarea::make('lessons_learned')
                        ->label('Leçons apprises')
                        ->rows(3)
                        ->placeholder('Ce que cet incident nous a appris...'),

                    Textarea::make('preventive_measures')
                        ->label('Mesures préventives')
                        ->rows(3)
                        ->placeholder('Mesures à prendre pour éviter une récidive...'),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'lessons_learned' => $data['lessons_learned'] ?? null,
                        'preventive_measures' => $data['preventive_measures'] ?? null,
                    ]);
                    $this->record->resolve($data['resolution_notes'] ?? null);

                    if (isset($data['resolution_date'])) {
                        $this->record->update(['resolution_date' => $data['resolution_date']]);
                    }

                    Notification::make()
                        ->title('Incident résolu')
                        ->body("L'incident « {$this->record->title} » a été résolu.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'resolution_date', 'resolution_notes', 'lessons_learned', 'preventive_measures']);
                })
                ->visible(fn () => $this->record->isActive() && HealthIncidentResource::canPerformAction('resolve')),

            EditAction::make()
                ->visible(fn () => HealthIncidentResource::canEdit($this->record)),
            DeleteAction::make()
                ->visible(fn () => HealthIncidentResource::canDelete($this->record)),
        ];
    }
}
