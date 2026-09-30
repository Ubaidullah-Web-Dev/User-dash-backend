// Replacement for frontend/lib/api.ts (axios). Same call signatures and error shape as axios:
//   api.get(url, { params, responseType }), api.post(url, data, config), ...
//   errors: err.message === 'Request failed with status code N', err.response = { status, data }
// Requests are same-origin and authenticated by the session cookie (+ CSRF header for writes).
const RESERVED = ['super-admin', 'login', 'register', 'api', '_next', 'static', 'favicon.ico', 'images', 'pdfs', 'tenants'];

function baseUrl(url) {
    const first = window.location.pathname.split('/').filter(Boolean)[0];
    const isGlobalUrl = url.startsWith('super-admin') || url.startsWith('/super-admin');
    return first && !RESERVED.includes(first) && !isGlobalUrl ? `/${first}/api/` : '/api/';
}

function buildQuery(params) {
    if (!params) return '';
    const search = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === null) return;
        if (Array.isArray(value)) value.forEach((v) => search.append(`${key}[]`, v));
        else search.append(key, value);
    });
    const qs = search.toString();
    return qs ? `?${qs}` : '';
}

async function request(method, url, data, config = {}) {
    const headers = {
        Accept: 'application/json, text/plain, */*',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        ...(config.headers || {}),
    };
    let body;
    if (data instanceof FormData) {
        body = data;
        delete headers['Content-Type'];
    } else if (data !== undefined) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(data);
    }

    const path = url.replace(/^\//, '');
    const response = await fetch(baseUrl(url) + path + buildQuery(config.params), {
        method, headers, body, credentials: 'same-origin',
    });

    let payload;
    if (config.responseType === 'blob' && response.ok) {
        payload = await response.blob();
    } else {
        const text = await response.text();
        try { payload = text ? JSON.parse(text) : ''; } catch { payload = text; }
    }

    const result = { data: payload, status: response.status, headers: response.headers };
    if (!response.ok) {
        const error = new Error(`Request failed with status code ${response.status}`);
        error.response = result;
        error.isAxiosError = true;
        throw error;
    }
    return result;
}

export const api = {
    get: (url, config) => request('GET', url, undefined, config),
    delete: (url, config) => request('DELETE', url, undefined, config),
    post: (url, data, config) => request('POST', url, data, config),
    put: (url, data, config) => request('PUT', url, data, config),
    patch: (url, data, config) => request('PATCH', url, data, config),
};

/** Port of getImageUrl() from frontend/lib/api.ts (backend is same-origin now). */
export const getImageUrl = (url) => {
    if (!url) return 'https://images.unsplash.com/photo-1526733170371-33157ae37812?q=80&w=600&auto=format&fit=crop';
    if (url.startsWith('http://') || url.startsWith('https://')) return url;
    return '/' + (url.startsWith('/') ? url.slice(1) : url);
};

export default api;
