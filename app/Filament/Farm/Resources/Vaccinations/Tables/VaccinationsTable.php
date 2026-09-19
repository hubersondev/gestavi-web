<?php

namespace App\Filament\Farm\Resources\Vaccinations\Tables;

use App\Enums\VaccinationStatus;
use App\Filament\Farm\Resources\Vaccinations\Schemas\VaccinationForm;
use App\Filament\Farm\Resources\Vaccinations\VaccinationResource;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VaccinationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
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

                TextColumn::make('treatment_name')
                    ->label('Traitement')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('treatment_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'vaccin' => 'info',
                        'antibiotique' => 'danger',
                        'vitamine' => 'success',
                        'vermifuge', 'anticoccidien', 'antiparasitaire' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => VaccinationForm::treatmentTypeOptions()[$state] ?? $state)
                    ->sortable(),

                TextColumn::make('scheduled_date')
                    ->label('Date prévue')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('administered_date')
                    ->label('Date réalisée')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (VaccinationStatus $state) => $state->color())
                    ->icon(fn (VaccinationStatus $state) => $state->icon())
                    ->formatStateUsing(fn (VaccinationStatus $state) => $state->label())
                    ->sortable(),

                TextColumn::make('birds_treated')
                    ->label('Sujets traités')
                    ->numeric()
                    ->suffix(' sujets')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cost')
                    ->label('Coût')
                    ->money('XOF')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Création')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('scheduled_date', 'desc')
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

                SelectFilter::make('treatment_type')
                    ->label('Type de traitement')
                    ->options(VaccinationForm::treatmentTypeOptions()),

                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(collect(VaccinationStatus::cases())
                        ->mapWithKeys(fn (VaccinationStatus $s) => [$s->value => $s->label()])
                        ->toArray()),

                Filter::make('scheduled_date')
                    ->label('Période prévue')
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
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('scheduled_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('scheduled_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => VaccinationResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => VaccinationResource::canDeleteAny()),
                ]),
            ]);
    }
}
