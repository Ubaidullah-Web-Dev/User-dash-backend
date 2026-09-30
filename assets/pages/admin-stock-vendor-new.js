// Port of app/(tenant)/[companySlug]/admin/stock/vendors/new/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { router, params } from '../lib/router.js';

Alpine.data('stockVendorNew', () => ({
    loading: false,
    formData: { name: '', email: '', phone: '', companyName: '', address: '', status: 'active' },
    statusOptions: [
        { value: 'active', label: 'ACTIVE', class: 'focus:bg-emerald-500/10 focus:text-emerald-500 font-bold py-3' },
        { value: 'inactive', label: 'INACTIVE', class: 'focus:bg-red-500/10 focus:text-red-500 font-bold py-3' },
    ],

    validateForm() {
        if (!this.formData.name.trim()) { toast.error('Vendor name is required'); return false; }
        if (!this.formData.email.trim()) { toast.error('Email is required'); return false; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.formData.email)) { toast.error('Please enter a valid email address'); return false; }
        return true;
    },

    async handleSubmit() {
        if (!this.validateForm()) return;
        this.loading = true;
        try {
            await api.post('/admin/vendors', this.formData);
            toast.success('Vendor Added!', { description: `${this.formData.name} has been successfully registered.` });
            router.push(`/${params().companySlug}/admin/stock`);
        } catch (error) {
            const message = error.response?.data?.message || 'Failed to add vendor.';
            toast.error('Operation Failed', { description: message });
            if (error.response?.data?.errors) error.response.data.errors.forEach((err) => toast.error(err));
        } finally {
            this.loading = false;
        }
    },
}));
