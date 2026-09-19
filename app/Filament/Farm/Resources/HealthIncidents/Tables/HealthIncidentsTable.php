<?php

namespace App\Filament\Farm\Resources\HealthIncidents\Tables;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Filament\Farm\Resources\HealthIncidents\HealthIncidentResource;
use App\Models\Farm;
use App\Models\Flock;
use App\Services\CurrentFarm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HealthIncidentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label("Titre de l'incident")
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(40),

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

                TextColumn::make('incident_date')
                    ->label('Date incident')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('severity')
                    ->label('Gravité')
                    ->badge()
                    ->color(fn (IncidentSeverity $state) => $state->color())
                    ->icon(fn (IncidentSeverity $state) => $state->icon())
                    ->formatStateUsing(fn (IncidentSeverity $state) => $state->label())
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Catégorie')
                    ->badge()
                    ->color(fn (IncidentCategory $state) => $state->color())
                    ->formatStateUsing(fn (IncidentCategory $state) => $state->label())
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (IncidentStatus $state) => $state->color())
                    ->icon(fn (IncidentStatus $state) => $state->icon())
                    ->formatStateUsing(fn (IncidentStatus $state) => $state->label())
                    ->sortable(),

                TextColumn::make('birds_affected')
                    ->label('Sujets affectés')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('birds_dead')
                    ->label('Morts')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'danger' : null)
                    ->alignEnd(),

                TextColumn::make('quarantine_applied')
                    ->label('Quarantaine')
                    ->badge()
                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state) => $state ? 'Oui' : 'Non')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Création')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('incident_date', 'desc')
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

                SelectFilter::make('severity')
                    ->label('Gravité')
                    ->options(collect(IncidentSeverity::cases())
                        ->mapWithKeys(fn (IncidentSeverity $s) => [$s->value => $s->label()])
                        ->toArray()),

                SelectFilter::make('category')
                    ->label('Catégorie')
                    ->options(collect(IncidentCategory::cases())
                        ->mapWithKeys(fn (IncidentCategory $c) => [$c->value => $c->label()])
                        ->toArray()),

                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(collect(IncidentStatus::cases())
                        ->mapWithKeys(fn (IncidentStatus $s) => [$s->value => $s->label()])
                        ->toArray()),

                TernaryFilter::make('quarantine_applied')
                    ->label('Quarantaine')
                    ->trueLabel('Avec quarantaine')
                    ->falseLabel('Sans quarantaine')
                    ->queries(
                        true: fn (Builder $q) => $q->where('quarantine_applied', true),
                        false: fn (Builder $q) => $q->where('quarantine_applied', false),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => HealthIncidentResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => HealthIncidentResource::canDeleteAny()),
                ]),
            ]);
    }
}
