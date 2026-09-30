// Port of app/login/page.tsx and app/(tenant)/[companySlug]/login/page.tsx
import Alpine from 'alpinejs';
import { toast } from '../lib/toast.js';
import { useSearchParams } from '../lib/router.js';

Alpine.data('loginPage', ({ endpoint, csrfToken, tenant }) => ({
    email: '',
    password: '',
    rememberMe: false,
    isLoading: false,
    showPassword: false,

    async handleSubmit() {
        if (tenant) toast.dismiss();
        this.isLoading = true;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    email: this.email,
                    password: this.password,
                    remember_me: this.rememberMe,
                    redirectTo: useSearchParams().get('redirectTo') || undefined,
                    _csrf_token: csrfToken,
                }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(`Request failed with status code ${response.status}`);
                error.response = { status: response.status, data };
                throw error;
            }
            toast.success('Welcome back!', { description: 'Logging you in safely...' });
            window.location.href = data.redirect;
        } catch (err) {
            const errorMessage = err.response?.data?.message || 'Invalid credentials';
            toast.error('Authentication failed', { description: errorMessage });
        } finally {
            this.isLoading = false;
        }
    },
}));
