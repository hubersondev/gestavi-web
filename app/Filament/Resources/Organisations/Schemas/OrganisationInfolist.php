<?php

namespace App\Filament\Resources\Organisations\Schemas;

use App\Services\SubscriptionService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrganisationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('email')
                    ->label('Adresse e-mail')
                    ->placeholder('-'),
                TextEntry::make('phone')
                    ->label('Numéro de téléphone')
                    ->placeholder('-'),
                TextEntry::make('address')
                    ->label('Adresse')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('plan.name')
                    ->label('Plan souscrit')
                    ->placeholder('Aucun'),
                TextEntry::make('plan_usage')
                    ->label('Usage du plan')
                    ->state(function ($record): string {
                        $usage = app(SubscriptionService::class)->usage($record);

                        return sprintf('%d ferme(s) sur %s · %d membre(s) actif(s) sur %s',
                            $usage['farms_used'], $usage['farms_max'] ?? 'illimité',
                            $usage['users_used'], $usage['users_max'] ?? 'illimité');
                    })
                    ->color(function ($record): string {
                        $usage = app(SubscriptionService::class)->usage($record);
                        $farmsFull = $usage['farms_max'] !== null && $usage['farms_used'] >= (int) $usage['farms_max'];
                        $usersFull = $usage['users_max'] !== null && $usage['users_used'] >= (int) $usage['users_max'];

                        return $farmsFull || $usersFull ? 'warning' : 'gray';
                    }),
                TextEntry::make('current_subscription')
                    ->label('Abonnement en cours')
                    ->state(function ($record): string {
                        $subscription = $record->currentSubscription();

                        return $subscription
                            ? ucfirst($subscription->summaryLabel()).' · '.$subscription->status->getLabel().' jusqu\'au '.$subscription->ends_at->format('d/m/Y').' ('.$subscription->daysRemaining().' j)'
                            : 'Aucun';
                    }),
                TextEntry::make('country.name')
                    ->label('Pays')
                    ->placeholder('-'),
                TextEntry::make('city.name')
                    ->label('Ville')
                    ->placeholder('-'),
                TextEntry::make('logo_url')
                    ->label('URL du logo')
                    ->placeholder('-'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->label('Statut')
                    ->badge(),
                TextEntry::make('status_changed_at')
                    ->label('Statut depuis le')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextEntry::make('status_reason')
                    ->label('Motif du statut')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->label('Date de création')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Date de modification')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
