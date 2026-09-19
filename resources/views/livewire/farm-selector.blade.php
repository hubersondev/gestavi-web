<div>
    @if($hasMultipleFarms)
        <x-filament::input.wrapper
            :prefix-icon="\Filament\Support\Icons\Heroicon::OutlinedBuildingOffice2"
            class="max-w-[220px]"
        >
            <x-filament::input.select
                wire:change="selectFarm($event.target.value)"
            >
                <option value="">Toutes les fermes</option>
                @foreach($farms as $farm)
                    <option value="{{ $farm['id'] }}" @selected($selectedFarmId === $farm['id'])>
                        {{ $farm['name'] }}
                    </option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    @endif
</div>
