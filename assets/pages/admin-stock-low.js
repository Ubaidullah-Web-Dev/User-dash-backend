// Port of app/(tenant)/[companySlug]/admin/stock/low/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('lowStockPage', () => ({
    lowStockItems: [],
    loading: true,

    async init() {
        try {
            const response = await api.get('/admin/labs/reports');
            this.lowStockItems = response.data.lowStock || [];
        } catch (err) {
            console.error('Failed to load low stock items', err);
            toast.error('Failed to load low stock data');
        } finally {
            this.loading = false;
        }
    },
}));
