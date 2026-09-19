<?php

namespace App\Filament\Farm\Resources\DailyRecords\Tables;

use App\Filament\Farm\Resources\DailyRecords\DailyRecordResource;
use App\Models\Farm;
use App\Models\Flock;
use App\Services\CurrentFarm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DailyRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('record_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('flock.code')
                    ->label('Bande')
                    ->sortable()
                    ->searchable()
                    ->description(fn ($record) => $record->flock?->name),

                TextColumn::make('flock.building.farm.name')
                    ->label('Ferme')
                    ->sortable()
                    ->hidden(function () {
                        $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                        return $membership && app(CurrentFarm::class)->get($membership) !== null;
                    }),

                TextColumn::make('flock.building.name')
                    ->label('Bâtiment')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('age_days')
                    ->label('Âge')
                    ->numeric()
                    ->sortable()
                    ->suffix(' j')
                    ->alignEnd(),

                TextColumn::make('mortality')
                    ->label('Mortalité')
                    ->numeric()
                    ->sortable()
                    ->suffix(' sujets')
                    ->color(fn ($state) => $state > 0 ? 'danger' : null)
                    ->icon(fn ($state) => $state > 0 ? 'heroicon-m-exclamation-triangle' : null),
                TextColumn::make('culls')
                    ->label('Réformes')
                    ->numeric()
                    ->sortable()
                    ->suffix(' sujets')
                    ->toggleable(),
                TextColumn::make('birds_present')
                    ->label('Effectif présent')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('eggs_collected')
                    ->label('Œufs collectés')
                    ->numeric()
                    ->sortable()
                    ->suffix(' œufs')
                    ->color(fn ($state) => $state > 0 ? 'success' : null)
                    ->toggleable(),

                TextColumn::make('eggs_broken')
                    ->label('Œufs cassés')
                    ->numeric()
                    ->sortable()
                    ->suffix(' œufs')
                    ->color(fn ($state) => $state > 0 ? 'warning' : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('feed_consumed_kg')
                    ->label('Aliment')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->suffix(' kg')
                    ->toggleable(),

                TextColumn::make('water_consumed_l')
                    ->label('Eau')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->suffix(' L')
                    ->toggleable(),

                TextColumn::make('average_weight_g')
                    ->label('Poids moy.')
                    ->numeric(decimalPlaces: 0)
                    ->sortable()
                    ->suffix(' g')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('temperature_max')
                    ->label('Temp. max')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->suffix(' °C')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('humidity_percent')
                    ->label('Humidité')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->suffix(' %')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('recorder.name')
                    ->label('Enregistré par')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Création')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('record_date', 'desc')
            ->filters([
                SelectFilter::make('flock_id')
                    ->label('Bande')
                    ->options(function () {
                        $tenant = Filament::getTenant();
                        $user = Filament::auth()->user();
                        $membership = $user?->membershipIn($tenant);
                        $currentFarm = $membership ? app(CurrentFarm::class)->get($membership) : null;

                        return Flock::query()
                            ->whereHas('building.farm', function (Builder $q) use ($tenant, $membership, $currentFarm) {
                                $q->where('organisation_id', $tenant?->getKey());
                                if ($membership) {
                                    $q->accessibleBy($membership);
                                }
                                if ($currentFarm) {
                                    $q->where('id', $currentFarm->id);
                                }
                            })
                            ->orderByDesc('start_date')
                            ->get()
                            ->mapWithKeys(fn (Flock $f) => [$f->id => $f->code.($f->name ? ' — '.$f->name : '')])
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),

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
                        fn (Builder $q, $farmId) => $q->whereHas('flock.building', fn (Builder $b) => $b->where('farm_id', $farmId))
                    ))
                    ->searchable()
                    ->preload()
                    ->hidden(function () {
                        $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                        return $membership && app(CurrentFarm::class)->get($membership) !== null;
                    }),

                Filter::make('record_date')
                    ->label('Période')
                    ->form([
                        DatePicker::make('from')
                            ->label('Du')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label('Au')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('record_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('record_date', '<=', $date));
                    }),

                TernaryFilter::make('with_mortality')
                    ->label('Mortalité')
                    ->trueLabel('Avec mortalité')
                    ->falseLabel('Sans mortalité')
                    ->queries(
                        true: fn (Builder $q) => $q->where('mortality', '>', 0),
                        false: fn (Builder $q) => $q->where('mortality', 0),
                    ),

                TernaryFilter::make('with_production')
                    ->label('Production')
                    ->trueLabel('Avec production')
                    ->falseLabel('Sans production')
                    ->queries(
                        true: fn (Builder $q) => $q->where('eggs_collected', '>', 0),
                        false: fn (Builder $q) => $q->where('eggs_collected', 0),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => DailyRecordResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => DailyRecordResource::canDeleteAny()),
                ]),
            ]);
    }
}
