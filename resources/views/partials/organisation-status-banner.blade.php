@php
    $tenant = \Filament\Facades\Filament::getTenant();
    $readOnly = $tenant instanceof \App\Models\Organisation && $tenant->status === \App\Enums\OrganisationStatus::ReadOnly;
@endphp

@if ($readOnly)
    <div class="mb-4 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200">
        <span class="font-semibold">Lecture seule</span>
        — vous pouvez consulter toutes vos données, mais aucune création, modification ni suppression n'est possible pour le moment
        @if ($tenant->status_changed_at)
            (depuis le {{ $tenant->status_changed_at->format('d/m/Y') }})
        @endif
        .
        @if ($tenant->status_reason)
            <span class="font-medium">Motif :</span> {{ $tenant->status_reason }}.
        @endif
        Contactez <a href="mailto:{{ \App\Services\PlatformSettings::supportEmail() }}" class="font-medium underline">{{ \App\Services\PlatformSettings::supportEmail() }}</a> pour rétablir la saisie.
    </div>
@endif
