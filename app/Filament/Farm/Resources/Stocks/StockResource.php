<?php

namespace App\Filament\Farm\Resources\Stocks;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Stocks\Pages\CreateStock;
use App\Filament\Farm\Resources\Stocks\Pages\EditStock;
use App\Filament\Farm\Resources\Stocks\Pages\ListStocks;
use App\Filament\Farm\Resources\Stocks\Pages\ViewStock;
use App\Filament\Farm\Resources\Stocks\RelationManagers\MovementsRelationManager;
use App\Filament\Farm\Resources\Stocks\Schemas\StockForm;
use App\Filament\Farm\Resources\Stocks\Schemas\StockInfolist;
use App\Filament\Farm\Resources\Stocks\Tables\StocksTable;
use App\Models\Stock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class StockResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Stock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCubeTransparent;

    protected static string|UnitEnum|null $navigationGroup = 'Stocks';

    protected static ?string $modelLabel = 'Produit';

    protected static ?string $pluralModelLabel = 'Produits en stock';

    protected static ?int $navigationSort = 2;

    protected static string $permissionGroup = 'stocks';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::StockManagement];
    }

    public static function form(Schema $schema): Schema
    {
        return StockForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StocksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStocks::route('/'),
            'create' => CreateStock::route('/create'),
            'view' => ViewStock::route('/{record}'),
            'edit' => EditStock::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return static::applyFarmScope(
            $query->where('organisation_id', $tenant?->getKey()),
        );
    }
}
