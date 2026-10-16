<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Abonnement')
                ->columns(3)
                ->schema([
                    TextEntry::make('organisation.name')->label('Structure'),
                    TextEntry::make('plan_name')->label('Plan'),
                    TextEntry::make('status')->label('Statut')->badge(),
                    TextEntry::make('billing_cycle')->label('Cycle')->badge()->color('gray'),
                    TextEntry::make('starts_at')->label('Début')->date('d/m/Y'),
                    TextEntry::make('ends_at')
                        ->label('Échéance')
                        ->date('d/m/Y')
                        ->helperText(fn (Subscription $record): string => $record->isCurrent() ? $record->daysRemaining().' jour(s) restant(s)' : 'Terminé')
                        ->color(fn (Subscription $record): string => $record->isExpiringSoon() ? 'warning' : ($record->isCurrent() ? 'success' : 'danger')),
                    TextEntry::make('trial_ends_at')->label('Fin d\'essai')->date('d/m/Y')->placeholder('—'),
                    TextEntry::make('cancelled_at')->label('Annulé le')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('cancellation_reason')->label('Motif d\'annulation')->placeholder('—'),
                ]),
            Section::make('Paiement')
                ->columns(3)
                ->schema([
                    TextEntry::make('price')->label('Prix de la période')->money('XOF', locale: 'fr'),
                    TextEntry::make('payment_method')->label('Mode de paiement')->badge()->placeholder('—'),
                    TextEntry::make('payment_reference')->label('Référence')->placeholder('—'),
                    TextEntry::make('creator.name')->label('Créé par')->placeholder('—'),
                    TextEntry::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i'),
                    TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Utilisation du plan')
                ->columns(2)
                ->schema([
                    TextEntry::make('usage_farms')
                        ->label('Fermes')
                        ->state(function (Subscription $record): string {
                            $usage = app(SubscriptionService::class)->usage($record->organisation, $record->plan);

                            return $usage['farms_used'].' / '.($usage['farms_max'] ?? 'illimité');
                        }),
                    TextEntry::make('usage_users')
                        ->label('Utilisateurs actifs')
                        ->state(function (Subscription $record): string {
                            $usage = app(SubscriptionService::class)->usage($record->organisation, $record->plan);

                            return $usage['users_used'].' / '.($usage['users_max'] ?? 'illimité');
                        }),
                ]),
        ]);
    }
}
