import { allEntries, cacheGet, cacheSet, enqueue, pendingEntries, purgeSynced, updateEntry } from './db.js';

/**
 * Page « Saisie terrain » : saisie du relevé du jour et validation des vaccinations,
 * en ligne comme hors-ligne. Toute saisie passe par la file IndexedDB puis est
 * synchronisée dès que le réseau revient.
 */
const root = document.getElementById('field-entry');

if (root) {
    boot(root).catch((error) => console.error('Saisie terrain : erreur au démarrage', error));
}

async function boot(root) {
    const config = {
        organisation: root.dataset.organisation,
        organisationName: root.dataset.organisationName,
        urls: JSON.parse(root.dataset.urls),
        csrf: root.dataset.csrf,
        canRecord: root.dataset.canRecord === '1',
        canVaccinate: root.dataset.canVaccinate === '1',
    };

    try {
        localStorage.setItem('gestavi.last_terrain_url', config.urls.page);
    } catch (error) {
        // Stockage indisponible (navigation privée) : sans conséquence
    }

    registerServiceWorker();

    const state = {
        flocks: [],
        vaccinations: [],
        selectedFlockId: null,
        online: navigator.onLine,
    };

    const ui = {
        network: root.querySelector('[data-network]'),
        pending: root.querySelector('[data-pending]'),
        flockList: root.querySelector('[data-flock-list]'),
        form: root.querySelector('[data-record-form]'),
        formFlock: root.querySelector('[data-form-flock]'),
        vaccinationList: root.querySelector('[data-vaccination-list]'),
        history: root.querySelector('[data-history]'),
        refresh: root.querySelector('[data-refresh]'),
        syncNow: root.querySelector('[data-sync-now]'),
        toast: root.querySelector('[data-toast]'),
        eggsBlock: root.querySelector('[data-eggs-block]'),
        weightBlock: root.querySelector('[data-weight-block]'),
    };

    const setNetwork = () => {
        state.online = navigator.onLine;
        ui.network.textContent = state.online ? 'En ligne' : 'Hors-ligne : les saisies seront envoyées au retour du réseau';
        ui.network.className = 'field-badge ' + (state.online ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800');
    };

    window.addEventListener('online', async () => {
        setNetwork();
        await sync();
        await loadReferenceData(true);
    });
    window.addEventListener('offline', setNetwork);
    setNetwork();

    ui.refresh?.addEventListener('click', () => loadReferenceData(true));
    ui.syncNow?.addEventListener('click', () => sync(true));

    ui.form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        await submitRecord();
    });

    ui.vaccinationList?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-vaccination-done]');

        if (button) {
            await submitVaccination(Number(button.dataset.vaccinationDone));
        }
    });

    ui.flockList?.addEventListener('click', (event) => {
        const card = event.target.closest('[data-flock-id]');

        if (card) {
            selectFlock(Number(card.dataset.flockId));
        }
    });

    await loadReferenceData(false);
    await renderHistory();
    await sync();
    await purgeSynced(config.organisation);

    // ────────────────────────────────────────────────────────────────────────

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf } : {}),
                ...(options.headers || {}),
            },
            ...options,
        });

        if (response.status === 419) {
            // Session régénérée pendant la coupure : on recharge le jeton et on rejoue une fois
            const token = await fetch(config.urls.csrf, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then((r) => r.json());
            config.csrf = token.token;

            if (!options._retried) {
                return fetchJson(url, { ...options, _retried: true });
            }
        }

        if (!response.ok) {
            const error = new Error(`HTTP ${response.status}`);
            error.status = response.status;
            error.body = await response.json().catch(() => null);
            throw error;
        }

        return response.json();
    }

    async function loadReferenceData(force) {
        const flockKey = `${config.organisation}:flocks`;
        const vaccinationKey = `${config.organisation}:vaccinations`;

        if (state.online) {
            try {
                const [flocks, vaccinations] = await Promise.all([
                    fetchJson(config.urls.flocks),
                    config.canVaccinate ? fetchJson(config.urls.vaccinations) : Promise.resolve({ vaccinations: [] }),
                ]);

                state.flocks = flocks.flocks;
                state.vaccinations = vaccinations.vaccinations;
                await cacheSet(flockKey, state.flocks);
                await cacheSet(vaccinationKey, state.vaccinations);
                render();

                return;
            } catch (error) {
                if (force) {
                    toast('Impossible de rafraîchir les données, affichage de la dernière version connue.', 'warning');
                }
            }
        }

        state.flocks = (await cacheGet(flockKey)) || [];
        state.vaccinations = (await cacheGet(vaccinationKey)) || [];
        render();
    }

    function render() {
        renderFlocks();
        renderVaccinations();
    }

    function renderFlocks() {
        if (!ui.flockList) {
            return;
        }

        if (state.flocks.length === 0) {
            ui.flockList.innerHTML = '<p class="text-gray-500 text-center py-6">Aucune bande active accessible.</p>';
            ui.formFlock.innerHTML = '';

            return;
        }

        ui.flockList.innerHTML = state.flocks.map((flock) => `
            <button type="button" data-flock-id="${flock.id}"
                class="field-card w-full text-left flex items-center justify-between gap-3 ${state.selectedFlockId === flock.id ? 'ring-2 ring-green-600' : ''}">
                <span>
                    <span class="block font-bold text-gray-900">${escapeHtml(flock.code)}${flock.name ? ' — ' + escapeHtml(flock.name) : ''}</span>
                    <span class="block text-sm text-gray-500">${escapeHtml(flock.farm || '')} · J${flock.age_days ?? '?'} · ${flock.quantity_current.toLocaleString('fr-FR')} sujets</span>
                    ${flock.last_record_date ? `<span class="block text-xs text-gray-400">Dernier relevé : ${formatDate(flock.last_record_date)}</span>` : '<span class="block text-xs text-amber-600">Aucun relevé</span>'}
                </span>
                <span class="text-green-700 text-2xl">›</span>
            </button>
        `).join('');

        ui.formFlock.innerHTML = state.flocks.map((flock) => `<option value="${flock.id}" ${state.selectedFlockId === flock.id ? 'selected' : ''}>${escapeHtml(flock.code)} — ${escapeHtml(flock.farm || '')}</option>`).join('');

        if (!state.selectedFlockId && state.flocks.length > 0) {
            selectFlock(state.flocks[0].id);
        }
    }

    function selectFlock(flockId) {
        state.selectedFlockId = flockId;
        const flock = state.flocks.find((f) => f.id === flockId);

        if (ui.formFlock) {
            ui.formFlock.value = String(flockId);
        }

        if (flock) {
            ui.eggsBlock?.classList.toggle('hidden', !flock.tracks_eggs);
            ui.weightBlock?.classList.toggle('hidden', !flock.tracks_weight);
            ui.form?.querySelector('[data-flock-hint]')?.replaceChildren(document.createTextNode(`Effectif présent : ${flock.quantity_current.toLocaleString('fr-FR')} sujets`));
        }

        renderFlocks();
        ui.form?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function renderVaccinations() {
        if (!ui.vaccinationList) {
            return;
        }

        if (state.vaccinations.length === 0) {
            ui.vaccinationList.innerHTML = '<p class="text-gray-500 text-center py-4">Aucune vaccination prévue dans les 14 jours.</p>';

            return;
        }

        ui.vaccinationList.innerHTML = state.vaccinations.map((v) => `
            <div class="field-card flex items-center justify-between gap-3">
                <span>
                    <span class="block font-semibold text-gray-900">${escapeHtml(v.treatment_name)}</span>
                    <span class="block text-sm text-gray-500">Bande ${escapeHtml(v.flock_code || '')} · prévue le ${formatDate(v.scheduled_date)}</span>
                    ${v.overdue ? '<span class="field-badge bg-red-100 text-red-800 mt-1">En retard</span>' : ''}
                </span>
                <button type="button" data-vaccination-done="${v.id}" class="rounded-xl bg-green-700 text-white px-4 py-3 font-semibold">Faite</button>
            </div>
        `).join('');
    }

    async function submitRecord() {
        const formData = new FormData(ui.form);
        const payload = { flock_id: Number(formData.get('flock_id')), record_date: formData.get('record_date') };

        for (const field of ['mortality', 'culls', 'eggs_collected', 'eggs_broken', 'birds_weighed']) {
            const value = formData.get(field);
            if (value !== null && value !== '') payload[field] = Number(value);
        }

        for (const field of ['feed_consumed_kg', 'water_consumed_l', 'average_weight_g', 'temperature_min', 'temperature_max', 'humidity_percent']) {
            const value = formData.get(field);
            if (value !== null && value !== '') payload[field] = Number(String(value).replace(',', '.'));
        }

        const notes = formData.get('notes');
        if (notes) payload.notes = notes;

        const flock = state.flocks.find((f) => f.id === payload.flock_id);
        const losses = (payload.mortality || 0) + (payload.culls || 0);

        if (flock && losses > flock.quantity_current) {
            toast(`Les pertes (${losses}) dépassent l'effectif présent (${flock.quantity_current}).`, 'error');

            return;
        }

        await enqueue(config.organisation, 'daily_record', payload);
        ui.form.reset();
        ui.form.querySelector('[name=record_date]').value = today();
        ui.formFlock.value = String(payload.flock_id);

        // Mise à jour optimiste de l'effectif affiché
        if (flock) {
            flock.quantity_current = Math.max(0, flock.quantity_current - losses);
            flock.last_record_date = payload.record_date;
            await cacheSet(`${config.organisation}:flocks`, state.flocks);
            render();
        }

        toast(state.online ? 'Relevé enregistré, synchronisation…' : 'Relevé enregistré hors-ligne, il sera envoyé au retour du réseau.', 'success');
        await renderHistory();
        await sync();
    }

    async function submitVaccination(vaccinationId) {
        const vaccination = state.vaccinations.find((v) => v.id === vaccinationId);

        if (!vaccination) {
            return;
        }

        await enqueue(config.organisation, 'vaccination_done', {
            vaccination_id: vaccinationId,
            administered_date: today(),
            birds_treated: vaccination.birds || null,
        });

        state.vaccinations = state.vaccinations.filter((v) => v.id !== vaccinationId);
        await cacheSet(`${config.organisation}:vaccinations`, state.vaccinations);
        renderVaccinations();
        toast(`Vaccination « ${vaccination.treatment_name} » marquée effectuée.`, 'success');
        await renderHistory();
        await sync();
    }

    let syncing = false;

    async function sync(manual = false) {
        if (syncing) {
            return;
        }

        const entries = await pendingEntries(config.organisation);
        updatePendingBadge(entries.length);

        if (entries.length === 0) {
            if (manual) toast('Rien à synchroniser.', 'info');

            return;
        }

        if (!navigator.onLine) {
            if (manual) toast('Pas de réseau : la synchronisation reprendra automatiquement.', 'warning');

            return;
        }

        syncing = true;

        try {
            const response = await fetchJson(config.urls.sync, {
                method: 'POST',
                body: JSON.stringify({
                    entries: entries.map(({ client_uuid, type, payload, recorded_at }) => ({ client_uuid, type, payload, recorded_at })),
                }),
            });

            let created = 0;
            let rejected = 0;

            for (const result of response.results) {
                await updateEntry(result.client_uuid, { status: result.status, message: result.message, synced_at: response.synced_at });
                if (result.status === 'created') created++;
                if (result.status === 'rejected') rejected++;
            }

            if (created > 0 || rejected > 0 || manual) {
                toast(`${created} saisie(s) synchronisée(s)${rejected > 0 ? `, ${rejected} refusée(s) — voir l'historique` : ''}.`, rejected > 0 ? 'warning' : 'success');
            }

            await loadReferenceData(false);
        } catch (error) {
            for (const entry of entries) {
                await updateEntry(entry.client_uuid, { attempts: (entry.attempts || 0) + 1 });
            }

            if (error.status === 403) {
                toast('Accès refusé : reconnectez-vous puis réessayez.', 'error');
            } else if (manual) {
                toast('Synchronisation impossible pour le moment, nouvel essai au retour du réseau.', 'warning');
            }
        } finally {
            syncing = false;
            await renderHistory();
            updatePendingBadge((await pendingEntries(config.organisation)).length);
        }
    }

    async function renderHistory() {
        if (!ui.history) {
            return;
        }

        const entries = (await allEntries(config.organisation)).slice(0, 30);

        if (entries.length === 0) {
            ui.history.innerHTML = '<p class="text-gray-500 text-center py-4">Aucune saisie sur cet appareil.</p>';

            return;
        }

        const labels = { pending: ['En attente', 'bg-amber-100 text-amber-800'], synced: ['Synchronisé', 'bg-green-100 text-green-800'], created: ['Synchronisé', 'bg-green-100 text-green-800'], duplicate: ['Doublon ignoré', 'bg-gray-200 text-gray-700'], rejected: ['Refusé', 'bg-red-100 text-red-800'] };

        ui.history.innerHTML = entries.map((entry) => {
            const [label, cls] = labels[entry.status] || labels.pending;
            const flock = state.flocks.find((f) => f.id === entry.payload.flock_id);
            const title = entry.type === 'daily_record'
                ? `Relevé ${formatDate(entry.payload.record_date)} · ${flock ? escapeHtml(flock.code) : 'bande #' + entry.payload.flock_id}`
                : `Vaccination #${entry.payload.vaccination_id}`;

            return `
                <div class="field-card">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-gray-900">${title}</span>
                        <span class="field-badge ${cls}">${label}</span>
                    </div>
                    ${entry.message ? `<p class="text-sm text-gray-600 mt-1">${escapeHtml(entry.message)}</p>` : ''}
                    <p class="text-xs text-gray-400 mt-1">Saisi le ${new Date(entry.recorded_at).toLocaleString('fr-FR')}</p>
                </div>
            `;
        }).join('');
    }

    function updatePendingBadge(count) {
        if (ui.pending) {
            ui.pending.textContent = count > 0 ? `${count} en attente` : 'Tout est synchronisé';
            ui.pending.className = 'field-badge ' + (count > 0 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800');
        }
    }

    let toastTimer;

    function toast(message, level = 'info') {
        if (!ui.toast) return;

        const colors = { info: 'bg-gray-800', success: 'bg-green-700', warning: 'bg-amber-600', error: 'bg-red-700' };
        ui.toast.textContent = message;
        ui.toast.className = `fixed bottom-4 left-4 right-4 z-50 rounded-xl px-4 py-3 text-white shadow-lg ${colors[level] || colors.info}`;
        ui.toast.classList.remove('hidden');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => ui.toast.classList.add('hidden'), 4000);
    }
}

function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => console.warn('Service worker non enregistré', error));
}

function today() {
    const d = new Date();

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function formatDate(value) {
    if (!value) return '—';
    const [y, m, d] = String(value).slice(0, 10).split('-');

    return `${d}/${m}/${y}`;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
