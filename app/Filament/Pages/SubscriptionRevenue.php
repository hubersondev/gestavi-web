<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Subscriptions\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\SubscriptionPayment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Encaissements d'abonnement sur une période : relevé, totaux par plan et par mode de paiement,
 * export Excel pour la comptabilité de l'éditeur, facture PDF de chaque ligne.
 */
class SubscriptionRevenue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Clients';

    protected static ?string $navigationLabel = 'Encaissements';

    protected static ?string $title = 'Encaissements d\'abonnement';

    protected static ?string $slug = 'encaissements';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.subscription-revenue';

    /** @var array<string, mixed> */
    public ?array $filters = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->hasPermission('organisations.view');
    }

    public function mount(): void
    {
        $this->filters = [
            'date_from' => today()->startOfMonth()->toDateString(),
            'date_to' => today()->toDateString(),
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('filters.date_from')->label('Du')->native(false)->live(),
            DatePicker::make('filters.date_to')->label('Au')->native(false)->live(),
        ])->columns(['sm' => 2, 'lg' => 4]);
    }

    /**
     * @return array{from: string, to: string}
     */
    public function period(): array
    {
        return [
            'from' => $this->filters['date_from'] ?? today()->startOfMonth()->toDateString(),
            'to' => $this->filters['date_to'] ?? today()->toDateString(),
        ];
    }

    public function baseQuery(): Builder
    {
        $period = $this->period();

        return SubscriptionPayment::query()->paidBetween($period['from'], $period['to']);
    }

    /**
     * @return array{total: float, count: int, by_plan: array<string, float>, by_method: array<string, float>}
     */
    public function getTotals(): array
    {
        $rows = $this->baseQuery()->get(['amount', 'plan_name', 'payment_method']);

        return [
            'total' => (float) $rows->sum('amount'),
            'count' => $rows->count(),
            'by_plan' => $rows->groupBy('plan_name')->map(fn ($g) => (float) $g->sum('amount'))->sortDesc()->all(),
            'by_method' => $rows->groupBy(fn (SubscriptionPayment $p): string => $p->payment_method?->getLabel() ?? 'Non précisé')->map(fn ($g) => (float) $g->sum('amount'))->sortDesc()->all(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseQuery()->with(['organisation', 'subscription']))
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('invoice_number')->label('Facture')->weight('medium')->searchable(),
                TextColumn::make('paid_at')->label('Réglé le')->date('d/m/Y')->sortable(),
                TextColumn::make('organisation.name')->label('Structure')->searchable(),
                TextColumn::make('plan_name')->label('Plan'),
                TextColumn::make('period')->label('Période')->state(fn (SubscriptionPayment $record): string => $record->periodLabel()),
                TextColumn::make('amount')->label('Montant')->money('XOF', locale: 'fr')->alignRight()->sortable(),
                TextColumn::make('payment_method')->label('Mode')->badge()->placeholder('—'),
                TextColumn::make('payment_reference')->label('Référence')->placeholder('—')->color('gray')->toggleable(),
            ])
            ->recordActions([
                Action::make('invoice')
                    ->label('Facture PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn (SubscriptionPayment $record): StreamedResponse => PaymentsRelationManager::download($record)),
                Action::make('subscription')
                    ->label('Abonnement')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (SubscriptionPayment $record): string => SubscriptionResource::getUrl('view', ['record' => $record->subscription_id])),
            ])
            ->emptyStateHeading('Aucun encaissement sur la période');
    }

    public function exportExcel(): StreamedResponse
    {
        $period = $this->period();
        $rows = $this->baseQuery()->with('organisation')->orderBy('paid_at')->get();
        $totals = $this->getTotals();

        $tempFile = tempnam(sys_get_temp_dir(), 'gestavi-abo-');
        $writer = new Writer;
        $writer->openToFile($tempFile);
        $writer->addRow(Row::fromValues(['Facture', 'Réglé le', 'Structure', 'Plan', 'Cycle', 'Période du', 'au', 'Montant (XOF)', 'Mode de paiement', 'Référence']));

        foreach ($rows as $payment) {
            $writer->addRow(Row::fromValues([
                $payment->invoice_number,
                $payment->paid_at->format('d/m/Y'),
                $payment->organisation?->name,
                $payment->plan_name,
                $payment->billing_cycle?->getLabel(),
                $payment->period_from?->format('d/m/Y'),
                $payment->period_to?->format('d/m/Y'),
                (float) $payment->amount,
                $payment->payment_method?->getLabel(),
                $payment->payment_reference,
            ]));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Total', '', '', '', '', '', '', $totals['total']]));
        $writer->close();

        $content = (string) file_get_contents($tempFile);
        @unlink($tempFile);

        return response()->streamDownload(
            fn () => print ($content),
            'encaissements-abonnements-'.$period['from'].'-'.$period['to'].'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
