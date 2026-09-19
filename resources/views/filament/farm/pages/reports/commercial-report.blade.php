<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtres</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php $kpis = $this->getKpis(); @endphp

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-primary-600">{{ number_format($kpis['total_ca'], 0, ',', ' ') }}</div>
                <div class="text-xs text-gray-500">CA total (XOF)</div>
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
