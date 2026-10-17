<?php

namespace App\Filament\Pages;

use App\Enums\MobileMoneyOperator;
use App\Jobs\GenerateReportExport;
use App\Services\PlatformSettings;
use App\Services\Weather\WeatherService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Réglages de la plateforme modifiables sans redéploiement (identité de l'éditeur, contact
 * support, délai de grâce) et état des intégrations configurées dans l'environnement.
 */
class PlatformSettingsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $navigationLabel = 'Plateforme';

    protected static ?string $title = 'Réglages de la plateforme';

    protected static ?string $slug = 'plateforme';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.platform-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasPermission('settings.update');
    }

    public function mount(): void
    {
        $this->form->fill(PlatformSettings::all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Éditeur de la plateforme')
                    ->description('Apparaît en en-tête des factures d\'abonnement remises aux organisations.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')->label('Raison sociale')->required()->maxLength(120),
                        TextInput::make('company_tax_id')->label('N° contribuable / RCCM')->maxLength(60),
                        TextInput::make('company_email')->label('Courriel de facturation')->email()->maxLength(120),
                        TextInput::make('company_phone')->label('Téléphone')->tel()->maxLength(40),
                        Textarea::make('company_address')->label('Adresse')->rows(2)->columnSpanFull(),
                    ]),
                Section::make('Support affiché aux organisations')
                    ->description('Utilisé dans les bandeaux de lecture seule, de suspension et sur la page des plans.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('support_email')->label('Courriel de support')->email()->required()->maxLength(120),
                        TextInput::make('support_phone')->label('Téléphone de support')->tel()->maxLength(40),
                    ]),
                Section::make('Abonnements')
                    ->columns(2)
                    ->schema([
                        TextInput::make('subscription_grace_days')
                            ->label('Délai de grâce après expiration (jours)')
                            ->numeric()->minValue(0)->maxValue(90)
                            ->placeholder((string) config('subscriptions.grace_days', 7))
                            ->helperText('Vide : la valeur de l\'environnement s\'applique ('.config('subscriptions.grace_days', 7).' jours). Au-delà, l\'organisation passe en lecture seule.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $data['subscription_grace_days'] = filled($data['subscription_grace_days'] ?? null) ? (int) $data['subscription_grace_days'] : null;

        PlatformSettings::setMany($data, Filament::auth()->id());

        Notification::make()->title('Réglages enregistrés')->success()->send();
    }

    /**
     * État des intégrations réglées dans l'environnement (lecture seule).
     *
     * @return list<array{label: string, configured: bool, detail: string}>
     */
    public function getIntegrations(): array
    {
        $operators = collect(MobileMoneyOperator::cases())
            ->mapWithKeys(fn (MobileMoneyOperator $o): array => [$o->value => filled(config("mobilemoney.operators.{$o->value}.webhook_secret"))]);

        return [
            ['label' => 'Courriel', 'configured' => config('mail.default') !== 'log', 'detail' => (string) config('mail.default')],
            ['label' => 'SMS', 'configured' => config('sms.default') !== 'log', 'detail' => (string) config('sms.default')],
            ['label' => 'Supervision des erreurs (Sentry)', 'configured' => filled(config('sentry.dsn')), 'detail' => filled(config('sentry.dsn')) ? 'DSN renseigné' : 'DSN absent'],
            ['label' => 'Météo (OpenWeatherMap)', 'configured' => WeatherService::enabled(), 'detail' => WeatherService::enabled() ? 'clé renseignée' : 'clé absente'],
            ['label' => 'Exports de rapports', 'configured' => GenerateReportExport::disk() !== 'local', 'detail' => GenerateReportExport::disk() === 'local' ? 'disque local (liens signés)' : GenerateReportExport::disk().' · '.(string) config('filesystems.disks.'.GenerateReportExport::disk().'.endpoint', 'S3')],
            ['label' => 'Mobile money', 'configured' => $operators->every(fn (bool $ok): bool => $ok), 'detail' => $operators->filter()->keys()->implode(', ') ?: 'aucun opérateur'],
            ['label' => 'Sauvegardes', 'configured' => ! in_array('local', (array) config('backup.backup.destination.disks', ['local']), true) || count((array) config('backup.backup.destination.disks', [])) > 1, 'detail' => implode(', ', (array) config('backup.backup.destination.disks', []))],
        ];
    }
}
