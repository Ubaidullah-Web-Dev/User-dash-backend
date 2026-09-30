// Port of components/ReagentRecoveryModal.tsx. Rendered inside a page whose scope provides
// `isRecoveryOpen` (isOpen), `isRecoveryOpen = false` (onClose) and `onRecoveryRestored()` (onRestoreSuccess).
import Alpine from 'alpinejs';
import api from './api.js';
import { toast } from './toast.js';

Alpine.data('reagentRecoveryModal', () => ({
    products: [],
    loading: false,
    search: '',
    page: 1,
    totalPages: 1,
    totalItems: 0,

    init() {
        this.$watch('isRecoveryOpen', (open) => {
            if (open) {
                this.fetchDeletedProducts(1, '');
                this.page = 1;
                this.search = '';
            }
        });
    },

    async fetchDeletedProducts(currentPage = this.page, searchQuery = this.search) {
        this.loading = true;
        try {
            const response = await api.get('/admin/products', { params: { page: currentPage, limit: 10, search: searchQuery, status: 'inactive' } });
            if (response.data.data) {
                this.products = response.data.data;
                this.totalPages = response.data.pages || 1;
                this.totalItems = response.data.total || 0;
            }
        } catch (err) {
            console.error('Failed to load deleted reagents', err);
            toast.error('Failed to load recovery registry');
        } finally {
            this.loading = false;
        }
    },

    onSearch() {
        this.page = 1;
        this.fetchDeletedProducts(1, this.search);
    },

    async handleRestore(id) {
        try {
            await api.patch(`/admin/products/${id}/restore`);
            toast.success('Reagent restored successfully');
            this.fetchDeletedProducts();
            this.onRecoveryRestored();
        } catch (err) {
            toast.error('Failed to restore reagent');
        }
    },

    async handlePermanentDelete(id) {
        if (!confirm('Are you sure you want to PERMANENTLY delete this reagent? This action cannot be undone and will only work if there are no associated sales.')) return;
        try {
            await api.delete(`/admin/products/${id}/permanent`);
            toast.success('Reagent permanently deleted');
            this.fetchDeletedProducts();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to permanently delete reagent');
        }
    },

    goTo(newPage) {
        this.page = newPage;
        this.fetchDeletedProducts(newPage);
    },
}));
