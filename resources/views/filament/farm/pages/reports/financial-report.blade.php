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
                <div class="text-xl font-bold text-gray-700">{{ $fmt($kpis['opening_balance']) }}</div>
                <div class="text-xs text-gray-500">Solde d'ouverture (XOF)</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-success-600">{{ $fmt($kpis['credits']) }}</div>
                <div class="text-xs text-gray-500">Entrées (XOF)</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-danger-600">{{ $fmt($kpis['debits']) }}</div>
                <div class="text-xs text-gray-500">Sorties (XOF)</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold {{ $kpis['net'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">{{ $fmt($kpis['net']) }}</div>
                <div class="text-xs text-gray-500">Flux net de la période (XOF)</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold {{ $kpis['closing_balance'] >= 0 ? 'text-primary-600' : 'text-danger-600' }}">{{ $fmt($kpis['closing_balance']) }}</div>
                <div class="text-xs text-gray-500">Solde de clôture (XOF)</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-xl font-bold text-primary-600">{{ number_format($kpis['count']) }}</div>
                <div class="text-xs text-gray-500">Transactions ({{ $kpis['accounts'] }} compte{{ $kpis['accounts'] > 1 ? 's' : '' }})</div>
            </div>
        </x-filament::section>
    </div>

    @php $balances = $this->getAccountBalances(); @endphp
    @if ($balances->isNotEmpty())
        <x-filament::section collapsible>
            <x-slot name="heading">Soldes par compte</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Ferme</th>
                            <th class="px-3 py-2">Compte</th>
                            <th class="px-3 py-2 text-right">Ouverture</th>
                            <th class="px-3 py-2 text-right">Entrées</th>
                            <th class="px-3 py-2 text-right">Sorties</th>
                            <th class="px-3 py-2 text-right">Clôture</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($balances as $balance)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 text-gray-500">{{ $balance->farm_name }}</td>
                                <td class="px-3 py-2 font-medium">{{ $balance->account_name }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($balance->opening) }}</td>
                                <td class="px-3 py-2 text-right text-success-600">{{ $fmt($balance->credits) }}</td>
                                <td class="px-3 py-2 text-right text-danger-600">{{ $fmt($balance->debits) }}</td>
                                <td class="px-3 py-2 text-right font-bold {{ $balance->closing >= 0 ? '' : 'text-danger-600' }}">{{ $fmt($balance->closing) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @php $comparison = $this->getFarmComparison(); @endphp
    @if ($comparison->count() > 1)
        <x-filament::section collapsible>
            <x-slot name="heading">Comparaison inter-fermes</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Ferme</th>
                            <th class="px-3 py-2 text-right">Comptes</th>
                            <th class="px-3 py-2 text-right">Transactions</th>
                            <th class="px-3 py-2 text-right">Entrées (XOF)</th>
                            <th class="px-3 py-2 text-right">Sorties (XOF)</th>
                            <th class="px-3 py-2 text-right">Flux net (XOF)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparison as $row)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-medium">{{ $row->farm_name }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->accounts }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->count }}</td>
                                <td class="px-3 py-2 text-right text-success-600">{{ $fmt($row->credits) }}</td>
                                <td class="px-3 py-2 text-right text-danger-600">{{ $fmt($row->debits) }}</td>
                                <td class="px-3 py-2 text-right font-bold {{ $row->net >= 0 ? 'text-success-600' : 'text-danger-600' }}">{{ $fmt($row->net) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

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
