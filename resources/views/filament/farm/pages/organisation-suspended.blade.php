<x-filament-panels::page>
    @php $suspended = $organisation && ! $organisation->status->allowsAccess(); @endphp

    <div class="rounded-xl border p-6 {{ $suspended ? 'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950' : 'border-success-300 bg-success-50 dark:border-success-700 dark:bg-success-950' }}">
        <div class="flex items-start gap-4">
            <x-filament::icon
                :icon="$suspended ? 'heroicon-o-lock-closed' : 'heroicon-o-check-circle'"
                @class(['h-8 w-8 flex-shrink-0', 'text-danger-500' => $suspended, 'text-success-500' => ! $suspended])
            />
            <div>
                @if ($suspended)
                    <h3 class="text-lg font-semibold text-danger-800 dark:text-danger-200">
                        L'accès à « {{ $organisation->name }} » est suspendu
                    </h3>
                    <p class="mt-1 text-sm text-danger-700 dark:text-danger-300">
                        La plateforme a suspendu cette organisation
                        @if ($organisation->status_changed_at)
                            le {{ $organisation->status_changed_at->format('d/m/Y') }}
                        @endif
                        . Vos données sont conservées mais ne sont plus consultables tant que la suspension n'est pas levée.
                    </p>
                    @if ($organisation->status_reason)
                        <p class="mt-3 text-sm text-danger-800 dark:text-danger-200">
                            <span class="font-medium">Motif :</span> {{ $organisation->status_reason }}
                        </p>
                    @endif
                    <p class="mt-4 text-sm text-danger-700 dark:text-danger-300">
                        Pour régulariser la situation, écrivez à
                        <a href="mailto:{{ \App\Services\PlatformSettings::supportEmail() }}" class="font-medium underline">{{ \App\Services\PlatformSettings::supportEmail() }}</a>.
                    </p>
                @else
                    <h3 class="text-lg font-semibold text-success-800 dark:text-success-200">Cette organisation n'est pas suspendue</h3>
                    <p class="mt-1 text-sm text-success-700 dark:text-success-300">
                        Vous pouvez revenir au <a href="{{ \Filament\Facades\Filament::getUrl($organisation) }}" class="font-medium underline">tableau de bord</a>.
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
