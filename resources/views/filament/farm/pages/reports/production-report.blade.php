<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtres</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php $kpis = $this->getKpis(); @endphp

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-primary-600">{{ $kpis['days'] }}</div>
                <div class="text-sm text-gray-500">Jours</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-danger-600">{{ number_format($kpis['mortality']) }}</div>
                <div class="text-sm text-gray-500">Mortalité totale</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-success-600">{{ number_format($kpis['avg_eggs'], 1) }}</div>
                <div class="text-sm text-gray-500">Oeufs moy./jour</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-info-600">{{ number_format($kpis['avg_weight'], 0) }} g</div>
                <div class="text-sm text-gray-500">Poids moyen</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-warning-600">{{ number_format($kpis['total_feed'], 1) }} kg</div>
                <div class="text-sm text-gray-500">Aliment total</div>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
