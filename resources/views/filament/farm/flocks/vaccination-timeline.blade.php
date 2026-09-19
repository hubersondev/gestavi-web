@php
    $flock        = $getRecord();
    $vaccinations = $flock->vaccinations()->with('healthProgram')->orderBy('scheduled_date')->get();

    $done    = $vaccinations->filter(fn ($v) => $v->isDone())->count();
    $overdue = $vaccinations->filter(fn ($v) => $v->isOverdue())->count();
    $delayed = $vaccinations->filter(fn ($v) => $v->isDelayed())->count();
    $pending = $vaccinations->filter(fn ($v) => $v->isPending() && ! $v->isOverdue())->count();
    $skipped = $vaccinations->filter(fn ($v) => $v->isSkipped())->count();

    $typeLabels = [
        'vaccin'          => 'Vaccin',
        'vermifuge'       => 'Vermifuge',
        'anticoccidien'   => 'Anticoccidien',
        'vitamine'        => 'Vitamine',
        'antibiotique'    => 'Antibiotique',
        'antiparasitaire' => 'Antiparasitaire',
        'desinfectant'    => 'Désinfectant',
        'autre'           => 'Autre',
    ];
@endphp

<div class="space-y-4 py-2">

    {{-- Résumé --}}
    <div class="flex flex-wrap gap-3">
        @if($done > 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-300">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
                {{ $done }} effectuée{{ $done > 1 ? 's' : '' }}
            </span>
        @endif
        @if($overdue > 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-800 dark:bg-red-900/30 dark:text-red-300">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                {{ $overdue }} en retard
            </span>
        @endif
        @if($delayed > 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-orange-100 px-3 py-1 text-sm font-medium text-orange-800 dark:bg-orange-900/30 dark:text-orange-300">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H3.989a.75.75 0 0 0-.75.75v4.242a.75.75 0 0 0 1.5 0v-2.43l.31.31a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0 .219-.53V2.929a.75.75 0 0 0-1.5 0V5.36l-.31-.31A7 7 0 0 0 3.239 8.188a.75.75 0 1 0 1.448.389A5.5 5.5 0 0 1 13.89 6.11l.311.31h-2.432a.75.75 0 0 0 0 1.5h4.243a.75.75 0 0 0 .53-.219Z" clip-rule="evenodd"/></svg>
                {{ $delayed }} reportée{{ $delayed > 1 ? 's' : '' }}
            </span>
        @endif
        @if($pending > 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-yellow-100 px-3 py-1 text-sm font-medium text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z" clip-rule="evenodd"/></svg>
                {{ $pending }} planifiée{{ $pending > 1 ? 's' : '' }}
            </span>
        @endif
        @if($skipped > 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd"/></svg>
                {{ $skipped }} ignorée{{ $skipped > 1 ? 's' : '' }}
            </span>
        @endif

        @if($vaccinations->isEmpty())
            <span class="text-sm text-gray-500 dark:text-gray-400">Aucune vaccination planifiée pour cette bande.</span>
        @endif
    </div>

    {{-- Timeline --}}
    @if($vaccinations->isNotEmpty())
        <div class="relative">
            <div class="absolute bottom-0 left-5 top-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>

            <div class="space-y-3">
                @foreach($vaccinations as $vaccination)
                    @php
                        $isOverdue = $vaccination->isOverdue();
                        $isDone    = $vaccination->isDone();
                        $isDelayed = $vaccination->isDelayed();
                        $isPending = $vaccination->isPending();
                        $isSkipped = $vaccination->isSkipped();

                        $dayLabel = null;
                        if ($vaccination->age_at_treatment !== null) {
                            $dayLabel = 'J' . $vaccination->age_at_treatment;
                        } elseif ($flock->start_date && $vaccination->scheduled_date) {
                            $days     = (int) $flock->start_date->diffInDays($vaccination->scheduled_date, false);
                            $dayLabel = 'J' . ($flock->age_at_arrival + $days);
                        }

                        $viewUrl = \App\Filament\Farm\Resources\Vaccinations\VaccinationResource::getUrl('view', ['record' => $vaccination->getKey()]);
                    @endphp

                    <div class="relative flex gap-4">
                        {{-- Dot --}}
                        <div class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white ring-2 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                            @if($isDone)
                                <div class="h-3 w-3 rounded-full bg-green-500"></div>
                            @elseif($isOverdue)
                                <div class="h-3 w-3 rounded-full bg-red-500"></div>
                            @elseif($isDelayed)
                                <div class="h-3 w-3 rounded-full bg-orange-500"></div>
                            @elseif($isPending)
                                <div class="h-3 w-3 rounded-full bg-yellow-400"></div>
                            @else
                                <div class="h-3 w-3 rounded-full bg-gray-400"></div>
                            @endif
                        </div>

                        {{-- Carte --}}
                        <a href="{{ $viewUrl }}" class="flex-1 rounded-lg border p-3 transition-shadow hover:shadow-sm
                            @if($isDone) border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20
                            @elseif($isOverdue) border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20
                            @elseif($isDelayed) border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-900/20
                            @elseif($isPending) border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900/20
                            @else border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50
                            @endif">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $vaccination->treatment_name }}
                                    </span>
                                    @if($dayLabel)
                                        <span class="rounded bg-gray-200 px-1.5 py-0.5 font-mono text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                            {{ $dayLabel }}
                                        </span>
                                    @endif
                                    @if($vaccination->treatment_type)
                                        <span class="rounded-full bg-white/60 px-2 py-0.5 text-xs text-gray-500 ring-1 ring-gray-200 dark:bg-gray-800/60 dark:text-gray-400 dark:ring-gray-600">
                                            {{ $typeLabels[$vaccination->treatment_type] ?? $vaccination->treatment_type }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Statut --}}
                                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium
                                    @if($isDone) bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300
                                    @elseif($isOverdue) bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300
                                    @elseif($isDelayed) bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300
                                    @elseif($isPending) bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300
                                    @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400
                                    @endif">
                                    @if($isOverdue) En retard @else {{ $vaccination->status->label() }} @endif
                                </span>
                            </div>

                            <div class="mt-1.5 flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                                <span>
                                    Prévue : <span class="font-medium text-gray-700 dark:text-gray-300">{{ $vaccination->scheduled_date?->format('d/m/Y') ?? '—' }}</span>
                                </span>
                                @if($isDone && $vaccination->administered_date)
                                    <span>
                                        Réalisée : <span class="font-medium text-green-700 dark:text-green-400">{{ $vaccination->administered_date->format('d/m/Y') }}</span>
                                    </span>
                                @endif
                                @if($isOverdue)
                                    <span class="font-medium text-red-600 dark:text-red-400">
                                        {{ $vaccination->getDaysOverdue() }} j de retard
                                    </span>
                                @endif
                                @if($vaccination->dosage)
                                    <span>Dose : {{ $vaccination->dosage }}</span>
                                @endif
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
