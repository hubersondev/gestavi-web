<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Bande et grandeur</x-slot>
        <x-slot name="description">
            Les prévisions sont recalculées chaque nuit à partir des 14 derniers relevés et de la courbe de la souche.
            Elles demandent au moins {{ \App\Services\Forecast\ForecastService::MIN_RECORDS }} relevés par bande.
        </x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php
        $flock = $this->selectedFlock();
        $horizon = $this->horizonPredictions();
        $type = $this->selectedType();
    @endphp

    @if (! $flock)
        <x-filament::section>
            <p class="text-sm text-gray-500">Aucune bande active dans ce périmètre.</p>
        </x-filament::section>
    @elseif ($horizon->isEmpty())
        <x-filament::section>
            <x-slot name="heading">{{ $flock->code }}{{ $flock->name ? ' – '.$flock->name : '' }}</x-slot>
            <p class="text-sm text-gray-500">
                Pas encore de prévision pour cette bande :
                @if ($this->dataPointsCount() < \App\Services\Forecast\ForecastService::MIN_RECORDS)
                    {{ $this->dataPointsCount() }} relevé(s) saisi(s) sur les {{ \App\Services\Forecast\ForecastService::MIN_RECORDS }} nécessaires.
                @else
                    le prochain calcul nocturne les produira.
                @endif
                @if (blank($flock->breed))
                    Renseignez la souche de la bande (Cobb 500, Ross 308, ISA Brown, Lohmann Brown…) pour comparer à sa courbe de référence.
                @endif
            </p>
        </x-filament::section>
    @else
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach ($horizon as $prediction)
                @php
                    $t = $prediction->type;
                    $std = $prediction->standard_value;
                    $worse = $std !== null && ($t->higherIsWorse() ? $prediction->predicted_value > $std * 1.1 : $prediction->predicted_value < $std * 0.9);
                @endphp
                <x-filament::section>
                    <div class="text-center">
                        <div class="text-xs text-gray-500">{{ $t->getLabel() }} · {{ $prediction->target_date->format('d/m') }}</div>
                        <div class="text-2xl font-bold {{ $worse ? 'text-danger-600' : 'text-primary-600' }}">
                            {{ number_format($prediction->predicted_value, $t->unit() === '%' ? 1 : 0, ',', ' ') }} {{ $t->unit() }}
                        </div>
                        <div class="text-xs text-gray-500">
                            Confiance {{ number_format($prediction->confidence, 0) }} % ({{ $prediction->confidenceLabel() }})
                            @if ($std !== null)
                                · souche {{ number_format($std, $t->unit() === '%' ? 1 : 0, ',', ' ') }} {{ $t->unit() }}
                            @endif
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        <div class="mb-6">
            @livewire(\App\Filament\Farm\Pages\Forecasts\ForecastChartWidget::class, ['flockId' => $flock->id, 'type' => $type->value], key('forecast-chart-'.$flock->id.'-'.$type->value))
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2">
                <x-filament::section collapsible>
                    <x-slot name="heading">{{ $type->getLabel() }} : prévisions et réalisé</x-slot>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                                <tr>
                                    <th class="px-3 py-2">Date cible</th>
                                    <th class="px-3 py-2 text-right">Âge (j)</th>
                                    <th class="px-3 py-2 text-right">Prévu</th>
                                    <th class="px-3 py-2 text-right">Souche</th>
                                    <th class="px-3 py-2 text-right">Réel</th>
                                    <th class="px-3 py-2 text-right">Écart</th>
                                    <th class="px-3 py-2 text-right">Confiance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->recentPredictions() as $p)
                                    @php $level = $p->accuracyLevel(); @endphp
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="px-3 py-2">{{ $p->target_date->format('d/m/Y') }}</td>
                                        <td class="px-3 py-2 text-right text-gray-500">{{ $p->target_age_days ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right font-medium">{{ number_format($p->predicted_value, 1, ',', ' ') }} {{ $type->unit() }}</td>
                                        <td class="px-3 py-2 text-right text-gray-500">{{ $p->standard_value !== null ? number_format($p->standard_value, 1, ',', ' ') : '—' }}</td>
                                        <td class="px-3 py-2 text-right">{{ $p->actual_value !== null ? number_format($p->actual_value, 1, ',', ' ') : '—' }}</td>
                                        <td class="px-3 py-2 text-right {{ $level === 'poor' ? 'text-danger-600' : ($level === 'fair' ? 'text-warning-600' : 'text-success-600') }}">
                                            {{ $p->variance_percent !== null ? number_format($p->variance_percent, 1, ',', ' ').' % · '.\App\Models\FlockPrediction::accuracyLabel($level) : '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-500">{{ number_format($p->confidence, 0) }} %</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>

                @php $latest = $horizon->get($type->value); @endphp
                @if ($latest && is_array($latest->factors))
                    <x-filament::section collapsible class="mt-6">
                        <x-slot name="heading">Comment cette prévision est calculée</x-slot>
                        <ul class="text-sm space-y-1">
                            @foreach ($latest->factors as $factor)
                                <li>
                                    <span class="font-medium">{{ $factor['label'] }}</span>
                                    @if (($factor['weight'] ?? 0) > 0)
                                        <span class="text-gray-500">(poids {{ number_format($factor['weight'] * 100, 0) }} %)</span>
                                    @endif
                                    : {{ $factor['value'] !== null ? number_format((float) $factor['value'], 1, ',', ' ') : '—' }}
                                    @if (! empty($factor['note']))
                                        <span class="text-gray-500">— {{ $factor['note'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-filament::section>
                @endif
            </div>

            <div>
                @livewire(\App\Filament\Farm\Pages\Forecasts\ForecastAccuracyWidget::class, ['organisationId' => $this->organisationId(), 'farmId' => $this->filters['farm_id'] ?? null, 'flockId' => $flock->id], key('forecast-accuracy-'.$flock->id))
            </div>
        </div>
    @endif
</x-filament-panels::page>
