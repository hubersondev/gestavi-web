<?php

namespace App\Filament\Resources\Organisations\Tables;

use App\Enums\MemberStatus;
use App\Enums\OrganisationStatus;
use App\Services\SubscriptionService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganisationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount(['farms', 'memberships as active_members_count' => fn (Builder $q) => $q->where('status', MemberStatus::Active->value)])
                ->withMax('activities', 'created_at'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Adresse email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Numéro de téléphone')
                    ->searchable(),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->placeholder('Aucun')
                    ->sortable(),
                TextColumn::make('subscription_ends_at')
                    ->label('Abonnement')
                    ->state(function ($record): string {
                        $subscription = $record->currentSubscription();

                        return $subscription
                            ? $subscription->status->getLabel().' jusqu\'au '.$subscription->ends_at->format('d/m/Y')
                            : 'Aucun';
                    })
                    ->color(function ($record): string {
                        $subscription = $record->currentSubscription();

                        return match (true) {
                            $subscription === null => 'gray',
                            $subscription->isExpiringSoon() => 'warning',
                            default => 'success',
                        };
                    })
                    ->badge()
                    ->toggleable(),
                TextColumn::make('farms_count')
                    ->label('Fermes')
                    ->alignRight()
                    ->sortable(),
                TextColumn::make('active_members_count')
                    ->label('Membres')
                    ->alignRight()
                    ->sortable(),
                TextColumn::make('activities_max_created_at')
                    ->label('Dernière activité')
                    ->since()
                    ->placeholder('jamais')
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label('Pays')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city.name')
                    ->label('Ville')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Date de création')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Date de modification')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(OrganisationStatus::class)
                    ->multiple(),
                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'name'),
                Filter::make('expiring')
                    ->label('Abonnement à échéance sous 7 jours ou en délai de grâce')
                    ->query(fn (Builder $query): Builder => $query->whereHas('subscriptions', fn (Builder $q) => $q
                        ->where(fn (Builder $w) => $w->where(fn (Builder $soon) => $soon->expiringWithin(7))
                            ->orWhere(fn (Builder $expired) => $expired->where('status', 'expired')->whereDate('ends_at', '>=', today()->subDays(SubscriptionService::graceDays()))))))
                    ->toggle(),
                Filter::make('without_owner')
                    ->label('Sans propriétaire actif')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('memberships', fn (Builder $q) => $q
                        ->where('status', MemberStatus::Active->value)
                        ->whereHas('organisationRole', fn (Builder $r) => $r->where('slug', 'owner'))))
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
