<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtres</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php $kpis = $this->getKpis(); @endphp

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-primary-600">{{ $kpis['products'] }}</div>
                <div class="text-xs text-gray-500">Produits actifs</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-success-600">{{ number_format($kpis['total_value'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">Valeur totale (XOF)</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold {{ $kpis['low_stock'] > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $kpis['low_stock'] }}</div>
                <div class="text-xs text-gray-500">Stocks bas</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-success-600">{{ number_format($kpis['entries'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">Entrées</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-danger-600">{{ number_format($kpis['exits'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">Sorties</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-info-600">{{ $kpis['movement_count'] }}</div>
                <div class="text-xs text-gray-500">Mouvements</div>
            </div>
        </x-filament::section>
    </div>

    @php $comparison = $this->getFarmComparison(); @endphp
    @if ($comparison->count() > 1)
        <x-filament::section collapsible>
            <x-slot name="heading">Comparaison inter-fermes</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Ferme</th>
                            <th class="px-3 py-2 text-right">Produits</th>
                            <th class="px-3 py-2 text-right">Valeur (XOF)</th>
                            <th class="px-3 py-2 text-right">Sous seuil</th>
                            <th class="px-3 py-2 text-right">Entrées</th>
                            <th class="px-3 py-2 text-right">Sorties</th>
                            <th class="px-3 py-2 text-right">Mouvements</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparison as $row)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-medium">{{ $row->farm_name }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->products }}</td>
                                <td class="px-3 py-2 text-right font-bold">{{ number_format($row->total_value, 0, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-right {{ $row->low_stock > 0 ? 'text-danger-600' : '' }}">{{ $row->low_stock }}</td>
                                <td class="px-3 py-2 text-right text-success-600">+{{ number_format($row->entries, 0, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-right text-danger-600">-{{ number_format($row->exits, 0, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->movement_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section collapsible>
        <x-slot name="heading">Niveaux de stocks actuels</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2">Ferme</th>
                        <th class="px-3 py-2">Produit</th>
                        <th class="px-3 py-2">Catégorie</th>
                        <th class="px-3 py-2 text-right">Quantité</th>
                        <th class="px-3 py-2 text-right">Seuil</th>
                        <th class="px-3 py-2 text-right">Valeur (XOF)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->getStockLevels() as $stock)
                        <tr class="border-b dark:border-gray-700 {{ $stock->isLowStock() ? 'bg-danger-50 dark:bg-danger-950' : '' }}">
                            <td class="px-3 py-2 text-gray-500">{{ $stock->farm?->name ?? 'Organisation' }}</td>
                            <td class="px-3 py-2 font-medium">{{ $stock->name }}</td>
                            <td class="px-3 py-2 text-gray-500">{{ $stock->category?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $stock->isLowStock() ? 'text-danger-600' : '' }}">{{ number_format($stock->quantity, 0, ',', ' ') }} {{ $stock->unit }}</td>
                            <td class="px-3 py-2 text-right text-gray-500">{{ number_format($stock->min_quantity, 0, ',', ' ') }} {{ $stock->unit }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($stock->getTotalValue(), 0, ',', ' ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @php $summary = $this->getProductSummary(); @endphp
    @if ($summary->isNotEmpty())
        <x-filament::section collapsible>
            <x-slot name="heading">Résumé par produit (période)</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Produit</th>
                            <th class="px-3 py-2 text-right">Entrées</th>
                            <th class="px-3 py-2 text-right">Sorties</th>
                            <th class="px-3 py-2 text-right">Solde net</th>
                            <th class="px-3 py-2 text-right">Mouvements</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary as $stock)
                            @php $net = ($stock->entries ?? 0) - ($stock->exits ?? 0); @endphp
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-medium">{{ $stock->name }}</td>
                                <td class="px-3 py-2 text-right text-success-600">+{{ number_format($stock->entries ?? 0, 0, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-right text-danger-600">-{{ number_format($stock->exits ?? 0, 0, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-right font-bold {{ $net >= 0 ? 'text-success-600' : 'text-danger-600' }}">{{ $net >= 0 ? '+' : '' }}{{ number_format($net, 0, ',', ' ') }}</td>
                                <td class="px-3 py-2 text-right">{{ $stock->movements_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
