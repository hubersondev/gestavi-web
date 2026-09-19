<?php

namespace App\Filament\Farm\Resources\Equipments\Pages;

use App\Filament\Farm\Resources\Equipments\EquipmentResource;
use App\Models\Building;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewEquipment extends ViewRecord
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => EquipmentResource::canEdit($this->record)),

            Action::make('move')
                ->label('Déplacer')
                ->icon('heroicon-o-arrow-right-circle')
                ->color('info')
                ->visible(fn () => ! $this->record->isDisposed() && EquipmentResource::canPerformAction('move'))
                ->form([
                    Select::make('building_id')
                        ->label('Bâtiment de destination')
                        ->options(function () {
                            $tenant = Filament::getTenant();
                            $user = Filament::auth()->user();
                            $membership = $user?->membershipIn($tenant);

                            return Building::query()
                                ->whereHas('farm', function ($q) use ($tenant, $membership) {
                                    $q->where('organisation_id', $tenant?->getKey());
                                    if ($membership) {
                                        $q->accessibleBy($membership);
                                    }
                                })
                                ->where('id', '!=', $this->record->building_id)
                                ->get()
                                ->mapWithKeys(fn (Building $b) => [$b->id => $b->farm->name.' — '.$b->name])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Laisser vide pour mettre en stock'),

                    Textarea::make('reason')
                        ->label('Motif du déplacement')
                        ->rows(2)
                        ->placeholder('Ex: Transfert pour la bande suivante'),
                ])
                ->action(function (array $data) {
                    $this->record->moveTo($data['building_id'] ?? null, $data['reason'] ?? '');
                    $this->refreshFormData(['building_id', 'farm_id', 'status']);
                }),

            Action::make('put_in_maintenance')
                ->label('Mettre en maintenance')
                ->icon('heroicon-o-wrench')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Mettre en maintenance')
                ->modalDescription('Le statut de l\'équipement passera à "En maintenance".')
                ->visible(fn () => ($this->record->isInService() || $this->record->isInStock()) && EquipmentResource::canPerformAction('maintenance'))
                ->action(function () {
                    $this->record->putInMaintenance();
                    $this->refreshFormData(['status']);
                }),

            Action::make('return_from_maintenance')
                ->label('Retour de maintenance')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Retour de maintenance')
                ->modalDescription('L\'équipement repassera en statut "En service" et la date de dernière maintenance sera enregistrée.')
                ->visible(fn () => $this->record->isInMaintenance() && EquipmentResource::canPerformAction('maintenance'))
                ->action(function () {
                    $this->record->returnFromMaintenance();
                    $this->refreshFormData(['status', 'last_maintenance_date']);
                }),

            Action::make('dispose')
                ->label('Réformer')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Réformer l\'équipement')
                ->modalDescription('L\'équipement sera marqué comme "Réformé" et retiré de tout bâtiment. Cette action est irréversible.')
                ->visible(fn () => ! $this->record->isDisposed() && EquipmentResource::canPerformAction('dispose'))
                ->action(function () {
                    $this->record->dispose();
                    $this->refreshFormData(['status', 'building_id']);
                }),
        ];
    }
}
