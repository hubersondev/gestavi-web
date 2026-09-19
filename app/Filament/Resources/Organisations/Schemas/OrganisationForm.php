<?php

namespace App\Filament\Resources\Organisations\Schemas;

use App\Models\City;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                TextInput::make('name')
                    ->label('Nom')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('email')
                    ->label('Adresse e-mail')
                    ->email(),
                TextInput::make('phone')
                    ->label('Numéro de téléphone')
                    ->tel(),
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
                Select::make('plan_id')
                    ->label('Plan souscrit')
                    ->relationship(
                        name: 'plan',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true),
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Aucun plan'),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->required(),
            ]);
    }
}
