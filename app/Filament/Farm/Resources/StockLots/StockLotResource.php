<?php

namespace App\Filament\Farm\Resources\StockLots;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Resources\StockLots\Pages\CreateStockLot;
use App\Filament\Farm\Resources\StockLots\Pages\EditStockLot;
use App\Filament\Farm\Resources\StockLots\Pages\ListStockLots;
use App\Filament\Farm\Resources\StockLots\Pages\ViewStockLot;
use App\Filament\Farm\Resources\StockLots\Schemas\StockLotForm;
use App\Filament\Farm\Resources\StockLots\Schemas\StockLotInfolist;
use App\Filament\Farm\Resources\StockLots\Tables\StockLotsTable;
use App\Models\StockLot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class StockLotResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;

    protected static ?string $model = StockLot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Stocks';

    protected static ?string $modelLabel = 'Lot de stock';

    protected static ?string $pluralModelLabel = 'Lots de stock';

    protected static ?int $navigationSort = 3;

    protected static string $permissionGroup = 'stock_lots';

    protected static ?string $recordTitleAttribute = 'lot_number';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::StockBatches];
    }

    public static function form(Schema $schema): Schema
    {
        return StockLotForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockLotInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockLotsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockLots::route('/'),
            'create' => CreateStockLot::route('/create'),
            'view' => ViewStockLot::route('/{record}'),
            'edit' => EditStockLot::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->whereHas('stock', fn (Builder $q) => $q->where('organisation_id', $tenant?->getKey()));
    }
}
