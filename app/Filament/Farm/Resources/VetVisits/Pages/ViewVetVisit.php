<?php

namespace App\Filament\Farm\Resources\VetVisits\Pages;

use App\Filament\Farm\Resources\VetVisits\VetVisitResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewVetVisit extends ViewRecord
{
    protected static string $resource = VetVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mark_paid')
                ->label('Marquer comme payé')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmer le paiement')
                ->modalDescription('Confirmer que cette visite a été réglée ?')
                ->action(fn () => $this->record->markAsPaid())
                ->visible(fn () => ! $this->record->is_paid && VetVisitResource::canPerformAction('mark_paid')),

            Action::make('create_follow_up')
                ->label('Créer un suivi')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->url(fn () => VetVisitResource::getUrl('create', [
                    'parent_visit_id' => $this->record->getKey(),
                    'farm_id' => $this->record->farm_id,
                    'flock_id' => $this->record->flock_id,
                    'veterinarian_id' => $this->record->veterinarian_id,
                ]))
                ->visible(fn () => $this->record->follow_up_required && VetVisitResource::canPerformAction('create_follow_up')),

            EditAction::make()
                ->visible(fn () => VetVisitResource::canEdit($this->record)),
            DeleteAction::make()
                ->visible(fn () => VetVisitResource::canDelete($this->record)),
        ];
    }
}
