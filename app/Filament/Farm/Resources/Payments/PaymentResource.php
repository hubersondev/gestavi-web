<?php

namespace App\Filament\Farm\Resources\Payments;

use App\Enums\PlanFeature;
use App\Filament\Farm\Concerns\ChecksOrganisationPermissions;
use App\Filament\Farm\Concerns\RequiresPlanFeatures;
use App\Filament\Farm\Concerns\ScopesToMemberFarms;
use App\Filament\Farm\Resources\Payments\Pages\CreatePayment;
use App\Filament\Farm\Resources\Payments\Pages\EditPayment;
use App\Filament\Farm\Resources\Payments\Pages\ListPayments;
use App\Filament\Farm\Resources\Payments\Pages\ViewPayment;
use App\Filament\Farm\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Farm\Resources\Payments\Schemas\PaymentInfolist;
use App\Filament\Farm\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PaymentResource extends Resource
{
    use ChecksOrganisationPermissions;
    use RequiresPlanFeatures;
    use ScopesToMemberFarms;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Commercial';

    protected static ?string $modelLabel = 'Paiement';

    protected static ?string $pluralModelLabel = 'Paiements';

    protected static ?int $navigationSort = 4;

    protected static string $permissionGroup = 'payments';

    public static function getRequiredPlanFeatures(): array
    {
        return [PlanFeature::Payments];
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'view' => ViewPayment::route('/{record}'),
            'edit' => EditPayment::route('/{record}/edit'),
        ];
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        $currentFarm = static::currentFarm();

        return $query->whereHas('sale', function (Builder $q) use ($tenant, $currentFarm): void {
            $q->where('organisation_id', $tenant?->getKey());

            if ($currentFarm) {
                $q->where(function (Builder $inner) use ($currentFarm): void {
                    $inner->where('farm_id', $currentFarm->id)->orWhereNull('farm_id');
                });
            }
        });
    }
}
