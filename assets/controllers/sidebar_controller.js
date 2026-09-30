import { Controller } from '@hotwired/stimulus';

// Port of the isSidebarOpen / isCompact state in admin/layout.tsx. React kept this state alive
// across client-side navigations; here it is stored in a cookie so the server renders the same
// width on the next page (no flicker).
export default class extends Controller {
    static targets = ['aside', 'content', 'compactOnly', 'expandedOnly', 'openButton', 'item'];
    static values = { compact: Boolean, open: { type: Boolean, default: true }, cookie: { type: String, default: 'sidebar_compact' } };

    toggleCompact() {
        this.compactValue = !this.compactValue;
        document.cookie = `${this.cookieValue}=${this.compactValue ? 1 : 0}; path=/; max-age=31536000; SameSite=Lax`;
    }

    open() { this.openValue = true; }
    close() { this.openValue = false; }

    compactValueChanged(compact) {
        this.asideTarget.classList.toggle('w-20', compact);
        this.asideTarget.classList.toggle('w-72', !compact);
        this.contentTarget.classList.toggle('lg:pl-20', compact);
        this.contentTarget.classList.toggle('lg:pl-72', !compact);
        this.compactOnlyTargets.forEach((el) => (el.hidden = !compact));
        this.expandedOnlyTargets.forEach((el) => (el.hidden = compact));
        this.element.querySelectorAll('[data-compact-class]').forEach((el) => {
            const split = (c) => (c || '').split(' ').filter(Boolean);
            el.classList.remove(...split(compact ? el.dataset.expandedClass : el.dataset.compactClass));
            el.classList.add(...split(compact ? el.dataset.compactClass : el.dataset.expandedClass));
        });
        this.itemTargets.forEach((el) => (compact ? el.setAttribute('title', el.dataset.label) : el.removeAttribute('title')));
    }

    openValueChanged(open) {
        this.asideTarget.classList.toggle('translate-x-0', open);
        this.asideTarget.classList.toggle('-translate-x-full', !open);
        this.openButtonTarget.classList.toggle('hidden', open);
    }
}
