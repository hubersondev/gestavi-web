<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtres</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php
        $kpis = $this->getKpis();
        $fmt = fn ($v, $d = 0) => number_format((float) $v, $d, ',', ' ');
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-primary-600">{{ $kpis['days'] }}</div>
                <div class="text-sm text-gray-500">Relevés ({{ $kpis['flocks'] }} bande{{ $kpis['flocks'] > 1 ? 's' : '' }})</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-danger-600">{{ $fmt($kpis['mortality']) }}</div>
                <div class="text-sm text-gray-500">Mortalité ({{ $fmt($kpis['mortality_rate'], 2) }} %)</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-600">{{ $fmt($kpis['culls']) }}</div>
                <div class="text-sm text-gray-500">Réformes</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-success-600">{{ $fmt($kpis['eggs']) }}</div>
                <div class="text-sm text-gray-500">Œufs ({{ $fmt($kpis['laying_rate'], 2) }} % de ponte)</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-warning-600">{{ $fmt($kpis['total_feed'], 1) }} kg</div>
                <div class="text-sm text-gray-500">Aliment ({{ $fmt($kpis['feed_per_bird_day'], 1) }} g/sujet/j)</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-info-600">{{ $fmt($kpis['avg_weight']) }} g</div>
                <div class="text-sm text-gray-500">Poids moyen</div>
            </div>
        </x-filament::section>
    </div>

    @if ($kpis['ic'] !== null || $kpis['gmq'] !== null)
        <div class="grid grid-cols-3 gap-4 mb-6">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">{{ $kpis['ic'] !== null ? $fmt($kpis['ic'], 2) : '—' }}</div>
                    <div class="text-sm text-gray-500">Indice de consommation (bande)</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">{{ $kpis['gmq'] !== null ? $fmt($kpis['gmq'], 1).' g/j' : '—' }}</div>
                    <div class="text-sm text-gray-500">GMQ (bande)</div>
                </div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">{{ $kpis['ipe'] !== null ? $fmt($kpis['ipe'], 1) : '—' }}</div>
                    <div class="text-sm text-gray-500">IPE / EPEF (bande)</div>
                </div>
            </x-filament::section>
        </div>
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
                            <th class="px-3 py-2 text-right">Bandes</th>
                            <th class="px-3 py-2 text-right">Relevés</th>
                            <th class="px-3 py-2 text-right">Mortalité</th>
                            <th class="px-3 py-2 text-right">Réformes</th>
                            <th class="px-3 py-2 text-right">Œufs</th>
                            <th class="px-3 py-2 text-right">Taux de ponte</th>
                            <th class="px-3 py-2 text-right">Aliment (kg)</th>
                            <th class="px-3 py-2 text-right">g/sujet/j</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comparison as $row)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-medium">{{ $row->farm_name }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->flocks }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->records }}</td>
                                <td class="px-3 py-2 text-right text-danger-600">{{ $fmt($row->mortality) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($row->culls) }}</td>
                                <td class="px-3 py-2 text-right text-success-600">{{ $fmt($row->eggs) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($row->laying_rate, 2) }} %</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($row->total_feed, 1) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($row->feed_per_bird_day, 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
