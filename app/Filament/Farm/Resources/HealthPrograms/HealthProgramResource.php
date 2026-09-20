<?php

namespace App\Filament\Farm\Resources\HealthPrograms;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\HealthPrograms\Pages\CreateHealthProgram;
use App\Filament\Farm\Resources\HealthPrograms\Pages\EditHealthProgram;
use App\Filament\Farm\Resources\HealthPrograms\Pages\ListHealthPrograms;
use App\Filament\Farm\Resources\HealthPrograms\Pages\ViewHealthProgram;
use App\Filament\Farm\Resources\HealthPrograms\Schemas\HealthProgramForm;
use App\Filament\Farm\Resources\HealthPrograms\Schemas\HealthProgramInfolist;
use App\Filament\Farm\Resources\HealthPrograms\Tables\HealthProgramsTable;
use App\Models\HealthProgram;
use App\Services\CurrentFarm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class HealthProgramResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = HealthProgram::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?string $modelLabel = 'Programme sanitaire';

    protected static ?string $pluralModelLabel = 'Programmes sanitaires';

    protected static ?string $recordTitleAttribute = 'treatment_name';

    protected static ?int $navigationSort = 3;

    protected static string $permissionGroup = 'health_programs';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::HealthPrograms];
    }

    public static function form(Schema $schema): Schema
    {
        return HealthProgramForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HealthProgramInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HealthProgramsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHealthPrograms::route('/'),
            'create' => CreateHealthProgram::route('/create'),
            'view' => ViewHealthProgram::route('/{record}'),
            'edit' => EditHealthProgram::route('/{record}/edit'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        $membership = static::currentMembership();
        $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey());

        if (! $membership) {
            return $query->whereRaw('1 = 0');
        }

        $ids = app(CurrentFarm::class)->accessibleFarmIds($membership);

        if ($ids === null) {
            return $query;
        }

        // Référentiel : les programmes d'organisation (farm_id NULL) restent visibles
        // de tous les membres ; ceux liés à une ferme suivent l'affectation.
        return $query->where(function (Builder $q) use ($ids): void {
            $q->whereNull('farm_id')->orWhereIn('farm_id', $ids);
        });
    }
}
