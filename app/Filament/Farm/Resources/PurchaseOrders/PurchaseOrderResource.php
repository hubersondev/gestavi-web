<?php

namespace App\Filament\Farm\Resources\PurchaseOrders;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Farm\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Farm\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Filament\Farm\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Farm\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Filament\Farm\Resources\PurchaseOrders\Schemas\PurchaseOrderInfolist;
use App\Filament\Farm\Resources\PurchaseOrders\Tables\PurchaseOrdersTable;
use App\Models\PurchaseOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PurchaseOrderResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Stocks';

    protected static ?string $modelLabel = 'Bon de commande';

    protected static ?string $pluralModelLabel = 'Bons de commande';

    protected static ?int $navigationSort = 5;

    protected static string $permissionGroup = 'purchase_orders';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::PurchaseOrders];
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseOrderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseOrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseOrders::route('/'),
            'create' => CreatePurchaseOrder::route('/create'),
            'view' => ViewPurchaseOrder::route('/{record}'),
            'edit' => EditPurchaseOrder::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        $currentFarm = static::currentFarm();

        return $query
            ->where('organisation_id', $tenant?->getKey())
            ->when($currentFarm, function (Builder $q) use ($currentFarm): void {
                $q->where(function (Builder $inner) use ($currentFarm): void {
                    $inner->where('farm_id', $currentFarm->id)->orWhereNull('farm_id');
                });
            });
    }
}
