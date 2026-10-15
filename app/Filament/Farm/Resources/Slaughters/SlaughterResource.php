<?php

namespace App\Filament\Farm\Resources\Slaughters;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Slaughters\Pages\CreateSlaughter;
use App\Filament\Farm\Resources\Slaughters\Pages\EditSlaughter;
use App\Filament\Farm\Resources\Slaughters\Pages\ListSlaughters;
use App\Filament\Farm\Resources\Slaughters\Schemas\SlaughterForm;
use App\Filament\Farm\Resources\Slaughters\Tables\SlaughtersTable;
use App\Models\Slaughter;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SlaughterResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Slaughter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScissors;

    protected static string|UnitEnum|null $navigationGroup = 'Production';

    protected static ?string $navigationLabel = 'Abattages';

    protected static ?string $modelLabel = 'Abattage';

    protected static ?string $pluralModelLabel = 'Abattages';

    protected static ?int $navigationSort = 6;

    protected static string $permissionGroup = 'slaughters';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Flocks];
    }

    public static function form(Schema $schema): Schema
    {
        return SlaughterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SlaughtersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlaughters::route('/'),
            'create' => CreateSlaughter::route('/create'),
            'edit' => EditSlaughter::route('/{record}/edit'),
        ];
    }

    /**
     * Un abattage a déjà sorti les sujets et alimenté le stock : on ne le supprime pas.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return static::applyFarmScope($query->where('slaughters.organisation_id', $tenant?->getKey()));
    }
}
