<?php

namespace App\Filament\Farm\Pages;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Sms\PhoneNumber;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Préférences de notification personnelles : ce qui arrive par e-mail, dans la cloche du panel
 * et par SMS. Dans le groupe Paramètres de la barre latérale et dans le menu utilisateur.
 */
class NotificationPreferences extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static ?string $title = 'Préférences de notification';

    protected static ?string $slug = 'notifications';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $navigationLabel = 'Préférences de notification';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.farm.pages.notification-preferences';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $preference = $user->notificationPreferencesOrDefault();

        $this->form->fill(array_merge(
            $preference->only(NotificationPreference::TOGGLES),
            ['sms_enabled' => (bool) $user->sms_alerts_enabled],
        ));
    }

    public function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $phone = PhoneNumber::normalize($user->phone);

        return $schema
            ->components([
                Section::make('Notifications e-mail')
                    ->description('Configurez les e-mails que vous souhaitez recevoir.')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->components([
                        Toggle::make('email_daily_report')
                            ->label('Rapport quotidien')
                            ->helperText('Résumé quotidien de l\'activité de vos fermes (envoyé à 07h00).'),
                        Toggle::make('email_alerts')
                            ->label('Alertes générales')
                            ->helperText('Relevés manquants, mortalité, maintenance, exports…'),
                        Toggle::make('email_stock')
                            ->label('Alertes stock')
                            ->helperText('Stock bas et lots bientôt périmés.'),
                        Toggle::make('email_vaccinations')
                            ->label('Rappels vaccination')
                            ->helperText('Vaccinations en retard ou à administrer.'),
                        Toggle::make('email_payments')
                            ->label('Paiements reçus')
                            ->helperText('Confirmation des encaissements clients et factures échues.'),
                    ]),

                Section::make('Notifications dans l\'application')
                    ->description('Cloche du panel et onglet Alertes de l\'application mobile.')
                    ->icon(Heroicon::OutlinedBell)
                    ->components([
                        Toggle::make('app_enabled')
                            ->label('Activer les notifications')
                            ->helperText('Désactiver pour ne plus recevoir de notifications dans l\'application.')
                            ->live(),
                        Toggle::make('app_alerts')
                            ->label('Alertes générales')
                            ->helperText('Relevés manquants, mortalité, vaccinations, maintenance…')
                            ->visible(fn (Get $get): bool => (bool) $get('app_enabled')),
                        Toggle::make('app_stock')
                            ->label('Alertes stock')
                            ->helperText('Stock bas et lots bientôt périmés.')
                            ->visible(fn (Get $get): bool => (bool) $get('app_enabled')),
                        Toggle::make('app_payments')
                            ->label('Paiements reçus')
                            ->visible(fn (Get $get): bool => (bool) $get('app_enabled')),
                    ]),

                Section::make('Notifications SMS')
                    ->description('Alertes critiques envoyées par SMS sur votre téléphone.')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->components([
                        Toggle::make('sms_enabled')
                            ->label('Activer les SMS')
                            ->helperText($phone
                                ? "Envoyés au {$phone}. Des frais SMS peuvent s'appliquer selon votre forfait."
                                : 'Renseignez d\'abord un numéro de téléphone valide sur votre profil.')
                            ->disabled($phone === null)
                            ->live(),
                        Toggle::make('sms_critical_alerts')
                            ->label('Alertes critiques')
                            ->helperText('Uniquement les alertes de gravité critique (mortalité élevée, factures échues…).')
                            ->visible(fn (Get $get): bool => (bool) $get('sms_enabled')),
                        Toggle::make('sms_payments')
                            ->label('Paiements reçus')
                            ->helperText('Confirmation SMS des encaissements.')
                            ->visible(fn (Get $get): bool => (bool) $get('sms_enabled')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        /** @var User $user */
        $user = Filament::auth()->user();

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            collect($state)->only(NotificationPreference::TOGGLES)->map(fn ($value): bool => (bool) $value)->all(),
        );

        $user->forceFill(['sms_alerts_enabled' => (bool) ($state['sms_enabled'] ?? false)])->save();

        Notification::make()
            ->title('Préférences enregistrées')
            ->body('Vos préférences de notification ont été mises à jour.')
            ->success()
            ->send();
    }
}
