// Port of components/CustomerRecoveryModal.tsx. Rendered inside a page whose scope provides
// `isRecoveryOpen` (isOpen), `isRecoveryOpen = false` (onClose) and `onRecoveryRestored()` (onRestoreSuccess).
import Alpine from 'alpinejs';
import api from './api.js';
import { toast } from './toast.js';

Alpine.data('customerRecoveryModal', () => ({
    customers: [],
    loading: false,
    search: '',
    page: 1,
    totalPages: 1,
    totalItems: 0,

    init() {
        this.$watch('isRecoveryOpen', (open) => {
            if (open) {
                this.fetchDeletedCustomers(1, '');
                this.page = 1;
                this.search = '';
            }
        });
    },

    async fetchDeletedCustomers(currentPage = this.page, searchQuery = this.search) {
        this.loading = true;
        try {
            const response = await api.get('/admin/labs/customers', { params: { page: currentPage, limit: 10, search: searchQuery, status: 'inactive' } });
            if (response.data.data) {
                this.customers = response.data.data;
                this.totalPages = response.data.pages || 1;
                this.totalItems = response.data.total || 0;
            }
        } catch (err) {
            console.error('Failed to load deleted customers', err);
            toast.error('Failed to load recovery registry');
        } finally {
            this.loading = false;
        }
    },

    onSearch() {
        this.page = 1;
        this.fetchDeletedCustomers(1, this.search);
    },

    async handleRestore(id) {
        try {
            await api.patch(`/admin/labs/customers/${id}/restore`);
            toast.success('Customer restored successfully');
            this.fetchDeletedCustomers();
            this.onRecoveryRestored();
        } catch (err) {
            toast.error('Failed to restore customer');
        }
    },

    async handlePermanentDelete(id) {
        if (!confirm('Are you sure you want to PERMANENTLY delete this customer? This action cannot be undone and will only work if there are no associated orders.')) return;
        try {
            await api.delete(`/admin/labs/customers/${id}/permanent`);
            toast.success('Customer permanently deleted');
            this.fetchDeletedCustomers();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to permanently delete customer');
        }
    },

    goTo(newPage) {
        this.page = newPage;
        this.fetchDeletedCustomers(newPage);
    },
}));
