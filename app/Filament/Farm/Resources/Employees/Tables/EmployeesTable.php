<?php

namespace App\Filament\Farm\Resources\Employees\Tables;

use App\Enums\EmployeeStatus;
use App\Filament\Farm\Resources\Employees\EmployeeResource;
use App\Models\Farm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->full_name).'&background=random&size=40'),

                TextColumn::make('employee_number')
                    ->label('Matricule')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('full_name')
                    ->label('Nom complet')
                    ->state(fn ($record) => $record->full_name)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('last_name', $direction)->orderBy('first_name', $direction);
                    }),

                TextColumn::make('activeContract.position')
                    ->label('Poste')
                    ->placeholder('—')
                    ->limit(20),

                TextColumn::make('department')
                    ->label('Département')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'production' => 'Production',
                        'maintenance' => 'Maintenance',
                        'administration' => 'Administration',
                        'securite' => 'Sécurité',
                        'logistique' => 'Logistique',
                        'vente' => 'Vente',
                        default => $state ?? '-',
                    })
                    ->color('info')
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn ($state) => $state->color())
                    ->sortable(),

                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable()
                    ->icon('heroicon-o-phone')
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('hire_date')
                    ->label('Embauché le')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('seniority')
                    ->label('Ancienneté')
                    ->state(fn ($record) => $record->getSeniorityInYears())
                    ->suffix(' ans')
                    ->alignEnd()
                    ->toggleable(),

                TextColumn::make('farm.name')
                    ->label('Ferme')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('activeContract.type')
                    ->label('Type de contrat')
                    ->badge()
                    ->color(fn ($state) => $state?->color())
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_name')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(EmployeeStatus::class),

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
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn ($record) => EmployeeResource::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => EmployeeResource::canDeleteAny()),
                ]),
            ]);
    }
}
