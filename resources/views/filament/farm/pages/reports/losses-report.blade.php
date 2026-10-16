<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtres</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php $kpis = $this->getKpis(); @endphp

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-gray-700 dark:text-gray-200">{{ number_format($kpis['mortality'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">Mortalité (sujets)</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-gray-700 dark:text-gray-200">{{ number_format($kpis['culls'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">Réformes (sujets)</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold {{ $kpis['bird_thefts'] > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ number_format($kpis['bird_thefts'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">Sujets volés</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-warning-600">{{ number_format($kpis['stock_loss_value'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">Pertes de stock (XOF) · {{ $kpis['stock_loss_count'] }} mvt</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold {{ $kpis['equipment_theft_count'] + $kpis['cash_theft_count'] > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $kpis['equipment_theft_count'] + $kpis['cash_theft_count'] }}</div>
                <div class="text-xs text-gray-500">Vols matériel / caisse</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold {{ $kpis['theft_value'] > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ number_format($kpis['theft_value'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">Total des vols (XOF)</div>
            </div>
        </x-filament::section>
    </div>

    <div class="flex justify-end mb-4">
        <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exportExcel">
            Excel
        </x-filament::button>
    </div>

    @php $flocks = $this->getFlockLosses(); @endphp
    <x-filament::section collapsible>
        <x-slot name="heading">Sujets perdus par bande</x-slot>
        @if ($flocks->isEmpty())
            <p class="text-sm text-gray-500">Aucune perte de sujets sur la période.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Ferme</th>
                            <th class="px-3 py-2">Bande</th>
                            <th class="px-3 py-2 text-right">Mortalité</th>
                            <th class="px-3 py-2 text-right">Réformes</th>
                            <th class="px-3 py-2 text-right">Vols</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($flocks as $row)
                            <tr class="border-b dark:border-gray-700 {{ $row->thefts > 0 ? 'bg-danger-50 dark:bg-danger-950' : '' }}">
                                <td class="px-3 py-2 text-gray-500">{{ $row->farm_name ?? '—' }}</td>
                                <td class="px-3 py-2 font-medium">{{ $row->flock_code }}{{ $row->flock_name ? ' – '.$row->flock_name : '' }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row->mortality, 0, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($row->culls, 0, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-right font-bold {{ $row->thefts > 0 ? 'text-danger-600' : '' }}">{{ number_format($row->thefts, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    @php $stockLosses = $this->getStockLosses(); @endphp
    <x-filament::section collapsible>
        <x-slot name="heading">Pertes, casse et vols de stock</x-slot>
        @if ($stockLosses->isEmpty())
            <p class="text-sm text-gray-500">Aucune sortie pour perte ou vol sur la période.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Ferme</th>
                            <th class="px-3 py-2">Produit</th>
                            <th class="px-3 py-2">Motif</th>
                            <th class="px-3 py-2 text-right">Quantité</th>
                            <th class="px-3 py-2 text-right">Valeur (XOF)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stockLosses as $movement)
                            <tr class="border-b dark:border-gray-700 {{ $movement->reason === \App\Enums\StockMovementReason::Theft ? 'bg-danger-50 dark:bg-danger-950' : '' }}">
                                <td class="px-3 py-2">{{ $movement->movement_date?->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $movement->stock?->farm?->name ?? 'Organisation' }}</td>
                                <td class="px-3 py-2 font-medium">{{ $movement->stock?->name }}</td>
                                <td class="px-3 py-2">{{ $movement->reason?->getLabel() }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($movement->quantity, 0, ',', ' ') }} {{ $movement->stock?->unit }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($movement->total_price, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    @php $equipmentThefts = $this->getEquipmentThefts(); @endphp
    <x-filament::section collapsible>
        <x-slot name="heading">Matériel volé</x-slot>
        @if ($equipmentThefts->isEmpty())
            <p class="text-sm text-gray-500">Aucun vol de matériel sur la période.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Équipement</th>
                            <th class="px-3 py-2">N° inventaire</th>
                            <th class="px-3 py-2 text-right">Valeur (XOF)</th>
                            <th class="px-3 py-2">Circonstances</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($equipmentThefts as $movement)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2">{{ $movement->movement_date?->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 font-medium">{{ $movement->equipment?->name }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $movement->equipment?->inventory_number }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($movement->equipment?->current_value ?? $movement->equipment?->purchase_price ?? 0, 0, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $movement->reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    @php $cashThefts = $this->getCashThefts(); @endphp
    <x-filament::section collapsible>
        <x-slot name="heading">Vols et pertes de caisse</x-slot>
        @if ($cashThefts->isEmpty())
            <p class="text-sm text-gray-500">Aucun vol de caisse sur la période.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Compte</th>
                            <th class="px-3 py-2 text-right">Montant (XOF)</th>
                            <th class="px-3 py-2">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($cashThefts as $transaction)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2">{{ $transaction->transaction_date?->format('d/m/Y') }}</td>
                                <td class="px-3 py-2 font-medium">{{ $transaction->account?->name }}</td>
                                <td class="px-3 py-2 text-right text-danger-600 font-bold">{{ number_format($transaction->amount, 0, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $transaction->description }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
