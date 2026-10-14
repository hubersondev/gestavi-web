<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Importer un fichier</x-slot>
        <x-slot name="description">
            Le fichier est contrôlé en entier avant toute écriture : s'il contient une erreur, rien n'est importé et toutes les erreurs sont listées.
            Importez dans l'ordre : clients et fournisseurs, produits, stocks, employés, puis factures et dépenses.
        </x-slot>

        <form wire:submit="import" class="space-y-6">
            {{ $this->form }}

            <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray" wire:loading.attr="disabled">
                Importer
            </x-filament::button>
        </form>
    </x-filament::section>

    @if ($this->result !== null)
        @if ($this->result['errors'] === [])
            <x-filament::section icon="heroicon-o-check-circle" icon-color="success">
                <x-slot name="heading">Import terminé</x-slot>
                {{ $this->result['imported'] }} enregistrement(s) créé(s) à partir de {{ $this->result['rows'] }} ligne(s).
            </x-filament::section>
        @else
            <x-filament::section icon="heroicon-o-x-circle" icon-color="danger">
                <x-slot name="heading">Fichier refusé : {{ count($this->result['errors']) }} erreur(s)</x-slot>
                <x-slot name="description">Aucune ligne n'a été importée. Corrigez le fichier puis recommencez.</x-slot>
                <ul class="list-disc space-y-1 pl-5 text-sm text-danger-700 dark:text-danger-400">
                    @foreach ($this->result['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif
    @endif

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Colonnes attendues par type</x-slot>
        @foreach (app(\App\Services\Imports\ExcelImportService::class)->definitions() as $definition)
            <div class="mb-4">
                <h3 class="font-semibold">{{ $definition->label() }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $definition->description() }}</p>
                <p class="mt-1 text-sm">
                    @foreach ($definition->columns() as [$label, $required, $hint])
                        <span @class(['inline-block rounded px-1.5 py-0.5 mr-1 mb-1 text-xs', 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' => $required, 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => ! $required])
                              @if ($hint) title="{{ $hint }}" @endif>{{ $label }}{{ $required ? ' *' : '' }}</span>
                    @endforeach
                </p>
            </div>
        @endforeach
    </x-filament::section>
</x-filament-panels::page>
