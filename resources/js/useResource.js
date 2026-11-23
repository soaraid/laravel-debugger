import { reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { api, onMutation } from './api';

/**
 * Page data, stale-while-revalidate:
 * - a page seen in the last minute shows instantly from memory, then is
 *   refreshed in the background if it is more than a few seconds old
 * - the next and previous pages are fetched ahead once the browser is
 *   idle, so Next and Previous are instant
 * - anything that changes data (POST/DELETE) empties the memory
 */

const FRESH_MS = 5000;
const KEEP_MS = 60000;
const MAX_ENTRIES = 60;

const cache = new Map();
const inflight = new Map();

// For the dimmed table while a page without cached data loads.
export const navigation = reactive({ loading: false });

onMutation(() => cache.clear());

function keyFor(path, query) {
    const params = new URLSearchParams();
    Object.keys(query).sort().forEach((k) => {
        if (query[k] !== undefined && query[k] !== null && query[k] !== '') params.set(k, query[k]);
    });
    return `${path}?${params}`;
}

function remember(key, data) {
    cache.delete(key);
    cache.set(key, { data, at: Date.now() });
    if (cache.size > MAX_ENTRIES) cache.delete(cache.keys().next().value);
}

function fetchOnce(path, query, key, background = false) {
    if (!inflight.has(key)) {
        inflight.set(key, api.get(path, query, { background })
            .then((data) => { remember(key, data); return data; })
            .finally(() => inflight.delete(key)));
    }
    return inflight.get(key);
}

const idle = window.requestIdleCallback || ((fn) => setTimeout(fn, 200));

export function useResource(path) {
    const route = useRoute();
    const data = ref(null);
    const failed = ref(false);
    let current = 0;

    // The pages next to this one, fetched once the browser is idle, so
    // Next and Previous show instantly.
    function prefetchNext(result, query) {
        const p = result?.pagination;
        if (!p) return;
        const page = p.page || 1;
        const around = [p.has_next && page + 1, p.has_previous && page - 1].filter(Boolean);

        around.forEach((n) => {
            const target = { ...query, page: n > 1 ? n : undefined };
            const key = keyFor(path, target);
            if (!cache.has(key)) idle(() => fetchOnce(path, target, key, true).catch(() => {}));
        });
    }

    async function load({ force = false } = {}) {
        const query = { ...route.query };
        const key = keyFor(path, query);
        const hit = cache.get(key);
        const request = ++current;

        if (hit && Date.now() - hit.at < KEEP_MS && !force) {
            data.value = hit.data;
            failed.value = false;
            prefetchNext(hit.data, query);
            if (Date.now() - hit.at < FRESH_MS) return;
        } else {
            navigation.loading = true;
        }

        try {
            const result = await fetchOnce(path, query, key);
            if (request !== current) return;
            data.value = result;
            failed.value = false;
            prefetchNext(result, query);
        } catch {
            if (request === current) failed.value = data.value === null;
        } finally {
            if (request === current) navigation.loading = false;
        }
    }

    watch(() => route.query, () => load(), { immediate: true });

    return { data, failed, reload: () => load({ force: true }) };
}
