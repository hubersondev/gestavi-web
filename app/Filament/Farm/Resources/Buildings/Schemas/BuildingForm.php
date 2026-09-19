<?php

namespace App\Filament\Farm\Resources\Buildings\Schemas;

use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class BuildingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Informations')
                    ->tabs([
                        // Onglet Informations générales
                        Tabs\Tab::make('Informations générales')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Section::make('Identité')
                                    ->schema([
                                        Select::make('farm_id')
                                            ->label('Ferme')
                                            ->relationship(
                                                'farm',
                                                'name',
                                                modifyQueryUsing: function (Builder $query) {
                                                    $tenant = Filament::getTenant();
                                                    $user = Filament::auth()->user();
                                                    $membership = $user?->membershipIn($tenant);

                                                    $query->where('organisation_id', $tenant?->getKey());

                                                    if ($membership) {
                                                        $query->accessibleBy($membership);
                                                    }
                                                }
                                            )
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->default(function () {
                                                $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                                                return $membership
                                                    ? app(CurrentFarm::class)->get($membership)?->id
                                                    : null;
                                            })
                                            ->hidden(function () {
                                                $membership = Filament::auth()->user()?->membershipIn(Filament::getTenant());

                                                return $membership
                                                    && app(CurrentFarm::class)->get($membership) !== null;
                                            })
                                            ->columnSpan(2),
                                        TextInput::make('code')
                                            ->label('Code')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(50)
                                            ->placeholder('BAT-001')
                                            ->helperText('Code unique du bâtiment'),
                                        TextInput::make('name')
                                            ->label('Nom du bâtiment')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Ex: Poulailler A'),

                                        Select::make('type')
                                            ->label('Type de bâtiment')
                                            ->required()
                                            ->options([
                                                'pondeuse' => 'Pondeuse',
                                                'chair' => 'Poulet de chair',
                                                'poussiniere' => 'Poussinière',
                                                'stockage' => 'Stockage',
                                            ])
                                            ->native(false),

                                        Toggle::make('is_active')
                                            ->label('Bâtiment actif')
                                            ->default(true)
                                            ->inline(false),
                                    ])
                                    ->columns(2),
                            ]),

                        // Onglet Capacité
                        Tabs\Tab::make('Capacité & Surface')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Section::make('Caractéristiques')
                                    ->schema([
                                        TextInput::make('capacity')
                                            ->label('Capacité')
                                            ->required()
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->suffix('sujets')
                                            ->helperText('Nombre maximum de volailles'),

                                        TextInput::make('area_sqm')
                                            ->label('Surface')
                                            ->numeric()
                                            ->suffix('m²')
                                            ->step(0.01)
                                            ->helperText('Surface au sol du bâtiment'),

                                        Textarea::make('description')
                                            ->label('Description')
                                            ->rows(4)
                                            ->placeholder('Description du bâtiment, particularités...')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
