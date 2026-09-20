<?php

namespace App\Filament\Farm\Resources\FarmLogs;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\FarmLogs\Pages\CreateFarmLog;
use App\Filament\Farm\Resources\FarmLogs\Pages\EditFarmLog;
use App\Filament\Farm\Resources\FarmLogs\Pages\ListFarmLogs;
use App\Filament\Farm\Resources\FarmLogs\Pages\ViewFarmLog;
use App\Filament\Farm\Resources\FarmLogs\Schemas\FarmLogForm;
use App\Filament\Farm\Resources\FarmLogs\Schemas\FarmLogInfolist;
use App\Filament\Farm\Resources\FarmLogs\Tables\FarmLogsTable;
use App\Models\FarmLog;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FarmLogResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = FarmLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?string $navigationLabel = 'Journal de ferme';

    protected static ?string $modelLabel = 'Entrée journal';

    protected static ?string $pluralModelLabel = 'Journal de ferme';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 8;

    protected static string $permissionGroup = 'farm_logs';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::FarmJournal];
    }

    public static function form(Schema $schema): Schema
    {
        return FarmLogForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FarmLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FarmLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFarmLogs::route('/'),
            'create' => CreateFarmLog::route('/create'),
            'view' => ViewFarmLog::route('/{record}'),
            'edit' => EditFarmLog::route('/{record}/edit'),
        ];
    }

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // FarmLog est lié au tenant indirectement via farm → organisation (pas de FK directe).
        // Le farm_id du formulaire établit l'appartenance ; on désactive l'auto-association de Filament.
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        // Discriminants directs (organisation_id / farm_id) : plus de jointure à travers la chaîne parente
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
