import { Controller } from '@hotwired/stimulus';

// Emits the server-rendered flash messages of the current page to the (permanent) toaster.
// Deferred one frame: during a Turbo render the permanent toaster is briefly disconnected.
export default class extends Controller {
    connect() {
        const flashes = [...this.element.querySelectorAll('template[data-type]')].map((el) => {
            el.remove();
            return { ...el.dataset };
        });
        requestAnimationFrame(() => flashes.forEach((detail) => window.dispatchEvent(new CustomEvent('toast', { detail }))));
    }
}
