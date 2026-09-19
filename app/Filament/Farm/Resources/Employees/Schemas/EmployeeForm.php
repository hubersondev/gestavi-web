<?php

namespace App\Filament\Farm\Resources\Employees\Schemas;

use App\Enums\EmployeeStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\Farm;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Employé')
                    ->tabs([

                        // ─── Onglet Identité ──────────────────────────────────────
                        Tabs\Tab::make('Identité')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Informations personnelles')
                                    ->schema([
                                        TextInput::make('last_name')
                                            ->label('Nom')
                                            ->required()
                                            ->maxLength(100),

                                        TextInput::make('first_name')
                                            ->label('Prénom')
                                            ->required()
                                            ->maxLength(100),

                                        Select::make('gender')
                                            ->label('Genre')
                                            ->options(Gender::class)
                                            ->native(false),

                                        Select::make('marital_status')
                                            ->label('Situation matrimoniale')
                                            ->options(MaritalStatus::class)
                                            ->native(false),

                                        DatePicker::make('birth_date')
                                            ->label('Date de naissance')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->maxDate(now()->subYears(16)),

                                        TextInput::make('birth_place')
                                            ->label('Lieu de naissance')
                                            ->maxLength(100),

                                        TextInput::make('nationality')
                                            ->label('Nationalité')
                                            ->default('Ivoirienne')
                                            ->maxLength(100),
                                    ])
                                    ->columns(2),

                                Section::make("Pièce d'identité")
                                    ->schema([
                                        Select::make('id_type')
                                            ->label('Type de pièce')
                                            ->options([
                                                'cni' => 'CNI',
                                                'passport' => 'Passeport',
                                                'attestation' => "Attestation d'identité",
                                                'carte_consulaire' => 'Carte consulaire',
                                            ])
                                            ->native(false),

                                        TextInput::make('id_number')
                                            ->label('Numéro de pièce')
                                            ->maxLength(100),

                                        DatePicker::make('id_expiry_date')
                                            ->label("Date d'expiration")
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),

                                        FileUpload::make('id_document')
                                            ->label('Scan / Photo de la pièce')
                                            ->disk('local')
                                            ->visibility('private')
                                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                                            ->directory('employees/documents')
                                            ->maxSize(5120)
                                            ->downloadable()
                                            ->openable()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(3),

                                Section::make('Photo')
                                    ->schema([
                                        FileUpload::make('photo')
                                            ->label('Photo de profil')
                                            ->disk('local')
                                            ->visibility('private')
                                            ->image()
                                            ->imageEditor()
                                            ->directory('employees/photos')
                                            ->maxSize(2048)
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsible()
                                    ->collapsed(),
                            ]),

                        // ─── Onglet Contact ───────────────────────────────────────
                        Tabs\Tab::make('Contact')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Coordonnées')
                                    ->schema([
                                        TextInput::make('phone')
                                            ->label('Téléphone principal')
                                            ->tel()
                                            ->required()
                                            ->maxLength(30),

                                        TextInput::make('phone_secondary')
                                            ->label('Téléphone secondaire')
                                            ->tel()
                                            ->maxLength(30),

                                        TextInput::make('email')
                                            ->label('Email')
                                            ->email()
                                            ->maxLength(255),
                                    ])
                                    ->columns(3),

                                Section::make('Adresse')
                                    ->schema([
                                        Textarea::make('address')
                                            ->label('Adresse')
                                            ->rows(2)
                                            ->maxLength(500),

                                        TextInput::make('city')
                                            ->label('Ville')
                                            ->maxLength(100),
                                    ])
                                    ->columns(2),

                                Section::make("Contact d'urgence")
                                    ->schema([
                                        TextInput::make('emergency_contact_name')
                                            ->label('Nom du contact')
                                            ->maxLength(150),

                                        TextInput::make('emergency_contact_phone')
                                            ->label('Téléphone')
                                            ->tel()
                                            ->maxLength(30),

                                        TextInput::make('emergency_contact_relationship')
                                            ->label('Lien de parenté')
                                            ->maxLength(100)
                                            ->placeholder('Ex : Épouse, Père, Frère…'),
                                    ])
                                    ->columns(3),
                            ]),

                        // ─── Onglet Emploi ────────────────────────────────────────
                        Tabs\Tab::make('Emploi')
                            ->icon('heroicon-o-briefcase')
                            ->schema([
                                Section::make('Poste')
                                    ->schema([
                                        TextInput::make('employee_number')
                                            ->label('Matricule')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->placeholder('Généré automatiquement'),

                                        Placeholder::make('position_display')
                                            ->label('Poste actuel')
                                            ->content(fn ($record) => $record?->activeContract?->position ?? '—')
                                            ->helperText('Défini dans le contrat actif'),

                                        Select::make('department')
                                            ->label('Département')
                                            ->options([
                                                'production' => 'Production',
                                                'maintenance' => 'Maintenance',
                                                'administration' => 'Administration',
                                                'securite' => 'Sécurité',
                                                'logistique' => 'Logistique',
                                                'vente' => 'Vente',
                                            ])
                                            ->native(false),

                                        Select::make('status')
                                            ->label('Statut')
                                            ->options(EmployeeStatus::class)
                                            ->default(EmployeeStatus::Active)
                                            ->required()
                                            ->native(false),

                                        Select::make('farm_id')
                                            ->label('Ferme')
                                            ->options(function () {
                                                $tenant = Filament::getTenant();
                                                $user = Filament::auth()->user();
                                                $membership = $user?->membershipIn($tenant);

                                                return Farm::query()
                                                    ->where('organisation_id', $tenant?->getKey())
                                                    ->when($membership, fn ($q) => $q->accessibleBy($membership))
                                                    ->where('is_active', true)
                                                    ->orderBy('name')
                                                    ->pluck('name', 'id');
                                            })
                                            ->default(function () {
                                                $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                                                return $membership
                                                    ? app(CurrentFarm::class)->get($membership)?->id
                                                    : null;
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),
                                    ])
                                    ->columns(2),

                                Section::make('Dates')
                                    ->schema([
                                        DatePicker::make('hire_date')
                                            ->label("Date d'embauche")
                                            ->required()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->default(now()),

                                        DatePicker::make('termination_date')
                                            ->label('Date de fin')
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->visible(fn ($get) => $get('status') === EmployeeStatus::Terminated->value),
                                    ])
                                    ->columns(2),

                                Section::make('Compte utilisateur')
                                    ->schema([
                                        Select::make('user_id')
                                            ->label('Compte utilisateur associé')
                                            ->relationship(
                                                'user',
                                                'name',
                                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                                    ->inOrganisation((int) Filament::getTenant()?->getKey()),
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Aucun compte')
                                            ->helperText("Associer un compte pour l'accès à l'application"),
                                    ])
                                    ->collapsible()
                                    ->collapsed(),
                            ]),

                        // ─── Onglet Paiement ──────────────────────────────────────
                        Tabs\Tab::make('Paiement')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Sécurité sociale')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('cnps_number')
                                            ->label('N° CNPS')
                                            ->maxLength(50)
                                            ->placeholder('Numéro de sécurité sociale'),

                                        Select::make('fiscal_parts')
                                            ->label('Parts fiscales (ITS)')
                                            ->options([
                                                '1.0' => '1 part', '1.5' => '1,5 part', '2.0' => '2 parts', '2.5' => '2,5 parts',
                                                '3.0' => '3 parts', '3.5' => '3,5 parts', '4.0' => '4 parts', '4.5' => '4,5 parts', '5.0' => '5 parts',
                                            ])
                                            ->default('1.0')
                                            ->required()
                                            ->native(false)
                                            ->helperText('Situation de famille déclarée : détermine la réduction d\'ITS pour charges de famille.'),
                                    ]),

                                Section::make('Mode de paiement du salaire')
                                    ->schema([
                                        Select::make('payment_method')
                                            ->label('Mode de paiement préféré')
                                            ->options([
                                                'cash' => 'Espèces',
                                                'bank_transfer' => 'Virement bancaire',
                                                'mobile_money' => 'Mobile Money',
                                            ])
                                            ->native(false)
                                            ->live()
                                            ->placeholder('Non défini'),
                                    ]),

                                Section::make('Coordonnées bancaires')
                                    ->schema([
                                        TextInput::make('bank_name')
                                            ->label('Banque')
                                            ->maxLength(100),

                                        TextInput::make('bank_account')
                                            ->label('N° de compte')
                                            ->maxLength(100),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($get) => $get('payment_method') === 'bank_transfer'),

                                Section::make('Mobile Money')
                                    ->schema([
                                        Select::make('mobile_money_operator')
                                            ->label('Opérateur')
                                            ->options([
                                                'orange_money' => 'Orange Money',
                                                'mtn_momo' => 'MTN MoMo',
                                                'moov_money' => 'Moov Money',
                                                'wave' => 'Wave',
                                            ])
                                            ->native(false),

                                        TextInput::make('mobile_money_number')
                                            ->label('Numéro')
                                            ->tel()
                                            ->maxLength(30),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($get) => $get('payment_method') === 'mobile_money'),
                            ]),

                        // ─── Onglet Résidence ─────────────────────────────────────
                        Tabs\Tab::make('Résidence')
                            ->icon('heroicon-o-home')
                            ->schema([
                                Section::make('Statut résidentiel')
                                    ->schema([
                                        Toggle::make('is_resident')
                                            ->label('Employé résident sur site')
                                            ->live()
                                            ->helperText("L'employé loge sur le site de la ferme"),

                                        Toggle::make('is_on_site')
                                            ->label('Actuellement sur site')
                                            ->visible(fn ($get) => (bool) $get('is_resident')),
                                    ])
                                    ->columns(2),

                                Section::make('Logement')
                                    ->schema([
                                        TextInput::make('accommodation')
                                            ->label('Logement attribué')
                                            ->maxLength(100)
                                            ->placeholder('Ex : Bâtiment A, Chambre 3'),

                                        DatePicker::make('residence_start_date')
                                            ->label('Début de résidence')
                                            ->native(false)
                                            ->displayFormat('d/m/Y'),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($get) => (bool) $get('is_resident')),

                                Section::make('Surveillance')
                                    ->schema([
                                        Toggle::make('requires_patrol')
                                            ->label('Soumis aux rondes de surveillance')
                                            ->live()
                                            ->helperText('Pour les gardiens et agents de sécurité'),

                                        TextInput::make('patrol_interval_hours')
                                            ->label('Intervalle de ronde (heures)')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(24)
                                            ->default(4)
                                            ->suffix('h')
                                            ->visible(fn ($get) => (bool) $get('requires_patrol')),
                                    ])
                                    ->columns(2)
                                    ->visible(fn ($get) => (bool) $get('is_resident')),
                            ]),

                        // ─── Onglet Notes ─────────────────────────────────────────
                        Tabs\Tab::make('Notes')
                            ->icon('heroicon-o-chat-bubble-left-ellipsis')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Textarea::make('notes')
                                            ->label('Notes')
                                            ->rows(5)
                                            ->placeholder("Informations complémentaires sur l'employé…")
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                    ])
                    ->columnSpanFull(),
            ]);
    }
}
