// Port of app/(tenant)/[companySlug]/admin/products/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('productsPage', () => ({
    products: [],
    loading: true,
    categories: [],
    isRefreshing: false,
    currentPage: 1,
    totalPages: 1,
    totalItems: 0,
    itemsPerPage: 10,
    searchName: '',
    searchId: '',
    selectedCategory: 'all',
    minPrice: '',
    maxPrice: '',
    statusFilter: 'all',
    statusButtons: [
        { id: 'all', label: 'ALL ENTITIES', icon: 'layout-grid' },
        { id: 'active', label: 'LIVE / SYNCED', icon: 'zap' },
        { id: 'hidden', label: 'SHADOW / HIDDEN', icon: 'eye-off' },
    ],

    init() {
        this.fetchCategories();
        this.$watch("[searchName, selectedCategory, searchId, minPrice, maxPrice, statusFilter, currentPage, itemsPerPage].join('|')", () => this.fetchProducts());
        this.fetchProducts();
    },

    async fetchCategories() {
        try {
            const response = await api.get('/categories');
            this.categories = response.data;
        } catch (err) {
            console.error('Failed to load categories', err);
        }
    },

    async fetchProducts() {
        try {
            this.isRefreshing = true;
            const response = await api.get('/admin/products', {
                params: {
                    search: this.searchName || undefined,
                    category: this.selectedCategory === 'all' ? undefined : this.selectedCategory,
                    id: this.searchId || undefined,
                    minPrice: this.minPrice || undefined,
                    maxPrice: this.maxPrice || undefined,
                    status: this.statusFilter,
                    page: this.currentPage,
                    limit: this.itemsPerPage,
                },
            });
            this.products = response.data.data;
            this.totalItems = response.data.total;
            this.totalPages = response.data.pages;
        } catch (err) {
            console.error(err);
            toast.error('Global Catalog Sync Failed');
        } finally {
            this.loading = false;
            this.isRefreshing = false;
        }
    },

    async handleDelete(id) {
        if (!confirm('Confirm entity deletion protocol? This action lis irreversible.')) return;
        try {
            await api.delete(`/admin/products/${id}`);
            this.fetchProducts();
            toast.success('Product scrubbed from database');
        } catch (err) {
            console.error(err);
            toast.error('Scrub protocol failed');
        }
    },

    async handleToggleActive(id) {
        try {
            const response = await api.patch(`/admin/products/${id}/toggle-active`);
            this.products = this.products.map((p) => (p.id === id ? { ...p, isActive: response.data.isActive } : p));
            toast.success(response.data.isActive ? 'Entity visibility: ACTIVE' : 'Entity visibility: HIDDEN');
        } catch (err) {
            console.error(err);
            toast.error('Visibility toggle failed');
        }
    },

    handleClearFilters() {
        this.searchName = '';
        this.searchId = '';
        this.selectedCategory = 'all';
        this.minPrice = '';
        this.maxPrice = '';
        this.statusFilter = 'all';
        this.currentPage = 1;
        toast.info('Filters reset to default');
    },

    get isRefined() {
        return this.searchName || this.selectedCategory !== 'all' || this.searchId || this.minPrice || this.maxPrice || this.statusFilter !== 'all';
    },
    get categoryOptions() {
        const cls = 'font-bold text-[10px] tracking-widest uppercase py-3';
        return [{ value: 'all', label: 'ALL CLASSIFICATIONS', class: cls }, ...this.categories.map((c) => ({ value: c.id.toString(), label: c.name, class: cls }))];
    },
    get pageNumbers() {
        return Array.from({ length: Math.min(5, this.totalPages) }, (_, i) => {
            if (this.totalPages <= 5) return i + 1;
            if (this.currentPage <= 3) return i + 1;
            if (this.currentPage >= this.totalPages - 2) return this.totalPages - 4 + i;
            return this.currentPage - 2 + i;
        });
    },
}));
