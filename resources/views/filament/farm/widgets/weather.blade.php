<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-cloud" heading="Météo" description="Prévisions à 5 jours par ferme, comparées aux seuils de la phase de production des bandes.">

        @if (! $this->isConfigured())
            <p class="text-sm text-gray-500">
                La météo n'est pas encore activée sur ce serveur : la clé OpenWeatherMap (OPENWEATHERMAP_API_KEY) doit être renseignée dans la configuration.
            </p>
        @else
            @php $farms = $this->getFarmsData(); @endphp

            @if ($farms === [])
                <p class="text-sm text-gray-500">Aucune ferme dans ce périmètre.</p>
            @endif

            <div class="space-y-6">
                @foreach ($farms as $data)
                    @php
                        $farm = $data['farm'];
                        $current = $data['current'];
                        $upcoming = $data['upcoming'];
                    @endphp
                    <div>
                        <div class="flex items-baseline justify-between gap-3 mb-3">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $farm->name }}</h3>
                            @if ($data['fetched_at'])
                                <span class="text-xs text-gray-500">Mise à jour {{ $data['fetched_at']->diffForHumans() }}</span>
                            @endif
                        </div>

                        @if (! $data['located'])
                            <p class="text-sm text-gray-500">Renseignez les coordonnées GPS ou la ville de la ferme pour obtenir ses prévisions.</p>
                        @elseif ($upcoming->isEmpty())
                            <p class="text-sm text-gray-500">Pas encore de prévision : la prochaine récupération (toutes les 3 heures) les apportera.</p>
                        @else
                            @if ($current)
                                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 mb-4">
                                    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $current->emoji() }} {{ round((float) $current->temp_avg) }} °C</div>
                                    <div class="text-sm text-gray-500 capitalize">{{ $current->description ?? '' }}</div>
                                    <div class="text-sm text-gray-500">{{ $current->temperatureRange() }}</div>
                                    <div class="text-sm text-gray-500">💧 {{ round((float) $current->humidity) }} %</div>
                                    <div class="text-sm text-gray-500">💨 {{ $current->windKmh() ?? '—' }} km/h</div>
                                    @if ((float) $current->rain_mm > 0)
                                        <div class="text-sm text-info-600">🌧️ {{ number_format((float) $current->rain_mm, 1, ',', ' ') }} mm</div>
                                    @endif
                                </div>
                            @endif

                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                @foreach ($upcoming as $day)
                                    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-center">
                                        <div class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ ucfirst($day->forecast_date->translatedFormat('D d/m')) }}</div>
                                        <div class="text-2xl my-1">{{ $day->emoji() }}</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-300">{{ $day->temperatureRange() }}</div>
                                        <div class="text-xs text-gray-500">💧 {{ round((float) $day->humidity) }} %</div>
                                        @if ((float) $day->rain_mm > 0)
                                            <div class="text-xs text-info-600">🌧️ {{ number_format((float) $day->rain_mm, 1, ',', ' ') }} mm</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @if ($data['anomalies'] !== [])
                                <div class="mt-4 space-y-1">
                                    @foreach (collect($data['anomalies'])->unique(fn ($a) => $a['flock']->id.'|'.$a['kind'].'|'.$a['date']->toDateString()) as $anomaly)
                                        <div class="rounded-md px-3 py-2 text-sm {{ $anomaly['critical'] ? 'bg-danger-50 text-danger-700 dark:bg-danger-950 dark:text-danger-300' : 'bg-warning-50 text-warning-700 dark:bg-warning-950 dark:text-warning-300' }}">
                                            <span class="font-medium">{{ $anomaly['flock']->code }}</span> — {{ $anomaly['message'] }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($data['insulation'] !== [])
                                <div class="mt-4 overflow-x-auto">
                                    <table class="w-full text-sm text-left">
                                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                                            <tr>
                                                <th class="px-3 py-2">Bande</th>
                                                <th class="px-3 py-2">Bâtiment</th>
                                                <th class="px-3 py-2 text-right">T° intérieure (hier)</th>
                                                <th class="px-3 py-2 text-right">T° extérieure</th>
                                                <th class="px-3 py-2 text-right">Écart</th>
                                                <th class="px-3 py-2">Isolation</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($data['insulation'] as $row)
                                                <tr class="border-b dark:border-gray-700">
                                                    <td class="px-3 py-2 font-medium">{{ $row['flock']->code }}</td>
                                                    <td class="px-3 py-2 text-gray-500">{{ $row['building'] ?? '—' }}</td>
                                                    <td class="px-3 py-2 text-right">{{ number_format($row['inside'], 1, ',', ' ') }} °C</td>
                                                    <td class="px-3 py-2 text-right">{{ number_format($row['outside'], 1, ',', ' ') }} °C</td>
                                                    <td class="px-3 py-2 text-right">{{ $row['delta'] > 0 ? '+' : '' }}{{ number_format($row['delta'], 1, ',', ' ') }} °C</td>
                                                    <td class="px-3 py-2 font-semibold {{ $row['score'] === 'bonne' ? 'text-success-600' : ($row['score'] === 'moyenne' ? 'text-warning-600' : 'text-danger-600') }}">{{ ucfirst($row['score']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
