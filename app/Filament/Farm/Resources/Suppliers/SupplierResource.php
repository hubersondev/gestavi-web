<?php

namespace App\Filament\Farm\Resources\Suppliers;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Farm\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Farm\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Farm\Resources\Suppliers\Pages\ViewSupplier;
use App\Filament\Farm\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Farm\Resources\Suppliers\Schemas\SupplierInfolist;
use App\Filament\Farm\Resources\Suppliers\Tables\SuppliersTable;
use App\Models\Supplier;
use App\Services\CurrentFarm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupplierResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?string $modelLabel = 'Fournisseur';

    protected static ?string $pluralModelLabel = 'Fournisseurs';

    protected static ?int $navigationSort = 6;

    protected static string $permissionGroup = 'suppliers';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Suppliers];
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupplierInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
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
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'view' => ViewSupplier::route('/{record}'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        $membership = static::currentMembership();
        $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey());

        if (! $membership) {
            return $query->whereRaw('1 = 0');
        }

        $currentFarm = app(CurrentFarm::class);
        $ids = $currentFarm->accessibleFarmIds($membership);

        if ($ids === null) {
            return $query;
        }

        $farmSelected = $currentFarm->get($membership) !== null;

        // Fournisseurs de ferme : selon l'affectation ; fournisseurs réseau (farm_id NULL) :
        // visibles de tous les membres sauf quand une ferme précise est sélectionnée.
        return $query->where(function (Builder $q) use ($ids, $farmSelected): void {
            $q->whereIn('farm_id', $ids);

            if (! $farmSelected) {
                $q->orWhereNull('farm_id');
            }
        });
    }
}
