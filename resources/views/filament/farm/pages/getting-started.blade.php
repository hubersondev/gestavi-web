<x-filament-panels::page>
    @php
        $steps = $this->getSteps();
        $required = collect($steps)->reject(fn ($s) => $s['optional']);
        $doneCount = $required->where('done', true)->count();
    @endphp

    <x-filament::section>
        <x-slot name="heading">Mise en route de {{ filament()->getTenant()?->name }}</x-slot>
        <x-slot name="description">
            Suivez les étapes dans l'ordre. Les étapes facultatives dépendent de votre activité.
            {{ $doneCount }} étape(s) obligatoire(s) sur {{ $required->count() }} terminée(s).
        </x-slot>

        <ol class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($steps as $index => $step)
                <li class="flex items-start gap-4 py-4">
                    <div @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                        'bg-success-600 text-white' => $step['done'],
                        'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' => ! $step['done'],
                    ])>
                        @if ($step['done'])
                            <x-filament::icon icon="heroicon-m-check" class="h-5 w-5" />
                        @else
                            {{ $index + 1 }}
                        @endif
                    </div>

                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class(['font-semibold', 'text-gray-500 line-through' => $step['done']])>{{ $step['title'] }}</span>
                            @if ($step['optional'])
                                <x-filament::badge color="gray" size="sm">Facultatif</x-filament::badge>
                            @endif
                            @if ($step['done'])
                                <x-filament::badge color="success" size="sm">Fait</x-filament::badge>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $step['description'] }}</p>
                    </div>

                    @if ($step['url'])
                        <x-filament::button tag="a" :href="$step['url']" size="sm" :color="$step['done'] ? 'gray' : 'primary'" :outlined="$step['done']">
                            {{ $step['action'] }}
                        </x-filament::button>
                    @endif
                </li>
            @endforeach
        </ol>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Ensuite, au quotidien</x-slot>
        <ul class="list-disc space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
            <li>Relevés quotidiens depuis la page terrain (mortalité, œufs, aliment), même sans réseau.</li>
            <li>Ventes et encaissements, dépenses et paiements : chaque pièce prend la date de l'exercice actif.</li>
            <li>Paie mensuelle : génération, validation, paiement, bulletins PDF.</li>
            <li>En fin d'exercice : contrôle des rapports, vérification du ledger, puis clôture. Les à-nouveaux sont générés automatiquement.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
