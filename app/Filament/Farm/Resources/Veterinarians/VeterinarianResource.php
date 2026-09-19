<?php

namespace App\Filament\Farm\Resources\Veterinarians;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Resources\Veterinarians\Pages\CreateVeterinarian;
use App\Filament\Farm\Resources\Veterinarians\Pages\EditVeterinarian;
use App\Filament\Farm\Resources\Veterinarians\Pages\ListVeterinarians;
use App\Filament\Farm\Resources\Veterinarians\Pages\ViewVeterinarian;
use App\Filament\Farm\Resources\Veterinarians\Schemas\VeterinarianForm;
use App\Filament\Farm\Resources\Veterinarians\Schemas\VeterinarianInfolist;
use App\Filament\Farm\Resources\Veterinarians\Tables\VeterinariansTable;
use App\Models\Veterinarian;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class VeterinarianResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;

    protected static ?string $model = Veterinarian::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $navigationLabel = 'Vétérinaires';

    protected static ?string $modelLabel = 'Vétérinaire';

    protected static ?string $pluralModelLabel = 'Vétérinaires';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 4;

    protected static string $permissionGroup = 'veterinarians';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::VetVisits];
    }

    public static function form(Schema $schema): Schema
    {
        return VeterinarianForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VeterinarianInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VeterinariansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVeterinarians::route('/'),
            'create' => CreateVeterinarian::route('/create'),
            'view' => ViewVeterinarian::route('/{record}'),
            'edit' => EditVeterinarian::route('/{record}/edit'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->where('organisation_id', $tenant?->getKey());
    }
}
