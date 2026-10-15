<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Enregistrer
            </x-filament::button>
            <span class="text-sm text-gray-500 dark:text-gray-400">La page se recharge pour appliquer le thème.</span>
        </div>
    </form>
</x-filament-panels::page>
