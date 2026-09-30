// Port of app/(tenant)/[companySlug]/admin/reagents/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import '../lib/category-management.js';
import '../lib/reagent-recovery-modal.js';

Alpine.data('reagentsPage', () => ({
    products: [],
    loading: true,
    categories: [],
    isRecoveryOpen: false,
    searchName: '',
    selectedCategory: 'all',
    currentPage: 1,
    totalPages: 1,
    totalItems: 0,
    limit: 15,

    init() {
        // useEffect(() => { fetchCategories(); fetchProducts(); }, [fetchProducts]) — fetchProducts changes with search/category/page
        this.$watch("searchName + '|' + selectedCategory + '|' + currentPage", () => { this.fetchCategories(); this.fetchProducts(); });
        this.fetchCategories();
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
        this.loading = true;
        try {
            const response = await api.get('/admin/products', {
                params: {
                    search: this.searchName || undefined,
                    category: this.selectedCategory === 'all' ? undefined : this.selectedCategory,
                    page: this.currentPage,
                    limit: this.limit,
                },
            });
            this.products = response.data.data;
            this.totalPages = response.data.pages;
            this.totalItems = response.data.total;
        } catch (err) {
            console.error(err);
            toast.error('Failed to load reagents');
        } finally {
            this.loading = false;
        }
    },

    async handleDelete(id) {
        if (!confirm('Are you sure you want to delete this reagent?')) return;
        try {
            await api.delete(`/admin/products/${id}`);
            this.fetchProducts();
            toast.success('Reagent deleted successfully');
        } catch (err) {
            console.error(err);
            toast.error('Failed to delete reagent');
        }
    },

    onRecoveryRestored() { this.fetchProducts(); },
    resetFilters() { this.searchName = ''; this.selectedCategory = 'all'; this.currentPage = 1; },
    isExpired(p) { return p.expiryDate && new Date(p.expiryDate) < new Date(); },
    get categoryOptions() {
        return [{ value: 'all', label: 'All Categories' }, ...this.categories.map((c) => ({ value: c.id.toString(), label: c.name }))];
    },
}));
