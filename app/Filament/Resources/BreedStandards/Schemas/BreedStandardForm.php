<?php

namespace App\Filament\Resources\BreedStandards\Schemas;

use App\Models\BreedStandard;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BreedStandardForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Souche et âge')
                ->columns(3)
                ->components([
                    TextInput::make('breed')
                        ->label('Souche')
                        ->required()
                        ->maxLength(100)
                        ->datalist(fn (): array => BreedStandard::breeds())
                        ->placeholder('Cobb 500, ISA Brown…'),
                    Select::make('bird_type')
                        ->label('Type de production')
                        ->options([
                            BreedStandard::TYPE_BROILER => 'Chair',
                            BreedStandard::TYPE_LAYER => 'Pondeuse',
                        ])
                        ->required()
                        ->native(false),
                    TextInput::make('age_days')
                        ->label('Âge (jours)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->suffix('j'),
                ]),
            Section::make('Valeurs attendues à cet âge')
                ->description('Laissez vide ce qui ne s\'applique pas à la souche (ponte pour la chair, par exemple).')
                ->columns(4)
                ->components([
                    TextInput::make('expected_weight_g')->label('Poids moyen')->numeric()->minValue(0)->suffix('g'),
                    TextInput::make('expected_daily_gain_g')->label('Gain quotidien')->numeric()->minValue(0)->suffix('g/j'),
                    TextInput::make('expected_feed_intake_g')->label('Aliment cumulé par sujet')->numeric()->minValue(0)->suffix('g'),
                    TextInput::make('expected_feed_per_day_g')->label('Aliment par sujet et par jour')->numeric()->minValue(0)->suffix('g/j'),
                    TextInput::make('expected_fcr')->label('Indice de consommation')->numeric()->minValue(0),
                    TextInput::make('expected_laying_rate')->label('Taux de ponte')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                    TextInput::make('expected_egg_weight_g')->label('Poids de l\'œuf')->numeric()->minValue(0)->suffix('g'),
                    TextInput::make('expected_mortality_cumul')->label('Mortalité cumulée')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                ]),
            Section::make()
                ->components([
                    Textarea::make('notes')->label('Notes')->rows(2)->columnSpanFull(),
                    Toggle::make('is_active')->label('Actif')->default(true),
                ]),
        ]);
    }
}
