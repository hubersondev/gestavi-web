<?php

namespace App\Filament\Farm\Resources\Flocks\Pages;

use App\Filament\Farm\Resources\Flocks\FlockResource;
use App\Services\FlockService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewFlock extends ViewRecord
{
    protected static string $resource = FlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('start')
                ->label('Démarrer la bande')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Démarrer la bande')
                ->modalDescription('La bande passera en statut "En cours". Cette action est irréversible.')
                ->action(function () {
                    app(FlockService::class)->startFlock($this->record);

                    Notification::make()
                        ->title('Bande démarrée')
                        ->body("La bande {$this->record->code} est maintenant en cours d'élevage.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'quantity_current', 'start_date']);
                })
                ->visible(fn () => $this->record->isPlanned() && FlockResource::canPerformAction('start')),

            Action::make('complete')
                ->label('Clôturer la bande')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Clôturer la bande')
                ->modalDescription('Cette action va clôturer définitivement la bande. Veuillez ajouter une note de clôture si nécessaire.')
                ->form([
                    Textarea::make('notes')
                        ->label('Notes de clôture')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    try {
                        $report = app(FlockService::class)->completeFlock(
                            $this->record,
                            $data['notes'] ?? null,
                        );
                    } catch (\LogicException $e) {
                        Notification::make()
                            ->title('Clôture impossible')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    $duration = $report['duree_jours'] ?? '—';
                    $mortality = number_format($report['taux_mortalite'] ?? 0, 1);
                    $losses = $report['pertes_totales'] ?? 0;

                    $body = "Durée : {$duration} jours | Mortalité : {$mortality}% ({$losses} sujets perdus)";

                    if (isset($report['oeufs_total'])) {
                        $body .= " | Œufs : {$report['oeufs_total']}";
                    }

                    if (isset($report['gmq'])) {
                        $body .= " | GMQ : {$report['gmq']} g/j";
                    }

                    Notification::make()
                        ->title('Bande clôturée avec succès')
                        ->body($body)
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'actual_end_date', 'notes']);
                })
                ->visible(fn () => $this->record->isActive() && FlockResource::canPerformAction('complete')),

            Action::make('cancel')
                ->label('Annuler la bande')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Annuler la bande')
                ->modalDescription('Cette action est irréversible. La bande sera définitivement annulée.')
                ->form([
                    Textarea::make('reason')
                        ->label("Motif d'annulation")
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    app(FlockService::class)->cancelFlock($this->record, $data['reason']);

                    Notification::make()
                        ->title('Bande annulée')
                        ->body("La bande {$this->record->code} a été annulée.")
                        ->warning()
                        ->send();

                    $this->refreshFormData(['status', 'actual_end_date', 'notes']);
                })
                ->visible(fn () => ($this->record->isPlanned() || $this->record->isActive()) && FlockResource::canPerformAction('cancel')),

            EditAction::make()
                ->visible(fn () => FlockResource::canEdit($this->record)),
            DeleteAction::make()
                ->visible(fn () => FlockResource::canDelete($this->record)),
        ];
    }
}
