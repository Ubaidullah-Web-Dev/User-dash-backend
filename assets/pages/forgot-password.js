// Port of app/(tenant)/[companySlug]/forgot-password/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { router, params } from '../lib/router.js';

Alpine.data('forgotPasswordPage', () => ({
    step: 'EMAIL', // 'EMAIL' | 'OTP' | 'PASSWORD'
    email: '',
    code: '',
    password: '',
    confirmPassword: '',
    isLoading: false,

    async handleRequestCode() {
        this.isLoading = true;
        try {
            await api.post('/forgot-password', { email: this.email });
            toast.success('Code Sent', { description: 'Check your email for the 6-digit code.' });
            this.step = 'OTP';
        } catch (err) {
            if (err.response?.status === 404) {
                toast.error('Account Not Found', { description: 'No account exists for this email. Redirecting to register...' });
                setTimeout(() => router.push(`/${params().companySlug}/register`), 2500);
            } else {
                toast.error('Request Failed', { description: err.response?.data?.message || 'Something went wrong' });
            }
        } finally {
            this.isLoading = false;
        }
    },

    async handleVerifyCode() {
        this.isLoading = true;
        try {
            await api.post('/verify-code', { email: this.email, code: this.code });
            toast.success('Code Verified', { description: 'Now set your new password.' });
            this.step = 'PASSWORD';
        } catch (err) {
            const message = err.response?.data?.message || 'The code you entered is invalid or expired.';
            toast.error('Invalid Code', { description: message });
        } finally {
            this.isLoading = false;
        }
    },

    async handleResetPassword() {
        if (this.password !== this.confirmPassword) {
            toast.error('Validation Error', { description: 'Passwords do not match.' });
            return;
        }
        this.isLoading = true;
        try {
            await api.post('/reset-password', { email: this.email, code: this.code, password: this.password });
            toast.success('Success!', { description: 'Your password has been reset successfully.' });
            window.location.href = `/${params().companySlug}/login`;
        } catch (err) {
            const message = err.response?.data?.message || 'Something went wrong';
            toast.error('Reset Failed', { description: message });
        } finally {
            this.isLoading = false;
        }
    },
}));
