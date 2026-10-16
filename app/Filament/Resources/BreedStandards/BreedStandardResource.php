<?php

namespace App\Filament\Resources\BreedStandards;

use App\Filament\Resources\BreedStandards\Pages\CreateBreedStandard;
use App\Filament\Resources\BreedStandards\Pages\EditBreedStandard;
use App\Filament\Resources\BreedStandards\Pages\ListBreedStandards;
use App\Filament\Resources\BreedStandards\Schemas\BreedStandardForm;
use App\Filament\Resources\BreedStandards\Tables\BreedStandardsTable;
use App\Models\BreedStandard;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Courbes de référence des souches (poids, ponte, aliment, mortalité par âge), partagées par
 * toutes les organisations et utilisées par les prévisions.
 */
class BreedStandardResource extends Resource
{
    protected static ?string $model = BreedStandard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?string $modelLabel = 'Standard de souche';

    protected static ?string $pluralModelLabel = 'Standards de souche';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return BreedStandardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BreedStandardsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBreedStandards::route('/'),
            'create' => CreateBreedStandard::route('/create'),
            'edit' => EditBreedStandard::route('/{record}/edit'),
        ];
    }
}
