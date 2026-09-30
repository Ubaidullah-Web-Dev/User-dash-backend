// Port of app/(tenant)/[companySlug]/admin/categories/[id]/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { router, params } from '../lib/router.js';

Alpine.data('categoryDetail', () => ({
    id: params().id,
    companySlug: params().companySlug,
    category: null,
    loading: true,
    isProcessing: false,
    editName: '',
    isEditingName: false,
    assignedProducts: [],
    assignedSearch: '',
    assignedAppliedSearch: '',
    assignedPage: 1,
    assignedTotalPages: 1,
    isAssignedLoading: false,
    isAddModalOpen: false,
    availableProducts: [],
    availableSearch: '',
    availableAppliedSearch: '',
    availablePage: 1,
    availableTotalPages: 1,
    isAvailableLoading: false,
    selectedForAdd: [],

    async init() {
        this.$watch("assignedAppliedSearch + '|' + assignedPage", () => this.fetchAssignedProducts());
        this.$watch("isAddModalOpen + '|' + availableAppliedSearch + '|' + availablePage", () => this.fetchAvailableProducts());
        this.fetchAssignedProducts();
        this.loading = true;
        await this.fetchCategoryStats();
        this.loading = false;
    },

    async fetchCategoryStats() {
        try {
            const catStatsRes = await api.get('/admin/categories/stats');
            const categoriesData = catStatsRes.data.data || catStatsRes.data;
            const currentCat = categoriesData.find((c) => c.id === Number(this.id));
            if (!currentCat) {
                toast.error('Category node not found');
                router.push(`/${this.companySlug}/admin/categories`);
                return;
            }
            this.category = currentCat;
            this.editName = currentCat.name;
        } catch (err) {
            console.error('Failed to load category data', err);
            toast.error('Category data synchronization failure');
        }
    },

    async fetchAssignedProducts() {
        try {
            this.isAssignedLoading = true;
            const response = await api.get('/admin/products', {
                params: { category: this.id, search: this.assignedAppliedSearch || undefined, page: this.assignedPage, limit: 6 },
            });
            this.assignedProducts = response.data.data;
            this.assignedTotalPages = response.data.pages;
        } catch (err) {
            console.error('Failed to load assigned products', err);
        } finally {
            this.isAssignedLoading = false;
        }
    },

    async fetchAvailableProducts() {
        if (!this.isAddModalOpen) return;
        try {
            this.isAvailableLoading = true;
            const response = await api.get('/admin/products', {
                params: { search: this.availableAppliedSearch || undefined, page: this.availablePage, limit: 10 },
            });
            this.availableProducts = response.data.data;
            this.availableTotalPages = response.data.pages;
        } catch (err) {
            console.error('Failed to load available products', err);
        } finally {
            this.isAvailableLoading = false;
        }
    },

    async handleUpdateName() {
        if (!this.editName.trim() || this.editName === this.category?.name) {
            this.isEditingName = false;
            return;
        }
        try {
            this.isProcessing = true;
            await api.put(`/admin/categories/${this.id}`, { name: this.editName });
            toast.success('Taxonomy node updated');
            this.fetchCategoryStats();
            this.isEditingName = false;
        } catch (err) {
            toast.error('Update failed');
        } finally {
            this.isProcessing = false;
        }
    },

    async handleUnlink(productId) {
        if (!confirm('Execute unlinking protocol?')) return;
        try {
            this.isProcessing = true;
            await api.delete(`/admin/categories/products/${productId}/unlink`);
            toast.success('Product scrubbed from this node');
            this.fetchCategoryStats();
            this.fetchAssignedProducts();
        } catch (err) {
            toast.error('Unlinking failed');
        } finally {
            this.isProcessing = false;
        }
    },

    async handleBulkAdd() {
        if (this.selectedForAdd.length === 0) return;
        try {
            this.isProcessing = true;
            await api.patch(`/admin/categories/${this.id}/products`, { productIds: this.selectedForAdd });
            toast.success(`Linked ${this.selectedForAdd.length} products`);
            this.fetchCategoryStats();
            this.fetchAssignedProducts();
            this.isAddModalOpen = false;
            this.selectedForAdd = [];
        } catch (err) {
            toast.error('Linkage failed');
        } finally {
            this.isProcessing = false;
        }
    },

    async handleDeleteCategory() {
        if ((this.category?.productCount || 0) > 0) {
            toast.error('Cannot destroy node with active linkages.');
            return;
        }
        if (!confirm('Permanently destroy this taxonomy node?')) return;
        try {
            this.isProcessing = true;
            await api.delete(`/admin/categories/${this.id}`);
            toast.success('Category node destroyed');
            router.push(`/${this.companySlug}/admin/categories`);
        } catch (err) {
            toast.error('Destruction failed');
        } finally {
            this.isProcessing = false;
        }
    },

    handleAssignedSearchSubmit() { this.assignedAppliedSearch = this.assignedSearch; this.assignedPage = 1; },
    handleAvailableSearchSubmit() { this.availableAppliedSearch = this.availableSearch; this.availablePage = 1; },

    isAlreadyAssigned(product) { return product.category?.id === Number(this.id); },
    toggleForAdd(product) {
        if (this.isAlreadyAssigned(product)) return;
        this.selectedForAdd = this.selectedForAdd.includes(product.id)
            ? this.selectedForAdd.filter((x) => x !== product.id)
            : [...this.selectedForAdd, product.id];
    },
    availableRowClass(product) {
        const assigned = this.isAlreadyAssigned(product);
        const selected = this.selectedForAdd.includes(product.id);
        return [
            assigned ? 'opacity-50 cursor-not-allowed bg-secondary/10 border-border/50' : 'cursor-pointer',
            selected ? 'bg-emerald-500/5 border-emerald-500 shadow-lg shadow-emerald-500/10' : (!assigned ? 'hover:bg-secondary/50 border-transparent hover:border-border' : ''),
        ].join(' ');
    },
    availableBadgeClass(product) {
        const assigned = this.isAlreadyAssigned(product);
        const selected = this.selectedForAdd.includes(product.id);
        return selected ? 'bg-emerald-500 text-white rotate-3' : (assigned ? 'bg-secondary text-muted-foreground' : 'bg-secondary text-muted-foreground group-hover:bg-emerald-500/10 group-hover:text-emerald-500');
    },
}));
