// Port of app/(tenant)/[companySlug]/marketplace/page.tsx
import Alpine from 'alpinejs';
import api, { getImageUrl } from '../lib/api.js';
import { useSearchParams } from '../lib/router.js';

Alpine.data('marketplacePage', () => ({
    products: [],
    loading: true,
    search: useSearchParams().get('search'),
    category: useSearchParams().get('category'),
    getImageUrl,

    async init() {
        this.loading = true;
        try {
            const params = {};
            if (this.search) params.search = this.search;
            if (this.category) params.category = this.category;
            const response = await api.get('/products', { params });
            this.products = response.data;
        } catch (error) {
            console.error('Failed to fetch products', error);
        } finally {
            this.loading = false;
        }
    },

    get heading() {
        return this.search ? `Search results for "${this.search}"` : this.category ? `${this.category} Collection` : 'Explore All Products';
    },
}));
