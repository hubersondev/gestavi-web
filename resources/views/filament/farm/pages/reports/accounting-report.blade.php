<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtres</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php
        $balance = $this->getBalance();
        $income = $this->getIncomeStatement();
        $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-filament::section><div class="text-center"><div class="text-xl font-bold text-success-600">{{ $fmt($income['total_produits']) }}</div><div class="text-xs text-gray-500">Produits (classe 7, XOF)</div></div></x-filament::section>
        <x-filament::section><div class="text-center"><div class="text-xl font-bold text-danger-600">{{ $fmt($income['total_charges']) }}</div><div class="text-xs text-gray-500">Charges (classe 6, XOF)</div></div></x-filament::section>
        <x-filament::section><div class="text-center"><div class="text-xl font-bold {{ $income['resultat'] < 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $fmt($income['resultat']) }}</div><div class="text-xs text-gray-500">Résultat (produits − charges)</div></div></x-filament::section>
        <x-filament::section><div class="text-center"><div class="text-xl font-bold text-primary-600">{{ $balance->count() }}</div><div class="text-xs text-gray-500">Comptes mouvementés</div></div></x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Compte de résultat simplifié</x-slot>
        <x-slot name="description">Charges et produits de la période, par compte du plan SYSCOHADA.</x-slot>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-sm">
            <div>
                <div class="font-semibold text-danger-600 mb-2">Charges</div>
                <table class="w-full">
                    @forelse ($income['charges'] as $row)
                        <tr class="border-b last:border-0"><td class="py-1 pr-2 font-mono">{{ $row['code'] }}</td><td class="py-1 pr-2">{{ $row['label'] }}</td><td class="py-1 text-right">{{ $fmt($row['balance']) }}</td></tr>
                    @empty
                        <tr><td class="py-2 text-gray-500">Aucune charge.</td></tr>
                    @endforelse
                    <tr class="font-semibold"><td colspan="2" class="py-1 pr-2 text-right">Total charges</td><td class="py-1 text-right">{{ $fmt($income['total_charges']) }}</td></tr>
                </table>
            </div>
            <div>
                <div class="font-semibold text-success-600 mb-2">Produits</div>
                <table class="w-full">
                    @forelse ($income['produits'] as $row)
                        <tr class="border-b last:border-0"><td class="py-1 pr-2 font-mono">{{ $row['code'] }}</td><td class="py-1 pr-2">{{ $row['label'] }}</td><td class="py-1 text-right">{{ $fmt(-$row['balance']) }}</td></tr>
                    @empty
                        <tr><td class="py-2 text-gray-500">Aucun produit.</td></tr>
                    @endforelse
                    <tr class="font-semibold"><td colspan="2" class="py-1 pr-2 text-right">Total produits</td><td class="py-1 text-right">{{ $fmt($income['total_produits']) }}</td></tr>
                </table>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Balance des comptes</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b">
                        <th class="py-2 pr-3">Compte</th>
                        <th class="py-2 pr-3">Intitulé</th>
                        <th class="py-2 pr-3 text-right">Total débit</th>
                        <th class="py-2 pr-3 text-right">Total crédit</th>
                        <th class="py-2 pr-3 text-right">Solde débiteur</th>
                        <th class="py-2 pr-3 text-right">Solde créditeur</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($balance as $row)
                        <tr class="border-b last:border-0">
                            <td class="py-1 pr-3 font-mono font-medium">{{ $row['code'] }}</td>
                            <td class="py-1 pr-3">{{ $row['label'] }}</td>
                            <td class="py-1 pr-3 text-right">{{ $fmt($row['debit']) }}</td>
                            <td class="py-1 pr-3 text-right">{{ $fmt($row['credit']) }}</td>
                            <td class="py-1 pr-3 text-right">{{ $row['balance'] > 0 ? $fmt($row['balance']) : '' }}</td>
                            <td class="py-1 pr-3 text-right">{{ $row['balance'] < 0 ? $fmt(-$row['balance']) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-center text-gray-500">Aucune écriture sur la période.</td></tr>
                    @endforelse
                    @if ($balance->isNotEmpty())
                        <tr class="font-semibold border-t">
                            <td colspan="2" class="py-2 pr-3 text-right">Totaux</td>
                            <td class="py-2 pr-3 text-right">{{ $fmt($balance->sum('debit')) }}</td>
                            <td class="py-2 pr-3 text-right">{{ $fmt($balance->sum('credit')) }}</td>
                            <td class="py-2 pr-3 text-right">{{ $fmt($balance->where('balance', '>', 0)->sum('balance')) }}</td>
                            <td class="py-2 pr-3 text-right">{{ $fmt(-$balance->where('balance', '<', 0)->sum('balance')) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Grand livre</x-slot>
        <x-slot name="description">Écritures groupées par compte (2 000 lignes maximum ; utilisez l'export Excel au-delà).</x-slot>
        @foreach ($this->getLedger() as $code => $lines)
            <div class="mb-4">
                <div class="font-semibold font-mono">{{ $code }} — {{ $lines->first()->chart_account_label }}</div>
                <table class="w-full text-sm">
                    @php $running = 0; @endphp
                    @foreach ($lines as $line)
                        @php $running += (float) $line->debit - (float) $line->credit; @endphp
                        <tr class="border-b last:border-0">
                            <td class="py-1 pr-2 whitespace-nowrap">{{ $line->entry_date->format('d/m/Y') }}</td>
                            <td class="py-1 pr-2">{{ $line->entry?->entry_number }}</td>
                            <td class="py-1 pr-2">{{ $line->label ?? $line->entry?->label }}</td>
                            <td class="py-1 pr-2 text-right">{{ (float) $line->debit > 0 ? $fmt($line->debit) : '' }}</td>
                            <td class="py-1 pr-2 text-right">{{ (float) $line->credit > 0 ? $fmt($line->credit) : '' }}</td>
                            <td class="py-1 text-right text-gray-500">{{ $fmt($running) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endforeach
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Journal</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
