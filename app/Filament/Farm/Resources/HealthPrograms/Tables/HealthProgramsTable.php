<?php

namespace App\Filament\Farm\Resources\HealthPrograms\Tables;

use App\Filament\Farm\Resources\HealthPrograms\HealthProgramResource;
use App\Models\ChickenType;
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
use Illuminate\Database\Eloquent\Builder;

class HealthProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('treatment_name')
                    ->label('Nom du traitement')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('treatment_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->getTreatmentTypeLabel())
                    ->color(fn ($state) => match ($state) {
                        'vaccin' => 'success',
                        'vermifuge', 'anticoccidien', 'antiparasitaire' => 'warning',
                        'vitamine' => 'primary',
                        'antibiotique' => 'danger',
                        default => 'gray',
                    })
                    ->icons([
                        'heroicon-o-beaker' => 'vaccin',
                        'heroicon-o-beaker' => 'vermifuge',
                        'heroicon-o-beaker' => 'anticoccidien',
                        'heroicon-o-beaker' => 'vitamine',
                        'heroicon-o-beaker' => 'antibiotique',
                    ])
                    ->sortable(),
                TextColumn::make('chickenType.name')
                    ->label('Type de volaille')
                    ->badge()
                    ->color('info')
                    ->placeholder('Tous types')
                    ->sortable(),
                TextColumn::make('scheduled_day')
                    ->label('Jour prévu')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => 'J'.$state)
                    ->alignEnd()
                    ->description(fn ($record) => 'Semaine '.ceil($record->scheduled_day / 7)),

                TextColumn::make('administration_route')
                    ->label('Voie')
                    ->formatStateUsing(fn ($record) => $record->getAdministrationRouteLabel())
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('dosage')
                    ->label('Dosage')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('withdrawal_days')
                    ->label('Délai attente')
                    ->numeric()
                    ->sortable()
                    ->suffix(' j')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->toggleable()
                    ->placeholder('0'),

                TextColumn::make('is_mandatory')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Obligatoire' : 'Recommandé')
                    ->colors([
                        'danger' => true,
                        'info' => false,
                    ])
                    ->icons([
                        'heroicon-o-exclamation-triangle' => true,
                        'heroicon-o-information-circle' => false,
                    ]),

                TextColumn::make('farm.name')
                    ->label('Ferme')
                    ->placeholder('Toutes')
                    ->sortable()
                    ->hidden(function () {
                        $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                        return $membership && app(CurrentFarm::class)->get($membership) !== null;
                    }),
            ])
            ->defaultSort('scheduled_day')
            ->filters([
                SelectFilter::make('chicken_type_id')
                    ->label('Type de volaille')
                    ->options(fn () => ChickenType::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('treatment_type')
                    ->label('Type de traitement')
                    ->options([
                        'vaccin' => 'Vaccin',
                        'vermifuge' => 'Vermifuge',
                        'anticoccidien' => 'Anticoccidien',
                        'vitamine' => 'Vitamine / Complément',
                        'antibiotique' => 'Antibiotique',
                        'antiparasitaire' => 'Antiparasitaire',
                        'desinfectant' => 'Désinfectant',
                        'autre' => 'Autre',
                    ]),

                SelectFilter::make('farm_id')
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
                    ->searchable()
                    ->preload()
                    ->hidden(function () {
                        $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                        return $membership && app(CurrentFarm::class)->get($membership) !== null;
                    }),

                SelectFilter::make('is_mandatory')
                    ->label('Statut')
                    ->options([
                        '1' => 'Obligatoire',
                        '0' => 'Recommandé',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => HealthProgramResource::canEdit($record)),
                DeleteAction::make()->visible(fn ($record) => HealthProgramResource::canDelete($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => HealthProgramResource::canDeleteAny()),
                ]),
            ]);
    }
}
