// Alpine versions of the Radix/shadcn primitives (Select, Switch, Dialog). Markup: templates/_ui.html.twig
export default function registerUi(Alpine) {
    // x-react-value="expr": a React-style controlled value. Like ReactDOMInput, the DOM value is only
    // rewritten when it differs (loosely for number inputs), so half-typed input such as "2." survives.
    Alpine.directive('react-value', (el, { expression }, { effect, evaluateLater }) => {
        const get = evaluateLater(expression);
        effect(() => get((value) => {
            const v = value ?? '';
            if (el.type === 'number') {
                // eslint-disable-next-line eqeqeq
                if ((v === 0 && el.value === '') || el.value != v) el.value = String(v);
            } else if (el.value !== String(v)) {
                el.value = String(v);
            }
        }));
    });

    // <Select value onValueChange><SelectTrigger/><SelectContent position="popper">…</Select>
    // Parent binds with x-model (x-modelable="value"); options come from the parent scope.
    Alpine.data('uiSelect', () => ({
        open: false,
        value: '',
        side: 'bottom',
        pos: { top: 0, left: 0, width: 0, maxHeight: 384 },
        highlighted: -1,

        toggle() { this.open ? this.close() : this.show(); },
        show() {
            if (this.$refs.trigger.disabled) return;
            const r = this.$refs.trigger.getBoundingClientRect();
            const below = window.innerHeight - r.bottom - 10;
            const above = r.top - 10;
            this.side = below < 200 && above > below ? 'top' : 'bottom';
            this.pos = {
                top: this.side === 'bottom' ? r.bottom + 4 : null,
                bottom: this.side === 'top' ? window.innerHeight - r.top + 4 : null,
                left: r.left,
                width: r.width,
                maxHeight: Math.min(384, this.side === 'bottom' ? below : above),
            };
            this.open = true;
            this.$nextTick(() => {
                const items = this.items();
                const i = items.findIndex((el) => el.dataset.value === String(this.value));
                this.focusItem(i >= 0 ? i : 0);
            });
        },
        close(refocus = true) {
            this.open = false;
            if (refocus) this.$refs.trigger.focus();
        },
        choose(v) {
            this.value = v;
            this.close();
            this.$dispatch('ui-change', v); // onValueChange
        },
        items() { return this.$refs.content ? [...this.$refs.content.querySelectorAll('[role=option]:not([data-disabled])')] : []; },
        focusItem(i) {
            const items = this.items();
            if (!items.length) return;
            this.highlighted = Math.max(0, Math.min(i, items.length - 1));
            items[this.highlighted].focus({ preventScroll: false });
        },
        onKey(e) {
            const items = this.items();
            const current = items.indexOf(document.activeElement);
            if (e.key === 'ArrowDown') { e.preventDefault(); this.focusItem(current + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); this.focusItem(current - 1); }
            else if (e.key === 'Home') { e.preventDefault(); this.focusItem(0); }
            else if (e.key === 'End') { e.preventDefault(); this.focusItem(items.length - 1); }
            else if (e.key === 'Escape' || e.key === 'Tab') { e.preventDefault(); this.close(); }
            else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (document.activeElement?.dataset.value !== undefined) this.choose(document.activeElement.dataset.value);
            } else if (e.key.length === 1) {
                const i = items.findIndex((el) => el.textContent.trim().toLowerCase().startsWith(e.key.toLowerCase()));
                if (i >= 0) this.focusItem(i);
            }
        },
        triggerKey(e) {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); this.show(); }
        },
    }));

    // components/ui/dialog.tsx: lock body scroll while open, Escape closes
    // (rendered inside <template x-if>, so init/destroy track open/close)
    Alpine.data('uiDialog', () => ({
        init() { document.body.style.overflow = 'hidden'; },
        destroy() { document.body.style.overflow = 'unset'; },
    }));
}
