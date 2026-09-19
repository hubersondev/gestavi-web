<?php

namespace App\Filament\Farm\Resources\FarmLogs\Pages;

use App\Filament\Farm\Resources\FarmLogs\FarmLogResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewFarmLog extends ViewRecord
{
    protected static string $resource = FarmLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resolve')
                ->label('Marquer comme résolu')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->form([
                    Textarea::make('notes')
                        ->label('Notes de résolution (optionnel)')
                        ->rows(3)
                        ->placeholder('Décrire comment le problème a été résolu...'),
                ])
                ->action(function (array $data): void {
                    $this->record->resolve(Filament::auth()->id(), $data['notes'] ?? null);
                    $this->refreshFormData(['is_resolved', 'resolved_at', 'resolved_by']);
                })
                ->visible(fn () => ! $this->record->is_resolved && FarmLogResource::canPerformAction('resolve')),

            Action::make('reopen')
                ->label('Rouvrir')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Rouvrir cette entrée ?')
                ->action(function (): void {
                    $this->record->reopen();
                    $this->refreshFormData(['is_resolved', 'resolved_at', 'resolved_by']);
                })
                ->visible(fn () => $this->record->is_resolved && FarmLogResource::canPerformAction('reopen')),

            EditAction::make()
                ->visible(fn () => FarmLogResource::canEdit($this->record)),
            DeleteAction::make()
                ->visible(fn () => FarmLogResource::canDelete($this->record)),
        ];
    }
}
