// Port of app/(tenant)/[companySlug]/register/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { router, params } from '../lib/router.js';

Alpine.data('registerPage', () => ({
    formData: { name: '', email: '', password: '', confirmPassword: '' },
    isLoading: false,
    showPassword: false,
    showConfirmPassword: false,

    async handleSubmit() {
        const { companySlug } = params();
        toast.dismiss();

        const allowedDomains = ['gmail.com', 'outlook.com', 'yahoo.com'];
        const emailDomain = this.formData.email.split('@')[1];
        if (!allowedDomains.includes(emailDomain)) {
            toast.error('Invalid email domain', { description: 'Only Gmail, Outlook, and Yahoo emails are allowed.' });
            return;
        }
        if (this.formData.password !== this.formData.confirmPassword) {
            toast.error('Passwords do not match');
            return;
        }
        if (this.formData.password.length < 6) {
            toast.error('Password must be at least 6 characters');
            return;
        }

        this.isLoading = true;
        try {
            await api.post('/register', this.formData);
            toast.success('Account created successfully!', { description: 'Redirecting you to login page...' });
            setTimeout(() => router.push(`/${companySlug}/login`), 2000);
        } catch (err) {
            let errorMessage = 'Registration failed';
            if (err && typeof err === 'object' && 'response' in err) {
                errorMessage = err.response?.data?.message || errorMessage;
            } else if (err instanceof Error) {
                errorMessage = err.message;
            }
            toast.error('Registration failed', { description: errorMessage });
        } finally {
            this.isLoading = false;
        }
    },
}));
