<?php

namespace App\Filament\Resources\Subscriptions\Tables;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\Actions\SubscriptionActions;
use App\Models\Plan;
use App\Models\Subscription;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ends_at')
            ->columns([
                TextColumn::make('organisation.name')
                    ->label('Structure')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan_name')
                    ->label('Plan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),
                TextColumn::make('billing_cycle')
                    ->label('Cycle')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('price')
                    ->label('Prix')
                    ->money('XOF', locale: 'fr')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('starts_at')
                    ->label('Début')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ends_at')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->sortable()
                    ->description(fn (Subscription $record): ?string => $record->isCurrent() ? $record->daysRemaining().' j restants' : null)
                    ->color(fn (Subscription $record): string => $record->isExpiringSoon() ? 'warning' : ($record->isCurrent() ? 'success' : 'danger')),
                TextColumn::make('payment_method')
                    ->label('Paiement')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(SubscriptionStatus::class)
                    ->multiple(),
                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->options(fn (): array => Plan::query()->orderBy('sort_order')->pluck('name', 'id')->all()),
                SelectFilter::make('billing_cycle')
                    ->label('Cycle')
                    ->options(BillingCycle::class),
                Filter::make('expiring')
                    ->label('Échéance sous 7 jours')
                    ->query(fn (Builder $query): Builder => $query->expiringWithin(7))
                    ->toggle(),
                Filter::make('current')
                    ->label('En cours seulement')
                    ->query(fn (Builder $query): Builder => $query->current())
                    ->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make(SubscriptionActions::make()),
            ]);
    }
}
