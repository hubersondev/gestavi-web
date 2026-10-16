@php
    $tenant = \Filament\Facades\Filament::getTenant();
    $subscription = $tenant instanceof \App\Models\Organisation ? $tenant->currentSubscription() : null;
    $membership = $tenant instanceof \App\Models\Organisation ? \Filament\Facades\Filament::auth()->user()?->membershipIn($tenant) : null;
    $show = $subscription && $subscription->isExpiringSoon() && $membership?->canAccessAllFarms();
@endphp

@if ($show)
    @php
        $days = $subscription->daysRemaining();
        $critical = $days <= 1;
    @endphp
    <div class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $critical ? 'border-danger-300 bg-danger-50 text-danger-800 dark:border-danger-700 dark:bg-danger-950 dark:text-danger-200' : 'border-warning-300 bg-warning-50 text-warning-800 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-200' }}">
        <span class="font-semibold">
            {{ $subscription->isTrial() ? 'Fin de votre essai gratuit' : 'Échéance de votre abonnement' }}
            {{ $days <= 0 ? 'aujourd\'hui' : ($days === 1 ? 'demain' : "dans {$days} jours") }}
        </span>
        — {{ ucfirst($subscription->summaryLabel()) }} jusqu'au {{ $subscription->ends_at->format('d/m/Y') }}.
        Sans renouvellement, l'organisation reviendra au plan gratuit.
        <a href="{{ \App\Filament\Farm\Pages\UpgradePlan::getUrl(tenant: $tenant) }}" class="ml-1 font-medium underline">Voir les plans</a>
    </div>
@endif
