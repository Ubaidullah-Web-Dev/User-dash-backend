// Replacements for the three framer-motion components (MagneticButton, SpotlightCard, AnimatedCounter),
// exposed as Alpine directives: x-magnetic, x-spotlight, x-counter="value".

/** Damped spring (framer-motion "spring" physics: stiffness, damping, mass), animated with rAF. */
export function createSpring({ stiffness = 100, damping = 10, mass = 1, restDelta = 0.01, restSpeed = 0.01 }, initial, onUpdate) {
    let value = initial;
    let velocity = 0;
    let target = initial;
    let frame = null;
    let last = 0;

    const step = (now) => {
        let dt = Math.min((now - last) / 1000, 0.064);
        last = now;
        // integrate in small sub-steps for stability with stiff springs / light masses
        const sub = 8;
        const h = dt / sub;
        for (let i = 0; i < sub; i++) {
            const force = -stiffness * (value - target) - damping * velocity;
            velocity += (force / mass) * h;
            value += velocity * h;
        }
        if (Math.abs(velocity) < restSpeed && Math.abs(value - target) < restDelta) {
            value = target;
            velocity = 0;
            onUpdate(value);
            frame = null;
            return;
        }
        onUpdate(value);
        frame = requestAnimationFrame(step);
    };

    return {
        set(next) {
            target = next;
            if (frame === null) {
                last = performance.now();
                frame = requestAnimationFrame(step);
            }
        },
        stop() { if (frame !== null) cancelAnimationFrame(frame); frame = null; },
    };
}

export default function registerMotion(Alpine) {
    // <MagneticButton>: follows the pointer by 35%, spring { stiffness: 150, damping: 15, mass: 0.1 }
    Alpine.directive('magnetic', (el, _, { cleanup }) => {
        const pos = { x: 0, y: 0 };
        const render = () => {
            el.style.transform = pos.x === 0 && pos.y === 0 ? 'none' : `translateX(${pos.x}px) translateY(${pos.y}px)`;
        };
        const opts = { stiffness: 150, damping: 15, mass: 0.1 };
        const sx = createSpring(opts, 0, (v) => { pos.x = v; render(); });
        const sy = createSpring(opts, 0, (v) => { pos.y = v; render(); });
        const move = (e) => {
            const r = el.getBoundingClientRect();
            sx.set((e.clientX - (r.left + r.width / 2)) * 0.35);
            sy.set((e.clientY - (r.top + r.height / 2)) * 0.35);
        };
        const reset = () => { sx.set(0); sy.set(0); };
        el.addEventListener('mousemove', move);
        el.addEventListener('mouseleave', reset);
        cleanup(() => { sx.stop(); sy.stop(); el.removeEventListener('mousemove', move); el.removeEventListener('mouseleave', reset); });
    });

    // <SpotlightCard>: radial highlight that follows the pointer (overlay child has [data-spotlight])
    Alpine.directive('spotlight', (el, _, { cleanup }) => {
        const overlay = el.querySelector('[data-spotlight]');
        const paint = (x, y) => {
            overlay.style.background = `radial-gradient(
                          650px circle at ${x}px ${y}px,
                          rgba(var(--primary-rgb, 99, 102, 241), 0.15),
                          transparent 80%
                        )`;
        };
        paint(0, 0);
        const move = (e) => {
            const r = el.getBoundingClientRect();
            paint(e.clientX - r.left, e.clientY - r.top);
        };
        el.addEventListener('mousemove', move);
        cleanup(() => el.removeEventListener('mousemove', move));
    });

    // <AnimatedCounter value={n} />: empty until scrolled into view (margin -100px, once), then springs
    // from 0 to n ({ damping: 50, stiffness: 100 }) and shows Intl.NumberFormat("en-US") of toFixed(0).
    Alpine.directive('counter', (el, { expression }, { evaluate, cleanup }) => {
        const value = Number(evaluate(expression));
        const format = (v) => Intl.NumberFormat('en-US').format(Number(v.toFixed(0)));
        let started = false;
        const spring = createSpring({ damping: 50, stiffness: 100, mass: 1 }, 0, (v) => { el.textContent = format(v); });
        const observer = new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting) && !started) {
                started = true;
                spring.set(value);
                observer.disconnect();
            }
        }, { rootMargin: '-100px' });
        observer.observe(el);
        cleanup(() => { observer.disconnect(); spring.stop(); });
    });
}
