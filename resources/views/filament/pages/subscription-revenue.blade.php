<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Période</x-slot>
        {{ $this->filtersForm }}
    </x-filament::section>

    @php
        $totals = $this->getTotals();
        $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-primary-600">{{ $fmt($totals['total']) }} XOF</div>
                <div class="text-xs text-gray-500">{{ $totals['count'] }} encaissement(s) sur la période</div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Par plan</div>
            @forelse ($totals['by_plan'] as $plan => $amount)
                <div class="flex justify-between text-sm"><span>{{ $plan }}</span><span class="font-medium">{{ $fmt($amount) }}</span></div>
            @empty
                <div class="text-sm text-gray-500">—</div>
            @endforelse
        </x-filament::section>
        <x-filament::section>
            <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Par mode de paiement</div>
            @forelse ($totals['by_method'] as $method => $amount)
                <div class="flex justify-between text-sm"><span>{{ $method }}</span><span class="font-medium">{{ $fmt($amount) }}</span></div>
            @empty
                <div class="text-sm text-gray-500">—</div>
            @endforelse
        </x-filament::section>
    </div>

    <div class="flex justify-end">
        <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exportExcel">Excel</x-filament::button>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
