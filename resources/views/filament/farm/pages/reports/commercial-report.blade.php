<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtres</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php
        $kpis = $this->getKpis();
        $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-primary-600">{{ $fmt($kpis['total_ca']) }}</div>
                <div class="text-xs text-gray-500">CA TTC (XOF) — HT {{ $fmt($kpis['ca_ht']) }}</div>
                @if ($kpis['credit_notes_applied'] > 0)
                    <div class="text-xs text-gray-400">Net d'avoirs : {{ $fmt($kpis['net_ca']) }}</div>
                @endif
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-success-600">{{ number_format($kpis['total_paid'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">Encaissé (XOF)</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-danger-600">{{ number_format($kpis['total_due'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">Impayé (XOF)</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-info-600">{{ $kpis['count'] }}</div>
                <div class="text-xs text-gray-500">Factures</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-warning-600">{{ number_format($kpis['avg_basket'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">Panier moyen</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold {{ $kpis['overdue'] > 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $kpis['overdue'] }}</div>
                <div class="text-xs text-gray-500">En retard</div>
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
                            <th class="px-3 py-2 text-right">Factures</th>
                            <th class="px-3 py-2 text-right">CA TTC (XOF)</th>
                            <th class="px-3 py-2 text-right">Encaissé (XOF)</th>
                            <th class="px-3 py-2 text-right">Impayé (XOF)</th>
                            <th class="px-3 py-2 text-right">Panier moyen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparison as $row)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-medium">{{ $row->farm_name }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->count }}</td>
                                <td class="px-3 py-2 text-right font-bold text-primary-600">{{ $fmt($row->total_ca) }}</td>
                                <td class="px-3 py-2 text-right text-success-600">{{ $fmt($row->total_paid) }}</td>
                                <td class="px-3 py-2 text-right text-danger-600">{{ $fmt($row->total_due) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($row->avg_basket) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @php $topCustomers = $this->getTopCustomers(); @endphp
    @if ($topCustomers->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Top 10 clients</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Client</th>
                            <th class="px-3 py-2 text-right">Factures</th>
                            <th class="px-3 py-2 text-right">CA (XOF)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topCustomers as $customer)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-medium">{{ $customer->name }}</td>
                                <td class="px-3 py-2 text-right">{{ $customer->sales_count }}</td>
                                <td class="px-3 py-2 text-right font-bold text-primary-600">{{ number_format($customer->sales_sum_total ?? 0, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
