@php
    $tenant = \Filament\Facades\Filament::getTenant();
@endphp
@if ($tenant)
    <a href="{{ route('offline.page', $tenant) }}"
       title="Saisie terrain (mobile, disponible hors-ligne)"
       class="fi-btn fi-btn-size-sm fi-btn-color-gray inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5">
        <x-filament::icon icon="heroicon-o-device-phone-mobile" class="h-5 w-5" />
        <span class="hidden sm:inline">Saisie terrain</span>
    </a>
@endif
