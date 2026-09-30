// Port of app/(tenant)/[companySlug]/admin/vendors/new/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { router, params } from '../lib/router.js';

Alpine.data('vendorNew', () => ({
    categories: [],
    loading: false,
    categoriesLoading: true,
    formData: { name: '', email: '', phone: '', companyName: '', address: '', status: 'active', categoryId: '' },
    statusOptions: [
        { value: 'active', label: 'ACTIVE', class: 'focus:bg-emerald-500/10 focus:text-emerald-500 font-bold py-3' },
        { value: 'inactive', label: 'INACTIVE', class: 'focus:bg-red-500/10 focus:text-red-500 font-bold py-3' },
    ],

    async init() {
        try {
            const response = await api.get('/categories');
            this.categories = response.data;
        } catch (err) {
            toast.error('Failed to load categories');
        } finally {
            this.categoriesLoading = false;
        }
    },

    get categoryOptions() {
        return this.categories.map((c) => ({ value: c.id.toString(), label: c.name.toUpperCase(), class: 'focus:bg-primary/10 focus:text-primary font-bold py-3' }));
    },

    validateForm() {
        if (!this.formData.name.trim()) { toast.error('Vendor name is required'); return false; }
        if (!this.formData.email.trim()) { toast.error('Email is required'); return false; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.formData.email)) { toast.error('Please enter a valid email address'); return false; }
        if (!this.formData.categoryId) { toast.error('Please select a category'); return false; }
        return true;
    },

    async handleSubmit() {
        if (!this.validateForm()) return;
        this.loading = true;
        try {
            await api.post('/admin/vendors', { ...this.formData, categoryId: parseInt(this.formData.categoryId) });
            toast.success('Vendor Added!', { description: `${this.formData.name} has been successfully registered.` });
            router.push(`/${params().companySlug}/admin/vendors`);
        } catch (error) {
            toast.error('Operation Failed', { description: error.response?.data?.message || 'Failed to add vendor.' });
            if (error.response?.data?.errors) error.response.data.errors.forEach((err) => toast.error(err));
        } finally {
            this.loading = false;
        }
    },
}));
