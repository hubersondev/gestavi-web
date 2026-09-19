<?php

namespace App\Filament\Resources\FarmUsers;

use App\Filament\Resources\FarmUsers\Pages\CreateFarmUser;
use App\Filament\Resources\FarmUsers\Pages\EditFarmUser;
use App\Filament\Resources\FarmUsers\Pages\ListFarmUsers;
use App\Filament\Resources\FarmUsers\Pages\ViewFarmUser;
use App\Filament\Resources\FarmUsers\Schemas\FarmUserForm;
use App\Filament\Resources\FarmUsers\Schemas\FarmUserInfolist;
use App\Filament\Resources\FarmUsers\Tables\FarmUsersTable;
use App\Models\User;
use App\Policies\FarmUserPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class FarmUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static UnitEnum|string|null $navigationGroup = 'Clients';

    protected static ?string $modelLabel = 'Utilisateur ferme';

    protected static ?string $pluralModelLabel = 'Utilisateurs ferme';

    protected static ?int $navigationSort = 2;

    protected static ?string $policy = FarmUserPolicy::class;

    public static function form(Schema $schema): Schema
    {
        return FarmUserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FarmUserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FarmUsersTable::configure($table);
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
            'index' => ListFarmUsers::route('/'),
            'create' => CreateFarmUser::route('/create'),
            'view' => ViewFarmUser::route('/{record}'),
            'edit' => EditFarmUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->farmUsers()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
