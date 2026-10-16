<?php

namespace App\Filament\Resources\Subscriptions\Actions;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Exceptions\SubscriptionException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

/**
 * Actions métier d'un abonnement (renouveler, changer de plan, annuler), partagées entre la
 * table et la fiche. Les règles sont portées par SubscriptionService.
 */
class SubscriptionActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            Action::make('renew')
                ->label('Renouveler')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn (Subscription $record): bool => $record->plan_id !== null && Filament::auth()->user()?->can('update', $record))
                ->schema([
                    Select::make('billing_cycle')
                        ->label('Cycle')
                        ->options(BillingCycle::class)
                        ->default(fn (Subscription $record): string => $record->billing_cycle?->value ?? BillingCycle::Monthly->value)
                        ->required()
                        ->native(false),
                    TextInput::make('price')
                        ->label('Montant encaissé (XOF)')
                        ->numeric()
                        ->minValue(0)
                        ->default(fn (Subscription $record): float => $record->plan ? ($record->billing_cycle ?? BillingCycle::Monthly)->priceFor($record->plan) : 0)
                        ->required(),
                    Select::make('payment_method')
                        ->label('Mode de paiement')
                        ->options(PaymentMethod::class)
                        ->default(fn (Subscription $record): ?string => $record->payment_method?->value)
                        ->native(false),
                    TextInput::make('payment_reference')
                        ->label('Référence de paiement')
                        ->maxLength(255),
                ])
                ->action(function (Subscription $record, array $data): void {
                    self::run(fn () => app(SubscriptionService::class)->renew(
                        $record,
                        BillingCycle::coerce($data['billing_cycle'] ?? null),
                        ['price' => (float) $data['price'], 'payment_method' => $data['payment_method'] ?? null, 'payment_reference' => $data['payment_reference'] ?? null],
                        Filament::auth()->user(),
                    ), 'Abonnement renouvelé jusqu\'au '.$record->fresh()->ends_at?->format('d/m/Y'));
                }),

            Action::make('changePlan')
                ->label('Changer de plan')
                ->icon('heroicon-o-arrows-right-left')
                ->color('info')
                ->visible(fn (Subscription $record): bool => $record->isCurrent() && Filament::auth()->user()?->can('update', $record))
                ->schema([
                    Select::make('plan_id')
                        ->label('Nouveau plan')
                        ->options(fn (Subscription $record): array => Plan::query()->where('is_active', true)->whereKeyNot($record->plan_id)->orderBy('sort_order')->pluck('name', 'id')->all())
                        ->required()
                        ->native(false),
                ])
                ->action(function (Subscription $record, array $data): void {
                    $plan = Plan::query()->findOrFail($data['plan_id']);

                    self::run(fn () => app(SubscriptionService::class)->changePlan($record, $plan, Filament::auth()->user()), 'Plan changé pour '.$plan->name.'.');
                }),

            Action::make('cancel')
                ->label('Annuler')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Subscription $record): bool => $record->isCurrent() && $record->cancelled_at === null && Filament::auth()->user()?->can('update', $record))
                ->requiresConfirmation()
                ->modalDescription('L\'accès au plan est maintenu jusqu\'à l\'échéance, sauf annulation immédiate.')
                ->schema([
                    Textarea::make('reason')->label('Motif')->rows(2),
                    Toggle::make('immediately')->label('Annulation immédiate (retour au plan gratuit aujourd\'hui)')->default(false),
                ])
                ->action(function (Subscription $record, array $data): void {
                    self::run(fn () => app(SubscriptionService::class)->cancel($record, $data['reason'] ?? null, (bool) ($data['immediately'] ?? false), Filament::auth()->user()), 'Abonnement annulé.');
                }),
        ];
    }

    private static function run(callable $operation, string $successTitle): void
    {
        try {
            $operation();
            Notification::make()->title($successTitle)->success()->send();
        } catch (SubscriptionException $e) {
            Notification::make()->title('Opération refusée')->body($e->getMessage())->danger()->send();
        }
    }
}
