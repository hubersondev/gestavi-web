<?php

namespace App\Filament\Farm\Widgets;

use App\Models\Stock;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockWidget extends TableWidget
{
    protected static ?string $heading = 'Stocks sous le seuil d\'alerte';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $tenant = Filament::getTenant();

                return Stock::query()
                    ->where('organisation_id', $tenant?->getKey())
                    ->active()
                    ->lowStock()
                    ->orderByRaw('quantity / NULLIF(min_quantity, 0) ASC')
                    ->limit(10);
            })
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Produit')
                    ->weight('bold')
                    ->description(fn (Stock $record): ?string => $record->category?->name),

                TextColumn::make('quantity')
                    ->label('Quantité')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(fn (Stock $record): string => ' '.$record->unit)
                    ->color('danger')
                    ->weight('bold'),

                TextColumn::make('min_quantity')
                    ->label('Seuil')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(fn (Stock $record): string => ' '.$record->unit),

                TextColumn::make('farm.name')
                    ->label('Ferme')
                    ->placeholder('Organisation'),
            ])
            ->emptyStateHeading('Aucun stock sous le seuil')
            ->emptyStateDescription('Tous les produits sont au-dessus de leur seuil d\'alerte.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
