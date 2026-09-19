<?php

namespace App\Filament\Farm\Widgets;

use App\Models\StockLot;
use App\Services\CurrentFarm;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpiringLotsWidget extends TableWidget
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'stock_lots.view';

    protected static ?string $heading = 'Lots qui vont périmer (30 jours)';

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $tenant = Filament::getTenant();

                return StockLot::query()
                    ->whereHas('stock', fn (Builder $q) => app(CurrentFarm::class)->restrictForCurrentUser($q->where('organisation_id', $tenant?->getKey())))
                    ->expiringSoon(30)
                    ->where('current_quantity', '>', 0)
                    ->orderBy('expiry_date', 'asc')
                    ->limit(10);
            })
            ->paginated(false)
            ->columns([
                TextColumn::make('lot_number')
                    ->label('N° Lot')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('stock.name')
                    ->label('Produit')
                    ->weight('bold'),

                TextColumn::make('expiry_date')
                    ->label('Péremption')
                    ->date('d/m/Y')
                    ->color(fn (StockLot $record): string => $record->isExpired() ? 'danger' : 'warning')
                    ->description(fn (StockLot $record): string => $record->isExpired()
                        ? 'Périmé'
                        : 'Dans '.(int) now()->diffInDays($record->expiry_date).' j'),

                TextColumn::make('current_quantity')
                    ->label('Qté restante')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(fn (StockLot $record): string => ' '.$record->stock?->unit)
                    ->weight('bold'),

                TextColumn::make('total_value')
                    ->label('Valeur')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF'),
            ])
            ->emptyStateHeading('Aucun lot critique')
            ->emptyStateDescription('Aucun lot ne périme dans les 30 prochains jours.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
