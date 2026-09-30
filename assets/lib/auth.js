// Replacement for context/AuthContext.tsx. The user comes from the server session
// (rendered into window.__APP__ by base.html.twig), so there is no loading phase.
const app = window.__APP__ || { user: null };

export const KNOWN_SLUGS = ['unique-healthcare-solutions', 'acme', 'tesla', 'demo'];

export function getCompanySlug() {
    const segments = window.location.pathname.split('/').filter(Boolean);
    return KNOWN_SLUGS.find((slug) => segments.includes(slug));
}

export const auth = {
    user: app.user,
    isAuthenticated: !!app.user,
    loading: false,
    token: app.user ? 'session' : null,
    /** AuthContext.logout(): end the session, then go to /{slug}/login or /login */
    logout() {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = '/logout';
        const add = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value ?? '';
            form.appendChild(input);
        };
        add('_csrf_token', document.querySelector('meta[name="logout-token"]')?.content);
        add('companySlug', getCompanySlug());
        document.body.appendChild(form);
        form.submit();
    },
};
export default auth;
