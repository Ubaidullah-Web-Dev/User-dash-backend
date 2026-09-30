import { Controller } from '@hotwired/stimulus';

// Port of components/ThemeToggle.tsx (next-themes + circular view-transition reveal).
export default class extends Controller {
    connect() {
        this.element.disabled = false; // mirrors the React "mounted" guard
    }

    toggle(event) {
        const root = document.documentElement;
        const isDark = root.classList.contains('dark');
        const newTheme = isDark ? 'light' : 'dark';
        const apply = () => {
            try { localStorage.setItem('theme', newTheme); } catch (e) {}
            root.classList.toggle('dark', newTheme === 'dark');
            root.style.colorScheme = newTheme;
        };

        if (!document.startViewTransition) {
            apply();
            return;
        }

        const x = event.clientX;
        const y = event.clientY;
        const endRadius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));
        root.style.setProperty('--x', `${x}px`);
        root.style.setProperty('--y', `${y}px`);

        const style = document.createElement('style');
        style.innerHTML = '* { transition: none !important; }';
        document.head.appendChild(style);

        const transition = document.startViewTransition(apply);
        transition.ready.then(() => {
            const clipPath = [`circle(0px at ${x}px ${y}px)`, `circle(${endRadius}px at ${x}px ${y}px)`];
            const animation = root.animate(
                { clipPath: isDark ? [...clipPath].reverse() : clipPath },
                {
                    duration: 1200,
                    easing: 'cubic-bezier(0.76, 0, 0.24, 1)',
                    pseudoElement: isDark ? '::view-transition-old(root)' : '::view-transition-new(root)',
                },
            );
            animation.onfinish = () => document.head.removeChild(style);
        }).catch(() => document.head.removeChild(style));
    }
}
