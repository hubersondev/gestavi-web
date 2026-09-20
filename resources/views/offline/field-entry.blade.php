<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#15803d">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="GESTAVI Terrain">
    <title>Saisie terrain — {{ $organisation->name }}</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.svg') }}">
    @vite(['resources/css/offline.css', 'resources/js/offline/field-entry.js'])
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
<div id="field-entry" class="mx-auto max-w-md px-4 pb-24 pt-4 space-y-6"
     data-organisation="{{ $organisation->slug }}"
     data-organisation-name="{{ $organisation->name }}"
     data-csrf="{{ csrf_token() }}"
     data-can-record="{{ $canRecord ? 1 : 0 }}"
     data-can-vaccinate="{{ $canVaccinate ? 1 : 0 }}"
     data-urls="{{ json_encode($urls) }}">

    <header class="space-y-2">
        <div class="flex items-center justify-between gap-2">
            <div>
                <h1 class="text-2xl font-bold text-green-800">Saisie terrain</h1>
                <p class="text-sm text-gray-500">{{ $organisation->name }} · {{ $user->name }}</p>
            </div>
            <a href="{{ url('/farm/'.$organisation->slug) }}" class="text-sm text-green-700 underline">Retour au panel</a>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span data-network class="field-badge bg-gray-100 text-gray-700">…</span>
            <span data-pending class="field-badge bg-gray-100 text-gray-700">…</span>
            <button type="button" data-sync-now class="ml-auto text-sm text-green-700 underline">Synchroniser</button>
            <button type="button" data-refresh class="text-sm text-green-700 underline">Actualiser</button>
        </div>
    </header>

    <section class="space-y-3">
        <h2 class="text-lg font-semibold">Mes bandes</h2>
        <div data-flock-list class="space-y-2">
            <p class="text-gray-500 text-center py-6">Chargement…</p>
        </div>
    </section>

    @if ($canRecord)
        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Relevé du jour</h2>
            <form data-record-form class="field-card space-y-4" autocomplete="off">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bande</label>
                    <select name="flock_id" data-form-flock class="field-input" required></select>
                    <p data-flock-hint class="text-xs text-gray-500 mt-1"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date du relevé</label>
                    <input type="date" name="record_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="field-input" required>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Morts</label>
                        <input type="number" name="mortality" inputmode="numeric" min="0" placeholder="0" class="field-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Réformes</label>
                        <input type="number" name="culls" inputmode="numeric" min="0" placeholder="0" class="field-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Aliment (kg)</label>
                        <input type="text" name="feed_consumed_kg" inputmode="decimal" placeholder="0" class="field-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Eau (L)</label>
                        <input type="text" name="water_consumed_l" inputmode="decimal" placeholder="0" class="field-input">
                    </div>
                </div>

                <div data-eggs-block class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Œufs collectés</label>
                        <input type="number" name="eggs_collected" inputmode="numeric" min="0" placeholder="0" class="field-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Œufs cassés</label>
                        <input type="number" name="eggs_broken" inputmode="numeric" min="0" placeholder="0" class="field-input">
                    </div>
                </div>

                <div data-weight-block class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Poids moyen (g)</label>
                        <input type="text" name="average_weight_g" inputmode="decimal" placeholder="—" class="field-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sujets pesés</label>
                        <input type="number" name="birds_weighed" inputmode="numeric" min="0" placeholder="—" class="field-input">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">T° min (°C)</label>
                        <input type="text" name="temperature_min" inputmode="decimal" placeholder="—" class="field-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">T° max (°C)</label>
                        <input type="text" name="temperature_max" inputmode="decimal" placeholder="—" class="field-input">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="field-input" placeholder="Observations…"></textarea>
                </div>

                <button type="submit" class="field-btn-primary">Enregistrer le relevé</button>
            </form>
        </section>
    @endif

    @if ($canVaccinate)
        <section class="space-y-3">
            <h2 class="text-lg font-semibold">Vaccinations à faire</h2>
            <div data-vaccination-list class="space-y-2">
                <p class="text-gray-500 text-center py-4">Chargement…</p>
            </div>
        </section>
    @endif

    <section class="space-y-3">
        <h2 class="text-lg font-semibold">Historique sur cet appareil</h2>
        <div data-history class="space-y-2"></div>
    </section>

    <div data-toast class="hidden"></div>
</div>
</body>
</html>
