// Port of app/(tenant)/[companySlug]/admin/categories/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('adminCategories', () => ({
    categories: [],
    products: [],
    loading: true,
    isRefreshing: false,
    currentPage: 1,
    totalPages: 1,
    totalItems: 0,
    itemsPerPage: 9,
    isCreateModalOpen: false,
    categoryName: '',
    isProcessing: false,
    selectedProductIds: [],
    productSearch: '',

    init() {
        // useEffect(() => { fetchCategories(); fetchAllProducts(); }, [fetchCategories]) — reruns on page change
        this.fetchCategories();
        this.fetchAllProducts();
        this.$watch('currentPage', () => { this.fetchCategories(); this.fetchAllProducts(); });
    },

    async fetchCategories() {
        try {
            this.isRefreshing = true;
            const response = await api.get('/admin/categories/stats', { params: { page: this.currentPage, limit: this.itemsPerPage } });
            this.categories = response.data.data;
            this.totalItems = response.data.total;
            this.totalPages = response.data.pages;
        } catch (err) {
            console.error('Failed to load categories', err);
            toast.error('Failed to load categories');
        } finally {
            this.loading = false;
            this.isRefreshing = false;
        }
    },

    async fetchAllProducts() {
        try {
            const response = await api.get('/admin/products', { params: { limit: 100 } });
            this.products = response.data.data;
        } catch (err) {
            console.error('Failed to load products', err);
        }
    },

    async handleCreate() {
        if (!this.categoryName.trim()) return;
        try {
            this.isProcessing = true;
            await api.post('/admin/categories', { name: this.categoryName, productIds: this.selectedProductIds });
            toast.success(`Category "${this.categoryName}" registered successfully`);
            this.fetchCategories();
            this.isCreateModalOpen = false;
            this.resetForm();
        } catch (err) {
            toast.error('Creation failed');
        } finally {
            this.isProcessing = false;
        }
    },

    resetForm() {
        this.categoryName = '';
        this.selectedProductIds = [];
        this.productSearch = '';
    },

    toggleProduct(id) {
        this.selectedProductIds = this.selectedProductIds.includes(id)
            ? this.selectedProductIds.filter((x) => x !== id)
            : [...this.selectedProductIds, id];
    },

    get filteredProductsForCreation() {
        return this.products.filter((p) => p.name.toLowerCase().includes(this.productSearch.toLowerCase()));
    },
    get pages() { return Array.from({ length: this.totalPages }, (_, i) => i + 1); },
}));
