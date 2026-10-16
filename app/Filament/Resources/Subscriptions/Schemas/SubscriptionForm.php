<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Organisation;
use App\Models\Plan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Création : organisation, plan, cycle, essai éventuel, paiement ; les dates et le prix sont
 * calculés par SubscriptionService. Modification : ajustement des dates, du statut et du paiement.
 */
class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Souscription')
                ->columns(2)
                ->schema([
                    Select::make('organisation_id')
                        ->label('Structure')
                        ->options(fn (): array => Organisation::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->dehydrated(),
                    Select::make('plan_id')
                        ->label('Plan')
                        ->options(fn (): array => Plan::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                            $plan = $state ? Plan::query()->find($state) : null;

                            if ($plan) {
                                $set('trial_days', (int) $plan->trial_days);
                                $set('price', self::priceFor($plan, $get('billing_cycle'), (int) $plan->trial_days));
                            }
                        })
                        ->native(false),
                    Select::make('billing_cycle')
                        ->label('Cycle de facturation')
                        ->options(BillingCycle::class)
                        ->default(BillingCycle::Monthly->value)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                            $plan = $get('plan_id') ? Plan::query()->find($get('plan_id')) : null;

                            if ($plan) {
                                $set('price', self::priceFor($plan, $state, (int) $get('trial_days')));
                            }
                        })
                        ->native(false),
                    TextInput::make('trial_days')
                        ->label('Jours d\'essai gratuit')
                        ->helperText('0 = abonnement payé dès aujourd\'hui. Prérempli avec l\'essai prévu par le plan.')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(365)
                        ->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                            $plan = $get('plan_id') ? Plan::query()->find($get('plan_id')) : null;

                            if ($plan) {
                                $set('price', self::priceFor($plan, $get('billing_cycle'), (int) $state));
                            }
                        })
                        ->visible(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(false),
                    DatePicker::make('starts_at')
                        ->label('Début')
                        ->default(today())
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y'),
                    DatePicker::make('ends_at')
                        ->label('Échéance')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->afterOrEqual('starts_at')
                        ->visible(fn (string $operation): bool => $operation === 'edit'),
                    DatePicker::make('trial_ends_at')
                        ->label('Fin d\'essai')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->visible(fn (string $operation): bool => $operation === 'edit'),
                    Select::make('status')
                        ->label('Statut')
                        ->options(SubscriptionStatus::class)
                        ->required()
                        ->native(false)
                        ->visible(fn (string $operation): bool => $operation === 'edit'),
                ]),
            Section::make('Paiement')
                ->columns(3)
                ->schema([
                    TextInput::make('price')
                        ->label('Prix de la période (XOF)')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),
                    Select::make('payment_method')
                        ->label('Mode de paiement')
                        ->options(PaymentMethod::class)
                        ->native(false),
                    TextInput::make('payment_reference')
                        ->label('Référence de paiement')
                        ->maxLength(255),
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function priceFor(Plan $plan, mixed $cycle, int $trialDays): float
    {
        if ($trialDays > 0) {
            return 0.0;
        }

        return BillingCycle::coerce($cycle)->priceFor($plan);
    }
}
