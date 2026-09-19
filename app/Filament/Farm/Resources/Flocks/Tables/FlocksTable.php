<?php

namespace App\Filament\Farm\Resources\Flocks\Tables;

use App\Enums\FlockStatus;
use App\Models\Farm;
use App\Services\CurrentFarm;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Filament\Farm\Resources\Flocks\FlockResource;
use Illuminate\Database\Eloquent\Builder;

class FlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('building.farm.name')
                    ->label('Ferme')
                    ->sortable()
                    ->hidden(function () {
                        $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                        return $membership && app(CurrentFarm::class)->get($membership) !== null;
                    }),
                TextColumn::make('building.name')
                    ->label('Bâtiment')
                    ->sortable(),
                TextColumn::make('chickenType.name')
                    ->label('Type')
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('breed')
                    ->label('Race')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quantity_started')
                    ->label('Effectif initial')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('quantity_current')
                    ->label('Effectif actuel')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Mise en place')
                    ->date()
                    ->sortable(),
                TextColumn::make('current_phase')
                    ->label('Phase actuelle')
                    ->state(fn ($record) => $record->getCurrentPhase()?->name)
                    ->placeholder('—')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Création')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('farm')
                    ->label('Ferme')
                    ->options(function () {
                        $tenant = Filament::getTenant();
                        $user = Filament::auth()->user();
                        $membership = $user?->membershipIn($tenant);

                        return Farm::query()
                            ->where('organisation_id', $tenant?->getKey())
                            ->when($membership, fn (Builder $q) => $q->accessibleBy($membership))
                            ->pluck('name', 'id');
                    })
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'],
                        fn (Builder $q, $farmId) => $q->whereHas('building', fn (Builder $b) => $b->where('farm_id', $farmId))
                    ))
                    ->searchable()
                    ->preload()
                    ->hidden(function () {
                        $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                        return $membership && app(CurrentFarm::class)->get($membership) !== null;
                    }),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(FlockStatus::class),
                SelectFilter::make('chicken_type_id')
                    ->label('Type de volaille')
                    ->relationship('chickenType', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => FlockResource::canEdit($record)),
                Action::make('start')
                    ->label('Démarrer')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Démarrer la bande')
                    ->modalDescription('La bande passera en statut "En cours". Cette action est irréversible.')
                    ->visible(fn ($record) => $record->isPlanned() && FlockResource::canPerformAction('start'))
                    ->action(fn ($record) => app(\App\Services\FlockService::class)->startFlock($record)),
                Action::make('complete')
                    ->label('Terminer')
                    ->icon('heroicon-o-check-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Terminer la bande')
                    ->modalDescription('La bande passera en statut "Terminé" et la date de fin réelle sera enregistrée.')
                    ->visible(fn ($record) => $record->isActive() && FlockResource::canPerformAction('complete'))
                    ->action(fn ($record) => app(\App\Services\FlockService::class)->completeFlock($record)),
                Action::make('cancel')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Annuler la bande')
                    ->modalDescription('La bande passera en statut "Annulé". Cette action est irréversible.')
                    ->visible(fn ($record) => ($record->isPlanned() || $record->isActive()) && FlockResource::canPerformAction('cancel'))
                    ->action(fn ($record) => app(\App\Services\FlockService::class)->cancelFlock($record, 'Annulée depuis la liste')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => FlockResource::canDeleteAny()),
                ]),
            ]);
    }
}
