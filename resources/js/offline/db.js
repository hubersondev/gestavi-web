/**
 * File d'attente locale des saisies terrain (IndexedDB « gestavi-offline », store « outbox »)
 * et cache des données de référence (store « cache »).
 *
 * Chaque saisie porte un client_uuid, un horodatage et un état : pending | synced | rejected | duplicate.
 */
const DB_NAME = 'gestavi-offline';
const DB_VERSION = 1;

function openDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const db = request.result;

            if (!db.objectStoreNames.contains('outbox')) {
                const outbox = db.createObjectStore('outbox', { keyPath: 'client_uuid' });
                outbox.createIndex('status', 'status', { unique: false });
                outbox.createIndex('organisation', 'organisation', { unique: false });
            }

            if (!db.objectStoreNames.contains('cache')) {
                db.createObjectStore('cache', { keyPath: 'key' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function tx(storeName, mode, work) {
    return openDb().then((db) => new Promise((resolve, reject) => {
        const transaction = db.transaction(storeName, mode);
        const store = transaction.objectStore(storeName);
        const result = work(store);

        transaction.oncomplete = () => resolve(result && 'result' in result ? result.result : result);
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    }));
}

function requestToPromise(request) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

export function uuid() {
    if (crypto.randomUUID) {
        return crypto.randomUUID();
    }

    // Repli pour les navigateurs sans randomUUID (Android WebView anciens)
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;

        return v.toString(16);
    });
}

export async function enqueue(organisation, type, payload) {
    const entry = {
        client_uuid: uuid(),
        organisation,
        type,
        payload,
        recorded_at: new Date().toISOString(),
        status: 'pending',
        message: null,
        attempts: 0,
    };

    await tx('outbox', 'readwrite', (store) => store.put(entry));

    return entry;
}

export async function pendingEntries(organisation) {
    const db = await openDb();

    return new Promise((resolve, reject) => {
        const store = db.transaction('outbox', 'readonly').objectStore('outbox');
        const request = store.index('organisation').getAll(organisation);

        request.onsuccess = () => resolve(request.result.filter((entry) => entry.status === 'pending'));
        request.onerror = () => reject(request.error);
    });
}

export async function allEntries(organisation) {
    const db = await openDb();

    return new Promise((resolve, reject) => {
        const store = db.transaction('outbox', 'readonly').objectStore('outbox');
        const request = store.index('organisation').getAll(organisation);

        request.onsuccess = () => resolve(request.result.sort((a, b) => (a.recorded_at < b.recorded_at ? 1 : -1)));
        request.onerror = () => reject(request.error);
    });
}

export async function updateEntry(clientUuid, changes) {
    const db = await openDb();
    const store = db.transaction('outbox', 'readwrite').objectStore('outbox');
    const current = await requestToPromise(store.get(clientUuid));

    if (!current) {
        return null;
    }

    const updated = { ...current, ...changes };
    await requestToPromise(store.put(updated));

    return updated;
}

export async function purgeSynced(organisation, olderThanDays = 7) {
    const limit = Date.now() - olderThanDays * 86400000;
    const entries = await allEntries(organisation);
    const db = await openDb();
    const store = db.transaction('outbox', 'readwrite').objectStore('outbox');

    for (const entry of entries) {
        if (entry.status !== 'pending' && new Date(entry.recorded_at).getTime() < limit) {
            store.delete(entry.client_uuid);
        }
    }
}

export async function cacheSet(key, value) {
    await tx('cache', 'readwrite', (store) => store.put({ key, value, cached_at: new Date().toISOString() }));
}

export async function cacheGet(key) {
    const db = await openDb();
    const store = db.transaction('cache', 'readonly').objectStore('cache');
    const row = await requestToPromise(store.get(key));

    return row ? row.value : null;
}
