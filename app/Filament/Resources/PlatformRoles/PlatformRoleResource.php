<?php

namespace App\Filament\Resources\PlatformRoles;

use App\Filament\Resources\PlatformRoles\Pages\CreatePlatformRole;
use App\Filament\Resources\PlatformRoles\Pages\EditPlatformRole;
use App\Filament\Resources\PlatformRoles\Pages\ListPlatformRoles;
use App\Filament\Resources\PlatformRoles\Pages\ViewPlatformRole;
use App\Filament\Resources\PlatformRoles\Schemas\PlatformRoleForm;
use App\Filament\Resources\PlatformRoles\Schemas\PlatformRoleInfolist;
use App\Filament\Resources\PlatformRoles\Tables\PlatformRolesTable;
use App\Models\PlatformRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PlatformRoleResource extends Resource
{
    protected static ?string $model = PlatformRole::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Paramètres';

    protected static ?string $modelLabel = 'Rôle';

    protected static ?string $pluralModelLabel = 'Rôles';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return PlatformRoleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlatformRoleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformRolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformRoles::route('/'),
            'create' => CreatePlatformRole::route('/create'),
            'view' => ViewPlatformRole::route('/{record}'),
            'edit' => EditPlatformRole::route('/{record}/edit'),
        ];
    }
}
