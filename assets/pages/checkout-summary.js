// Port of app/(tenant)/[companySlug]/checkout/summary/[id]/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { auth } from '../lib/auth.js';
import { router, params } from '../lib/router.js';

Alpine.data('checkoutSummaryPage', () => ({
    loading: true,
    order: null,
    user: auth.user,

    async init() {
        const { companySlug, id } = params();
        if (!auth.isAuthenticated) {
            router.push(`/${companySlug}/login`);
            return;
        }
        if (!id) return;
        try {
            const response = await api.get(`/orders/${id}`);
            this.order = response.data;
        } catch (err) {
            console.error(err);
            toast.error('Failed to load order details');
        } finally {
            this.loading = false;
        }
    },

    get subtotal() { return this.order.total; },
    get shipping() { return this.subtotal > 500 ? 0 : 25; },
    get total() { return this.subtotal + this.shipping; },

    async handleDownloadInvoice() {
        try {
            if (!this.order) return;
            const response = await api.get(`/invoice/download?orderId=${params().id}`, { responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `invoice-${this.order.id}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.parentNode?.removeChild(link);
        } catch (err) {
            console.error('Failed to download invoice', err);
            toast.error('Download failed');
        }
    },
}));
