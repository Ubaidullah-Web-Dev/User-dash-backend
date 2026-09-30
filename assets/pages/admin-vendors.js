// Port of app/(tenant)/[companySlug]/admin/vendors/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('vendorsPage', () => ({
    categories: [],
    vendors: [],
    selectedCategory: null,
    loading: true,
    vendorsLoading: false,

    async init() {
        this.$watch('selectedCategory', () => this.fetchVendors());
        try {
            const response = await api.get('/categories');
            this.categories = response.data;
        } catch (err) {
            toast.error('Failed to load categories');
        } finally {
            this.loading = false;
        }
    },

    async fetchVendors() {
        if (!this.selectedCategory) return;
        this.vendorsLoading = true;
        try {
            const response = await api.get(`/admin/vendors?category=${this.selectedCategory.id}`);
            this.vendors = response.data;
        } catch (err) {
            toast.error('Failed to load vendors');
        } finally {
            this.vendorsLoading = false;
        }
    },
}));
