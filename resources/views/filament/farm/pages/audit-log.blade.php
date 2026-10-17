<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Traçabilité des enregistrements sensibles</x-slot>
        <x-slot name="description">
            Chaque création, modification (avec les valeurs avant et après) et suppression est enregistrée avec son auteur et l'heure exacte :
            trésorerie, ventes et paiements, dépenses, stocks, bandes et relevés, paie, employés, écritures comptables. Ces entrées ne peuvent être ni modifiées ni effacées depuis l'application.
        </x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
