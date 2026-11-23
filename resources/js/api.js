import { reactive } from 'vue';
import { config } from './config';
import { showNotice } from './notice';

// Number of requests in flight, for the progress bar in the header.
export const activity = reactive({ pending: 0 });

const mutationListeners = [];

/** Called after any request that changes data, so cached pages are dropped. */
export function onMutation(listener) {
    mutationListeners.push(listener);
}

export class ValidationError extends Error {
    constructor(errors) {
        super('The form has errors.');
        this.errors = errors;
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/**
 * Calls the debugger API with the app's session and CSRF token. Shows the
 * notice an action returns, and turns known failures into notices too.
 */
export async function request(method, path, { query, body, signal, background = false } = {}) {
    const url = new URL(config.api + path, window.location.origin);

    Object.entries(query || {}).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, value);
    });

    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };

    let payload;
    if (body instanceof FormData) {
        payload = body;
    } else if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    // Background requests (prefetching) don't move the progress bar.
    if (!background) activity.pending++;

    if (method !== 'GET') mutationListeners.forEach((listener) => listener());

    try {
        const response = await fetch(url, { method, headers, body: payload, credentials: 'same-origin', signal });
        const data = await response.json().catch(() => ({}));

        if (response.status === 422) throw new ValidationError(data.errors || {});

        if (!response.ok) {
            const text = {
                401: 'You are signed out. Sign in to the app, then reload this page.',
                403: data.message || "You don't have access to this.",
                404: "That doesn't exist any more.",
                419: 'Your session expired. Reload the page and try again.',
            }[response.status] || `The server returned an error (${response.status}). Check the logs, then try again.`;

            // A failed prefetch stays quiet; the page will ask again if needed.
            if (!background) showNotice({ tone: 'danger', text });
            throw new Error(text);
        }

        if (data.notice) showNotice(data.notice);

        return data;
    } finally {
        if (!background) activity.pending--;
    }
}

export const api = {
    get: (path, query, options = {}) => request('GET', path, { query, ...options }),
    post: (path, body) => request('POST', path, { body }),
    delete: (path, body) => request('DELETE', path, { body }),
};
