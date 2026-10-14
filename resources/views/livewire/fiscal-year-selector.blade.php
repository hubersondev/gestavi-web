<div>
    @if($fiscalYears !== [])
        <x-filament::input.wrapper
            :prefix-icon="\Filament\Support\Icons\Heroicon::OutlinedCalendarDays"
            class="max-w-[200px]"
        >
            <x-filament::input.select
                wire:change="selectFiscalYear($event.target.value)"
                title="Exercice comptable actif"
            >
                <option value="" @selected($selectedFiscalYearId === null)>Tous les exercices</option>
                @foreach($fiscalYears as $fiscalYear)
                    <option value="{{ $fiscalYear['id'] }}" @selected($selectedFiscalYearId === $fiscalYear['id'])>
                        Exercice {{ $fiscalYear['label'] }}
                    </option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    @endif
</div>
