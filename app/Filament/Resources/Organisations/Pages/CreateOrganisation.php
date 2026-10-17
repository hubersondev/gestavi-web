<?php

namespace App\Filament\Resources\Organisations\Pages;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\UserType;
use App\Exceptions\SubscriptionException;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Organisations\Schemas\OrganisationForm;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use App\Services\SubscriptionService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Assistant de création d'une organisation en une passe : la structure, son propriétaire
 * (compte créé et rattaché avec le rôle propriétaire, mot de passe saisi ou lien envoyé) et
 * son abonnement (essai ou payé, via SubscriptionService). Plus d'organisation sans propriétaire.
 */
class CreateOrganisation extends CreateRecord
{
    use HasWizard;

    protected static string $resource = OrganisationResource::class;

    /**
     * @return array<int, Step>
     */
    public function getSteps(): array
    {
        return [
            Step::make('Structure')
                ->description('Identité et localisation')
                ->columns(2)
                ->schema(OrganisationForm::structureFields()),

            Step::make('Propriétaire')
                ->description('Premier compte, rôle propriétaire')
                ->columns(2)
                ->schema([
                    TextInput::make('owner_name')->label('Nom complet')->required()->maxLength(255),
                    TextInput::make('owner_email')->label('Adresse e-mail')->email()->required()->maxLength(255)->unique(table: 'users', column: 'email'),
                    TextInput::make('owner_phone')->label('Téléphone')->tel()->maxLength(20),
                    Toggle::make('owner_send_link')
                        ->label('Envoyer un lien pour choisir le mot de passe')
                        ->default(true)
                        ->live()
                        ->helperText('Recommandé : le propriétaire choisit lui-même son mot de passe depuis le courriel reçu.'),
                    TextInput::make('owner_password')
                        ->label('Mot de passe provisoire')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->maxLength(255)
                        ->required(fn (Get $get): bool => ! $get('owner_send_link'))
                        ->visible(fn (Get $get): bool => ! $get('owner_send_link')),
                ]),

            Step::make('Abonnement')
                ->description('Plan, essai ou paiement')
                ->columns(2)
                ->schema([
                    Select::make('plan_id')
                        ->label('Plan')
                        ->options(fn (): array => Plan::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                            $plan = $state ? Plan::query()->find($state) : null;

                            if ($plan) {
                                $set('trial_days', (int) $plan->trial_days);
                                $set('price', (int) $plan->trial_days > 0 ? 0 : BillingCycle::coerce($get('billing_cycle'))->priceFor($plan));
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

                            if ($plan && (int) $get('trial_days') === 0) {
                                $set('price', BillingCycle::coerce($state)->priceFor($plan));
                            }
                        })
                        ->native(false),
                    TextInput::make('trial_days')
                        ->label('Jours d\'essai gratuit')
                        ->numeric()->minValue(0)->maxValue(365)->default(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                            $plan = $get('plan_id') ? Plan::query()->find($get('plan_id')) : null;

                            if ($plan) {
                                $set('price', (int) $state > 0 ? 0 : BillingCycle::coerce($get('billing_cycle'))->priceFor($plan));
                            }
                        })
                        ->helperText('0 = abonnement payé dès aujourd\'hui.'),
                    TextInput::make('price')->label('Montant encaissé (XOF)')->numeric()->minValue(0)->default(0)
                        ->visible(fn (Get $get): bool => (int) $get('trial_days') === 0),
                    Select::make('payment_method')->label('Mode de paiement')->options(PaymentMethod::class)->native(false)
                        ->visible(fn (Get $get): bool => (int) $get('trial_days') === 0),
                    TextInput::make('payment_reference')->label('Référence de paiement')->maxLength(255)
                        ->visible(fn (Get $get): bool => (int) $get('trial_days') === 0),
                ]),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $ownerData = [
            'name' => $data['owner_name'],
            'email' => $data['owner_email'],
            'phone' => $data['owner_phone'] ?? null,
            'send_link' => (bool) ($data['owner_send_link'] ?? true),
            'password' => $data['owner_password'] ?? null,
        ];
        $subscriptionData = [
            'plan_id' => $data['plan_id'] ?? null,
            'billing_cycle' => $data['billing_cycle'] ?? null,
            'trial_days' => (int) ($data['trial_days'] ?? 0),
            'price' => $data['price'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'payment_reference' => $data['payment_reference'] ?? null,
        ];

        $organisationData = collect($data)
            ->except(['owner_name', 'owner_email', 'owner_phone', 'owner_send_link', 'owner_password', 'plan_id', 'billing_cycle', 'trial_days', 'price', 'payment_method', 'payment_reference'])
            ->all();

        try {
            return DB::transaction(function () use ($organisationData, $ownerData, $subscriptionData): Organisation {
                /** @var Organisation $organisation */
                $organisation = Organisation::query()->create($organisationData);

                $owner = User::query()->create([
                    'name' => $ownerData['name'],
                    'email' => $ownerData['email'],
                    'phone' => $ownerData['phone'],
                    'password' => $ownerData['password'] ?: Str::password(24),
                    'type' => UserType::Farmer,
                    'is_active' => true,
                ]);
                $organisation->addMember($owner, 'owner');

                if ($subscriptionData['plan_id']) {
                    app(SubscriptionService::class)->start(
                        organisation: $organisation,
                        plan: Plan::query()->findOrFail($subscriptionData['plan_id']),
                        cycle: BillingCycle::coerce($subscriptionData['billing_cycle']),
                        trialDays: $subscriptionData['trial_days'],
                        payment: [
                            'price' => isset($subscriptionData['price']) ? (float) $subscriptionData['price'] : null,
                            'payment_method' => $subscriptionData['payment_method'],
                            'payment_reference' => $subscriptionData['payment_reference'],
                        ],
                        by: Filament::auth()->user(),
                    );
                }

                if ($ownerData['send_link']) {
                    Password::broker()->sendResetLink(['email' => $owner->email]);
                }

                return $organisation;
            });
        } catch (SubscriptionException $e) {
            Notification::make()->title('Abonnement refusé')->body($e->getMessage())->danger()->send();

            throw ValidationException::withMessages(['data.plan_id' => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Organisation créée avec son propriétaire';
    }
}
