<?php

namespace App\Filament\Farm\Resources\VetVisits\Tables;

use App\Enums\VetVisitType;
use App\Filament\Farm\Resources\VetVisits\VetVisitResource;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Veterinarian;
use App\Services\CurrentFarm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VetVisitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('visit_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('visit_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('farm.name')
                    ->label('Ferme')
                    ->sortable()
                    ->hidden(function () {
                        $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                        return $membership && app(CurrentFarm::class)->get($membership) !== null;
                    }),

                TextColumn::make('flock.code')
                    ->label('Bande')
                    ->sortable()
                    ->placeholder('Ferme entière'),

                TextColumn::make('veterinarian.name')
                    ->label('Vétérinaire')
                    ->sortable()
                    ->searchable()
                    ->description(fn ($record) => $record->veterinarian?->clinic),

                TextColumn::make('reason')
                    ->label('Motif')
                    ->limit(50)
                    ->searchable(),

                TextColumn::make('total_cost')
                    ->label('Coût total')
                    ->money('XOF')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),

                IconColumn::make('is_paid')
                    ->label('Payé')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('follow_up_required')
                    ->label('Suivi requis')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('next_visit_date')
                    ->label('Prochaine visite')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('-')
                    ->color(fn ($record) => match (true) {
                        $record->follow_up_required && $record->getDaysUntilNextVisit() !== null && $record->getDaysUntilNextVisit() < 0 => 'danger',
                        $record->follow_up_required && $record->getDaysUntilNextVisit() !== null && $record->getDaysUntilNextVisit() <= 3 => 'warning',
                        default => null,
                    })
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Création')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('visit_date', 'desc')
            ->filters([
                SelectFilter::make('visit_type')
                    ->label('Type de visite')
                    ->options(VetVisitType::class)
                    ->native(false),

                SelectFilter::make('veterinarian_id')
                    ->label('Vétérinaire')
                    ->options(function () {
                        return Veterinarian::query()
                            ->where('organisation_id', Filament::getTenant()?->getKey())
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload(),

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

                Filter::make('visit_date')
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
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('visit_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('visit_date', '<=', $date));
                    }),

                TernaryFilter::make('follow_up_required')
                    ->label('Suivi requis')
                    ->trueLabel('Avec suivi requis')
                    ->falseLabel('Sans suivi requis'),

                TernaryFilter::make('is_paid')
                    ->label('Paiement')
                    ->trueLabel('Payées')
                    ->falseLabel('Non payées'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => VetVisitResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => VetVisitResource::canDeleteAny()),
                ]),
            ]);
    }
}
