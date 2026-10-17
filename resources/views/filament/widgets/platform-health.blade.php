<x-filament-widgets::widget>
    @php
        $health = $this->getHealth();
        $summary = $health['summary'];
        $tone = $summary['fail'] > 0 ? 'danger' : ($summary['warn'] > 0 ? 'warning' : 'success');
    @endphp
    <x-filament::section icon="heroicon-o-heart" heading="État d'exploitation"
        description="{{ $summary['fail'] }} en échec · {{ $summary['warn'] }} avertissement(s) · {{ $summary['ok'] }} OK. Le diagnostic complet reste « php artisan gestavi:doctor » sur le serveur.">
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach ($health['checks'] as $check)
                @php
                    $dot = match ($check['status']) { 'ok' => 'bg-success-500', 'warn' => 'bg-warning-500', default => 'bg-danger-500' };
                @endphp
                <li class="flex items-start gap-3 py-2 text-sm">
                    <span class="mt-1.5 inline-block h-2.5 w-2.5 flex-shrink-0 rounded-full {{ $dot }}"></span>
                    <span class="w-48 flex-shrink-0 font-medium text-gray-900 dark:text-gray-100">{{ $check['check'] }}</span>
                    <span class="text-gray-600 dark:text-gray-300">{{ $check['detail'] }}</span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
