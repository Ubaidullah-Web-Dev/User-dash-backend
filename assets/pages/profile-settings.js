// Port of app/(tenant)/[companySlug]/profile/settings/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { auth } from '../lib/auth.js';
import { router } from '../lib/router.js';

Alpine.data('profileSettingsPage', () => ({
    user: auth.user,
    loading: false,
    formData: { name: '', password: '', confirmPassword: '' },

    init() {
        if (!auth.isAuthenticated) {
            router.push('/login');
        } else if (this.user) {
            this.formData.name = this.user.name || '';
        }
    },

    async handleSubmit() {
        if (this.formData.password && this.formData.password !== this.formData.confirmPassword) {
            toast.error('Passwords do not match');
            return;
        }
        this.loading = true;
        try {
            await api.put('/profile/update', { name: this.formData.name, password: this.formData.password });
            toast.success('Profile updated successfully', { description: 'Your changes have been saved.' });
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Something went wrong.';
            toast.error('Update failed', { description: message });
        } finally {
            this.loading = false;
        }
    },

    logout() { auth.logout(); },
}));
