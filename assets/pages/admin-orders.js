// Port of app/(tenant)/[companySlug]/admin/orders/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('ordersPage', () => ({
    orders: [],
    loading: true,
    filter: '',
    productName: '',
    orderId: '',
    category: '',
    productId: '',
    page: 1,
    totalPages: 1,
    limit: 10,
    statuses: ['', 'pending', 'approved', 'received', 'cancelled'],

    init() {
        this.$watch('page', () => this.fetchOrders());
        this.fetchOrders();
    },

    async fetchOrdersWithParams(f = this.filter, pN = this.productName, oI = this.orderId, c = this.category, pI = this.productId, pg = this.page) {
        try {
            const query = new URLSearchParams({
                status: f,
                page: pg.toString(),
                limit: this.limit.toString(),
                ...(pN && { productName: pN }),
                ...(oI && { orderId: oI }),
                ...(c && { category: c }),
                ...(pI && { productId: pI }),
            });
            const response = await api.get(`/admin/vendor-orders?${query.toString()}`);
            this.orders = response.data.data;
            this.totalPages = response.data.pages;
        } catch (err) {
            toast.error('Failed to load supply orders');
        } finally {
            this.loading = false;
        }
    },

    fetchOrders() { return this.fetchOrdersWithParams(); },

    handleApplyFilters() {
        this.page = 1;
        this.fetchOrders();
    },

    handleClearFilters() {
        this.filter = '';
        this.productName = '';
        this.orderId = '';
        this.category = '';
        this.productId = '';
        this.page = 1;
        this.fetchOrdersWithParams('', '', '', '', '', 1);
    },

    async handleStatusUpdate(id, newStatus) {
        try {
            await api.patch(`/admin/vendor-orders/${id}/status`, { status: newStatus });
            toast.success(`Order marked as ${newStatus}`);
            this.fetchOrders();
        } catch (err) {
            toast.error('Failed to update status');
        }
    },

    async handleDownloadInvoice(id) {
        try {
            const response = await api.get(`/invoice/buyer/download?vendorOrderId=${id}`, { responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `invoice-VO-${id}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            toast.success('Invoice downloaded successfully');
        } catch (err) {
            toast.error('Failed to download invoice');
        }
    },

    getStatusColor(status) {
        switch (status) {
            case 'pending': return 'bg-amber-500/10 text-amber-500 border-amber-500/20';
            case 'approved': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
            case 'received': return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20';
            case 'cancelled': return 'bg-red-500/10 text-red-500 border-red-500/20';
            default: return 'bg-secondary text-muted-foreground';
        }
    },

    get pages() { return Array.from({ length: this.totalPages }, (_, i) => i + 1); },
}));
