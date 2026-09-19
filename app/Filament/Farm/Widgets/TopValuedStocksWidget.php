<?php

namespace App\Filament\Farm\Widgets;

use App\Models\Stock;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopValuedStocksWidget extends TableWidget
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'stocks.view';

    protected static ?string $heading = 'Top 10 produits en valeur';

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $tenant = Filament::getTenant();

                return Stock::query()
                    ->where('organisation_id', $tenant?->getKey())
                    ->active()
                    ->where('quantity', '>', 0)
                    ->orderByRaw('(quantity * unit_price) DESC')
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
                    ->suffix(fn (Stock $record): string => ' '.$record->unit),

                TextColumn::make('unit_price')
                    ->label('PU')
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF'),

                TextColumn::make('total_value')
                    ->label('Valeur totale')
                    ->state(fn (Stock $record): float => $record->getTotalValue())
                    ->numeric(thousandsSeparator: ' ')
                    ->suffix(' XOF')
                    ->color('primary')
                    ->weight('bold'),
            ])
            ->emptyStateHeading('Aucun stock valorisé')
            ->emptyStateDescription('Aucun produit en stock avec valeur positive.')
            ->emptyStateIcon('heroicon-o-cube-transparent');
    }
}
