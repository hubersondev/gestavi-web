<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Période</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php
        $rows = $this->getRows();
        $totals = $this->getTotals();
        $xof = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <x-filament::section><div class="text-center"><div class="text-xl font-bold text-primary-600">{{ $totals['farms'] }}</div><div class="text-xs text-gray-500">Fermes</div></div></x-filament::section>
        <x-filament::section><div class="text-center"><div class="text-xl font-bold">{{ $xof($totals['birds_peak']) }}</div><div class="text-xs text-gray-500">Effectif (pic période)</div></div></x-filament::section>
        <x-filament::section><div class="text-center"><div class="text-xl font-bold text-danger-600">{{ $xof($totals['mortality']) }}</div><div class="text-xs text-gray-500">Mortalité</div></div></x-filament::section>
        <x-filament::section><div class="text-center"><div class="text-xl font-bold text-success-600">{{ $xof($totals['sales']) }}</div><div class="text-xs text-gray-500">Ventes (XOF)</div></div></x-filament::section>
        <x-filament::section><div class="text-center"><div class="text-xl font-bold {{ $totals['result'] < 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $xof($totals['result']) }}</div><div class="text-xs text-gray-500">Ventes − dépenses (XOF)</div></div></x-filament::section>
        <x-filament::section><div class="text-center"><div class="text-xl font-bold text-info-600">{{ $xof($totals['balance']) }}</div><div class="text-xs text-gray-500">Trésorerie (XOF)</div></div></x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Comparaison des fermes</x-slot>
        <x-slot name="description">
            Agrégats quotidiens pré-calculés (recalcul automatique chaque soir, ou via « Recalculer la période »).
            Taux de mortalité et de ponte rapportés aux sujets-jours de la période.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b">
                        <th class="py-2 pr-3">Ferme</th>
                        <th class="py-2 pr-3 text-right">Bandes</th>
                        <th class="py-2 pr-3 text-right">Effectif</th>
                        <th class="py-2 pr-3 text-right">Mortalité</th>
                        <th class="py-2 pr-3 text-right">Taux</th>
                        <th class="py-2 pr-3 text-right">Œufs</th>
                        <th class="py-2 pr-3 text-right">Ponte</th>
                        <th class="py-2 pr-3 text-right">Aliment (kg)</th>
                        <th class="py-2 pr-3 text-right">g/sujet/j</th>
                        <th class="py-2 pr-3 text-right">Ventes</th>
                        <th class="py-2 pr-3 text-right">Dépenses</th>
                        <th class="py-2 pr-3 text-right">Résultat</th>
                        <th class="py-2 pr-3 text-right">Trésorerie</th>
                        <th class="py-2 pr-3 text-right">Calculé le</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-b last:border-0">
                            <td class="py-2 pr-3 font-medium">{{ $row['farm'] }}</td>
                            <td class="py-2 pr-3 text-right">{{ $row['active_flocks'] }}</td>
                            <td class="py-2 pr-3 text-right">{{ $xof($row['birds_peak']) }}</td>
                            <td class="py-2 pr-3 text-right text-danger-600">{{ $xof($row['mortality']) }}</td>
                            <td class="py-2 pr-3 text-right">{{ $row['mortality_rate'] === null ? '—' : number_format($row['mortality_rate'], 2, ',', ' ').' %' }}</td>
                            <td class="py-2 pr-3 text-right">{{ $xof($row['eggs']) }}</td>
                            <td class="py-2 pr-3 text-right">{{ $row['laying_rate'] === null ? '—' : number_format($row['laying_rate'], 1, ',', ' ').' %' }}</td>
                            <td class="py-2 pr-3 text-right">{{ number_format($row['feed_kg'], 1, ',', ' ') }}</td>
                            <td class="py-2 pr-3 text-right">{{ $row['feed_per_bird_g'] === null ? '—' : number_format($row['feed_per_bird_g'], 1, ',', ' ') }}</td>
                            <td class="py-2 pr-3 text-right text-success-600">{{ $xof($row['sales']) }}</td>
                            <td class="py-2 pr-3 text-right">{{ $xof($row['expenses']) }}</td>
                            <td class="py-2 pr-3 text-right font-semibold {{ $row['result'] < 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $xof($row['result']) }}</td>
                            <td class="py-2 pr-3 text-right">{{ $xof($row['balance']) }}</td>
                            <td class="py-2 pr-3 text-right text-xs text-gray-400">{{ $row['computed_at'] ?? 'jamais' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="14" class="py-6 text-center text-gray-500">Aucune ferme.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
