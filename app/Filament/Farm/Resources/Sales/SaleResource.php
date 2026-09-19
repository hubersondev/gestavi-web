<?php

namespace App\Filament\Farm\Resources\Sales;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Sales\Pages\CreateSale;
use App\Filament\Farm\Resources\Sales\Pages\EditSale;
use App\Filament\Farm\Resources\Sales\Pages\ListSales;
use App\Filament\Farm\Resources\Sales\Pages\ViewSale;
use App\Filament\Farm\Resources\Sales\RelationManagers\PaymentsRelationManager;
use App\Filament\Farm\Resources\Sales\Schemas\SaleForm;
use App\Filament\Farm\Resources\Sales\Schemas\SaleInfolist;
use App\Filament\Farm\Resources\Sales\Tables\SalesTable;
use App\Models\Sale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SaleResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?string $modelLabel = 'Facture';

    protected static ?string $pluralModelLabel = 'Factures';

    protected static ?int $navigationSort = 3;

    protected static string $permissionGroup = 'sales';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Invoices];
    }

    public static function form(Schema $schema): Schema
    {
        return SaleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
            'create' => CreateSale::route('/create'),
            'view' => ViewSale::route('/{record}'),
            'edit' => EditSale::route('/{record}/edit'),
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
