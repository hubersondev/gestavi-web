<?php

namespace App\Filament\Farm\Resources\StockCategories;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Resources\StockCategories\Pages\CreateStockCategory;
use App\Filament\Farm\Resources\StockCategories\Pages\EditStockCategory;
use App\Filament\Farm\Resources\StockCategories\Pages\ListStockCategories;
use App\Filament\Farm\Resources\StockCategories\Pages\ViewStockCategory;
use App\Filament\Farm\Resources\StockCategories\Schemas\StockCategoryForm;
use App\Filament\Farm\Resources\StockCategories\Schemas\StockCategoryInfolist;
use App\Filament\Farm\Resources\StockCategories\Tables\StockCategoriesTable;
use App\Models\StockCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StockCategoryResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;

    protected static ?string $model = StockCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Stocks';

    protected static ?string $modelLabel = 'Catégorie de stock';

    protected static ?string $pluralModelLabel = 'Catégories';

    protected static ?int $navigationSort = 1;

    protected static string $permissionGroup = 'stock_categories';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::StockManagement];
    }

    public static function form(Schema $schema): Schema
    {
        return StockCategoryForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockCategoryInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockCategories::route('/'),
            'create' => CreateStockCategory::route('/create'),
            'view' => ViewStockCategory::route('/{record}'),
            'edit' => EditStockCategory::route('/{record}/edit'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey());
    }
}
