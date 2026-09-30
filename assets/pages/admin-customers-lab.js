// Port of app/(tenant)/[companySlug]/admin/customers-lab/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { debounce } from '../lib/utils.js';
import '../lib/customer-recovery-modal.js';

const today = () => new Date().toISOString().split('T')[0];

Alpine.data('customersLab', () => ({
    customers: [],
    loading: true,
    downloading: false,
    search: '',
    pendingFilter: 'all',
    page: 1,
    totalPages: 1,
    totalItems: 0,
    isLedgerOpen: false,
    selectedCustomer: null,
    startDate: today(),
    endDate: today(),
    previewUrl: null,
    previewing: false,
    showHeader: true,
    isRecoveryOpen: false,
    filterOptions: [
        { value: 'all', label: 'All Clients' },
        { value: 'pending', label: 'Pending Balance' },
        { value: 'paid', label: 'No Pending Balance' },
    ],

    init() {
        this.debouncedSearch = debounce((query) => {
            this.page = 1;
            this.fetchCustomers(1, query, this.pendingFilter);
        }, 500);
        this.$watch('page', () => this.fetchCustomers(this.page, this.search, this.pendingFilter));
        this.fetchCustomers(this.page, this.search, this.pendingFilter);
    },

    async fetchCustomers(currentPage = this.page, searchQuery = this.search, pending = this.pendingFilter) {
        this.loading = true;
        try {
            const response = await api.get('/admin/labs/customers', {
                params: {
                    page: currentPage,
                    limit: 10,
                    search: searchQuery,
                    pending: pending === 'all' ? undefined : (pending === 'pending' ? 'true' : 'false'),
                },
            });
            if (response.data.data && Array.isArray(response.data.data)) {
                this.customers = response.data.data;
                this.totalPages = response.data.pages || 1;
                this.totalItems = response.data.total || 0;
            } else if (Array.isArray(response.data)) {
                this.customers = response.data;
            }
        } catch (err) {
            console.error('Failed to load customers', err);
            toast.error('Failed to load customer registry');
        } finally {
            this.loading = false;
        }
    },

    handleSearchChange() { this.debouncedSearch(this.search); },

    onFilterChange(val) {
        this.page = 1;
        this.fetchCustomers(1, this.search, val);
    },

    onRecoveryRestored() { this.fetchCustomers(this.page, this.search, this.pendingFilter); },

    async handleDelete(id) {
        if (!confirm('Are you sure you want to delete this customer record?')) return;
        try {
            await api.delete(`/admin/labs/customers/${id}`);
            toast.success('Customer record deleted');
            this.fetchCustomers(this.page, this.search, this.pendingFilter);
        } catch (err) {
            toast.error('Failed to delete customer');
        }
    },

    ledgerParams() {
        return { params: { period: 'custom', startDate: this.startDate, endDate: this.endDate, showHeader: this.showHeader }, responseType: 'blob' };
    },

    async handleDownloadLedger() {
        if (!this.selectedCustomer) return;
        this.downloading = true;
        try {
            const response = await api.get(`/admin/labs/invoice/customer/${this.selectedCustomer.id}`, this.ledgerParams());
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Ledger-${this.selectedCustomer.name}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            this.isLedgerOpen = false;
            toast.success('Ledger downloaded successfully');
        } catch (err) {
            console.error('Ledger error', err);
            toast.error('Failed to generate ledger');
        } finally {
            this.downloading = false;
        }
    },

    async handlePreviewLedger() {
        if (!this.selectedCustomer) return;
        this.previewing = true;
        try {
            const response = await api.get(`/admin/labs/invoice/customer/${this.selectedCustomer.id}`, this.ledgerParams());
            this.previewUrl = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        } catch (err) {
            console.error('Preview error', err);
            toast.error('Failed to generate preview');
        } finally {
            this.previewing = false;
        }
    },

    downloadPreview() {
        const link = document.createElement('a');
        link.href = this.previewUrl;
        link.setAttribute('download', `Ledger-${this.selectedCustomer?.name}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
    },

    closePreview() {
        URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    },
}));
