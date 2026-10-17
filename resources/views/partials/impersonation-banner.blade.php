@php
    $impersonator = \App\Services\Impersonation::isActive() ? \App\Services\Impersonation::impersonator() : null;
    $current = \Filament\Facades\Filament::auth()->user();
@endphp

@if ($impersonator && $current)
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-info-300 bg-info-50 px-4 py-3 text-sm text-info-900 dark:border-info-700 dark:bg-info-950 dark:text-info-100">
        <div>
            <span class="font-semibold">Session de support</span>
            — vous voyez l'espace de <span class="font-medium">{{ $current->name }}</span> ({{ $current->email }}) en tant que {{ $impersonator->name }}.
            Toute saisie sera enregistrée à son nom, avec votre identifiant dans le journal d'audit.
        </div>
        <a href="{{ route('impersonation.leave') }}" class="rounded-md border border-info-400 px-3 py-1 font-medium hover:bg-info-100 dark:hover:bg-info-900">Revenir à l'administration</a>
    </div>
@endif
