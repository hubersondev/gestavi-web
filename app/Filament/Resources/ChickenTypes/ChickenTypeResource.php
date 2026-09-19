<?php

namespace App\Filament\Resources\ChickenTypes;

use App\Filament\Resources\ChickenTypes\Pages\CreateChickenType;
use App\Filament\Resources\ChickenTypes\Pages\EditChickenType;
use App\Filament\Resources\ChickenTypes\Pages\ListChickenTypes;
use App\Filament\Resources\ChickenTypes\Pages\ViewChickenType;
use App\Filament\Resources\ChickenTypes\Schemas\ChickenTypeForm;
use App\Filament\Resources\ChickenTypes\Schemas\ChickenTypeInfolist;
use App\Filament\Resources\ChickenTypes\Tables\ChickenTypesTable;
use App\Models\ChickenType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ChickenTypeResource extends Resource
{
    protected static ?string $model = ChickenType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?string $modelLabel = 'Type de poulet';

    protected static ?string $pluralModelLabel = 'Types de poulets';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ChickenTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ChickenTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChickenTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChickenTypes::route('/'),
            'create' => CreateChickenType::route('/create'),
            'view' => ViewChickenType::route('/{record}'),
            'edit' => EditChickenType::route('/{record}/edit'),
        ];
    }
}
