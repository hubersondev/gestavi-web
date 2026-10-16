<?php

namespace App\Filament\Resources\Organisations\RelationManagers;

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Historique des abonnements d'une organisation, avec accès à la fiche de chacun.
 */
class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Abonnements';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('ends_at', 'desc')
            ->columns([
                TextColumn::make('plan_name')->label('Plan'),
                TextColumn::make('status')->label('Statut')->badge(),
                TextColumn::make('billing_cycle')->label('Cycle')->badge()->color('gray'),
                TextColumn::make('price')->label('Prix')->money('XOF', locale: 'fr'),
                TextColumn::make('starts_at')->label('Début')->date('d/m/Y'),
                TextColumn::make('ends_at')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->color(fn (Subscription $record): string => $record->isExpiringSoon() ? 'warning' : ($record->isCurrent() ? 'success' : 'danger')),
            ])
            ->headerActions([
                Action::make('create')
                    ->label('Nouvel abonnement')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => SubscriptionResource::getUrl('create')),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('Voir')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Subscription $record): string => SubscriptionResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
