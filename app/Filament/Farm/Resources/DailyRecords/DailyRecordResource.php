<?php

namespace App\Filament\Farm\Resources\DailyRecords;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\DailyRecords\Pages\CreateDailyRecord;
use App\Filament\Farm\Resources\DailyRecords\Pages\EditDailyRecord;
use App\Filament\Farm\Resources\DailyRecords\Pages\ListDailyRecords;
use App\Filament\Farm\Resources\DailyRecords\Pages\ViewDailyRecord;
use App\Filament\Farm\Resources\DailyRecords\Schemas\DailyRecordForm;
use App\Filament\Farm\Resources\DailyRecords\Schemas\DailyRecordInfolist;
use App\Filament\Farm\Resources\DailyRecords\Tables\DailyRecordsTable;
use App\Models\DailyRecord;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DailyRecordResource extends Resource
{
    use RequiresPlanFeatures;
    use ChecksOrganisationPermissions;
    use ScopesToMemberFarms;

    protected static ?string $model = DailyRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?string $navigationLabel = 'Suivi Quotidien';

    protected static ?string $modelLabel = 'Suivi quotidien';

    protected static ?string $pluralModelLabel = 'Suivis quotidiens';

    protected static ?string $recordTitleAttribute = 'record_date';

    protected static ?int $navigationSort = 5;

    protected static string $permissionGroup = 'daily_records';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::DailyRecords];
    }

    public static function form(Schema $schema): Schema
    {
        return DailyRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DailyRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DailyRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyRecords::route('/'),
            'create' => CreateDailyRecord::route('/create'),
            'view' => ViewDailyRecord::route('/{record}'),
            'edit' => EditDailyRecord::route('/{record}/edit'),
        ];
    }

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // DailyRecord est lié au tenant indirectement via flock → building → farm → organisation.
        // Il n'a pas de FK directe sur organisation_id ; le flock_id établit l'appartenance au tenant.
        // On désactive l'association automatique de Filament pour éviter l'appel à $record->organisation().
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        $membership = static::currentMembership();
        $currentFarm = static::currentFarm();

        return $query->whereHas('flock.building.farm', function (Builder $q) use ($tenant, $membership, $currentFarm) {
            $q->where('farms.organisation_id', $tenant?->getKey());

            if ($membership) {
                $q->accessibleBy($membership);
            }

            if ($currentFarm) {
                $q->where('farms.id', $currentFarm->id);
            }
        });
    }
}
