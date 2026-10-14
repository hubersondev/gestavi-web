<?php

namespace App\Filament\Farm\Resources\FarmLogs\Tables;

use App\Enums\FarmLogCategory;
use App\Enums\FarmLogPriority;
use App\Filament\Farm\Resources\FarmLogs\FarmLogResource;
use App\Models\Farm;
use App\Models\Flock;
use App\Services\CurrentFarm;
use App\Services\CurrentFiscalYear;
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

class FarmLogsTable
{
    public static function configure(Table $table): Table
    {
        return app(CurrentFiscalYear::class)->applyToTable($table, 'log_date')
            ->columns([
                TextColumn::make('log_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category')
                    ->label('Catégorie')
                    ->badge()
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Priorité')
                    ->badge()
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->limit(50),

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

                TextColumn::make('building.name')
                    ->label('Bâtiment')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('requires_follow_up')
                    ->label('Suivi')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_resolved')
                    ->label('Résolu')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('follow_up_date')
                    ->label('Date suivi')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('-')
                    ->color(fn ($record) => $record->isOverdueFollowUp() ? 'danger' : null)
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
            ->defaultSort('log_date', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->label('Catégorie')
                    ->options(FarmLogCategory::class)
                    ->multiple()
                    ->native(false),

                SelectFilter::make('priority')
                    ->label('Priorité')
                    ->options(FarmLogPriority::class)
                    ->multiple()
                    ->native(false),

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

                Filter::make('log_date')
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
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('log_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('log_date', '<=', $date));
                    }),

                TernaryFilter::make('is_resolved')
                    ->label('Statut')
                    ->trueLabel('Résolus')
                    ->falseLabel('Non résolus'),

                TernaryFilter::make('requires_follow_up')
                    ->label('Suivi requis')
                    ->trueLabel('Avec suivi requis')
                    ->falseLabel('Sans suivi requis'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => FarmLogResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => FarmLogResource::canDeleteAny()),
                ]),
            ]);
    }
}
