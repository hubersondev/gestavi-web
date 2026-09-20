<?php

namespace App\Filament\Farm\Resources\Quotes;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Quotes\Pages\CreateQuote;
use App\Filament\Farm\Resources\Quotes\Pages\EditQuote;
use App\Filament\Farm\Resources\Quotes\Pages\ListQuotes;
use App\Filament\Farm\Resources\Quotes\Pages\ViewQuote;
use App\Filament\Farm\Resources\Quotes\Schemas\QuoteForm;
use App\Filament\Farm\Resources\Quotes\Schemas\QuoteInfolist;
use App\Filament\Farm\Resources\Quotes\Tables\QuotesTable;
use App\Models\Quote;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class QuoteResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Quote::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?string $modelLabel = 'Devis';

    protected static ?string $pluralModelLabel = 'Devis';

    protected static ?int $navigationSort = 2;

    protected static string $permissionGroup = 'quotes';

    protected static ?string $recordTitleAttribute = 'quote_number';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Quotes];
    }

    public static function form(Schema $schema): Schema
    {
        return QuoteForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return QuoteInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuotesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuotes::route('/'),
            'create' => CreateQuote::route('/create'),
            'view' => ViewQuote::route('/{record}'),
            'edit' => EditQuote::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return static::applyFarmScope(
            $query->where($query->qualifyColumn('organisation_id'), $tenant?->getKey()),
        );
    }
}
