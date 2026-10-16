<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtres</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @if (! $this->hasEnoughFarms())
        <x-filament::section>
            <p class="text-sm text-gray-500">
                Choisissez au moins deux fermes à comparer.
                @if (count($this->farmOptions()) < 2)
                    Votre périmètre ne compte qu'une ferme : la comparaison demande d'en avoir accès à plusieurs.
                @endif
            </p>
        </x-filament::section>
    @else
        @php
            $rows = $this->getRows();
            $leaders = $this->getLeaders();
            $params = $this->chartParams();
            $kind = $this->productionKind();
            $key = implode('-', $params['farmIds']).'-'.$params['dateFrom'].'-'.$params['dateTo'].'-'.($params['productionKind'] ?? 'all');
            $fmt = fn ($v, int $dec = 0) => $v === null ? '—' : number_format((float) $v, $dec, ',', ' ');
            $best = fn (string $metric, array $r) => $leaders[$metric] === $r['farm_id'] && $rows->count() > 1 ? 'font-bold text-success-600' : '';
        @endphp

        <div class="flex justify-end mb-2">
            <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exportExcel">
                Excel
            </x-filament::button>
        </div>

        <x-filament::section>
            <x-slot name="heading">Indicateurs par ferme</x-slot>
            <x-slot name="description">
                Sur la période choisie{{ $kind ? ', bandes « '.$kind->getLabel().' » uniquement' : '' }}. Taux de mortalité et de ponte rapportés aux sujets-jours des relevés ; la meilleure ferme de chaque colonne est en vert.
            </x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Ferme</th>
                            <th class="px-3 py-2 text-right">Bandes actives</th>
                            <th class="px-3 py-2 text-right">Effectif</th>
                            <th class="px-3 py-2 text-right">Mortalité</th>
                            <th class="px-3 py-2 text-right">Taux</th>
                            @if ($kind !== \App\Enums\ProductionKind::Broiler)
                                <th class="px-3 py-2 text-right">Œufs/jour</th>
                                <th class="px-3 py-2 text-right">Ponte</th>
                            @endif
                            @if ($kind !== \App\Enums\ProductionKind::Layer)
                                <th class="px-3 py-2 text-right">Poids moyen</th>
                            @endif
                            <th class="px-3 py-2 text-right">Aliment g/sujet/j</th>
                            <th class="px-3 py-2 text-right">Ventes</th>
                            <th class="px-3 py-2 text-right">Dépenses</th>
                            <th class="px-3 py-2 text-right">Résultat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr class="border-b dark:border-gray-700">
                                <td class="px-3 py-2 font-medium">{{ $r['farm_name'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $r['active_flocks'] }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($r['birds']) }}</td>
                                <td class="px-3 py-2 text-right text-danger-600">{{ $fmt($r['mortality']) }}</td>
                                <td class="px-3 py-2 text-right {{ $best('mortality_rate', $r) }}">{{ $r['mortality_rate'] === null ? '—' : $fmt($r['mortality_rate'], 2).' %' }}</td>
                                @if ($kind !== \App\Enums\ProductionKind::Broiler)
                                    <td class="px-3 py-2 text-right">{{ $fmt($r['eggs_per_day']) }}</td>
                                    <td class="px-3 py-2 text-right {{ $best('laying_rate', $r) }}">{{ $r['laying_rate'] === null ? '—' : $fmt($r['laying_rate'], 1).' %' }}</td>
                                @endif
                                @if ($kind !== \App\Enums\ProductionKind::Layer)
                                    <td class="px-3 py-2 text-right {{ $best('avg_weight_g', $r) }}">{{ $r['avg_weight_g'] === null ? '—' : $fmt($r['avg_weight_g']).' g' }}</td>
                                @endif
                                <td class="px-3 py-2 text-right {{ $best('feed_per_bird_day_g', $r) }}">{{ $fmt($r['feed_per_bird_day_g'], 1) }}</td>
                                <td class="px-3 py-2 text-right {{ $best('sales', $r) }}">{{ $fmt($r['sales']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($r['expenses']) }}</td>
                                <td class="px-3 py-2 text-right {{ $r['result'] < 0 ? 'text-danger-600' : '' }} {{ $best('result', $r) }}">{{ $fmt($r['result']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            @foreach ($this->chartMetrics() as $metric)
                @livewire(\App\Filament\Farm\Pages\Reports\Comparison\FarmComparisonBarWidget::class, $params + ['metric' => $metric], key('farm-compare-bar-'.$metric.'-'.$key))
            @endforeach
        </div>

        @livewire(\App\Filament\Farm\Pages\Reports\Comparison\FarmComparisonRadarWidget::class, $params, key('farm-compare-radar-'.$key))
    @endif
</x-filament-panels::page>
