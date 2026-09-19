<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Enums\PlanFeature;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanForm
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
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit'),
                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                        TextInput::make('monthly_price')
                            ->label('Prix mensuel')
                            ->minValue(0)
                            ->numeric()
                            ->prefix('FCFA')
                            ->required()
                            ->default(0),
                        TextInput::make('yearly_price')
                            ->label('Prix annuel')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('FCFA'),
                        TextInput::make('duration_months')
                            ->label('Durée')
                            ->numeric()
                            ->suffix('mois')
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        TextInput::make('trial_days')
                            ->label("Période d'essai")
                            ->numeric()
                            ->minValue(0)
                            ->suffix('jours')
                            ->default(0),
                        TextInput::make('max_farms')
                            ->label('Nombre max de fermes')
                            ->minValue(1)
                            ->numeric()
                            ->placeholder('Illimité'),
                        TextInput::make('max_users')
                            ->label("Nombre max d'utilisateurs")
                            ->minValue(1)
                            ->numeric()
                            ->placeholder('Illimité'),
                        TextInput::make('sort_order')
                            ->label("Ordre d'affichage")
                            ->minValue(0)
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Actif')
                            ->default(true),
                    ]),
                ...self::featureSections(),
            ]);
    }

    /**
     * Génère une section par groupe de fonctionnalités.
     *
     * @return array<Section>
     */
    private static function featureSections(): array
    {
        $allFeatureKeys = array_keys(PlanFeature::options());

        return collect(PlanFeature::groupedOptions())
            ->map(fn (array $options, string $group) => Section::make($group)
                ->schema([
                    CheckboxList::make('features')
                        ->label('Features')
                        ->options($options)
                        ->in($allFeatureKeys)
                        ->columns(3),
                ])
                ->collapsible())
            ->values()
            ->all();
    }
}
