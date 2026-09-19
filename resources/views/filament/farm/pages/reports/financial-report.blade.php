<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtres</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php $kpis = $this->getKpis(); @endphp

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-success-600">{{ number_format($kpis['credits'], 0, ',', ' ') }}</div>
                <div class="text-sm text-gray-500">Entrées (XOF)</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-danger-600">{{ number_format($kpis['debits'], 0, ',', ' ') }}</div>
                <div class="text-sm text-gray-500">Sorties (XOF)</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold {{ $kpis['net'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">{{ number_format($kpis['net'], 0, ',', ' ') }}</div>
                <div class="text-sm text-gray-500">Solde net (XOF)</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-primary-600">{{ number_format($kpis['count']) }}</div>
                <div class="text-sm text-gray-500">Transactions</div>
            </div>
        </x-filament::section>
    </div>

    @php $expenses = $this->getExpensesByCategory(); @endphp
    @if ($expenses->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Dépenses par catégorie</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Catégorie</th>
                            <th class="px-3 py-2 text-right">Nombre</th>
                            <th class="px-3 py-2 text-right">Montant (XOF)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($expenses as $expense)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-medium">{{ \App\Enums\ExpenseCategory::tryFrom($expense->category)?->getLabel() ?? $expense->category }}</td>
                                <td class="px-3 py-2 text-right">{{ $expense->count }}</td>
                                <td class="px-3 py-2 text-right font-bold text-danger-600">{{ number_format($expense->total, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
