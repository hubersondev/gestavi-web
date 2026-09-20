<?php

namespace App\Filament\Farm\Resources\Deliveries;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Deliveries\Pages\CreateDelivery;
use App\Filament\Farm\Resources\Deliveries\Pages\EditDelivery;
use App\Filament\Farm\Resources\Deliveries\Pages\ListDeliveries;
use App\Filament\Farm\Resources\Deliveries\Pages\ViewDelivery;
use App\Filament\Farm\Resources\Deliveries\Schemas\DeliveryForm;
use App\Filament\Farm\Resources\Deliveries\Tables\DeliveriesTable;
use App\Models\Delivery;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DeliveryResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Delivery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?string $navigationLabel = 'Livraisons';

    protected static ?string $modelLabel = 'Livraison';

    protected static ?string $pluralModelLabel = 'Livraisons';

    protected static ?string $recordTitleAttribute = 'delivery_number';

    protected static ?int $navigationSort = 6;

    protected static string $permissionGroup = 'deliveries';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Deliveries];
    }

    public static function form(Schema $schema): Schema
    {
        return DeliveryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeliveriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeliveries::route('/'),
            'create' => CreateDelivery::route('/create'),
            'view' => ViewDelivery::route('/{record}'),
            'edit' => EditDelivery::route('/{record}/edit'),
        ];
    }

    /**
     * Une livraison effectuée n'est plus modifiable.
     */
    public static function canEdit(Model $record): bool
    {
        return static::memberHasPermission('update') && $record instanceof Delivery && $record->isOpen();
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return static::applyFarmScope($query->where('deliveries.organisation_id', $tenant?->getKey()));
    }
}
