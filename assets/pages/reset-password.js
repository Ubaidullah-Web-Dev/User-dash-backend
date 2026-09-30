// Port of app/(tenant)/[companySlug]/reset-password/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { router, params, useSearchParams } from '../lib/router.js';

Alpine.data('resetPasswordPage', () => ({
    password: '',
    confirmPassword: '',
    isLoading: false,
    token: useSearchParams().get('token'),

    init() {
        if (!this.token) {
            toast.error('Invalid Request', { description: 'Missing password reset token.' });
            router.push(`/${params().companySlug}/login`);
        }
    },

    async handleSubmit() {
        if (this.password !== this.confirmPassword) {
            toast.error('Validation Error', { description: 'Passwords do not match.' });
            return;
        }
        this.isLoading = true;
        try {
            await api.post('/reset-password', { token: this.token, password: this.password });
            toast.success('Password Updated', { description: 'Your password has been reset successfully.' });
            router.push(`/${params().companySlug}/login`);
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Invalid or expired token';
            toast.error('Reset Failed', { description: errorMessage });
        } finally {
            this.isLoading = false;
        }
    },
}));
