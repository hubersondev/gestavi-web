<?php

namespace App\Filament\Resources\Organisations\Schemas;

use App\Enums\OrganisationStatus;
use App\Models\City;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrganisationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...self::structureFields(),
                Select::make('plan_id')
                    ->label('Plan souscrit')
                    ->relationship(
                        name: 'plan',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true),
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Aucun plan')
                    ->helperText('Suit l\'abonnement courant ; modifier ici seulement pour corriger.'),
                Select::make('status')
                    ->label('Statut')
                    ->options(OrganisationStatus::class)
                    ->default(OrganisationStatus::Active->value)
                    ->required()
                    ->live()
                    ->native(false)
                    ->helperText(fn ($state): ?string => ($state instanceof OrganisationStatus ? $state : OrganisationStatus::tryFrom((string) $state))?->getDescription()),
                Textarea::make('status_reason')
                    ->label('Motif (affiché aux membres)')
                    ->rows(2)
                    ->visible(fn ($get): bool => ($get('status') instanceof OrganisationStatus ? $get('status')->value : $get('status')) !== OrganisationStatus::Active->value)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Identité et localisation de la structure (sans plan ni statut) : formulaire de
     * modification et première étape de l'assistant de création.
     *
     * @return array<int, Component>
     */
    public static function structureFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Nom')
                ->required(),
            TextInput::make('slug')
                ->helperText('Vide : généré à partir du nom.')
                ->unique(ignoreRecord: true),
            TextInput::make('email')
                ->label('Adresse e-mail')
                ->email(),
            TextInput::make('phone')
                ->label('Numéro de téléphone')
                ->tel(),
            TextInput::make('cnps_employer_number')
                ->label('N° CNPS employeur')
                ->maxLength(30),
            Textarea::make('address')
                ->label('Adresse')
                ->columnSpanFull(),
            Select::make('country_id')
                ->label('Pays')
                ->relationship(
                    name: 'country',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true),
                )
                ->searchable()
                ->preload()
                ->required()
                ->live(),
            Select::make('city_id')
                ->label('Ville')
                ->options(fn (Get $get): Collection => City::query()
                    ->where('country_id', $get('country_id'))
                    ->where('is_active', true)
                    ->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required(),
            FileUpload::make('logo_url')
                ->label('Logo')
                ->image()
                ->disk('public')
                ->directory('logos')
                ->imageEditor()
                ->maxSize(2048),
            Textarea::make('description')
                ->label('Description')
                ->columnSpanFull(),
        ];
    }
}
