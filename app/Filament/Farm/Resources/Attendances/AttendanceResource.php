<?php

namespace App\Filament\Farm\Resources\Attendances;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Resources\Attendances\Pages\CreateAttendance;
use App\Filament\Farm\Resources\Attendances\Pages\EditAttendance;
use App\Filament\Farm\Resources\Attendances\Pages\ListAttendances;
use App\Filament\Farm\Resources\Attendances\Pages\ViewAttendance;
use App\Filament\Farm\Resources\Attendances\Schemas\AttendanceForm;
use App\Filament\Farm\Resources\Attendances\Schemas\AttendanceInfolist;
use App\Filament\Farm\Resources\Attendances\Tables\AttendancesTable;
use App\Models\Attendance;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AttendanceResource extends Resource
{
    use RequiresPlanFeatures;
    use ChecksOrganisationPermissions;

    protected static ?string $model = Attendance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Ressources Humaines';

    protected static ?string $navigationLabel = 'Pointages';

    protected static ?string $modelLabel = 'Pointage';

    protected static ?string $pluralModelLabel = 'Pointages';

    protected static ?int $navigationSort = 34;

    protected static string $permissionGroup = 'attendances';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Attendance];
    }

    public static function form(Schema $schema): Schema
    {
        return AttendanceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AttendanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendancesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListAttendances::route('/'),
            'create' => CreateAttendance::route('/create'),
            'view'   => ViewAttendance::route('/{record}'),
            'edit'   => EditAttendance::route('/{record}/edit'),
        ];
    }

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // Attendance est lié au tenant indirectement via employee → organisation.
        // Pas d'auto-association Filament nécessaire.
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->whereHas('employee', fn (Builder $q) => $q->where('organisation_id', $tenant?->getKey()));
    }
}
