<?php

namespace App\Filament\Farm\Resources\ProductionPhases;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Resources\ProductionPhases\Pages\CreateProductionPhase;
use App\Filament\Farm\Resources\ProductionPhases\Pages\EditProductionPhase;
use App\Filament\Farm\Resources\ProductionPhases\Pages\ListProductionPhases;
use App\Filament\Farm\Resources\ProductionPhases\Pages\ViewProductionPhase;
use App\Filament\Farm\Resources\ProductionPhases\Schemas\ProductionPhaseForm;
use App\Filament\Farm\Resources\ProductionPhases\Schemas\ProductionPhaseInfolist;
use App\Filament\Farm\Resources\ProductionPhases\Tables\ProductionPhasesTable;
use App\Models\ProductionPhase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ProductionPhaseResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;

    protected static ?string $model = ProductionPhase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $modelLabel = 'Phase de production';

    protected static ?string $pluralModelLabel = 'Phases de production';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    protected static string $permissionGroup = 'production_phases';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::ProductionPhases];
    }

    public static function form(Schema $schema): Schema
    {
        return ProductionPhaseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductionPhaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionPhasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionPhases::route('/'),
            'create' => CreateProductionPhase::route('/create'),
            'view' => ViewProductionPhase::route('/{record}'),
            'edit' => EditProductionPhase::route('/{record}/edit'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->where('organisation_id', $tenant?->getKey());
    }
}
