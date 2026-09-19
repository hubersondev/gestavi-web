<?php

namespace App\Filament\Farm\Resources\Equipments\Tables;

use App\Models\Farm;
use App\Services\CurrentFarm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Filament\Farm\Resources\Equipments\EquipmentResource;
use Illuminate\Database\Eloquent\Builder;

class EquipmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inventory_number')
                    ->label('N° inventaire')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('building.farm.name')
                    ->label('Ferme')
                    ->sortable()
                    ->placeholder('Non assigné')
                    ->hidden(function () {
                        $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                        return $membership && app(CurrentFarm::class)->get($membership) !== null;
                    }),

                TextColumn::make('building.name')
                    ->label('Bâtiment')
                    ->sortable()
                    ->placeholder('En stock'),

                TextColumn::make('category')
                    ->label('Catégorie')
                    ->formatStateUsing(fn ($record) => $record->getCategoryLabel())
                    ->sortable(),

                TextColumn::make('brand')
                    ->label('Marque')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->getStatusLabel())
                    ->color(fn ($state) => match ($state) {
                        'in_service' => 'success',
                        'in_stock' => 'info',
                        'in_maintenance' => 'warning',
                        'out_of_service' => 'danger',
                        'disposed' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('condition')
                    ->label('État')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->getConditionLabel())
                    ->color(fn ($state) => match ($state) {
                        'new' => 'success',
                        'good' => 'info',
                        'fair' => 'warning',
                        'poor', 'broken' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('purchase_price')
                    ->label('Prix d\'achat')
                    ->money('XOF')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('next_maintenance_date')
                    ->label('Proch. maintenance')
                    ->date()
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Création')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
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
                        fn (Builder $q, $farmId) => $q->where('farm_id', $farmId)
                    ))
                    ->searchable()
                    ->preload()
                    ->hidden(function () {
                        $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                        return $membership && app(CurrentFarm::class)->get($membership) !== null;
                    }),

                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'in_service' => 'En service',
                        'in_stock' => 'En stock',
                        'in_maintenance' => 'En maintenance',
                        'out_of_service' => 'Hors service',
                        'disposed' => 'Réformé',
                    ]),

                SelectFilter::make('category')
                    ->label('Catégorie')
                    ->options([
                        'alimentation' => 'Alimentation',
                        'abreuvement' => 'Abreuvement',
                        'ventilation' => 'Ventilation',
                        'chauffage' => 'Chauffage',
                        'eclairage' => 'Éclairage',
                        'pesage' => 'Pesage',
                        'transport' => 'Transport',
                        'nettoyage' => 'Nettoyage',
                        'stockage' => 'Stockage',
                        'abattage' => 'Abattage',
                        'autre' => 'Autre',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => EquipmentResource::canEdit($record)),
                DeleteAction::make()->visible(fn ($record) => EquipmentResource::canDelete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => EquipmentResource::canDeleteAny()),
                ]),
            ]);
    }
}
