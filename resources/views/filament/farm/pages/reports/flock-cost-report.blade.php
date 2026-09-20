<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtres</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php $comparison = $this->getFarmComparison(); @endphp

    @if ($comparison->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Consolidation par ferme</x-slot>
            <x-slot name="description">
                Coûts directs imputés aux bandes (poussins, aliment, santé, dépenses), paie et charges de ferme réparties au prorata des sujets-jours.
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="py-2 pr-4">Ferme</th>
                            <th class="py-2 pr-4 text-right">Bandes</th>
                            <th class="py-2 pr-4 text-right">Sujets</th>
                            <th class="py-2 pr-4 text-right">Coût total (XOF)</th>
                            <th class="py-2 pr-4 text-right">Ventes (XOF)</th>
                            <th class="py-2 pr-4 text-right">Marge (XOF)</th>
                            <th class="py-2 pr-4 text-right">Taux</th>
                            <th class="py-2 pr-4 text-right">Coût / sujet</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparison as $row)
                            <tr class="border-b last:border-0">
                                <td class="py-2 pr-4 font-medium">{{ $row['ferme'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ $row['bandes'] }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($row['sujets'], 0, ',', ' ') }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($row['cout_total'], 0, ',', ' ') }}</td>
                                <td class="py-2 pr-4 text-right">{{ number_format($row['ventes'], 0, ',', ' ') }}</td>
                                <td class="py-2 pr-4 text-right font-semibold {{ $row['marge'] < 0 ? 'text-danger-600' : 'text-success-600' }}">
                                    {{ number_format($row['marge'], 0, ',', ' ') }}
                                </td>
                                <td class="py-2 pr-4 text-right">{{ $row['taux_marge'] === null ? '—' : number_format($row['taux_marge'], 1, ',', ' ').' %' }}</td>
                                <td class="py-2 pr-4 text-right">{{ $row['cout_par_sujet'] === null ? '—' : number_format($row['cout_par_sujet'], 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
