<?php

namespace App\Filament\Farm\Resources\StockLots;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
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
    use ScopesToMemberFarms;

    protected static ?string $model = StockLot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Stocks';

    protected static ?string $modelLabel = 'Lot de stock';

    protected static ?string $pluralModelLabel = 'Lots de stock';

    protected static ?int $navigationSort = 3;

    protected static string $permissionGroup = 'stock_lots';

    /**
     * Un lot a généré une entrée de stock à sa création : on ne le supprime pas
     * (les quantités sont pilotées par les mouvements, ses métadonnées restent éditables).
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

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
        // Discriminants directs (organisation_id / farm_id) : plus de jointure à travers la chaîne parente
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
