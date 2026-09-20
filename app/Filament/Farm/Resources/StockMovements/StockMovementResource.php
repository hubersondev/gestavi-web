<?php

namespace App\Filament\Farm\Resources\StockMovements;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\StockMovements\Pages\CreateStockMovement;
use App\Filament\Farm\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Farm\Resources\StockMovements\Pages\ViewStockMovement;
use App\Filament\Farm\Resources\StockMovements\Schemas\StockMovementForm;
use App\Filament\Farm\Resources\StockMovements\Schemas\StockMovementInfolist;
use App\Filament\Farm\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StockMovementResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static string|UnitEnum|null $navigationGroup = 'Stocks';

    protected static ?string $modelLabel = 'Mouvement de stock';

    protected static ?string $pluralModelLabel = 'Mouvements';

    protected static ?int $navigationSort = 4;

    protected static string $permissionGroup = 'stock_movements';

    /**
     * Un mouvement de stock est un journal : il a déjà modifié la quantité
     * du produit et ne se modifie ni ne se supprime après coup.
     */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::StockManagement];
    }

    public static function form(Schema $schema): Schema
    {
        return StockMovementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockMovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
            'create' => CreateStockMovement::route('/create'),
            'view' => ViewStockMovement::route('/{record}'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        // Discriminants directs (organisation_id / farm_id) : plus de jointure à travers la chaîne parente
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
