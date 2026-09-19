<?php

namespace App\Filament\Farm\Resources\Members\Schemas;

use App\Enums\MemberStatus;
use App\Models\Farm;
use App\Models\OrganisationRole;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nouvel utilisateur')
                    ->columns(2)
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        TextInput::make('user_name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('user_email')
                            ->label('Adresse e-mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique('users', 'email'),
                        TextInput::make('user_phone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('user_password')
                            ->label('Mot de passe')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->maxLength(255)
                            ->rules([fn () => Password::defaults() ?? 'min:8']),
                    ]),

                Section::make('Profil utilisateur')
                    ->columns(2)
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->schema([
                        TextInput::make('user_name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('user_phone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('user_password')
                            ->label('Nouveau mot de passe')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->maxLength(255)
                            ->rules([fn () => Password::defaults() ?? 'min:8'])
                            ->helperText('Laisser vide pour conserver le mot de passe actuel.')
                            ->visible(function ($record): bool {
                                $currentUser = Filament::auth()->user();

                                return $record && $record->user_id === $currentUser?->id;
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Affectation')
                    ->columns(2)
                    ->schema([
                        Select::make('organisation_role_id')
                            ->label('Rôle')
                            ->options(function () {
                                $tenant = Filament::getTenant();
                                $actor = Filament::auth()->user()?->membershipIn($tenant);

                                // Un membre ne peut attribuer qu'un rôle strictement inférieur au sien ;
                                // seul un propriétaire peut nommer un autre propriétaire.
                                return OrganisationRole::query()
                                    ->where('organisation_id', $tenant->id)
                                    ->when(
                                        $actor && ! $actor->isOwner(),
                                        fn ($query) => $query->where('weight', '<', $actor->organisationRole?->weight ?? 0),
                                    )
                                    ->orderByDesc('weight')
                                    ->pluck('name', 'id');
                            })
                            ->helperText('Seuls les rôles de niveau inférieur au vôtre sont proposés.')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('farm_ids', [])),
                        Select::make('status')
                            ->label('Statut')
                            ->options(MemberStatus::class)
                            ->default(MemberStatus::Active->value)
                            ->required()
                            ->native(false),
                        Select::make('farm_ids')
                            ->label('Fermes assignées')
                            ->multiple()
                            ->options(function () {
                                $tenant = Filament::getTenant();

                                return Farm::query()
                                    ->where('organisation_id', $tenant->id)
                                    ->where('is_active', true)
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->visible(function (Get $get): bool {
                                $roleId = $get('organisation_role_id');

                                if (! $roleId) {
                                    return false;
                                }

                                return OrganisationRole::where('id', $roleId)
                                    ->where('weight', '<', OrganisationRole::FULL_ACCESS_WEIGHT)
                                    ->exists();
                            })
                            ->helperText('Sélectionnez les fermes auxquelles ce membre aura accès.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
