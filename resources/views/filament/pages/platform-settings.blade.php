<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check">Enregistrer</x-filament::button>
        </div>
    </form>

    <x-filament::section icon="heroicon-o-link" heading="Intégrations" description="Réglées dans l'environnement du serveur (.env), modifiables uniquement par un déploiement. Les secrets ne sont jamais affichés.">
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach ($this->getIntegrations() as $integration)
                <li class="flex items-start gap-3 py-2 text-sm">
                    <span class="mt-1.5 inline-block h-2.5 w-2.5 flex-shrink-0 rounded-full {{ $integration['configured'] ? 'bg-success-500' : 'bg-warning-500' }}"></span>
                    <span class="w-64 flex-shrink-0 font-medium text-gray-900 dark:text-gray-100">{{ $integration['label'] }}</span>
                    <span class="text-gray-600 dark:text-gray-300">{{ $integration['configured'] ? 'Configuré' : 'Non configuré' }} · {{ $integration['detail'] }}</span>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-panels::page>
