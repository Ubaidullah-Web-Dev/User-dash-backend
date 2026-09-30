// next/navigation replacement (full page loads; toasts survive via the toaster's session handoff)
export const router = {
    push: (url) => { window.location.href = url; },
    replace: (url) => { window.location.replace(url); },
    back: () => window.history.back(),
    refresh: () => window.location.reload(),
};

export function useSearchParams() {
    return new URLSearchParams(window.location.search);
}

/** Route params, e.g. params().companySlug — provided by the server via window.__APP__.params */
export function params() {
    return (window.__APP__ && window.__APP__.params) || {};
}
export default router;
