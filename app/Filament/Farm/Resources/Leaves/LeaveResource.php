<?php

namespace App\Filament\Farm\Resources\Leaves;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Leaves\Pages\CreateLeave;
use App\Filament\Farm\Resources\Leaves\Pages\EditLeave;
use App\Filament\Farm\Resources\Leaves\Pages\ListLeaves;
use App\Filament\Farm\Resources\Leaves\Pages\ViewLeave;
use App\Filament\Farm\Resources\Leaves\Schemas\LeaveForm;
use App\Filament\Farm\Resources\Leaves\Schemas\LeaveInfolist;
use App\Filament\Farm\Resources\Leaves\Tables\LeavesTable;
use App\Models\Leave;
use BackedEnum;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class LeaveResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Leave::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Ressources Humaines';

    protected static ?string $navigationLabel = 'Congés';

    protected static ?string $modelLabel = 'Congé';

    protected static ?string $pluralModelLabel = 'Congés';

    protected static ?int $navigationSort = 33;

    protected static string $permissionGroup = 'leaves';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::LeaveManagement];
    }

    public static function form(Schema $schema): Schema
    {
        return LeaveForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LeaveInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeavesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeaves::route('/'),
            'create' => CreateLeave::route('/create'),
            'view' => ViewLeave::route('/{record}'),
            'edit' => EditLeave::route('/{record}/edit'),
        ];
    }

    public static function observeTenancyModelCreation(Panel $panel): void
    {
        // Leave est lié au tenant via employee → organisation_id (pas de FK directe).
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return static::applyFarmScopeThrough(
            $query->whereHas('employee', fn (Builder $q) => $q->where('organisation_id', $tenant?->getKey())),
            'employee',
        );
    }
}
