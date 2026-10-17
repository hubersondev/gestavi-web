<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Toutes les organisations</x-slot>
        <x-slot name="description">Créations, modifications et suppressions des enregistrements sensibles, avec leur auteur, pour le support. Filtrez par structure pour instruire une demande.</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
