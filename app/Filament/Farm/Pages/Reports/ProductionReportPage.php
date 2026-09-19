<?php

namespace App\Filament\Farm\Pages\Reports;

use App\Models\DailyRecord;
use App\Models\Flock;
use App\Services\CurrentFarm;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action as TableAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class ProductionReportPage extends Page implements HasTable
{
    use \App\Filament\Farm\Concerns\RequiresOrganisationPermission;

    protected static string $requiredPermission = 'daily_records.view';

    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Rapports';

    protected static ?string $navigationLabel = 'Production';

    protected static ?string $title = 'Rapport de production';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.farm.pages.reports.production-report';

    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
            'flock_id' => null,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filters.date_from')
                ->label('Du')
                ->default(now()->startOfMonth())
                ->native(false)
                ->live(),

            DatePicker::make('filters.date_to')
                ->label('Au')
                ->default(now())
                ->native(false)
                ->live(),

            Select::make('filters.flock_id')
                ->label('Bande')
                ->options(function (): array {
                    $tenant = Filament::getTenant();

                    return Flock::query()
                        ->whereHas('building.farm', fn ($q) => app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q->where('organisation_id', $tenant?->getKey())))
                        ->orderByDesc('start_date')
                        ->get()
                        ->mapWithKeys(fn (Flock $f) => [$f->id => $f->code.' — '.$f->name])
                        ->toArray();
                })
                ->placeholder('Toutes les bandes')
                ->searchable()
                ->preload()
                ->live(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DailyRecord::query()->with('flock')
            )
            ->modifyQueryUsing(function (Builder $query): void {
                $tenant = Filament::getTenant();
                $from = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
                $to = $this->filters['date_to'] ?? now()->toDateString();
                $flockId = $this->filters['flock_id'] ?? null;

                $query
                    ->whereHas('flock.building.farm', fn (Builder $q) => app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q->where('organisation_id', $tenant?->getKey())))
                    ->when($flockId, fn (Builder $q) => $q->where('flock_id', $flockId))
                    ->whereBetween('record_date', [$from, $to]);
            })
            ->defaultSort('record_date')
            ->columns([
                TextColumn::make('record_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('flock.code')
                    ->label('Bande'),
                TextColumn::make('age_days')
                    ->label('Âge')
                    ->suffix('j'),
                TextColumn::make('mortality')
                    ->label('Mortalité')
                    ->numeric()
                    ->color(fn ($state): ?string => $state > 0 ? 'danger' : null),
                TextColumn::make('eggs_collected')
                    ->label('Oeufs')
                    ->numeric()
                    ->color('success'),
                TextColumn::make('average_weight_g')
                    ->label('Poids (g)')
                    ->numeric(0),
                TextColumn::make('feed_consumed_kg')
                    ->label('Aliment (kg)')
                    ->numeric(1),
                TextColumn::make('water_consumed_l')
                    ->label('Eau (L)')
                    ->numeric(1),
                TextColumn::make('temperature_min')
                    ->label('T° min')
                    ->placeholder('—'),
                TextColumn::make('temperature_max')
                    ->label('T° max')
                    ->placeholder('—'),
            ])
            ->headerActions([
                TableAction::make('exportExcel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => $this->exportExcel()),
                TableAction::make('exportPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document')
                    ->color('danger')
                    ->action(fn () => $this->exportPdf()),
            ])
            ->emptyStateHeading('Aucun enregistrement')
            ->emptyStateDescription('Aucun enregistrement sur cette période.')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function getRecords(): Collection
    {
        $tenant = Filament::getTenant();
        $from = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $to = $this->filters['date_to'] ?? now()->toDateString();
        $flockId = $this->filters['flock_id'] ?? null;

        return DailyRecord::query()
            ->with('flock')
            ->whereHas('flock.building.farm', fn (Builder $q) => app(CurrentFarm::class)->restrictFarmsTableForCurrentUser($q->where('organisation_id', $tenant?->getKey())))
            ->when($flockId, fn (Builder $q) => $q->where('flock_id', $flockId))
            ->whereBetween('record_date', [$from, $to])
            ->orderBy('record_date')
            ->get();
    }

    public function getKpis(): array
    {
        $records = $this->getRecords();

        if ($records->isEmpty()) {
            return ['days' => 0, 'mortality' => 0, 'avg_eggs' => 0, 'avg_weight' => 0, 'total_feed' => 0];
        }

        return [
            'days' => $records->count(),
            'mortality' => (int) $records->sum('mortality'),
            'avg_eggs' => round((float) $records->avg('eggs_collected'), 1),
            'avg_weight' => round((float) $records->avg('average_weight_g'), 1),
            'total_feed' => round((float) $records->sum('feed_consumed_kg'), 1),
        ];
    }

    public function exportExcel(): StreamedResponse
    {
        $records = $this->getRecords();
        $filename = 'rapport-production-'.($this->filters['date_from'] ?? 'all').'.xlsx';

        return response()->streamDownload(function () use ($records): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                'Date', 'Bande', 'Âge (j)', 'Mortalité', 'Oeufs collectés',
                'Oeufs cassés', 'Poids moy. (g)', 'Aliment (kg)', 'Eau (L)',
                'T° min (°C)', 'T° max (°C)', 'Humidité (%)',
            ]));

            foreach ($records as $record) {
                $writer->addRow(Row::fromValues([
                    $record->record_date?->format('d/m/Y') ?? '',
                    $record->flock?->code ?? '',
                    $record->age_days ?? '',
                    $record->mortality ?? 0,
                    $record->eggs_collected ?? 0,
                    $record->eggs_broken ?? 0,
                    (float) ($record->average_weight_g ?? 0),
                    (float) ($record->feed_consumed_kg ?? 0),
                    (float) ($record->water_consumed_l ?? 0),
                    (float) ($record->temperature_min ?? 0),
                    (float) ($record->temperature_max ?? 0),
                    (float) ($record->humidity_percent ?? 0),
                ]));
            }

            $writer->close();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function exportPdf(): StreamedResponse
    {
        $records = $this->getRecords();
        $kpis = $this->getKpis();
        $organisation = Filament::getTenant();

        $flock = ! empty($this->filters['flock_id']) ? Flock::find($this->filters['flock_id']) : null;

        $pdf = Pdf::loadView('pdf.production-report', [
            'organisation' => $organisation,
            'flock' => $flock,
            'dateFrom' => $this->filters['date_from'],
            'dateTo' => $this->filters['date_to'],
            'kpis' => $kpis,
            'records' => $records,
        ])->setPaper('a4', 'landscape');

        $filename = 'rapport-production-'.($this->filters['date_from'] ?? 'all').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
