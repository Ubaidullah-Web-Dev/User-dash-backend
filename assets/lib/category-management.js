// Port of components/CategoryManagement.tsx (embedded at the bottom of the reagents page)
import Alpine from 'alpinejs';
import api from './api.js';
import { toast } from './toast.js';

Alpine.data('categoryManagement', () => ({
    categories: [],
    vendors: [],
    loading: true,
    isFormOpen: false,
    editingCategory: null,
    formName: '',
    formVendorId: 'none',
    submitting: false,
    page: 1,
    totalPages: 1,
    totalItems: 0,
    searchTerm: '',
    isProductModalOpen: false,
    selectedCategoryForProducts: null,
    unassignedProducts: [],
    selectedProductIds: [],
    assigningProducts: false,

    init() {
        this.$watch('page', () => this.fetchData());
        this.fetchData();
        // useEffect([searchTerm]) — also runs once on mount
        const onSearch = () => {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                if (this.page === 1) this.fetchData(1);
                else this.page = 1;
            }, 500);
        };
        this.$watch('searchTerm', onSearch);
        onSearch();
    },

    async fetchData(targetPage = this.page) {
        this.loading = true;
        try {
            const [catRes, venRes] = await Promise.all([
                api.get('/admin/categories/stats', { params: { page: targetPage, limit: 10, search: this.searchTerm || undefined } }),
                api.get('/admin/vendors'),
            ]);
            this.categories = catRes.data.data;
            this.totalPages = catRes.data.pages || 1;
            this.totalItems = catRes.data.total || 0;
            this.vendors = venRes.data;
        } catch (err) {
            console.error('Failed to fetch category data', err);
            toast.error('Failed to load categories or vendors');
        } finally {
            this.loading = false;
        }
    },

    async handleSubmit() {
        if (!this.formName.trim()) {
            toast.error('Category name is required');
            return;
        }
        this.submitting = true;
        try {
            const payload = { name: this.formName, vendorId: this.formVendorId === 'none' ? null : parseInt(this.formVendorId) };
            if (this.editingCategory) {
                await api.put(`/admin/categories/${this.editingCategory.id}`, payload);
                toast.success('Category updated successfully');
            } else {
                await api.post('/admin/categories', payload);
                toast.success('Category created successfully');
            }
            this.isFormOpen = false;
            this.editingCategory = null;
            this.formName = '';
            this.formVendorId = 'none';
            this.fetchData();
        } catch (err) {
            console.error(err);
            toast.error('Operation failed');
        } finally {
            this.submitting = false;
        }
    },

    async handleDelete(id) {
        if (!confirm('Are you sure? Categories with products cannot be deleted.')) return;
        try {
            await api.delete(`/admin/categories/${id}`);
            toast.success('Category deleted');
            this.fetchData();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Delete failed');
        }
    },

    openForm(cat = null) {
        if (cat) {
            this.editingCategory = cat;
            this.formName = cat.name;
            this.formVendorId = cat.vendor?.id.toString() || 'none';
        } else {
            this.editingCategory = null;
            this.formName = '';
            this.formVendorId = 'none';
        }
        this.isFormOpen = true;
    },

    async openProductAssignment(cat) {
        this.selectedCategoryForProducts = cat;
        this.isProductModalOpen = true;
        this.selectedProductIds = [];
        try {
            const res = await api.get('/admin/products', { params: { limit: 100 } });
            this.unassignedProducts = res.data.data.filter((p) => p.category?.id !== cat.id);
        } catch (err) {
            toast.error('Failed to load products');
        }
    },

    async handleAssignProducts() {
        if (!this.selectedCategoryForProducts || this.selectedProductIds.length === 0) return;
        this.assigningProducts = true;
        try {
            await api.patch(`/admin/categories/${this.selectedCategoryForProducts.id}/products`, { productIds: this.selectedProductIds });
            toast.success('Products assigned successfully');
            this.isProductModalOpen = false;
            this.fetchData();
        } catch (err) {
            toast.error('Assignment failed');
        } finally {
            this.assigningProducts = false;
        }
    },

    toggleProductSelection(id) {
        this.selectedProductIds = this.selectedProductIds.includes(id)
            ? this.selectedProductIds.filter((p) => p !== id)
            : [...this.selectedProductIds, id];
    },

    get vendorOptions() {
        return [{ value: 'none', label: 'None (No link)' }, ...this.vendors.map((v) => ({ value: v.id.toString(), label: v.name }))];
    },
    // pages 1, last, and current±1, with "..." gaps
    get pageItems() {
        const pages = Array.from({ length: this.totalPages }, (_, i) => i + 1)
            .filter((p) => p === 1 || p === this.totalPages || Math.abs(p - this.page) <= 1);
        return pages.map((p, i, arr) => ({ p, gap: i > 0 && arr[i - 1] !== p - 1 }));
    },
}));
