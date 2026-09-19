<?php

namespace App\Filament\Farm\Resources\StockMovements\Pages;

use App\Filament\Farm\Resources\StockMovements\StockMovementResource;
use App\Models\StockMovement;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Exporter (Excel)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->schema([
                    DatePicker::make('from')
                        ->label('Du')
                        ->default(now()->startOfMonth())
                        ->required()
                        ->native(false),

                    DatePicker::make('to')
                        ->label('Au')
                        ->default(now())
                        ->required()
                        ->native(false)
                        ->afterOrEqual('from'),
                ])
                ->action(function (array $data): StreamedResponse {
                    $tenant = Filament::getTenant();
                    $from = \Carbon\Carbon::parse($data['from']);
                    $to = \Carbon\Carbon::parse($data['to']);

                    $movements = StockMovement::query()
                        ->with(['stock', 'stockLot', 'flock', 'user'])
                        ->whereHas('stock', fn ($q) => $q->where('organisation_id', $tenant?->getKey()))
                        ->forDateRange($from, $to)
                        ->orderBy('movement_date', 'desc')
                        ->get();

                    $filename = 'mouvements-stock-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx';

                    if ($movements->isEmpty()) {
                        Notification::make()
                            ->title('Aucun mouvement sur cette période')
                            ->warning()
                            ->send();
                    }

                    return response()->streamDownload(function () use ($movements): void {
                        $writer = new Writer;
                        $writer->openToFile('php://output');

                        $writer->addRow(Row::fromValues([
                            'Date', 'Produit', 'Type', 'Motif', 'Quantité', 'Unité',
                            'PU (XOF)', 'Total (XOF)', 'Lot', 'Bande', 'Référence', 'Utilisateur', 'Notes',
                        ]));

                        foreach ($movements as $movement) {
                            $writer->addRow(Row::fromValues([
                                $movement->movement_date?->format('d/m/Y') ?? '',
                                $movement->stock?->name ?? '',
                                $movement->type?->getLabel() ?? '',
                                $movement->reason?->getLabel() ?? '',
                                (float) $movement->quantity,
                                $movement->stock?->unit ?? '',
                                (float) ($movement->unit_price ?? 0),
                                (float) ($movement->total_price ?? 0),
                                $movement->stockLot?->lot_number ?? '',
                                $movement->flock?->code ?? '',
                                $movement->reference ?? '',
                                $movement->user?->name ?? '',
                                $movement->notes ?? '',
                            ]));
                        }

                        $writer->close();
                    }, $filename, [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
                }),

            CreateAction::make(),
        ];
    }
}
