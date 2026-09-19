<?php

namespace App\Filament\Farm\Resources\StockInventories;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Resources\StockInventories\Pages\CreateStockInventory;
use App\Filament\Farm\Resources\StockInventories\Pages\EditStockInventory;
use App\Filament\Farm\Resources\StockInventories\Pages\ListStockInventories;
use App\Filament\Farm\Resources\StockInventories\Pages\ViewStockInventory;
use App\Filament\Farm\Resources\StockInventories\Schemas\StockInventoryForm;
use App\Filament\Farm\Resources\StockInventories\Schemas\StockInventoryInfolist;
use App\Filament\Farm\Resources\StockInventories\Tables\StockInventoriesTable;
use App\Models\StockInventory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StockInventoryResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;

    protected static ?string $model = StockInventory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Stocks';

    protected static ?string $modelLabel = 'Inventaire';

    protected static ?string $pluralModelLabel = 'Inventaires';

    protected static ?int $navigationSort = 6;

    protected static string $permissionGroup = 'stock_inventories';

    protected static ?string $recordTitleAttribute = 'inventory_ref';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Inventories];
    }

    public static function form(Schema $schema): Schema
    {
        return StockInventoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockInventoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockInventoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockInventories::route('/'),
            'create' => CreateStockInventory::route('/create'),
            'view' => ViewStockInventory::route('/{record}'),
            'edit' => EditStockInventory::route('/{record}/edit'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->where('organisation_id', $tenant?->getKey());
    }
}
