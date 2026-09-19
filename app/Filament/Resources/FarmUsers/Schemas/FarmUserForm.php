<?php

namespace App\Filament\Resources\FarmUsers\Schemas;

use App\Models\Organisation;
use App\Models\OrganisationRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FarmUserForm
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
                Section::make('Affectation')
                    ->columns(2)
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->schema([
                        Select::make('organisation_id')
                            ->label('Structure')
                            ->options(fn () => Organisation::query()
                                ->where('is_active', true)
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Select::make('organisation_role_id')
                            ->label('Rôle')
                            ->options(function (Get $get) {
                                $organisationId = $get('organisation_id');

                                if (! $organisationId) {
                                    return [];
                                }

                                return OrganisationRole::query()
                                    ->where('organisation_id', $organisationId)
                                    ->orderByDesc('weight')
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->native(false),
                    ]),
                Section::make('Statut')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true),
                    ]),
            ]);
    }
}
