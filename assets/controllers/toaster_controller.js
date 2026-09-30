import { Controller } from '@hotwired/stimulus';

const ICONS = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" height="20" width="20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>',
    info: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" height="20" width="20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>',
    warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" height="20" width="20"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/></svg>',
    error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" height="20" width="20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
};
const CLOSE = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
const DURATION = 4000; // sonner default
const HANDOFF_KEY = 'toaster:handoff';

// Replaces sonner's <Toaster />. See templates/partials/_toaster.html.twig and assets/lib/toast.js.
// Sonner lived in the root layout, so toasts survived client-side navigation; with full page loads the
// open toasts are handed to the next page through sessionStorage (with their remaining time).
export default class extends Controller {
    connect() {
        this.onToast = (e) => this.show(e.detail);
        this.onDismiss = () => [...this.element.querySelectorAll('.toast')].forEach((t) => this.remove(t));
        this.onPageHide = () => this.handOff();
        window.addEventListener('toast', this.onToast);
        window.addEventListener('toast:dismiss', this.onDismiss);
        window.addEventListener('pagehide', this.onPageHide);
        this.restore();
    }

    disconnect() {
        window.removeEventListener('toast', this.onToast);
        window.removeEventListener('toast:dismiss', this.onDismiss);
        window.removeEventListener('pagehide', this.onPageHide);
    }

    show({ type = 'normal', title = '', description = '' }, remaining = DURATION, animate = true) {
        const kind = ICONS[type] ? type : 'normal';
        const toast = document.createElement('div');
        toast.className = `toast toast-${kind}`;
        toast.setAttribute('role', 'status');
        toast.innerHTML = `
            <button type="button" class="toast-close" aria-label="Close toast">${CLOSE}</button>
            ${ICONS[kind] ? `<div class="toast-icon">${ICONS[kind]}</div>` : ''}
            <div class="toast-content"><div class="toast-title"></div>${description ? '<div class="toast-description"></div>' : ''}</div>`;
        toast.querySelector('.toast-title').textContent = title;
        if (description) toast.querySelector('.toast-description').textContent = description;
        toast.querySelector('.toast-close').addEventListener('click', () => this.remove(toast));
        toast.data = { type: kind, title, description };
        toast.expiresAt = Date.now() + remaining;
        this.element.prepend(toast);
        if (animate) requestAnimationFrame(() => requestAnimationFrame(() => toast.setAttribute('data-mounted', '')));
        else toast.setAttribute('data-mounted', '');
        toast.timer = setTimeout(() => this.remove(toast), remaining);
    }

    remove(toast) {
        clearTimeout(toast.timer);
        toast.setAttribute('data-removed', '');
        setTimeout(() => toast.remove(), 400);
    }

    handOff() {
        const open = [...this.element.querySelectorAll('.toast:not([data-removed])')]
            .map((t) => ({ ...t.data, remaining: t.expiresAt - Date.now() }))
            .filter((t) => t.remaining > 300);
        try { sessionStorage.setItem(HANDOFF_KEY, JSON.stringify({ at: Date.now(), toasts: open.reverse() })); } catch (e) {}
    }

    restore() {
        let saved;
        try { saved = JSON.parse(sessionStorage.getItem(HANDOFF_KEY) || 'null'); sessionStorage.removeItem(HANDOFF_KEY); } catch (e) {}
        if (!saved) return;
        const elapsed = Date.now() - saved.at;
        saved.toasts.forEach((t) => {
            const remaining = t.remaining - elapsed;
            if (remaining > 300) this.show(t, remaining, false);
        });
    }
}
