<x-filament-panels::page>
    <div class="space-y-8">
        {{-- Message principal --}}
        <div class="rounded-xl border border-warning-300 bg-warning-50 p-6 dark:border-warning-600 dark:bg-warning-950">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <x-filament::icon
                        icon="heroicon-o-exclamation-triangle"
                        class="h-8 w-8 text-warning-500"
                    />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-warning-800 dark:text-warning-200">
                        Fonctionnalité non disponible
                    </h3>
                    <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                        La fonctionnalité que vous essayez d'utiliser n'est pas incluse dans votre plan actuel.
                        Contactez votre administrateur pour passer à un plan supérieur.
                    </p>
                </div>
            </div>
        </div>

        {{-- Plan actuel --}}
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                Votre plan actuel
            </h2>
            <div class="mt-3 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                @if ($currentPlan)
                    @if ($currentSubscription)
                        <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $currentSubscription->isExpiringSoon() ? 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200' : 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200' }}">
                                {{ $currentSubscription->status->getLabel() }}
                            </span>
                            <span class="text-gray-600 dark:text-gray-300">
                                {{ ucfirst($currentSubscription->summaryLabel()) }} · {{ $currentSubscription->periodLabel() }}
                            </span>
                            <span class="font-medium {{ $currentSubscription->isExpiringSoon() ? 'text-warning-700 dark:text-warning-300' : 'text-gray-700 dark:text-gray-200' }}">
                                {{ $currentSubscription->daysRemaining() }} jour(s) restant(s)
                            </span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xl font-bold text-primary-600 dark:text-primary-400">
                                {{ $currentPlan->name }}
                            </p>
                            @if ($currentPlan->description)
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $currentPlan->description }}
                                </p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-2xl font-bold text-gray-950 dark:text-white">
                                {{ number_format($currentPlan->monthly_price, 0, ',', ' ') }} FCFA
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">/ mois</p>
                        </div>
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400">
                        Aucun plan souscrit.
                    </p>
                @endif
            </div>
        </div>

        {{-- Plans disponibles --}}
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                Plans disponibles
            </h2>
            <div class="mt-3 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($availablePlans as $plan)
                    <div @class([
                        'rounded-xl border p-6 shadow-sm transition',
                        'border-primary-500 bg-primary-50 ring-2 ring-primary-500 dark:border-primary-400 dark:bg-primary-950' => $currentPlan?->id === $plan->id,
                        'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' => $currentPlan?->id !== $plan->id,
                    ])>
                        <div class="mb-4">
                            <h3 class="text-lg font-bold text-gray-950 dark:text-white">
                                {{ $plan->name }}
                            </h3>
                            @if ($plan->description)
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $plan->description }}
                                </p>
                            @endif
                        </div>

                        <div class="mb-4">
                            <span class="text-3xl font-bold text-gray-950 dark:text-white">
                                {{ number_format($plan->monthly_price, 0, ',', ' ') }}
                            </span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">FCFA / mois</span>
                        </div>

                        <div class="mb-4 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                            <p>
                                <span class="font-medium">Fermes :</span>
                                {{ $plan->max_farms ?? 'Illimité' }}
                            </p>
                            <p>
                                <span class="font-medium">Utilisateurs :</span>
                                {{ $plan->max_users ?? 'Illimité' }}
                            </p>
                        </div>

                        @if ($plan->features)
                            <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Fonctionnalités incluses
                                </p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach (array_slice($plan->features, 0, 8) as $feature)
                                        @php
                                            $enumCase = \App\Enums\PlanFeature::tryFrom($feature);
                                        @endphp
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                            {{ $enumCase?->label() ?? $feature }}
                                        </span>
                                    @endforeach
                                    @if (count($plan->features) > 8)
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                            +{{ count($plan->features) - 8 }} autres
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($currentPlan?->id === $plan->id)
                            <div class="mt-4">
                                <span class="inline-flex items-center rounded-full bg-primary-100 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                                    Plan actuel
                                </span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Contact --}}
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-6 text-center dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Pour changer de plan, contactez votre administrateur ou écrivez-nous à
                <a href="mailto:{{ \App\Services\PlatformSettings::supportEmail() }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                    {{ \App\Services\PlatformSettings::supportEmail() }}
                </a>
            </p>
        </div>
    </div>
</x-filament-panels::page>
