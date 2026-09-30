// Port of app/(tenant)/[companySlug]/admin/cash-recovery/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('cashRecovery', () => ({
    loading: true,
    customers: [],
    searchQuery: '',
    page: 1,
    totalPages: 1,
    showBranding: true,
    selectedCustomer: null,
    recoveryAmount: '',
    remarks: '',
    isProcessing: false,
    successData: null,
    previewUrl: null,
    previewing: false,

    init() {
        this.$watch("searchQuery + '|' + page", () => this.fetchCustomers());
        this.fetchCustomers();
    },

    async fetchCustomers() {
        this.loading = true;
        try {
            const response = await api.get('/admin/cash-recovery/customers', { params: { search: this.searchQuery, page: this.page, limit: 10, pending: 'true' } });
            this.customers = response.data.data;
            this.totalPages = response.data.totalPages;
        } catch (error) {
            console.error('Failed to fetch customers', error);
            toast.error(error.response?.data?.message || 'Failed to load customer data');
        } finally {
            this.loading = false;
        }
    },

    openRecovery(customer) {
        this.selectedCustomer = customer;
        this.successData = null;
        this.recoveryAmount = '';
        this.remarks = '';
    },

    async handleRecovery() {
        if (!this.selectedCustomer || !this.recoveryAmount) return;
        const amount = parseFloat(this.recoveryAmount);
        if (isNaN(amount) || amount <= 0) { toast.error('Please enter a valid amount'); return; }
        if (amount > this.selectedCustomer.remainingBalance) {
            toast.error(`Amount cannot exceed the pending balance of PKR ${this.selectedCustomer.remainingBalance}`);
            return;
        }
        this.isProcessing = true;
        try {
            const response = await api.post('/admin/cash-recovery/pay', { customerId: this.selectedCustomer.id, amount, remarks: this.remarks });
            this.successData = { recoveryId: response.data.recoveryId, amount };
            toast.success('Payment recorded successfully');
            this.fetchCustomers();
        } catch (error) {
            console.error('Payment failed', error);
            toast.error('Failed to record payment');
        } finally {
            this.isProcessing = false;
        }
    },

    async handleDownload(id) {
        try {
            toast.info('Downloading payment receipt...');
            const response = await api.get(`/admin/labs/invoice/recovery/${id}`, { params: { showHeader: this.showBranding }, responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Receipt-RECOV-${id}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.parentNode?.removeChild(link);
            window.URL.revokeObjectURL(url);
        } catch (err) {
            toast.error('Download failed');
        }
    },

    async handlePreview(id) {
        this.previewing = true;
        try {
            toast.info('Generating receipt preview...');
            const response = await api.get(`/admin/labs/invoice/recovery/${id}`, { params: { showHeader: this.showBranding, preview: true }, responseType: 'blob' });
            this.previewUrl = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        } catch (err) {
            toast.error('Preview failed');
        } finally {
            this.previewing = false;
        }
    },

    downloadPreview() {
        const link = document.createElement('a');
        link.href = this.previewUrl;
        link.setAttribute('download', 'Receipt-Preview.pdf');
        document.body.appendChild(link);
        link.click();
        link.remove();
    },

    closePreview() {
        URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    },

    get totalPending() { return this.customers.reduce((acc, c) => acc + c.remainingBalance, 0); },
    get pages() { return Array.from({ length: this.totalPages }, (_, i) => i + 1); },
}));
