<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserType;
use App\Models\PlatformRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Adresse e-mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label('Téléphone')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('password')
                            ->label('Mot de passe')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->minLength(8)
                            ->maxLength(255),
                    ]),
                Section::make('Type, rôle et statut')
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options(UserType::class)
                            ->required()
                            ->native(false)
                            ->live()
                            ->disabled(fn (?User $record): bool => $record !== null && Auth::id() === $record->id),
                        Select::make('platform_role')
                            ->label('Rôle plateforme')
                            ->options(fn (): array => self::availableRoleOptions())
                            ->native(false)
                            ->visible(fn (Get $get): bool => $get('type') === UserType::System->value)
                            ->required(fn (Get $get): bool => $get('type') === UserType::System->value),
                        Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true)
                            ->disabled(fn (?User $record): bool => $record !== null && Auth::id() === $record->id),
                    ]),
            ]);
    }

    /**
     * Retourne les options de rôle disponibles selon l'utilisateur connecté.
     *
     * @return array<int, string>
     */
    private static function availableRoleOptions(): array
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();

        $query = PlatformRole::query();

        // Seul un super admin peut assigner le rôle super-admin
        if (! $currentUser?->isSuperAdmin()) {
            $query->where('slug', '!=', 'super-admin');
        }

        return $query->pluck('name', 'id')->toArray();
    }
}
